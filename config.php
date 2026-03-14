<?php
// ============================================================
//  Database Configuration
// ============================================================
define('DB_HOST', 'localhost');
define('DB_USER', 'nepdine');       // Change to your MySQL username
define('DB_PASS', 'strong-password');           // Change to your MySQL password
define('DB_NAME', 'nepdine_db');

// eSewa sandbox credentials (replace with live values in production)
define('ESEWA_MERCHANT_CODE', 'EPAYTEST');
define('ESEWA_PAYMENT_URL', 'https://uat.esewa.com.np/epay/main');
define('ESEWA_VERIFY_URL',  'https://uat.esewa.com.np/epay/transrec');

// Khalti credentials (set your real public key for production)
define('KHALTI_PUBLIC_KEY', 'test_public_key_xxxxx');

function getDB(): mysqli {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        die(json_encode(['error' => 'Database connection failed: ' . $conn->connect_error]));
    }
    $conn->set_charset('utf8mb4');
    return $conn;
}

// Start session helper
function requireLogin(): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}
?>
