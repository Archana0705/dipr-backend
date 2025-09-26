<?php
require_once('../../helper/header.php');
header("Access-Control-Allow-Methods: GET");
require_once('../../helper/db/dipr_read.php');

if ($_SERVER["REQUEST_METHOD"] == "GET") {
    $lang = isset($_GET['lang']) ? urldecode($_GET['lang']) : 'ta';
    $sql = "SELECT id, form_name, attachment_name, uploaded_by, uploaded_on, mime_type, language FROM tn_forms;";
    try {
        $stmt = $dipr_read_db->prepare($sql);
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
