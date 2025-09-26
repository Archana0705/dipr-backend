<?php
require_once('../../helper/header.php');
header("Access-Control-Allow-Methods: POST");
header("Content-Type: application/json");
require_once('../../helper/db/dipr_read.php');

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["success" => 0, "message" => "Method Not Allowed. Only POST is allowed."]);
    exit;
}

// Get raw POST data (supports JSON input)
$jsonData = file_get_contents("php://input");
$data = json_decode($jsonData, true);

// Validate input fields
if (!isset($data['user_name']) || !isset($data['password']) || empty(trim($data['user_name'])) || empty(trim($data['password']))) {
    http_response_code(400);
    echo json_encode(["success" => 0, "message" => "Username and password are required."]);
    exit;
}

// Sanitize input
$username = strtoupper(trim($data['user_name']));
$password = trim($data['password']);

try {
    $sql = "SELECT username, password, role FROM user_list WHERE username = :username OR email_id = :username";
    $stmt = $dipr_read_db->prepare($sql);
    $stmt->bindParam(':username', $username, PDO::PARAM_STR);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && password_verify($password, $user['password'])) {
        echo json_encode(["success" => 1, "message" => "Authentication successful.", "role" => $user['role']]);
    } else {
        echo json_encode(["success" => 0, "message" => "Invalid username or password."]);
    }
   

} catch (Exception $e) {
    error_log("Error during authentication: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["success" => 0, "message" => "An error occurred while processing the request."]);
}
?>
