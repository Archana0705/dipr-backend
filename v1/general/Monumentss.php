<?php
require_once('../../helper/header.php');
require_once('../../helper/db/dipr_read.php');


header("Access-Control-Allow-Methods: POST");
header("Content-Type: application/json");

$jsonData = file_get_contents("php://input");
$data = json_decode($jsonData, true);

if (empty($data['action']) ) {
    http_response_code(400);
    echo json_encode(["success" => 0, "message" => "Invalid action or module"]);
    exit;
}

$action = $data['action'];
$table = "TN_MONUMENTS";
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
            http_response_code(500);
            echo json_encode(["success" => 0, "message" => "Internal server error"]);
        }
        break;
    
    case 'insert':
        $sql = "INSERT INTO $table (MONUMENTS, PLACE, CREATED_BY, CREATED_ON, UPDATED_BY, UPDATED_ON, DISTRICT, VIDEO_NAME, VIDEO_ATTACHMENT_NAME, MIME_TYPE, LANGUAGE)
                VALUES (:monuments, :place, :created_by, NOW(), :updated_by, NOW(), :district, :video_name, :video_attachment_name, :mime_type, :language)";
        $params = [
            ':monuments' => $data['monuments'],
            ':place' => $data['place'],
            ':created_by' => $data['created_by'] ?? 'admin',
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
                http_response_code(201);
                echo json_encode(["success" => 1, "message" => "Data inserted successfully"]);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["success" => 0, "message" => "Internal server error"]);
        }
        break;
    
    case 'update':
        if (empty($data[$primaryKey])) {
            http_response_code(400);
            echo json_encode(["success" => 0, "message" => "SLNO is required"]);
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
            echo json_encode(["success" => 0, "message" => "SLNO is required"]);
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
        echo json_encode(["success" => 0, "message" => "Invalid action"]);
}
