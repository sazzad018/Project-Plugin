<?php
ob_start(); // Start buffering to catch any stray whitespaces/errors

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

// Disable error display to prevent breaking JSON
ini_set('display_errors', 0);
error_reporting(0);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    ob_end_clean();
    http_response_code(200);
    exit;
}

include 'db.php';

ob_clean(); // Clean buffer before outputting JSON

$input = json_decode(file_get_contents("php://input"), true);
$key = isset($input['license_key']) ? $conn->real_escape_string($input['license_key']) : '';
$domain = isset($input['domain']) ? $conn->real_escape_string($input['domain']) : '';

if (empty($key)) {
    echo json_encode(["valid" => false, "message" => "License key missing"]);
    exit;
}

// Optional: Validate Domain matching (Currently strict check disabled for ease of use)
// $sql = "SELECT * FROM licenses WHERE license_key = '$key' AND domain_name = '$domain'";
$sql = "SELECT * FROM licenses WHERE license_key = '$key'";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    
    if ($row['status'] === 'active') {
        echo json_encode(["valid" => true, "message" => "License Active"]);
    } else {
        echo json_encode(["valid" => false, "message" => "License is inactive"]);
    }
} else {
    echo json_encode(["valid" => false, "message" => "Invalid License Key"]);
}

$conn->close();
?>