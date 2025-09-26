<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
date_default_timezone_set('Asia/Calcutta');
require_once('auth.php');
// Get current timestamp
$startTime = microtime(true);


$environment = 'development';
$debug = true;

if ($debug && $environment == 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 'On');
} elseif ($debug && $environment == 'production') {
    die("Disable Debug to false in Production Environment");
} elseif (!$debug && $environment == 'production') {
    error_reporting(0);
} else {
    error_reporting(0);
}
function encrypt($data) {
    $type = $_SERVER['HTTP_X_APP_TYPE'] ?? null;
    $tipe = $_SERVER['HTTP_X_APP_TIPE'] ?? null;
    // print_r($data);
    if ($type == 'te$t' || $tipe == 't@st') {
        return $data;
    } else {
        $secretKey = 'xmcK|fbngp@!71L$'; 
        $binaryKey = hash('sha256', $secretKey, true); 
        $iv = random_bytes(16); 

        $cipherText = openssl_encrypt(json_encode($data), 'aes-256-cbc', $binaryKey, OPENSSL_RAW_DATA, $iv);
        if ($cipherText === false) {
            die("Encryption failed: " . openssl_error_string());
        }

        $combinedData = $iv . $cipherText;
        // echo $combinedData;
        return base64_encode($combinedData);
    }
}

function decryptData($encryptedData) {
    $type = $_SERVER['HTTP_X_APP_TYPE'] ?? null;
    if ($type == 'te$t') {
        return $encryptedData;
    } else {
        $secretKey = 'xmcK|fbngp@!71L$'; 
        $binaryKey = hash('sha256', $secretKey, true); 
        $decodedData = base64_decode($encryptedData);
        if ($decodedData === false) {
            die("Base64 decode failed.");
        }

        $iv = substr($decodedData, 0, 16);
        $cipherText = substr($decodedData, 16);

        $decrypted = openssl_decrypt($cipherText, 'aes-256-cbc', $binaryKey, OPENSSL_RAW_DATA, $iv);
        if ($decrypted === false) {
            die("Decryption failed: " . openssl_error_string());
        }

        return json_decode($decrypted, true);
    }
}