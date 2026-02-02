<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

include 'db.php';

// Get JSON input
$input = json_decode(file_get_contents("php://input"), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Invalid JSON input"]);
    exit;
}

// --- 1. Fetch Admin Gateway Configuration (Used for actual sending) ---
$stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'sms_config'");
$stmt->execute();
$res = $stmt->get_result();
$gatewayConfig = [];
if ($res->num_rows > 0) {
    $val = $res->fetch_assoc()['setting_value'];
    $gatewayConfig = json_decode($val, true);
    if (is_string($gatewayConfig)) $gatewayConfig = json_decode($gatewayConfig, true);
}

$api_key = $gatewayConfig['apiKey'] ?? '';
$senderid = $gatewayConfig['senderId'] ?? '';

if (empty($api_key) || empty($senderid)) {
    // Fallback: Check input if admin is testing directly via API tool
    $api_key = isset($input['api_key']) ? $input['api_key'] : '';
    $senderid = isset($input['senderid']) ? $input['senderid'] : '';
}

if (empty($api_key) || empty($senderid)) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "SMS Gateway not configured in Dashboard Settings"]);
    exit;
}

// --- 2. Input Data ---
$msg = isset($input['msg']) ? $input['msg'] : '';
$contacts = isset($input['contacts']) ? $input['contacts'] : '';
$type = isset($input['type']) ? $input['type'] : 'text';
$license_key = isset($input['license_key']) ? $input['license_key'] : '';

if (empty($contacts) || empty($msg)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Missing contacts or message"]);
    exit;
}

// --- 3. Balance Check & Deduction Logic ---

// Calculate Cost (SMS Parts)
$gsmRegex = '/^[\x00-\x7F]*$/';
$isUnicode = !preg_match($gsmRegex, $msg);
$len = mb_strlen($msg, 'UTF-8');
$segments = 1;
if ($isUnicode) {
    $segments = $len <= 70 ? 1 : ceil($len / 67);
} else {
    $segments = $len <= 160 ? 1 : ceil($len / 153);
}

$contactsArr = explode(',', $contacts);
$recipientCount = count($contactsArr);
$totalCost = $segments * $recipientCount;

// A. License Based Sending (Plugin)
if ($license_key) {
    $clean_key = $conn->real_escape_string($license_key);
    $licRes = $conn->query("SELECT id, sms_balance, status FROM licenses WHERE license_key = '$clean_key'");
    
    if ($licRes && $licRes->num_rows > 0) {
        $licData = $licRes->fetch_assoc();
        
        if ($licData['status'] !== 'active') {
            echo json_encode(["success" => false, "message" => "License is inactive"]);
            exit;
        }
        
        if ($licData['sms_balance'] < $totalCost) {
            echo json_encode(["success" => false, "message" => "Insufficient Balance. Required: $totalCost, Available: " . $licData['sms_balance']]);
            exit;
        }
        
        // Deduct from License
        $licId = $licData['id'];
        $conn->query("UPDATE licenses SET sms_balance = sms_balance - $totalCost WHERE id = $licId");
        
    } else {
        echo json_encode(["success" => false, "message" => "Invalid License Key"]);
        exit;
    }
} 
// B. Global/Admin Based Sending (Dashboard)
else {
    $balRes = $conn->query("SELECT id, balance FROM sms_balance_store ORDER BY id DESC LIMIT 1");
    if ($balRes && $balRes->num_rows > 0) {
        $balData = $balRes->fetch_assoc();
        if ($balData['balance'] < $totalCost) {
            echo json_encode(["success" => false, "message" => "Insufficient Global Balance"]);
            exit;
        }
        // Deduct from Global
        $gId = $balData['id'];
        $conn->query("UPDATE sms_balance_store SET balance = balance - $totalCost WHERE id = $gId");
    } else {
        // If no table exists yet, allow pass through (or strict block) - choosing block for safety
        echo json_encode(["success" => false, "message" => "Global Balance not initialized"]);
        exit;
    }
}

// --- 4. Send via Gateway ---
$url = "https://sms.mram.com.bd/smsapi";
$data = [
  "api_key" => $api_key,
  "type" => $type,
  "contacts" => $contacts,
  "senderid" => $senderid,
  "msg" => $msg,
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$err = curl_error($ch);
curl_close($ch);

if ($err) {
    // Optional: Refund if curl failed entirely? For now, we assume it went through or log error.
    echo json_encode(["success" => false, "message" => "cURL Error: " . $err]);
} else {
    echo json_encode([
        "success" => true, 
        "message" => "Request processed", 
        "cost" => $totalCost,
        "provider_response" => $response
    ]);
}
?>