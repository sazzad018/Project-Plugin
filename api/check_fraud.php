
<?php
// Increase execution time
set_time_limit(120); 
ini_set('max_execution_time', 120);

// Clean output buffer
if (function_exists('ob_clean')) ob_clean();

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

include 'db.php';

// Disable error display to prevent JSON errors
ini_set('display_errors', 0);
error_reporting(0);

// --- HELPER FUNCTIONS ---

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
 * Standard cURL Request Wrapper
 * Mimics wp_remote_get/post behavior from the Plugin
 */
function make_request($url, $method = 'GET', $params = [], $cookie_file = null) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    // Cookie Handling for Steadfast
    if ($cookie_file) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
    }

    $headers = isset($params['headers']) ? $params['headers'] : [];
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if (isset($params['body'])) {
            $body = $params['body'];
            // Detect JSON vs Form Data
            if (is_array($body)) {
                // If Content-Type is JSON, encode it
                $isJson = false;
                foreach($headers as $h) {
                    if (strpos(strtolower($h), 'content-type: application/json') !== false) $isJson = true;
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

    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    return ['body' => $response, 'code' => $httpCode, 'error' => $err];
}

// --- INPUT HANDLING ---

$phone = isset($_GET['phone']) ? $_GET['phone'] : '';
$force_refresh = isset($_GET['refresh']) && $_GET['refresh'] == 'true';

// Sanitize Phone Number (Logic from SBSP_Functions::sanitize_phone_number)
$phone = preg_replace('/^\+?88/', '', $phone); // Remove +88 or 88
$phone = preg_replace('/^\+/', '', $phone);    // Remove + if still at start
$phone = preg_replace('/[^0-9]/', '', $phone); // Remove all except numbers

if (empty($phone) || strlen($phone) != 11) {
    echo json_encode(["error" => "Invalid phone number"]);
    exit;
}

// 1. Check Database Cache (Skip if force refresh)
if (!$force_refresh) {
    $stmt = $conn->prepare("SELECT * FROM fraud_check_cache WHERE phone = ? AND updated_at > (NOW() - INTERVAL 12 HOUR)");
    $stmt->bind_param("s", $phone);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $details = json_decode($row['data'], true);
        echo json_encode([
            "source" => "cache",
            "success_rate" => (float)$row['success_rate'],
            "total_orders" => (int)$row['total_orders'],
            "delivered" => 0, 
            "cancelled" => 0,
            "details" => $details
        ]);
        exit;
    }
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
$cookieDir = sys_get_temp_dir();

// ==========================================
// 1. STEADFAST LOGIC (Matching SBSP Plugin)
// ==========================================
if ($steadfastConfig && !empty($steadfastConfig['email']) && !empty($steadfastConfig['password'])) {
    
    // Create unique cookie file for this account
    $cookieFile = $cookieDir . '/sf_' . md5($steadfastConfig['email']) . '.txt';
    $isLoggedIn = false;

    // Check if we have a recent valid session (simple file mtime check)
    if (file_exists($cookieFile) && (time() - filemtime($cookieFile) < 1800)) {
        $isLoggedIn = true;
    }

    // Attempt Login if needed
    if (!$isLoggedIn) {
        // Step 1: GET Login Page for CSRF Token & Init Cookies
        $res = make_request("https://steadfast.com.bd/login", 'GET', [], $cookieFile);
        preg_match('/<input type="hidden" name="_token" value="(.*?)"/', $res['body'], $matches);
        $token = $matches[1] ?? '';

        if ($token) {
            // Step 2: POST Login
            $loginRes = make_request("https://steadfast.com.bd/login", 'POST', [
                'body' => [
                    '_token' => $token,
                    'email' => $steadfastConfig['email'],
                    'password' => $steadfastConfig['password']
                ],
                'headers' => ['Content-Type: application/x-www-form-urlencoded']
            ], $cookieFile);

            // Check if login redirected (302) or successful body content
            if ($loginRes['code'] == 302 || strpos($loginRes['body'], 'dashboard') !== false) {
                $isLoggedIn = true;
                $history['debug'][] = "Steadfast Login OK";
            } else {
                $history['debug'][] = "Steadfast Login Failed";
            }
        }
    }

    // If Login Success, Get Data
    if ($isLoggedIn) {
        $checkUrl = "https://steadfast.com.bd/user/frauds/check/" . $phone;
        // The plugin uses wp_remote_get which is simple GET with cookies
        $resCheck = make_request($checkUrl, 'GET', [], $cookieFile);
        
        $data = json_decode($resCheck['body'], true);

        if ($data && isset($data['total_delivered'])) {
            $s_del = (int)$data['total_delivered'];
            $s_can = isset($data['total_cancelled']) ? (int)$data['total_cancelled'] : 0;
            $s_tot = $s_del + $s_can; // Plugin Logic: Total is sum of del + cancel (ignoring pending)

            if ($s_tot > 0) {
                $history['delivered'] += $s_del;
                $history['cancelled'] += $s_can;
                $history['total'] += $s_tot;
                $history['breakdown'][] = ['courier' => 'Steadfast', 'status' => "Del: $s_del | Can: $s_can"];
            }
        } else {
            // Retry logic if 401 (Session Expired)
            if ($resCheck['code'] == 401) {
                @unlink($cookieFile); // Force re-login next time
            }
        }
    }
}

// ==========================================
// 2. PATHAO LOGIC (Matching SBSP Plugin)
// ==========================================
if ($pathaoConfig && !empty($pathaoConfig['username']) && !empty($pathaoConfig['password'])) {
    
    // Check for cached token in DB
    $pSession = getSetting($conn, 'pathao_merchant_token');
    $accessToken = $pSession['token'] ?? '';
    $expiry = $pSession['expiry'] ?? 0;

    // Refresh Token if needed
    if (!$accessToken || time() > $expiry) {
        // Plugin uses this specific endpoint for username/password login
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
            // Cache it for ~6 hours
            saveSetting($conn, 'pathao_merchant_token', ['token' => $accessToken, 'expiry' => time() + 20000]); 
            $history['debug'][] = "Pathao Login OK";
        } else {
            $history['debug'][] = "Pathao Login Failed: " . ($authData['message'] ?? 'Unknown');
        }
    }

    // Get Data using Token
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
        
        // Plugin Logic: $data['data']['customer']['successful_delivery']
        if (isset($pData['data']['customer'])) {
            $cust = $pData['data']['customer'];
            $del = (int)($cust['successful_delivery'] ?? 0);
            $tot = (int)($cust['total_delivery'] ?? 0);
            $can = $tot - $del; // Plugin calculates cancel as total - success

            if ($tot > 0) {
                $history['delivered'] += $del;
                $history['cancelled'] += $can;
                $history['total'] += $tot;
                $history['breakdown'][] = ['courier' => 'Pathao', 'status' => "Del: $del | Can: $can"];
            }
        } elseif ($resCheck['code'] == 401) {
            // Token expired, clear cache
            saveSetting($conn, 'pathao_merchant_token', ['token' => '', 'expiry' => 0]);
        }
    }
}

