<?php
require_once('config/database.php');

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Starting project fixes...\n\n";

// 1. Fix users table
$sql = "ALTER TABLE users 
        ADD COLUMN IF NOT EXISTS is_admin TINYINT(1) DEFAULT 0,
        MODIFY COLUMN password VARCHAR(255) NOT NULL,
        ADD INDEX idx_email (email)";

if(mysqli_query($conn, $sql)) {
    echo "✓ Users table updated successfully\n";
} else {
    echo "✗ Error updating users table: " . mysqli_error($conn) . "\n";
}

// 2. Fix vehicles table status enum
$sql = "ALTER TABLE vehicles 
        MODIFY COLUMN status ENUM('available', 'booked', 'maintenance') DEFAULT 'available',
        ADD INDEX idx_status (status),
        ADD INDEX idx_brand (brand_id)";

if(mysqli_query($conn, $sql)) {
    echo "✓ Vehicles table updated successfully\n";
} else {
    echo "✗ Error updating vehicles table: " . mysqli_error($conn) . "\n";
}

// 3. Fix bookings table
$sql = "ALTER TABLE bookings 
        ADD INDEX idx_dates (start_date, end_date),
        ADD INDEX idx_user (user_id),
        ADD INDEX idx_vehicle (vehicle_id),
        ADD INDEX idx_status (status)";

if(mysqli_query($conn, $sql)) {
    echo "✓ Bookings table updated successfully\n";
} else {
    echo "✗ Error updating bookings table: " . mysqli_error($conn) . "\n";
}

// 4. Create contact_queries table if not exists
$sql = "CREATE TABLE IF NOT EXISTS contact_queries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    admin_reply TEXT,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
)";

if(mysqli_query($conn, $sql)) {
    echo "✓ Contact queries table created/verified successfully\n";
} else {
    echo "✗ Error with contact queries table: " . mysqli_error($conn) . "\n";
}

// 5. Create vehicle_images table if not exists
$sql = "CREATE TABLE IF NOT EXISTS vehicle_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vehicle_id INT NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    is_primary TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE
)";

if(mysqli_query($conn, $sql)) {
    echo "✓ Vehicle images table created/verified successfully\n";
} else {
    echo "✗ Error with vehicle images table: " . mysqli_error($conn) . "\n";
}

// 6. Create uploads directory if it doesn't exist
if (!file_exists('uploads')) {
    if (mkdir('uploads', 0777, true)) {
        echo "✓ Uploads directory created successfully\n";
    } else {
        echo "✗ Error creating uploads directory\n";
    }
}

// 7. Create vehicle images directory if it doesn't exist
if (!file_exists('uploads/vehicles')) {
    if (mkdir('uploads/vehicles', 0777, true)) {
        echo "✓ Vehicle images directory created successfully\n";
    } else {
        echo "✗ Error creating vehicle images directory\n";
    }
}

echo "\nAll fixes have been applied. Please check the messages above for any errors.\n";
echo "Note: The database connection is still using port 3310 as per your XAMPP configuration.\n";
?> 