
<?php
// Prevent any unwanted output (whitespace/warnings)
ob_start();

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    ob_end_clean();
    http_response_code(200);
    exit;
}

include 'db.php';

// Clean buffer before sending JSON
ob_clean();

// Check connection explicitly
if ($conn->connect_error) {
    echo json_encode(["balance" => 0, "error" => "DB Connection failed: " . $conn->connect_error]);
    exit;
}

// 1. Auto-create table if it doesn't exist
$table_sql = "CREATE TABLE IF NOT EXISTS sms_balance_store (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    balance INT(11) NOT NULL DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

$conn->query($table_sql);

// 2. Ensure at least one row exists
$check_sql = "SELECT id FROM sms_balance_store LIMIT 1";
$result = $conn->query($check_sql);
if ($result->num_rows == 0) {
    $conn->query("INSERT INTO sms_balance_store (balance) VALUES (0)");
}

// 3. Handle Requests
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Fetch the latest entry (highest ID)
    $result = $conn->query("SELECT balance FROM sms_balance_store ORDER BY id DESC LIMIT 1");
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        echo json_encode(["balance" => (int)$row['balance']]);
    } else {
        echo json_encode(["balance" => 0]);
    }
} 
elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents("php://input"), true);
    
    if (isset($input['balance'])) {
        $new_balance = (int)$input['balance'];
        if ($new_balance < 0) $new_balance = 0;

        // Find the latest ID to update
        $last_id_res = $conn->query("SELECT id FROM sms_balance_store ORDER BY id DESC LIMIT 1");
        
        if ($last_id_res->num_rows > 0) {
            $last_row = $last_id_res->fetch_assoc();
            $target_id = $last_row['id'];
            $update_sql = "UPDATE sms_balance_store SET balance = $new_balance WHERE id = $target_id";
        } else {
            $update_sql = "INSERT INTO sms_balance_store (balance) VALUES ($new_balance)";
        }
        
        if ($conn->query($update_sql) === TRUE) {
            echo json_encode(["success" => true, "balance" => $new_balance, "message" => "Balance updated"]);
        } else {
            echo json_encode(["success" => false, "message" => "Error updating record: " . $conn->error]);
        }
    } else {
        echo json_encode(["success" => false, "message" => "Balance value missing"]);
    }
}

$conn->close();
?>
