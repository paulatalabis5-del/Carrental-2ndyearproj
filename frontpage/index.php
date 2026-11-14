<?php
// Demo content keeps the public front page usable when MySQL is not installed,
// the mysqli PHP extension is disabled, or the database is temporarily offline.
$instructions = [
    [
        'id' => 1,
        'icon' => 'ri-car-line',
        'title' => "Valid Driver's License",
        'front_description' => "Bring a valid driver's license in the main driver's name.",
        'description' => "Bring the original, unexpired driver's license. Non-local renters may also need an International Driving Permit.",
    ],
    [
        'id' => 2,
        'icon' => 'ri-bank-card-line',
        'title' => 'Payment and Deposit',
        'front_description' => 'Prepare your payment reference and refundable security deposit.',
        'description' => 'Present proof of payment for cashless transactions. The security deposit is returned after the vehicle passes its return inspection.',
    ],
    [
        'id' => 3,
        'icon' => 'ri-id-card-line',
        'title' => 'Government-Issued ID',
        'front_description' => 'Bring one valid government-issued ID for identity verification.',
        'description' => 'A passport, national ID, or another current government-issued ID is required for security and record-keeping.',
    ],
];

$vehicles = [
    [
        'brand_name' => 'Toyota',
        'model' => 'Fortuner',
        'year' => 2024,
        'registration_number' => 'DEMO-001',
        'specifications' => 'Automatic transmission | 7 passengers',
        'daily_rate' => 3500,
        'image' => 'user/image/fortuner.png',
    ],
    [
        'brand_name' => 'Toyota',
        'model' => 'Vios',
        'year' => 2024,
        'registration_number' => 'DEMO-002',
        'specifications' => 'Automatic transmission | 5 passengers',
        'daily_rate' => 2000,
        'image' => 'user/image/vios.png',
    ],
    [
        'brand_name' => 'Mitsubishi',
        'model' => 'Montero Sport',
        'year' => 2023,
        'registration_number' => 'DEMO-003',
        'specifications' => 'Automatic transmission | 7 passengers',
        'daily_rate' => 3300,
        'image' => 'user/image/montero.png',
    ],
];

$usingFallbackData = true;

if (function_exists('mysqli_connect')) {
    try {
        // Throw database errors so this page can recover with demo content.
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        require_once __DIR__ . '/../../config/database.php';

        $vehicleResult = mysqli_query(
            $conn,
            "SELECT v.*, vb.name AS brand_name
             FROM vehicles v
             JOIN vehicle_brands vb ON v.brand_id = vb.id
             WHERE v.status = 'available'
             ORDER BY v.created_at DESC"
        );
        $databaseVehicles = mysqli_fetch_all($vehicleResult, MYSQLI_ASSOC);
        mysqli_free_result($vehicleResult);

        $instructionResult = mysqli_query(
            $conn,
            'SELECT * FROM pickup_instructions ORDER BY id ASC'
        );
        $databaseInstructions = mysqli_fetch_all($instructionResult, MYSQLI_ASSOC);
        mysqli_free_result($instructionResult);

        $vehicles = $databaseVehicles;
        $instructions = $databaseInstructions;
        $usingFallbackData = false;
    } catch (Throwable $exception) {
        error_log('QuickTrip front page is using demo data: ' . $exception->getMessage());
    }
} else {
    error_log('QuickTrip front page is using demo data: the mysqli PHP extension is not enabled.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QuickTrip Car Rental - Welcome</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet">
    <link rel="stylesheet" href="../style.css">
</head>
<body>
    <nav>
        <div class="nav__header">
            <a href="#" class="logo">
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
    <header>
        <div class="header__container" id="home">
            <div class="header__content">
                <h1>
                    <span>WHEELS FOR</span>
                    <span>EVERY JOURNEY</span>
                </h1>
                <p class="section__description">
                    Sakay na! Traveling just got better. Our prices may be the cheapest, but our cars are new and the latest models. Enjoy a smooth, comfortable ride with top-notch service, ensuring safety and convenience every step of the way. Whether you're heading to work or on a weekend getaway, we make every trip hassle-free and affordable!
                </p>
            </div>
            <div class="header__image">
                <img src="../image/car2.png" alt="Orange SUV" />
            </div>
        </div>
    </header>
    <div class="section__container mt-5">
        <h2 class="section-title">What You Need and Know to Pick Up the Car</h2>
        <div class="row g-4">
            <?php foreach($instructions as $inst): ?>
                <div class="col-md-4">
                    <div class="card h-100 text-center p-3 shadow instruction-card" style="cursor:pointer;">
                        <div class="mb-3">
                            <i class="<?php echo htmlspecialchars($inst['icon']); ?>" style="font-size:2.5rem;color:#D35400;"></i>
                        </div>
                        <h5 class="fw-bold"><?php echo htmlspecialchars($inst['title']); ?></h5>
                        <p class="text-black mb-2"><?php echo nl2br(htmlspecialchars($inst['front_description'] ?? '')); ?></p>
                        <div class="d-flex justify-content-center">
                            <button type="button" class="btn btn-sm btn-orange rounded-pill px-3" style="width:100px;" data-bs-toggle="modal" data-bs-target="#instructionModal<?php echo $inst['id']; ?>">
                                <i class="fas fa-eye"></i> See More
                            </button>
                        </div>
                    </div>
                </div>
                <!-- Modal for this instruction -->
                <div class="modal fade" id="instructionModal<?php echo $inst['id']; ?>" tabindex="-1" aria-labelledby="instructionModalLabel<?php echo $inst['id']; ?>" aria-hidden="true">
                  <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                      <div class="modal-header">
                        <h5 class="modal-title" id="instructionModalLabel<?php echo $inst['id']; ?>">
                          <i class="<?php echo htmlspecialchars($inst['icon']); ?>" style="font-size:2rem;color:#D35400;"></i>
                          <?php echo htmlspecialchars($inst['title']); ?>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                      </div>
                      <div class="modal-body">
                        <p class="text-black fs-5 mb-0"><?php echo nl2br(htmlspecialchars($inst['description'])); ?></p>
                      </div>
                    </div>
                  </div>
                </div>
            <?php endforeach; ?>
            <?php if (empty($instructions)): ?>
                <div class="col-12 text-center text-muted">No instructions set by admin yet.</div>
            <?php endif; ?>
        </div>
    </div>
    <div class="section__container">
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
