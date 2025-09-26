<?php
// Include necessary files
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

        // Get raw input
        $rawInput = file_get_contents("php://input");

        // Decrypt incoming data
        $data = decryptData($rawInput); // decryptData will handle JSON decoding

        if (!is_array($data)) {
            throw new Exception("Decrypted data is not valid JSON.");
        }

        // Validate required fields
        $requiredFields = [
            'PRESS_FILE_NAME', 
            'UPLOADED_BY', 
            'PRESS_RELEASE_NO', 
            'PRESS_NAME', 
            'MIMETYPE', 
            'PR_DATE', 
            'LANGUAGE'
        ];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                http_response_code(400);
                echo json_encode(["success" => 0, "message" => "Invalid input. Missing field: $field"]);
                exit;
            }
        }

        // Assign variables and sanitize input
        $PRESS_FILE_NAME = trim($data['PRESS_FILE_NAME']);
        $UPLOADED_BY = trim($data['UPLOADED_BY']);
        $PRESS_RELEASE_NO = trim($data['PRESS_RELEASE_NO']);
        $PRESS_NAME = trim($data['PRESS_NAME']);
        $MIMETYPE = trim($data['MIMETYPE']);
        $PR_DATE = date('Y-m-d', strtotime(trim($data['PR_DATE']))); 
        $LANGUAGE = substr(trim($data['LANGUAGE']), 0, 2); // Ensure it's within VARCHAR(2) limit
        $UPLOADED_DATE = date("Y-m-d H:i:s");

        // Validate PR_DATE format (YYYY-MM-DD)
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $PR_DATE)) {
            http_response_code(400);
            echo json_encode(["success" => 0, "message" => "Invalid date format for PR_DATE. Expected YYYY-MM-DD."]);
            exit;
        }

        // Prepare SQL query with placeholders
        $sql = "INSERT INTO TN_GOVT_PRESS_RELEASE (
                    PRESS_FILE_NAME, 
                    UPLOADED_BY, 
                    UPLOADED_DATE, 
                    PRESS_RELEASE_NO, 
                    PRESS_NAME, 
                    MIMETYPE, 
                    PR_DATE, 
                    LANGUAGE
                ) VALUES (
                    :PRESS_FILE_NAME, 
                    :UPLOADED_BY, 
                    :UPLOADED_DATE, 
                    :PRESS_RELEASE_NO, 
                    :PRESS_NAME, 
                    :MIMETYPE, 
                    :PR_DATE, 
                    :LANGUAGE
                )";

        $stmt = $dipr_write_db->prepare($sql);

        // Bind parameters
        $stmt->bindParam(':PRESS_FILE_NAME', $PRESS_FILE_NAME, PDO::PARAM_STR);
        $stmt->bindParam(':UPLOADED_BY', $UPLOADED_BY, PDO::PARAM_STR);
        $stmt->bindParam(':UPLOADED_DATE', $UPLOADED_DATE, PDO::PARAM_STR);
        $stmt->bindParam(':PRESS_RELEASE_NO', $PRESS_RELEASE_NO, PDO::PARAM_STR);
        $stmt->bindParam(':PRESS_NAME', $PRESS_NAME, PDO::PARAM_STR);
        $stmt->bindParam(':MIMETYPE', $MIMETYPE, PDO::PARAM_STR);
        $stmt->bindParam(':PR_DATE', $PR_DATE, PDO::PARAM_STR);
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
        echo json_encode(["success" => 0, "message" => "Internal Server Error: " . $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(["success" => 0, "message" => "Method Not Allowed. Only POST is allowed."]);
}
