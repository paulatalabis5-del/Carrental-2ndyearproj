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

// Handle subscriber actions
if(isset($_POST['delete_subscriber'])) {
    $subscriber_id = mysqli_real_escape_string($conn, $_POST['subscriber_id']);
    $delete_query = "DELETE FROM subscribers WHERE id = '$subscriber_id'";
    if(mysqli_query($conn, $delete_query)) {
        $_SESSION['success'] = "Subscriber deleted successfully!";
        header("Location: subscribers.php");
        exit();
    } else {
        $error = "Failed to delete subscriber: " . mysqli_error($conn);
    }
}

// Handle search/filter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$date_filter = isset($_GET['date_filter']) ? trim($_GET['date_filter']) : '';
// Build query with search and filter
$query = "SELECT * FROM subscribers WHERE 1";
if ($search !== '') {
    $search_esc = mysqli_real_escape_string($conn, $search);
    $query .= " AND email LIKE '%$search_esc%'";
}
if ($date_filter !== '') {
    if ($date_filter === 'today') {
        $query .= " AND DATE(created_at) = CURDATE()";
    } elseif ($date_filter === 'last7') {
        $query .= " AND created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
    } elseif ($date_filter === 'last30') {
        $query .= " AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
    }
}
$query .= " ORDER BY created_at DESC";
$result = mysqli_query($conn, $query);
if($result === false) {
    die("Error fetching subscribers: " . mysqli_error($conn));
}
$subscribers = mysqli_fetch_all($result, MYSQLI_ASSOC);
mysqli_free_result($result);

$page_title = "Subscribers";
include('includes/header.php');
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Newsletter Subscribers</h2>
    </div>
    <!-- Search Bar and Filter -->
    <form class="row g-3 mb-3 align-items-center" method="get" action="">
        <div class="col-md-4 d-flex align-items-center">
            <input type="text" class="form-control" name="search" placeholder="Search by Email..." value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit" class="btn btn-primary ms-2" title="Search" style="padding: 0.375rem 0.75rem;">
                <i class="fas fa-search"></i>
            </button>
        </div>
        <div class="col-md-3">
            <select class="form-select" name="date_filter">
                <option value="">All Dates</option>
                <option value="today" <?php if($date_filter==='today') echo 'selected'; ?>>Today</option>
                <option value="last7" <?php if($date_filter==='last7') echo 'selected'; ?>>Last 7 Days</option>
                <option value="last30" <?php if($date_filter==='last30') echo 'selected'; ?>>Last 30 Days</option>
            </select>
        </div>
    </form>

    <?php if(isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <?php if(isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php 
            echo $_SESSION['success'];
            unset($_SESSION['success']);
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

        <div class="card">
            <div class="card-body">
                <!-- Top Scrollbar -->
                <div id="top-scroll-subscribers" style="overflow-x: auto; overflow-y: hidden; width: 100%;">
                    <div style="width: max-content; height: 1px;"></div>
                </div>
                <div id="table-scroll-subscribers" class="table-responsive">
                    <table class="table table-bordered table-hover" id="subscribers-table">
                        <thead>
                            <tr>
                                <th>Email</th>
                                <th>Subscribed At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if(count($subscribers) > 0): ?>
                            <?php foreach($subscribers as $subscriber): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($subscriber['email']); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($subscriber['created_at'])); ?></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" 
                                                data-bs-target="#deleteSubscriber<?php echo $subscriber['id']; ?>">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>

                                <!-- Delete Subscriber Modal -->
                                <div class="modal fade" id="deleteSubscriber<?php echo $subscriber['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Delete Subscriber</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <p>Are you sure you want to delete this subscriber?</p>
                                                <p class="text-danger">This action cannot be undone.</p>
                                            </div>
                                            <div class="modal-footer">
                                                <form method="post">
                                                    <input type="hidden" name="subscriber_id" value="<?php echo $subscriber['id']; ?>">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" name="delete_subscriber" class="btn btn-danger">Delete</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" class="text-center">No subscribers found</td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

<script>
// Sync top and bottom scrollbars for subscribers table
window.addEventListener('DOMContentLoaded', function() {
    var topScroll = document.getElementById('top-scroll-subscribers');
    var tableScroll = document.getElementById('table-scroll-subscribers');
    var table = document.getElementById('subscribers-table');
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.querySelector('input[name="search"]');
    const dateFilter = document.querySelector('select[name="date_filter"]');
    const form = searchInput.closest('form');

    // Trigger form submit on input change or keyup for search input
    searchInput.addEventListener('keyup', function() {
        form.submit();
    });

    // Trigger form submit on change for date filter dropdown
    dateFilter.addEventListener('change', function() {
        form.submit();
    });
});
</script>

<?php include('includes/footer.php'); ?>
