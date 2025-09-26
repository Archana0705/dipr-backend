<?php
require_once('../../helper/header.php');
header("Access-Control-Allow-Methods: POST");
require_once('../../helper/db/dipr_read.php');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
   
    try {
        // Ensure database connection exists
        if (!$dipr_read_db) {
            throw new Exception("Database connection failed.");
        }

        // SQL Query
        $sql = "SELECT 
    id,
    v_name,
    v_desc,
    v_url,
    created_on,
    created_by,
    updated_by,
    updated_on,
    v_date,
    language
FROM govt_attachment_video
";

        $stmt = $dipr_read_db->prepare($sql);

        if ($stmt->execute()) {
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC); // Fetch all rows

            if (!empty($result)) {
                http_response_code(200);
                echo json_encode([
                    "success" => 1,
                    "message" => "Data fetched successfully.",
                    "data" => $result
                ]);
            } else {
                http_response_code(404);
                echo json_encode(["success" => 0, "message" => "No data found."]);
            }
        } else {
            throw new Exception("Query execution failed.");
        }
    } catch (Exception $e) {
        error_log("Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(["success" => 0, "message" => "An error occurred while processing the request."]);
    }
} else {
    http_response_code(405);
    echo json_encode(["success" => 0, "message" => "Method Not Allowed. Only POST is allowed."]);
}
