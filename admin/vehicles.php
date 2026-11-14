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

// Create uploads directory if it doesn't exist
$upload_dir = "../uploads/vehicles/";
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Get all vehicle brands for the dropdown
$query = "SELECT * FROM vehicle_brands ORDER BY name";
$result = mysqli_query($conn, $query);
if($result === false) {
    die("Error fetching brands: " . mysqli_error($conn));
}
$brands = mysqli_fetch_all($result, MYSQLI_ASSOC);
mysqli_free_result($result);

// Handle search/filter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status_filter']) ? trim($_GET['status_filter']) : '';

// Build query with search and filter
$query = "SELECT v.*, vb.name as brand_name 
    FROM vehicles v
    JOIN vehicle_brands vb ON v.brand_id = vb.id
    WHERE 1";
if ($search !== '') {
    $search_esc = mysqli_real_escape_string($conn, $search);
    $query .= " AND (vb.name LIKE '%$search_esc%' OR v.model LIKE '%$search_esc%' OR v.id LIKE '%$search_esc%')";
}
if ($status_filter !== '') {
    $status_esc = mysqli_real_escape_string($conn, $status_filter);
    $query .= " AND v.status = '$status_esc'";
}
$query .= " ORDER BY v.id DESC";
$result = mysqli_query($conn, $query);
if($result === false) {
    die("Error fetching vehicles: " . mysqli_error($conn));
}
$vehicles = mysqli_fetch_all($result, MYSQLI_ASSOC);
mysqli_free_result($result);

// Handle vehicle actions
if(isset($_POST['add_vehicle'])) {
    $brand_id = $_POST['brand_id'];
    $model = trim($_POST['model']);
    $year = (int)$_POST['year'];
    $daily_rate = (float)$_POST['daily_rate'];
    $specifications = trim($_POST['specifications']);
    $image_path = '';
    $error = '';
    $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
    
    // Handle image upload
    if(isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        $filename = $_FILES['image']['name'];
        $file_ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if(!in_array($file_ext, $allowed)) {
            $error = "Only JPG, JPEG, PNG & WEBP files are allowed.";
        } else {
            // Generate unique filename
            $new_filename = uniqid() . '.' . $file_ext;
            $destination = $upload_dir . $new_filename;

            if(move_uploaded_file($_FILES['image']['tmp_name'], $destination)) {
                $image_path = 'uploads/vehicles/' . $new_filename;
            } else {
                $error = "Failed to upload image.";
            }
        }
    }

    if(empty($model) || $daily_rate <= 0) {
        $error = "All fields are required and daily rate must be greater than 0";
    } else {
        // Use prepared statement for insert
        $query = "INSERT INTO vehicles (brand_id, model, year, daily_rate, specifications, image, status, quantity) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = mysqli_prepare($conn, $query);
        if($stmt) {
            $status = 'available'; // Default status for new vehicles
            mysqli_stmt_bind_param($stmt, "isidsssi", 
                $brand_id, $model, $year, $daily_rate, 
                $specifications, $image_path, $status, $quantity);
            
            if(mysqli_stmt_execute($stmt)) {
                $_SESSION['success'] = "Vehicle added successfully!";
                header("Location: vehicles.php");
                exit();
            } else {
                $error = "Failed to add vehicle: " . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt);
        } else {
            $error = "Failed to prepare statement: " . mysqli_error($conn);
        }
    }
}

if(isset($_POST['edit_vehicle'])) {
    $id = $_POST['vehicle_id'];
    $brand_id = $_POST['brand_id'];
    $model = trim($_POST['model']);
    $year = (int)$_POST['year'];
    $daily_rate = (float)$_POST['daily_rate'];
    $status = $_POST['status'];
    $specifications = trim($_POST['specifications']);
    $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
    
    if(empty($model) || $daily_rate <= 0) {
        $error = "All fields are required and daily rate must be greater than 0";
    } else {
        // Use prepared statement for update
        $query = "UPDATE vehicles 
                 SET brand_id = ?, model = ?, year = ?, 
                     daily_rate = ?, status = ?, specifications = ?, quantity = ?
                 WHERE id = ?";
        
        $stmt = mysqli_prepare($conn, $query);
        if($stmt) {
            mysqli_stmt_bind_param($stmt, "isidssii", 
                $brand_id, $model, $year, $daily_rate, $status, $specifications, $quantity, $id);
            
            if(mysqli_stmt_execute($stmt)) {
                $_SESSION['success'] = "Vehicle updated successfully!";
                header("Location: vehicles.php");
                exit();
            } else {
                $error = "Failed to update vehicle: " . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt);
        } else {
            $error = "Failed to prepare statement: " . mysqli_error($conn);
        }
    }
}

