<?php
require_once('../../../helper/header.php');
require_once('../../../helper/db/dipr_write.php');

header("Content-Type: application/json");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        // Ensure database connection exists
        if (!$dipr_write_db) {
            throw new Exception("Database connection failed.");
        }

        // Get raw JSON input
        $jsonData = file_get_contents("php://input");
        $data = json_decode($jsonData, true); // Decode JSON into an associative array

        // Validate required fields
        if (!isset($data['SLNO'], $data['DAY_MONTH'], $data['FUNCTIONS'], $data['DISTRICT'], $data['LANGUAGE'],$data['CREATED_BY'])) {
            http_response_code(400);
            echo json_encode(["success" => 0, "message" => "Invalid input. All fields are required."]);
            exit;
        }

        // Assign values
        $SLNO = trim($data['SLNO']);
        $DAY_MONTH = trim($data['DAY_MONTH']);
        $FUNCTIONS = trim($data['FUNCTIONS']);
        $DISTRICT = trim($data['DISTRICT']);
        $LANGUAGE = trim($data['LANGUAGE']);
        $CREATED_BY =trim($data['CREATED_BY']); // Replace with actual user if available
        $CREATED_ON = date("Y-m-d H:i:s"); // Current date

        // SQL Query (Fixed)
        $sql = "UPDATE TN_GOVT_FUNCTIONS_CHENNAI
                SET DAY_MONTH  = :p_DAY_MONTH,
                    FUNCTIONS  = :p_FUNCTIONS,
                    CREATED_BY = :p_CREATED_BY,
                    CREATED_ON = :p_CREATED_ON,
                    DISTRICT   = :p_DISTRICT,
                    LANGUAGE   = :p_LANGUAGE
                WHERE SLNO = :p_SLNO;";

        $stmt = $dipr_write_db->prepare($sql);

        // Bind parameters
        $stmt->bindParam(':p_SLNO', $SLNO, PDO::PARAM_INT);
        $stmt->bindParam(':p_DAY_MONTH', $DAY_MONTH, PDO::PARAM_STR);
        $stmt->bindParam(':p_FUNCTIONS', $FUNCTIONS, PDO::PARAM_STR);
        $stmt->bindParam(':p_CREATED_BY', $CREATED_BY, PDO::PARAM_STR);
        $stmt->bindParam(':p_CREATED_ON', $CREATED_ON, PDO::PARAM_STR);
        $stmt->bindParam(':p_DISTRICT', $DISTRICT, PDO::PARAM_STR);
        $stmt->bindParam(':p_LANGUAGE', $LANGUAGE, PDO::PARAM_STR);

        // Execute query
        if ($stmt->execute()) {
            http_response_code(200); // HTTP 200 OK
            echo json_encode(["success" => 1, "message" => "Data updated successfully."]);
        } else {
            throw new Exception("Failed to update data.");
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
