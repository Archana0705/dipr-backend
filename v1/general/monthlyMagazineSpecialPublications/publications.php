<?php
require_once('../../../helper/header.php');
require_once('../../../helper/db/dipr_read.php');

header("Access-Control-Allow-Methods: POST");
header("Content-Type: application/json");

// Read JSON input
$jsonData = file_get_contents("php://input");
$data = json_decode($jsonData, true);

// Check if 'action' is provided
if (empty($data['action'])) {
    http_response_code(400);
    echo json_encode(["success" => 0, "message" => "Action is required"]);
    exit;
}

$action = $data['action'];

switch ($action) {
    case 'fetch':
        // Fetch all records
        $sql = "SELECT SLNO, FILE_NAME, MIME_TYPE, FILENAME, CREATED_ON, CREATED_BY, UPDATED_ON, UPDATED_BY, LANGUAGE FROM TN_PUBLICATIONS";
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
        // Insert a new record
        if (empty($data['file_name']) || empty($data['mime_type']) || empty($data['filename']) || empty($data['language'])) {
            http_response_code(400);
            echo json_encode(["success" => 0, "message" => "Required fields are missing"]);
            exit;
        }

        $sql = "INSERT INTO TN_PUBLICATIONS (FILE_NAME, MIME_TYPE, FILENAME, CREATED_ON, CREATED_BY, UPDATED_ON, UPDATED_BY, LANGUAGE)
                VALUES (:p_FILE_NAME, :p_MIME_TYPE, :p_FILENAME, NOW(), :p_CREATED_BY, NOW(), :p_UPDATED_BY, :p_LANGUAGE)";

        try {
            $stmt = $dipr_read_db->prepare($sql);
            $stmt->bindValue(':p_FILE_NAME', $data['file_name'], PDO::PARAM_STR);
            $stmt->bindValue(':p_MIME_TYPE', $data['mime_type'], PDO::PARAM_STR);
            $stmt->bindValue(':p_FILENAME', $data['filename'], PDO::PARAM_STR);
            $stmt->bindValue(':p_CREATED_BY', $data['created_by'] ?? 'admin', PDO::PARAM_STR);
            $stmt->bindValue(':p_UPDATED_BY', $data['updated_by'] ?? 'admin', PDO::PARAM_STR);
            $stmt->bindValue(':p_LANGUAGE', $data['language'], PDO::PARAM_STR);


            if ($stmt->execute()) {
                http_response_code(201);
                echo json_encode(["success" => 1, "message" => "Data inserted successfully"]);
            } else {
                error_log("Execution failed: " . print_r($stmt->errorInfo(), true));
                http_response_code(500);
                echo json_encode(["success" => 0, "message" => "Database execution failed"]);
            }
        } catch (Exception $e) {
            error_log("Error inserting data: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(["success" => 0, "message" => "Internal server error"]);
        }
        break;

    case 'update':
        // Update an existing record
        if (empty($data['slno']) || empty($data['file_name']) || empty($data['mime_type']) || empty($data['filename']) || empty($data['language'])) {
            http_response_code(400);
            echo json_encode(["success" => 0, "message" => "Required fields are missing"]);
            exit;
        }

        $sql = "UPDATE TN_PUBLICATIONS
                SET FILE_NAME = :p_FILE_NAME,
                    MIME_TYPE = :p_MIME_TYPE,
                    FILENAME = :p_FILENAME,
                    UPDATED_ON = NOW(),
                    UPDATED_BY = :p_UPDATED_BY,
                    LANGUAGE = :p_LANGUAGE
                WHERE SLNO = :p_SLNO";

        try {
            $stmt = $dipr_read_db->prepare($sql);
            $stmt->bindParam(':p_SLNO', $data['slno'], PDO::PARAM_INT);

            $stmt->bindValue(':p_FILE_NAME', $data['file_name'], PDO::PARAM_STR);
            $stmt->bindValue(':p_MIME_TYPE', $data['mime_type'], PDO::PARAM_STR);
            $stmt->bindValue(':p_FILENAME', $data['filename'], PDO::PARAM_STR);
            $stmt->bindValue(':p_UPDATED_BY', $data['updated_by'], PDO::PARAM_STR);
            $stmt->bindValue(':p_LANGUAGE', $data['language'], PDO::PARAM_STR);


            if ($stmt->execute()) {
                http_response_code(200);
                echo json_encode(["success" => 1, "message" => "Data updated successfully"]);
            } else {
                error_log("Execution failed: " . print_r($stmt->errorInfo(), true));
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
        // Delete a record
        if (empty($data['slno'])) {
            http_response_code(400);
            echo json_encode(["success" => 0, "message" => "SLNO is required"]);
            exit;
        }

        $sql = "DELETE FROM TN_PUBLICATIONS WHERE SLNO = :p_SLNO";

        try {
            $stmt = $dipr_read_db->prepare($sql);
            $stmt->bindParam(':p_SLNO', $data['slno'], PDO::PARAM_INT);

            if ($stmt->execute()) {
                http_response_code(200);
                echo json_encode(["success" => 1, "message" => "Data deleted successfully"]);
            } else {
                error_log("Execution failed: " . print_r($stmt->errorInfo(), true));
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
