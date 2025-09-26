<?php
require_once('../../helper/header.php');
header("Access-Control-Allow-Methods: GET");
require_once('../../helper/db/dipr_read.php');

if ($_SERVER["REQUEST_METHOD"] == "GET") {
    $lang = isset($_GET['lang']) ? urldecode($_GET['lang']) : null;
    $select_date = isset($_GET['date']) ? urldecode($_GET['date']) : null; // Added date parameter

    $sql = "SELECT ID, order_name, order_file_name, uploaded_by, uploaded_on, order_no, mimetype, go_date  FROM tn_govt_orders  WHERE language = :P0_LANGUAGE  AND (go_date = TO_DATE(:P73_SELECT_DATE, 'MM/DD/YYYY')  OR (:P73_SELECT_DATE IS NULL AND go_date BETWEEN CURRENT_DATE - INTERVAL '10 days' AND CURRENT_DATE)) ORDER BY go_date DESC;";

    try {
        $stmt = $dipr_read_db->prepare($sql);
        $stmt->bindParam(':P0_LANGUAGE', $lang, PDO::PARAM_STR);
        if ($select_date) {
            $stmt->bindParam(':P73_SELECT_DATE', $select_date, PDO::PARAM_STR);
        } else {
            $stmt->bindValue(':P73_SELECT_DATE', null, PDO::PARAM_NULL);
        }
        if ($stmt->execute()) {
            $data_count = $stmt->rowCount();
            if ($data_count > 0) {
                $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                http_response_code(200);
                $data = array("success" => 1, "message" => "Data retrieved successfully", "data" => $result);
                echo json_encode($data);
            } else {
                http_response_code(200);
                $data = array("success" => 2, "message" => "No data found");
                echo json_encode($data);
            }
        } else {
            http_response_code(500);
            $data = array("success" => 3, "message" => "Problem in executing the query in db");
            echo json_encode($data);
        }
    } catch (Exception $e) {
        error_log("Error during order retrieval: " . $e->getMessage());
        http_response_code(500);
        $data = array("success" => 0, "message" => "An error occurred while processing the request.");
        echo json_encode($data);
    }
} else {
    http_response_code(405);
    $data = array("success" => 0, "message" => "Method Not Allowed");
    echo json_encode($data);
}
