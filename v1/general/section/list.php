<?php

require_once('../../../helper/header.php');
header("Access-Control-Allow-Methods: GET");
require_once('../../../helper/db/dipr_read.php');

if ($_SERVER["REQUEST_METHOD"] == "GET") {
   
    $sql = "SELECT ID, LIST_NAME, PAGE_NAME, encode(LIST_IMG::bytea, 'base64') AS LIST_IMG, LIST_TYPE, FILE_NAME, MIME_TYPE, LANGUAGE  FROM govt_navigation_list;";

    try {
        $stmt = $dipr_read_db->prepare($sql);
    
        if ($stmt->execute()) {
            $data_count = $stmt->rowCount();
            if ($data_count > 0) {
                $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                http_response_code(200);
                $data = array("success" => 1, "message" => "Data retrieved successfully", "data" => $result);
                ob_clean();
                echo json_encode($data, JSON_UNESCAPED_UNICODE);
            } else {
                http_response_code(200);
                $data = array("success" => 2, "message" => "No data found");
                echo json_encode($data);
            }
        } else {
            error_log(print_r($stmt->errorInfo(), true));
            http_response_code(500);
            $data = array("success" => 3, "message" => "Problem in executing the query in db");
            echo json_encode($data);
        }
    } catch (Exception $e) {
        error_log("Error during navigation list retrieval: " . $e->getMessage());
        http_response_code(500);
        $data = array("success" => 0, "message" => "An error occurred while processing the request.");
        echo json_encode($data);
    }
} else {
    http_response_code(405);
    $data = array("success" => 0, "message" => "Method Not Allowed");
    echo json_encode($data);
}
