
<?php
// Increase execution time & memory
set_time_limit(120); 
ini_set('max_execution_time', 120);
ini_set('memory_limit', '256M');

// Enable error logging internally but hide from output
ini_set('display_errors', 0);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

include 'db.php';

// --- CONFIGURATION & HELPERS ---

// Create a local directory for cookies to avoid permission issues in system temp
$cookieDir = __DIR__ . '/cookies';
if (!file_exists($cookieDir)) {
    @mkdir($cookieDir, 0755, true);
    // Create index.php to prevent directory listing
    @file_put_contents($cookieDir . '/index.php', '<?php // Silence is golden');
}

function getSetting($conn, $key) {
    $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows > 0) {
        $val = $res->fetch_assoc()['setting_value'];
        $decoded = json_decode($val, true);
        return is_string($decoded) ? json_decode($decoded, true) : $decoded;
    }
    return null;
}

function saveSetting($conn, $key, $value) {
    $val = is_string($value) ? $value : json_encode($value);
    $stmt = $conn->prepare("REPLACE INTO settings (setting_key, setting_value) VALUES (?, ?)");
    $stmt->bind_param("ss", $key, $val);
    $stmt->execute();
}

/**
 * Robust HTTP Request Function
 */
function make_request($url, $method = 'GET', $params = [], $cookie_file = null) {
    $ch = curl_init();
    
    // Default Headers (Mimic Chrome)
    $defaultHeaders = [
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
        'Accept-Language: en-US,en;q=0.9',
        'Cache-Control: no-cache',
        'Connection: keep-alive',
        'Upgrade-Insecure-Requests: 1'
    ];

    $customHeaders = isset($params['headers']) ? $params['headers'] : [];
    $finalHeaders = array_merge($defaultHeaders, $customHeaders);

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Handle with care in prod
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 40);
    curl_setopt($ch, CURLOPT_ENCODING, ""); // Handle gzip
    
    // Cookie Handling
    if ($cookie_file) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
    }

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if (isset($params['body'])) {
            $body = $params['body'];
            // If body is array, decide based on content-type
            if (is_array($body)) {
                $isJson = false;
                foreach($finalHeaders as $h) {
                    if (stripos($h, 'application/json') !== false) $isJson = true;
                }
                
                if ($isJson) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
                } else {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($body));
                }
            } else {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            }
        }
    }

    curl_setopt($ch, CURLOPT_HTTPHEADER, $finalHeaders);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    $err = curl_error($ch);
    curl_close($ch);

    return [
        'body' => $response, 
        'code' => $httpCode, 
        'url' => $finalUrl, 
        'error' => $err
    ];
}

// --- INPUT HANDLING ---

$phone = isset($_GET['phone']) ? preg_replace('/[^0-9]/', '', $_GET['phone']) : '';
$phone = preg_replace('/^88/', '', $phone); // Remove 88 prefix if present

if (empty($phone) || strlen($phone) < 10) {
    echo json_encode(["error" => "Invalid phone number"]);
    exit;
}

$history = [
    'delivered' => 0,
    'cancelled' => 0,
    'total' => 0,
    'breakdown' => [],
    'debug' => []
];

// Fetch Configurations
$steadfastConfig = getSetting($conn, 'courier_config');
$pathaoConfig = getSetting($conn, 'pathao_config');
$redxConfig = getSetting($conn, 'redx_config');

// ==========================================
// 1. STEADFAST LOGIC
// ==========================================
if ($steadfastConfig && !empty($steadfastConfig['email']) && !empty($steadfastConfig['password'])) {
    
    // Cookie file path
    $cookieFile = $cookieDir . '/sf_' . md5($steadfastConfig['email']) . '.txt';
    
    $isLoggedIn = false;

    // Check if session is arguably valid (file exists and modified < 45 mins ago)
    if (file_exists($cookieFile) && (time() - filemtime($cookieFile) < 2700)) {
        $isLoggedIn = true;
        $history['debug'][] = "Steadfast: Using cached session";
    }

    // Login if not valid
    if (!$isLoggedIn) {
        $history['debug'][] = "Steadfast: Attempting fresh login";
        
        // Step 1: Visit Login to get CSRF Token
        $resInit = make_request("https://steadfast.com.bd/login", 'GET', [], $cookieFile);
        
        // Extract CSRF Token
        if (preg_match('/<input type="hidden" name="_token" value="(.*?)"/', $resInit['body'], $matches)) {
            $token = $matches[1];
            
            // Step 2: Perform Login
            $loginRes = make_request("https://steadfast.com.bd/login", 'POST', [
                'body' => [
                    '_token' => $token,
                    'email' => trim($steadfastConfig['email']),
                    'password' => trim($steadfastConfig['password'])
                ],
                'headers' => [
                    'Origin: https://steadfast.com.bd',
                    'Referer: https://steadfast.com.bd/login',
                    'Content-Type: application/x-www-form-urlencoded'
                ]
            ], $cookieFile);

            // Verify Login Success (Check if redirected to dashboard or contains dashboard text)
            if (strpos($loginRes['url'], '/dashboard') !== false || strpos($loginRes['body'], 'Dashboard') !== false) {
                $isLoggedIn = true;
                $history['debug'][] = "Steadfast: Login Successful";
            } else {
                $history['debug'][] = "Steadfast: Login Failed (Check Credentials)";
                // Optional: Log body to debug file if needed
            }
        } else {
            $history['debug'][] = "Steadfast: Could not find CSRF token";
        }
    }

    // Get Data
    if ($isLoggedIn) {
        $checkUrl = "https://steadfast.com.bd/user/frauds/check/" . $phone;
        $resCheck = make_request($checkUrl, 'GET', [
            'headers' => ['Referer: https://steadfast.com.bd/user/frauds']
        ], $cookieFile);
        
        $data = json_decode($resCheck['body'], true);

        if ($data && isset($data['total_delivered'])) {
            $s_del = (int)$data['total_delivered'];
            $s_can = isset($data['total_cancelled']) ? (int)$data['total_cancelled'] : 0;
            $s_tot = $s_del + $s_can;

            if ($s_tot > 0) {
                $history['delivered'] += $s_del;
                $history['cancelled'] += $s_can;
                $history['total'] += $s_tot;
                $history['breakdown'][] = ['courier' => 'Steadfast', 'status' => "Del: $s_del | Can: $s_can"];
            } else {
                $history['debug'][] = "Steadfast: No data found for phone";
            }
        } else {
            // Check if session expired during request
            if ($resCheck['code'] == 401 || strpos($resCheck['body'], 'login') !== false) {
                $history['debug'][] = "Steadfast: Session expired during check";
                @unlink($cookieFile); // Force login next time
            } else {
                $history['debug'][] = "Steadfast: Invalid JSON response";
            }
        }
    }
} else {
    $history['debug'][] = "Steadfast: Credentials missing in settings";
}

