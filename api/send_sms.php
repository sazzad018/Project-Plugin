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

// Get JSON input from the dashboard or plugin
$input = json_decode(file_get_contents("php://input"), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Invalid JSON input"]);
    exit;
}

// 1. Try to get credentials from Input (Dashboard sends these)
$api_key = isset($input['api_key']) ? $input['api_key'] : '';
$senderid = isset($input['senderid']) ? $input['senderid'] : '';

// 2. If missing (Plugin request), fetch from Database Settings
if (empty($api_key) || empty($senderid)) {
    $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'sms_config'");
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows > 0) {
        $configVal = $res->fetch_assoc()['setting_value'];
        $config = json_decode($configVal, true);
        if (is_string($config)) $config = json_decode($config, true); // Double decode safety
        
        if (!empty($config['apiKey'])) $api_key = $config['apiKey'];
        if (!empty($config['senderId'])) $senderid = $config['senderId'];
    }
}

// Extract message details
$msg = isset($input['msg']) ? $input['msg'] : '';
$contacts = isset($input['contacts']) ? $input['contacts'] : '';
$type = isset($input['type']) ? $input['type'] : 'text';

// Validation
if (empty($api_key) || empty($senderid)) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "SMS Gateway not configured in Dashboard Settings"]);
    exit;
}

if (empty($contacts) || empty($msg)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Missing contacts or message"]);
    exit;
}

// Prepare data for the SMS Gateway API
$url = "https://sms.mram.com.bd/smsapi";
$data = [
  "api_key" => $api_key,
  "type" => $type,
  "contacts" => $contacts,
  "senderid" => $senderid,
  "msg" => $msg,
];

// Initialize cURL
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
    echo json_encode(["success" => false, "message" => "cURL Error: " . $err]);
} else {
    // Return success response
    echo json_encode([
        "success" => true, 
        "message" => "Request processed", 
        "provider_response" => $response
    ]);
}
?>