<?php
require_once('../../helper/header.php');
require_once('../../helper/db/dipr_read.php');

header("Access-Control-Allow-Methods: POST");
header("Content-Type: application/json");

// Read JSON input
$jsonData = file_get_contents("php://input");
$data = json_decode($jsonData, true);

if (empty($data['action'])) {
    http_response_code(400);
    echo json_encode(["success" => 0, "message" => "Action is required"]);
    exit;
}

$action = $data['action'];

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
                echo json_encode(["success" => 0, "message" => "Database execution failed"]);
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
                echo json_encode(["success" => 0, "message" => "Database execution failed"]);
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
            echo json_encode(["success" => 0, "message" => "SLNO is required"]);
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
