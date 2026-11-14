<?php
session_name('quicktrip_session');
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

$user_id = $_SESSION['user_id'];

// Get user information
$user_query = "SELECT * FROM users WHERE id = ?";
$stmt = mysqli_prepare($conn, $user_query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

// Fetch available vehicles with their brand names
$query = "
    SELECT v.*, vb.name as brand_name
    FROM vehicles v
    JOIN vehicle_brands vb ON v.brand_id = vb.id
    WHERE v.status = 'available'
    ORDER BY v.created_at DESC
";
$result = mysqli_query($conn, $query);
if($result === false) {
    die('Error fetching vehicles: ' . mysqli_error($conn));
}
$vehicles = mysqli_fetch_all($result, MYSQLI_ASSOC);
mysqli_free_result($result);

// Handle search and filter for bookings
$booking_search = isset($_GET['booking_search']) ? trim($_GET['booking_search']) : '';
$booking_status_filter = isset($_GET['booking_status_filter']) ? trim($_GET['booking_status_filter']) : '';

// Fetch user's bookings with search and filter
$query = "SELECT b.*, v.model, v.registration_number, vb.name as brand_name, v.image, v.daily_rate,
          b.start_date as pickup_date, b.end_date as return_date 
          FROM bookings b 
          JOIN vehicles v ON b.vehicle_id = v.id 
          JOIN vehicle_brands vb ON v.brand_id = vb.id 
          WHERE b.user_id = ?";
$params = [$user_id];
$types = 'i';
if ($booking_search !== '') {
    $query .= " AND (b.id LIKE ? OR v.model LIKE ? OR vb.name LIKE ? OR b.status LIKE ?)";
    $search_term = "%$booking_search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
    $types .= 'ssss';
}
if ($booking_status_filter !== '') {
    $query .= " AND b.status = ?";
    $params[] = $booking_status_filter;
    $types .= 's';
}
$query .= " ORDER BY b.created_at DESC";

$stmt = mysqli_prepare($conn, $query);
if (!$stmt) {
    die("Error preparing statement: " . mysqli_error($conn));
}
// Dynamic bind_param
mysqli_stmt_bind_param($stmt, $types, ...$params);
if (!mysqli_stmt_execute($stmt)) {
    die("Error executing statement: " . mysqli_stmt_error($stmt));
}
$result = mysqli_stmt_get_result($stmt);
$bookings = [];
while ($row = mysqli_fetch_assoc($result)) {
    // Fetch payment info for this booking
    $payment_query = "SELECT * FROM payments WHERE booking_id = ? ORDER BY created_at DESC LIMIT 1";
    $payment_stmt = mysqli_prepare($conn, $payment_query);
    mysqli_stmt_bind_param($payment_stmt, "i", $row['id']);
    mysqli_stmt_execute($payment_stmt);
    $payment_result = mysqli_stmt_get_result($payment_stmt);
    $payment = mysqli_fetch_assoc($payment_result);
    $row['payment'] = $payment;
    $bookings[] = $row;
}

// Handle booking cancellation
if(isset($_POST['cancel_booking'])) {
    $booking_id = mysqli_real_escape_string($conn, $_POST['booking_id']);
    
    $cancel_query = "UPDATE bookings SET status = 'cancelled' WHERE id = ? AND user_id = ?";
    $stmt = mysqli_prepare($conn, $cancel_query);
    
    if (!$stmt) {
        die("Error preparing cancel statement: " . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($stmt, "ii", $booking_id, $user_id);
    
    if(mysqli_stmt_execute($stmt)) {
        // Set vehicle status to available and increment quantity
        $vehicle_update_query = "UPDATE vehicles v JOIN bookings b ON v.id = b.vehicle_id SET v.status = 'available', v.quantity = v.quantity + 1 WHERE b.id = ?";
        $stmt2 = mysqli_prepare($conn, $vehicle_update_query);
        if ($stmt2) {
            mysqli_stmt_bind_param($stmt2, "i", $booking_id);
            mysqli_stmt_execute($stmt2);
            mysqli_stmt_close($stmt2);
        }
        $_SESSION['success'] = "Booking cancelled successfully. Cancellation fee of ₱200 will be applied.";
        header("Location: my_bookings.php");
        exit();
    } else {
        $error = "Failed to cancel booking: " . mysqli_error($conn);
    }
}

$today = date('Y-m-d');
$tomorrow = date('Y-m-d', strtotime('+1 day'));
$prefill_pickup_date = isset($_GET['pickup_date']) ? $_GET['pickup_date'] : $today;
$prefill_pickup_time = isset($_GET['pickup_time']) ? $_GET['pickup_time'] : '08:00';
$prefill_return_date = isset($_GET['return_date']) ? $_GET['return_date'] : $tomorrow;
$prefill_return_time = isset($_GET['return_time']) ? $_GET['return_time'] : '09:00';

$show_payment_success = false;
$recent_booking = null;
if (isset($_SESSION['payment_success']) && $_SESSION['payment_success'] && isset($_SESSION['recent_booking_id'])) {
    $show_payment_success = true;
    $recent_booking_id = $_SESSION['recent_booking_id'];
    // Find the recent booking from $bookings
    foreach ($bookings as $b) {
        if ($b['id'] == $recent_booking_id) {
            $recent_booking = $b;
            break;
        }
    }
    // Unset session variables so it only shows once
    unset($_SESSION['payment_success']);
    unset($_SESSION['recent_booking_id']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">    
    <title>QuickTrip Car Rental - Dashboard</title>
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
<div class="container py-4">
    <?php if(isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php 
            echo $_SESSION['success'];
            unset($_SESSION['success']);
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if(isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php 
            echo $_SESSION['error'];
            unset($_SESSION['error']);
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="section__container">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="section-title">Available Vehicles</h2>
            <a href="#myBookingsSection" class="btn btn-warning" style="font-weight:bold;"><i class="ri-eye-line"></i> Check My Bookings</a>
        </div>
        <?php if(empty($vehicles)): ?>
            <div class="no-vehicles">
                <i class="ri-car-line"></i>
                <h3>No Vehicles Available</h3>
                <p>Check back later for new vehicles!</p>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach($vehicles as $vehicle): ?>
                    <div class="col-md-6 col-lg-4">
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
                                <a href="book_vehicle.php?id=<?php echo $vehicle['id']; ?>" class="btn btn-primary book-now-btn">
                                    <i class="ri-calendar-check-line"></i> Book Now
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="card shadow" id="myBookingsSection">
        <div class="card-header bg-primary text-white">
            <h4 class="mb-0">My Bookings</h4>
        </div>
        <div class="card-body">
<form class="row g-3 mb-3 align-items-center" method="get" action="#myBookingsSection">
    <div class="col-md-5 d-flex align-items-center">
        <input type="text" class="form-control" name="booking_search" placeholder="Search by Booking ID, Car, Status..." value="<?php echo htmlspecialchars($booking_search); ?>">
        <button type="submit" class="btn btn-primary ms-2" title="Search" style="padding: 0.375rem 0.75rem;">
            <i class="ri-search-line"></i>
        </button>
    </div>
    <div class="col-md-3">
        <select class="form-select" name="booking_status_filter">
            <option value="">All Statuses</option>
            <option value="pending" <?php if($booking_status_filter==='pending') echo 'selected'; ?>>Pending</option>
            <option value="confirmed" <?php if($booking_status_filter==='confirmed') echo 'selected'; ?>>Confirmed</option>
            <option value="cancelled" <?php if($booking_status_filter==='cancelled') echo 'selected'; ?>>Cancelled</option>
            <option value="completed" <?php if($booking_status_filter==='completed') echo 'selected'; ?>>Completed</option>
        </select>
    </div>
</form>
            <div class="table-responsive" style="max-height:400px; overflow-y:auto;">
                <table class="table table-hover">
                    <thead style="position:sticky;top:0;background:#fff;z-index:1;">
                        <tr>
                            <!-- <th>Booking ID</th> -->
                            <th>Car</th>
                            <th>Pickup Date</th>
                            <th>Return Date</th>
                            <th>Total Amount</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
        <?php foreach($bookings as $booking): ?>
                            <tr>
                                <!-- <td>#<?php echo $booking['id']; ?></td> -->
                                <td><?php echo $booking['brand_name'] . ' ' . $booking['model']; ?></td>
                                <td><?php echo date('M j, Y', strtotime($booking['pickup_date'])); ?></td>
                                <td><?php echo date('M j, Y', strtotime($booking['return_date'])); ?></td>
                                <td>₱<?php echo number_format($booking['total_amount'], 2); ?></td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo $booking['status'] == 'pending' ? 'warning' : 
                                            ($booking['status'] == 'confirmed' ? 'success' : 
                                            ($booking['status'] == 'cancelled' ? 'danger' : 'secondary')); 
                                    ?>">
                        <?php echo ucfirst($booking['status']); ?>
                    </span>
                                </td>
                                <td>
                                    <div class="d-flex gap-2">
                                        <button type="button" class="btn btn-sm btn-orange" 
                                                onclick="viewReceipt(<?php echo htmlspecialchars(json_encode($booking)); ?>)">
                                            <i class="fas fa-receipt"></i> Receipt
                                        </button>
                                        <?php if($booking['status'] === 'pending'): ?>
                                            <button type="button" class="btn btn-sm btn-red" data-bs-toggle="modal" 
                                                    data-bs-target="#cancelModal<?php echo $booking['id']; ?>">
                                                <i class="fas fa-times"></i> Cancel
                                            </button>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Cancel Booking Modal -->
                                    <div class="modal fade" id="cancelModal<?php echo $booking['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content">
                                                <div class="modal-header bg-danger text-white">
                                                    <h5 class="modal-title">Cancel Booking</h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="alert alert-danger">
                                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                                        Reservation fee is not refundable after 24 hours of booking.
                                                    </div>
                                                    <div class="mb-2">
                                                        <strong>Refundable Cancellation Time Left:</strong>
                                                        <span id="refundTimer<?php echo $booking['id']; ?>">Calculating...</span>
                                                    </div>
                                                    <p>Are you sure you want to cancel this booking?</p>
                                                </div>
                                                <div class="modal-footer">
                                                    <form method="post">
                                                        <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">No, Keep Booking</button>
                                                        <button type="submit" name="cancel_booking" class="btn btn-danger">Yes, Cancel Booking</button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
                </div>
                
<!-- Receipt Modal -->
<div class="modal fade" id="receiptModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Booking Receipt</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="receiptContent">
                <!-- Receipt content will be inserted here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="generatePDF()">
                    <i class="fas fa-download"></i> Save as PDF
                </button>
            </div>
        </div>
                        </div>
                    </div>
                    
<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
function viewReceipt(booking) {
    // Payment info
    let paymentHtml = '';
    if (booking.payment) {
        paymentHtml = `
        <div class="mb-4">
            <h5>Payment Information</h5>
            <hr>
            <div class="row mb-2">
                <div class="col-6"><strong>Sender Name:</strong></div>
                <div class="col-6 text-end">${booking.payment.sender_name || ''}</div>
            </div>
            <div class="row mb-2">
                <div class="col-6"><strong>Reference Number:</strong></div>
                <div class="col-6 text-end">${booking.payment.reference_number || ''}</div>
            </div>
            <div class="row mb-2">
                <div class="col-6"><strong>Amount Paid:</strong></div>
                <div class="col-6 text-end">₱${parseFloat(booking.payment.amount || 0).toFixed(2)}</div>
            </div>
            <div class="row mb-2 align-items-center" style="display:flex;align-items:center;">
                <div class="col-6"><strong>Proof of Payment:</strong></div>
                <div class="col-6 text-end" style="display:flex;justify-content:flex-end;align-items:center;">
                    ${booking.payment.image ? `<a href="../${booking.payment.image}" target="_blank"><img src="../${booking.payment.image}" alt="Proof" style="max-width:100px;width:100px;height:auto;border:1px solid #ccc;"></a>` : 'N/A'}
                </div>
            </div>
        </div>
        `;
    }
    // Calculate number of days
    const pickup = new Date(booking.pickup_date);
    const returnD = new Date(booking.return_date);
    let diffMs = returnD - pickup;
    let diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));
    let rentalDays = diffDays;
    if (rentalDays < 1) rentalDays = 1;
    const dailyRate = parseFloat(booking.daily_rate);
    const rentalFee = rentalDays * dailyRate;
    const reservationFee = rentalDays * 200;
    const totalAmount = rentalFee + reservationFee;
    const requiredDeposit = reservationFee;
    const amountPaid = booking.payment ? parseFloat(booking.payment.amount || 0) : 0;
    let balance = totalAmount - amountPaid;
    let balanceLabel = 'Balance Due';
    if (balance < 0) {
        balanceLabel = 'Change';
        balance = Math.abs(balance);
    }
    const receiptContent = `
        <div class="receipt-container p-4">
            <div class="text-center mb-4">
                <h4>QuickTrip Car Rental Receipt</h4>
                <p class="mb-0">Brgy. Bubukal, Sta Cruz, Laguna</p>
                <p>Contact: 1234567890</p>
            </div>
            
            <div class="row mb-4">
                <div class="col-6">
                    <strong>Booking ID:</strong> #${booking.id}<br>
                    <strong>Date:</strong> ${new Date(booking.created_at).toLocaleDateString()}<br>
                    <strong>Status:</strong> ${booking.status.charAt(0).toUpperCase() + booking.status.slice(1)}
                </div>
                <div class="col-6 text-end">
                    <strong>Car:</strong> ${booking.brand_name} ${booking.model}<br>
                    <strong>Daily Rate:</strong> ₱${dailyRate.toFixed(2)}
                </div>
            </div>
            
            ${paymentHtml}
            <div class="mb-4">
                <h5>Payment Summary</h5>
                <hr>
                <div class="row">
                    <div class="col-8">Daily Rate:</div>
                    <div class="col-4 text-end">₱${dailyRate.toFixed(2)}</div>
                </div>
                <div class="row">
                    <div class="col-8">Number of Days:</div>
                    <div class="col-4 text-end">${rentalDays} day(s)</div>
                </div>
                <div class="row">
                    <div class="col-8">Reservation Fee:</div>
                    <div class="col-4 text-end">₱${reservationFee.toFixed(2)} (${rentalDays} x ₱200)</div>
                </div>
                <div class="row">
                    <div class="col-8">Total Rental Amount:</div>
                    <div class="col-4 text-end">₱${rentalFee.toFixed(2)}</div>
                </div>
                <div class="row">
                    <div class="col-8 fw-bold">Required Deposit:</div>
                    <div class="col-4 text-end fw-bold">₱${requiredDeposit.toFixed(2)}</div>
                </div>
                <hr>
            </div>
            <div class="mb-4">
                <div class="row fw-bold">
                    <div class="col-8">Total Amount:</div>
                    <div class="col-4 text-end">₱${totalAmount.toFixed(2)}</div>
                </div>
                <div class="row fw-bold">
                    <div class="col-8">Amount Paid:</div>
                    <div class="col-4 text-end">₱${amountPaid.toFixed(2)}</div>
                </div>
                <div class="row fw-bold">
                    <div class="col-8">${balanceLabel}:</div>
                    <div class="col-4 text-end">₱${balance.toFixed(2)}</div>
                </div>
            </div>
            <div class="mt-4">
                <h5>Important Information</h5>
                <hr>
                <ul class="mb-0">
                    <li>Late Return Penalty: ₱${(dailyRate * 0.3).toFixed(2)} per day</li>
                    <li>Reservation Fee: ₱200.00 per day (required deposit)</li>
                </ul>
            </div>
            <div class="mt-4 pt-4 border-top">
                <small class="text-muted">
                    Note: This is an official receipt for your car rental booking. Please keep this for your records.
                    For any questions or concerns, please contact our office.
                </small>
            </div>
        </div>
    `;
    document.getElementById('receiptContent').innerHTML = receiptContent;
    new bootstrap.Modal(document.getElementById('receiptModal')).show();
}

function generatePDF() {
    const element = document.getElementById('receiptContent');
    const opt = {
        margin: 1,
        filename: 'car-rental-receipt.pdf',
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2 },
        jsPDF: { unit: 'in', format: 'letter', orientation: 'portrait' }
    };
    
    html2pdf().set(opt).from(element).save();
}

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

// Add refund timer for each booking
<?php foreach($bookings as $booking): ?>
(function() {
    var timerId = 'refundTimer<?php echo $booking['id']; ?>';
    var createdAt = new Date('<?php echo $booking['created_at']; ?>');
    var refundDeadline = new Date(createdAt.getTime() + 24 * 60 * 60 * 1000);
    function updateRefundTimer() {
        var now = new Date();
        var diff = refundDeadline - now;
        var el = document.getElementById(timerId);
        if (!el) return;
        if (diff > 0) {
            var hours = Math.floor(diff / (1000 * 60 * 60));
            var minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
            var seconds = Math.floor((diff % (1000 * 60)) / 1000);
            el.textContent = hours + 'h ' + minutes + 'm ' + seconds + 's';
        } else {
            el.textContent = 'No refund (24 hours passed)';
        }
    }
    setInterval(updateRefundTimer, 1000);
    updateRefundTimer();
})();
<?php endforeach; ?>
</script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.querySelector('input[name="booking_search"]');
    const statusFilter = document.querySelector('select[name="booking_status_filter"]');
    const form = searchInput.closest('form');

    // Trigger form submit on input change or keyup for search input
    searchInput.addEventListener('keyup', function() {
        form.submit();
    });

    // Trigger form submit on change for status filter dropdown
    statusFilter.addEventListener('change', function() {
        form.submit();
    });
});
</script>

<?php if ($show_payment_success && $recent_booking): ?>
<!-- Payment Success Modal -->
<style>
#paymentSuccessModalBg {
  position: fixed;
  top: 0; left: 0; right: 0; bottom: 0;
  background: rgba(0,0,0,0.3);
  z-index: 3000;
  display: flex;
  align-items: center;
  justify-content: center;
}
#paymentSuccessModal {
  background-color: #D35400;
  border-radius: 10px;
  padding: 40px 30px 30px 30px;
  color: white;
  width: 400px;
  max-width: 95vw;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
  text-align: center;
  animation: slideIn 1s ease-out forwards;
}
#paymentSuccessModal .icon-container {
  display: flex;
  justify-content: center;
  margin-bottom: 20px;
  animation: bounce 1s ease-in-out;
}
#paymentSuccessModal .checkmark {
  width: 50px;
  height: 50px;
  background-color: #fff;
  border-radius: 50%;
  display: flex;
  justify-content: center;
  align-items: center;
  position: relative;
  animation: scaleUp 0.5s ease-in forwards;
}
#paymentSuccessModal .checkmark svg {
  width: 24px;
  height: 24px;
  stroke: #D35400;
  stroke-width: 2;
}
#paymentSuccessModal h1 {
  margin: 20px 0 10px 0;
  font-size: 24px;
  font-weight: 600;
  animation: fadeIn 1s ease-in forwards;
}
#paymentSuccessModal p {
  margin-bottom: 20px;
  animation: fadeIn 1.2s ease-in forwards;
}
#paymentSuccessModal .btn-show-receipt {
  display: inline-block;
  background-color: #ffffff;
  color: #D35400;
  padding: 10px 30px;
  border-radius: 30px;
  text-decoration: none;
  font-size: 16px;
  font-weight: 600;
  border: none;
  transition: background-color 0.3s ease, transform 0.3s ease;
  cursor: pointer;
}
#paymentSuccessModal .btn-show-receipt:hover {
  background-color: #e0e0e0;
  transform: scale(1.05);
}
@keyframes slideIn {
  from { transform: translateY(-50px); opacity: 0; }
  to { transform: translateY(0); opacity: 1; }
}
@keyframes bounce {
  0%,100% { transform: translateY(-5px); }
  50% { transform: translateY(5px); }
}
@keyframes scaleUp {
  from { transform: scale(0.5); opacity: 0; }
  to { transform: scale(1); opacity: 1; }
}
@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}
</style>
<div id="paymentSuccessModalBg">
  <div id="paymentSuccessModal">
    <div class="icon-container">
      <div class="checkmark">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M20 6L9 17l-5-5"></path>
        </svg>
      </div>
    </div>
    <h1>Payment Successful!</h1>
    <p>Your transaction was completed successfully. Thank you for your purchase.</p>
    <button class="btn-show-receipt">Show Receipt</button>
  </div>
