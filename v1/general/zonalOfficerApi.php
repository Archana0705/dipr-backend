<?php
require_once('../../helper/header.php');
require_once('../../helper/db/dipr_read.php');

header("Access-Control-Allow-Methods: POST");
header("Content-Type: application/json");
session_start();
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

// Call at the top of your script

checkRateLimit($_SERVER['REMOTE_ADDR']);
// ----------------------
// Helper: Detect forbidden patterns (HTML/CSS/JS injection)
// ----------------------
function containsForbiddenPattern($value, &$found = null) {
    $found = [];
    if (preg_match('/[<>&\'"]/', $value)) {
        $found[] = 'forbidden characters < > & \' "';
    }
    $patterns = [
        '/<\s*script\b/i'           => '<script>',
        '/<\s*style\b/i'            => '<style>',
        '/on\w+\s*=/i'              => 'event_handler (onclick, onerror, etc.)',
        '/style\s*=/i'              => 'style attribute',
        '/javascript\s*:/i'         => 'javascript: URI',
        '/data\s*:/i'               => 'data: URI',
        '/expression\s*\(/i'        => 'CSS expression()',
        '/url\s*\(\s*["\']?\s*javascript\s*:/i' => 'url(javascript:...)',
        '/<\s*iframe\b/i'           => '<iframe>',
        '/<\s*svg\b/i'              => '<svg>',
        '/<\s*img\b[^>]*on\w+/i'    => 'img with on* handler',
        '/<\s*meta\b/i'             => '<meta>',
        '/<\/\s*script\s*>/i'       => '</script>',
    ];
    foreach ($patterns as $pat => $desc) {
        if (preg_match($pat, $value)) {
            $found[] = $desc;
        }
    }
    return !empty($found);
}

// ----------------------
// Validate all input recursively
// ----------------------
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

// ----------------------
// Read input
// ----------------------
$jsonData = file_get_contents("php://input");
$data = json_decode($jsonData, true);
if (isset($data['data'])) {
            $data = decryptData($data['data']);
        }
        if (empty($data['user_id'])) {
            http_response_code(401);
            exit;
        }
        if (!empty($data['user_id'])) {
            $stmtCheck = $dipr_read_db->prepare("SELECT session_id FROM user_sessions WHERE user_id = :uid");
            $stmtCheck->execute([':uid' => $data['user_id']]);
            $existingSession = $stmtCheck->fetchColumn();
            // Validate session
            if (empty($existingSession)) {
                http_response_code(401);
                exit;
            }
        }
// Validate input
$badFields = [];
validateInputRecursive($data, $badFields);
if (!empty($badFields)) {
    $messages = [];
    foreach ($badFields as $field => $reasons) {
        $messages[] = "$field: " . implode(', ', (array)$reasons);
    }
    http_response_code(400);
    echo json_encode([
        "success" => 0,
        "message" => "Invalid input detected (possible HTML/CSS/JS injection).",
        "details" => $messages
    ]);
    exit;
}

// ----------------------
// Check action
// ----------------------
if (empty($data['action'])) {
    http_response_code(400);
  //  echo json_encode(["success" => 0, "message" => "Action is required"]);
    exit;
}

$action = strtolower($data['action']);

