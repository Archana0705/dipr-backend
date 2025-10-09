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
        // Validate JSON data (Ensure all required fields are present)
        if (!isset( $data['YEAR'], $data['NO_OF_BENEFICIARIES'], $data['AMOUNT'], 
                    $data['CREATED_BY'], $data['LANGUAGE'])) {
            http_response_code(400);
            echo json_encode(["success" => 0, "message" => "Invalid input. Required fields are missing."]);
            exit;
        }

        // Assign variables
        $YEAR = (int) trim($data['YEAR']);
        $NO_OF_BENEFICIARIES = (int) trim($data['NO_OF_BENEFICIARIES']);
        $AMOUNT = trim($data['AMOUNT']);
        $CREATED_BY = trim($data['CREATED_BY']);
        $CREATED_ON = date("Y-m-d H:i:s"); // Auto-generate current timestamp
        $LANGUAGE = substr(trim($data['LANGUAGE']), 0, 2); // Ensure it's within VARCHAR(2) limit

        // SQL Query with placeholders
        $sql = "INSERT INTO TN_CINE_WORKERS_BENEFICIARIES_DETAILS 
                ( YEAR, NO_OF_BENEFICIARIES, AMOUNT, CREATED_BY, CREATED_ON, LANGUAGE)
                VALUES 
                (:p_YEAR, :p_NO_OF_BENEFICIARIES, :p_AMOUNT, :p_CREATED_BY, :p_CREATED_ON, :p_LANGUAGE)";

        $stmt = $dipr_write_db->prepare($sql);

        // Bind parameters correctly
        $stmt->bindParam(':p_YEAR', $YEAR, PDO::PARAM_INT);
        $stmt->bindParam(':p_NO_OF_BENEFICIARIES', $NO_OF_BENEFICIARIES, PDO::PARAM_INT);
        $stmt->bindParam(':p_AMOUNT', $AMOUNT, PDO::PARAM_STR);
        $stmt->bindParam(':p_CREATED_BY', $CREATED_BY, PDO::PARAM_STR);
        $stmt->bindParam(':p_CREATED_ON', $CREATED_ON, PDO::PARAM_STR);
        $stmt->bindParam(':p_LANGUAGE', $LANGUAGE, PDO::PARAM_STR);

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
