<?php
require_once('../../../helper/header.php');
header("Access-Control-Allow-Methods: GET");
require_once('../../../helper/db/dipr_read.php');

if ($_SERVER["REQUEST_METHOD"] == "GET") {
    $language = $_GET['lang'] ?? null;
    $date = $_GET['date'] ?? null;

    if ($date) {
        // Pass date parameter safely
        $sql = "SELECT * FROM GOVT_ATTACHMENT_VIDEO_fn(:P0_LANGUAGE, CAST(:P12_DATE AS DATE));";
    } else {
        // If no date provided, call with NULL
        $sql = "SELECT * FROM GOVT_ATTACHMENT_VIDEO_fn(:P0_LANGUAGE, NULL);";
    }

    try {
        $stmt = $dipr_read_db->prepare($sql);
        $stmt->bindParam(':P0_LANGUAGE', $language, PDO::PARAM_STR);

        if ($date) {
            $stmt->bindParam(':P12_DATE', $date, PDO::PARAM_STR);
        }

        if ($stmt->execute()) {
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($result)) {
                http_response_code(200);
                echo json_encode([
                    "success" => 1,
                    "message" => "Data retrieved successfully",
                    "data" => $result
                ], JSON_UNESCAPED_UNICODE);
            } else {
                http_response_code(200);
                echo json_encode([
                    "success" => 2,
                    "message" => "No data found"
                ]);
            }
        } else {
            error_log("Execution failed: " . print_r($stmt->errorInfo(), true));
            http_response_code(500);
            echo json_encode([
                "success" => 3,
                "message" => "Problem in executing the query in db"
            ]);
        }
    } catch (Exception $e) {
        error_log("Error during video retrieval: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            "success" => 0,
            "message" => "An error occurred while processing the request."
        ]);
    }
} else {
    http_response_code(405);
    echo json_encode([
        "success" => 0,
        "message" => "Method Not Allowed"
    ]);
}
