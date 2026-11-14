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

// Handle testimonial deletion
if(isset($_POST['delete_testimonial'])) {
    $testimonial_id = mysqli_real_escape_string($conn, $_POST['testimonial_id']);
    $delete_query = "DELETE FROM testimonials WHERE id = '$testimonial_id'";
    if(mysqli_query($conn, $delete_query)) {
        $_SESSION['success'] = "Testimonial deleted successfully!";
        header("Location: testimonials.php");
        exit();
    } else {
        $error = "Failed to delete testimonial: " . mysqli_error($conn);
    }
}

// Add hide/unhide logic at the top
if(isset($_POST['toggle_hide'])) {
    $testimonial_id = (int)$_POST['testimonial_id'];
    $current_hidden = (int)$_POST['current_hidden'];
    $new_hidden = $current_hidden ? 0 : 1;
    $query = "UPDATE testimonials SET hidden = $new_hidden WHERE id = $testimonial_id";
    if(mysqli_query($conn, $query)) {
        $_SESSION['success'] = $new_hidden ? 'Testimonial hidden.' : 'Testimonial unhidden.';
    } else {
        $_SESSION['error'] = 'Failed to update testimonial visibility.';
    }
    header('Location: testimonials.php');
    exit();
}

// Handle search/filter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$rating_filter = isset($_GET['rating_filter']) ? trim($_GET['rating_filter']) : '';
// Build query with search and filter
$query = "
    SELECT t.*, u.name as user_name, u.email as user_email
    FROM testimonials t
    LEFT JOIN users u ON t.user_id = u.id 
    WHERE 1";
if ($search !== '') {
    $search_esc = mysqli_real_escape_string($conn, $search);
    $query .= " AND (u.name LIKE '%$search_esc%' OR u.email LIKE '%$search_esc%' OR t.content LIKE '%$search_esc%')";
}
if ($rating_filter !== '' && in_array($rating_filter, ['1','2','3','4','5'])) {
    $query .= " AND t.rating = " . intval($rating_filter);
}
$query .= " ORDER BY t.created_at DESC";
$result = mysqli_query($conn, $query);
if($result === false) {
    die("Error fetching testimonials: " . mysqli_error($conn));
}
$testimonials = mysqli_fetch_all($result, MYSQLI_ASSOC);
mysqli_free_result($result);

