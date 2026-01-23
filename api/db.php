
<?php
// Database Configuration
$servername = "localhost";
$username = "root";      // আপনার ডাটাবেস ইউজারনেম দিন
$password = "";          // আপনার ডাটাবেস পাসওয়ার্ড দিন
$dbname = "ecommerce_db"; // আপনার ডাটাবেসের নাম দিন

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die(json_encode(["error" => "Connection failed: " . $conn->connect_error]));
}
