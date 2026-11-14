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

// Handle Add
if (isset($_POST['add'])) {
    $icon = trim($_POST['icon']);
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $front_description = trim($_POST['front_description']);
    $stmt = mysqli_prepare($conn, "INSERT INTO pickup_instructions (icon, title, description, front_description) VALUES (?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "ssss", $icon, $title, $description, $front_description);
    mysqli_stmt_execute($stmt);
    header("Location: pickup_instructions.php");
    exit();
}

// Handle Edit
if (isset($_POST['edit'])) {
    $id = (int)$_POST['id'];
    $icon = trim($_POST['icon']);
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $front_description = trim($_POST['front_description']);
    $stmt = mysqli_prepare($conn, "UPDATE pickup_instructions SET icon=?, title=?, description=?, front_description=? WHERE id=?");
    mysqli_stmt_bind_param($stmt, "ssssi", $icon, $title, $description, $front_description, $id);
    mysqli_stmt_execute($stmt);
    header("Location: pickup_instructions.php");
    exit();
}

// Handle Delete
if (isset($_POST['delete'])) {
    $id = (int)$_POST['id'];
    $stmt = mysqli_prepare($conn, "DELETE FROM pickup_instructions WHERE id=?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    header("Location: pickup_instructions.php");
    exit();
}

// Handle search/filter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
// Build query with search
$query = "SELECT * FROM pickup_instructions WHERE 1";
if ($search !== '') {
    $search_esc = mysqli_real_escape_string($conn, $search);
    $query .= " AND (icon LIKE '%$search_esc%' OR title LIKE '%$search_esc%' OR description LIKE '%$search_esc%')";
}
$query .= " ORDER BY id DESC";
$result = mysqli_query($conn, $query);
$instructions = mysqli_fetch_all($result, MYSQLI_ASSOC);

// For editing
$edit_instruction = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $res = mysqli_query($conn, "SELECT * FROM pickup_instructions WHERE id=$edit_id");
    $edit_instruction = mysqli_fetch_assoc($res);
}

$page_title = "Pickup Instructions";
include 'includes/header.php'; // This includes sidebar and toggle buttons
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Pickup Instructions</h2>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addInstructionModal">
            <i class="fas fa-plus"></i> Add New Instruction
        </button>
    </div>
    <!-- Search Bar -->
    <form class="row g-3 mb-3 align-items-center" method="get" action="">
        <div class="col-md-4 d-flex align-items-center">
            <input type="text" class="form-control" name="search" placeholder="Search by Icon, Title, Description..." value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit" class="btn btn-primary ms-2" title="Search" style="padding: 0.375rem 0.75rem;">
                <i class="fas fa-search"></i>
            </button>
        </div>
    </form>

    <?php if(isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if(isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <!-- Top Scrollbar -->
            <div id="top-scroll-instructions" style="overflow-x: auto; overflow-y: hidden; width: 100%;">
                <div style="width: max-content; height: 1px;"></div>
            </div>
            <div id="table-scroll-instructions" class="table-responsive">
                <table class="table table-bordered table-hover align-middle" id="instructions-table">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Icon</th>
                            <th>Title</th>
                            <th>Description</th>
                            <th>Front Description</th>
                            <th style="width: 140px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($instructions as $inst): ?>
                        <tr>
                            <td><?php echo $inst['id']; ?></td>
                            <td><i class="<?php echo htmlspecialchars($inst['icon']); ?>" style="font-size:2rem;color:#D35400"></i></td>
                            <td><?php echo htmlspecialchars($inst['title']); ?></td>
                            <td><?php echo htmlspecialchars($inst['description']); ?></td>
                            <td><?php echo htmlspecialchars($inst['front_description'] ?? ''); ?></td>
                            <td>
                                <button class="btn btn-sm btn-warning me-1" data-bs-toggle="modal" data-bs-target="#editInstructionModal<?php echo $inst['id']; ?>">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form method="post" style="display:inline" onsubmit="return confirm('Delete this instruction?');">
                                    <input type="hidden" name="id" value="<?php echo $inst['id']; ?>">
                                    <button type="submit" name="delete" class="btn btn-sm btn-danger">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <!-- Edit Modal for this instruction -->
                        <div class="modal fade" id="editInstructionModal<?php echo $inst['id']; ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <form method="post">
                                        <input type="hidden" name="id" value="<?php echo $inst['id']; ?>">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Edit Instruction</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="mb-3">
                                                <label class="form-label">Icon (Remixicon class)</label>
                                                <input type="text" name="icon" class="form-control" required value="<?php echo htmlspecialchars($inst['icon']); ?>">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Title</label>
                                                <input type="text" name="title" class="form-control" required value="<?php echo htmlspecialchars($inst['title']); ?>">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Description</label>
                                                <textarea name="description" class="form-control" required><?php echo htmlspecialchars($inst['description']); ?></textarea>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Front Description</label>
                                                <input type="text" name="front_description" class="form-control" required value="<?php echo htmlspecialchars($inst['front_description'] ?? ''); ?>">
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" name="edit" class="btn btn-primary">Update</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($instructions)): ?>
                        <tr><td colspan="5" class="text-center text-muted">No instructions yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addInstructionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Instruction</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Icon (Remixicon class)</label>
                        <input type="text" name="icon" class="form-control" required placeholder="e.g. ri-id-card-line">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Title</label>
                        <input type="text" name="title" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Front Description</label>
                        <input type="text" name="front_description" class="form-control" required placeholder="Short summary for front display">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add" class="btn btn-primary">Add</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Sync top and bottom scrollbars for instructions table
window.addEventListener('DOMContentLoaded', function() {
    var topScroll = document.getElementById('top-scroll-instructions');
    var tableScroll = document.getElementById('table-scroll-instructions');
    var table = document.getElementById('instructions-table');
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
