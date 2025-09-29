<?php
session_start();
$captcha_text = substr(str_shuffle("ABCDEFGHJKLMNPQRSTUVWXYZ23456789"), 0, 6);
$_SESSION['captcha'] = $captcha_text;

// Return the session ID as well
header("Content-Type: application/json");
echo json_encode([
    "captcha" => $captcha_text, 
    "session_id" => session_id()
]);
