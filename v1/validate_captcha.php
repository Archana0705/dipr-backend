<?php
// If session_id is sent, resume that session
if (isset($_POST['session_id'])) {
    session_id($_POST['session_id']);
}
session_start();

header("Content-Type: application/json");

if (!isset($_SESSION['captcha'])) {
    echo json_encode(["success" => false, "message" => "CAPTCHA expired or missing"]);
    exit;
}

$user_input = $_POST['captcha'] ?? '';
if (strcasecmp($user_input, $_SESSION['captcha']) === 0) {
    echo json_encode(["success" => true, "message" => "Captcha matched"]);
} else {
    echo json_encode(["success" => false, "message" => "Captcha does not match"]);
}
