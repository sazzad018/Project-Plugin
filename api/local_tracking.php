
<?php
ob_start();
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    ob_end_clean();
    http_response_code(200);
    exit;
}

include 'db.php';

// Create table if not exists
$conn->query("CREATE TABLE IF NOT EXISTS local_tracking (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id VARCHAR(50) UNIQUE NOT NULL,
    tracking_code VARCHAR(100),
    status VARCHAR(50),
    courier_name VARCHAR(50),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

ob_clean();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $result = $conn->query("SELECT * FROM local_tracking ORDER BY updated_at DESC");
    $data = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
    }
    echo json_encode($data);
} 
elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents("php://input"), true);
    
    $order_id = isset($input['order_id']) ? $conn->real_escape_string($input['order_id']) : '';
    $tracking_code = isset($input['tracking_code']) ? $conn->real_escape_string($input['tracking_code']) : '';
    $status = isset($input['status']) ? $conn->real_escape_string($input['status']) : '';
    $courier_name = isset($input['courier_name']) ? $conn->real_escape_string($input['courier_name']) : '';

    if ($order_id) {
        $sql = "REPLACE INTO local_tracking (order_id, tracking_code, status, courier_name) VALUES ('$order_id', '$tracking_code', '$status', '$courier_name')";
        if ($conn->query($sql) === TRUE) {
            echo json_encode(["success" => true]);
        } else {
            echo json_encode(["success" => false, "error" => $conn->error]);
        }
    } else {
        echo json_encode(["success" => false, "error" => "Missing order_id"]);
    }
}

$conn->close();
?>
