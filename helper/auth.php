<?php
function respondWithError($statusCode, $message) {
    http_response_code($statusCode);
    echo json_encode(["success" => 0, "message" => $message]);
    exit;
}

$app_key = $_SERVER['HTTP_X_APP_KEY'] ?? null;
$app_name = $_SERVER['HTTP_X_APP_NAME'] ?? null;

$expected_app_key = 'dipr';
$expected_app_name = 'dipr';



if (!$app_key) {
    respondWithError(404, "APP Key Missing");
}

if (!$app_name) {
    respondWithError(404, "APP Name Missing");
}

// if (!isset($allowedApps[$app_key]) || $allowedApps[$app_key] !== $app_name) {
//     respondWithError(400, "Invalid App Key or App Name");
// }
if ($app_key !== $expected_app_key || $app_name !== $expected_app_name) {
    respondWithError(400, "Invalid App Key or App Name");
}
?>