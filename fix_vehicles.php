<?php
require_once('config/database.php');

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Starting vehicles table fixes...\n\n";

// 1. Add image column if it doesn't exist
$sql = "ALTER TABLE vehicles 
        ADD COLUMN IF NOT EXISTS image VARCHAR(255) AFTER specifications";

if(mysqli_query($conn, $sql)) {
    echo "✓ Added image column to vehicles table\n";
} else {
    echo "✗ Error adding image column: " . mysqli_error($conn) . "\n";
}

// 2. Create uploads directory if it doesn't exist
$upload_dir = "uploads/vehicles";
if (!file_exists($upload_dir)) {
    if (mkdir($upload_dir, 0777, true)) {
        echo "✓ Created uploads/vehicles directory\n";
    } else {
        echo "✗ Error creating uploads directory\n";
    }
}

// 3. Update vehicles table to ensure all required columns exist
$sql = "ALTER TABLE vehicles 
        MODIFY COLUMN brand_id INT NOT NULL,
        MODIFY COLUMN model VARCHAR(255) NOT NULL,
        MODIFY COLUMN year INT NOT NULL,
        MODIFY COLUMN registration_number VARCHAR(50) NOT NULL UNIQUE,
        MODIFY COLUMN daily_rate DECIMAL(10,2) NOT NULL,
        MODIFY COLUMN status ENUM('available', 'booked', 'maintenance') DEFAULT 'available'";

if(mysqli_query($conn, $sql)) {
    echo "✓ Updated vehicles table structure\n";
} else {
    echo "✗ Error updating table structure: " . mysqli_error($conn) . "\n";
}

// 4. Add indexes for better performance
$sql = "ALTER TABLE vehicles 
        ADD INDEX idx_brand_id (brand_id),
        ADD INDEX idx_status (status),
        ADD INDEX idx_registration (registration_number)";

if(mysqli_query($conn, $sql)) {
    echo "✓ Added indexes to vehicles table\n";
} else {
    echo "✗ Error adding indexes: " . mysqli_error($conn) . "\n";
}

echo "\nAll fixes have been applied. Please check the messages above for any errors.\n";
?> 