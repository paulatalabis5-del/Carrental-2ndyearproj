<?php
require_once('../../config/database.php');

// Fetch available vehicles with their brand names
$query = "
    SELECT v.*, vb.name as brand_name
    FROM vehicles v
    JOIN vehicle_brands vb ON v.brand_id = vb.id
    WHERE v.status = 'available'
    ORDER BY v.created_at DESC
";
$result = mysqli_query($conn, $query);
if($result === false) {
    die("Error fetching vehicles: " . mysqli_error($conn));
}
$vehicles = mysqli_fetch_all($result, MYSQLI_ASSOC);
mysqli_free_result($result);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Available Vehicles - QuickTrip Car Rental</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet">
    <link rel="stylesheet" href="../style.css">
</head>
<body>
    <nav>
        <div class="nav__header">
            <a href="index.php" class="logo">
                <img src="../image/logo.jpg" alt="QuickTrip Logo">
                <span>QuickTrip</span>
            </a>
            <div class="hamburger-menu">
                <span></span>
                <span></span>
                <span></span>
            </div>
        </div>
        <div class="nav__links">
            <a href="index.php"><i class="ri-home-line"></i> Home</a>
            <a href="vehicles.php"><i class="ri-car-line"></i> Vehicles</a>
            <a href="contact.php"><i class="ri-customer-service-line"></i> Contact Us</a>
            <a href="../login.php" class="btn btn-primary ms-2">Login</a>
            <a href="../register.php" class="btn btn-outline-primary ms-2">Sign Up</a>
        </div>
    </nav>
    <div class="section__container mt-5">
        <h2 class="section-title">Available Vehicles</h2>
        <?php if(empty($vehicles)): ?>
            <div class="no-vehicles">
                <i class="ri-car-line"></i>
                <h3>No Vehicles Available</h3>
                <p>Check back later for new vehicles!</p>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach($vehicles as $vehicle): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="vehicle-card">
                            <div class="vehicle-image-container">
                                <?php if(!empty($vehicle['image'])): ?>
                                    <img src="../../<?php echo htmlspecialchars($vehicle['image']); ?>" 
                                         class="vehicle-image" 
                                         alt="<?php echo htmlspecialchars($vehicle['brand_name'] . ' ' . $vehicle['model']); ?>">
                                <?php else: ?>
                                    <img src="../../uploads/vehicles/default-car.jpg" 
                                         class="vehicle-image" 
                                         alt="Default vehicle image">
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <h5 class="vehicle-title">
                                    <?php echo htmlspecialchars($vehicle['brand_name'] . ' ' . $vehicle['model']); ?>
                                </h5>
                                <ul class="vehicle-features">
                                    <li>
                                        <i class="ri-calendar-line"></i>
                                        <span>Year: <?php echo $vehicle['year']; ?></span>
                                    </li>
                                    <li>
                                        <i class="ri-fingerprint-line"></i>
                                        <span>Reg: <?php echo htmlspecialchars($vehicle['registration_number']); ?></span>
                                    </li>
                                    <?php if(!empty($vehicle['specifications'])): ?>
                                    <li>
                                        <i class="ri-settings-3-line"></i>
                                        <span><?php echo htmlspecialchars($vehicle['specifications']); ?></span>
                                    </li>
                                    <?php endif; ?>
                                    <li>
                                        <i class="ri-money-dollar-circle-line"></i>
                                        <span class="vehicle-price">₱<?php echo number_format($vehicle['daily_rate'], 2); ?></span> per day
                                    </li>
                                </ul>
                                <a href="../login.php" class="btn btn-primary book-now-btn">
                                    <i class="ri-calendar-check-line"></i> Book Now
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <footer class="footer mt-5">
        <div class="container text-center py-3">
            <p class="mb-0">&copy; <?php echo date('Y'); ?> QuickTrip Car Rental. All rights reserved.</p>
        </div>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 