<?php
require_once('../../../helper/header.php');
header("Access-Control-Allow-Methods: GET");
require_once('../../../helper/db/dipr_read.php');

if ($_SERVER["REQUEST_METHOD"] == "GET") {
    $lang = isset($_GET['lang']) ? $_GET['lang'] : 'en';
    $sql = "SELECT  id, name, designation, phone, language, sequence, created_by, created_on, updated_by, updatde_on FROM  tn_corporations WHERE  language = :P0_LANGUAGE ORDER BY  sequence ASC;";
    $stmt = $dipr_read_db->prepare($sql);
    $stmt->bindParam(':P0_LANGUAGE', $lang, PDO::PARAM_STR);
    if ($stmt->execute()) {
        $data_count = $stmt->rowCount();
        if ($data_count > 0) {
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            http_response_code(200);
            $data = array("success" => 1, "message" => "Data retrived successfully", "data" => $result);
            echo json_encode($data);
            die();
        } else {
            http_response_code(200);
            $data = array("success" => 2, "message" => "No data Found");
            echo json_encode($data);
            die();
        }
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
