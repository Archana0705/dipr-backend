<?php
require_once('../../helper/header.php');
header("Access-Control-Allow-Methods: GET");
require_once('../../helper/db/dipr_read.php');

if ($_SERVER["REQUEST_METHOD"] == "GET") {
    $current_date = date('Y-m-d');
    // $current_date = '2024-10-25';
    $sql = "SELECT press_name FROM tn_govt_press_release WHERE uploaded_date = :CURRENT_DATE 
            UNION 
            SELECT press_note_name FROM tn_press_notes WHERE uploaded_on = :CURRENT_DATE;";
   
        $stmt = $dipr_read_db->prepare($sql);
        $stmt->bindParam(':CURRENT_DATE', $current_date);
        if ($stmt->execute()) {
            $data_count = $stmt->rowCount();
            if ($data_count > 0) {
                $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    http_response_code(200);
                        $data = array( "success" => 1, "message" => "Data retrieved successfully", "data" => $result );  
                    echo json_encode($data);          
            } else {
                    http_response_code(200);
                        $data = array( "success" => 2, "message" => "No data found" );
                    echo json_encode($data);
            }
        } else {
            error_log(print_r($stmt->errorInfo(), true));
            throw new Exception("Problem in executing the query in the database.");
        }
} else {
   
    http_response_code(405);
    echo json_encode(array("success" => 0, "message" => "Method Not Allowed."));
}
?>
