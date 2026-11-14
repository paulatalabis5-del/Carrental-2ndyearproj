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

// Handle contact query actions
if(isset($_POST['delete_query'])) {
    $query_id = mysqli_real_escape_string($conn, $_POST['query_id']);
    $delete_query = "DELETE FROM contact_queries WHERE id = '$query_id'";
    if(mysqli_query($conn, $delete_query)) {
        $_SESSION['success'] = "Contact query deleted successfully!";
        header("Location: contact_queries.php");
        exit();
    } else {
        $error = "Failed to delete contact query: " . mysqli_error($conn);
    }
}

if(isset($_POST['update_status'])) {
    $query_id = mysqli_real_escape_string($conn, $_POST['query_id']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    
    $update_query = "UPDATE contact_queries SET status = '$status' WHERE id = '$query_id'";
    if(mysqli_query($conn, $update_query)) {
        $_SESSION['success'] = "Query status updated successfully!";
        header("Location: contact_queries.php");
        exit();
    } else {
        $error = "Failed to update query status: " . mysqli_error($conn);
    }
}

// Handle admin reply
if(isset($_POST['reply_query'])) {
    $query_id = mysqli_real_escape_string($conn, $_POST['query_id']);
    $admin_reply = mysqli_real_escape_string($conn, $_POST['admin_reply']);
    $reply_query = "UPDATE contact_queries SET admin_reply = '$admin_reply', status = 'resolved' WHERE id = '$query_id'";
    if(mysqli_query($conn, $reply_query)) {
        $_SESSION['success'] = "Reply sent successfully!";
        header("Location: contact_queries.php");
        exit();
    } else {
        $error = "Failed to send reply: " . mysqli_error($conn);
    }
}

// Handle search/filter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status_filter']) ? trim($_GET['status_filter']) : '';
// Build query with search and filter
$query = "SELECT * FROM contact_queries WHERE 1";
if ($search !== '') {
    $search_esc = mysqli_real_escape_string($conn, $search);
    $query .= " AND (name LIKE '%$search_esc%' OR email LIKE '%$search_esc%' OR subject LIKE '%$search_esc%' OR message LIKE '%$search_esc%')";
}
if ($status_filter !== '' && in_array($status_filter, ['pending','in_progress','resolved'])) {
    $query .= " AND status = '" . mysqli_real_escape_string($conn, $status_filter) . "'";
}
$query .= " ORDER BY created_at DESC";
$result = mysqli_query($conn, $query);
if($result === false) {
    die("Error fetching contact queries: " . mysqli_error($conn));
}
$queries = mysqli_fetch_all($result, MYSQLI_ASSOC);
mysqli_free_result($result);

$page_title = "Contact Queries";
include('includes/header.php');
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Contact Queries Management</h2>
    </div>
    <!-- Search Bar and Filter -->
    <form class="row g-3 mb-3 align-items-center" method="get" action="">
        <div class="col-md-4 d-flex align-items-center">
            <input type="text" class="form-control" name="search" placeholder="Search by Name, Email, Subject, Message..." value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit" class="btn btn-primary ms-2" title="Search" style="padding: 0.375rem 0.75rem;">
                <i class="fas fa-search"></i>
            </button>
        </div>
        <div class="col-md-3">
            <select class="form-select" name="status_filter">
                <option value="">All Statuses</option>
                <option value="pending" <?php if($status_filter==='pending') echo 'selected'; ?>>Pending</option>
                <option value="in_progress" <?php if($status_filter==='in_progress') echo 'selected'; ?>>In Progress</option>
                <option value="resolved" <?php if($status_filter==='resolved') echo 'selected'; ?>>Resolved</option>
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
                <div id="top-scroll-contact" style="overflow-x: auto; overflow-y: hidden; width: 100%;">
                    <div style="width: max-content; height: 1px;"></div>
                </div>
                <div id="table-scroll-contact" class="table-responsive">
                    <table class="table table-bordered table-hover" id="contact-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Subject</th>
                                <th>Message</th>
                                <th>Status</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if(count($queries) > 0): ?>
                            <?php foreach($queries as $query): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($query['name']); ?></td>
                                    <td><?php echo htmlspecialchars($query['email']); ?></td>
                                    <td><?php echo htmlspecialchars($query['subject']); ?></td>
                                    <td><?php echo htmlspecialchars($query['message']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo match($query['status']) {
                                                'pending' => 'warning',
                                                'in_progress' => 'info',
                                                'resolved' => 'success',
                                                default => 'secondary'
                                            };
                                        ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $query['status'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($query['created_at'])); ?></td>
                                    <td>
                                        <div class="d-flex gap-2">
                                            <button class="btn btn-sm" style="background:#D35400;color:white;" data-bs-toggle="modal" data-bs-target="#updateStatus<?php echo $query['id']; ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#viewContact<?php echo $query['id']; ?>">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" 
                                                    data-bs-target="#replyQuery<?php echo $query['id']; ?>">
                                                <i class="fas fa-reply"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteQuery<?php echo $query['id']; ?>">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            <!-- Delete Contact Query Modal -->
                                            <div class="modal fade" id="deleteQuery<?php echo $query['id']; ?>" tabindex="-1" aria-labelledby="deleteQueryLabel<?php echo $query['id']; ?>" aria-hidden="true">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title" id="deleteQueryLabel<?php echo $query['id']; ?>">Delete Contact Query</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <p>Are you sure you want to delete this contact query?</p>
                                                            <p class="text-danger">This action cannot be undone.</p>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <form method="post">
                                                                <input type="hidden" name="query_id" value="<?php echo $query['id']; ?>">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                <button type="submit" name="delete_query" class="btn btn-danger">Delete</button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>

                                <!-- Update Status Modal -->
                                <div class="modal fade" id="updateStatus<?php echo $query['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Update Query Status</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form method="post">
                                                <div class="modal-body">
                                                    <input type="hidden" name="query_id" value="<?php echo $query['id']; ?>">
                                                    <div class="mb-3">
                                                        <label class="form-label">Status</label>
                                                        <select class="form-select" name="status" required>
                                                            <option value="pending" <?php echo $query['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                            <option value="in_progress" <?php echo $query['status'] == 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                                            <option value="resolved" <?php echo $query['status'] == 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                    <button type="submit" name="update_status" class="btn btn-primary">Update Status</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <!-- Reply Modal -->
                                <div class="modal fade" id="replyQuery<?php echo $query['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Reply to Query</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form method="post">
                                                <div class="modal-body">
                                                    <input type="hidden" name="query_id" value="<?php echo $query['id']; ?>">
                                                    <div class="mb-3">
                                                        <label class="form-label">Reply</label>
                                                        <textarea class="form-control" name="admin_reply" rows="4" required><?php echo htmlspecialchars($query['admin_reply']); ?></textarea>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                    <button type="submit" name="reply_query" class="btn btn-success">Send Reply</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <!-- Contact Query View Modal -->
                                <div class="modal fade" id="viewContact<?php echo $query['id']; ?>" tabindex="-1" aria-labelledby="viewContactLabel<?php echo $query['id']; ?>" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered" style="max-width:500px;">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="viewContactLabel<?php echo $query['id']; ?>">Contact Query</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <ul class="list-group list-group-flush mb-3">
                                                    <li class="list-group-item"><strong>Name:</strong> <?php echo htmlspecialchars($query['name']); ?></li>
                                                    <li class="list-group-item"><strong>Email:</strong> <?php echo htmlspecialchars($query['email']); ?></li>
                                                    <li class="list-group-item"><strong>Subject:</strong> <?php echo htmlspecialchars($query['subject']); ?></li>
                                                    <li class="list-group-item"><strong>Message:</strong> <br><?php echo nl2br(htmlspecialchars($query['message'])); ?></li>
                                                    <li class="list-group-item"><strong>Status:</strong> <span class="badge bg-<?php 
                                                        echo match($query['status']) {
                                                            'pending' => 'warning',
                                                            'in_progress' => 'info',
                                                            'resolved' => 'success',
                                                            default => 'secondary'
                                                        };
                                                    ?>"><?php echo ucfirst(str_replace('_', ' ', $query['status'])); ?></span></li>
                                                    <li class="list-group-item"><strong>Created At:</strong> <?php echo date('M j, Y', strtotime($query['created_at'])); ?></li>
                                                    <?php if (!empty($query['admin_reply'])): ?>
                                                        <li class="list-group-item"><strong>Admin Reply:</strong> <br><?php echo nl2br(htmlspecialchars($query['admin_reply'])); ?></li>
                                                    <?php endif; ?>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center">No contact queries found</td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Sync top and bottom scrollbars for contact queries table
window.addEventListener('DOMContentLoaded', function() {
    var topScroll = document.getElementById('top-scroll-contact');
    var tableScroll = document.getElementById('table-scroll-contact');
    var table = document.getElementById('contact-table');
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

<?php include('includes/footer.php'); ?>
