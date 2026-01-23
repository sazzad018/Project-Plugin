
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
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For live server compatibility
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.3');
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    
    if ($err) {
        return ["status" => "error", "message" => "cURL Error: $err"];
    }
    
    $decoded = json_decode($response, true);
    
    // Attach HTTP code for debugging if authentication fails
    if ($httpCode >= 400 && is_array($decoded)) {
        $decoded['http_code'] = $httpCode;
    }
    
    return $decoded;
}

$action = isset($_GET['action']) ? $_GET['action'] : '';
$config = getSetting($conn, 'bkash_config');

if (!$config) {
    ob_clean();
    echo json_encode(["status" => "error", "message" => "bKash configuration not found in database."]);
    exit;
}

// Determine Base URL
// Strict check for boolean true or string "true"
$isSandbox = isset($config['isSandbox']) && ($config['isSandbox'] === true || $config['isSandbox'] === "true");
$base_url = $isSandbox ? "https://tokenized.sandbox.bka.sh/v1.2.0-beta" : "https://tokenized.pay.bka.sh/v1.2.0-beta";

// 1. Get Token (Used internally)
function getToken($config, $base_url) {
    $username = trim($config['username'] ?? '');
    $password = trim($config['password'] ?? '');
    $appKey = trim($config['appKey'] ?? '');
    $appSecret = trim($config['appSecret'] ?? '');
    
    if (empty($appKey) || empty($appSecret) || empty($username) || empty($password)) {
        return ["status" => "error", "message" => "Missing one or more credentials (Username, Password, AppKey, AppSecret) in settings."];
    }

    // DOCS COMPLIANCE: Grant Token headers ONLY need username and password.
    // x-app-key should NOT be here for Grant Token.
    $headers = [
        "Content-Type: application/json",
        "username: " . $username,
        "password: " . $password
    ];
    
    $data = json_encode([
        "app_key" => $appKey, 
        "app_secret" => $appSecret
    ]);
    
    $res = bkash_call("$base_url/token/grant", "POST", $data, $headers);
    
    return $res;
}

ob_clean(); // Ensure clean output

if ($action === 'create') {
    // Input: amount, sms_count
    $input = json_decode(file_get_contents("php://input"), true);
    $amount = isset($input['amount']) ? $input['amount'] : 0;
    $sms_qty = isset($input['sms_qty']) ? $input['sms_qty'] : 0;

    // Step 1: Get Token
    $tokenRes = getToken($config, $base_url);
    
    if (isset($tokenRes['id_token'])) {
        $token = $tokenRes['id_token'];
    } else {
        // Detailed Error Response from Gateway
        $errMsg = "Unknown Auth Error";
        if (isset($tokenRes['statusMessage'])) $errMsg = $tokenRes['statusMessage'];
        if (isset($tokenRes['message'])) $errMsg = $tokenRes['message'];
        
        echo json_encode([
            "status" => "error", 
            "message" => "Auth Failed: $errMsg",
            "full_response" => $tokenRes
        ]);
        exit;
    }

    // Step 2: Create Payment
    // Embed SMS Qty in invoice number: INV_{Timestamp}_{SMSQty}
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

    // DOCS COMPLIANCE: Create Payment headers NEED Authorization and x-app-key
    $headers = [
        "Content-Type: application/json",
        "Authorization: " . $token,
        "x-app-key: " . trim($config['appKey'])
    ];

    $res = bkash_call("$base_url/tokenized/checkout/create", "POST", $create_data, $headers);

    if (isset($res['bkashURL'])) {
        echo json_encode(["status" => "success", "bkashURL" => $res['bkashURL']]);
    } else {
        $msg = isset($res['statusMessage']) ? $res['statusMessage'] : (isset($res['message']) ? $res['message'] : json_encode($res));
        echo json_encode(["status" => "error", "message" => "Create failed: " . $msg]);
    }

} elseif ($action === 'callback') {
    // bKash redirects here with paymentID and status
    $paymentID = isset($_GET['paymentID']) ? $_GET['paymentID'] : '';
    $status = isset($_GET['status']) ? $_GET['status'] : '';

    // Redirect Base (The React App URL)
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $app_url = $protocol . $_SERVER['HTTP_HOST'] . dirname(dirname($_SERVER['PHP_SELF'])); 

    if ($status !== 'success') {
        header("Location: $app_url/?payment_status=failed&msg=User Cancelled or Failed");
        exit;
    }

    // Step 1: Get Token (Again, stateless)
    $tokenRes = getToken($config, $base_url);
    if (isset($tokenRes['id_token'])) {
        $token = $tokenRes['id_token'];
    } else {
        header("Location: $app_url/?payment_status=error_auth&msg=" . urlencode("Auth failed during callback"));
        exit;
    }

    // Step 2: Execute Payment
    $execute_data = json_encode(["paymentID" => $paymentID]);
    
    // DOCS COMPLIANCE: Execute Payment headers NEED Authorization and x-app-key
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
            $bal_res = $conn->query("SELECT balance FROM sms_balance_store ORDER BY id DESC LIMIT 1");
            $current_bal = 0;
            if ($bal_res && $bal_res->num_rows > 0) {
                $current_bal = (int)$bal_res->fetch_assoc()['balance'];
            }
            
            $new_bal = $current_bal + $sms_to_add;
            
            $check_sql = "SELECT id FROM sms_balance_store ORDER BY id DESC LIMIT 1";
            $c_res = $conn->query($check_sql);
            if($c_res && $c_res->num_rows > 0) {
                $target_id = $c_res->fetch_assoc()['id'];
                $conn->query("UPDATE sms_balance_store SET balance = $new_bal WHERE id = $target_id");
            } else {
                $conn->query("INSERT INTO sms_balance_store (balance) VALUES ($new_bal)");
            }
        }

        header("Location: $app_url/?payment_status=success&added=$sms_to_add");
    } else {
        $err = isset($res['errorMessage']) ? $res['errorMessage'] : (isset($res['message']) ? $res['message'] : 'Unknown Error');
        header("Location: $app_url/?payment_status=failed&msg=" . urlencode($err));
    }
} else {
    echo json_encode(["status" => "error", "message" => "Invalid Action"]);
}

$conn->close();
?>