switch ($action) {
    case 'fetch':
        $sql = "SELECT SLNO, ZONE, DISTRICTS, CREATED_BY, CREATED_ON, UPDATED_BY, UPDATED_ON, LANGUAGE FROM TN_ZONAL_OFFICERS";
        try {
            $stmt = $dipr_read_db->prepare($sql);
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(["success" => 1, "data" => $result], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log("Error fetching data: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(["success" => 0, "message" => "Internal server error"]);
        }
        break;

    case 'insert':
        if (empty($data['zone']) || empty($data['districts']) || empty($data['language'])) {
            http_response_code(400);
            echo json_encode(["success" => 0, "message" => "Required fields are missing"]);
            exit;
        }

        $sql = "INSERT INTO TN_ZONAL_OFFICERS (ZONE, DISTRICTS, CREATED_BY, CREATED_ON, UPDATED_BY, UPDATED_ON, LANGUAGE)
                VALUES (:p_ZONE, :p_DISTRICTS, :p_CREATED_BY, NOW(), :p_UPDATED_BY, NOW(), :p_LANGUAGE)";
        try {
            $stmt = $dipr_read_db->prepare($sql);
            $stmt->bindValue(':p_ZONE', $data['zone'], PDO::PARAM_STR);
            $stmt->bindValue(':p_DISTRICTS', $data['districts'], PDO::PARAM_STR);
            $stmt->bindValue(':p_CREATED_BY', $data['created_by'] ?? 'admin', PDO::PARAM_STR);
            $stmt->bindValue(':p_UPDATED_BY', $data['updated_by'] ?? 'admin', PDO::PARAM_STR);
            $stmt->bindValue(':p_LANGUAGE', $data['language'], PDO::PARAM_STR);

            if ($stmt->execute()) {
                http_response_code(201);
                echo json_encode(["success" => 1, "message" => "Data inserted successfully"]);
            } else {
                http_response_code(500);
             //   echo json_encode(["success" => 0, "message" => "Database execution failed"]);
            }
        } catch (Exception $e) {
            error_log("Error inserting data: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(["success" => 0, "message" => "Internal server error"]);
        }
        break;

    case 'update':
        if (empty($data['slno']) || empty($data['zone']) || empty($data['districts']) || empty($data['language'])) {
            http_response_code(400);
            echo json_encode(["success" => 0, "message" => "Required fields are missing"]);
            exit;
        }

        $sql = "UPDATE TN_ZONAL_OFFICERS
                SET ZONE = :p_ZONE, DISTRICTS = :p_DISTRICTS, UPDATED_BY = :p_UPDATED_BY, UPDATED_ON = NOW(), LANGUAGE = :p_LANGUAGE
                WHERE SLNO = :p_SLNO";
        try {
            $stmt = $dipr_read_db->prepare($sql);
            $stmt->bindValue(':p_SLNO', $data['slno'], PDO::PARAM_INT);
            $stmt->bindValue(':p_ZONE', $data['zone'], PDO::PARAM_STR);
            $stmt->bindValue(':p_DISTRICTS', $data['districts'], PDO::PARAM_STR);
            $stmt->bindValue(':p_UPDATED_BY', $data['updated_by'] ?? 'admin', PDO::PARAM_STR);
            $stmt->bindValue(':p_LANGUAGE', $data['language'], PDO::PARAM_STR);

            if ($stmt->execute()) {
                http_response_code(200);
                echo json_encode(["success" => 1, "message" => "Data updated successfully"]);
            } else {
                http_response_code(500);
           //     echo json_encode(["success" => 0, "message" => "Database execution failed"]);
            }
        } catch (Exception $e) {
            error_log("Error updating data: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(["success" => 0, "message" => "Internal server error"]);
        }
        break;

    case 'delete':
        if (empty($data['slno'])) {
            http_response_code(400);
          //  echo json_encode(["success" => 0, "message" => "SLNO is required"]);
            exit;
        }

        $sql = "DELETE FROM TN_ZONAL_OFFICERS WHERE SLNO = :p_SLNO";
        try {
            $stmt = $dipr_read_db->prepare($sql);
            $stmt->bindValue(':p_SLNO', $data['slno'], PDO::PARAM_INT);

            if ($stmt->execute()) {
                http_response_code(200);
                echo json_encode(["success" => 1, "message" => "Data deleted successfully"]);
            } else {
                http_response_code(500);
              //  echo json_encode(["success" => 0, "message" => "Database execution failed"]);
            }
        } catch (Exception $e) {
            error_log("Error deleting data: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(["success" => 0, "message" => "Internal server error"]);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(["success" => 0, "message" => "Invalid action"]);
}
