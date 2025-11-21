<?php
error_reporting(E_ALL);
ini_set('display_errors',1);

// Database configuration
$host = 'localhost';
$dbname = 'zona_dental_care';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
// Start session


// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Redirect if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit();
    }
}

// Generate random ID
function generateId($prefix) {
    return $prefix . date('YmdHis') . rand(100, 999);
}
?>