</div>
<script>
  document.addEventListener('DOMContentLoaded', function() {
    var modalBg = document.getElementById('paymentSuccessModalBg');
    var showBtn = document.querySelector('#paymentSuccessModal .btn-show-receipt');
    var recentBooking = <?php echo json_encode($recent_booking); ?>;
    showBtn.addEventListener('click', function() {
      modalBg.style.display = 'none';
      setTimeout(function() {
        viewReceipt(recentBooking);
      }, 300);
    });
    // Optionally, close modal with Esc key
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') {
        modalBg.style.display = 'none';
      }
    });
  });
</script>
<?php endif; ?>

<?php
// Fetch footer content from admin if not already fetched
if (!isset($footer_content)) {
    $footer_query = "SELECT * FROM footer_content ORDER BY id DESC LIMIT 1";
    $footer_result = mysqli_query($conn, $footer_query);
    $footer_content = mysqli_fetch_assoc($footer_result);
}
?>
    <!-- Footer -->
    <footer class="footer bg-dark text-white mt-5 pt-5 pb-4">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-4">
                    <h5><i class="ri-map-pin-fill" style="color:#D35400;"></i> Location</h5>
                    <p><?= htmlspecialchars($footer_content['location'] ?? 'No location provided') ?></p>
                    <p><i class="ri-phone-fill" style="color:#D35400;"></i> <strong>Contact Number:</strong> <?= htmlspecialchars($footer_content['contact_number'] ?? 'N/A') ?></p>
                    <p><i class="ri-calendar-schedule-fill" style="color:#D35400;"></i> <strong>Office Hours:</strong> <?= htmlspecialchars($footer_content['office_hours'] ?? 'N/A') ?></p>
                </div>
                <div class="col-md-4 mb-4">
                    <h5>Connect with Us</h5>
                    <ul class="list-unstyled d-flex gap-3">
                        <?php if (!empty($footer_content['facebook'])): ?>
                            <li><a href="<?= htmlspecialchars($footer_content['facebook']) ?>" target="_blank" class="text-white fs-4"><i class="ri-facebook-circle-fill" style="color:#D35400;"></i></a></li>
                        <?php endif; ?>
                        <?php if (!empty($footer_content['linkedin'])): ?>
                            <li><a href="<?= htmlspecialchars($footer_content['linkedin']) ?>" target="_blank" class="text-white fs-4"><i class="ri-linkedin-fill" style="color:#D35400;"></i></a></li>
                        <?php endif; ?>
                        <?php if (!empty($footer_content['instagram'])): ?>
                            <li><a href="<?= htmlspecialchars($footer_content['instagram']) ?>" target="_blank" class="text-white fs-4"><i class="ri-instagram-line" style="color:#D35400;"></i></a></li>
                        <?php endif; ?>
                    </ul>
                </div>
                <div class="col-md-4 mb-4">
                    <h5><i class="ri-mail-fill" style="color:#D35400;"></i> Email</h5>
                    <p><?= htmlspecialchars($footer_content['email'] ?? 'No email provided') ?></p>
                </div>
            </div>
            <div class="text-center mt-3">
                <small>&copy; <?= date('Y') ?> QuickTrip Car Rental. All rights reserved.</small>
            </div>
        </div>
    </footer>

    <style>
        .footer a:hover {
            color: #D35400 !important;
            text-decoration: none;
        }
        .footer ul {
            padding-left: 0;
        }
        .footer ul li {
            list-style: none;
        }
    </style>
</body>
</html> 
