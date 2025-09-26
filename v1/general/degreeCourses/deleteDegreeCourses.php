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
        if (!isset($data['SLNO'])) {
            http_response_code(400);
            echo json_encode(["success" => 0, "message" => "Invalid input. All required fields must be provided."]);
            exit;
        }

        // Assign variables

        $SLNO = trim($data['SLNO']);


        // SQL Query with placeholders
                $sql = "DELETE
                            FROM TN_DEGREE_COURSES
                            WHERE SLNO = :SLNO;";

        $stmt = $dipr_write_db->prepare($sql);

        // Bind parameters correctly

        $stmt->bindParam(':SLNO', $SLNO, PDO::PARAM_INT);

        // Execute query
        if ($stmt->execute()) {
            http_response_code(201); // HTTP 201 Created
            echo json_encode(["success" => 1, "message" => "Record Deleted successfully."]);
        } else {
            throw new Exception("Failed to Delete record.");
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
