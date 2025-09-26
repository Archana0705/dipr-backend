<?php
require_once('../../../helper/header.php');
require_once('../../../helper/db/dipr_read.php');

header("Content-Type: application/json");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        // Ensure database connection exists
        if (!$dipr_read_db) {
            throw new Exception("Database connection failed.");
        }

        // Get raw JSON input
        $jsonData = file_get_contents("php://input");
        $data = json_decode($jsonData, true); // Decode JSON into an associative array

        // Validate required fields
        if (!isset($data['SLNO'], $data['DAY_MONTH'], $data['FUNCTIONS'], $data['DISTRICT'], $data['LANGUAGE'])) {
            http_response_code(400);
            echo json_encode(["success" => 0, "message" => "Invalid input. All fields are required."]);
            exit;
        }

        // Assign values
        $SLNO = trim($data['SLNO']);
        $DAY_MONTH = trim($data['DAY_MONTH']);
        $FUNCTIONS = trim($data['FUNCTIONS']);
        $DISTRICT = trim($data['DISTRICT']);
        $LANGUAGE = substr(trim($data['LANGUAGE']), 0, 2); // Ensure it's within VARCHAR(2) limit
        $CURRENT_USER = "System"; // Replace with actual logged-in user if available
        $CURRENT_DATE = date("Y-m-d H:i:s"); // Auto-generate current date

        // SQL Query (Corrected)
        $sql = "INSERT INTO TN_GOVT_FUNCTIONS_CHENNAI (
                    SLNO, 
                    DAY_MONTH, 
                    FUNCTIONS, 
                    CREATED_BY, 
                    CREATED_ON, 
                    DISTRICT, 
                    LANGUAGE
                ) VALUES (
                    :SLNO, 
                    :DAY_MONTH, 
                    :FUNCTIONS, 
                    :CREATED_BY, 
                    :CREATED_ON, 
                    :DISTRICT, 
                    :LANGUAGE
                )";

        $stmt = $dipr_read_db->prepare($sql);

        // Bind parameters
        $stmt->bindParam(':SLNO', $SLNO, PDO::PARAM_INT);
        $stmt->bindParam(':DAY_MONTH', $DAY_MONTH, PDO::PARAM_STR);
        $stmt->bindParam(':FUNCTIONS', $FUNCTIONS, PDO::PARAM_STR);
        $stmt->bindParam(':CREATED_BY', $CURRENT_USER, PDO::PARAM_STR);
        $stmt->bindParam(':CREATED_ON', $CURRENT_DATE, PDO::PARAM_STR);
        $stmt->bindParam(':DISTRICT', $DISTRICT, PDO::PARAM_STR);
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
?>
