<?php
session_name('quicktrip_session');
session_start();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
header('Pragma: no-cache');
require_once('../config/database.php');

// Check if admin is logged in
if(!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

// Handle booking status updates
if(isset($_POST['update_booking'])) {
    $booking_id = mysqli_real_escape_string($conn, $_POST['booking_id']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    
    // Start transaction
    mysqli_begin_transaction($conn);
    
    try {
        // Update booking status
        $query = "UPDATE bookings SET status = '$status' WHERE id = '$booking_id'";
        if(!mysqli_query($conn, $query)) {
            throw new Exception("Error updating booking status: " . mysqli_error($conn));
        }
        
        // If booking is confirmed, update vehicle status to booked
        if($status === 'confirmed') {
            $query = "UPDATE vehicles v JOIN bookings b ON v.id = b.vehicle_id SET v.status = 'booked' WHERE b.id = '$booking_id'";
            if(!mysqli_query($conn, $query)) {
                throw new Exception("Error updating vehicle status: " . mysqli_error($conn));
            }
        }
        // If booking is cancelled or completed, update vehicle status to available
        elseif($status === 'cancelled' || $status === 'completed') {
            $query = "UPDATE vehicles v JOIN bookings b ON v.id = b.vehicle_id SET v.status = 'available' WHERE b.id = '$booking_id'";
            if(!mysqli_query($conn, $query)) {
                throw new Exception("Error updating vehicle status: " . mysqli_error($conn));
            }
            // If completed, increment vehicle quantity by 1
            if($status === 'completed') {
                $query = "UPDATE vehicles v JOIN bookings b ON v.id = b.vehicle_id SET v.quantity = v.quantity + 1 WHERE b.id = '$booking_id'";
                if(!mysqli_query($conn, $query)) {
                    throw new Exception("Error updating vehicle quantity: " . mysqli_error($conn));
                }
            }
        }
        
        mysqli_commit($conn);
        $_SESSION['success'] = "Booking status updated successfully!";
        header("Location: bookings.php");
        exit();
    } catch(Exception $e) {
        mysqli_rollback($conn);
        $error = "Failed to update booking status: " . $e->getMessage();
    }
}

// Handle search/filter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status_filter']) ? trim($_GET['status_filter']) : '';

// Build query with search and filter
$query = "SELECT b.*, u.name as user_name, v.model as vehicle_model, vb.name as brand_name 
          FROM bookings b
          JOIN users u ON b.user_id = u.id
          JOIN vehicles v ON b.vehicle_id = v.id
          JOIN vehicle_brands vb ON v.brand_id = vb.id
          WHERE 1";
if ($search !== '') {
    $search_esc = mysqli_real_escape_string($conn, $search);
    $query .= " AND (b.id LIKE '%$search_esc%' OR u.name LIKE '%$search_esc%' OR v.model LIKE '%$search_esc%' OR vb.name LIKE '%$search_esc%')";
}
if ($status_filter !== '') {
    $status_esc = mysqli_real_escape_string($conn, $status_filter);
    $query .= " AND b.status = '$status_esc'";
}
$query .= " ORDER BY b.created_at DESC";
$result = mysqli_query($conn, $query);
if($result === false) {
    die("Error fetching bookings: " . mysqli_error($conn));
}
$bookings = mysqli_fetch_all($result, MYSQLI_ASSOC);
mysqli_free_result($result);

// Fetch payment info for each booking
foreach ($bookings as &$booking) {
    $payment_query = "SELECT * FROM payments WHERE booking_id = ? ORDER BY created_at DESC LIMIT 1";
    $stmt = mysqli_prepare($conn, $payment_query);
    mysqli_stmt_bind_param($stmt, "i", $booking['id']);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $payment = mysqli_fetch_assoc($result);
    $booking['payment'] = $payment;
}
unset($booking);

// Set page title for the header
$page_title = "Booking Management";

// Include header
include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Bookings</h2>
    </div>
    
    <!-- Search and Filter Bar -->
    <form class="row g-3 mb-3 align-items-center" method="get" action="" id="filterForm">
        <div class="col-md-4 d-flex align-items-center">
            <input type="text" class="form-control" name="search" placeholder="Search by Booking ID, User, Vehicle..." value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit" class="btn btn-primary ms-2" title="Search" style="padding: 0.375rem 0.75rem;">
                <i class="fas fa-search"></i>
            </button>
        </div>
        <div class="col-md-3">
            <select class="form-select" name="status_filter" id="statusFilter">
                <option value="">All Statuses</option>
                <option value="pending" <?php if($status_filter==='pending') echo 'selected'; ?>>Pending</option>
                <option value="confirmed" <?php if($status_filter==='confirmed') echo 'selected'; ?>>Confirmed</option>
                <option value="completed" <?php if($status_filter==='completed') echo 'selected'; ?>>Completed</option>
                <option value="cancelled" <?php if($status_filter==='cancelled') echo 'selected'; ?>>Cancelled</option>
            </select>
        </div>
    </form>
    
    <?php if(isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php 
            echo $_SESSION['success'];
            unset($_SESSION['success']);
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <?php if(isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

<script>
    document.getElementById('statusFilter').addEventListener('change', function() {
        document.getElementById('filterForm').submit();
    });
</script>

    <div class="card">
        <div class="card-body">
            <!-- Top Scrollbar -->
            <div id="top-scroll-bookings" style="overflow-x: auto; overflow-y: hidden; width: 100%;">
                <div style="width: max-content; height: 1px;"></div>
            </div>
            <div id="table-scroll-bookings" class="table-responsive">
                <table class="table table-bordered table-hover" id="bookings-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Vehicle</th>
                            <th>Pickup Date & Time</th>
                            <th>Return Date & Time</th>
                            <th>Total Amount</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($bookings) > 0): ?>
                            <?php foreach($bookings as $booking): ?>
                                <tr>
                                    <td><?php echo $booking['id']; ?></td>
                                    <td><?php echo htmlspecialchars($booking['user_name']); ?></td>
                                    <td><?php echo htmlspecialchars($booking['brand_name'] . ' ' . $booking['vehicle_model']); ?></td>
                                    <td>
                                        <?php 
                                        $pickup_datetime = new DateTime($booking['start_date'] . ' ' . $booking['start_time']);
                                        echo $pickup_datetime->format('M d, Y h:i A');
                                        ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $return_datetime = new DateTime($booking['end_date'] . ' ' . $booking['end_time']);
                                        echo $return_datetime->format('M d, Y h:i A');
                                        ?>
                                    </td>
                                    <td>₱<?php echo number_format($booking['total_amount'], 2); ?></td>
                                    <td>
                                        <?php 
                                        $status_class = '';
                                        switch($booking['status']) {
                                            case 'pending':
                                                $status_class = 'bg-warning';
                                                break;
                                            case 'confirmed':
                                                $status_class = 'bg-success';
                                                break;
                                            case 'completed':
                                                $status_class = 'bg-info';
                                                break;
                                            case 'cancelled':
                                                $status_class = 'bg-danger';
                                                break;
                                        }
                                        ?>
                                        <span class="badge <?php echo $status_class; ?>"><?php echo ucfirst($booking['status']); ?></span>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-primary edit-booking"
                                            data-id="<?php echo $booking['id']; ?>"
                                            data-status="<?php echo $booking['status']; ?>"
                                            data-bs-toggle="modal" data-bs-target="#editBookingModal">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-info" style="font-size:0.7rem;" onclick='viewReceipt(<?php echo json_encode($booking); ?>)'>
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center">No bookings found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Edit Booking Modal -->
<div class="modal fade" id="editBookingModal" tabindex="-1" aria-labelledby="editBookingModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editBookingModalLabel">Update Booking Status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" id="edit_booking_id" name="booking_id">
                    <div class="mb-3">
                        <label for="edit_status" class="form-label">Status</label>
                        <select class="form-select" id="edit_status" name="status" required>
                            <option value="pending">Pending</option>
                            <option value="confirmed">Confirmed</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_booking" class="btn btn-primary">Update Status</button>
                </div>
            </form>
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

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
    // Edit booking functionality
    document.querySelectorAll('.edit-booking').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const status = this.getAttribute('data-status');
            
            document.getElementById('edit_booking_id').value = id;
            document.getElementById('edit_status').value = status;
        });
    });

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
        const pickup = new Date(booking.start_date);
        const returnD = new Date(booking.end_date);
        let diffMs = returnD - pickup;
        let diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));
        let rentalDays = diffDays;
        if (rentalDays < 1) rentalDays = 1;
        const dailyRate = parseFloat(booking.daily_rate || booking.total_amount / rentalDays);
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
                        <strong>Car:</strong> ${booking.brand_name} ${booking.vehicle_model}<br>
                        <strong>Daily Rate:</strong> ₱${dailyRate.toFixed(2)}
                    </div>
                </div>
                
                <div class="mb-4">
                    <h5>Booking Details</h5>
                    <hr>
                    <div class="row">
                        <div class="col-6">
                            <strong>Pickup Date:</strong><br>
                            ${new Date(booking.start_date).toLocaleDateString()}
                        </div>
                        <div class="col-6">
                            <strong>Return Date:</strong><br>
                            ${new Date(booking.end_date).toLocaleDateString()}
                        </div>
                    </div>
                    <div class="mt-3">
                        <strong>Pickup Location:</strong><br>
                        ${booking.pickup_barangay}, ${booking.pickup_municipality}, ${booking.pickup_province}
                        <br>
                        <strong>Return Location:</strong><br>
                        ${booking.return_barangay}, ${booking.return_municipality}, ${booking.return_province}
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

    // Sync top and bottom scrollbars for bookings table
    window.addEventListener('DOMContentLoaded', function() {
        var topScroll = document.getElementById('top-scroll-bookings');
        var tableScroll = document.getElementById('table-scroll-bookings');
        var table = document.getElementById('bookings-table');
        // Set the width of the inner div in top-scroll to match the table
        function syncWidths() {
            topScroll.firstElementChild.style.width = table.scrollWidth + 'px';
        }
        syncWidths();
        window.addEventListener('resize', syncWidths);
        // Sync scroll positions
        topScroll.onscroll = function() {
            tableScroll.scrollLeft = topScroll.scrollLeft;
        };
        tableScroll.onscroll = function() {
            topScroll.scrollLeft = tableScroll.scrollLeft;
        };
    });
</script>

<?php include 'includes/footer.php'; ?> 