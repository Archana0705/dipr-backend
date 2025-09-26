<?php
function uploadFile($file, $targetDir, $allowedFormats = ['mp4', 'avi', 'mov', 'mkv'], $maxFileSize = 50 * 1024 * 1024) {
    // Ensure the directory exists
    if (!is_dir($targetDir) && !mkdir($targetDir, 0777, true)) {
        return ["success" => 0, "message" => "Failed to create upload directory."];
    }

    // Validate if file is uploaded properly
    if (!isset($file) || $file["error"] !== UPLOAD_ERR_OK) {
        return ["success" => 0, "message" => "File upload error: " . $file["error"]];
    }

    // Validate file size
    if ($file["size"] > $maxFileSize) {
        return ["success" => 0, "message" => "Error: File size exceeds the maximum limit of 50MB."];
    }

    // Get file details
    $fileName = time() . "_" . basename($file["name"]);
    $targetFilePath = $targetDir . DIRECTORY_SEPARATOR . $fileName;
    $fileType = strtolower(pathinfo($targetFilePath, PATHINFO_EXTENSION));

    // Validate file extension
    if (!in_array($fileType, $allowedFormats)) {
        return ["success" => 0, "message" => "Invalid file format. Allowed formats: " . implode(", ", $allowedFormats)];
    }

    // Validate MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file["tmp_name"]);
    finfo_close($finfo);

    $allowedMimeTypes = [
        'mp4' => 'video/mp4',
        'avi' => 'video/x-msvideo',
        'mov' => 'video/quicktime',
        'mkv' => 'video/x-matroska'
    ];

    if (!in_array($mimeType, $allowedMimeTypes)) {
        return ["success" => 0, "message" => "Invalid MIME type. Detected: $mimeType"];
    }

    // Move uploaded file to target directory
    if (!move_uploaded_file($file["tmp_name"], $targetFilePath)) {
        return ["success" => 0, "message" => "File upload failed."];
    }

    return ["success" => 1, "message" => "File uploaded successfully.", "file_path" => $targetFilePath , "file_name" => $fileName,"mime_type" => $mimeType];    
}

