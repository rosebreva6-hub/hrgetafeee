<?php
// Database Configuration File
// HRGetafe System

// Database Credentials
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'hrgetafee');

// Create Connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check Connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set Charset to UTF-8
$conn->set_charset("utf8");

// Session Configuration
define('SESSION_TIMEOUT', 3600); // 1 hour in seconds

// Base URL
define('BASE_URL', 'http://localhost/hr_system/');

// For Development - Error Reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>