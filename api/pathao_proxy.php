<?php
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['code' => 400, 'message' => 'Invalid Request Body']);
    exit;
}

$config = $input['config'];
$endpoint = $input['endpoint'];
$method = $input['method'] ?? 'GET';
$data = $input['data'] ?? null;

$baseUrl = $config['isSandbox'] ? 'https://api-hermes.pathao.com' : 'https://api-hermes.pathao.com'; // Pathao uses same base for both usually, check their latest dashboard if changed

// ১. টোকেন ম্যানেজমেন্ট (Caching Token)
$tokenFile = 'pathao_token.json';
$accessToken = null;

if (file_exists($tokenFile)) {
    $tokenData = json_decode(file_get_contents($tokenFile), true);
    if ($tokenData && $tokenData['expires_at'] > time()) {
        $accessToken = $tokenData['access_token'];
    }
}

if (!$accessToken) {
    // নতুন টোকেন ইস্যু করা
    $authUrl = $baseUrl . '/aladdin/api/v1/issue-token';
    $authBody = [
        'client_id' => $config['clientId'],
        'client_secret' => $config['clientSecret'],
        'username' => $config['username'],
        'password' => $config['password'],
        'grant_type' => 'password'
    ];

    $ch = curl_init($authUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($authBody));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
    
    $authResponse = curl_exec($ch);
    $authData = json_decode($authResponse, true);
    curl_close($ch);

    if (isset($authData['access_token'])) {
        $accessToken = $authData['access_token'];
        file_put_contents($tokenFile, json_encode([
            'access_token' => $accessToken,
            'expires_at' => time() + ($authData['expires_in'] - 60) // ১ মিনিট আগে বাফার রাখা
        ]));
    } else {
        echo json_encode(['code' => 401, 'message' => 'Pathao Authentication Failed', 'details' => $authData]);
        exit;
    }
}

// ২. মেইন এপিআই কল করা
$apiUrl = $baseUrl . '/' . ltrim($endpoint, '/');
$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

$headers = [
    'Authorization: Bearer ' . $accessToken,
    'Content-Type: application/json',
    'Accept: application/json'
];

if ($data) {
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
}

curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo $response;