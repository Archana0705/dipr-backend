<?php
require_once('../../helper/header.php');
require_once('../../helper/db/dipr_read.php');
require_once('../../helper/db/dipr_write.php');

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
// Helper function: check for dangerous characters / HTML/JS/CSS injection
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

// Recursive input validation for arrays
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

// Read input
$jsonData = file_get_contents("php://input");
$data = json_decode($jsonData, true) ?? $_POST;

if (isset($data['data'])) {
    $data = decryptData($data['data']);
}
if (empty($data['action'])) {
    http_response_code(400);
   // echo json_encode(["success" => 0, "message" => "Action is required"]);
    exit;
}

// Validate all inputs for dangerous patterns
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

$action = $data['action'];
$table = "TN_ARANGAMS";
$primaryKey = "slno";

switch ($action) {
    case 'fetch':
        try {
            $stmt = $dipr_read_db->prepare("SELECT * FROM $table WHERE language = ?");
            $stmt->execute([$data['lang']]);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(["success" => 1, "data" => $result], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log("Error fetching data: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(["success" => 0, "message" => "Internal server error"]);
        }
        break;

    case 'insert':
    $targetDir = __DIR__ . "/uploads/videos/tn_arangam/";
    if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);

    // Check if file exists
    if (empty($_FILES['video']['name'])) {
        echo json_encode(["success" => 0, "message" => "No video file uploaded."]);
        exit;
    }

    $file = $_FILES['video'];
    $fileName = basename($file["name"]);
    $targetFilePath = $targetDir . $fileName;

    // Validate file size
    $maxFileSize = 50 * 1024 * 1024; // 50 MB
    if ($file["size"] > $maxFileSize) {
        echo json_encode(["success" => 0, "message" => "File size exceeds 50MB."]);
        exit;
    }

    // Validate MIME type
    $allowedMimeTypes = ['video/mp4', 'video/x-msvideo', 'video/quicktime', 'video/x-matroska'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file["tmp_name"]);
    finfo_close($finfo);

    if (!in_array($mimeType, $allowedMimeTypes)) {
        echo json_encode(["success" => 0, "message" => "Invalid file format."]);
        exit;
    }

    // Move uploaded file
    if (!move_uploaded_file($file["tmp_name"], $targetFilePath)) {
        echo json_encode(["success" => 0, "message" => "Error uploading video."]);
        exit;
    }

    // Safe defaults for missing fields
    $slno       = $data['slno'] ?? '';
    $arangam    = $data['arangam'] ?? $data['monuments'] ?? '';
    $place      = $data['place'] ?? '';
    $created_by = $data['created_by'] ?? 'admin';
    $updated_by = $data['updated_by'] ?? $created_by;
    $district   = $data['district'] ?? '';
    $video_name = $data['video_name'] ?? '';
    $language   = $data['language'] ?? 'en';

    // Prepare SQL
    $sql = "INSERT INTO TN_ARANGAMS 
            (ARANGAM, PLACE, CREATED_BY, CREATED_ON, UPDATED_BY, UPDATED_ON, DISTRICT, VIDEO_NAME, VIDEO_ATTACHMENT_NAME, MIME_TYPE, LANGUAGE)
            VALUES ( :p_ARANGAM, :p_PLACE, :p_CREATED_BY, NOW(), :p_UPDATED_BY, NOW(), :p_DISTRICT, :p_VIDEO_NAME, :p_VIDEO_ATTACHMENT_NAME, :p_MIME_TYPE, :p_LANGUAGE)";

    $params = [
        ':p_SLNO' => $slno,
        ':p_ARANGAM' => $arangam,
        ':p_PLACE' => $place,
        ':p_CREATED_BY' => $created_by,
        ':p_UPDATED_BY' => $updated_by,
        ':p_DISTRICT' => $district,
        ':p_VIDEO_NAME' => $video_name,
        ':p_VIDEO_ATTACHMENT_NAME' => $fileName,
        ':p_MIME_TYPE' => $mimeType,
        ':p_LANGUAGE' => $language
    ];

    try {
        $stmt = $dipr_write_db->prepare($sql);
        if ($stmt->execute($params)) {
            http_response_code(201);
            echo json_encode(["success" => 1, "message" => "Data inserted successfully"]);
        } else {
            http_response_code(500);
            echo json_encode(["success" => 0, "message" => "Database execution failed"]);
        }
    } catch (Exception $e) {
        error_log("Error inserting data: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(["success" => 0, "message" => "Internal server error: " . $e->getMessage()]);
    }
    break;


    case 'update':
        if (empty($data[$primaryKey])) {
            http_response_code(400);
            //echo json_encode(["success" => 0, "message" => "$primaryKey is required"]);
            exit;
        }

        $sql = "UPDATE $table SET ARANGAM=:p_ARANGAM, PLACE=:p_PLACE, UPDATED_BY=:p_UPDATED_BY, UPDATED_ON=NOW(), DISTRICT=:p_DISTRICT, VIDEO_NAME=:p_VIDEO_NAME, VIDEO_ATTACHMENT_NAME=:p_VIDEO_ATTACHMENT_NAME, MIME_TYPE=:p_MIME_TYPE, LANGUAGE=:p_LANGUAGE WHERE $primaryKey=:p_SLNO";
        $params = [
            ':p_SLNO' => $data['slno'],
            ':p_ARANGAM' => $data['arangam'],
            ':p_PLACE' => $data['place'],
            ':p_UPDATED_BY' => $data['updated_by'],
            ':p_DISTRICT' => $data['district'],
            ':p_VIDEO_NAME' => $data['video_name'],
            ':p_VIDEO_ATTACHMENT_NAME' => $data['video_attachment_name'],
            ':p_MIME_TYPE' => $data['mime_type'],
            ':p_LANGUAGE' => $data['language']
        ];

        try {
            $stmt = $dipr_read_db->prepare($sql);
            if ($stmt->execute($params)) {
                http_response_code(200);
                echo json_encode(["success" => 1, "message" => "Data updated successfully"]);
            } else {
                http_response_code(500);
                //echo json_encode(["success" => 0, "message" => "Database execution failed"]);
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
            //echo json_encode(["success" => 0, "message" => "$primaryKey is required"]);
            exit;
        }

        $sql = "DELETE FROM $table WHERE $primaryKey=:p_SLNO";
        try {
            $stmt = $dipr_read_db->prepare($sql);
            $stmt->bindValue(':p_SLNO', $data[$primaryKey], PDO::PARAM_INT);
            if ($stmt->execute()) {
                http_response_code(200);
                echo json_encode(["success" => 1, "message" => "Data deleted successfully"]);
            } else {
                http_response_code(500);
               // echo json_encode(["success" => 0, "message" => "Database execution failed"]);
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
