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

        // Validate JSON data (Ensure required fields are present)
        if (!isset($data['SLNO'])) {
            http_response_code(400);
            echo json_encode(["success" => 0, "message" => "Invalid inputs."]);
            exit;
        }

        // Assign variable
        $SLNO = (int) trim($data['SLNO']);

        // SQL Query with placeholder
        $sql = "DELETE FROM TN_SPECIAL_ISSUES_PRICE_DETAILS WHERE SLNO = :p_SLNO";

        $stmt = $dipr_write_db->prepare($sql);

        // Bind parameter
        $stmt->bindParam(':p_SLNO', $SLNO, PDO::PARAM_INT);

        // Execute query
        if ($stmt->execute()) {
            if ($stmt->rowCount() > 0) {
                http_response_code(200); // HTTP 200 OK
                echo json_encode(["success" => 1, "message" => "Record deleted successfully."]);
            } else {
                http_response_code(404); // HTTP 404 Not Found
                echo json_encode(["success" => 0, "message" => "No record found"]);
            }
        } else {
            throw new Exception("Failed to delete record.");
        }
    } catch (Exception $e) {
        error_log("Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(["success" => 0, "message" => $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(["success" => 0, "message" => "Method Not Allowed. Only DELETE is allowed."]);
}
?>
