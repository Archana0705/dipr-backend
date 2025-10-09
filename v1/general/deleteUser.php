<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Methods: POST");
require_once('../../helper/header.php');
require_once('../../helper/db/dipr_write.php');
require_once('../../helper/db/dipr_read.php');
session_start();

// 🧠 Simple rate limiting
function checkRateLimit($ip, $maxRequests = 1, $windowSeconds = 10)
{
    $key = "rate_limit_" . md5($ip);
    $now = time();

    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = [];
    }

    // Keep only recent requests
    $_SESSION[$key] = array_filter($_SESSION[$key], fn($t) => ($t > $now - $windowSeconds));
    $_SESSION[$key][] = $now;

    if (count($_SESSION[$key]) > $maxRequests) {
        http_response_code(429); // Too Many Requests
        echo json_encode(["success" => 0, "message" => "Rate limit exceeded. Try later."]);
        exit;
    }
}

checkRateLimit($_SERVER['REMOTE_ADDR']);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        // ✅ Ensure DB connection
        if (!$dipr_write_db) {
            throw new Exception("Database connection failed.");
        }

        // ✅ Read input
        $jsonData = file_get_contents("php://input");
        $input = json_decode($jsonData, true);

        // ✅ Decrypt if needed
        if (isset($input['data'])) {
            $data = decryptData($input['data']);
        } else {
            $data = $input;
        }

        // ✅ Validate required fields
        if (empty($data['ID'])) {
            http_response_code(400);
            exit;
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
        $p_ID = trim($data['ID']);
 

        // ✅ DELETE SQL
        $sql = "DELETE FROM USER_LIST WHERE ID = :p_ID";
        $stmt = $dipr_write_db->prepare($sql);
        $stmt->bindParam(':p_ID', $p_ID, PDO::PARAM_INT);

        if ($stmt->execute()) {
            http_response_code(200);
            echo json_encode(["success" => 1, "message" => "Data deleted successfully."]);
        } else {
            throw new Exception("Failed to delete data.");
        }

    } catch (Exception $e) {
        error_log("Delete Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(["success" => 0, "message" => "Server error: " . $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(["success" => 0, "message" => "Method Not Allowed. Only POST is allowed."]);
}
