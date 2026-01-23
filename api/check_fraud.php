<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

include 'db.php';

// --- AUTO-CREATE TABLE IF NOT EXISTS ---
$conn->query("CREATE TABLE IF NOT EXISTS fraud_check_cache (
    phone VARCHAR(20) PRIMARY KEY,
    data JSON DEFAULT NULL,
    success_rate DECIMAL(5,2) DEFAULT 0.00,
    total_orders INT(11) DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");
// ---------------------------------------

// Helper: Get Settings
function getSetting($conn, $key) {
    // Ensure settings table exists
    $conn->query("CREATE TABLE IF NOT EXISTS settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(50) UNIQUE NOT NULL,
        setting_value TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");

    $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $res = $stmt->get_result();
    return ($res->num_rows > 0) ? json_decode($res->fetch_assoc()['setting_value'], true) : null;
}

// Helper: Save Settings
function saveSetting($conn, $key, $value) {
    $val = json_encode($value);
    $stmt = $conn->prepare("REPLACE INTO settings (setting_key, setting_value) VALUES (?, ?)");
    $stmt->bind_param("ss", $key, $val);
    $stmt->execute();
}

// Helper: Parse Cookies from Header
function get_cookies_from_header($header) {
    preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $header, $matches);
    $cookies = [];
    foreach ($matches[1] as $item) {
        $cookies[] = $item;
    }
    return implode("; ", $cookies);
}

// Helper: Standard cURL Request with User-Agent
function make_curl_request($url, $method = 'GET', $data = [], $headers = [], $cookies = '') {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_HEADER, true); // Always get headers to capture cookies

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($data) ? http_build_query($data) : $data);
    }

    $reqHeaders = [];
    if (!empty($cookies)) {
        $reqHeaders[] = "Cookie: $cookies";
    }
    foreach ($headers as $h) {
        $reqHeaders[] = $h;
    }
    if (!empty($reqHeaders)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $reqHeaders);
    }

    $response = curl_exec($ch);
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $header = substr($response, 0, $header_size);
    $body = substr($response, $header_size);
    
    curl_close($ch);

    return ['header' => $header, 'body' => $body];
}

// --- MAIN LOGIC ---

$phone = isset($_GET['phone']) ? $_GET['phone'] : '';
$force_refresh = isset($_GET['refresh']) && $_GET['refresh'] == 'true';

if (empty($phone)) {
    echo json_encode(["error" => "Phone number required"]);
    exit;
}

// Normalize Phone
$phone = preg_replace('/[^0-9]/', '', $phone);
if (strlen($phone) > 11 && substr($phone, 0, 2) == '88') {
    $phone = substr($phone, 2); 
}

// Check Cache
if (!$force_refresh) {
    $stmt = $conn->prepare("SELECT * FROM fraud_check_cache WHERE phone = ? AND updated_at > (NOW() - INTERVAL 24 HOUR)");
    $stmt->bind_param("s", $phone);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $details = json_decode($row['data'], true);
        
        $total = (int)$row['total_orders'];
        $rate = (float)$row['success_rate'];
        $delivered = 0;
        $cancelled = 0;
        
        // Try to extract exact counts from details if available
        if (is_array($details)) {
            foreach ($details as $d) {
                if (preg_match('/Delivered: (\d+)/', $d['status'], $m)) $delivered += (int)$m[1];
                if (preg_match('/Cancelled: (\d+)/', $d['status'], $m)) $cancelled += (int)$m[1];
            }
        }
        
        // Fallback calculation if parsing fails
        if ($delivered == 0 && $cancelled == 0 && $total > 0) {
            $delivered = round(($rate / 100) * $total);
            $cancelled = $total - $delivered;
        }

        echo json_encode([
            "source" => "cache",
            "success_rate" => $rate,
            "total_orders" => $total,
            "delivered" => $delivered, 
            "cancelled" => $cancelled,
            "details" => $details
        ]);
        exit;
    }
}

$history = [
    'delivered' => 0,
    'cancelled' => 0,
    'total' => 0,
    'breakdown' => []
];

$steadfastConfig = getSetting($conn, 'courier_config');
$pathaoConfig = getSetting($conn, 'pathao_config');

