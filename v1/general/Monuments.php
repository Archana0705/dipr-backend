<?php
require_once('../../helper/header.php');
require_once('../../helper/generic.php');
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
// Helper: detect forbidden patterns (HTML/CSS/JS injection)
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
$jsonData = file_get_contents("php://input");
$data = json_decode($jsonData, true) ?? $_POST;

// ----------------------
// Check action
// ----------------------
$action = $data['action'] ?? null;
$table = "TN_MONUMENTS";
$primaryKey = "slno";

if (!$action) {
    http_response_code(400);
    //echo json_encode(["success" => 0, "message" => "Invalid or missing action"]);
    exit;
}

// ----------------------
// Validate all input fields for HTML/CSS injection
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
// CRUD Logic
// ----------------------
switch ($action) {
    case 'fetch':
        $sql = "SELECT * FROM $table";
        try {
            $stmt = $dipr_read_db->prepare($sql);
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(["success" => 1, "data" => $result], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["success" => 0, "message" => "Internal server error"]);
        }
        break;

    case 'insert':
        $response = uploadFile($_FILES["video_attachment_name"], "uploads/videos/monuments/");
        if (!$response['success']) {
            echo json_encode(["success" => 0, "message" => "File upload failed."]);
            exit;
        }

        $sql = "INSERT INTO $table (MONUMENTS, PLACE, CREATED_BY, CREATED_ON, UPDATED_BY, UPDATED_ON, DISTRICT, VIDEO_NAME, VIDEO_ATTACHMENT_NAME, MIME_TYPE, LANGUAGE)
                VALUES (:monuments, :place, :created_by, NOW(), :updated_by, NOW(), :district, :video_name, :video_attachment_name, :mime_type, :language)";

        $params = [
            ':monuments' => $data['monuments'],
            ':place' => $data['place'],
            ':created_by' => $data['created_by'] ?? 'admin',
            ':updated_by' => $data['updated_by'] ?? 'admin',
            ':district' => $data['district'],
            ':video_name' => $response['file_name'],
            ':video_attachment_name' => $response['file_path'],
            ':mime_type' => $response['mime_type'],
            ':language' => $data['language']
        ];

        try {
            $stmt = $dipr_read_db->prepare($sql);
            if ($stmt->execute($params)) {
                http_response_code(201);
                echo json_encode(["success" => 1, "message" => "Data inserted successfully"]);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["success" => 0, "message" => "Internal server error", "error" => $e->getMessage()]);
        }
        break;

    case 'update':
        if (empty($data[$primaryKey])) {
            http_response_code(400);
           // echo json_encode(["success" => 0, "message" => "SLNO is required"]);
            exit;
        }

        $sql = "UPDATE $table SET MONUMENTS = :monuments, PLACE = :place, UPDATED_BY = :updated_by, UPDATED_ON = NOW(), DISTRICT = :district, VIDEO_NAME = :video_name, VIDEO_ATTACHMENT_NAME = :video_attachment_name, MIME_TYPE = :mime_type, LANGUAGE = :language WHERE $primaryKey = :slno";

        $params = [
            ':slno' => $data['slno'],
            ':monuments' => $data['monuments'],
            ':place' => $data['place'],
            ':updated_by' => $data['updated_by'] ?? 'admin',
            ':district' => $data['district'],
            ':video_name' => $data['video_name'],
            ':video_attachment_name' => $data['video_attachment_name'],
            ':mime_type' => $data['mime_type'],
            ':language' => $data['language']
        ];

        try {
            $stmt = $dipr_read_db->prepare($sql);
            if ($stmt->execute($params)) {
                http_response_code(200);
                echo json_encode(["success" => 1, "message" => "Data updated successfully"]);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["success" => 0, "message" => "Internal server error"]);
        }
        break;

    case 'delete':
        if (empty($data[$primaryKey])) {
            http_response_code(400);
           // echo json_encode(["success" => 0, "message" => "SLNO is required"]);
            exit;
        }

        $sql = "DELETE FROM $table WHERE $primaryKey = :slno";
        try {
            $stmt = $dipr_read_db->prepare($sql);
            $stmt->bindValue(':slno', $data[$primaryKey], PDO::PARAM_INT);
            if ($stmt->execute()) {
                http_response_code(200);
                echo json_encode(["success" => 1, "message" => "Data deleted successfully"]);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["success" => 0, "message" => "Internal server error"]);
        }
        break;

    default:
        http_response_code(400);
        //echo json_encode(["success" => 0, "message" => "Invalid action"]);
}
