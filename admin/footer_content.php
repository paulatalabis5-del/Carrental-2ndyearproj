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

// Check how many footer content rows exist
$footer_count_result = mysqli_query($conn, "SELECT COUNT(*) as count FROM footer_content");
$footer_count_row = mysqli_fetch_assoc($footer_count_result);
$footer_count = $footer_count_row ? (int)$footer_count_row['count'] : 0;

// Handle CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_footer'])) {
        // Prevent adding if already 1 row exists
        if ($footer_count >= 1) {
            $_SESSION['error'] = "Only one footer content entry is allowed. Please edit the existing entry.";
            header("Location: footer_content.php");
            exit();
        }

        $location = mysqli_real_escape_string($conn, $_POST['location']);
        $facebook = mysqli_real_escape_string($conn, $_POST['facebook']);
        $linkedin = mysqli_real_escape_string($conn, $_POST['linkedin']);
        $instagram = mysqli_real_escape_string($conn, $_POST['instagram']);
        $contact_number = mysqli_real_escape_string($conn, $_POST['contact_number']);
        $email = mysqli_real_escape_string($conn, $_POST['email']);
        $office_hours = mysqli_real_escape_string($conn, $_POST['office_hours']);
        
        $stmt = mysqli_prepare($conn, "INSERT INTO footer_content (location, facebook, linkedin, instagram, contact_number, email, office_hours) VALUES (?, ?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "sssssss", $location, $facebook, $linkedin, $instagram, $contact_number, $email, $office_hours);
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['success'] = "Footer content added successfully!";
        } else {
            $error = "Error adding footer content: " . mysqli_error($conn);
        }
        header("Location: footer_content.php");
        exit();
    }

    if (isset($_POST['edit_footer'])) {
        $id = (int)$_POST['id'];
        $location = mysqli_real_escape_string($conn, $_POST['location']);
        $facebook = mysqli_real_escape_string($conn, $_POST['facebook']);
        $linkedin = mysqli_real_escape_string($conn, $_POST['linkedin']);
        $instagram = mysqli_real_escape_string($conn, $_POST['instagram']);
        $contact_number = mysqli_real_escape_string($conn, $_POST['contact_number']);
        $email = mysqli_real_escape_string($conn, $_POST['email']);
        $office_hours = mysqli_real_escape_string($conn, $_POST['office_hours']);
        
        $stmt = mysqli_prepare($conn, "UPDATE footer_content SET location=?, facebook=?, linkedin=?, instagram=?, contact_number=?, email=?, office_hours=? WHERE id=?");
        mysqli_stmt_bind_param($stmt, "sssssssi", $location, $facebook, $linkedin, $instagram, $contact_number, $email, $office_hours, $id);
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['success'] = "Footer content updated successfully!";
        } else {
            $error = "Error updating footer content: " . mysqli_error($conn);
        }
        header("Location: footer_content.php");
        exit();
    }

    if (isset($_POST['delete_footer'])) {
        $id = (int)$_POST['id'];
        $stmt = mysqli_prepare($conn, "DELETE FROM footer_content WHERE id=?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['success'] = "Footer content deleted successfully!";
        } else {
            $error = "Error deleting footer content: " . mysqli_error($conn);
        }
        header("Location: footer_content.php");
        exit();
    }
}

$query = "SELECT * FROM footer_content ORDER BY id DESC";
$result = mysqli_query($conn, $query);
$footer_contents = mysqli_fetch_all($result, MYSQLI_ASSOC);

