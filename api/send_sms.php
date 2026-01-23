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

// Get JSON input from the dashboard
$input = json_decode(file_get_contents("php://input"), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Invalid JSON input"]);
    exit;
}

// Extract variables
$api_key = isset($input['api_key']) ? $input['api_key'] : '';
$senderid = isset($input['senderid']) ? $input['senderid'] : '';
$msg = isset($input['msg']) ? $input['msg'] : '';
$contacts = isset($input['contacts']) ? $input['contacts'] : '';
$type = isset($input['type']) ? $input['type'] : 'text'; // Default to text

// Validation
if (empty($api_key) || empty($senderid) || empty($contacts) || empty($msg)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Missing required fields (api_key, senderid, contacts, msg)"]);
    exit;
}

// Prepare data for the API
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
    // Return success response to the dashboard
    // We attach the provider's raw response for debugging/confirmation
    echo json_encode([
        "success" => true, 
        "message" => "Request processed", 
        "provider_response" => $response
    ]);
}
?>