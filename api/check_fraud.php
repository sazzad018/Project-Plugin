<?php
ob_start();

// Performance & Error Handling
set_time_limit(60); 
ini_set('display_errors', 0);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    ob_end_clean();
    http_response_code(200);
    exit;
}

include 'db.php';
ob_clean(); // Ensure clean output

// --- HELPERS ---

function getSetting($conn, $key) {
    $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows > 0) {
        $val = $res->fetch_assoc()['setting_value'];
        // Remove quotes if json encoded string
        return trim($val, '"'); 
    }
    return null;
}

function make_request($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    return ['body' => $response, 'code' => $httpCode, 'error' => $err];
}

// --- INPUT HANDLING ---

$phone = isset($_GET['phone']) ? preg_replace('/[^0-9]/', '', $_GET['phone']) : '';
// Remove 88 prefix if present for Hoorin API consistency
if (substr($phone, 0, 2) === '88') {
    $phone = substr($phone, 2);
}

$forceRefresh = isset($_GET['refresh']) && $_GET['refresh'] === 'true';

if (empty($phone) || strlen($phone) < 10) {
    echo json_encode(["error" => "Invalid phone number"]);
    exit;
}

// --- 1. CACHE CHECK (DATABASE) ---
if (!$forceRefresh) {
    // Cache valid for 24 hours
    $cacheSql = "SELECT * FROM fraud_check_cache WHERE phone = '$phone' AND updated_at > NOW() - INTERVAL 24 HOUR";
    $cacheRes = $conn->query($cacheSql);
    
    if ($cacheRes && $cacheRes->num_rows > 0) {
        $row = $cacheRes->fetch_assoc();
        $cacheData = json_decode($row['data'], true);
        
        echo json_encode([
            "source" => "local_cache",
            "success_rate" => (float)$row['success_rate'],
            "total_orders" => (int)$row['total_orders'],
            "delivered" => $cacheData['delivered'] ?? 0,
            "cancelled" => $cacheData['cancelled'] ?? 0,
            "details" => $cacheData['breakdown'] ?? [],
            "debug" => ["Loaded from local cache"]
        ]);
        $conn->close();
        exit;
    }
}

// --- 2. LIVE FETCH (HOORIN API) ---

// Get API Key from DB or use Default
$dbKey = getSetting($conn, 'hoorin_api_key');
$apiKey = $dbKey ? $dbKey : 'f72e06481ead7b346161b7'; // Default provided key

$apiUrl = "https://dash.hoorin.com/api/courier/api?apiKey=" . $apiKey . "&searchTerm=" . $phone;

$apiRes = make_request($apiUrl);
$apiData = json_decode($apiRes['body'], true);

$history = [
    'delivered' => 0,
    'cancelled' => 0,
    'total' => 0,
    'breakdown' => [],
    'debug' => []
];

$history['debug'][] = "API URL: " . $apiUrl;
$history['debug'][] = "Response Code: " . $apiRes['code'];

if ($apiData && isset($apiData['Summaries'])) {
    foreach ($apiData['Summaries'] as $courierName => $data) {
        // Normalize keys (API returns different casing sometimes)
        $total = isset($data['Total Parcels']) ? (int)$data['Total Parcels'] : (isset($data['Total Delivery']) ? (int)$data['Total Delivery'] : 0);
        $del = isset($data['Delivered Parcels']) ? (int)$data['Delivered Parcels'] : (isset($data['Successful Delivery']) ? (int)$data['Successful Delivery'] : 0);
        $can = isset($data['Canceled Parcels']) ? (int)$data['Canceled Parcels'] : (isset($data['Canceled Delivery']) ? (int)$data['Canceled Delivery'] : 0);

        if ($total > 0) {
            $history['total'] += $total;
            $history['delivered'] += $del;
            $history['cancelled'] += $can;
            
            $history['breakdown'][] = [
                'courier' => $courierName,
                'status' => "Del: $del | Can: $can"
            ];
        }
    }
} else {
    $history['debug'][] = "Invalid API Response or No Data Found";
    $history['debug'][] = substr($apiRes['body'], 0, 200); // Log partial response
}

// --- FINAL CALCULATION ---

$success_rate = 0;
if ($history['total'] > 0) {
    $success_rate = ($history['delivered'] / $history['total']) * 100;
}

// Cache the result
$jsonData = $conn->real_escape_string(json_encode($history));
$sql = "INSERT INTO fraud_check_cache (phone, data, success_rate, total_orders) 
        VALUES ('$phone', '$jsonData', $success_rate, {$history['total']})
        ON DUPLICATE KEY UPDATE 
        data = '$jsonData', success_rate = $success_rate, total_orders = {$history['total']}, updated_at = NOW()";
$conn->query($sql);

echo json_encode([
    "source" => "live_api_hoorin",
    "success_rate" => round($success_rate, 2),
    "total_orders" => $history['total'],
    "delivered" => $history['delivered'],
    "cancelled" => $history['cancelled'],
    "details" => $history['breakdown'],
    "debug" => $history['debug']
]);

$conn->close();
?>