<?php
ob_start(); // Start buffering to catch any whitespace

// Performance & Error Handling Settings
set_time_limit(180); 
ini_set('max_execution_time', 180);
ini_set('memory_limit', '512M');
ini_set('display_errors', 0);
error_reporting(E_ALL);

// CORS & Headers
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

// Clean the buffer to remove any whitespace from db.php or others
ob_clean();

// --- HELPERS ---

// 1. Cookie Management for Steadfast
$cookieDir = __DIR__ . '/cookies';
if (!file_exists($cookieDir)) {
    if (!@mkdir($cookieDir, 0755, true)) {
        $cookieDir = sys_get_temp_dir();
    } else {
        @file_put_contents($cookieDir . '/index.php', '<?php // Silence is golden');
    }
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

function getRandomUserAgent() {
    $agents = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36'
    ];
    return $agents[array_rand($agents)];
}

function make_request($url, $method = 'GET', $params = [], $cookie_file = null) {
    $ch = curl_init();
    
    // Base Headers
    $headers = [
        'User-Agent: ' . getRandomUserAgent(),
        'Accept: application/json, text/plain, */*',
        'Accept-Language: en-US,en;q=0.9',
        'Connection: keep-alive'
    ];

    if (isset($params['headers'])) {
        $headers = array_merge($headers, $params['headers']);
    }

    // CURL Options
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_ENCODING, ""); // Handle compressed responses
    curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4); 
    
    if ($cookie_file) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
    }

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if (isset($params['body'])) {
            $body = $params['body'];
            // Check content type to decide encoding
            $isJson = false;
            foreach ($headers as $h) {
                if (stripos($h, 'Content-Type: application/json') !== false) {
                    $isJson = true;
                    break;
                }
            }
            
            if ($isJson && is_array($body)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
            } elseif (is_array($body)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($body));
            } else {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            }
        }
    }

    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

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
// Standardize phone
if (substr($phone, 0, 2) === '88') {
    $phone = substr($phone, 2);
}

$forceRefresh = isset($_GET['refresh']) && $_GET['refresh'] === 'true';

if (empty($phone) || strlen($phone) < 10) {
    echo json_encode(["error" => "Invalid phone number"]);
    exit;
}

// --- 1. CACHE CHECK ---
if (!$forceRefresh) {
    $cacheSql = "SELECT * FROM fraud_check_cache WHERE phone = '$phone' AND updated_at > NOW() - INTERVAL 24 HOUR";
    $cacheRes = $conn->query($cacheSql);
    
    if ($cacheRes && $cacheRes->num_rows > 0) {
        $row = $cacheRes->fetch_assoc();
        $cacheData = json_decode($row['data'], true);
        
        $delivered = $cacheData['delivered'] ?? 0;
        $cancelled = $cacheData['cancelled'] ?? 0;
        $breakdown = $cacheData['breakdown'] ?? [];
        
        // Legacy support
        if (empty($breakdown) && is_array($cacheData)) {
            if (isset($cacheData['delivered'])) $delivered = (int)$cacheData['delivered'];
        }

        echo json_encode([
            "source" => "local_cache",
            "success_rate" => (float)$row['success_rate'],
            "total_orders" => (int)$row['total_orders'],
            "delivered" => $delivered,
            "cancelled" => $cancelled,
            "details" => $breakdown,
            "debug" => ["Loaded from local database"]
        ]);
        $conn->close();
        exit;
    }
}

// --- 2. LIVE FETCH ---

$history = [
    'delivered' => 0,
    'cancelled' => 0,
    'total' => 0,
    'breakdown' => [],
    'debug' => []
];

// Load Configs
$steadfastConfig = getSetting($conn, 'courier_config');
$pathaoConfig = getSetting($conn, 'pathao_config');
$redxConfig = getSetting($conn, 'redx_config');

