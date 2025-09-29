<?php
// Resume correct session if sid passed
if (isset($_GET['sid'])) {
    session_id($_GET['sid']);
}
session_start();

$captcha_text = $_SESSION['captcha'] ?? 'ERROR';

// Generate image
header("Content-Type: image/png");
$width = 150;
$height = 50;
$image = imagecreate($width, $height);

$bg  = imagecolorallocate($image, 255, 255, 255);
$fg  = imagecolorallocate($image, 0, 0, 0);

imagestring($image, 5, 40, 15, $captcha_text, $fg);
imagepng($image);
imagedestroy($image);
