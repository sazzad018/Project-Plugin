
<?php
ob_start();
session_start();

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    ob_end_clean();
    http_response_code(200);
    exit;
}

include 'db.php';

// Helper: Get Settings
function getSetting($conn, $key) {
    $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows > 0) {
        $val = $res->fetch_assoc()['setting_value'];
        return json_decode($val, true);
    }
    return null;
}

// Helper: bKash API Call
function bkash_call($url, $method, $data, $headers) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    if ($data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    }
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    
    if ($err) {
        return ["status" => "error", "message" => "cURL Error: $err"];
    }
    
    return json_decode($response, true);
}

$action = isset($_GET['action']) ? $_GET['action'] : '';
$config = getSetting($conn, 'bkash_config');

if (!$config) {
    ob_clean();
    echo json_encode(["status" => "error", "message" => "bKash not configured"]);
    exit;
}

// Determine Base URL (Robust check for string 'true' or boolean true)
$isSandbox = isset($config['isSandbox']) && ($config['isSandbox'] === true || $config['isSandbox'] === "true");
$base_url = $isSandbox ? "https://tokenized.sandbox.bka.sh/v1.2.0-beta" : "https://tokenized.pay.bka.sh/v1.2.0-beta";

// 1. Get Token (Used internally)
function getToken($config, $base_url) {
    $headers = [
        "Content-Type: application/json",
        "username: " . trim($config['username']),
        "password: " . trim($config['password']),
        "x-app-key: " . trim($config['appKey']) // Added x-app-key header which is required by some gateways
    ];
    $data = json_encode(["app_key" => trim($config['appKey']), "app_secret" => trim($config['appSecret'])]);
    
    $res = bkash_call("$base_url/token/grant", "POST", $data, $headers);
    return $res;
}

ob_clean(); // Ensure clean output

if ($action === 'create') {
    // Input: amount, sms_count
    $input = json_decode(file_get_contents("php://input"), true);
    $amount = $input['amount'];
    $sms_qty = $input['sms_qty']; // We pass this to track how much to add

    $tokenRes = getToken($config, $base_url);
    if (isset($tokenRes['id_token'])) {
        $token = $tokenRes['id_token'];
    } else {
        // Return detailed error with URL for debugging
        $errMsg = isset($tokenRes['statusMessage']) ? $tokenRes['statusMessage'] : json_encode($tokenRes);
        if (isset($tokenRes['message'])) $errMsg = $tokenRes['message']; // Catch gateway errors
        
        echo json_encode([
            "status" => "error", 
            "message" => "Auth failed at $base_url: " . $errMsg
        ]);
        exit;
    }

    // Embed SMS Qty in invoice number: INV_{Timestamp}_{SMSQty}
    // Example: INV_1723456789_500
    $invoice_no = "INV_" . time() . "_" . $sms_qty;
    
    // Detect Protocol and Host for Callback
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $callback_url = $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] . "?action=callback";

    $create_data = json_encode([
        "mode" => "0011",
        "payerReference" => "01770618575", // Optional
        "callbackURL" => $callback_url,
        "amount" => (string)$amount,
        "currency" => "BDT",
        "intent" => "sale",
        "merchantInvoiceNumber" => $invoice_no
    ]);

    $headers = [
        "Content-Type: application/json",
        "Authorization: " . $token,
        "x-app-key: " . trim($config['appKey'])
    ];

    $res = bkash_call("$base_url/tokenized/checkout/create", "POST", $create_data, $headers);

    if (isset($res['bkashURL'])) {
        echo json_encode(["status" => "success", "bkashURL" => $res['bkashURL']]);
    } else {
        echo json_encode(["status" => "error", "message" => "Create failed: " . (isset($res['statusMessage']) ? $res['statusMessage'] : json_encode($res))]);
    }

} elseif ($action === 'callback') {
    // bKash redirects here with paymentID and status
    $paymentID = isset($_GET['paymentID']) ? $_GET['paymentID'] : '';
    $status = isset($_GET['status']) ? $_GET['status'] : '';

    // Redirect Base (The React App URL)
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    // Assuming the React app is at root / 
    $app_url = $protocol . $_SERVER['HTTP_HOST'] . dirname(dirname($_SERVER['PHP_SELF'])); 

    if ($status !== 'success') {
        header("Location: $app_url/?payment_status=failed");
        exit;
    }

    $tokenRes = getToken($config, $base_url);
    if (isset($tokenRes['id_token'])) {
        $token = $tokenRes['id_token'];
    } else {
        header("Location: $app_url/?payment_status=error_auth&msg=" . urlencode("Auth failed during callback"));
        exit;
    }

    $execute_data = json_encode(["paymentID" => $paymentID]);
    $headers = [
        "Content-Type: application/json",
        "Authorization: " . $token,
        "x-app-key: " . trim($config['appKey'])
    ];

    $res = bkash_call("$base_url/tokenized/checkout/execute", "POST", $execute_data, $headers);

    // Check if transaction completed
    if (isset($res['transactionStatus']) && ($res['transactionStatus'] === 'Completed' || $res['transactionStatus'] === 'Authorized')) {
        
        // Extract SMS Qty from Invoice Number
        // Invoice Format: INV_{Time}_{Qty}
        $invoice_parts = explode('_', $res['merchantInvoiceNumber']);
        $sms_to_add = 0;
        if (count($invoice_parts) >= 3) {
            $sms_to_add = (int)$invoice_parts[2];
        }

        // UPDATE DATABASE
        if ($sms_to_add > 0) {
            // Get current balance
            $bal_res = $conn->query("SELECT balance FROM sms_balance_store ORDER BY id DESC LIMIT 1");
            $current_bal = 0;
            if ($bal_res->num_rows > 0) {
                $current_bal = (int)$bal_res->fetch_assoc()['balance'];
            }
            
            $new_bal = $current_bal + $sms_to_add;
            
            // Insert new record or update
            // We'll update the latest record to keep it simple, or insert new log
            $target_id = 1; 
            $check_sql = "SELECT id FROM sms_balance_store ORDER BY id DESC LIMIT 1";
            $c_res = $conn->query($check_sql);
            if($c_res->num_rows > 0) {
                $target_id = $c_res->fetch_assoc()['id'];
                $conn->query("UPDATE sms_balance_store SET balance = $new_bal WHERE id = $target_id");
            } else {
                $conn->query("INSERT INTO sms_balance_store (balance) VALUES ($new_bal)");
            }
        }

        header("Location: $app_url/?payment_status=success&added=$sms_to_add");
    } else {
        $err = isset($res['errorMessage']) ? $res['errorMessage'] : 'Unknown Error';
        header("Location: $app_url/?payment_status=failed&msg=" . urlencode($err));
    }
} else {
    echo json_encode(["status" => "error", "message" => "Invalid Action"]);
}

$conn->close();
?>
