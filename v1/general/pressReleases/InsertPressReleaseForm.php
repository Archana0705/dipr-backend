<?php
// Include necessary files
require_once('../../../helper/header.php');
header("Content-Type: application/json");
header("Access-Control-Allow-Methods: POST");
require_once('../../../helper/db/dipr_write.php');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        if (!$dipr_write_db) {
            throw new Exception("Database connection failed.");
        }

        // Get raw input
        $rawInput = file_get_contents("php://input");

        // Decrypt incoming data
        $data = decryptData($rawInput);

        if (!is_array($data)) {
            throw new Exception("Decrypted data is not valid JSON.");
        }

        // Required fields
        $requiredFields = [
            'PRESS_FILE_NAME', 
            'UPLOADED_BY', 
            'PRESS_RELEASE_NO', 
            'PRESS_NAME', 
            'MIMETYPE', 
            'PR_DATE', 
            'LANGUAGE'
        ];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                http_response_code(400);
                echo json_encode(["success" => 0, "message" => "Invalid input. Missing field: $field"]);
                exit;
            }
        }

        // Assign values
        $fileBase64   = $data['PRESS_FILE_NAME']; // base64 file data
        $UPLOADED_BY  = trim($data['UPLOADED_BY']);
        $PRESS_RELEASE_NO = trim($data['PRESS_RELEASE_NO']);
        $PRESS_NAME   = trim($data['PRESS_NAME']);
        $MIMETYPE     = trim($data['MIMETYPE']);
        $PR_DATE      = date('Y-m-d', strtotime(trim($data['PR_DATE'])));
        $LANGUAGE     = substr(trim($data['LANGUAGE']), 0, 2);
        $UPLOADED_DATE = date("Y-m-d H:i:s");

        // Validate PR_DATE
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $PR_DATE)) {
            http_response_code(400);
            echo json_encode(["success" => 0, "message" => "Invalid date format for PR_DATE. Expected YYYY-MM-DD."]);
            exit;
        }

        // === Save file to uploads folder ===
        $uploadDir = __DIR__ . '/../uploads/'; // /general/uploads relative to this script
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        // Create unique filename to prevent overwrite
        $extension = pathinfo($PRESS_NAME, PATHINFO_EXTENSION);
        $safeFileName = preg_replace("/[^a-zA-Z0-9_\.-]/", "_", pathinfo($PRESS_NAME, PATHINFO_FILENAME));
        $finalFileName = $safeFileName . "_" . time() . "." . $extension;
        $filePath = $uploadDir . $finalFileName;

        // Decode and save
        $fileData = base64_decode($fileBase64);
        if ($fileData === false) {
            throw new Exception("Failed to decode base64 file data.");
        }

        if (file_put_contents($filePath, $fileData) === false) {
            throw new Exception("Failed to save uploaded file.");
        }

        // Update PRESS_FILE_NAME to stored filename
        $PRESS_FILE_NAME = $finalFileName;

        // === Insert metadata into DB ===
        $sql = "INSERT INTO TN_GOVT_PRESS_RELEASE (
                    PRESS_FILE_NAME, 
                    UPLOADED_BY, 
                    UPLOADED_DATE, 
                    PRESS_RELEASE_NO, 
                    PRESS_NAME, 
                    MIMETYPE, 
                    PR_DATE, 
                    LANGUAGE
                ) VALUES (
                    :PRESS_FILE_NAME, 
                    :UPLOADED_BY, 
                    :UPLOADED_DATE, 
                    :PRESS_RELEASE_NO, 
                    :PRESS_NAME, 
                    :MIMETYPE, 
                    :PR_DATE, 
                    :LANGUAGE
                )";

        $stmt = $dipr_write_db->prepare($sql);
        $stmt->bindParam(':PRESS_FILE_NAME', $PRESS_FILE_NAME, PDO::PARAM_STR);
        $stmt->bindParam(':UPLOADED_BY', $UPLOADED_BY, PDO::PARAM_STR);
        $stmt->bindParam(':UPLOADED_DATE', $UPLOADED_DATE, PDO::PARAM_STR);
        $stmt->bindParam(':PRESS_RELEASE_NO', $PRESS_RELEASE_NO, PDO::PARAM_STR);
        $stmt->bindParam(':PRESS_NAME', $PRESS_NAME, PDO::PARAM_STR);
        $stmt->bindParam(':MIMETYPE', $MIMETYPE, PDO::PARAM_STR);
        $stmt->bindParam(':PR_DATE', $PR_DATE, PDO::PARAM_STR);
        $stmt->bindParam(':LANGUAGE', $LANGUAGE, PDO::PARAM_STR);

        if ($stmt->execute()) {
            http_response_code(201);
            echo json_encode([
                "success" => 1, 
                "message" => "Data inserted successfully.",
                "file"    => $finalFileName
            ]);
        } else {
            throw new Exception("Failed to insert data.");
        }

    } catch (Exception $e) {
        error_log("Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(["success" => 0, "message" => "Internal Server Error: " . $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(["success" => 0, "message" => "Method Not Allowed. Only POST is allowed."]);
}
