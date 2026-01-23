<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

// Disable display errors
ini_set('display_errors', 0);
error_reporting(0);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["message" => "Method Not Allowed"]);
    exit;
}

// Check inputs
if (!isset($_FILES['file']) || !isset($_POST['url']) || !isset($_POST['consumer_key']) || !isset($_POST['consumer_secret'])) {
    http_response_code(400);
    echo json_encode(["message" => "Missing fields in proxy"]);
    exit;
}

$file = $_FILES['file'];
$wpUrl = rtrim($_POST['url'], '/');
$consumerKey = trim($_POST['consumer_key']);
$consumerSecret = trim($_POST['consumer_secret']);

// Construct WP API URL (Keep params in URL for compatibility)
$endpoint = $wpUrl . "/wp-json/wp/v2/media?consumer_key=" . $consumerKey . "&consumer_secret=" . $consumerSecret;

// Read file data
$fileContent = file_get_contents($file['tmp_name']);

// Init Curl
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $endpoint);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $fileContent);

// Construct Basic Auth Header
$auth = base64_encode($consumerKey . ":" . $consumerSecret);

// Headers
$headers = [
    'Authorization: Basic ' . $auth, // Added Basic Auth for robust authentication
    'Content-Type: ' . $file['type'],
    'Content-Disposition: attachment; filename="' . $file['name'] . '"',
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/91.0.4472.124 Safari/537.36'
];

curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

// Security options
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 120); 

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    http_response_code(500);
    echo json_encode(["message" => "Proxy cURL Error: " . $curlError]);
    exit;
}

// Check specifically for permissions error
if ($httpCode === 401 || $httpCode === 403) {
    $json = json_decode($response, true);
    if (isset($json['message'])) {
         // Pass the WP error message through
         http_response_code($httpCode);
         echo json_encode(["message" => "WP Permission Error: " . $json['message'] . " (Check if API Key has Read/Write permissions)"]);
         exit;
    }
}

http_response_code($httpCode >= 200 && $httpCode < 300 ? 200 : $httpCode);

// Return raw response (let JS handle parsing)
echo $response;
?>