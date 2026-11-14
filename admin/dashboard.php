<?php
session_name('quicktrip_session');
session_start();
require_once('../config/database.php');

// Check if admin is logged in
if(!isset($_SESSION['admin_id'])) {
    header("Location: ../mainlogin.php");
    exit();
}

// Get total users (excluding admins)
$result = $conn->query("SELECT COUNT(*) as count FROM users WHERE is_admin = 0");
$total_users = $result->fetch_assoc()['count'];

// Get total vehicles
$result = $conn->query("SELECT COUNT(*) as count FROM vehicles");
$total_vehicles = $result->fetch_assoc()['count'];

// Get total bookings
$result = $conn->query("SELECT COUNT(*) as count FROM bookings");
$total_bookings = $result->fetch_assoc()['count'];

// Get pending bookings
$result = $conn->query("SELECT COUNT(*) as count FROM bookings WHERE status = 'pending'");
$pending_bookings = $result->fetch_assoc()['count'];

// Get pending testimonials
$result = $conn->query("SELECT COUNT(*) as count FROM testimonials WHERE status = 'pending'");
$pending_testimonials = $result->fetch_assoc()['count'];

// Get recent bookings
$stmt = $conn->prepare("
    SELECT b.*, u.name as customer_name, v.model as vehicle_model, vb.name as brand_name
    FROM bookings b
    JOIN users u ON b.user_id = u.id
    JOIN vehicles v ON b.vehicle_id = v.id
    JOIN vehicle_brands vb ON v.brand_id = vb.id
    ORDER BY b.created_at DESC
    LIMIT 5
");
$stmt->execute();
$recent_bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Set page title for the header
$page_title = "Dashboard";

// Include header
include 'includes/header.php';
?>

<div class="container-fluid">
    <!-- Dashboard Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <a href="users.php" class="text-decoration-none">
                <div class="dashboard-card blue">
                    <div class="card-content">
                        <div class="number"><?php echo $total_users; ?></div>
                        <div class="icon"><i class="fas fa-users"></i></div>
                    </div>
                    <div class="title">TOTAL USERS</div>
                </div>
            </a>
        </div>
        <div class="col-md-3">
            <a href="vehicles.php" class="text-decoration-none">
                <div class="dashboard-card green">
                    <div class="card-content">
                        <div class="number"><?php echo $total_vehicles; ?></div>
                        <div class="icon"><i class="fas fa-car"></i></div>
                    </div>
                    <div class="title">TOTAL VEHICLES</div>
                </div>
            </a>
        </div>
        <div class="col-md-3">
            <a href="bookings.php" class="text-decoration-none">
                <div class="dashboard-card yellow">
                    <div class="card-content">
                        <div class="number"><?php echo $total_bookings; ?></div>
                        <div class="icon"><i class="fas fa-calendar-check"></i></div>
                    </div>
                    <div class="title">TOTAL BOOKINGS</div>
                </div>
            </a>
        </div>
        <div class="col-md-3">
            <a href="bookings.php" class="text-decoration-none">
                <div class="dashboard-card red">
                    <div class="card-content">
                        <div class="number"><?php echo $pending_bookings; ?></div>
                        <div class="icon"><i class="fas fa-clock"></i></div>
                    </div>
                    <div class="title">PENDING BOOKINGS</div>
                </div>
            </a>
        </div>
    </div>
    
    <div class="row">
        <div class="col-12">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">Recent Bookings</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>CUSTOMER</th>
                                    <th>VEHICLE</th>
                                    <th>STATUS</th>
                                    <th>DATE</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(isset($recent_bookings) && count($recent_bookings) > 0): ?>
                                    <?php foreach($recent_bookings as $booking): ?>
                                        <tr>
                                            <td><?php echo $booking['id']; ?></td>
                                            <td><?php echo $booking['customer_name']; ?></td>
                                            <td><?php echo $booking['brand_name'] . ' ' . $booking['vehicle_model']; ?></td>
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
                                                    case 'cancelled':
                                                        $status_class = 'bg-danger';
                                                        break;
                                                    case 'completed':
                                                        $status_class = 'bg-info';
                                                        break;
                                                }
                                                ?>
                                                <span class="badge <?php echo $status_class; ?>"><?php echo ucfirst($booking['status']); ?></span>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($booking['created_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center">No bookings found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer">
                    <a href="bookings.php" class="btn btn-primary">View All Bookings</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include('includes/footer.php'); ?> 