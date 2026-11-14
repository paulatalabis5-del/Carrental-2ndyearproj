<?php
session_name('quicktrip_session');
session_start();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
header('Pragma: no-cache');
require_once('../config/database.php');

// Check if user is logged in
if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get user information
$user_id = $_SESSION['user_id'];
$query = "SELECT * FROM users WHERE id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$user = mysqli_stmt_get_result($stmt)->fetch_assoc();
mysqli_stmt_close($stmt);

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

// Fetch pickup instructions
$instructions = [];
$inst_result = mysqli_query($conn, "SELECT * FROM pickup_instructions ORDER BY id ASC");
if ($inst_result) {
    $instructions = mysqli_fetch_all($inst_result, MYSQLI_ASSOC);
}

// Fetch footer content from admin
$footer_query = "SELECT * FROM footer_content ORDER BY id DESC LIMIT 1";
$footer_result = mysqli_query($conn, $footer_query);
$footer_content = mysqli_fetch_assoc($footer_result);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QuickTrip Car Rental - Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <nav>
        <div class="nav__header">
            <a href="#" class="logo">
                <img src="image/logo.jpg" alt="QuickTrip Logo">
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
            <a href="my_bookings.php"><i class="ri-calendar-check-line"></i> My Bookings</a>
            <a href="profile.php"><i class="ri-user-line"></i> Profile</a>
            <a href="testimonials.php"><i class="ri-star-line"></i> Testimonials</a>
            <a href="contact.php"><i class="ri-customer-service-line"></i> Contact Us</a>
            <a href="subscribe.php"><i class="ri-mail-line"></i> Newsletter</a>
            <a href="logout.php" class="btn-logout"><i class="ri-logout-box-line"></i> Logout</a>
        </div>
    </nav>

    <header>
        <div class="header__container" id="home">
            <div class="header__content">
                <div class="welcome-message">
                    <h3>Welcome, <?php echo htmlspecialchars($user['name']); ?></h3>
                </div>
                <h1>
                    <span>WHEELS FOR</span>
                    <span>EVERY JOURNEY</span>
                </h1>
                <p class="section__description">
                    Sakay na! Traveling just got better. Our prices may be the cheapest, but our cars are new and the latest models. Enjoy a smooth, comfortable ride with top-notch service, ensuring safety and convenience every step of the way. Whether you're heading to work or on a weekend getaway, we make every trip hassle-free and affordable!
                </p>
            </div>
            <div class="header__image">
                <img src="image/car2.png" alt="Orange SUV" />
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
                                    <img src="../<?php echo htmlspecialchars($vehicle['image']); ?>" 
                                         class="vehicle-image" 
                                         alt="<?php echo htmlspecialchars($vehicle['brand_name'] . ' ' . $vehicle['model']); ?>">
                                <?php else: ?>
                                    <img src="../uploads/vehicles/default-car.jpg" 
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
                                <a href="book_vehicle.php?id=<?php echo $vehicle['id']; ?>" class="btn btn-primary book-now-btn">
                                    <i class="ri-calendar-check-line"></i> Book Now
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const hamburgerMenu = document.querySelector('.hamburger-menu');
        const navLinks = document.querySelector('.nav__links');

        hamburgerMenu.addEventListener('click', () => {
            hamburgerMenu.classList.toggle('active');
            navLinks.classList.toggle('active');
        });

        // Close menu when clicking outside
        document.addEventListener('click', (e) => {
            if (!hamburgerMenu.contains(e.target) && !navLinks.contains(e.target)) {
                hamburgerMenu.classList.remove('active');
                navLinks.classList.remove('active');
            }
        });
    </script>

    <style>
    .btn-orange {
        background-color: #D35400;
        color: #fff;
        border: none;
        width: 100px;
        text-align: center;
    }
    .btn-orange:hover, .btn-orange:focus {
        background-color: #b34700;
        color: #fff;
    }
    </style>

    <!-- Footer -->
    <footer class="footer bg-dark text-white mt-5 pt-5 pb-4">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-4">
                    <h5><i class="ri-map-pin-fill" style="color:#D35400;"></i> Location</h5>
                    <p><?= htmlspecialchars($footer_content['location'] ?? 'No location provided') ?></p>
                    <p><i class="ri-phone-fill" style="color:#D35400;"></i> <strong>Contact Number:</strong> <?= htmlspecialchars($footer_content['contact_number'] ?? 'N/A') ?></p>
                    <p><i class="ri-calendar-schedule-fill" style="color:#D35400;"></i> <strong>Office Hours:</strong> <?= htmlspecialchars($footer_content['office_hours'] ?? 'N/A') ?></p>
                </div>
                <div class="col-md-4 mb-4">
                    <h5>Connect with Us</h5>
                    <ul class="list-unstyled d-flex gap-3">
                        <?php if (!empty($footer_content['facebook'])): ?>
                            <li><a href="<?= htmlspecialchars($footer_content['facebook']) ?>" target="_blank" class="text-white fs-4"><i class="ri-facebook-circle-fill" style="color:#D35400;"></i></a></li>
                        <?php endif; ?>
                        <?php if (!empty($footer_content['linkedin'])): ?>
                            <li><a href="<?= htmlspecialchars($footer_content['linkedin']) ?>" target="_blank" class="text-white fs-4"><i class="ri-linkedin-fill" style="color:#D35400;"></i></a></li>
                        <?php endif; ?>
                        <?php if (!empty($footer_content['instagram'])): ?>
                            <li><a href="<?= htmlspecialchars($footer_content['instagram']) ?>" target="_blank" class="text-white fs-4"><i class="ri-instagram-line" style="color:#D35400;"></i></a></li>
                        <?php endif; ?>
                    </ul>
                </div>
                <div class="col-md-4 mb-4">
                    <h5><i class="ri-mail-fill" style="color:#D35400;"></i> Email</h5>
                    <p><?= htmlspecialchars($footer_content['email'] ?? 'No email provided') ?></p>
                </div>
            </div>
            <div class="text-center mt-3">
                <small>&copy; <?= date('Y') ?> QuickTrip Car Rental. All rights reserved.</small>
            </div>
        </div>
    </footer>

    <style>
        .footer a:hover {
            color: #D35400 !important;
            text-decoration: none;
        }
        .footer ul {
            padding-left: 0;
        }
        .footer ul li {
            list-style: none;
        }
    </style>
</body>
</html> 
