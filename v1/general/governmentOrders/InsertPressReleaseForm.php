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

        // Validate JSON data (Ensure all required fields are present)
        if (!isset($data['V_NAME'], $data['V_DESC'], $data['V_URL'], 
                    $data['Current_User'], $data['V_DATE'], $data['LANGUAGE'])) {
            http_response_code(400);
            echo json_encode(["success" => 0, "message" => "Invalid input. All fields are required."]);
            exit;
        }

   
        $V_NAME = trim($data['V_NAME']);
        $V_DESC = trim($data['V_DESC']);
        $V_URL = trim($data['V_URL']);
        $Current_User = trim($data['Current_User']);
        $Current_Date = date("Y-m-d H:i:s"); // Auto-generate current date
        $V_DATE = trim($data['V_DATE']);
        $LANGUAGE = substr(trim($data['LANGUAGE']), 0, 2); // Ensure it's within VARCHAR(2) limit

        // Correct SQL Query with placeholders
        $sql = "INSERT INTO TN_GOVT_ORDERS(ORDER_NAME, ORDER_FILE_NAME, UPLOADED_BY, UPLOADED_ON, 
                ORDER_NO, MIMETYPE, GO_DATE, LANGUAGE) 
                VALUES(:p_ORDER_NAME, :p_ORDER_FILE_NAME, :Current_Users, :Current_Dates, 
                :p_ORDER_NO, :p_MIMETYPE, :p_GO_DATE, :p_LANGUAGE)";

        $stmt = $dipr_write_db->prepare($sql);

        // Bind parameters correctly
        $stmt->bindParam(':p_ORDER_NAME', $V_NAME, PDO::PARAM_STR); // Correct parameter binding
        $stmt->bindParam(':p_ORDER_FILE_NAME', $V_URL, PDO::PARAM_STR); // Correct parameter binding
        $stmt->bindParam(':Current_Users', $Current_User, PDO::PARAM_STR);
        $stmt->bindParam(':Current_Dates', $Current_Date, PDO::PARAM_STR);
        $stmt->bindParam(':p_ORDER_NO', $V_DESC, PDO::PARAM_STR); // Assuming you want to use `V_DESC` for ORDER_NO
        $stmt->bindParam(':p_MIMETYPE', $V_DESC, PDO::PARAM_STR); // Assuming you want to use `V_DESC` for MIMETYPE
        $stmt->bindParam(':p_GO_DATE', $V_DATE, PDO::PARAM_STR);
        $stmt->bindParam(':p_LANGUAGE', $LANGUAGE, PDO::PARAM_STR);

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
