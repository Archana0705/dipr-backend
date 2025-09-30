<?php
require_once('../../../helper/header.php');
header("Access-Control-Allow-Methods: GET");
require_once('../../../helper/db/dipr_read.php');
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
  
    $sql = "SELECT ID,
       PRESS_FILE_NAME,
       UPLOADED_BY,
       UPLOADED_DATE,
       PRESS_RELEASE_NO,
       PRESS_NAME,
       language,
       pr_date
  from TN_GOVT_PRESS_RELEASE";
    try {
        $stmt = $dipr_read_db->prepare($sql);
     
        if ($stmt->execute()) {
            $data_count = $stmt->rowCount();
            if ($data_count > 0) {
                $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                http_response_code(200);
                $data = array("success" => 1, "message" => "Data retrieved successfully", "data" => $result);
                echo json_encode($data, JSON_UNESCAPED_UNICODE);
            } else {
                error_log("No rows found in the query");
                http_response_code(200);
                $data = array("success" => 2, "message" => "No data found");
                echo json_encode($data);
            }
        } else {
            error_log("Execution failed: " . print_r($stmt->errorInfo(), true));
            http_response_code(500);
            $data = array("success" => 3, "message" => "Problem in executing the query in db");
            echo json_encode($data);
        }
    } catch (Exception $e) {
        error_log("Error during press release retrieval: " . $e->getMessage());
        http_response_code(500);
        $data = array("success" => 0, "message" => "An error occurred while processing the request.");
        echo json_encode($data);
    }
} else {
    http_response_code(405);
    $data = array("success" => 0, "message" => "Method Not Allowed");
    echo json_encode($data);
}
