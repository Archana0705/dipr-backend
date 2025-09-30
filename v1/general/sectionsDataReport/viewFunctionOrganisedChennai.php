<?php
require_once('../../../helper/header.php');
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
            $sql = "SELECT SLNO,
       DAY_MONTH,
       FUNCTIONS,
       CREATED_BY,
       CREATED_ON,
       UPDATED_BY,
       UPDATED_ON,
       DISTRICT,
       LANGUAGE
FROM TN_GOVT_FUNCTIONS_CHENNAI";

    $stmt = $dipr_read_db->prepare($sql);

    if ($stmt->execute()) {
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($result) {
            http_response_code(200);
            $data = array("success" => 1, "message" => "Data retrived successfully", "data" => $result);
        } else {
            http_response_code(200);
            $data = array("success" => 2, "message" => "No data Found");
        }
        echo json_encode($data);
        die();
    } else {
        error_log(print_r($stmt->errorInfo(), true));
        http_response_code(500);
        $data = array("success" => 3, "message" => "Problem in executing the query in db");
        echo json_encode($data);
        die();
    }
} else {
    http_response_code(405);
    $data = array("success" => 0, "message" => "Method Not Allowed");
    echo json_encode($data);
    die();
}
