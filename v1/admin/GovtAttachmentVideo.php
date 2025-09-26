<?php
require_once('../../helper/header.php');
header("Content-Type: application/json");
header("Access-Control-Allow-Methods: POST");
require_once('../../helper/db/dipr_read.php');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        if (!$dipr_read_db) {
            throw new Exception("Database connection failed.");
        }

        $jsonData = file_get_contents("php://input");
        $data = json_decode($jsonData, true);
	

        if (!isset($data['action'])) {
            http_response_code(400);
            echo json_encode(["success" => 0, "message" => "Missing action."]);
            exit;
        }

        $action = strtolower($data['action']);

        switch ($action) {
            case 'insert':
print_r($data);

                if (!isset($data['V_NAME'], $data['V_DESC'], $data['V_URL'], 
                           $data['LANGUAGE'])) {
                    http_response_code(400);
                    echo json_encode(["success" => 0, "message" => "Invalid input. All fields are required."]);
                    exit;
                }

                $V_NAME = trim($data['V_NAME']);
                $V_DESC = trim($data['V_DESC']);
                $V_URL = trim($data['V_URL']);
                $Current_User = trim($data['created_by']);
                $Current_Date = date("Y-m-d H:i:s");
                $V_DATE = trim($data['V_DATE']);
                $LANGUAGE = substr(trim($data['LANGUAGE']), 0, 2);

                $sql = "INSERT INTO govt_attachment_video(
                            V_NAME, V_DESC, V_URL, CREATED_ON, CREATED_BY, V_DATE, LANGUAGE)
                        VALUES (:V_NAME, :V_DESC, :V_URL, :Current_Date, :Current_User, :V_DATE, :LANGUAGE)";
                $stmt = $dipr_read_db->prepare($sql);
                $stmt->bindParam(':V_NAME', $V_NAME);
                $stmt->bindParam(':V_DESC', $V_DESC);
                $stmt->bindParam(':V_URL', $V_URL);
                $stmt->bindParam(':Current_Date', $Current_Date);
                $stmt->bindParam(':Current_User', $Current_User);
                $stmt->bindParam(':V_DATE', $V_DATE);
                $stmt->bindParam(':LANGUAGE', $LANGUAGE);

                if ($stmt->execute()) {
                    http_response_code(201);
                    echo json_encode(["success" => 1, "message" => "Data inserted successfully."]);
                } else {
                    throw new Exception("Failed to insert data.");
                }
                break;

            case 'fetch':
                $sql = "SELECT * FROM govt_attachment_video ORDER BY V_DATE DESC";
                $stmt = $dipr_read_db->query($sql);
                $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(["success" => 1, "data" => $data]);
                break;

            case 'delete':
                if (empty($data['id'])) {
                    http_response_code(400);
                    echo json_encode(["success" => 0, "message" => "Missing ID for delete."]);
                    exit;
                }

                $sql = "DELETE FROM govt_attachment_video WHERE id = :id";
                $stmt = $dipr_read_db->prepare($sql);
                $stmt->bindParam(':id', $data['id']);

                if ($stmt->execute()) {
                    echo json_encode(["success" => 1, "message" => "Data deleted successfully."]);
                } else {
                    throw new Exception("Failed to delete data.");
                }
                break;

            case 'update':
                if (!isset($data['id'], $data['V_NAME'], $data['V_DESC'], $data['V_URL'], 
                        $data['Current_User'], $data['V_DATE'], $data['LANGUAGE'])) {
                    http_response_code(400);
                    echo json_encode(["success" => 0, "message" => "Invalid input. All fields including ID are required."]);
                    exit;
                }

                $ID = $data['id'];
                $V_NAME = trim($data['V_NAME']);
                $V_DESC = trim($data['V_DESC']);
                $V_URL = trim($data['V_URL']);
                $V_DATE = trim($data['V_DATE']);
                $LANGUAGE = substr(trim($data['LANGUAGE']), 0, 2);

                $sql = "UPDATE govt_attachment_video
                        SET V_NAME = :V_NAME,
                            V_DESC = :V_DESC,
                            V_URL = :V_URL,
                            V_DATE = :V_DATE,
                            LANGUAGE = :LANGUAGE
                        WHERE id = :id";

                $stmt = $dipr_read_db->prepare($sql);
                $stmt->bindParam(':V_NAME', $V_NAME);
                $stmt->bindParam(':V_DESC', $V_DESC);
                $stmt->bindParam(':V_URL', $V_URL);
                $stmt->bindParam(':V_DATE', $V_DATE);
                $stmt->bindParam(':LANGUAGE', $LANGUAGE);
                $stmt->bindParam(':id', $ID);

                if ($stmt->execute()) {
                    echo json_encode(["success" => 1, "message" => "Data updated successfully."]);
                } else {
                    throw new Exception("Failed to update data.");
                }
                break;


            default:
                http_response_code(400);
                echo json_encode(["success" => 0, "message" => "Invalid action."]);
        }

    } catch (Exception $e) {
        error_log("Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(["success" => 0, "message" => $e->getMessage()]);
    }

} else {
    http_response_code(405);
    echo json_encode(["success" => 0, "message" => "Method Not Allowed. Only POST is allowed."]);
}
