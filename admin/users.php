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

// Handle user deletion
if(isset($_POST['delete_user'])) {
    $user_id = mysqli_real_escape_string($conn, $_POST['user_id']);
    
    // Prevent deleting yourself
    if($user_id != $_SESSION['admin_id']) {
        $delete_query = "DELETE FROM users WHERE id = ?";
        $stmt = mysqli_prepare($conn, $delete_query);
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        
        if(mysqli_stmt_execute($stmt)) {
            $_SESSION['success'] = "User deleted successfully!";
        } else {
            $_SESSION['error'] = "Failed to delete user: " . mysqli_error($conn);
        }
        mysqli_stmt_close($stmt);
    } else {
        $_SESSION['error'] = "You cannot delete your own account!";
    }
    header("Location: users.php");
    exit();
}

// Handle user update
if(isset($_POST['update_user'])) {
    $user_id = mysqli_real_escape_string($conn, $_POST['user_id']);
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $is_admin = $_POST['role'] === 'admin' ? 1 : 0;
    
    // Check if email already exists for other users
    $check_email = "SELECT id FROM users WHERE email = ? AND id != ?";
    $stmt = mysqli_prepare($conn, $check_email);
    mysqli_stmt_bind_param($stmt, "si", $email, $user_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    
    if(mysqli_stmt_num_rows($stmt) > 0) {
        $_SESSION['error'] = "Email already exists!";
    } else {
        $update_query = "UPDATE users SET name = ?, email = ?, is_admin = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $update_query);
        mysqli_stmt_bind_param($stmt, "ssii", $name, $email, $is_admin, $user_id);
        
        if(mysqli_stmt_execute($stmt)) {
            $_SESSION['success'] = "User updated successfully!";
        } else {
            $_SESSION['error'] = "Failed to update user: " . mysqli_error($conn);
        }
    }
    mysqli_stmt_close($stmt);
    header("Location: users.php");
    exit();
}

// Handle freeze
if(isset($_POST['freeze_user'])) {
    $user_id = mysqli_real_escape_string($conn, $_POST['user_id']);
    $freeze_reason = mysqli_real_escape_string($conn, $_POST['freeze_reason']);
    // Prevent freezing yourself
    if($user_id == $_SESSION['admin_id']) {
        $_SESSION['error'] = "You cannot freeze your own account!";
        header("Location: users.php");
        exit();
    }
    $query = "UPDATE users SET is_frozen = 1, freeze_reason = ? WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "si", $freeze_reason, $user_id);
    if(mysqli_stmt_execute($stmt)) {
        $_SESSION['success'] = "User account frozen!";
    } else {
        $_SESSION['error'] = "Failed to freeze account: " . mysqli_error($conn);
    }
    header("Location: users.php");
    exit();
}

// Handle unfreeze
if(isset($_POST['unfreeze_user'])) {
    $user_id = mysqli_real_escape_string($conn, $_POST['user_id']);
    // Prevent unfreezing yourself
    if($user_id == $_SESSION['admin_id']) {
        $_SESSION['error'] = "You cannot unfreeze your own account from here!";
        header("Location: users.php");
        exit();
    }
    $query = "UPDATE users SET is_frozen = 0, freeze_reason = NULL WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    if(mysqli_stmt_execute($stmt)) {
        $_SESSION['success'] = "User account unfrozen!";
    } else {
        $_SESSION['error'] = "Failed to unfreeze account: " . mysqli_error($conn);
    }
    header("Location: users.php");
    exit();
}

// Handle search/filter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$role_filter = isset($_GET['role_filter']) ? trim($_GET['role_filter']) : '';
// Build query with search and filter
$query = "
    SELECT u.*, 
           CASE WHEN u.is_admin = 1 THEN 'admin' ELSE 'user' END as role,
           COUNT(DISTINCT b.id) as booking_count,
           COUNT(DISTINCT t.id) as testimonial_count
    FROM users u
    LEFT JOIN bookings b ON u.id = b.user_id
    LEFT JOIN testimonials t ON u.id = t.user_id
    WHERE 1";
