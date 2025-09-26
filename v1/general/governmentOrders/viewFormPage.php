<?php
require_once('../../../helper/header.php');
require_once('../../../helper/db/dipr_read.php');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        // Prepare SQL query to retrieve data
        $sql = "SELECT  ID ,
	ORDER_NAME ,
	ORDER_FILE_NAME ,
	UPLOADED_BY ,
	UPLOADED_ON ,
	ORDER_NO ,
	MIMETYPE ,
	GO_DATE ,
	LANGUAGE   FROM TN_GOVT_ORDERS	
";
        $stmt = $dipr_read_db->prepare($sql);
        $stmt->execute(); // Execute query directly

        // Check if any data is returned
        $data_count = $stmt->rowCount();
        if ($data_count > 0) {
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            http_response_code(200);
            $data = array("success" => 1, "message" => "Data retrieved successfully", "data" => $result);
        } else {
            http_response_code(200);
            $data = array("success" => 2, "message" => "No data found");
        }

        // Send response as JSON
        echo json_encode($data, JSON_UNESCAPED_UNICODE);

    } catch (PDOException $e) {
        error_log("Error: " . $e->getMessage()); // Log the error
        http_response_code(500);
        $data = array("success" => 3, "message" => "Problem executing the query in the database");
        echo json_encode($data);
    }

} else {
    http_response_code(405); // Method Not Allowed
    $data = array("success" => 0, "message" => "Method Not Allowed");
    echo json_encode($data);
}
