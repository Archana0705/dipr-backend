<?php
require_once('../../helper/header.php');
require_once('../../helper/db/dipr_write.php');

header("Access-Control-Allow-Methods: POST");
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
    $user_name = trim($_POST['user_name'] ?? '');
    $email = trim($_POST['email_id'] ?? '');

    // -----------------------
    // Validation
    // -----------------------
    if (empty($user_name) || empty($email)) {
        http_response_code(400);
        echo json_encode(["success" => 0, "message" => "All fields are required."]);
        exit;
    }

    // Stronger email validation
    if (
        !filter_var($email, FILTER_VALIDATE_EMAIL) ||
        !preg_match('/^[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}$/', $email)
    ) {
        http_response_code(400);
        echo json_encode(["success" => 0, "message" => "Invalid email format."]);
        exit;
    }

    // Lowercase for consistency
    $email = strtolower($email); 
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$allowed_domains = [
        'gmail.com',       // Gmail
        'googlemail.com',  // legacy Gmail alias
        'hotmail.com',     // Hotmail
        'outlook.com',     // Microsoft
        'live.com'         // Microsoft
    ];

    // Extract domain
    $atPos = strrpos($email, '@');
    if ($atPos === false) {
        http_response_code(400);
        echo json_encode(["success" => 0, "message" => "Invalid email address."]);
        exit;
    }
    $domain = substr($email, $atPos + 1);

    if (!in_array($domain, $allowed_domains, true)) {
        http_response_code(400);
        echo json_encode([
            "success" => 0,
            "message" => "Only Gmail and Hotmail (Microsoft) email addresses are allowed."
        ]);
        exit;
    }
    try {
        if (!$dipr_write_db) {
            throw new Exception("Database connection error.");
        }

        // -----------------------
        // Rate limiting (per email)
        // -----------------------
        $limit = 2;          // max attempts
        $window = 3600;      // 1 hour
        $cacheDir = sys_get_temp_dir() . "/rate_limit";
        if (!file_exists($cacheDir)) {
            mkdir($cacheDir, 0777, true);
        }

        $key = md5("email_" . $email);
        $file = $cacheDir . "/" . $key . ".json";

        $now = time();
        $data = ["count" => 0, "start" => $now];

        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            if (($now - $data['start']) < $window) {
                $data['count']++;
            } else {
                // reset window
                $data = ["count" => 1, "start" => $now];
            }
        } else {
            $data = ["count" => 1, "start" => $now];
        }

        if ($data['count'] > $limit) {
            http_response_code(429);
            header("Retry-After: " . ($window - ($now - $data['start'])));
            echo json_encode([
                "success" => 0,
                "message" => "Too many requests for this email. Please Try later."
            ]);
            exit;
        }

        // Save back to file
        file_put_contents($file, json_encode($data));

        // -----------------------
        // Check for duplicate subscription
        // -----------------------
        $checkSql = "SELECT COUNT(*) FROM govt_subscription WHERE email = :email";
        $checkStmt = $dipr_write_db->prepare($checkSql);
        $checkStmt->execute([':email' => $email]);

        if ($checkStmt->fetchColumn() > 0) {
            http_response_code(409);
            echo json_encode(["success" => 0, "message" => "Email already subscribed."]);
            exit;
        }

        // -----------------------
        // Insert new subscription
        // -----------------------
        $sql = "INSERT INTO govt_subscription (name, email, created_on, is_subscribe) 
                VALUES (:name, :email, CURRENT_TIMESTAMP, 'Yes')";
        $stmt = $dipr_write_db->prepare($sql);
        $stmt->bindParam(':name', $user_name, PDO::PARAM_STR);
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);

        if ($stmt->execute()) {
            http_response_code(201);
            echo json_encode(["success" => 1, "message" => "Subscription successful."]);
        } else {
            throw new Exception("Failed to insert subscription.");
        }
    } catch (Exception $e) {
        error_log("Error inserting user data: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(["success" => 0, "message" => "An error occurred while processing your request."]);
    }
} else {
    http_response_code(405);
    echo json_encode(["success" => 0, "message" => "Method Not Allowed."]);
}
?>
