<?php
require_once('../../../helper/header.php');
header("Access-Control-Allow-Methods: GET");
require_once('../../../helper/db/dipr_read.php');

if ($_SERVER["REQUEST_METHOD"] == "GET") {
    $language = isset($_GET['lang']) ? $_GET['lang'] : null;
    $select_date = isset($_GET['date']) ? $_GET['date'] : null;
    $sql = "SELECT * FROM tn_govt_press_release WHERE language = :P0_LANGUAGE  AND (pr_date = TO_DATE(:P33_SELECT_DATE, 'MM/DD/YYYY')  OR (:P33_SELECT_DATE IS NULL AND pr_date BETWEEN CURRENT_DATE - INTERVAL '10 days' AND CURRENT_DATE)) ORDER BY pr_date DESC;";
    try {
        $stmt = $dipr_read_db->prepare($sql);
        $stmt->bindParam(':P0_LANGUAGE', $language, PDO::PARAM_STR);
        if ($select_date) {
            $stmt->bindParam(':P33_SELECT_DATE', $select_date, PDO::PARAM_STR);
        } else {
            $stmt->bindValue(':P33_SELECT_DATE', null, PDO::PARAM_NULL);
        }
        if ($stmt->execute()) {
            $data_count = $stmt->rowCount();
            if ($data_count > 0) {
                $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                http_response_code(200);
                $data = array("success" => 1, "message" => "Data retrieved successfully", "data" => $result);
                echo json_encode($data, JSON_UNESCAPED_UNICODE);
            } else {
                error_log("No rows found in the query");
                http_response_code(200);
                $data = array("success" => 2, "message" => "No data found");
                echo json_encode($data);
            }
        } else {
            error_log("Execution failed: " . print_r($stmt->errorInfo(), true));
            http_response_code(500);
            $data = array("success" => 3, "message" => "Problem in executing the query in db");
            echo json_encode($data);
        }
    } catch (Exception $e) {
        error_log("Error during press release retrieval: " . $e->getMessage());
        http_response_code(500);
        $data = array("success" => 0, "message" => "An error occurred while processing the request.");
        echo json_encode($data);
    }
} else {
    http_response_code(405);
    $data = array("success" => 0, "message" => "Method Not Allowed");
    echo json_encode($data);
}