// 1. STEADFAST GLOBAL CHECK (Login & Scrape)
if ($steadfastConfig && !empty($steadfastConfig['email']) && !empty($steadfastConfig['password'])) {
    
    $session = getSetting($conn, 'steadfast_session');
    $cookies = $session['cookies'] ?? '';
    $last_login = $session['last_login'] ?? 0;
    $logged_in = false;

    // Check if session is valid by trying a lightweight request? 
    // Or just re-login if older than 12 hours.
    if (empty($cookies) || (time() - $last_login > 43200)) { // 12 hours
        // Login Flow
        $loginUrl = "https://steadfast.com.bd/login";
        
        // Step A: Get Page for Token & Cookies
        $res1 = make_curl_request($loginUrl, 'GET');
        $init_cookies = get_cookies_from_header($res1['header']);
        
        preg_match('/<input type="hidden" name="_token" value="(.*?)"/', $res1['body'], $matches);
        $token = $matches[1] ?? '';

        if ($token) {
            // Step B: Post Login
            $postData = [
                '_token' => $token,
                'email' => $steadfastConfig['email'],
                'password' => $steadfastConfig['password']
            ];
            
            // Wait a sec to simulate human
            sleep(1);
            
            $res2 = make_curl_request($loginUrl, 'POST', $postData, [], $init_cookies);
            $auth_cookies = get_cookies_from_header($res2['header']);
            
            // Merge cookies
            $cookies = $init_cookies . '; ' . $auth_cookies;
            
            // Verify Login success (check if redirect to dashboard or contains user data)
            if (strpos($res2['body'], 'dashboard') !== false || strpos($res2['header'], 'Location') !== false) {
                saveSetting($conn, 'steadfast_session', ['cookies' => $cookies, 'last_login' => time()]);
                $logged_in = true;
            }
        }
    } else {
        $logged_in = true;
    }
    
    if ($logged_in) {
        $checkUrl = "https://steadfast.com.bd/user/frauds/check/" . $phone;
        $res3 = make_curl_request($checkUrl, 'GET', [], ["X-Requested-With: XMLHttpRequest"], $cookies);
        
        $data = json_decode($res3['body'], true);
        
        // If response is not JSON or empty, session might have died despite our check
        if (!$data && (time() - $last_login < 43200)) {
            // Retry login once
            saveSetting($conn, 'steadfast_session', ['cookies' => '', 'last_login' => 0]);
            // (Recursive call or logic repeat omitted for simplicity, next run will fix)
        }

        $s_del = 0; $s_can = 0; $s_tot = 0;

        if (isset($data['total_delivered'])) {
            $s_del = (int)$data['total_delivered'];
            $s_can = isset($data['total_cancelled']) ? (int)$data['total_cancelled'] : 0;
            $s_tot = $s_del + $s_can;
        } elseif (is_array($data)) {
             // Handle array list response
             foreach ($data as $c) {
                if (isset($c['status'])) {
                    $status = strtolower($c['status']);
                    $s_tot++;
                    if (strpos($status, 'delivered') !== false) {
                        $s_del++;
                    } elseif (strpos($status, 'cancelled') !== false || strpos($status, 'return') !== false) {
                        $s_can++;
                    }
                }
            }
        }

        if ($s_tot > 0) {
            $history['delivered'] += $s_del;
            $history['cancelled'] += $s_can;
            $history['total'] += $s_tot;
            $history['breakdown'][] = ['courier' => 'Steadfast', 'status' => "Delivered: $s_del | Cancelled: $s_can"];
        }
    }
}

// 2. PATHAO GLOBAL CHECK (Merchant API)
if ($pathaoConfig && !empty($pathaoConfig['username']) && !empty($pathaoConfig['password'])) {
    
    $p_session = getSetting($conn, 'pathao_session');
    $access_token = $p_session['access_token'] ?? '';
    $last_login = $p_session['last_login'] ?? 0;
    
    // Pathao tokens usually last longer, but let's refresh every 6 hours to be safe
    if (empty($access_token) || (time() - $last_login > 21600)) {
        $loginUrl = "https://merchant.pathao.com/api/v1/login";
        $headers = ["Content-Type: application/json"];
        $postData = json_encode([
            "username" => $pathaoConfig['username'],
            "password" => $pathaoConfig['password']
        ]);
        
        $res = make_curl_request($loginUrl, 'POST', $postData, $headers);
        $data = json_decode($res['body'], true);
        
        if (isset($data['access_token'])) {
            $access_token = $data['access_token'];
            saveSetting($conn, 'pathao_session', ['access_token' => $access_token, 'last_login' => time()]);
        }
    }
    
    if ($access_token) {
        $checkUrl = "https://merchant.pathao.com/api/v1/user/success";
        $headers = [
            "Content-Type: application/json",
            "Authorization: Bearer $access_token"
        ];
        $postData = json_encode(["phone" => $phone]);
        
        $res = make_curl_request($checkUrl, 'POST', $postData, $headers);
        $pData = json_decode($res['body'], true);
        
        if (isset($pData['data']['customer'])) {
            $cust = $pData['data']['customer'];
            $del = isset($cust['successful_delivery']) ? (int)$cust['successful_delivery'] : 0;
            $tot = isset($cust['total_delivery']) ? (int)$cust['total_delivery'] : 0;
            $can = $tot - $del;
            
            if ($tot > 0) {
                $history['delivered'] += $del;
                $history['cancelled'] += $can;
                $history['total'] += $tot;
                $history['breakdown'][] = ['courier' => 'Pathao', 'status' => "Delivered: $del | Cancelled: $can"];
            }
        }
    }
}

// Calculate Success Rate
$success_rate = 0;
if ($history['total'] > 0) {
    $success_rate = ($history['delivered'] / $history['total']) * 100;
}

// Save to Cache
$jsonData = $conn->real_escape_string(json_encode($history['breakdown']));
$sql = "INSERT INTO fraud_check_cache (phone, data, success_rate, total_orders) 
        VALUES ('$phone', '$jsonData', $success_rate, {$history['total']})
        ON DUPLICATE KEY UPDATE 
        data = '$jsonData', success_rate = $success_rate, total_orders = {$history['total']}, updated_at = NOW()";
$conn->query($sql);

echo json_encode([
    "source" => "live_global",
    "success_rate" => round($success_rate, 2),
    "total_orders" => $history['total'],
    "delivered" => $history['delivered'],
    "cancelled" => $history['cancelled'],
    "details" => $history['breakdown']
]);

$conn->close();
?>