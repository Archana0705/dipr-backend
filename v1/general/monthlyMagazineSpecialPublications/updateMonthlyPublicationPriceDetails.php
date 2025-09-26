<?php
require_once('../../../helper/header.php');
header("Access-Control-Allow-Methods: POST");
require_once('../../../helper/db/dipr_write.php');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get raw JSON input
    $jsonData = file_get_contents("php://input");
    $data = json_decode($jsonData, true); // Decode JSON into an associative array

    // Validate required fields
    if (empty($data['sno']) || empty($data['monthly_issue']) || empty($data['price']) || empty($data['language'])) {
        http_response_code(400);
        echo json_encode(["success" => 0, "message" => "Required fields are missing"]);
        exit;
    }

    // Assign variables
    $sno = $data['sno'];
    $monthly_issue = $data['monthly_issue'];
    $price = $data['price'];
    $language = $data['language'];
    $created_by = $data['created_by'] ?? 'admin'; // Default if not provided
    $updated_by = $data['updated_by'] ?? 'admin'; // Default if not provided
    $created_on = date('Y-m-d H:i:s');
    $updated_on = date('Y-m-d H:i:s');

    // SQL Query for Update
    $sql = "UPDATE TN_TAMILARASU_MONTHLY_PUBLICATION_PRICE_DETAILS
            SET MONTHLY_ISSUE = :p_MONTHLY_ISSUE,
                PRICE = :p_PRICE,
                CREATED_ON = :p_CREATED_ON,
                CREATED_BY = :p_CREATED_BY,
                UPDATED_BY = :p_UPDATED_BY,
                UPDATED_ON = :p_UPDATED_ON,
                LANGUAGE = :p_LANGUAGE
            WHERE SLNO = :p_SLNO";

    try {
        $stmt = $dipr_write_db->prepare($sql);
        $stmt->bindParam(':p_MONTHLY_ISSUE', $monthly_issue);
        $stmt->bindParam(':p_PRICE', $price);
        $stmt->bindParam(':p_CREATED_ON', $created_on);
        $stmt->bindParam(':p_CREATED_BY', $created_by);
        $stmt->bindParam(':p_UPDATED_BY', $updated_by);
        $stmt->bindParam(':p_UPDATED_ON', $updated_on);
        $stmt->bindParam(':p_LANGUAGE', $language);
        $stmt->bindParam(':p_SLNO', $sno, PDO::PARAM_INT);

        // Execute the query
        if ($stmt->execute()) {
            http_response_code(200);
            echo json_encode(["success" => 1, "message" => "Data updated successfully"]);
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
