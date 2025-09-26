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
        if (!isset( $data['COURSE_NAME'], $data['YEAR'], $data['CREATED_BY'], $data['LANGUAGE'])) {
            http_response_code(400);
            echo json_encode(["success" => 0, "message" => "Invalid input. All required fields must be provided."]);
            exit;
        }

        // Assign variables

        $COURSE_NAME = trim($data['COURSE_NAME']);
        $YEAR = (int) trim($data['YEAR']);
        $CREATED_BY = trim($data['CREATED_BY']);
        $CREATED_ON = date("Y-m-d H:i:s"); // Auto-generate current timestamp
        $LANGUAGE = substr(trim($data['LANGUAGE']), 0, 2); // Ensure it's within VARCHAR(2) limit

        // SQL Query with placeholders
        $sql = "INSERT INTO TN_DEGREE_COURSES ( COURSE_NAME, YEAR, CREATED_BY, LANGUAGE) 
                VALUES ( :COURSE_NAME, :YEAR, :CREATED_BY, :LANGUAGE)";

        $stmt = $dipr_write_db->prepare($sql);

        // Bind parameters correctly

        $stmt->bindParam(':COURSE_NAME', $COURSE_NAME, PDO::PARAM_STR);
        $stmt->bindParam(':YEAR', $YEAR, PDO::PARAM_INT);
        $stmt->bindParam(':CREATED_BY', $CREATED_BY, PDO::PARAM_STR);
        $stmt->bindParam(':LANGUAGE', $LANGUAGE, PDO::PARAM_STR);

        // Execute query
        if ($stmt->execute()) {
            http_response_code(201); // HTTP 201 Created
            echo json_encode(["success" => 1, "message" => "Record inserted successfully."]);
        } else {
            throw new Exception("Failed to insert record.");
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
