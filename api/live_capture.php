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

// Check if feature is enabled
$featCheck = $conn->query("SELECT is_enabled FROM feature_flags WHERE feature_key = 'live_capture'");
if ($featCheck && $featCheck->num_rows > 0) {
    $isEnabled = (bool)$featCheck->fetch_assoc()['is_enabled'];
    if (!$isEnabled && $_SERVER['REQUEST_METHOD'] === 'POST') {
        echo json_encode(["success" => false, "message" => "Feature disabled"]);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    
    $session_id = isset($data['session_id']) ? $conn->real_escape_string($data['session_id']) : '';
    $phone = isset($data['phone']) ? $conn->real_escape_string($data['phone']) : '';
    
    // Only save if phone is present (core requirement)
    if ($session_id && $phone) {
        $name = isset($data['name']) ? $conn->real_escape_string($data['name']) : '';
        $email = isset($data['email']) ? $conn->real_escape_string($data['email']) : '';
        $address = isset($data['address']) ? $conn->real_escape_string($data['address']) : '';
        $cart_items = isset($data['cart_items']) ? $conn->real_escape_string(json_encode($data['cart_items'])) : '[]';
        $cart_total = isset($data['cart_total']) ? (float)$data['cart_total'] : 0.00;
        $ip = $_SERVER['REMOTE_ADDR'];

        $sql = "INSERT INTO incomplete_orders (session_id, customer_name, phone, email, address, cart_items, cart_total, ip_address) 
                VALUES ('$session_id', '$name', '$phone', '$email', '$address', '$cart_items', $cart_total, '$ip')
                ON DUPLICATE KEY UPDATE 
                customer_name = '$name', phone = '$phone', email = '$email', address = '$address', 
                cart_items = '$cart_items', cart_total = $cart_total, updated_at = NOW()";

        if ($conn->query($sql)) {
            echo json_encode(["success" => true]);
        } else {
            echo json_encode(["success" => false, "error" => $conn->error]);
        }
    } else {
        echo json_encode(["success" => false, "message" => "Phone number required"]);
    }
} 
elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Fetch leads
    $result = $conn->query("SELECT * FROM incomplete_orders ORDER BY updated_at DESC LIMIT 100");
    $leads = [];
    while($row = $result->fetch_assoc()) {
        $row['cart_items'] = json_decode($row['cart_items']);
        $leads[] = $row;
    }
    echo json_encode($leads);
}

$conn->close();
?>