<?php
require_once('../../../helper/header.php');
require_once('../../../helper/db/dipr_read.php');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
            $sql = "SELECT 
            ID, 
            LIST_NAME, 
            PAGE_NAME, 
            -- LIST_IMG, 
            LIST_TYPE, 
            FILE_NAME, 
            MIME_TYPE, 
            LANGUAGE
        FROM 
            GOVT_NAVIGATION_LIST";

    $stmt = $dipr_read_db->prepare($sql);

    if ($stmt->execute()) {
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($result) {
            http_response_code(200);
            $data = array("success" => 1, "message" => "Data retrived successfully", "data" => $result);
        } else {
            http_response_code(200);
            $data = array("success" => 2, "message" => "No data Found");
        }
        echo json_encode($data);
        die();
    } else {
        error_log(print_r($stmt->errorInfo(), true));
        http_response_code(500);
        $data = array("success" => 3, "message" => "Problem in executing the query in db");
        echo json_encode($data);
        die();
    }
} else {
    http_response_code(405);
    $data = array("success" => 0, "message" => "Method Not Allowed");
    echo json_encode($data);
    die();
}
