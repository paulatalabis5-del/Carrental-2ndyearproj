<?php
session_name('admin_session');
session_start();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
header('Pragma: no-cache');
require_once('../config/database.php');

// Check if user is logged in
if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get booking form data from URL
$car_id = isset($_GET['car_id']) ? (int)$_GET['car_id'] : 0;
$pickup_date = isset($_GET['pickup_date']) ? $_GET['pickup_date'] : '';
$pickup_time = isset($_GET['pickup_time']) ? $_GET['pickup_time'] : '';
$return_date = isset($_GET['return_date']) ? $_GET['return_date'] : '';
$return_time = isset($_GET['return_time']) ? $_GET['return_time'] : '';

$success = '';
$error = '';

// Fetch vehicle details for summary
$vehicle = null;
if ($car_id) {
    $query = "SELECT v.*, vb.name as brand_name FROM vehicles v JOIN vehicle_brands vb ON v.brand_id = vb.id WHERE v.id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $car_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $vehicle = mysqli_fetch_assoc($result);
}

// Calculate payment summary
$rental_days = 0;
$daily_rate = $vehicle && isset($vehicle['daily_rate']) ? (float)$vehicle['daily_rate'] : 0;
$rental_fee = 0;
$reservation_fee = 0;
$total_amount = 0;
$required_deposit = 0;
$balance_after_deposit = 0;
$summary_error = '';
if ($pickup_date && $pickup_time && $return_date && $return_time && $vehicle) {
    $start = strtotime($pickup_date . ' ' . $pickup_time);
    $end = strtotime($return_date . ' ' . $return_time);
    if ($end > $start) {
        $diff = $end - $start;
        $days = floor($diff / (60 * 60 * 24));
        $hours = floor(($diff % (60 * 60 * 24)) / (60 * 60));
        $rental_days = $days + ($hours > 0 ? 1 : 0);
        if ($rental_days < 1) $rental_days = 1;
        $rental_fee = $rental_days * $daily_rate;
        $reservation_fee = $rental_days * 200;
        $total_amount = $rental_fee + $reservation_fee;
        $required_deposit = $reservation_fee;
        $balance_after_deposit = $total_amount - $required_deposit;
    } else {
        $summary_error = 'Return date/time must be after pickup date/time.';
    }
}

