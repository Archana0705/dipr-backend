<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

require_once('../helper/header.php');
require_once('../helper/db/dipr_read.php');

header("Access-Control-Allow-Methods: POST");
header("Content-Type: application/json");

$jsonData = file_get_contents("php://input");
$json_data = json_decode($jsonData, true);
if (!$json_data && !empty($_POST)) {
    $json_data = $_POST;
}

$encryptedData = $json_data['data'] ?? null;
if (!$encryptedData) {
    http_response_code(400);
    echo json_encode(["success" => 0, "message" => "Missing encrypted data"]);
    exit;
}

$data = decryptData($encryptedData);
$userId = $data['user_id'] ?? null;

if (!$userId) {
    http_response_code(400);
    echo json_encode(["success" => 0, "message" => "Missing user ID"]);
    exit;
}

try {
    // Delete session from database
    $stmt = $dipr_read_db->prepare("DELETE FROM user_sessions WHERE user_id = :uid");
    $stmt->execute([':uid' => $userId]);

    // Destroy PHP session
    session_destroy();

    echo json_encode([
        "success" => 1,
        "message" => "Logged out successfully"
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => 0,
        "message" => "Logout failed: " . $e->getMessage()
    ]);
}
