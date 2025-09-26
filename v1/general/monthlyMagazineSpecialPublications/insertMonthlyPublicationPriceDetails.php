<?php
require_once('../../../helper/header.php');
header("Access-Control-Allow-Methods: POST");
require_once('../../../helper/db/dipr_read.php');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get raw JSON input
    $jsonData = file_get_contents("php://input");
    $data = json_decode($jsonData, true); // Decode JSON into an associative array

    if (empty($data['lang']) || empty($data['monthly_issue']) || empty($data['price'])) {
        http_response_code(400);
        echo json_encode(["success" => 0, "message" => "Required fields are missing"]);
        exit;
    }

    $lang = $data['lang'];
    $monthly_issue = $data['monthly_issue'];
    $price = $data['price'];
    $created_by = $data['created_by'] ?? 'admin'; // Default value if not provided
    $updated_by = $data['updated_by'] ?? 'admin'; // Default value if not provided
    $created_on = date('Y-m-d H:i:s');
    $updated_on = date('Y-m-d H:i:s');

    $sql = "INSERT INTO TN_TAMILARASU_MONTHLY_PUBLICATION_PRICE_DETAILS(
        MONTHLY_ISSUE, PRICE, CREATED_ON, CREATED_BY, UPDATED_BY, UPDATED_ON, LANGUAGE
    ) VALUES (
        :monthly_issue, :price, :created_on, :created_by, :updated_by, :updated_on, :lang
    )";

    try {
        $stmt = $dipr_read_db->prepare($sql);
        $stmt->bindParam(':monthly_issue', $monthly_issue);
        $stmt->bindParam(':price', $price);
        $stmt->bindParam(':created_on', $created_on);
        $stmt->bindParam(':created_by', $created_by);
        $stmt->bindParam(':updated_by', $updated_by);
        $stmt->bindParam(':updated_on', $updated_on);
        $stmt->bindParam(':lang', $lang);

        if ($stmt->execute()) {
            http_response_code(201);
            echo json_encode(["success" => 1, "message" => "Data inserted successfully"]);
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
