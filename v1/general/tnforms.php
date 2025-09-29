<?php
require_once('../../helper/header.php');
require_once('../../helper/db/dipr_read.php');

header("Access-Control-Allow-Methods: POST");
header("Content-Type: application/json");

// Helper function: detect dangerous characters / HTML/JS/CSS injection
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

// Recursive validation for all inputs
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
$data = json_decode($jsonData, true);

if (empty($data['action'])) {
    http_response_code(400);
  //  echo json_encode(["success" => 0, "message" => "Action is required"]);
    exit;
}

// Validate inputs
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
$table = "TN_FORMS";
$primaryKey = "id";

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
        $sql = "INSERT INTO $table (ID, FORM_NAME, ATTACHMENT_NAME, UPLOADED_BY, UPLOADED_ON, MIME_TYPE, LANGUAGE)
                VALUES (:p_ID, :p_FORM_NAME, :p_ATTACHMENT_NAME, :p_UPLOADED_BY, NOW(), :p_MIME_TYPE, :p_LANGUAGE)";
        try {
            $stmt = $dipr_read_db->prepare($sql);
            $stmt->execute([
                ':p_ID' => $data['id'],
                ':p_FORM_NAME' => $data['form_name'],
                ':p_ATTACHMENT_NAME' => $data['attachment_name'],
                ':p_UPLOADED_BY' => $data['uploaded_by'] ?? 'admin',
                ':p_MIME_TYPE' => $data['mime_type'],
                ':p_LANGUAGE' => $data['language']
            ]);
            http_response_code(201);
            echo json_encode(["success" => 1, "message" => "Data inserted successfully"]);
        } catch (Exception $e) {
            error_log("Error inserting data: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(["success" => 0, "message" => "Internal server error"]);
        }
        break;

    case 'update':
        if (empty($data[$primaryKey])) {
            http_response_code(400);
        //    echo json_encode(["success" => 0, "message" => "$primaryKey is required"]);
            exit;
        }
        $sql = "UPDATE $table SET FORM_NAME = :p_FORM_NAME, ATTACHMENT_NAME = :p_ATTACHMENT_NAME, UPLOADED_BY = :p_UPLOADED_BY, UPLOADED_ON = NOW(), MIME_TYPE = :p_MIME_TYPE, LANGUAGE = :p_LANGUAGE WHERE $primaryKey = :p_ID";
        try {
            $stmt = $dipr_read_db->prepare($sql);
            $stmt->execute([
                ':p_ID' => $data['id'],
                ':p_FORM_NAME' => $data['form_name'],
                ':p_ATTACHMENT_NAME' => $data['attachment_name'],
                ':p_UPLOADED_BY' => $data['uploaded_by'] ?? 'admin',
                ':p_MIME_TYPE' => $data['mime_type'],
                ':p_LANGUAGE' => $data['language']
            ]);
            http_response_code(200);
            echo json_encode(["success" => 1, "message" => "Data updated successfully"]);
        } catch (Exception $e) {
            error_log("Error updating data: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(["success" => 0, "message" => "Internal server error"]);
        }
        break;

    case 'delete':
        if (empty($data[$primaryKey])) {
            http_response_code(400);
          //  echo json_encode(["success" => 0, "message" => "$primaryKey is required"]);
            exit;
        }
        $sql = "DELETE FROM $table WHERE $primaryKey = :p_ID";
        try {
            $stmt = $dipr_read_db->prepare($sql);
            $stmt->bindValue(':p_ID', $data[$primaryKey], PDO::PARAM_INT);
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
