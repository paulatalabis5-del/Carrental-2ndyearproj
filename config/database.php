<?php
$host = "localhost"; // Change back to localhost
$port = 3310;  // Custom MySQL port
$dbname = "car_rental";
$username = "root";
$password = "";

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Simple connection
$conn = mysqli_connect($host, $username, $password, $dbname, $port);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error() . " (" . mysqli_connect_errno() . ")");
}

// Set charset to utf8
if (!mysqli_set_charset($conn, "utf8")) {
    die("Error loading character set utf8: " . mysqli_error($conn));
}

// Make connection global
global $conn;

// Create subscribers table if it doesn't exist
$sql = "CREATE TABLE IF NOT EXISTS subscribers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    email VARCHAR(255) NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_email (email),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
)";

if (!mysqli_query($conn, $sql)) {
    die("Error creating subscribers table: " . mysqli_error($conn));
}

// Update vehicles table to add specifications column if it doesn't exist
$check_column = "SHOW COLUMNS FROM vehicles LIKE 'specifications'";
$result = mysqli_query($conn, $check_column);

if (mysqli_num_rows($result) == 0) {
    $alter_table = "ALTER TABLE vehicles ADD COLUMN specifications TEXT AFTER daily_rate";
    if (!mysqli_query($conn, $alter_table)) {
        die("Error adding specifications column: " . mysqli_error($conn));
    }
}

// Add quantity column if it doesn't exist
$check_quantity = "SHOW COLUMNS FROM vehicles LIKE 'quantity'";
$result = mysqli_query($conn, $check_quantity);

if (mysqli_num_rows($result) == 0) {
    $alter_table = "ALTER TABLE vehicles ADD COLUMN quantity INT DEFAULT 1 AFTER specifications";
    if (!mysqli_query($conn, $alter_table)) {
        die("Error adding quantity column: " . mysqli_error($conn));
    }
}

// Create vehicles table if it doesn't exist
$sql = "CREATE TABLE IF NOT EXISTS vehicles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    brand_id INT NOT NULL,
    model VARCHAR(255) NOT NULL,
    year INT NOT NULL,
    registration_number VARCHAR(50) NOT NULL UNIQUE,
    daily_rate DECIMAL(10,2) NOT NULL,
    specifications TEXT,
    image VARCHAR(255),
    status ENUM('available', 'rented', 'maintenance') DEFAULT 'available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (brand_id) REFERENCES vehicle_brands(id)
)";

if (!mysqli_query($conn, $sql)) {
    die("Error creating vehicles table: " . mysqli_error($conn));
}
?> 