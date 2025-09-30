<?php
require_once('../../../helper/header.php');
require_once('../../../helper/db/dipr_write.php');

header("Content-Type: application/json");
session_start();
function checkRateLimit($ip, $maxRequests = 1, $windowSeconds = 10) {
    $key = "rate_limit_" . md5($ip);
    $now = time();
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = [];
    }

    // Keep only requests within the time window
    $_SESSION[$key] = array_filter($_SESSION[$key], fn($t) => ($t > $now - $windowSeconds));
    $_SESSION[$key][] = $now;

    if (count($_SESSION[$key]) > $maxRequests) {
        http_response_code(429); // Too Many Requests
        echo json_encode(["success" => 0, "message" => "Rate limit exceeded. Try later."]);
        exit;
    }
}

// Call at the top of your script

checkRateLimit($_SERVER['REMOTE_ADDR']);
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
        if (!isset($data['SLNO'])) {
            http_response_code(400);
            echo json_encode(["success" => 0, "message" => "Invalid input. All fields are required."]);
            exit;
        }

        // Assign values
        $SLNO = trim($data['SLNO']);
       

        // SQL Query (Corrected)
        $sql = "DELETE FROM TN_GOVT_FUNCTIONS_CHENNAI
WHERE SLNO = :p_SLNO;";

        $stmt = $dipr_write_db->prepare($sql);

        // Bind parameters
        $stmt->bindParam(':p_SLNO', $SLNO, PDO::PARAM_INT);
    
        // Execute query
        if ($stmt->execute()) {
            http_response_code(201); // HTTP 201 Created
            echo json_encode(["success" => 1, "message" => "Data Deleted."]);
        } else {
            throw new Exception("Failed to delete data.");
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
