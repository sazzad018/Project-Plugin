<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Api-Key, Secret-Key, Authorization");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit;
}

$apiKey = $_SERVER['HTTP_API_KEY'] ?? '';
$secretKey = $_SERVER['HTTP_SECRET_KEY'] ?? '';
$action = $_GET['action'] ?? '';

// Reverted to Packzy URL which is more commonly compatible for Steadfast API
$baseUrl = "https://portal.packzy.com/api/v1";

$ch = curl_init();
$url = "";

if ($action === 'balance') {
    $url = $baseUrl . "/get_balance";
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
} elseif ($action === 'create') {
    $url = $baseUrl . "/create_order";
    $data = file_get_contents('php://input');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
} elseif ($action === 'status') {
    $code = $_GET['tracking_code'] ?? '';
    // Official Status endpoint: /status_by_trackingcode/{trackingCode}
    $url = $baseUrl . "/status_by_trackingcode/" . $code;
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
}

curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Fix for SSL handshake issues on some servers
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Api-Key: $apiKey",
    "Secret-Key: $secretKey",
    "Content-Type: application/json"
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (curl_errno($ch)) {
    echo json_encode(['status' => 500, 'message' => 'CURL Error: ' . curl_error($ch)]);
} else {
    echo $response;
}

curl_close($ch);
?>