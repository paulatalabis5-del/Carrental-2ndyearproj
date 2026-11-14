<?php
require_once('config/database.php');

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Add is_admin column if it doesn't exist
$sql = "ALTER TABLE users ADD COLUMN IF NOT EXISTS is_admin TINYINT(1) DEFAULT 0";

if(mysqli_query($conn, $sql)) {
    echo "Successfully added is_admin column to users table.<br>";
} else {
    echo "Error adding is_admin column: " . mysqli_error($conn) . "<br>";
}

// Add is_frozen column if it doesn't exist
$sql = "ALTER TABLE users ADD COLUMN IF NOT EXISTS is_frozen TINYINT(1) DEFAULT 0";
if(mysqli_query($conn, $sql)) {
    echo "Successfully added is_frozen column to users table.<br>";
} else {
    echo "Error adding is_frozen column: " . mysqli_error($conn) . "<br>";
}

// Add freeze_reason column if it doesn't exist
$sql = "ALTER TABLE users ADD COLUMN IF NOT EXISTS freeze_reason TEXT DEFAULT NULL";
if(mysqli_query($conn, $sql)) {
    echo "Successfully added freeze_reason column to users table.<br>";
} else {
    echo "Error adding freeze_reason column: " . mysqli_error($conn) . "<br>";
}

// Verify the columns were added
$check_columns = mysqli_query($conn, "SHOW COLUMNS FROM users");
$columns = array();
while($column = mysqli_fetch_assoc($check_columns)) {
    $columns[] = $column['Field'];
}

$required_columns = array('is_admin', 'is_frozen', 'freeze_reason');
foreach($required_columns as $column) {
    if(in_array($column, $columns)) {
        echo "Verified: {$column} column exists in users table.<br>";
    } else {
        echo "Error: {$column} column was not added successfully.<br>";
    }
}

// Also update the database.sql file to include all columns
$database_sql = file_get_contents('database.sql');
$users_table_pattern = "/CREATE TABLE IF NOT EXISTS users \(\s+id INT AUTO_INCREMENT PRIMARY KEY,\s+name VARCHAR\(100\) NOT NULL,\s+email VARCHAR\(100\) NOT NULL UNIQUE,\s+password VARCHAR\(255\) NOT NULL,\s+created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP\s+\);/";
$users_table_replacement = "CREATE TABLE IF NOT EXISTS users (\n    id INT AUTO_INCREMENT PRIMARY KEY,\n    name VARCHAR(100) NOT NULL,\n    email VARCHAR(100) NOT NULL UNIQUE,\n    password VARCHAR(255) NOT NULL,\n    is_admin TINYINT(1) DEFAULT 0,\n    is_frozen TINYINT(1) DEFAULT 0,\n    freeze_reason TEXT DEFAULT NULL,\n    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP\n);";

$updated_sql = preg_replace($users_table_pattern, $users_table_replacement, $database_sql);
file_put_contents('database.sql', $updated_sql);

echo "Database schema has been updated successfully.";
?> 