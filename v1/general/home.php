<?php
require_once('../../helper/header.php');
header("Access-Control-Allow-Methods: GET");
require_once('../../helper/db/dipr_read.php');

if ($_SERVER["REQUEST_METHOD"] == "GET") {
    $lang = $_GET['lang'] ?? 'en';
    $type = $_GET['list_type'] ?? 'Main Menu';

    $sql = "SELECT id, list_name, page_name, list_type, file_name, mime_type 
            FROM govt_navigation_list 
            WHERE list_type = :listtype 
            AND language = COALESCE(:P0_LANGUAGE, 'en') 
            ORDER BY id;";

    $stmt = $dipr_read_db->prepare($sql);
    $stmt->bindParam(':listtype', $type);
    $stmt->bindParam(':P0_LANGUAGE', $lang);

    if ($stmt->execute()) {
        $data_count = $stmt->rowCount();
        if ($data_count > 0) {
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            http_response_code(200);
            echo json_encode(["success" => 1, "message" => "Data retrieved successfully", "data" => $result]);
        } else {
            http_response_code(200);
            echo json_encode(["success" => 2, "message" => "No data found"]);
        }
    } else {
        error_log(print_r($stmt->errorInfo(), true));
        http_response_code(500);
        echo json_encode(["success" => 3, "message" => "Error executing query"]);
    }
} else {
    http_response_code(405);
    echo json_encode(["success" => 0, "message" => "Method not allowed"]);
}
