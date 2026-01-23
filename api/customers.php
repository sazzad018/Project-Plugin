
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
$conn->query("CREATE TABLE IF NOT EXISTS customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    phone VARCHAR(20) UNIQUE NOT NULL,
    name VARCHAR(100),
    email VARCHAR(100),
    address TEXT,
    avatar VARCHAR(255),
    total_spent DECIMAL(10,2) DEFAULT 0,
    order_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

ob_clean();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $result = $conn->query("SELECT name, phone, email, address, avatar, total_spent as totalSpent, order_count as orderCount FROM customers ORDER BY order_count DESC");
    $customers = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $row['totalSpent'] = (float)$row['totalSpent'];
            $row['orderCount'] = (int)$row['orderCount'];
            $customers[] = $row;
        }
    }
    echo json_encode($customers);
}
elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    
    $phone = isset($data['phone']) ? $conn->real_escape_string($data['phone']) : '';
    $name = isset($data['name']) ? $conn->real_escape_string($data['name']) : 'Customer';
    $email = isset($data['email']) ? $conn->real_escape_string($data['email']) : '';
    $address = isset($data['address']) ? $conn->real_escape_string($data['address']) : '';
    $avatar = isset($data['avatar']) ? $conn->real_escape_string($data['avatar']) : '';
    $order_total = isset($data['total']) ? (float)$data['total'] : 0;

    if ($phone) {
        // Update existing or insert new
        // First check if exists to update counts
        $check = $conn->query("SELECT id, total_spent, order_count FROM customers WHERE phone = '$phone'");
        
        if ($check && $check->num_rows > 0) {
            $row = $check->fetch_assoc();
            $new_total = (float)$row['total_spent'] + $order_total;
            // Only increment order count if this request comes from a new order (simple logic: if total > 0)
            // Or better, just update details. For full POS sync, we might just want to upsert details.
            $new_count = (int)$row['order_count'];
            if ($order_total > 0) $new_count++;
            
            $sql = "UPDATE customers SET name='$name', email='$email', address='$address', avatar='$avatar', total_spent=$new_total, order_count=$new_count WHERE phone='$phone'";
        } else {
            $initial_count = ($order_total > 0) ? 1 : 0;
            $sql = "INSERT INTO customers (phone, name, email, address, avatar, total_spent, order_count) VALUES ('$phone', '$name', '$email', '$address', '$avatar', $order_total, $initial_count)";
        }
        
        if ($conn->query($sql) === TRUE) {
            echo json_encode(["success" => true]);
        } else {
            echo json_encode(["success" => false, "error" => $conn->error]);
        }
    } else {
        echo json_encode(["success" => false, "error" => "Phone required"]);
    }
}

$conn->close();
?>
