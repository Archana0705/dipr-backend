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
        // Validate JSON data (Ensure all required fields are present)
        if (!isset($data['ID'])) {
            http_response_code(400);
            echo json_encode(["success" => 0, "message" => "Invalid input. All fields are required."]);
            exit;
        }

        // Assign variables
        $p_ID = trim($data['ID']);
       
        // Correct SQL Query with placeholders
        $sql = "DELETE FROM TN_GOVT_PRESS_RELEASE
WHERE ID = :p16_ID";

        $stmt = $dipr_write_db->prepare($sql);

        // Bind parameters correctly
        $stmt->bindParam(':p16_ID', $p_ID, PDO::PARAM_INT);
        

        // Execute query
        if ($stmt->execute()) {
            http_response_code(201); // HTTP 201 Created
            echo json_encode(["success" => 1, "message" => "Data Deleted."]);
        } else {
            throw new Exception("Failed to insert data.");
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
