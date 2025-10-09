<?php
require_once('../../../helper/header.php');
header("Content-Type: application/json"); // Set content type to JSON
header("Access-Control-Allow-Methods: POST");
require_once('../../../helper/db/dipr_write.php');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        // Ensure database connection exists
        if (!$dipr_write_db) {
            throw new Exception("Database connection failed.");
        }

        // Get raw JSON input
        $jsonData = file_get_contents("php://input");
        $data = json_decode($jsonData, true); // Decode JSON into an associative array
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
        // Validate JSON data
        if (!isset($data['p_ID'], $data['SECTIONS_NAME'], $data['DESCRIPTION'], $data['LANGUAGE'], $data['UPDATED_BY'])) {
            http_response_code(400);
            echo json_encode(["success" => 0, "message" => "Invalid input. All fields are required."]);
            exit;
        }

        // Assign variables
        $p_ID = trim($data['p_ID']);
        $SECTIONS_NAME = trim($data['SECTIONS_NAME']);
        $DESCRIPTION = trim($data['DESCRIPTION']);
        $UPDATED_BY = trim($data['UPDATED_BY']);
        $UPDATED_ON = date("Y-m-d H:i:s"); // Auto-generate current date
        $LANGUAGE = substr(trim($data['LANGUAGE']), 0, 2); // Ensure within VARCHAR(2) limit

        // Corrected SQL Query
        $sql = "UPDATE TN_SECTIONS
                    SET SECTIONS_NAME = :SECTIONS_NAME,
                        DESCRIPTION = :DESCRIPTION,
                        UPDATED_BY = :UPDATED_BY,
                        UPDATED_ON = :UPDATED_ON,
                        LANGUAGE = :LANGUAGE
                    WHERE ID = :p_ID";

        $stmt = $dipr_write_db->prepare($sql);

        // Bind parameters correctly
        $stmt->bindParam(':p_ID', $p_ID, PDO::PARAM_INT);
        $stmt->bindParam(':SECTIONS_NAME', $SECTIONS_NAME, PDO::PARAM_STR);
        $stmt->bindParam(':DESCRIPTION', $DESCRIPTION, PDO::PARAM_STR);
        $stmt->bindParam(':UPDATED_BY', $UPDATED_BY, PDO::PARAM_STR);
        $stmt->bindParam(':UPDATED_ON', $UPDATED_ON, PDO::PARAM_STR);
        $stmt->bindParam(':LANGUAGE', $LANGUAGE, PDO::PARAM_STR);

        // Execute query
        if ($stmt->execute()) {
            http_response_code(200); // HTTP 200 OK
            echo json_encode(["success" => 1, "message" => "Data updated successfully."]);
        } else {
            throw new Exception("Failed to update data.");
        }
    } catch (Exception $e) {
        error_log("Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(["success" => 0, "message" => $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(["success" => 0, "message" => "Method Not Allowed. Only POST is allowed."]);
}
