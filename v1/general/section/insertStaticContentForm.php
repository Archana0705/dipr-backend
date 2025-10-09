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
        if (!isset($data['p_ID'], $data['V_NAME'], $data['V_DESC'], $data['Current_User'], $data['LANGUAGE'])) {
            http_response_code(400);
            echo json_encode(["success" => 0, "message" => "Invalid input. All fields are required."]);
            exit;
        }

        // Assign variables
        $p_ID = trim($data['p_ID']);
        $V_NAME = trim($data['V_NAME']);
        $V_DESC = trim($data['V_DESC']);
        $Current_User = trim($data['Current_User']);
        $Current_Date = date("Y-m-d H:i:s"); // Auto-generate current date
        $LANGUAGE = substr(trim($data['LANGUAGE']), 0, 2); // Ensure within VARCHAR(2) limit

        // Corrected SQL Query
        $sql = "INSERT INTO TN_SECTIONS (
                      ID, 
                      SECTIONS_NAME, 
                      DESCRIPTION, 
                      CREATED_BY, 
                      CREATED_ON, 
                      LANGUAGE
                  ) VALUES (
                      :p_ID, 
                      :V_NAME, 
                      :V_DESC, 
                      :Current_User, 
                      :Current_Date, 
                      :LANGUAGE
                  )";

        $stmt = $dipr_write_db->prepare($sql);

        // Bind parameters correctly
        $stmt->bindParam(':p_ID', $p_ID, PDO::PARAM_INT);
        $stmt->bindParam(':V_NAME', $V_NAME, PDO::PARAM_STR);
        $stmt->bindParam(':V_DESC', $V_DESC, PDO::PARAM_STR);
        $stmt->bindParam(':Current_User', $Current_User, PDO::PARAM_STR);
        $stmt->bindParam(':Current_Date', $Current_Date, PDO::PARAM_STR);
        $stmt->bindParam(':LANGUAGE', $LANGUAGE, PDO::PARAM_STR);

        // Execute query
        if ($stmt->execute()) {
            http_response_code(201); // HTTP 201 Created
            echo json_encode(["success" => 1, "message" => "Data inserted successfully."]);
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
