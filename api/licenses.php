<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

include 'db.php';

// Ensure table exists with sms_balance
$conn->query("CREATE TABLE IF NOT EXISTS `licenses` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `domain_name` varchar(255) NOT NULL,
  `license_key` varchar(64) NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `sms_balance` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `license_key` (`license_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Migration check for sms_balance column
$colCheck = $conn->query("SHOW COLUMNS FROM licenses LIKE 'sms_balance'");
if ($colCheck->num_rows == 0) {
    $conn->query("ALTER TABLE licenses ADD COLUMN sms_balance int(11) DEFAULT 0");
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $result = $conn->query("SELECT * FROM licenses ORDER BY id DESC");
    $licenses = [];
    while ($row = $result->fetch_assoc()) {
        $row['sms_balance'] = (int)$row['sms_balance'];
        $licenses[] = $row;
    }
    echo json_encode($licenses);
} 
elseif ($method === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    $action = isset($data['action']) ? $data['action'] : '';

    if ($action === 'create') {
        $domain = $conn->real_escape_string($data['domain']);
        // Generate a random license key
        $key = 'BDC-' . strtoupper(bin2hex(random_bytes(8)));
        
        $sql = "INSERT INTO licenses (domain_name, license_key, status, sms_balance) VALUES ('$domain', '$key', 'active', 10)"; // Default 10 credits
        if ($conn->query($sql)) {
            echo json_encode(["success" => true, "message" => "License created", "key" => $key]);
        } else {
            echo json_encode(["success" => false, "message" => "Error: " . $conn->error]);
        }
    } 
    elseif ($action === 'toggle') {
        $id = (int)$data['id'];
        $status = $data['status'] === 'active' ? 'active' : 'inactive';
        $conn->query("UPDATE licenses SET status = '$status' WHERE id = $id");
        echo json_encode(["success" => true]);
    }
    elseif ($action === 'delete') {
        $id = (int)$data['id'];
        $conn->query("DELETE FROM licenses WHERE id = $id");
        echo json_encode(["success" => true]);
    }
    elseif ($action === 'update_balance') {
        $id = (int)$data['id'];
        $amount = (int)$data['amount']; // Can be negative
        $sql = "UPDATE licenses SET sms_balance = sms_balance + ($amount) WHERE id = $id";
        if ($conn->query($sql)) {
             // Ensure it doesn't go below 0
             $conn->query("UPDATE licenses SET sms_balance = 0 WHERE sms_balance < 0 AND id = $id");
             echo json_encode(["success" => true]);
        } else {
             echo json_encode(["success" => false, "error" => $conn->error]);
        }
    }
}

$conn->close();
?>