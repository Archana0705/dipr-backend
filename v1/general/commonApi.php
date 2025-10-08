<?php
require_once('../../helper/header.php');
require_once('../../helper/db/dipr_read.php');

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: " . $_SERVER['HTTP_ORIGIN']);
}

header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: POST");
header("Content-Type: application/json");
session_start();

function respondServerError($message = "Internal server error", $httpCode = 500, $exception = null) {
    if ($exception instanceof Exception) {
        error_log("DB ERROR: " . $exception->getMessage());
    }
    http_response_code($httpCode);
    echo json_encode(["success" => 0, "message" => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode([
        "success" => 0,
        "message" => "Method Not Allowed. Only POST is supported."
    ]);
    exit;
}
function contains_forbidden_pattern(string $value, array &$found = null): bool {
    $found = [];
    if (preg_match('/[<>&\'"]/', $value)) $found[] = 'forbidden_chars_< > & \' "';
    $patterns = [
        '/<\s*script\b/i'           => '<script>',
        '/<\s*style\b/i'            => '<style>',
        '/on\w+\s*=/i'              => 'event_handler',
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
    foreach ($patterns as $pat => $label) {
        if (preg_match($pat, $value)) $found[] = $label;
    }
    return !empty($found);
}
function validateFields($data, &$errors = [], $parentKey = '') {
    if (is_array($data)) {
        foreach ($data as $k => $v) {
            $keyName = $parentKey === '' ? $k : ($parentKey . '.' . $k);
            validateFields($v, $errors, $keyName);
        }
        return;
    }
    if (!is_string($data)) return;
    $value = $data;

    // Email check
    if (preg_match('/email|email_id|emailid/i', $parentKey)) {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) $errors[$parentKey][] = 'Invalid email';
    }

    // Forbidden patterns
    $found = [];
    if (contains_forbidden_pattern($value, $found)) $errors[$parentKey] = $found;
}
function validateCommonFields($data, &$errors = []) {
    foreach ($data as $key => $val) {
        if (stripos($key, 'phone') !== false) {
            if (!is_int($val) || strlen((string)$val) !== 10) {
                $errors[$key][] = 'Must be an integer of 10 digits';
            }
        }
    }
}


// read input (json preferred; fallback to $_POST)
$jsonData = file_get_contents("php://input");
$data = json_decode($jsonData, true) ?? $_POST;
if (isset($data['data'])) {
    $data = decryptData($data['data']);
}
$stmtCheck = $dipr_read_db->prepare("SELECT session_id FROM user_sessions WHERE user_id = :uid");
$stmtCheck->execute([':uid' => $data['user_id']]);
$existingSession = $stmtCheck->fetchColumn();
// Validate session
if (empty($existingSession)) {
    http_response_code(401);
    exit;
}
// Validate required action/table early
if (empty($data['action']) || empty($data['table'])) {
    http_response_code(400);
    //echo json_encode(["success" => 0, "message" => "Action and table name are required"]);
    exit;
}
$errors = [];
validateFields($data, $errors);
validateCommonFields($data, $errors);
if (!empty($errors)) {
    http_response_code(400);
    echo json_encode([
        "success" => 0,
        "message" => "Validation failed",
        "details" => $errors
    ], JSON_UNESCAPED_UNICODE);
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

// Call at the top of your script

checkRateLimit($_SERVER['REMOTE_ADDR']);

// everything else: existing flow
$action = trim($data['action']);
$table  = $data['table'];
$primaryKey = $data['primary_key'] ?? 'slno';

switch ($action) { 
    case 'fetch':
        $filters = $data['filters'] ?? [];
        $columns = $data['columns'] ?? '*';

        if ($columns !== '*' && is_array($columns)) {
            $columns = implode(", ", array_map(function($c){ return preg_replace('/[^A-Za-z0-9_.*, ]/','', $c); }, $columns));
        } elseif ($columns !== '*') {
            $columns = preg_replace('/[^A-Za-z0-9_,.*\s]/', '', $columns);
        }

        $sql = "SELECT $columns FROM $table";
        if (!empty($filters)) {
            $whereClauses = [];
            foreach ($filters as $key => $value) {
                $whereClauses[] = "$key = :$key";
            }
            $sql .= " WHERE " . implode(" AND ", $whereClauses);
        }
        try {
            $stmt = $dipr_read_db->prepare($sql);
            foreach ($filters as $key => $value) {
                $stmt->bindValue(":$key", $value);
            }
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(["success" => 1, "data" => $result], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            respondServerError("Failed to fetch data", 500, $e);
        }
        break;
 
    case 'insert':
        $targetDir = "uploads/{$table}/";
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }
        if (!empty($data['ATTACHMENT_NAME'])) {
                $fileContent = base64_decode($data['ATTACHMENT_NAME']);
                $fileName = $data['PRESS_NOTE_NAME'] ?? 'file_' . time();
                $targetFilePath = $targetDir . $fileName;

                if (file_put_contents($targetFilePath, $fileContent) === false) {
                    respondServerError("Failed to save uploaded file");
                }
        
                $data['ATTACHMENT_NAME'] = $targetFilePath;
            }
        if (!empty($_FILES["file"])) {
            $fileName = basename($_FILES["file"]["name"]);
            $targetFilePath = $targetDir . $fileName;
            $fileType = strtolower(pathinfo($targetFilePath, PATHINFO_EXTENSION));

            $allowedFormats = ['mp4', 'avi', 'mov', 'mkv', 'jpg', 'jpeg', 'png', 'pdf'];
            $maxFileSize = 50 * 1024 * 1024;

            if ($_FILES["file"]["size"] > $maxFileSize) {
                echo json_encode(["success" => 0, "message" => "Error: File size exceeds 50MB."]);
                exit;
            }

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $_FILES["file"]["tmp_name"]);
            finfo_close($finfo);

            if (!in_array($fileType, $allowedFormats)) {
                echo json_encode(["success" => 0, "message" => "Invalid file format."]);
                exit;
            }

            if (!move_uploaded_file($_FILES["file"]["tmp_name"], $targetFilePath)) {
                echo json_encode(["success" => 0, "message" => "Error uploading file."]);
                exit;
            }

            if ($data['upload_type'] == 'video') {
                $data['video_attachment_name'] = $targetFilePath;
                $data['mime_type'] = $mimeType;
            } else if ($data['upload_type'] == 'file') {
                $data['file_attachment_name'] = $targetFilePath;
                $data['mime_type'] = $mimeType;
            } else if ($data['upload_type'] == 'image') {
                $data['image_attachment_name'] = $targetFilePath;
                $data['mime_type'] = $mimeType;
            } else {
                http_response_code(400);
                echo json_encode(["success" => 0, "message" => "Invalid upload type"]);
                exit;
            }
        }

        $excludedKeys = ['action', 'table', 'upload_type', 'file', 'primary_key','user_id'];
        $filteredData = array_diff_key($data, array_flip($excludedKeys));

        $columns = implode(", ", array_keys($filteredData));
        $placeholders = ":" . implode(", :", array_keys($filteredData));
        $sql = "INSERT INTO $table ($columns) VALUES ($placeholders)";

        try {
            $stmt = $dipr_read_db->prepare($sql);
            foreach ($filteredData as $key => $value) {
                $stmt->bindValue(":$key", $value);
            }
            if ($stmt->execute()) {
                http_response_code(201);
                echo json_encode(["success" => 1, "message" => "Data inserted successfully"]);
            } else {
                respondServerError("Database execution failed", 500);
            }
        } catch (Exception $e) {
            respondServerError("Failed to insert data", 500, $e);
        }
        break;
 
    case 'update':
        if (!empty($_FILES["file"])) {
            $targetDir = "uploads/{$table}/";
            $existing_file = $data['existing_file'] ?? null;

            if ($existing_file && file_exists($existing_file)) {
                @unlink($existing_file);
            }

            if (!is_dir($targetDir)) {
                if (!mkdir($targetDir, 0755, true)) {
                    echo json_encode(["success" => 0, "message" => "Error creating upload directory."]);
                    exit;
                }
            }

            $fileName = basename($_FILES["file"]["name"]);
            $targetFilePath = $targetDir . $fileName;
            $fileType = strtolower(pathinfo($targetFilePath, PATHINFO_EXTENSION));
            $allowedFormats = [
                'mp4' => 'video/mp4', 'avi' => 'video/x-msvideo', 'mov' => 'video/quicktime', 'mkv' => 'video/x-matroska',
                'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'pdf' => 'application/pdf'
            ];
            $maxFileSize = ($data['upload_type'] === 'video') ? 50 * 1024 * 1024 : 5 * 1024 * 1024;

            if ($_FILES["file"]["size"] > $maxFileSize) {
                echo json_encode(["success" => 0, "message" => "Error: File too large."]);
                exit;
            }

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $_FILES["file"]["tmp_name"]);
            finfo_close($finfo);

            if (!array_key_exists($fileType, $allowedFormats) || $mimeType !== $allowedFormats[$fileType]) {
                echo json_encode(["success" => 0, "message" => "Invalid file format."]);
                exit;
            }

            if (!move_uploaded_file($_FILES["file"]["tmp_name"], $targetFilePath)) {
                echo json_encode(["success" => 0, "message" => "Error uploading file."]);
                exit;
            }

            if ($data['upload_type'] === 'video') {
                $data['video_attachment_name'] = $targetFilePath;
                $data['mime_type'] = $mimeType;
            } elseif ($data['upload_type'] === 'file') {
                $data['file_attachment_name'] = $targetFilePath;
                $data['mime_type'] = $mimeType;
            } elseif ($data['upload_type'] === 'image') {
                $data['image_attachment_name'] = $targetFilePath;
                $data['mime_type'] = $mimeType;
            } else {
                http_response_code(400);
                echo json_encode(["success" => 0, "message" => "Invalid upload type."]);
                exit;
            }
        }

        $excludedKeys = ['action', 'table', 'upload_type', 'file', 'primary_key','id','slno','ID','SLNO'];
        $filteredData = array_diff_key($data, array_flip($excludedKeys));

        $setClauses = [];
        foreach ($filteredData as $key => $value) {
            if ($key !== $primaryKey) {
                $setClauses[] = "$key = :$key";
            }
        }

        $updateQuery = "UPDATE {$table} SET " . implode(", ", $setClauses) . " WHERE {$primaryKey} = :{$primaryKey}";

        try {
            $stmt = $dipr_read_db->prepare($updateQuery);
            $updateParams = $filteredData;
            $updateParams[$primaryKey] = $data[strtoupper($primaryKey)] ?? $data[$primaryKey];
            $stmt->execute($updateParams);

            echo json_encode(["success" => 1, "message" => "Data updated successfully"]);
        } catch (Exception $e) {
            respondServerError("Failed to update data", 500, $e);
        }
        break;
 
    case 'delete':
        if (empty($data[$primaryKey])) {
            http_response_code(400);
           // echo json_encode(["success" => 0, "message" => "Primary key is required"]);
            exit;
        }

        $sql = "DELETE FROM $table WHERE $primaryKey = :$primaryKey";

        try {
            $stmt = $dipr_read_db->prepare($sql);
            $stmt->bindValue(":$primaryKey", $data[$primaryKey]);
            if ($stmt->execute()) {
                http_response_code(200);
                echo json_encode(["success" => 1, "message" => "Data deleted successfully"]);
            } else {
                respondServerError("Database execution failed", 500);
            }
        } catch (Exception $e) {
            respondServerError("Failed to delete data", 500, $e);
        }
        break;
 
    default:
        http_response_code(400);
       // echo json_encode(["success" => 0, "message" => "Invalid action"]);
}