$page_title = "Footer Content Management";
include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Footer Content Management</h2>
        <?php if ($footer_count < 1): ?>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addFooterModal">
            <i class="fas fa-plus"></i> Add New
        </button>
        <?php endif; ?>
    </div>


    <?php if(isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= $_SESSION['success'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead>
                        <tr>
                            <th>Location</th>
                            <th>Facebook</th>
                            <th>LinkedIn</th>
                            <th>Instagram</th>
                            <th>Contact Number</th>
                            <th>Email</th>
                            <th>Office Hours</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($footer_contents as $content): ?>
                        <tr>
                            <td><?= htmlspecialchars($content['location']) ?></td>
                            <td><?= htmlspecialchars($content['facebook']) ?></td>
                            <td><?= htmlspecialchars($content['linkedin']) ?></td>
                            <td><?= htmlspecialchars($content['instagram']) ?></td>
                            <td><?= htmlspecialchars($content['contact_number']) ?></td>
                            <td><?= htmlspecialchars($content['email']) ?></td>
                            <td><?= htmlspecialchars($content['office_hours']) ?></td>
                            <td>
                                <button class="btn btn-sm btn-warning edit-btn"
                                    data-id="<?= $content['id'] ?>"
                                    data-location="<?= htmlspecialchars($content['location']) ?>"
                                    data-facebook="<?= htmlspecialchars($content['facebook']) ?>"
                                    data-linkedin="<?= htmlspecialchars($content['linkedin']) ?>"
                                    data-instagram="<?= htmlspecialchars($content['instagram']) ?>"
                                    data-contact_number="<?= htmlspecialchars($content['contact_number']) ?>"
                                    data-email="<?= htmlspecialchars($content['email']) ?>"
                                    data-office_hours="<?= htmlspecialchars($content['office_hours']) ?>"
                                    data-bs-toggle="modal" data-bs-target="#editFooterModal">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteFooter<?= $content['id'] ?>">
                                    <i class="fas fa-trash"></i>
                                </button>
                                <!-- Delete Footer Content Modal -->
                                <div class="modal fade" id="deleteFooter<?= $content['id'] ?>" tabindex="-1" aria-labelledby="deleteFooterLabel<?= $content['id'] ?>" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="deleteFooterLabel<?= $content['id'] ?>">Delete Footer Content</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <p>Are you sure you want to delete this footer content?</p>
                                                <p class="text-danger">This action cannot be undone.</p>
                                            </div>
                                            <div class="modal-footer">
                                                <form method="POST">
                                                    <input type="hidden" name="id" value="<?= $content['id'] ?>">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" name="delete_footer" class="btn btn-danger">Delete</button>
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

<!-- Add Modal -->
<div class="modal fade" id="addFooterModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Add Footer Content</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label>Location</label>
                        <input type="text" name="location" class="form-control" placeholder="Company Address" required>
                    </div>
                    <div class="mb-3">
                        <label>Facebook URL</label>
                        <input type="url" name="facebook" class="form-control" placeholder="https://facebook.com/yourpage">
                    </div>
                    <div class="mb-3">
                        <label>LinkedIn URL</label>
                        <input type="url" name="linkedin" class="form-control" placeholder="https://linkedin.com/yourpage">
                    </div>
                    <div class="mb-3">
                        <label>Instagram URL</label>
                        <input type="url" name="instagram" class="form-control" placeholder="https://instagram.com/yourpage">
                    </div>
                    <div class="mb-3">
                        <label>Contact Number</label>
                        <input type="text" name="contact_number" class="form-control" placeholder="09123456789">
                    </div>
                    <div class="mb-3">
                        <label>Email</label>
                        <input type="email" name="email" class="form-control" placeholder="email@example.com">
                    </div>
                    <div class="mb-3">
                        <label>Office Hours</label>
                        <input type="text" name="office_hours" class="form-control" placeholder="8:00 Am - 5:00 PM">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_footer" class="btn btn-primary">Add</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editFooterModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Footer Content</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label>Location</label>
                        <input type="text" name="location" id="edit_location" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Facebook URL</label>
                        <input type="url" name="facebook" id="edit_facebook" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label>LinkedIn URL</label>
                        <input type="url" name="linkedin" id="edit_linkedin" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label>Instagram URL</label>
                        <input type="url" name="instagram" id="edit_instagram" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label>Contact Number</label>
                        <input type="text" name="contact_number" id="edit_contact_number" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label>Email</label>
                        <input type="email" name="email" id="edit_email" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label>Office Hours</label>
                        <input type="text" name="office_hours" id="edit_office_hours" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_footer" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Edit modal population
document.querySelectorAll('.edit-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('edit_id').value = btn.dataset.id;
        document.getElementById('edit_location').value = btn.dataset.location;
        document.getElementById('edit_facebook').value = btn.dataset.facebook;
        document.getElementById('edit_linkedin').value = btn.dataset.linkedin;
        document.getElementById('edit_instagram').value = btn.dataset.instagram;
        document.getElementById('edit_contact_number').value = btn.dataset.contact_number;
        document.getElementById('edit_email').value = btn.dataset.email;
        document.getElementById('edit_office_hours').value = btn.dataset.office_hours;
    });
});
</script>

<?php include 'includes/footer.php'; ?>