$page_title = "Testimonials";
include('includes/header.php');
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">Testimonials Management</h2>
    </div>

    <!-- Search Bar and Filter -->
    <form class="row g-3 mb-3 align-items-center" method="get" action="" id="filterForm">
        <div class="col-md-4 d-flex align-items-center">
            <input type="text" class="form-control" name="search" placeholder="Search by User, Email, Message..." value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit" class="btn btn-primary ms-2" title="Search" style="padding: 0.375rem 0.75rem;">
                <i class="fas fa-search"></i>
            </button>
        </div>
        <div class="col-md-2">
            <select class="form-select" name="rating_filter" id="ratingFilter">
                <option value="">All Ratings</option>
                <option value="5" <?php if($rating_filter==='5') echo 'selected'; ?>>5 Stars</option>
                <option value="4" <?php if($rating_filter==='4') echo 'selected'; ?>>4 Stars</option>
                <option value="3" <?php if($rating_filter==='3') echo 'selected'; ?>>3 Stars</option>
                <option value="2" <?php if($rating_filter==='2') echo 'selected'; ?>>2 Stars</option>
                <option value="1" <?php if($rating_filter==='1') echo 'selected'; ?>>1 Star</option>
            </select>
        </div>
    </form>

    <?php if(isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
            <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <?php if(isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
            <?php 
            echo $_SESSION['success'];
            unset($_SESSION['success']);
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

<script>
    document.getElementById('ratingFilter').addEventListener('change', function() {
        document.getElementById('filterForm').submit();
    });
</script>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <!-- Top Scrollbar -->
            <div id="top-scroll-testimonials" style="overflow-x: auto; overflow-y: hidden; width: 100%;">
                <div style="width: max-content; height: 1px;"></div>
            </div>
            <div id="table-scroll-testimonials" class="table-responsive">
                <table class="table table-bordered table-hover mb-0" id="testimonials-table">
                    <thead class="bg-light">
                        <tr>
                            <th class="px-4 py-3">User</th>
                            <th class="px-4 py-3">Email</th>
                            <th class="px-4 py-3">Rating</th>
                            <th class="px-4 py-3">Message</th>
                            <th class="px-4 py-3">Created At</th>
                            <th class="px-4 py-3 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if(count($testimonials) > 0): ?>
                        <?php foreach($testimonials as $testimonial): ?>
                            <tr>
                                <td class="px-4 py-3"><?php echo htmlspecialchars($testimonial['user_name']); ?></td>
                                <td class="px-4 py-3"><?php echo htmlspecialchars($testimonial['user_email']); ?></td>
                                <td class="px-4 py-3">
                                    <?php 
                                    for($i = 1; $i <= 5; $i++) {
                                        echo $i <= $testimonial['rating'] ? 
                                            '<i class="fas fa-star text-warning"></i>' : 
                                            '<i class="far fa-star text-warning"></i>';
                                    }
                                    ?>
                                </td>
                                <td class="px-4 py-3"><?php echo htmlspecialchars($testimonial['content']); ?></td>
                                <td class="px-4 py-3"><?php echo date('M j, Y', strtotime($testimonial['created_at'])); ?></td>
                                <td class="px-4 py-3 text-center">
                                    <form method="post" style="display:inline;">
                                        <input type="hidden" name="testimonial_id" value="<?php echo $testimonial['id']; ?>">
                                        <input type="hidden" name="current_hidden" value="<?php echo $testimonial['hidden']; ?>">
                                        <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#viewTestimonial<?php echo $testimonial['id']; ?>">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button type="submit" name="toggle_hide" class="btn btn-sm btn-warning" title="<?php echo $testimonial['hidden'] ? 'Unhide' : 'Hide'; ?>">
                                            <i class="fas fa-eye<?php echo $testimonial['hidden'] ? '-slash' : ''; ?>"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <!-- Testimonial View Modal -->
                            <div class="modal fade" id="viewTestimonial<?php echo $testimonial['id']; ?>" tabindex="-1" aria-labelledby="viewTestimonialLabel<?php echo $testimonial['id']; ?>" aria-hidden="true">
                              <div class="modal-dialog modal-dialog-centered" style="max-width:500px;">
                                <div class="modal-content">
                                  <div class="modal-header">
                                    <h5 class="modal-title" id="viewTestimonialLabel<?php echo $testimonial['id']; ?>">Testimonial</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                  </div>
                                  <div class="modal-body">
                                    <ul class="list-group list-group-flush mb-3">
                                      <li class="list-group-item"><strong>User:</strong> <?php echo htmlspecialchars($testimonial['user_name']); ?></li>
                                      <li class="list-group-item"><strong>Email:</strong> <?php echo htmlspecialchars($testimonial['user_email']); ?></li>
                                      <li class="list-group-item"><strong>Rating:</strong> 
                                        <?php for($i = 1; $i <= 5; $i++) {
                                            echo $i <= $testimonial['rating'] ? '<i class=\'fas fa-star text-warning\'></i>' : '<i class=\'far fa-star text-warning\'></i>';
                                        } ?>
                                      </li>
                                      <li class="list-group-item"><strong>Message:</strong> <br><?php echo nl2br(htmlspecialchars($testimonial['content'])); ?></li>
                                      <li class="list-group-item"><strong>Created At:</strong> <?php echo date('M j, Y', strtotime($testimonial['created_at'])); ?></li>
                                      <li class="list-group-item"><strong>Status:</strong> <span class="badge bg-<?php echo $testimonial['hidden'] ? 'secondary' : 'success'; ?>"><?php echo $testimonial['hidden'] ? 'Hidden' : 'Visible'; ?></span></li>
                                    </ul>
                                  </div>
                                </div>
                              </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-4">No testimonials found</td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
// Sync top and bottom scrollbars for testimonials table
window.addEventListener('DOMContentLoaded', function() {
    var topScroll = document.getElementById('top-scroll-testimonials');
    var tableScroll = document.getElementById('table-scroll-testimonials');
    var table = document.getElementById('testimonials-table');
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