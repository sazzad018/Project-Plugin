<?php
// Increase execution time & memory
set_time_limit(120); 
ini_set('max_execution_time', 120);
ini_set('memory_limit', '256M');

// Hide errors from output but log them
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

// --- DATABASE HELPER FUNCTIONS (Replacement for WP update_option) ---

function getSetting($conn, $key) {
    $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows > 0) {
        $val = $res->fetch_assoc()['setting_value'];
        $decoded = json_decode($val, true);
        return is_array($decoded) ? $decoded : $val; // Return array if JSON, string otherwise
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
 * Handles Cookie Storage in Memory/DB instead of Files
 */
function make_request($url, $method = 'GET', $params = [], $cookies_string = null) {
    $ch = curl_init();
    
    $defaultHeaders = [
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Accept: application/json, text/plain, */*',
        'Connection: keep-alive'
    ];

    $customHeaders = isset($params['headers']) ? $params['headers'] : [];
    
    // Merge headers properly
    $finalHeaders = [];
    foreach ($defaultHeaders as $h) $finalHeaders[] = $h;
    
    // Add custom headers (handling Key: Value format)
    if (count($customHeaders) > 0) {
        // If associative array
        if (array_keys($customHeaders) !== range(0, count($customHeaders) - 1)) {
            foreach ($customHeaders as $k => $v) {
                $finalHeaders[] = "$k: $v";
            }
        } else {
            // If indexed array
            $finalHeaders = array_merge($finalHeaders, $customHeaders);
        }
    }

    // Add Cookie Header manually if provided (SBSP Style)
    if ($cookies_string) {
        $finalHeaders[] = "Cookie: " . $cookies_string;
    }

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    // Capture Header to extract cookies
    curl_setopt($ch, CURLOPT_HEADER, 1); 

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if (isset($params['body'])) {
            $body = $params['body'];
            if (is_array($body)) {
                // Check if sending JSON
                $isJson = false;
                foreach($finalHeaders as $h) {
                    if (stripos($h, 'application/json') !== false) $isJson = true;
                }
                curl_setopt($ch, CURLOPT_POSTFIELDS, $isJson ? json_encode($body) : http_build_query($body));
            } else {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            }
        }
    }

    curl_setopt($ch, CURLOPT_HTTPHEADER, $finalHeaders);

    $responseRaw = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $err = curl_error($ch);
    
    $headerStr = substr($responseRaw, 0, $headerSize);
    $bodyStr = substr($responseRaw, $headerSize);

    curl_close($ch);

    return [
        'headers' => $headerStr,
        'body' => $bodyStr, 
        'code' => $httpCode,
        'error' => $err
    ];
}

// Helper to extract cookies from Header String
function extract_cookies($header) {
    preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $header, $matches);
    $cookies = [];
    foreach($matches[1] as $item) {
        $cookies[] = $item;
    }
    return implode('; ', $cookies);
}

// --- INPUT HANDLING ---

$phone = isset($_GET['phone']) ? preg_replace('/[^0-9]/', '', $_GET['phone']) : '';
$phone = preg_replace('/^88/', '', $phone); 

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
// 1. STEADFAST LOGIC (SBSP Style - DB Storage)
// ==========================================
if ($steadfastConfig && !empty($steadfastConfig['email']) && !empty($steadfastConfig['password'])) {
    
    // Retrieve session from DB
    $sfSession = getSetting($conn, 'steadfast_session');
    $cookies = isset($sfSession['cookies']) ? $sfSession['cookies'] : null;
    $lastLogin = isset($sfSession['last_login']) ? $sfSession['last_login'] : 0;

    // Relogin if session is old (45 mins) or empty
    if (empty($cookies) || (time() - $lastLogin > 2700)) {
        $history['debug'][] = "Steadfast: Logging in...";
        
        // Step 1: Get CSRF
        $resInit = make_request("https://steadfast.com.bd/login", 'GET');
        preg_match('/<input type="hidden" name="_token" value="(.*?)"/', $resInit['body'], $matches);
        $token = isset($matches[1]) ? $matches[1] : '';
        
        // Initial Cookies
        $initCookies = extract_cookies($resInit['headers']);

        if ($token) {
            // Step 2: POST Login
            $resLogin = make_request("https://steadfast.com.bd/login", 'POST', [
                'body' => [
                    '_token' => $token,
                    'email' => trim($steadfastConfig['email']),
                    'password' => trim($steadfastConfig['password'])
                ],
                'headers' => ['Content-Type' => 'application/x-www-form-urlencoded']
            ], $initCookies);

            // Extract Login Cookies
            $loginCookies = extract_cookies($resLogin['headers']);
            
            // Merge Cookies (Init + Login) for persistence
            $finalCookies = $initCookies . '; ' . $loginCookies;

            if (strpos($resLogin['body'], 'Dashboard') !== false || strpos($resLogin['headers'], 'Location') !== false) {
                // Save to DB
                saveSetting($conn, 'steadfast_session', [
                    'cookies' => $finalCookies,
                    'last_login' => time()
                ]);
                $cookies = $finalCookies;
                $history['debug'][] = "Steadfast: Login Success";
            } else {
                $history['debug'][] = "Steadfast: Login Failed";
            }
        }
    }

    if ($cookies) {
        $checkUrl = "https://steadfast.com.bd/user/frauds/check/" . $phone;
        $resCheck = make_request($checkUrl, 'GET', [], $cookies);
        
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
            }
        } elseif ($resCheck['code'] == 401) {
            // Force re-login next time
            saveSetting($conn, 'steadfast_session', []);
            $history['debug'][] = "Steadfast: Session Expired";
        }
    }
}

// ==========================================
// 2. PATHAO LOGIC (Using Stored Token)
// ==========================================
if ($pathaoConfig && !empty($pathaoConfig['username']) && !empty($pathaoConfig['password'])) {
    
    $pSession = getSetting($conn, 'pathao_merchant_token');
    $accessToken = isset($pSession['token']) ? $pSession['token'] : '';
    $expiry = isset($pSession['expiry']) ? $pSession['expiry'] : 0;

    // Refresh Token
    if (!$accessToken || time() > $expiry) {
        $history['debug'][] = "Pathao: Refreshing Token";
        
        $resLogin = make_request("https://merchant.pathao.com/api/v1/login", 'POST', [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => [
                'username' => $pathaoConfig['username'],
                'password' => $pathaoConfig['password']
            ]
        ]);

        $authData = json_decode($resLogin['body'], true);
        if (isset($authData['access_token'])) {
            $accessToken = $authData['access_token'];
            saveSetting($conn, 'pathao_merchant_token', ['token' => $accessToken, 'expiry' => time() + 20000]); 
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
    }
}

// --- FINAL RESPONSE ---

$success_rate = 0;
if ($history['total'] > 0) {
    $success_rate = ($history['delivered'] / $history['total']) * 100;
}

// Cache Result (Avoid frequent hits if needed, mainly for UI speed)
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