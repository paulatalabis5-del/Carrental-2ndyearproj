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

// Get user information
$user_id = $_SESSION['user_id'];
$user_query = "SELECT * FROM users WHERE id = ?";
$stmt = mysqli_prepare($conn, $user_query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$user = mysqli_stmt_get_result($stmt)->fetch_assoc();

// Get vehicle ID from POST or URL
$vehicle_id = isset($_POST['car_id']) ? (int)$_POST['car_id'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);

// Fetch vehicle details
$query = "
    SELECT v.*, vb.name as brand_name 
    FROM vehicles v
    JOIN vehicle_brands vb ON v.brand_id = vb.id
    WHERE v.id = ?
";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $vehicle_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$vehicle = mysqli_fetch_assoc($result);

// If vehicle not found, redirect back
if(!$vehicle) {
    $_SESSION['error'] = "Vehicle not found.";
    header("Location: index.php");
    exit();
}

// Get prefill values from URL if present
$today = date('Y-m-d');
$tomorrow = date('Y-m-d', strtotime('+1 day'));
$prefill_pickup_date = isset($_POST['pickup_date']) ? $_POST['pickup_date'] : (isset($_GET['pickup_date']) ? $_GET['pickup_date'] : $today);
$prefill_pickup_time = isset($_POST['pickup_time']) ? $_POST['pickup_time'] : (isset($_GET['pickup_time']) ? $_GET['pickup_time'] : '08:00');
$prefill_return_date = isset($_POST['return_date']) ? $_POST['return_date'] : (isset($_GET['return_date']) ? $_GET['return_date'] : $tomorrow);
$prefill_return_time = isset($_POST['return_time']) ? $_POST['return_time'] : (isset($_GET['return_time']) ? $_GET['return_time'] : '09:00');
$payment_done = isset($_GET['payment_done']) && $_GET['payment_done'] == 1;

// After fetching $vehicle ...
$vehicle['daily_rate'] = isset($vehicle['daily_rate']) && is_numeric($vehicle['daily_rate']) ? (float)$vehicle['daily_rate'] : 0;

// Fetch pickup instructions
$instructions = [];
$inst_result = mysqli_query($conn, "SELECT * FROM pickup_instructions ORDER BY id ASC");
if ($inst_result) {
    $instructions = mysqli_fetch_all($inst_result, MYSQLI_ASSOC);
}

function create_booking($conn, $user_id, $vehicle_id, $pickup_date, $pickup_time, $return_date, $return_time, $pickup_province, $pickup_municipality, $pickup_barangay, $return_province, $return_municipality, $return_barangay, $vehicle) {
    // Always fetch the latest vehicle data
    $vehicle_query = "SELECT * FROM vehicles WHERE id = ? LIMIT 1 FOR UPDATE";
    $stmt = mysqli_prepare($conn, $vehicle_query);
    mysqli_stmt_bind_param($stmt, "i", $vehicle_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $vehicle = mysqli_fetch_assoc($result);
    
    if(!$vehicle || $vehicle['quantity'] <= 0 || $vehicle['status'] !== 'available') {
        return false;
    }

    // Calculate number of days and hours
    $start_datetime = new DateTime($pickup_date . ' ' . $pickup_time);
    $end_datetime = new DateTime($return_date . ' ' . $return_time);
    
    if($end_datetime <= $start_datetime) {
        return false;
    }
    
    $interval = $start_datetime->diff($end_datetime);
    $days = $interval->days;
    $hours = $interval->h;
    $total_cost = $vehicle['daily_rate'] * max(1, $days + ($hours > 0 ? 1 : 0));

    // Start transaction
    mysqli_begin_transaction($conn);
    
    try {
        // Add booking
        $booking_query = "INSERT INTO bookings (user_id, vehicle_id, start_date, start_time, end_date, end_time, pickup_province, pickup_municipality, pickup_barangay, return_province, return_municipality, return_barangay, total_amount, status) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $booking_query);
        $status = 'pending';
        mysqli_stmt_bind_param($stmt, "iissssssssssss", 
            $user_id,
            $vehicle_id,
            $pickup_date,
            $pickup_time,
            $return_date,
            $return_time,
            $pickup_province,
            $pickup_municipality,
            $pickup_barangay,
            $return_province,
            $return_municipality,
            $return_barangay,
            $total_cost,
            $status
        );
        mysqli_stmt_execute($stmt);
        
        // Deduct quantity
        $update_quantity = "UPDATE vehicles SET quantity = quantity - 1 WHERE id = ? AND quantity > 0";
        $stmt = mysqli_prepare($conn, $update_quantity);
        mysqli_stmt_bind_param($stmt, "i", $vehicle_id);
        mysqli_stmt_execute($stmt);
        
        // Update status if quantity becomes 0
        if($vehicle['quantity'] - 1 <= 0) {
            $update_status = "UPDATE vehicles SET status = 'booked' WHERE id = ?";
            $stmt = mysqli_prepare($conn, $update_status);
            mysqli_stmt_bind_param($stmt, "i", $vehicle_id);
            mysqli_stmt_execute($stmt);
        }
        
        mysqli_commit($conn);
        return mysqli_insert_id($conn);
    } catch (Exception $e) {
        mysqli_rollback($conn);
        return false;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Backend validation for rental duration limit (31 days)
    $pickup_date = $_POST['pickup_date'] ?? '';
    $pickup_time = $_POST['pickup_time'] ?? '';
    $return_date = $_POST['return_date'] ?? '';
    $return_time = $_POST['return_time'] ?? '';
    $pickup_province = $_POST['pickup_province'] ?? '';
    $pickup_municipality = $_POST['pickup_municipality'] ?? '';
    $pickup_barangay = $_POST['pickup_barangay'] ?? '';
    $return_province = $_POST['return_province'] ?? '';
    $return_municipality = $_POST['return_municipality'] ?? '';
    $return_barangay = $_POST['return_barangay'] ?? '';

    // Combine pickup location parts into one string
    $pickup_location = trim($pickup_province . ', ' . $pickup_municipality . ', ' . $pickup_barangay);

    // Combine return location parts into one string
    $return_location = trim($return_province . ', ' . $return_municipality . ', ' . $return_barangay);

    if ($pickup_date && $pickup_time && $return_date && $return_time) {
        $start_datetime = DateTime::createFromFormat('Y-m-d H:i', $pickup_date . ' ' . $pickup_time);
        $end_datetime = DateTime::createFromFormat('Y-m-d H:i', $return_date . ' ' . $return_time);

        if ($start_datetime && $end_datetime) {
            $interval = $start_datetime->diff($end_datetime);
            $diff_days = (int)$interval->format('%a');

            if ($diff_days > 31) {
                $error = 'You can only rent a vehicle for up to 31 days.';
            }
        }
    }

    echo '<pre style="background:#fffbe6;border:1px solid #ffb300;padding:10px;">';
    echo 'DEBUG: POST DATA:' . PHP_EOL;
    print_r($_POST);
    echo 'Pickup Location: ' . $pickup_location . PHP_EOL;
    echo 'Return Location: ' . $return_location . PHP_EOL;
    if (isset($error)) echo 'ERROR: ' . $error . PHP_EOL;
    echo '</pre>';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Vehicle - <?php echo htmlspecialchars($vehicle['brand_name'] . ' ' . $vehicle['model']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">

</head>
<body>
    <nav>
        <div class="nav__header">
            <a href="#" class="logo">
                <img src="image/logo.jpg" alt="QuickTrip Logo">
                <span>QuickTrip</span>
            </a>
            <div class="hamburger-menu">
                <span></span>
                <span></span>
                <span></span>
            </div>
        </div>
        <div class="nav__links">
            <a href="index.php"><i class="ri-home-line"></i> Home</a>
            <a href="my_bookings.php"><i class="ri-calendar-check-line"></i> My Bookings</a>
            <a href="profile.php"><i class="ri-user-line"></i> Profile</a>
            <a href="testimonials.php"><i class="ri-star-line"></i> Testimonials</a>
            <a href="contact.php"><i class="ri-customer-service-line"></i> Contact Us</a>
            <a href="subscribe.php"><i class="ri-mail-line"></i> Newsletter</a>
            <a href="logout.php" class="btn-logout"><i class="ri-logout-box-line"></i> Logout</a>
        </div>
    </nav>

    <div class="section__container book-vehicle-bg">
        <div class="row justify-content-center g-4">
            <div class="col-md-5">
                <div class="vehicle-card">
                    <div class="vehicle-image-container">
                        <?php if(!empty($vehicle['image'])): ?>
                            <img src="../<?php echo htmlspecialchars($vehicle['image']); ?>" 
                                 class="vehicle-image" 
                                 alt="<?php echo htmlspecialchars($vehicle['brand_name'] . ' ' . $vehicle['model']); ?>">
                        <?php else: ?>
                            <img src="../uploads/vehicles/default-car.jpg" 
                                 class="vehicle-image" 
                                 alt="Default vehicle image">
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <h5 class="vehicle-title">
                            <?php echo htmlspecialchars($vehicle['brand_name'] . ' ' . $vehicle['model']); ?>
                        </h5>
                        <ul class="vehicle-features">
                            <li>
                                <i class="ri-calendar-line"></i>
                                <span>Year: <?php echo $vehicle['year']; ?></span>
                            </li>
                            <li>
                                <i class="ri-fingerprint-line"></i>
                                <span>Reg: <?php echo htmlspecialchars($vehicle['registration_number']); ?></span>
                            </li>
                            <?php if(!empty($vehicle['specifications'])): ?>
                            <li>
                                <i class="ri-settings-3-line"></i>
                                <span><?php echo htmlspecialchars($vehicle['specifications']); ?></span>
                            </li>
                            <?php endif; ?>
                            <li>
                                <i class="ri-money-dollar-circle-line"></i>
                                <span class="vehicle-price">₱<?php echo number_format($vehicle['daily_rate'], 2); ?></span> per day
                            </li>
                        </ul>
                        
                        <!-- Pickup Instructions Section -->
                        <?php if (!empty($instructions)): ?>
                            <div class="mt-4">
                                <h6 class="fw-bold" style="color: #d35400;">What You Need and Know to Pick Up the Car</h6>
                                <div class="pickup-instructions">
                                    <?php foreach($instructions as $inst): ?>
                                        <div class="instruction-item mb-3 p-2 border-bottom">
                                            <div class="d-flex">
                                                <i class="<?php echo htmlspecialchars($inst['icon']); ?>" style="font-size:1.5rem;color:#D35400;min-width:30px;"></i>
                                                <div class="ms-2">
                                                    <strong><?php echo htmlspecialchars($inst['title']); ?></strong>
                                                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($inst['description'])); ?></p>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-7">
                <div class="booking-form-card">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h3 class="mb-0">Book This Vehicle</h3>
                        <button type="button" class="btn btn-secondary" onclick="window.history.back();" title="Back">
                            <i class="ri-arrow-left-line"></i>
                        </button>
                    </div>
                    
                    <?php if(isset($error)): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <form method="POST" action="" class="booking-form" onsubmit="return validateForm()">
                        <input type="hidden" name="car_id" value="<?php echo $vehicle_id; ?>">
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="pickup_date" class="form-label">Pickup Date</label>
                                <input type="date" class="form-control" id="pickup_date" name="pickup_date" 
                                       min="<?php echo date('Y-m-d'); ?>" required onchange="calculateTotal()" value="<?php echo htmlspecialchars($prefill_pickup_date); ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="pickup_time" class="form-label">Pickup Time</label>
        <input type="time" class="form-control" id="pickup_time" name="pickup_time" 
                                       min="08:00" max="17:00" value="<?php echo $prefill_pickup_time ? htmlspecialchars($prefill_pickup_time) : '08:00'; ?>" required onchange="calculateTotal()">
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="return_date" class="form-label">Return Date</label>
                                <input type="date" class="form-control" id="return_date" name="return_date" 
                                       min="<?php echo date('Y-m-d'); ?>" required onchange="calculateTotal()" value="<?php echo htmlspecialchars($prefill_return_date); ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="return_time" class="form-label">Return Time</label>
                        <input type="time" class="form-control" id="return_time" name="return_time" 
                                       min="08:00" max="17:00" value="<?php echo $prefill_return_time ? htmlspecialchars($prefill_return_time) : '17:00'; ?>" required onchange="calculateTotal()">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Pickup Location</label>
                            <div class="row g-2">
                                <div class="col">
                                    <select class="form-select" id="pickup_province" name="pickup_province" required>
                                        <option value="" disabled selected>Select Province</option>
                                        <option value="Laguna">Laguna</option>
                                    </select>
                                </div>
                                <div class="col">
                                    <select class="form-select" id="pickup_municipality" name="pickup_municipality" required>
                                        <option value="" disabled selected>Select Municipality/City</option>
                                        <option value="Santa Cruz">Santa Cruz</option>
                                    </select>
                                </div>
                                <div class="col">
                                    <select class="form-select" id="pickup_barangay" name="pickup_barangay" required>
                                        <option value="" disabled selected>Select Barangay</option>
                                        <option value="Alipit">Alipit</option>
                                        <option value="Bagumbayan">Bagumbayan</option>
                                        <option value="Bubukal">Bubukal</option>
                                        <option value="Calios">Calios</option>
                                        <option value="Duhat">Duhat</option>
                                        <option value="Gatid">Gatid</option>
                                        <option value="Jasaan">Jasaan</option>
                                        <option value="Labuin">Labuin</option>
                                        <option value="Malinao">Malinao</option>
                                        <option value="Oogong">Oogong</option>
                                        <option value="Pagsawitan">Pagsawitan</option>
                                        <option value="Palasan">Palasan</option>
                                        <option value="Patimbao">Patimbao</option>
                                        <option value="Poblacion I">Poblacion I</option>
                                        <option value="Poblacion II">Poblacion II</option>
                                        <option value="Poblacion III">Poblacion III</option>
                                        <option value="Poblacion IV">Poblacion IV</option>
                                        <option value="Poblacion V">Poblacion V</option>
                                        <option value="San Jose">San Jose</option>
                                        <option value="San Juan">San Juan</option>
                                        <option value="San Pablo Norte">San Pablo Norte</option>
                                        <option value="San Pablo Sur">San Pablo Sur</option>
                                        <option value="Santisima Cruz">Santisima Cruz</option>
                                        <option value="Santo Angel Central">Santo Angel Central</option>
                                        <option value="Santo Angel Norte">Santo Angel Norte</option>
                                        <option value="Santo Angel Sur">Santo Angel Sur</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Return Location</label>
                            <div class="row g-2">
                                <div class="col">
                                    <select class="form-select" id="return_province" name="return_province" required>
                                        <option value="" disabled selected>Select Province</option>
                                        <option value="Laguna">Laguna</option>
                                    </select>
                                </div>
                                <div class="col">
                                    <select class="form-select" id="return_municipality" name="return_municipality" required>
                                        <option value="" disabled selected>Select Municipality/City</option>
                                        <option value="Santa Cruz">Santa Cruz</option>
                                    </select>
                                </div>
                                <div class="col">
                                    <select class="form-select" id="return_barangay" name="return_barangay" required>
                                        <option value="" disabled selected>Select Barangay</option>
                                        <option value="Alipit">Alipit</option>
                                        <option value="Bagumbayan">Bagumbayan</option>
                                        <option value="Bubukal">Bubukal</option>
                                        <option value="Calios">Calios</option>
                                        <option value="Duhat">Duhat</option>
                                        <option value="Gatid">Gatid</option>
                                        <option value="Jasaan">Jasaan</option>
                                        <option value="Labuin">Labuin</option>
                                        <option value="Malinao">Malinao</option>
                                        <option value="Oogong">Oogong</option>
                                        <option value="Pagsawitan">Pagsawitan</option>
                                        <option value="Palasan">Palasan</option>
                                        <option value="Patimbao">Patimbao</option>
                                        <option value="Poblacion I">Poblacion I</option>
                                        <option value="Poblacion II">Poblacion II</option>
                                        <option value="Poblacion III">Poblacion III</option>
                                        <option value="Poblacion IV">Poblacion IV</option>
                                        <option value="Poblacion V">Poblacion V</option>
                                        <option value="San Jose">San Jose</option>
                                        <option value="San Juan">San Juan</option>
                                        <option value="San Pablo Norte">San Pablo Norte</option>
                                        <option value="San Pablo Sur">San Pablo Sur</option>
                                        <option value="Santisima Cruz">Santisima Cruz</option>
                                        <option value="Santo Angel Central">Santo Angel Central</option>
                                        <option value="Santo Angel Norte">Santo Angel Norte</option>
                                        <option value="Santo Angel Sur">Santo Angel Sur</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Payment Summary -->
                        <div class="card mb-3" id="paymentSummaryCard">
                            <div class="card-header bg-primary text-white">
                                <strong>Payment Summary</strong>
                            </div>
                            <div class="card-body">
                                <div class="row mb-2">
                                    <div class="col-7">Rental Duration</div>
                                    <div class="col-5 text-end" id="summaryDuration">0 day(s)</div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-7">Price per day</div>
                                    <div class="col-5 text-end">₱<span id="summaryPricePerDay">0.00</span></div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-7">Reservation Fee</div>
                                    <div class="col-5 text-end" id="summaryReservationFee">₱0.00 (0 x ₱200)</div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-7">SUB-TOTAL</div>
                                    <div class="col-5 text-end">₱<span id="summarySubTotal">0.00</span></div>
                                </div>
                                <div class="row mb-2 fw-bold">
                                    <div class="col-7">Required Deposit</div>
                                    <div class="col-5 text-end text-danger" id="summaryDeposit">₱0.00</div>
                                </div>
                                <div class="row mb-2 fw-bold">
                                    <div class="col-7">TOTAL PRICE</div>
                                    <div class="col-5 text-end">₱<span id="summaryTotal">0.00</span></div>
                                </div>
                            </div>
                        </div>

                        <div class="alert alert-info mt-3">
                            <h5 class="alert-heading">Important Information:</h5>
                            <ul class="mb-0">
                                <li>Daily Rate: ₱<?php echo number_format($vehicle['daily_rate'], 2); ?></li>
                                <li>Late Return Penalty: ₱500 per day</li>
                                <li>Reservation Fee: ₱1,000.00 (deductible from total amount)</li>
                                <li>Operating Hours: 8:00 AM - 8:00 PM</li>
                                <li>Terms and Condition must read and agree to confirm your booking</li>
                            </ul>
                        </div>
                        
                        <div class="form-check mb-3">
                          <input class="form-check-input" type="checkbox" value="" id="agreeCheck">
                          <label class="form-check-label" for="agreeCheck">
                            I Agree to the <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">Terms & Condition</a>
                          </label>
                        </div>
                        
                        <?php if (!$payment_done): ?>
                        <button type="button" class="btn btn-warning w-100 mb-2" id="proceedPaymentBtn">
                            <i class="ri-wallet-3-line"></i> Proceed to Payment
                        </button>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const hamburgerMenu = document.querySelector('.hamburger-menu');
        const navLinks = document.querySelector('.nav__links');

        hamburgerMenu.addEventListener('click', () => {
            hamburgerMenu.classList.toggle('active');
            navLinks.classList.toggle('active');
        });

        // Close menu when clicking outside
        document.addEventListener('click', (e) => {
            if (!hamburgerMenu.contains(e.target) && !navLinks.contains(e.target)) {
                hamburgerMenu.classList.remove('active');
                navLinks.classList.remove('active');
            }
        });

        function calculateTotal() {
            var pickupDate = document.getElementById('pickup_date').value;
            var pickupTime = document.getElementById('pickup_time').value;
            var returnDate = document.getElementById('return_date').value;
            var returnTime = document.getElementById('return_time').value;
            var dailyRate = Number(<?php echo json_encode($vehicle['daily_rate']); ?>) || 0;

            if (pickupDate && pickupTime && returnDate && returnTime) {
                var start = new Date(pickupDate + 'T' + pickupTime);
                var end = new Date(returnDate + 'T' + returnTime);

                var diffMs = end - start;
                var diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));
                var diffHours = Math.floor((diffMs % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));

                // Minimum 1 day charge
                var rentalDays = diffDays + (diffHours > 0 ? 1 : 0);
                if (rentalDays < 1) rentalDays = 0;

                var rentalFee = rentalDays * dailyRate;
                var reservationFee = rentalDays * 200;
                var subTotal = rentalFee;
                var total = rentalFee + reservationFee;

                document.getElementById('summaryDuration').textContent = rentalDays + ' day(s)';
                document.getElementById('summaryPricePerDay').textContent = dailyRate.toFixed(2);
                document.getElementById('summaryReservationFee').textContent = '₱' + reservationFee.toFixed(2) + ' (' + rentalDays + ' x ₱200)';
                document.getElementById('summarySubTotal').textContent = subTotal.toFixed(2);
                document.getElementById('summaryDeposit').textContent = '₱' + reservationFee.toFixed(2);
                document.getElementById('summaryTotal').textContent = total.toFixed(2);
            } else {
                document.getElementById('summaryDuration').textContent = '0 day(s)';
                document.getElementById('summaryPricePerDay').textContent = '0.00';
                document.getElementById('summaryReservationFee').textContent = '₱0.00 (0 x ₱200)';
                document.getElementById('summarySubTotal').textContent = '0.00';
                document.getElementById('summaryDeposit').textContent = '₱0.00';
                document.getElementById('summaryTotal').textContent = '0.00';
            }
        }

        function validateForm() {
            const pickupDate = new Date(document.getElementById('pickup_date').value);
            const pickupTime = document.getElementById('pickup_time').value;
            const returnDate = new Date(document.getElementById('return_date').value);
            const returnTime = document.getElementById('return_time').value;
            
            const startDateTime = new Date(pickupDate.toDateString() + ' ' + pickupTime);
            const endDateTime = new Date(returnDate.toDateString() + ' ' + returnTime);
            
            if(endDateTime <= startDateTime) {
                alert('Return date and time must be after pickup date and time');
                return false;
            }
            
            // Check if the gap is at least 24 hours
            const diffMs = endDateTime - startDateTime;
            if(diffMs < 24 * 60 * 60 * 1000) {
                alert('The minimum gap between pickup and return must be 24 hours.');
                return false;
            }

            // Check if the rental duration exceeds 31 days
            const maxDays = 31;
            const diffDays = diffMs / (1000 * 60 * 60 * 24);
            if(diffDays > maxDays) {
                alert('You can only rent a vehicle for up to 31 days.');
                return false;
            }
            
            // Check if times are within operating hours (8am to 5pm)
            const pickupHour = parseInt(pickupTime.split(':')[0]);
            const returnHour = parseInt(returnTime.split(':')[0]);
            
            if(pickupHour < 8 || pickupHour >= 17) {
                alert('Pickup time must be between 8:00 AM and 5:00 PM');
                return false;
            }
            
            if(returnHour < 8 || returnHour >= 17) {
                alert('Return time must be between 8:00 AM and 5:00 PM');
                return false;
            }
            
            return true;
        }
        // Initial calculation
        calculateTotal();

        window.addEventListener('DOMContentLoaded', function() {
            calculateTotal();
            var proceedBtn = document.getElementById('proceedPaymentBtn');
            if (proceedBtn) {
                proceedBtn.addEventListener('click', function() {
                    var agreeCheck = document.getElementById('agreeCheck');
                    if (!agreeCheck.checked) {
                        alert('Please agree to terms and conditions before proceeding to payment.');
                        return;
                    }
                    // Validate booking dates and times before proceeding
                    if (!validateForm()) {
                        return;
                    }
                    var pickup_date = document.getElementById('pickup_date').value;
                    var pickup_time = document.getElementById('pickup_time').value;
                    var return_date = document.getElementById('return_date').value;
                    var return_time = document.getElementById('return_time').value;
                    var car_id = <?php echo $vehicle_id; ?>;
                    var payment_done = false;
                    var payment_url = 'payment.php?pickup_date=' + pickup_date + '&pickup_time=' + pickup_time + '&return_date=' + return_date + '&return_time=' + return_time + '&car_id=' + car_id + '&payment_done=' + payment_done;
                    window.location.href = payment_url;
                });
            }
        });
    </script>

    <!-- Terms & Condition Modal -->
    <div class="modal fade" id="termsModal" tabindex="-1" aria-labelledby="termsModalLabel" aria-hidden="true">
      <div class="modal-dialog" style="max-width:700px;">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="termsModalLabel">Terms & Condition</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body" style="white-space: pre-line;">
<span style="color:#D35400;font-weight:bold;"><i class="ri-error-warning-line" style="font-size:1.2em;vertical-align:middle;"></i> All bookings are final and cannot be rescheduled. Please make sure your chosen date is correct before confirming.</span>

<span style="color:#D35400;font-weight:bold;"><i class="ri-bank-card-line" style="font-size:1.2em;vertical-align:middle;"></i> Accepted Payment Methods</span>
We accept payments via GCash QR Code or Walk-In (Cash or GCash).

<span style="color:#D35400;font-weight:bold;"><i class="ri-cash-line" style="font-size:1.2em;vertical-align:middle;"></i> Reservation Fee</span>
Reservation Fee: ₱200 per day booked. The total reservation fee is calculated based on the number of days you book. This fee is required within 24 hours of booking. If not paid within 24 hours, your booking will be automatically cancelled.

<span style="color:#D35400;font-weight:bold;"><i class="ri-refund-2-line" style="font-size:1.2em;vertical-align:middle;"></i> Refund Policy</span>
Cancelled within 24 hours of payment: Full refund.
Cancelled after 24 hours of payment: No refund.
No-show on rental day: No refund.

<span style="color:#D35400;font-weight:bold;"><i class="ri-list-check-2" style="font-size:1.2em;vertical-align:middle;"></i> Full Payment Options</span>
Option 1: Pay full amount at pickup. If returned early, unused days are refunded minus used days (charged at full daily rate).
Option 2: Pay 50% at pickup and 50% upon return.

<span style="color:#D35400;font-weight:bold;"><i class="ri-shield-cross-line" style="font-size:1.2em;vertical-align:middle;"></i> Penalties & Legal Conditions</span>

<span style="color:#D35400;font-weight:bold;"><i class="ri-tools-line" style="font-size:1.2em;vertical-align:middle;"></i> Damage to Vehicle</span>
If the car is damaged, the renter will pay a penalty.
Penalty is based on repair cost and number of days the vehicle is out of service.
A damage report will be sent via email.

<span style="color:#D35400;font-weight:bold;"><i class="ri-lock-line" style="font-size:1.2em;vertical-align:middle;"></i> Unpaid Penalties</span>
If the penalty is not paid within 3 days, your account will be frozen, and if the penalty is not paid within 7 days, your account may be permanently deleted and legal action will be taken.

<span style="color:#D35400;font-weight:bold;"><i class="ri-time-line" style="font-size:1.2em;vertical-align:middle;"></i> Late Return</span>
Late return penalty is ₱500 per day (for up to 24 hours).
After 24 hours, legal action will be considered.
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          </div>
        </div>
      </div>
    </div>
</body>
</html>