// ==========================================
// 2. PATHAO LOGIC
// ==========================================
if ($pathaoConfig && !empty($pathaoConfig['username']) && !empty($pathaoConfig['password'])) {
    
    $pSession = getSetting($conn, 'pathao_merchant_token');
    $accessToken = $pSession['token'] ?? '';
    $expiry = $pSession['expiry'] ?? 0;

    // Refresh Token
    if (!$accessToken || time() > $expiry) {
        $history['debug'][] = "Pathao: Refreshing Token";
        
        $loginRes = make_request("https://merchant.pathao.com/api/v1/login", 'POST', [
            'headers' => ['Content-Type: application/json'],
            'body' => [
                'username' => $pathaoConfig['username'],
                'password' => $pathaoConfig['password']
            ]
        ]);

        $authData = json_decode($loginRes['body'], true);
        if (isset($authData['access_token'])) {
            $accessToken = $authData['access_token'];
            saveSetting($conn, 'pathao_merchant_token', ['token' => $accessToken, 'expiry' => time() + 20000]); 
            $history['debug'][] = "Pathao: Token Refreshed";
        } else {
            $history['debug'][] = "Pathao: Login Failed";
        }
    }

    if ($accessToken) {
        $checkUrl = "https://merchant.pathao.com/api/v1/user/success";
        $resCheck = make_request($checkUrl, 'POST', [
            'headers' => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $accessToken
            ],
            'body' => ['phone' => $phone]
        ]);

        $pData = json_decode($resCheck['body'], true);
        
        if (isset($pData['data']['customer'])) {
            $cust = $pData['data']['customer'];
            $del = (int)($cust['successful_delivery'] ?? 0);
            $tot = (int)($cust['total_delivery'] ?? 0);
            $can = $tot - $del;

            if ($tot > 0) {
                $history['delivered'] += $del;
                $history['cancelled'] += $can;
                $history['total'] += $tot;
                $history['breakdown'][] = ['courier' => 'Pathao', 'status' => "Del: $del | Can: $can"];
            }
        } elseif ($resCheck['code'] == 401) {
            saveSetting($conn, 'pathao_merchant_token', ['token' => '', 'expiry' => 0]);
            $history['debug'][] = "Pathao: Token Expired";
        }
    }
}

// ==========================================
// 3. REDX LOGIC
// ==========================================
if ($redxConfig && !empty($redxConfig['accessToken'])) {
    $checkUrl = 'https://redx.com.bd/api/redx_se/admin/parcel/customer-success-return-rate?phoneNumber=88' . $phone;
    
    $resCheck = make_request($checkUrl, 'GET', [
        'headers' => [
            'Authorization: Bearer ' . trim($redxConfig['accessToken']),
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
            'Accept: application/json',
            'Content-Type: application/json'
        ]
    ]);

    $rData = json_decode($resCheck['body'], true);

    if ($rData && isset($rData['data'])) {
        $del = (int)($rData['data']['deliveredParcels'] ?? 0);
        $tot = (int)($rData['data']['totalParcels'] ?? 0);
        $can = $tot - $del;

        if ($tot > 0) {
            $history['delivered'] += $del;
            $history['cancelled'] += $can;
            $history['total'] += $tot;
            $history['breakdown'][] = ['courier' => 'RedX', 'status' => "Del: $del | Can: $can"];
        }
    } else {
        $history['debug'][] = "RedX: No data or Token Error";
    }
}

// --- FINAL RESPONSE ---

$success_rate = 0;
if ($history['total'] > 0) {
    $success_rate = ($history['delivered'] / $history['total']) * 100;
}

// Cache Result
if ($history['total'] > 0) {
    $jsonData = $conn->real_escape_string(json_encode($history['breakdown']));
    $sql = "INSERT INTO fraud_check_cache (phone, data, success_rate, total_orders) 
            VALUES ('$phone', '$jsonData', $success_rate, {$history['total']})
            ON DUPLICATE KEY UPDATE 
            data = '$jsonData', success_rate = $success_rate, total_orders = {$history['total']}, updated_at = NOW()";
    $conn->query($sql);
}

echo json_encode([
    "source" => "live_global",
    "success_rate" => round($success_rate, 2),
    "total_orders" => $history['total'],
    "delivered" => $history['delivered'],
    "cancelled" => $history['cancelled'],
    "details" => $history['breakdown'],
    "debug" => $history['debug']
]);

$conn->close();
?>
