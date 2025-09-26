<?php
require_once('../../../helper/header.php');
header("Access-Control-Allow-Methods: GET");
require_once('../../../helper/db/dipr_read.php');

if ($_SERVER["REQUEST_METHOD"] == "GET") {
    $sql = "SELECT id,  list_name,  page_name,  encode(list_img::bytea, 'base64') AS list_img,  list_type,  file_name,  mime_type,  language FROM govt_navigation_list;";
    $stmt = $dipr_read_db->prepare($sql);
    $result_stmt = $stmt->execute();
    if ($result_stmt) {
        $data_count = $stmt->rowCount();
        if ($data_count > 0) {
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            http_response_code(200);
            $data = array("success" => 1, "message" => "Data retrived successfully", "data" => $result); 
            ob_clean();
            echo json_encode($data, JSON_UNESCAPED_UNICODE);
        } else {
            http_response_code(200);
            $data = array("success" => 2, "message" => "No data Found");
        }
        echo json_encode($data);
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
