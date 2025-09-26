<?php
require_once('../../../helper/header.php');
header("Access-Control-Allow-Methods: POST");
require_once('../../../helper/db/dipr_read.php');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get raw JSON input
    $jsonData = file_get_contents("php://input");
    $data = json_decode($jsonData, true); // Decode JSON into an associative array

    if (empty($data['sno'])) {
        http_response_code(400);
        echo json_encode(["success" => 0, "message" => "Required fields are missing"]);
        exit;
    }

    $sno = $data['sno'];
   

    $sql = "DELETE FROM TN_TAMILARASU_MONTHLY_PUBLICATION_PRICE_DETAILS
WHERE SLNO = :p55_SLNO";

    try {
        $stmt = $dipr_read_db->prepare($sql);
        $stmt->bindParam(':p55_SLNO', $sno);
       

        if ($stmt->execute()) {
            http_response_code(201);
            echo json_encode(["success" => 1, "message" => "Data Deleted successfully"]);
        } else {
            error_log("Execution failed: " . print_r($stmt->errorInfo(), true));
            http_response_code(500);
            echo json_encode(["success" => 0, "message" => "Database execution failed"]);
        }
    } catch (Exception $e) {
        error_log("Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(["success" => 0, "message" => "Internal server error"]);
    }
} else {
    http_response_code(405);
    echo json_encode(["success" => 0, "message" => "Method Not Allowed"]);
}