if(isset($_POST['delete_vehicle'])) {
    $id = $_POST['vehicle_id'];
    
    // Check if vehicle has any bookings using prepared statement
    $check_query = "SELECT COUNT(*) as count FROM bookings WHERE vehicle_id = ? AND status NOT IN ('completed', 'cancelled')";
    $stmt = mysqli_prepare($conn, $check_query);
    if($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        $has_bookings = $row['count'] > 0;
        mysqli_stmt_close($stmt);
        
        if(!$has_bookings) {
            // Use prepared statement for delete
            $query = "DELETE FROM vehicles WHERE id = ?";
            $stmt = mysqli_prepare($conn, $query);
            if($stmt) {
                mysqli_stmt_bind_param($stmt, "i", $id);
                if(mysqli_stmt_execute($stmt)) {
                    $_SESSION['success'] = "Vehicle deleted successfully!";
                    header("Location: vehicles.php");
                    exit();
                } else {
                    $error = "Failed to delete vehicle: " . mysqli_error($conn);
                }
                mysqli_stmt_close($stmt);
            } else {
                $error = "Failed to prepare delete statement: " . mysqli_error($conn);
            }
        } else {
            $error = "Cannot delete vehicle that has bookings";
        }
    } else {
        $error = "Failed to check bookings: " . mysqli_error($conn);
    }
}

// Set page title for the header
$page_title = "Vehicle Management";

// Include header
include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Vehicles</h2>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addVehicleModal">
            <i class="fas fa-plus"></i> Add New Vehicle
        </button>
    </div>
    <!-- Search and Filter Bar -->
    <form class="row g-3 mb-3 align-items-center" method="get" action="" id="filterForm">
        <div class="col-md-4 d-flex align-items-center">
            <input type="text" class="form-control" name="search" placeholder="Search by Brand, Model, Registration..." value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit" class="btn btn-primary ms-2" title="Search" style="padding: 0.375rem 0.75rem;">
                <i class="fas fa-search"></i>
            </button>
        </div>
        <div class="col-md-3">
            <select class="form-select" name="status_filter" id="statusFilter">
                <option value="">All Statuses</option>
                <option value="available" <?php if($status_filter==='available') echo 'selected'; ?>>Available</option>
                <option value="rented" <?php if($status_filter==='rented') echo 'selected'; ?>>Rented</option>
                <option value="booked" <?php if($status_filter==='booked') echo 'selected'; ?>>Booked</option>
                <option value="maintenance" <?php if($status_filter==='maintenance') echo 'selected'; ?>>Maintenance</option>
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

<script>
    document.getElementById('statusFilter').addEventListener('change', function() {
        document.getElementById('filterForm').submit();
    });
</script>

