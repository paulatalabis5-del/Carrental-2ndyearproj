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

// Handle search/filter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build query with search
$query = "SELECT p.*, b.user_id, b.vehicle_id, b.start_date, b.end_date, u.name as user_name, v.model as vehicle_model FROM payments p LEFT JOIN bookings b ON p.booking_id = b.id LEFT JOIN users u ON b.user_id = u.id LEFT JOIN vehicles v ON b.vehicle_id = v.id WHERE 1";
if ($search !== '') {
    $search_esc = mysqli_real_escape_string($conn, $search);
    $query .= " AND (p.sender_name LIKE '%$search_esc%' OR p.reference_number LIKE '%$search_esc%' OR p.amount LIKE '%$search_esc%')";
}
$query .= " ORDER BY p.created_at DESC";
$result = mysqli_query($conn, $query);

$page_title = "Payments";
include 'includes/header.php'; // This includes sidebar and toggle buttons
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Payment Submissions</h2>
    </div>
    <!-- Search and Filter Bar -->
    <form class="row g-3 mb-3 align-items-center" method="get" action="">
        <div class="col-md-4 d-flex align-items-center">
            <input type="text" class="form-control" name="search" placeholder="Search by Sender, Reference, Amount..." value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit" class="btn btn-primary ms-2" title="Search" style="padding: 0.375rem 0.75rem;">
                <i class="fas fa-search"></i>
            </button>
        </div>
    </form>
    <div class="card">
        <div class="card-body">
            <!-- Top Scrollbar -->
            <div id="top-scroll" style="overflow-x: auto; overflow-y: hidden; width: 100%;">
                <div style="width: max-content; height: 1px;"></div>
            </div>
            <div id="table-scroll" class="table-responsive">
                <table class="table table-bordered table-hover align-middle" id="payments-table">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Sender Name</th>
                            <th>Reference Number</th>
                            <th>Amount (₱)</th>
                            <th>Submitted At</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php 
                    $rows = [];
                    while($row = mysqli_fetch_assoc($result)) {
                        $rows[] = $row;
                    }
                    $total = count($rows);
                    for($i = 0; $i < $total; $i++): 
                        $row = $rows[$i];
                    ?>
                    <tr>
                        <td><?php echo $total - $i; ?></td>
                        <td><?php echo htmlspecialchars($row['sender_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['reference_number']); ?></td>
                        <td>₱<?php echo number_format($row['amount'], 2); ?></td>
                        <td><?php echo date('M d, Y h:i A', strtotime($row['created_at'])); ?></td>
                        <td>
                            <button class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#viewModal<?php echo $row['id']; ?>">
                                <i class="fas fa-eye"></i>
                            </button>
                        </td>
                    </tr>
                    <!-- Modal -->
                    <div class="modal fade" id="viewModal<?php echo $row['id']; ?>" tabindex="-1" aria-labelledby="viewModalLabel<?php echo $row['id']; ?>" aria-hidden="true">
                      <div class="modal-dialog modal-dialog-centered" style="max-width:700px;">
                        <div class="modal-content">
                          <div class="modal-header">
                            <h5 class="modal-title" id="viewModalLabel<?php echo $row['id']; ?>">Payment Details</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                          </div>
                          <div class="modal-body">
                            <p><strong>Sender Name:</strong> <?php echo htmlspecialchars($row['sender_name']); ?></p>
                            <p><strong>Reference Number:</strong> <?php echo htmlspecialchars($row['reference_number']); ?></p>
                            <p><strong>Amount:</strong> ₱<?php echo number_format($row['amount'], 2); ?></p>
                            <p><strong>Submitted At:</strong> <?php echo date('M d, Y h:i A', strtotime($row['created_at'])); ?></p>
                            <p><strong>Payment Screenshot:</strong></p>
                            <?php if(!empty($row['image'])): ?>
                                <img src="../<?php echo htmlspecialchars($row['image']); ?>" class="modal-img" alt="Payment Screenshot" style="max-width:100%;height:auto;display:block;margin:auto;">
                            <?php else: ?>
                                <span class="text-muted">No image uploaded.</span>
                            <?php endif; ?>
                          </div>
                          <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                          </div>
                        </div>
                      </div>
                    </div>
                    <?php endfor; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
// Sync top and bottom scrollbars
window.addEventListener('DOMContentLoaded', function() {
    var topScroll = document.getElementById('top-scroll');
    var tableScroll = document.getElementById('table-scroll');
    var table = document.getElementById('payments-table');
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

<?php include 'includes/footer.php'; // This includes the sidebar JS and overlay ?>