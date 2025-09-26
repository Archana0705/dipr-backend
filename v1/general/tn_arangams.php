<?php
require_once('../../helper/header.php');
require_once('../../helper/db/dipr_read.php');

header("Access-Control-Allow-Methods: POST");
header("Content-Type: application/json");

$jsonData = file_get_contents("php://input");
$data = json_decode($jsonData, true);



if (empty($data['action'])) {
    http_response_code(400);
    echo json_encode(["success" => 0, "message" => "Action is required"]);
    exit;
}

$action = $data['action'];
$table = "TN_ARANGAMS";
$primaryKey = "slno";

switch ($action) {
    case 'fetch':
      
        try {
            $stmt = $pdo->prepare("SELECT * FROM $table WHERE language = ?");
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
        $targetDir = "uploads/videos/tn_arangam/";

        // Ensure the directory exists
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true); // Create the directory with stricter permissions
        }

        // Get the uploaded file details
        $fileName = basename($_FILES["video"]["name"]);
        $targetFilePath = $targetDir . $fileName;
        $fileType = strtolower(pathinfo($targetFilePath, PATHINFO_EXTENSION));

        // Allowed video formats
        $allowedFormats = ['mp4', 'avi', 'mov', 'mkv'];
        $maxFileSize = 50 * 1024 * 1024; // 50 MB

        // Validate file size
        if ($_FILES["video"]["size"] > $maxFileSize) {
            echo json_encode(["success" => 0, "message" => "Error: File size exceeds the maximum limit of 50MB."]);
            exit;
        }

        // Validate MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $_FILES["video"]["tmp_name"]);
        finfo_close($finfo);
        
        if (!in_array($mimeType, ['video/mp4', 'video/x-msvideo', 'video/quicktime', 'video/x-matroska'])) {
            echo json_encode(["success" => 0, "message" => "Invalid file format. Only MP4, AVI, MOV, and MKV are allowed."]);
            exit;
        }

        // Move the uploaded file to the target directory
        if (move_uploaded_file($_FILES["video"]["tmp_name"], $targetFilePath)) {
            // Prepare and execute the INSERT query
            $sql = "INSERT INTO $table (SLNO, ARANGAM, PLACE, CREATED_BY, CREATED_ON, UPDATED_BY, UPDATED_ON, DISTRICT, VIDEO_NAME, VIDEO_ATTACHMENT_NAME, MIME_TYPE, LANGUAGE) 
                        VALUES (:p_SLNO, :p_ARANGAM, :p_PLACE, :p_CREATED_BY, NOW(), :p_UPDATED_BY, NOW(), :p_DISTRICT, :p_VIDEO_NAME, :p_VIDEO_ATTACHMENT_NAME, :p_MIME_TYPE, :p_LANGUAGE)";
            $params = [
                ':p_SLNO' => $data['slno'],
                ':p_ARANGAM' => $data['arangam'],
                ':p_PLACE' => $data['place'],
                ':p_CREATED_BY' => $data['created_by'],
                ':p_UPDATED_BY' => $data['updated_by'],
                ':p_DISTRICT' => $data['district'],
                ':p_VIDEO_NAME' => $data['video_name'],
                ':p_VIDEO_ATTACHMENT_NAME' => $fileName,
                ':p_MIME_TYPE' => $mimeType,
                ':p_LANGUAGE' => $data['language']
            ];

            try {
                $stmt = $dipr_read_db->prepare($sql);
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
                echo json_encode(["success" => 0, "message" => "Internal server error"]);
            }
        } else {
            echo json_encode(["success" => 0, "message" => "Error uploading video."]);
        }
        break;

    case 'update':
        if (empty($data[$primaryKey])) {
            http_response_code(400);
            echo json_encode(["success" => 0, "message" => "$primaryKey is required"]);
            exit;
        }

        $sql = "UPDATE $table 
                SET ARANGAM = :p_ARANGAM, 
                    PLACE = :p_PLACE, 
                    UPDATED_BY = :p_UPDATED_BY, 
                    UPDATED_ON = NOW(), 
                    DISTRICT = :p_DISTRICT, 
                    VIDEO_NAME = :p_VIDEO_NAME, 
                    VIDEO_ATTACHMENT_NAME = :p_VIDEO_ATTACHMENT_NAME, 
                    MIME_TYPE = :p_MIME_TYPE, 
                    LANGUAGE = :p_LANGUAGE 
                WHERE $primaryKey = :p_SLNO";
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
                echo json_encode(["success" => 0, "message" => "Database execution failed"]);
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
            echo json_encode(["success" => 0, "message" => "$primaryKey is required"]);
            exit;
        }

        $sql = "DELETE FROM $table WHERE $primaryKey = :p_SLNO";
        try {
            $stmt = $dipr_read_db->prepare($sql);
            $stmt->bindValue(':p_SLNO', $data[$primaryKey], PDO::PARAM_INT);
            if ($stmt->execute()) {
                http_response_code(200);
                echo json_encode(["success" => 1, "message" => "Data deleted successfully"]);
            } else {
                http_response_code(500);
                echo json_encode(["success" => 0, "message" => "Database execution failed"]);
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