// A. STEADFAST LOGIC
if ($steadfastConfig && !empty($steadfastConfig['email']) && !empty($steadfastConfig['password'])) {
    
    $cookieFile = $cookieDir . '/sf_' . md5($steadfastConfig['email']) . '.txt';
    $sfSuccess = false;
    $sfAttempts = 0;

    while ($sfAttempts < 2 && !$sfSuccess) {
        $sfAttempts++;
        $isLoggedIn = false;

        if (file_exists($cookieFile) && (time() - filemtime($cookieFile) < 2700)) {
            $isLoggedIn = true;
        }

        if (!$isLoggedIn) {
            $resInit = make_request("https://steadfast.com.bd/login", 'GET', [], $cookieFile);
            if (preg_match('/<input type="hidden" name="_token" value="(.*?)"/', $resInit['body'], $matches)) {
                $token = $matches[1];
                
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

                if (strpos($loginRes['url'], '/dashboard') !== false || strpos($loginRes['body'], 'Dashboard') !== false) {
                    $isLoggedIn = true;
                } else {
                    $history['debug'][] = "Steadfast: Login failed (Attempt $sfAttempts)";
                }
            } else {
                $history['debug'][] = "Steadfast: CSRF token missing";
            }
        }

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

                if ($s_tot >= 0) {
                    $history['delivered'] += $s_del;
                    $history['cancelled'] += $s_can;
                    $history['total'] += $s_tot;
                    if ($s_tot > 0) {
                        $history['breakdown'][] = ['courier' => 'Steadfast', 'status' => "Del: $s_del | Can: $s_can"];
                    }
                    $sfSuccess = true;
                }
            } else {
                // Invalid response, likely session timeout
                @unlink($cookieFile);
                $history['debug'][] = "Steadfast: Invalid response, cookie cleared.";
            }
        }
        
        if (!$sfSuccess) sleep(1);
    }
} else {
    $history['debug'][] = "Steadfast: Config missing";
}

// B. PATHAO LOGIC
if ($pathaoConfig && !empty($pathaoConfig['username']) && !empty($pathaoConfig['password'])) {
    
    function getPathaoAccessToken($config) {
        $res = make_request("https://merchant.pathao.com/api/v1/login", 'POST', [
            'headers' => ['Content-Type: application/json'],
            'body' => [
                'username' => $config['username'],
                'password' => $config['password']
            ]
        ]);
        $data = json_decode($res['body'], true);
        return $data['access_token'] ?? null;
    }

    $pSession = getSetting($conn, 'pathao_merchant_token');
    $accessToken = $pSession['token'] ?? '';
    $expiry = $pSession['expiry'] ?? 0;

    if (!$accessToken || time() > $expiry) {
        $accessToken = getPathaoAccessToken($pathaoConfig);
        if ($accessToken) {
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

        if ($resCheck['code'] == 401) {
            $history['debug'][] = "Pathao: Token expired, refreshing...";
            $accessToken = getPathaoAccessToken($pathaoConfig);
            if ($accessToken) {
                saveSetting($conn, 'pathao_merchant_token', ['token' => $accessToken, 'expiry' => time() + 20000]);
                $resCheck = make_request($checkUrl, 'POST', [
                    'headers' => [
                        'Content-Type: application/json',
                        'Authorization: Bearer ' . $accessToken
                    ],
                    'body' => ['phone' => $phone]
                ]);
            }
        }

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
        } else {
             $history['debug'][] = "Pathao: No data or API change.";
        }
    } else {
        $history['debug'][] = "Pathao: Auth failed";
    }
} else {
    $history['debug'][] = "Pathao: Config missing";
}

// C. REDX LOGIC
if ($redxConfig && !empty($redxConfig['accessToken'])) {
    $redxPhone = '88' . $phone;
    $checkUrl = 'https://redx.com.bd/api/redx_se/admin/parcel/customer-success-return-rate?phoneNumber=' . $redxPhone;
    
    $resCheck = make_request($checkUrl, 'GET', [
        'headers' => [
            'Authorization: Bearer ' . trim($redxConfig['accessToken']),
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
        $history['debug'][] = "RedX: Fetch failed";
    }
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
    "source" => "live_updated",
    "success_rate" => round($success_rate, 2),
    "total_orders" => $history['total'],
    "delivered" => $history['delivered'],
    "cancelled" => $history['cancelled'],
    "details" => $history['breakdown'],
    "debug" => $history['debug']
]);

$conn->close();
?>