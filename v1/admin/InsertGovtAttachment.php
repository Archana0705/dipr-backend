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
        if (!isset($data['p_id'], $data['p_file_attachment'], $data['Current_user'], 
                    $data['p_Filename'], $data['p_mimetype'], $data['p_p_date'], $data['p_language'])) {
            http_response_code(400);
            echo json_encode(["success" => 0, "message" => "Invalid input. All fields are required."]);
            exit;
        }

        // Assign variables
        $p_id = trim($data['p_id']);
        $p_file_attachment = $data['p_file_attachment']; // Assuming it's base64-encoded or a path
        $current_user = trim($data['Current_user']);
        $current_date = date("Y-m-d H:i:s"); // Auto-generate current date
        $p_Filename = trim($data['p_Filename']);
        $p_mimetype = trim($data['p_mimetype']);
        $p_p_date = trim($data['p_p_date']);
        $p_language = trim($data['p_language']);

        // SQL Query
        $sql = "INSERT INTO govt_attachments(
                        file_attachment_name, created_by, created_on, file_name, mimetype, p_date, language)
                VALUES(:p_file_attachment, :Current_user, :current_date, :p_Filename, :p_mimetype, :p_p_date, :p_language)";

        $stmt = $dipr_read_db->prepare($sql);

        // Bind parameters
        $stmt->bindParam(':p_file_attachment', $p_file_attachment, PDO::PARAM_STR); // Modify if handling files differently
        $stmt->bindParam(':Current_user', $current_user, PDO::PARAM_STR);
        $stmt->bindParam(':current_date', $current_date, PDO::PARAM_STR);
        $stmt->bindParam(':p_Filename', $p_Filename, PDO::PARAM_STR);
        $stmt->bindParam(':p_mimetype', $p_mimetype, PDO::PARAM_STR);
        $stmt->bindParam(':p_p_date', $p_p_date, PDO::PARAM_STR);
        $stmt->bindParam(':p_language', $p_language, PDO::PARAM_STR);

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
