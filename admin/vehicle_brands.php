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

// Handle brand actions
if(isset($_POST['add_brand'])) {
    $name = trim($_POST['name']);
    
    if(!empty($name)) {
        // Capitalize first letter and lowercase the rest
        $name = ucfirst(strtolower($name));
        $name = mysqli_real_escape_string($conn, $name);
        // Check if brand already exists (case-insensitive)
        $check_query = "SELECT id FROM vehicle_brands WHERE LOWER(name) = LOWER('$name')";
        $check_result = mysqli_query($conn, $check_query);
        if(mysqli_num_rows($check_result) > 0) {
            $error = "Brand already exists";
        } else {
            $query = "INSERT INTO vehicle_brands (name) VALUES ('$name')";
            if(mysqli_query($conn, $query)) {
                $_SESSION['success'] = "Brand added successfully!";
            } else {
                $error = "Failed to add brand: " . mysqli_error($conn);
            }
        }
    } else {
        $error = "Brand name is required";
    }
}

if(isset($_POST['edit_brand'])) {
    $id = mysqli_real_escape_string($conn, $_POST['brand_id']);
    $name = trim($_POST['name']);
    
    if(!empty($name)) {
        $name = mysqli_real_escape_string($conn, $name);
        $query = "UPDATE vehicle_brands SET name = '$name' WHERE id = '$id'";
        if(mysqli_query($conn, $query)) {
            $_SESSION['success'] = "Brand updated successfully!";
        } else {
            $error = "Failed to update brand: " . mysqli_error($conn);
        }
    } else {
        $error = "Brand name is required";
    }
}

if(isset($_POST['delete_brand'])) {
    $id = mysqli_real_escape_string($conn, $_POST['brand_id']);
    
    // Check if brand has vehicles
    $check_query = "SELECT COUNT(*) as count FROM vehicles WHERE brand_id = '$id'";
    $result = mysqli_query($conn, $check_query);
    $row = mysqli_fetch_assoc($result);
    $has_vehicles = $row['count'] > 0;
    
    if(!$has_vehicles) {
        $query = "DELETE FROM vehicle_brands WHERE id = '$id'";
        if(mysqli_query($conn, $query)) {
            $_SESSION['success'] = "Brand deleted successfully!";
        } else {
            $error = "Failed to delete brand: " . mysqli_error($conn);
        }
    } else {
        $error = "Cannot delete brand that has vehicles assigned to it";
    }
}

// Handle search/filter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
// Build query with search
$query = "
    SELECT vb.*, COUNT(v.id) as vehicle_count 
    FROM vehicle_brands vb 
    LEFT JOIN vehicles v ON vb.id = v.brand_id 
    WHERE 1";
if ($search !== '') {
    $search_esc = mysqli_real_escape_string($conn, $search);
    $query .= " AND vb.name LIKE '%$search_esc%'";
}
$query .= " GROUP BY vb.id ORDER BY vb.name";
$result = mysqli_query($conn, $query);
if($result === false) {
    die("Error fetching brands: " . mysqli_error($conn));
}
$brands = mysqli_fetch_all($result, MYSQLI_ASSOC);
mysqli_free_result($result);

// Set page title for the header
$page_title = "Vehicle Brands";

// Include header
include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Vehicle Brands</h2>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addBrandModal">
            <i class="fas fa-plus"></i> Add New Brand
        </button>
    </div>
    <!-- Search Bar -->
    <form class="row g-3 mb-3 align-items-center" method="get" action="">
        <div class="col-md-4 d-flex align-items-center">
            <input type="text" class="form-control" name="search" placeholder="Search by Brand Name..." value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit" class="btn btn-primary ms-2" title="Search" style="padding: 0.375rem 0.75rem;">
                <i class="fas fa-search"></i>
            </button>
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
            <div id="top-scroll-brands" style="overflow-x: auto; overflow-y: hidden; width: 100%;">
                <div style="width: max-content; height: 1px;"></div>
            </div>
            <div id="table-scroll-brands" class="table-responsive">
                <table class="table table-bordered table-hover" id="brands-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Brand Name</th>
                            <th>Vehicles</th>
                            <th>Created At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($brands) > 0): ?>
                            <?php foreach($brands as $brand): ?>
                                <tr>
                                    <td><?php echo $brand['id']; ?></td>
                                    <td><?php echo htmlspecialchars($brand['name']); ?></td>
                                    <td><?php echo $brand['vehicle_count']; ?></td>
                                    <td><?php echo date('M d, Y', strtotime($brand['created_at'])); ?></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-primary edit-brand" 
                                                data-id="<?php echo $brand['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($brand['name']); ?>"
                                                data-bs-toggle="modal" data-bs-target="#editBrandModal">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php if($brand['vehicle_count'] == 0): ?>
                                            <button type="button" class="btn btn-sm btn-danger delete-brand" 
                                                    data-id="<?php echo $brand['id']; ?>"
                                                    data-name="<?php echo htmlspecialchars($brand['name']); ?>"
                                                    data-bs-toggle="modal" data-bs-target="#deleteBrandModal">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center">No brands found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Brand Modal -->
<div class="modal fade" id="addBrandModal" tabindex="-1" aria-labelledby="addBrandModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addBrandModalLabel">Add New Brand</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="name" class="form-label">Brand Name</label>
                        <input type="text" class="form-control" id="name" name="name" maxlength="20" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_brand" class="btn btn-primary">Add Brand</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Brand Modal -->
<div class="modal fade" id="editBrandModal" tabindex="-1" aria-labelledby="editBrandModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editBrandModalLabel">Edit Brand</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" id="edit_brand_id" name="brand_id">
                    <div class="mb-3">
                        <label for="edit_name" class="form-label">Brand Name</label>
                        <input type="text" class="form-control" id="edit_name" name="name" maxlength="20" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_brand" class="btn btn-primary">Update Brand</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Brand Modal -->
<div class="modal fade" id="deleteBrandModal" tabindex="-1" aria-labelledby="deleteBrandModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteBrandModalLabel">Delete Brand</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete the brand: <strong id="delete_brand_name"></strong>?</p>
                <p class="text-danger">This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <form method="POST" action="">
                    <input type="hidden" id="delete_brand_id" name="brand_id">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="delete_brand" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    // Edit brand functionality
    document.querySelectorAll('.edit-brand').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const name = this.getAttribute('data-name');
            
            document.getElementById('edit_brand_id').value = id;
            document.getElementById('edit_name').value = name;
        });
    });
    
    // Delete brand functionality
    document.querySelectorAll('.delete-brand').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const name = this.getAttribute('data-name');
            
            document.getElementById('delete_brand_id').value = id;
            document.getElementById('delete_brand_name').textContent = name;
        });
    });

    // Sync top and bottom scrollbars for brands table
    window.addEventListener('DOMContentLoaded', function() {
        var topScroll = document.getElementById('top-scroll-brands');
        var tableScroll = document.getElementById('table-scroll-brands');
        var table = document.getElementById('brands-table');
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