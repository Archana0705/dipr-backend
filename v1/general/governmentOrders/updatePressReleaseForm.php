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
        if (!isset($data['p_ID'], $data['V_NAME'], $data['V_URL'], $data['Current_User'], $data['V_DESC'], 
                    $data['V_DATE'], $data['LANGUAGE'])) {
            http_response_code(400);
            echo json_encode(["success" => 0, "message" => "Invalid input. All fields are required."]);
            exit;
        }

        // Assign variables
        $p_ID = trim($data['p_ID']);
        $V_NAME = trim($data['V_NAME']);
        $V_URL = trim($data['V_URL']);
        $Current_User = trim($data['Current_User']);
        $Current_Date = date("Y-m-d H:i:s"); // Auto-generate current date
        $V_DESC = trim($data['V_DESC']);
        $V_DATE = trim($data['V_DATE']);
        $LANGUAGE = substr(trim($data['LANGUAGE']), 0, 2); // Ensure it's within VARCHAR(2) limit

        // Correct SQL Query with placeholders
        $sql = "UPDATE TN_GOVT_ORDERS
                SET ORDER_NAME = :p_ORDER_NAME 
                    , ORDER_FILE_NAME = :p_ORDER_FILE_NAME 
                    , UPLOADED_BY = :Current_User 
                    , UPLOADED_ON = :Current_Date 
                    , ORDER_NO = :p_ORDER_NO 
                    , MIMETYPE = :p_MIMETYPE 
                    , GO_DATE = :p_GO_DATE 
                    , LANGUAGE = :p_LANGUAGE
                WHERE ID = :p_ID";

        $stmt = $dipr_write_db->prepare($sql);

        // Bind parameters correctly
        $stmt->bindParam(':p_ID', $p_ID, PDO::PARAM_INT);
        $stmt->bindParam(':p_ORDER_NAME', $V_NAME, PDO::PARAM_STR);
        $stmt->bindParam(':p_ORDER_FILE_NAME', $V_URL, PDO::PARAM_STR);
        $stmt->bindParam(':Current_User', $Current_User, PDO::PARAM_STR);
        $stmt->bindParam(':Current_Date', $Current_Date, PDO::PARAM_STR);
        $stmt->bindParam(':p_ORDER_NO', $V_DESC, PDO::PARAM_STR);  // Assuming you want to use V_DESC for ORDER_NO
        $stmt->bindParam(':p_MIMETYPE', $V_DESC, PDO::PARAM_STR);    // Assuming you want to use V_DESC for MIMETYPE
        $stmt->bindParam(':p_GO_DATE', $V_DATE, PDO::PARAM_STR);
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
