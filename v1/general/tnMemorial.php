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
// Recursive validation for all input fields
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
$data = json_decode(file_get_contents("php://input"), true) ?? $_POST;
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
if (empty($data['action'])) {
    http_response_code(400);
 //   echo json_encode(["success" => 0, "message" => "Action is required"]);
    exit;
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
// ----------------------
// Validate input to prevent injection
// ----------------------
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
// CRUD logic
// ----------------------
$action = $data['action'];
$table = "TN_MEMORIALS";
$primaryKey = "slno";

switch ($action) {
    case 'fetch':
        $sql = "SELECT * FROM $table";
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
        $sql = "INSERT INTO $table (MEMORIAL, PLACE, CREATED_BY, CREATED_ON, UPDATED_BY, UPDATED_ON, DISTRICT, VIDEO_NAME, MIME_TYPE, VIDEO_ATTACHMENT_NAME, LANGUAGE)
                VALUES (:p_MEMORIAL, :p_PLACE, :p_CREATED_BY, NOW(), :p_UPDATED_BY, NOW(), :p_DISTRICT, :p_VIDEO_NAME, :p_MIME_TYPE, :p_VIDEO_ATTACHMENT_NAME, :p_LANGUAGE)";
        $params = [
            ':p_MEMORIAL' => $data['memorial'],
            ':p_PLACE' => $data['place'],
            ':p_CREATED_BY' => $data['created_by'] ?? 'admin',
            ':p_UPDATED_BY' => $data['updated_by'] ?? 'admin',
            ':p_DISTRICT' => $data['district'],
            ':p_VIDEO_NAME' => $data['video_name'],
            ':p_MIME_TYPE' => $data['mime_type'],
            ':p_VIDEO_ATTACHMENT_NAME' => $data['video_attachment_name'],
            ':p_LANGUAGE' => $data['language']
        ];
        try {
            $stmt = $dipr_read_db->prepare($sql);
            if ($stmt->execute($params)) {
                http_response_code(201);
                echo json_encode(["success" => 1, "message" => "Data inserted successfully"]);
            } else {
                http_response_code(500);
            //    echo json_encode(["success" => 0, "message" => "Database execution failed"]);
            }
        } catch (Exception $e) {
            error_log("Error inserting data: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(["success" => 0, "message" => "Internal server error"]);
        }
        break;

    case 'update':
        if (empty($data[$primaryKey])) {
            http_response_code(400);
          //  echo json_encode(["success" => 0, "message" => "$primaryKey is required"]);
            exit;
        }
        $sql = "UPDATE $table SET MEMORIAL = :p_MEMORIAL, PLACE = :p_PLACE, UPDATED_BY = :p_UPDATED_BY, UPDATED_ON = NOW(), DISTRICT = :p_DISTRICT, VIDEO_NAME = :p_VIDEO_NAME, MIME_TYPE = :p_MIME_TYPE, VIDEO_ATTACHMENT_NAME = :p_VIDEO_ATTACHMENT_NAME, LANGUAGE = :p_LANGUAGE WHERE $primaryKey = :p_SLNO";
        $params = [
            ':p_SLNO' => $data['slno'],
            ':p_MEMORIAL' => $data['memorial'],
            ':p_PLACE' => $data['place'],
            ':p_UPDATED_BY' => $data['updated_by'] ?? 'admin',
            ':p_DISTRICT' => $data['district'],
            ':p_VIDEO_NAME' => $data['video_name'],
            ':p_MIME_TYPE' => $data['mime_type'],
            ':p_VIDEO_ATTACHMENT_NAME' => $data['video_attachment_name'],
            ':p_LANGUAGE' => $data['language']
        ];
        try {
            $stmt = $dipr_read_db->prepare($sql);
            if ($stmt->execute($params)) {
                http_response_code(200);
                echo json_encode(["success" => 1, "message" => "Data updated successfully"]);
            } else {
                http_response_code(500);
             //   echo json_encode(["success" => 0, "message" => "Database execution failed"]);
            }
        } catch (Exception $e) {
            error_log("Error updating data: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(["success" => 0, "message" => "Internal server error"]);
        }
        break;

    case 'delete':
        if (empty($data[$primaryKey])) {
            http_response_code(400);
         //   echo json_encode(["success" => 0, "message" => "$primaryKey is required"]);
            exit;
        }
        $sql = "DELETE FROM $table WHERE $primaryKey = :p_SLNO";
        try {
            $stmt = $dipr_read_db->prepare($sql);
            $stmt->bindValue(':p_SLNO', $data[$primaryKey], PDO::PARAM_INT);
            $stmt->execute();
            http_response_code(200);
            echo json_encode(["success" => 1, "message" => "Data deleted successfully"]);
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
