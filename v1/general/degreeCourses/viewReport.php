<?php
require_once('../../../helper/header.php');
require_once('../../../helper/db/dipr_read.php');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $sql = "SELECT SLNO,
       COURSE_NAME,
       YEAR,
       CREATED_BY,
       CREATED_ON,
       UPDATED_BY,
       UPDATED_ON,
       LANGUAGE
FROM TN_DEGREE_COURSES;";

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
