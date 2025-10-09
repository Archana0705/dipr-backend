<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
require_once('../helper/header.php');
require_once('../helper/db/dipr_read.php');
header("Access-Control-Allow-Methods: POST");
header("Content-Type: application/json");
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: " . $_SERVER['HTTP_ORIGIN']);
}

header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
session_start();
//  $stmtTruncate = $dipr_read_db->prepare("TRUNCATE TABLE user_sessions");
//     $stmtTruncate->execute();
function respondServerError($message = "Internal server error", $httpCode = 500, $exception = null) {
    if ($exception instanceof Exception) {
        error_log("DB ERROR: " . $exception->getMessage());
    }
    http_response_code($httpCode);
    echo json_encode(["success" => 0, "message" => $message]);
    exit;
}
function checkRateLimit($ip, $maxRequests = 1, $windowSeconds = 10) {
    $key = "rate_limit_" . md5($ip);
    $now = time();
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = [];
    }

    // Keep only requests within the time window
    $_SESSION[$key] = array_filter($_SESSION[$key], fn($t) => ($t > $now - $windowSeconds));
    $_SESSION[$key][] = $now;

    if (count($_SESSION[$key]) > $maxRequests) {
        http_response_code(429); // Too Many Requests
        echo json_encode(["success" => 0, "message" => "Rate limit exceeded. Try later."]);
        exit;
    }
} 
checkRateLimit($_SERVER['REMOTE_ADDR']);
 function containsForbiddenPattern($value, &$found = null) {
    $found = [];
    if (preg_match('/[<>&\'"]/', $value)) {
        $found[] = 'forbidden characters < > & \' "';
    }

    $patterns = [
        '/<\s*script\b/i'                     => '<script>',
        '/<\s*style\b/i'                      => '<style>',
        '/on\w+\s*=/i'                        => 'event_handler (onclick, onerror, etc.)',
        '/style\s*=/i'                         => 'style attribute',
        '/javascript\s*:/i'                    => 'javascript: URI',
        '/data\s*:/i'                           => 'data: URI',
        '/expression\s*\(/i'                    => 'CSS expression()',
        '/url\s*\(\s*["\']?\s*javascript\s*:/i'=> 'url(javascript:...)',
        '/<\s*iframe\b/i'                      => '<iframe>',
        '/<\s*svg\b/i'                         => '<svg>',
        '/<\s*img\b[^>]*on\w+/i'               => 'img with on* handler',
        '/<\s*meta\b/i'                        => '<meta>',
        '/<\/\s*script\s*>/i'                  => '</script>',
    ];

    foreach ($patterns as $pat => $desc) {
        if (preg_match($pat, $value)) {
            $found[] = $desc;
        }
    }
    return !empty($found);
}
function validateInputRecursive($data, &$badFields, $parentKey = '') {
    if (is_array($data)) {
        foreach ($data as $k => $v) {
            $keyName = $parentKey === '' ? $k : ($parentKey . '.' . $k);
            validateInputRecursive($v, $badFields, $keyName);
        }
        return;
    }
    if (!is_string($data)) return;

    $value = $data;
    $found = [];
    if (containsForbiddenPattern($value, $found)) {
        $badFields[$parentKey] = $found;
    }
}
$inputData  = file_get_contents("php://input");
$data = json_decode($inputData, true);
if (!$data && !empty($_POST)) {
    $data = $_POST;
}
if (isset($data['data'])) {
    $data = decryptData($data['data']);
}
// $encryptedData = $data['data'] ?? null;
// if (!$encryptedData) {
//     http_response_code(400);
//      exit;
// }
 
if (empty($data['action'])) {
    http_response_code(400);
     exit;
}
 $badFields = [];
validateInputRecursive($data, $badFields);

if (!empty($badFields)) {
    $messages = [];
    foreach ($badFields as $field => $reasons) {
        $messages[] = "$field: " . implode(', ', (array)$reasons);
    }
    http_response_code(400);
    // echo json_encode([
    //     "success" => 0,
    //     "message" => "Invalid input detected (possible HTML/CSS/JS injection).",
    //     "details" => $messages
    // ]);
    exit;
}
$action = $data['action'] ?? null;
$limit = isset($data['limit']) && $data['limit'] !== null ? max(1, (int)$data['limit']) : null;
$offset = $limit !== null ? (isset($data['offset']) ? max(0, (int)$data['offset']) : 0) : null;
$search = $data['search'] ?? null;
$search_key = $data['search_key'] ?? null;

