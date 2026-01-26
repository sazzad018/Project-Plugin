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

// Ensure table exists
$conn->query("CREATE TABLE IF NOT EXISTS `licenses` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `domain_name` varchar(255) NOT NULL,
  `license_key` varchar(64) NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `license_key` (`license_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $result = $conn->query("SELECT * FROM licenses ORDER BY id DESC");
    $licenses = [];
    while ($row = $result->fetch_assoc()) {
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
        
        $sql = "INSERT INTO licenses (domain_name, license_key, status) VALUES ('$domain', '$key', 'active')";
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
}

$conn->close();
?>