// ==========================================
// 3. REDX LOGIC (Matching SBSP Plugin)
// ==========================================
if ($redxConfig && !empty($redxConfig['accessToken'])) {
    // RedX uses a simple GET request with Bearer token
    $checkUrl = 'https://redx.com.bd/api/redx_se/admin/parcel/customer-success-return-rate?phoneNumber=88' . $phone;
    
    $resCheck = make_request($checkUrl, 'GET', [
        'headers' => [
            'Authorization: Bearer ' . $redxConfig['accessToken'],
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
            'Accept: application/json, text/plain, */*',
            'Content-Type: application/json'
        ]
    ]);

    $rData = json_decode($resCheck['body'], true);

    if ($resCheck['code'] === 401) {
        $history['debug'][] = "RedX Token Invalid";
    } elseif ($rData && isset($rData['data'])) {
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

// --- CALCULATION & RESPONSE ---

$success_rate = 0;
if ($history['total'] > 0) {
    $success_rate = ($history['delivered'] / $history['total']) * 100;
}

// Update DB logic
if ($history['total'] > 0) {
    $jsonData = $conn->real_escape_string(json_encode($history['breakdown']));
    $sql = "INSERT INTO fraud_check_cache (phone, data, success_rate, total_orders) 
            VALUES ('$phone', '$jsonData', $success_rate, {$history['total']})
            ON DUPLICATE KEY UPDATE 
            data = '$jsonData', success_rate = $success_rate, total_orders = {$history['total']}, updated_at = NOW()";
    $conn->query($sql);
} else {
    // Fallback if APIs failed/no data but we have old cache (Optional)
    $stmt = $conn->prepare("SELECT * FROM fraud_check_cache WHERE phone = ?");
    $stmt->bind_param("s", $phone);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $history['total'] = (int)$row['total_orders'];
        $success_rate = (float)$row['success_rate'];
        $history['breakdown'] = json_decode($row['data'], true);
        $history['debug'][] = "Used Fallback Cache";
        $history['delivered'] = 0; // Not stored in flat columns, just showing rate
        $history['cancelled'] = 0;
    }
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