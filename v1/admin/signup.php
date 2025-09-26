<?php
require_once('../../helper/header.php');
header("Content-Type: application/json");
header("Access-Control-Allow-Methods: POST");
require_once('../../helper/db/dipr_write.php');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        // Get raw JSON input
        $jsonData = file_get_contents("php://input");
        $data = json_decode($jsonData, true);

        // Validate required fields
        if (!isset($data['USERNAME'], $data['PASSWORD'], $data['EMAIL_ID'], 
                   $data['CREATED_BY'], $data['ROLE'], $data['DISTRICT'])) {
            http_response_code(400);
            echo json_encode(["success" => 0, "message" => "Missing required fields."]);
            exit;
        }

        // Assign variables
        $USERNAME   = strtoupper(trim($data['USERNAME']));
        $PASSWORD   = password_hash(trim($data['PASSWORD']), PASSWORD_BCRYPT); // Hash password
        $EMAIL_ID   = strtolower(trim($data['EMAIL_ID']));
        $CREATED_BY = trim($data['CREATED_BY']);
        $CREATED_ON = date("Y-m-d H:i:s"); // Auto-generate timestamp
        $UPDATED_BY = isset($data['UPDATED_BY']) ? trim($data['UPDATED_BY']) : NULL;
        $UPDATED_ON = isset($data['UPDATED_ON']) ? trim($data['UPDATED_ON']) : NULL;
        $ROLE       = trim($data['ROLE']);
        $DISTRICT   = trim($data['DISTRICT']);

        // -----------------------
        // Email Validation
        // -----------------------
        if (
            !filter_var($EMAIL_ID, FILTER_VALIDATE_EMAIL) || 
            !preg_match('/^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$/', $EMAIL_ID)
        ) {
            http_response_code(400);
            echo json_encode(["success" => 0, "message" => "Invalid email format."]);
            exit;
        }

        // Optional: Block disposable/fake email domains
        $disposableDomains = ["tempmail.com", "10minutemail.com", "mailinator.com"];
        $domain = substr(strrchr($EMAIL_ID, "@"), 1);
        if (in_array(strtolower($domain), $disposableDomains)) {
            http_response_code(400);
            echo json_encode(["success" => 0, "message" => "Disposable email addresses are not allowed."]);
            exit;
        }

        // -----------------------
        // SQL Query with placeholders
        // -----------------------
        $sql = "INSERT INTO USER_LIST (USERNAME, PASSWORD, EMAIL_ID, CREATED_BY, CREATED_ON, 
                                       UPDATED_BY, UPDATED_ON, ROLE, DISTRICT) 
                VALUES (:USERNAME, :PASSWORD, :EMAIL_ID, :CREATED_BY, :CREATED_ON, 
                        :UPDATED_BY, :UPDATED_ON, :ROLE, :DISTRICT)";

        $stmt = $dipr_write_db->prepare($sql);

        // Bind parameters
        $stmt->bindParam(':USERNAME', $USERNAME, PDO::PARAM_STR);
        $stmt->bindParam(':PASSWORD', $PASSWORD, PDO::PARAM_STR);
        $stmt->bindParam(':EMAIL_ID', $EMAIL_ID, PDO::PARAM_STR);
        $stmt->bindParam(':CREATED_BY', $CREATED_BY, PDO::PARAM_STR);
        $stmt->bindParam(':CREATED_ON', $CREATED_ON, PDO::PARAM_STR);
        $stmt->bindParam(':UPDATED_BY', $UPDATED_BY, PDO::PARAM_STR);
        $stmt->bindParam(':UPDATED_ON', $UPDATED_ON, PDO::PARAM_STR);
        $stmt->bindParam(':ROLE', $ROLE, PDO::PARAM_STR);
        $stmt->bindParam(':DISTRICT', $DISTRICT, PDO::PARAM_STR);

        // Execute query
        if ($stmt->execute()) {
            http_response_code(201); // HTTP 201 Created
            echo json_encode(["success" => 1, "message" => "User created successfully."]);
        } else {
            throw new Exception("Failed to insert user.");
        }
        
    } catch (Exception $e) {
        error_log("Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(["success" => 0, "message" => "Server error.", 'error' => $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(["success" => 0, "message" => "Method Not Allowed."]);
}
?>