switch ($action) {
    case 'function_call':
        $functionName = $data['function_name'] ?? null;
        $params = $data['params'] ?? [];
        $columns = $data['columns'] ?? '*';

        if (!$functionName) {
            http_response_code(400);
             exit;
        }
 
        if ($functionName === 'user_logout') {
            $userId = $data['params']['user_id'] ?? null;
            if (!$userId) {
                http_response_code(400);
                 exit;
            }

            try {
                $stmt = $dipr_read_db->prepare("DELETE FROM user_sessions WHERE user_id = :uid");
                $stmt->execute([':uid' => $userId]);

                $_SESSION = [];
                session_destroy();

                echo json_encode(["success" => 1, "message" => "Logged out successfully"]);
            } catch (Exception $e) {
                respondServerError("Logout failed", 500, $e);
            }
            exit;
        }
 
        $scalarFunctions = ['user_login_fn', 'forget_password_fn'];
        $isLoginFn = ($functionName === 'user_login_fn');

        if ($isLoginFn) {
            // configure attempts & lockout
            $maxAttempts = 2;      // adjust as needed
            $lockoutTime = 300;    // seconds

            if (!isset($_SESSION['login_attempts'])) {
                $_SESSION['login_attempts'] = 0;
                $_SESSION['lockout_time'] = 0;
            }

            // if previously locked out and still inside lockout window
            if ($_SESSION['login_attempts'] >= $maxAttempts) {
                if ((time() - (int)$_SESSION['lockout_time']) < $lockoutTime) {
                    http_response_code(429);
                    $failData = [['result' => false]];
                    echo json_encode([
                        "success" => 0,
                        "message" => "Too many failed login attempts. Please try again later.",
                        "data" => encrypt($failData)
                    ]);
                    exit;
                } else {
                    // lockout expired, reset
                    $_SESSION['login_attempts'] = 0;
                    $_SESSION['lockout_time'] = 0;
                }
            }
        }

        // sanitize function name
        $functionName = preg_replace('/[^a-zA-Z0-9_]/', '', $functionName);

        // build SQL
        $placeholders = '';
        if (!empty($params)) {
            $placeholders = implode(', ', array_map(fn($k) => ':' . $k, array_keys($params)));
        }
        if (in_array($functionName, $scalarFunctions)) {
            $sql = "SELECT $functionName($placeholders) as result";
        } else {
            $sql = "SELECT $columns FROM $functionName($placeholders)";
        }

        try {
            $response = ["success" => 1];
            $bindParams = $params;

            if (!in_array($functionName, $scalarFunctions)) {
                $whereConditions = [];
                if ($search !== null && $search_key !== null) {
                    $safe_search_key = preg_replace('/[^a-zA-Z0-9_]/', '', $search_key);
                    $whereConditions[] = "$safe_search_key LIKE :search";
                    $bindParams['search'] = "%" . $search . "%";
                }
                if (!empty($whereConditions)) {
                    $sql .= " WHERE " . implode(' AND ', $whereConditions);
                }
            }

            if (!in_array($functionName, $scalarFunctions) && $limit !== null) {
                $sql .= " LIMIT :limit OFFSET :offset";
                $bindParams['limit'] = $limit;
                $bindParams['offset'] = $offset;
            }

            $stmt = $dipr_read_db->prepare($sql);
            foreach ($bindParams as $key => $value) {
                $paramType = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
                $stmt->bindValue(":$key", $value, $paramType);
            }
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
 
            if (in_array($functionName, $scalarFunctions) && $functionName === 'user_login_fn') { 
                $raw = $result[0]['result'] ?? null;
                $loginResult = null;
                if ($raw !== null) {
                    // try decode
                    $decoded = json_decode($raw, true);
                    if (is_array($decoded)) {
                        $loginResult = $decoded;
                    } else {
                        // handle plain 'true'/'false' or other formats
                        if ($raw === 'true' || $raw === true) {
                            // weird case — treat as success but missing user_id -> fail
                            $loginResult = null;
                        } elseif ($raw === 'false' || $raw === false) {
                            $loginResult = null;
                        } else {
                            // maybe DB returned quoted JSON string (double encoded). try second decode:
                            $double = json_decode($raw, true);
                            if (is_array($double)) {
                                $loginResult = $double;
                            } else {
                                $loginResult = null;
                            }
                        }
                    }
                }

                // If loginResult is not an array with user_id -> treat as failed login attempt
                $isSuccess = is_array($loginResult) && !empty($loginResult['user_id']);

                if (!$isSuccess) {
                    // increment attempts and possibly set lockout
                    if (!isset($maxAttempts)) { $maxAttempts = 2; $lockoutTime = 300; }
                    $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
                    if ($_SESSION['login_attempts'] >= $maxAttempts) {
                        $_SESSION['lockout_time'] = time();
                    }

                    http_response_code(401);
                    $failData = [['result' => false]];
                    echo json_encode([
                        "success" => 0,
                        "message" => "Invalid credentials. Attempt {$_SESSION['login_attempts']} of {$maxAttempts}",
                        "data" => encrypt($failData)
                    ]);
                    exit;
                }               
                $userId = $loginResult['user_id'];
                $currentSessionId = session_id();
                if (!$userId) {
                    // treat as failed attempt (safer than returning Missing user ID immediately)
                    $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
                    if ($_SESSION['login_attempts'] >= $maxAttempts) {
                        $_SESSION['lockout_time'] = time();
                    }
                    http_response_code(401);
                    $failData = [['result' => false]];
                    echo json_encode([
                        "success" => 0,
                        "message" => "Invalid credentials. Attempt {$_SESSION['login_attempts']} of {$maxAttempts}",
                        "data" => encrypt($failData)
                    ]);
                    exit;
                }

                // Prevent multiple sessions for same user
                $stmtCheck = $dipr_read_db->prepare("SELECT session_id FROM user_sessions WHERE user_id = :uid");
                $stmtCheck->execute([':uid' => $userId]);
                $existingSession = $stmtCheck->fetchColumn();

                if ($existingSession && $existingSession !== $currentSessionId) {
                    http_response_code(403);
                    $failData = [['result' => false]];
                    echo json_encode([
                        "success" => 0,
                        "message" => "User already logged in from another device/browser.",
                        "data" => encrypt($failData)
                    ]);
                    exit;
                }else{
        
                $stmtInsert = $dipr_read_db->prepare("
                    INSERT INTO user_sessions (user_id, session_id, last_active)
                    VALUES (:uid, :sid, NOW())
                    ON CONFLICT (user_id) DO UPDATE 
                    SET session_id = EXCLUDED.session_id,
                        last_active = EXCLUDED.last_active
                ");
                $stmtInsert->execute([
                    ':uid' => $userId,
                    ':sid' => $currentSessionId
                ]);

                // success — reset attempts
                $_SESSION['login_attempts'] = 0;
                $_SESSION['lockout_time'] = 0;
                $_SESSION['user_role'] = $loginResult['role'] ?? '';
                $_SESSION['user_district'] = $loginResult['district'] ?? '';
                $_SESSION['user_name'] = $loginResult['user_name'] ?? '';
                $_SESSION['user_id'] = $userId ?? '';
                // print_r($_SESSION);
                echo json_encode([
                    "success" => 1,
                    "message" => "Login Successful",
                    "data" => encrypt([$loginResult])
                ]);
                exit;
            }

        } 
            if (!in_array($functionName, $scalarFunctions)) {
                $response["data"] = encrypt($result);

                if ($limit !== null) {
                     $countSql = "SELECT COUNT(*) as total FROM $functionName($placeholders)";
                    $countStmt = $dipr_read_db->prepare($countSql);
                    foreach ($params as $key => $value) {
                        $paramType = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
                        $countStmt->bindValue(":$key", $value, $paramType);
                    }
                    $countStmt->execute();
                    $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

                    $response["pagination"] = [
                        "total" => (int)$totalCount,
                        "limit" => $limit,
                        "offset" => $offset,
                        "total_pages" => ceil($totalCount / $limit),
                        "current_page" => floor($offset / $limit) + 1
                    ];
                }

                if (empty($result)) {
                    $response["success"] = 0;
                    $response["message"] = "Data not found";
                }

                echo json_encode($response);
                exit;
            }

        } catch (Exception $e) {
            respondServerError("Request failed", 500, $e);
        }
        break;

    default:
        http_response_code(400);
 }
