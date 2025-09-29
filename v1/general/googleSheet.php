<?php
require_once('../../helper/header.php');
require_once('../../helper/db/dipr_read.php');

header("Access-Control-Allow-Methods: POST");
// header("Content-Type: application/json");
if (!empty($_POST)) {
    $data = $_POST;
} else {
    $jsonData = file_get_contents("php://input");
    $data = json_decode($jsonData, true) ?? [];
}
if (empty($data['action']) || empty($data['table'])) {
    http_response_code(400);
    //echo json_encode(["success" => 0, "message" => "Action and table name are required"]);
    exit;
}


$action = $data['action'];

$table = $data['table'];
$primaryKey = $data['primary_key'] ?? 'slno';

switch (trim($action)) {

    case 'fetch':
        $filters = $data['filters'] ?? [];
        $columns = $data['columns'] ?? '*';

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
            http_response_code(500);
            echo json_encode(["success" => 0, "message" => $e->getMessage()]);
        }
        break;

    case 'insert':
        $targetDir = "uploads/{$table}/";

        // Ensure the directory exists
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        // Handle file upload if present
        if (!empty($_FILES["file"])) {

            $extension = pathinfo($_FILES["file"]["name"], PATHINFO_EXTENSION);
            $uniqueName = uniqid('upload_', true) . '.' . $extension;
            $targetFilePath = $targetDir . $uniqueName; 
            $fileType = strtolower(pathinfo($targetFilePath, PATHINFO_EXTENSION));

            // Allowed file formats
            $allowedFormats = ['mp4', 'avi', 'mov', 'mkv', 'jpg', 'jpeg', 'png', 'pdf'];
            $maxFileSize = 50 * 1024 * 1024; // 50 MB

            // Validate file size
            if ($_FILES["file"]["size"] > $maxFileSize) {
                echo json_encode(["success" => 0, "message" => "Error: File size exceeds the maximum limit of 50MB."]);
                exit;
            }

            // Validate MIME type
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $_FILES["file"]["tmp_name"]);
            finfo_close($finfo);

            if (!in_array($fileType, $allowedFormats)) {
                echo json_encode(["success" => 0, "message" => "Invalid file format. Only MP4, AVI, MOV, MKV, JPG, JPEG, PNG, and PDF are allowed."]);
                exit;
            }
            // Move the uploaded file to the target directory
            if (!move_uploaded_file($_FILES["file"]["tmp_name"], $targetFilePath)) {
                echo json_encode(["success" => 0, "message" => "Error uploading file."]);
                exit;
            }
            if ($data['upload_type'] == 'video') {

                $data['attachment_name'] = $targetFilePath;
                $data['mimetype'] = $mimeType;
            } else if ($data['upload_type'] == 'file') {
                $data['file_attachment_name'] = $targetFilePath;
                $data['mimetype'] = $mimeType;
            } else if ($data['upload_type'] == 'image') {
                $data['image_attachment_name'] = $targetFilePath;
                $data['mimetype'] = $mimeType;
            } else {
                http_response_code(400);
                echo json_encode(["success" => 0, "message" => "invalid upload type"]);
                exit;
            }
            // Add the file path to the data array

        }

        // Prepare the INSERT query
        $excludedKeys = ['action', 'table', 'upload_type', 'file', 'primary_key'];
        $filteredData = array_diff_key($data, array_flip($excludedKeys));

        // Construct the columns string
        $columns = implode(", ", array_keys($filteredData));
        $placeholders = ":" . implode(", :", array_keys($filteredData));
        $sql = "INSERT INTO $table ($columns) VALUES ($placeholders)";

        $data_exception = array_diff_key($data, array_flip($excludedKeys));

        try {
            $stmt = $dipr_read_db->prepare($sql);
            foreach ($data_exception as $key => $value) {
                $stmt->bindValue(":$key", $value);
            }
            if ($stmt->execute()) {
                http_response_code(201);
                echo json_encode(["success" => 1, "message" => "Data inserted successfully"]);
            } else {
                http_response_code(500);
                //echo json_encode(["success" => 0, "message" => "Database execution failed"]);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["success" => 0, "message" => $e->getMessage()]);
        }
        break;

        case 'update':
            
            // Handle file upload if present
            if (!empty($_FILES["file"])) {
                $targetDir = "uploads/{$table}/";
                $existing_file = $data['existing_file'] ?? null;
        
                // Delete existing file if it exists
                if ($existing_file && file_exists($existing_file)) {
                    if (!unlink($existing_file)) {
                        echo json_encode(["success" => 0, "message" => "Error deleting existing file."]);
                        exit;
                    }
                }
        
                // Ensure the directory exists
                if (!is_dir($targetDir)) {
                    if (!mkdir($targetDir, 0755, true)) {
                        echo json_encode(["success" => 0, "message" => "Error creating upload directory."]);
                        exit;
                    }
                }
        
                // Validate file details
                $extension = pathinfo($_FILES["file"]["name"], PATHINFO_EXTENSION);
                $uniqueName = uniqid('upload_', true) . '.' . $extension;
                $targetFilePath = $targetDir . $uniqueName;
                $fileType = strtolower(pathinfo($targetFilePath, PATHINFO_EXTENSION));
                $allowedFormats = [
                    'mp4' => 'video/mp4', 'avi' => 'video/x-msvideo', 'mov' => 'video/quicktime', 'mkv' => 'video/x-matroska',
                    'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'pdf' => 'application/pdf'
                ];
                $maxFileSize = ($data['upload_type'] === 'video') ? 50 * 1024 * 1024 : 5 * 1024 * 1024;
        
                if ($_FILES["file"]["size"] > $maxFileSize) {
                    echo json_encode(["success" => 0, "message" => "Error: File size exceeds the maximum limit."]);
                    exit;
                }
        
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_file($finfo, $_FILES["file"]["tmp_name"]);
                finfo_close($finfo);
        
                if (!array_key_exists($fileType, $allowedFormats) || $mimeType !== $allowedFormats[$fileType]) {
                    echo json_encode(["success" => 0, "message" => "Invalid file format."]);
                    exit;
                }
        
                // Move the uploaded file
                if (!move_uploaded_file($_FILES["file"]["tmp_name"], $targetFilePath)) {
                    echo json_encode(["success" => 0, "message" => "Error uploading file."]);
                    exit;
                }
        
                // Update data array with file details
                if ($data['upload_type'] === 'video') {
                    $data['attachment_name'] = $targetFilePath;
                    $data['mimetype'] = $mimeType;
                } elseif ($data['upload_type'] === 'file') {
                    $data['file_attachment_name'] = $targetFilePath;
                    $data['mimetype'] = $mimeType;
                } elseif ($data['upload_type'] === 'image') {
                    $data['image_attachment_name'] = $targetFilePath;
                    $data['mimetype'] = $mimeType;
                } else {
                    http_response_code(400);
                    echo json_encode(["success" => 0, "message" => "Invalid upload type."]);
                    exit;
                }
            }
        
            // Exclude specific keys from the data array
            $excludedKeys = ['action', 'table', 'upload_type', 'file', 'primary_key','id','slno','ID','SLNO'];
            $filteredData = array_diff_key($data, array_flip($excludedKeys));
        
            // Prepare the UPDATE query
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
                
                error_log("Database error: " . $e->getMessage());
                http_response_code(500);
                echo json_encode(["success" => 0, "message" => "Error.", "error"=>$e->getMessage()]);
            }
            break;
    case 'delete':
        if (empty($data[$primaryKey])) {
            http_response_code(400);
            //echo json_encode(["success" => 0, "message" => "Primary key is required"]);
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
                http_response_code(500);
                //echo json_encode(["success" => 0, "message" => "Database execution failed"]);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["success" => 0, "message" => $e->getMessage()]);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(["success" => 0, "message" => $action]);
}