if ($search !== '') {
    $search_esc = mysqli_real_escape_string($conn, $search);
    $query .= " AND (u.name LIKE '%$search_esc%' OR u.email LIKE '%$search_esc%' OR u.phone LIKE '%$search_esc%' OR u.address LIKE '%$search_esc%')";
}
if ($role_filter !== '') {
    if ($role_filter === 'admin') {
        $query .= " AND u.is_admin = 1";
    } elseif ($role_filter === 'user') {
        $query .= " AND u.is_admin = 0";
    }
}
$query .= " GROUP BY u.id ORDER BY u.created_at DESC";
$result = mysqli_query($conn, $query);
if(!$result) {
    die("Error fetching users: " . mysqli_error($conn));
}

$page_title = "User Management";
include('includes/header.php');
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">User Management</h2>
    </div>
    <!-- Search Bar and Filter -->
    <form class="row g-3 mb-3 align-items-center" method="get" action="" id="filterForm">
        <div class="col-md-4 d-flex align-items-center">
            <input type="text" class="form-control" name="search" placeholder="Search by Name, Email, Phone, Address..." value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit" class="btn btn-primary ms-2" title="Search" style="padding: 0.375rem 0.75rem;">
                <i class="fas fa-search"></i>
            </button>
        </div>
        <div class="col-md-2">
            <select class="form-select" name="role_filter" id="roleFilter">
                <option value="">All Roles</option>
                <option value="admin" <?php if($role_filter==='admin') echo 'selected'; ?>>Admin</option>
                <option value="user" <?php if($role_filter==='user') echo 'selected'; ?>>User</option>
            </select>
        </div>
    </form>

    <?php if(isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php 
            echo $_SESSION['error'];
            unset($_SESSION['error']);
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if(isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php 
            echo $_SESSION['success'];
            unset($_SESSION['success']);
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

<script>
    document.getElementById('roleFilter').addEventListener('change', function() {
        document.getElementById('filterForm').submit();
    });
</script>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <!-- Top Scrollbar -->
            <div id="top-scroll-users" style="overflow-x: auto; overflow-y: hidden; width: 100%;">
                <div style="width: max-content; height: 1px;"></div>
            </div>
            <div id="table-scroll-users" class="table-responsive">
                <table class="table table-bordered table-hover mb-0" id="users-table">
                    <thead class="bg-light">
                        <tr>
                            <th class="px-4 py-3">Profile</th>
                            <th class="px-4 py-3">Name</th>
                            <th class="px-4 py-3">Number</th>
                            <th class="px-4 py-3">Address</th>
                            <th class="px-4 py-3">Email</th>
                            <th class="px-4 py-3">Role</th>
                            <th class="px-4 py-3">Bookings</th>
                            <th class="px-4 py-3">Testimonials</th>
                            <th class="px-4 py-3">Joined</th>
                            <th class="px-4 py-3 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while($user = mysqli_fetch_assoc($result)): ?>
                        <tr>
                            <td class="px-4 py-3 text-center">
                                <img src="<?php echo !empty($user['profile_picture']) ? '../' . htmlspecialchars($user['profile_picture']) : 'https://ui-avatars.com/api/?name=' . urlencode($user['name']); ?>" alt="Profile Picture" class="rounded-circle" style="width:40px;height:40px;object-fit:cover;">
                            </td>
                            <td class="px-4 py-3"><?php echo htmlspecialchars($user['name']); ?></td>
                            <td class="px-4 py-3"><?php echo htmlspecialchars($user['phone'] ?? ''); ?></td>
                            <td class="px-4 py-3"><?php echo htmlspecialchars($user['address'] ?? ''); ?></td>
                            <td class="px-4 py-3"><?php echo htmlspecialchars($user['email']); ?></td>
                            <td class="px-4 py-3">
                                <span class="badge <?php echo $user['is_admin'] == 1 ? 'bg-danger' : 'bg-primary'; ?>">
                                    <?php echo $user['is_admin'] == 1 ? 'Admin' : 'User'; ?>
                                </span>
                            </td>
                            <td class="px-4 py-3"><?php echo $user['booking_count']; ?></td>
                            <td class="px-4 py-3"><?php echo $user['testimonial_count']; ?></td>
                            <td class="px-4 py-3"><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                            <td class="px-4 py-3">
                                <div class="d-flex justify-content-center align-items-center gap-2">
                                    <?php if($user['is_admin'] == 0): ?>
                                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" 
                                            data-bs-target="#editUser<?php echo $user['id']; ?>">
                                            <i class="fas fa-edit"></i>
                                    </button>
                                    <?php endif; ?>
                                    <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#viewUser<?php echo $user['id']; ?>">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <?php if($user['id'] != $_SESSION['admin_id']): ?>
                                        <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" 
                                                data-bs-target="#deleteUser<?php echo $user['id']; ?>">
                                                <i class="fas fa-trash"></i>
                                        </button>
                                        <!-- Freeze/Unfreeze Button -->
                                        <?php if($user['is_frozen']): ?>
                                            <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#unfreezeUser<?php echo $user['id']; ?>">
                                                <i class="fas fa-unlock"></i>
                                            </button>
                                        <?php else: ?>
                                            <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#freezeUser<?php echo $user['id']; ?>">
                                                <i class="fas fa-lock"></i>
                                            </button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>

                        <!-- Edit User Modal -->
                        <div class="modal fade" id="editUser<?php echo $user['id']; ?>" tabindex="-1">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Edit User</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <form method="post" id="editForm<?php echo $user['id']; ?>" onsubmit="return confirmAdminRole(<?php echo $user['id']; ?>);">
                                        <div class="modal-body">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            
                                            <div class="mb-3">
                                                <label for="name<?php echo $user['id']; ?>" class="form-label">Name</label>
                                                <input type="text" class="form-control" id="name<?php echo $user['id']; ?>" name="name" 
                                                       value="<?php echo htmlspecialchars($user['name']); ?>" required>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="email<?php echo $user['id']; ?>" class="form-label">Email</label>
                                                <input type="email" class="form-control" id="email<?php echo $user['id']; ?>" name="email" 
                                                       value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="role<?php echo $user['id']; ?>" class="form-label">Role</label>
                                                <select class="form-select" id="role<?php echo $user['id']; ?>" name="role" required
                                                        data-original-role="<?php echo $user['is_admin'] == 1 ? 'admin' : 'user'; ?>">
                                                    <option value="user" <?php echo $user['is_admin'] == 0 ? 'selected' : ''; ?>>User</option>
                                                    <option value="admin" <?php echo $user['is_admin'] == 1 ? 'selected' : ''; ?>>Admin</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" name="update_user" class="btn btn-primary">Save Changes</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Admin Role Confirmation Modal -->
                        <div class="modal fade" id="confirmAdminRole<?php echo $user['id']; ?>" tabindex="-1">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Confirm Role Change</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="alert alert-warning">
                                            <i class="fas fa-exclamation-triangle me-2"></i>
                                            Are you sure you want to change this user's role to Admin?
                                        </div>
                                        <p class="mb-0">This will give them full administrative access to the system.</p>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" onclick="cancelAdminChange(<?php echo $user['id']; ?>)">Cancel</button>
                                        <button type="button" class="btn btn-primary" onclick="confirmAdminChange(<?php echo $user['id']; ?>)">Confirm</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Delete User Modal -->
                        <div class="modal fade" id="deleteUser<?php echo $user['id']; ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Delete User</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <p class="mb-2">Are you sure you want to delete this user?</p>
                                        <p class="text-danger mb-0">This action cannot be undone. All associated bookings and testimonials will also be deleted.</p>
                                    </div>
                                    <div class="modal-footer">
                                        <form method="post">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" name="delete_user" class="btn btn-danger">Delete</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Freeze User Modal -->
                        <div class="modal fade" id="freezeUser<?php echo $user['id']; ?>" tabindex="-1">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content">
                                    <form method="post">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Freeze Account</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <div class="mb-3">
                                                <label class="form-label">Reason for Freezing</label>
                                                <textarea class="form-control" name="freeze_reason" required></textarea>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" name="freeze_user" class="btn btn-warning">Freeze</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Unfreeze User Modal -->
                        <div class="modal fade" id="unfreezeUser<?php echo $user['id']; ?>" tabindex="-1">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content">
                                    <form method="post">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Unfreeze Account</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <p>Are you sure you want to unfreeze this account?</p>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" name="unfreeze_user" class="btn btn-success">Unfreeze</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- User Profile View Modal -->
                        <div class="modal fade" id="viewUser<?php echo $user['id']; ?>" tabindex="-1" aria-labelledby="viewUserLabel<?php echo $user['id']; ?>" aria-hidden="true">
                          <div class="modal-dialog modal-dialog-centered" style="max-width:500px;">
                            <div class="modal-content">
                              <div class="modal-header">
                                <h5 class="modal-title" id="viewUserLabel<?php echo $user['id']; ?>">User Profile</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                              </div>
                              <div class="modal-body">
                                <div class="text-center mb-3">
                                  <img src="<?php echo !empty($user['profile_picture']) ? '../' . htmlspecialchars($user['profile_picture']) : 'https://ui-avatars.com/api/?name=' . urlencode($user['name']); ?>" alt="Profile Picture" class="rounded-circle mb-2" style="width:80px;height:80px;object-fit:cover;">
                                  <h5 class="mb-0 mt-2"><?php echo htmlspecialchars($user['name']); ?></h5>
                                  <span class="badge <?php echo $user['is_admin'] == 1 ? 'bg-danger' : 'bg-primary'; ?>">
                                    <?php echo $user['is_admin'] == 1 ? 'Admin' : 'User'; ?>
                                  </span>
                                </div>
                                <ul class="list-group list-group-flush mb-3">
                                  <li class="list-group-item"><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></li>
                                  <li class="list-group-item"><strong>Phone:</strong> <?php echo htmlspecialchars($user['phone'] ?? ''); ?></li>
                                  <li class="list-group-item"><strong>Address:</strong> <?php echo htmlspecialchars($user['address'] ?? ''); ?></li>
                                  <li class="list-group-item"><strong>Joined:</strong> <?php echo date('M j, Y', strtotime($user['created_at'])); ?></li>
                                </ul>
                                <!-- Removed Testimonials count as per request -->
                              </div>
                            </div>
                          </div>
                        </div>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php 
mysqli_free_result($result);
include('includes/footer.php'); 
?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function confirmAdminRole(userId) {
        const roleSelect = document.getElementById('role' + userId);
        const originalRole = roleSelect.getAttribute('data-original-role');
        
        // If changing to admin role and wasn't admin before
        if (roleSelect.value === 'admin' && originalRole !== 'admin') {
            // Use simple confirm dialog instead of modal
            if (confirm("Are you sure you want to change this user's role to Admin? This will give them full administrative access to the system.")) {
                return true; // Allow form submission
            } else {
                // Reset to original value
                roleSelect.value = originalRole;
                return false; // Prevent form submission
            }
        }
        return true; // Allow form submission for other cases
    }

    function cancelAdminChange(userId) {
        // Reset to original value
        const roleSelect = document.getElementById('role' + userId);
        roleSelect.value = 'user';
        
        // Hide confirmation modal
        const confirmModal = bootstrap.Modal.getInstance(document.getElementById('confirmAdminRole' + userId));
        confirmModal.hide();
    }

    function confirmAdminChange(userId) {
        // Hide confirmation modal
        const confirmModal = bootstrap.Modal.getInstance(document.getElementById('confirmAdminRole' + userId));
        confirmModal.hide();
        
        // Submit the form
        document.getElementById('editForm' + userId).submit();
    }

    // Sync top and bottom scrollbars for users table
    window.addEventListener('DOMContentLoaded', function() {
        var topScroll = document.getElementById('top-scroll-users');
        var tableScroll = document.getElementById('table-scroll-users');
        var table = document.getElementById('users-table');
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
</body>
</html> 