<?php if(isset($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo $error; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <!-- Top Scrollbar -->
            <div id="top-scroll-vehicles" style="overflow-x: auto; overflow-y: hidden; width: 100%;">
                <div style="width: max-content; height: 1px;"></div>
            </div>
            <div id="table-scroll-vehicles" class="table-responsive">
                <table class="table table-bordered table-hover" id="vehicles-table">
                    <thead>
                        <tr>
                        <th>ID</th>
                        <th>Image</th>
                            <th>Brand</th>
                            <th>Model</th>
                            <th>Year</th>
                            <th>Daily Rate (₱)</th>
                            <th>Quantity</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if(count($vehicles) > 0): ?>
                        <?php foreach($vehicles as $vehicle): ?>
                            <tr>
                                <td><?php echo $vehicle['id']; ?></td>
                                <td>
                                    <?php if(!empty($vehicle['image'])): ?>
                                        <img src="../<?php echo $vehicle['image']; ?>" alt="<?php echo $vehicle['model']; ?>" class="img-thumbnail" style="max-width: 100px;">
                                    <?php else: ?>
                                        <span class="text-muted">No image</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $vehicle['brand_name']; ?></td>
                                <td><?php echo $vehicle['model']; ?></td>
                                <td><?php echo $vehicle['year']; ?></td>
                                <td>₱<?php echo number_format($vehicle['daily_rate'], 2); ?></td>
                                <td><?php echo $vehicle['quantity']; ?></td>
                                <td>
                                    <?php 
                                    $status_class = '';
                                    switch($vehicle['status']) {
                                        case 'available':
                                            $status_class = 'bg-success';
                                            break;
                                        case 'rented':
                                            $status_class = 'bg-warning';
                                            break;
                                        case 'booked':
                                            $status_class = 'bg-info';
                                            break;
                                        case 'maintenance':
                                            $status_class = 'bg-danger';
                                            break;
                                    }
                                    ?>
                                    <span class="badge <?php echo $status_class; ?>"><?php echo ucfirst($vehicle['status']); ?></span>
                                </td>
                                <td>
                                    <div class="d-flex flex-column gap-2">
                                        <button type="button" class="btn btn-sm btn-primary edit-vehicle"
                                            data-id="<?php echo $vehicle['id']; ?>"
                                            data-brand="<?php echo $vehicle['brand_id']; ?>"
                                            data-model="<?php echo htmlspecialchars($vehicle['model']); ?>"
                                            data-year="<?php echo $vehicle['year']; ?>"
                                            data-rate="<?php echo $vehicle['daily_rate']; ?>"
                                            data-status="<?php echo $vehicle['status']; ?>"
                                            data-specifications="<?php echo htmlspecialchars($vehicle['specifications']); ?>"
                                            data-quantity="<?php echo $vehicle['quantity']; ?>"
                                            data-bs-toggle="modal" data-bs-target="#editVehicleModal">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-info" style="font-size:0.7rem;" onclick='viewReceipt(<?php echo json_encode($booking); ?>)'>
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-danger delete-vehicle"
                                            data-id="<?php echo $vehicle['id']; ?>"
                                            data-model="<?php echo htmlspecialchars($vehicle['model']); ?>"
                                            data-bs-toggle="modal" data-bs-target="#deleteVehicleModal">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="10" class="text-center">No vehicles found</td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Vehicle Modal -->
<div class="modal fade" id="addVehicleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Vehicle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
        <form method="post" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="mb-3">
                    <label class="form-label">Brand</label>
                    <select class="form-select" name="brand_id" required>
                        <option value="">Select Brand</option>
                        <?php foreach($brands as $brand): ?>
                            <option value="<?php echo $brand['id']; ?>"><?php echo htmlspecialchars($brand['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Model</label>
                    <input type="text" class="form-control" name="model" maxlength="30" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Year</label>
                    <select class="form-select" name="year" required>
                        <option value="">Select Year</option>
                        <?php 
                        for($year = 2025; $year >= 2021; $year--): ?>
                            <option value="<?php echo $year; ?>"><?php echo $year; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Vehicle Image</label>
                    <input type="file" class="form-control" name="image" accept="image/*" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Daily Rate (₱)</label>
                    <input type="number" class="form-control" name="daily_rate" min="1" step="0.01" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Quantity</label>
                    <input type="number" class="form-control" name="quantity" min="1" max="30" value="1" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Specifications</label>
                    <textarea class="form-control" name="specifications" rows="3" required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" name="add_vehicle" class="btn btn-primary">Add Vehicle</button>
            </div>
        </form>
    </div>
</div>
</div>

<!-- Edit Vehicle Modal -->
<div class="modal fade" id="editVehicleModal" tabindex="-1" aria-labelledby="editVehicleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editVehicleModalLabel">Edit Vehicle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" id="edit_vehicle_id" name="vehicle_id">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_brand_id" class="form-label">Brand</label>
                            <select class="form-select" id="edit_brand_id" name="brand_id" required>
                                <option value="">Select Brand</option>
                                <?php foreach($brands as $brand): ?>
                                    <option value="<?php echo $brand['id']; ?>"><?php echo $brand['name']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_model" class="form-label">Model</label>
                    <input type="text" class="form-control" id="edit_model" name="model" maxlength="30" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_year" class="form-label">Year</label>
                            <select class="form-select" id="edit_year" name="year" required>
                                <option value="">Select Year</option>
                                <?php for($year = 2025; $year >= 2021; $year--): ?>
                                    <option value="<?php echo $year; ?>"><?php echo $year; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_daily_rate" class="form-label">Daily Rate (₱)</label>
                            <input type="number" class="form-control" id="edit_daily_rate" name="daily_rate" step="0.01" min="1" required>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_status" class="form-label">Status</label>
                                <select class="form-select" id="edit_status" name="status" required>
                                <option value="available">Available</option>
                                <option value="rented">Rented</option>
                                <option value="booked">Booked</option>
                                <option value="maintenance">Maintenance</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="edit_quantity" class="form-label">Quantity</label>
                        <input type="number" class="form-control" id="edit_quantity" name="quantity" min="1" max="30" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_specifications" class="form-label">Specifications</label>
                        <textarea class="form-control" id="edit_specifications" name="specifications" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_vehicle" class="btn btn-primary">Update Vehicle</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Vehicle Modal -->
<div class="modal fade" id="deleteVehicleModal" tabindex="-1" aria-labelledby="deleteVehicleModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteVehicleModalLabel">Delete Vehicle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete the vehicle: <strong id="delete_vehicle_model"></strong>?</p>
                <p class="text-danger">This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <form method="POST" action="">
                    <input type="hidden" id="delete_vehicle_id" name="vehicle_id">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="delete_vehicle" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php foreach($vehicles as $vehicle): ?>
<div class="modal fade" id="viewVehicleModal<?php echo $vehicle['id']; ?>" tabindex="-1" aria-labelledby="viewVehicleModalLabel<?php echo $vehicle['id']; ?>" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="viewVehicleModalLabel<?php echo $vehicle['id']; ?>">Vehicle Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="row">
          <div class="col-md-5 text-center">
            <?php if (!empty($vehicle['image'])): ?>
              <img src="../<?php echo htmlspecialchars($vehicle['image']); ?>" class="img-fluid rounded" alt="Vehicle Image">
            <?php else: ?>
              <img src="../uploads/vehicles/default-car.jpg" class="img-fluid rounded" alt="Default vehicle image">
            <?php endif; ?>
          </div>
          <div class="col-md-7">
            <ul class="list-group list-group-flush">
              <li class="list-group-item"><strong>Brand:</strong> <?php echo htmlspecialchars($vehicle['brand_name']); ?></li>
              <li class="list-group-item"><strong>Model:</strong> <?php echo htmlspecialchars($vehicle['model']); ?></li>
              <li class="list-group-item"><strong>Year:</strong> <?php echo htmlspecialchars($vehicle['year']); ?></li>
              <li class="list-group-item"><strong>Daily Rate:</strong> ₱<?php echo number_format($vehicle['daily_rate'], 2); ?></li>
              <li class="list-group-item"><strong>Quantity:</strong> <?php echo htmlspecialchars($vehicle['quantity']); ?></li>
              <li class="list-group-item"><strong>Status:</strong> <?php echo htmlspecialchars($vehicle['status']); ?></li>
              <li class="list-group-item"><strong>Created At:</strong> <?php echo htmlspecialchars($vehicle['created_at']); ?></li>
              <?php if (!empty($vehicle['specifications'])): ?>
                <li class="list-group-item"><strong>Specifications:</strong> <?php echo htmlspecialchars($vehicle['specifications']); ?></li>
              <?php endif; ?>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php endforeach; ?>

<script>
    // Edit vehicle functionality
    document.querySelectorAll('.edit-vehicle').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const brand = this.getAttribute('data-brand');
            const model = this.getAttribute('data-model');
            const year = this.getAttribute('data-year');
            const rate = this.getAttribute('data-rate');
            const status = this.getAttribute('data-status');
            const specifications = this.getAttribute('data-specifications');
            const quantity = this.getAttribute('data-quantity');
            
            document.getElementById('edit_vehicle_id').value = id;
            document.getElementById('edit_brand_id').value = brand;
            document.getElementById('edit_model').value = model;
            document.getElementById('edit_year').value = year;
            document.getElementById('edit_daily_rate').value = rate;
            document.getElementById('edit_status').value = status;
            document.getElementById('edit_specifications').value = specifications;
            document.getElementById('edit_quantity').value = quantity;
        });
    });
    
    // Delete vehicle functionality
    document.querySelectorAll('.delete-vehicle').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const model = this.getAttribute('data-model');
            
            document.getElementById('delete_vehicle_id').value = id;
            document.getElementById('delete_vehicle_model').textContent = model;
        });
    });

    // Sync top and bottom scrollbars for vehicles table
    window.addEventListener('DOMContentLoaded', function() {
        var topScroll = document.getElementById('top-scroll-vehicles');
        var tableScroll = document.getElementById('table-scroll-vehicles');
        var table = document.getElementById('vehicles-table');
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