// Copy create_booking function from book_vehicle.php
function create_booking($conn, $user_id, $vehicle_id, $pickup_date, $pickup_time, $return_date, $return_time, $vehicle) {
    $vehicle_query = "SELECT * FROM vehicles WHERE id = ? LIMIT 1 FOR UPDATE";
    $stmt = mysqli_prepare($conn, $vehicle_query);
    mysqli_stmt_bind_param($stmt, "i", $vehicle_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $vehicle = mysqli_fetch_assoc($result);
    if(!$vehicle || $vehicle['quantity'] <= 0 || $vehicle['status'] !== 'available') {
        return false;
    }
    $start_datetime = new DateTime($pickup_date . ' ' . $pickup_time);
    $end_datetime = new DateTime($return_date . ' ' . $return_time);
    if($end_datetime <= $start_datetime) {
        return false;
    }
    $interval = $start_datetime->diff($end_datetime);
    $days = $interval->days;
    $hours = $interval->h;
    $total_cost = $vehicle['daily_rate'] * max(1, $days + ($hours > 0 ? 1 : 0));
    mysqli_begin_transaction($conn);
    try {
        $booking_query = "INSERT INTO bookings (user_id, vehicle_id, start_date, start_time, end_date, end_time, total_amount, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $booking_query);
        $status = 'pending';
        mysqli_stmt_bind_param($stmt, "iissssss",
            $_SESSION['user_id'],
            $vehicle_id,
            $pickup_date,
            $pickup_time,
            $return_date,
            $return_time,
            $total_cost,
            $status
        );
        mysqli_stmt_execute($stmt);
        $booking_id = mysqli_insert_id($conn);
        $update_quantity = "UPDATE vehicles SET quantity = quantity - 1 WHERE id = ? AND quantity > 0";
        $stmt = mysqli_prepare($conn, $update_quantity);
        mysqli_stmt_bind_param($stmt, "i", $vehicle_id);
        mysqli_stmt_execute($stmt);
        if($vehicle['quantity'] - 1 <= 0) {
            $update_status = "UPDATE vehicles SET status = 'booked' WHERE id = ?";
            $stmt = mysqli_prepare($conn, $update_status);
            mysqli_stmt_bind_param($stmt, "i", $vehicle_id);
            mysqli_stmt_execute($stmt);
        }
        mysqli_commit($conn);
        return $booking_id;
    } catch (Exception $e) {
        mysqli_rollback($conn);
        return false;
    }
}

if(isset($_POST['submit_payment'])) {
    $sender_name = trim($_POST['sender_name']);
    $reference_number = trim($_POST['reference_number']);
    $amount = (float)$_POST['amount'];
    $image_path = '';

    // Handle image upload
    if(isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png'];
        $filename = $_FILES['image']['name'];
        $file_ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if(!in_array($file_ext, $allowed)) {
            $error = "Only JPG, JPEG, PNG files are allowed.";
        } else {
            $upload_dir = '../uploads/payments/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $new_filename = uniqid('pay_') . '.' . $file_ext;
            $destination = $upload_dir . $new_filename;
            if(move_uploaded_file($_FILES['image']['tmp_name'], $destination)) {
                $image_path = 'uploads/payments/' . $new_filename;
            } else {
                $error = "Failed to upload image.";
            }
        }
    } else {
        $error = "Please upload a payment screenshot (JPEG/PNG).";
    }

    // Create booking if not already created
    $booking_id = null;
    if(!$error) {
        $booking_id = create_booking($conn, $_SESSION['user_id'], $car_id, $pickup_date, $pickup_time, $return_date, $return_time, $vehicle);
        if(!$booking_id) {
            $error = "Failed to create booking. Please check vehicle availability and booking details.";
        }
    }

    if(!$error && $booking_id) {
        // Insert payment into the database
        $payment_query = "INSERT INTO payments (booking_id, sender_name, reference_number, amount, image, status) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $payment_query);
        $status = 'pending';
        mysqli_stmt_bind_param($stmt, "issdss", $booking_id, $sender_name, $reference_number, $amount, $image_path, $status);
        if (mysqli_stmt_execute($stmt)) {
            $success = "Payment submitted successfully!";
            // Set session variables for alert and receipt
            $_SESSION['payment_success'] = true;
            $_SESSION['recent_booking_id'] = $booking_id;
            header("Location: my_bookings.php");
            exit();
        } else {
            $error = "Failed to submit payment.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment - QuickTrip Car Rental</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <link rel="js" href="main.js">
</head>
<body>
    <div class="payment-container">
        <h2 class="mb-4 text-center">Pay for Your Booking</h2>
        <button type="button" class="btn btn-secondary mb-3" onclick="window.history.back();">Back</button>
        <?php if($success): ?>
            <div class="alert alert-success text-center"><?php echo $success; ?></div>
        <?php elseif($error): ?>
            <div class="alert alert-danger text-center"><?php echo $error; ?></div>
        <?php endif; ?>
        <div class="row justify-content-center">
            <div class="col-md-5 mb-4">
                <div class="card" id="paymentSummaryCard">
                    <div class="card-header bg-primary text-white">
                        <strong>Payment Summary</strong>
                    </div>
                    <div class="card-body">
                        <?php if($vehicle): ?>
                        <div class="mb-2"><strong>Vehicle:</strong> <?php echo htmlspecialchars($vehicle['brand_name'] . ' ' . $vehicle['model']); ?></div>
                        <?php endif; ?>
                        <div class="mb-2"><strong>Rental Duration:</strong> <?php echo $rental_days; ?> day(s)</div>
                        <div class="mb-2"><strong>Daily Rate:</strong> ₱<?php echo number_format($daily_rate, 2); ?></div>
                        <div class="mb-2"><strong>Rental Fee:</strong> ₱<?php echo number_format($rental_fee, 2); ?></div>
                        <div class="mb-2"><strong>Reservation Fee:</strong> ₱<?php echo number_format($reservation_fee, 2); ?> (<?php echo $rental_days; ?> x ₱200)</div>
                        <div class="mb-2 fw-bold"><strong>Total Amount:</strong> ₱<span id="summaryTotalAmount"><?php echo number_format($total_amount, 2); ?></span></div>
                        <div class="mb-2"><strong>Required Deposit:</strong> ₱<?php echo number_format($required_deposit, 2); ?></div>
                        <hr>
                        <div class="mb-2"><strong>Cash Paid:</strong> ₱<span id="cashPaid">0.00</span></div>
                        <div class="mb-2"><strong>Balance Left:</strong> ₱<span id="balanceLeft">0.00</span></div>
                        <?php if($summary_error): ?>
                            <div class="alert alert-danger mt-2"><?php echo $summary_error; ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-7">
                <div class="qr-section">
                    <div class="qr-img">
                        <a href="qr.jpg" target="_blank">
                            <img src="qr.jpg" style="width: 300px; height: 300px;">
                        </a>
                    </div>
                    <form class="payment-form" method="POST" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label class="form-label">Sender Name</label>
                            <input type="text" class="form-control" name="sender_name" required minlength="1" maxlength="50" pattern=".{1,50}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Reference Number</label>
                            <input type="text" class="form-control" name="reference_number" required minlength="15" maxlength="15" pattern=".{15,15}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Amount (₱)</label>
                            <input type="number" class="form-control" name="amount" id="amountInput" min="<?php echo $required_deposit; ?>" max="<?php echo $total_amount; ?>" step="0.01" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Upload Payment Screenshot (JPEG/PNG)</label>
                            <input type="file" class="form-control" name="image" accept="image/jpeg,image/png" required>
                        </div>
                        <button type="submit" name="submit_payment" class="btn btn-primary w-100">Checkout</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="mt-4 text-center text-muted">
        </div>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var amountInput = document.getElementById('amountInput');
        var cashPaid = document.getElementById('cashPaid');
        var balanceLeft = document.getElementById('balanceLeft');
        var totalAmount = parseFloat(document.getElementById('summaryTotalAmount').textContent.replace(/,/g, ''));
        // Set initial balance left to 0.00
        balanceLeft.textContent = '0.00';
        amountInput.addEventListener('input', function() {
            var paid = parseFloat(amountInput.value) || 0;
            cashPaid.textContent = paid.toFixed(2);
            var left = paid > 0 ? (totalAmount - paid) : 0;
            balanceLeft.textContent = left.toFixed(2);
        });
    });
    </script>
</body>
</html> 