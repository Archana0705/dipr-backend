<?php
require_once('../../helper/header.php');
header("Content-Type: application/json"); // Set content type to JSON
header("Access-Control-Allow-Methods: POST");
require_once('../../helper/db/dipr_read.php');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        // Ensure database connection exists
        if (!$dipr_read_db) {
            throw new Exception("Database connection failed.");
        }

        // Get raw JSON input
        $jsonData = file_get_contents("php://input");
        $data = json_decode($jsonData, true); // Decode JSON into an associative array

        // Validate JSON data
        if (!isset($data['id'])) {
            http_response_code(400);
            echo json_encode(["success" => 0, "message" => "Invalid input. All fields are required."]);
            exit;
        }

        // Assign variables
        $id = trim($data['id']);
      
        // SQL Query
        $sql = "DELETE from govt_attachments 
        where id = :P9_ID;";

        $stmt = $dipr_read_db->prepare($sql);

        // Bind parameters
        $stmt->bindParam(':P9_ID', $id, PDO::PARAM_INT);
      
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
