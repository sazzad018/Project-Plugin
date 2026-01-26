<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

include 'db.php';

$input = json_decode(file_get_contents("php://input"), true);
$key = isset($input['license_key']) ? $conn->real_escape_string($input['license_key']) : '';
$domain = isset($input['domain']) ? $conn->real_escape_string($input['domain']) : '';

if (empty($key)) {
    echo json_encode(["valid" => false, "message" => "License key missing"]);
    exit;
}

$sql = "SELECT * FROM licenses WHERE license_key = '$key'";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    
    if ($row['status'] === 'active') {
        // Optional: Update domain if not set or verify match
        // For flexibility, we just check key and status in this version
        echo json_encode(["valid" => true, "message" => "License Active"]);
    } else {
        echo json_encode(["valid" => false, "message" => "License is inactive"]);
    }
} else {
    echo json_encode(["valid" => false, "message" => "Invalid License Key"]);
}

$conn->close();
?>