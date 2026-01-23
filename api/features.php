<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

include 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $result = $conn->query("SELECT * FROM feature_flags");
    $features = [];
    while ($row = $result->fetch_assoc()) {
        $features[$row['feature_key']] = (bool)$row['is_enabled'];
    }
    echo json_encode($features);
} 
elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    
    if (isset($data['key']) && isset($data['enabled'])) {
        $key = $conn->real_escape_string($data['key']);
        $enabled = $data['enabled'] ? 1 : 0;
        
        $sql = "INSERT INTO feature_flags (feature_key, is_enabled) VALUES ('$key', $enabled) 
                ON DUPLICATE KEY UPDATE is_enabled = $enabled";
                
        if ($conn->query($sql)) {
            echo json_encode(["success" => true]);
        } else {
            echo json_encode(["success" => false, "error" => $conn->error]);
        }
    } else {
        echo json_encode(["success" => false, "error" => "Invalid input"]);
    }
}
$conn->close();
?>