<?php
session_name('quicktrip_session');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '', // or your domain, e.g., 'localhost'
    'secure' => false, // true if using HTTPS
    'httponly' => true,
    'samesite' => 'Lax'
]);
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
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    die("Error: User not found");
}

// Get unread messages count
$stmt = $conn->prepare("SELECT COUNT(*) as unread_count FROM contact_queries WHERE user_id = ? AND admin_reply IS NOT NULL AND is_read = 0");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$unread_count = $result->fetch_assoc()['unread_count'];
$stmt->close();

// Handle newsletter subscription
if(isset($_POST['subscribe'])) {
    $email = trim($_POST['email']);
    
    if(empty($email)) {
        $_SESSION['error'] = "Email is required";
    } elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Invalid email format";
    } else {
        // Check if already subscribed
        $stmt = $conn->prepare("SELECT id FROM subscribers WHERE email = ?");
        if ($stmt) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->store_result();
            
            if($stmt->num_rows > 0) {
                $_SESSION['error'] = "You are already subscribed to our newsletter!";
                $stmt->close();
            } else {
                $stmt->close();
                
                // Add new subscriber
                $stmt = $conn->prepare("INSERT INTO subscribers (email, status) VALUES (?, 'active')");
                if ($stmt) {
                    $stmt->bind_param("s", $email);
                    if($stmt->execute()) {
                        $_SESSION['success'] = "Thank you for subscribing to our newsletter!";
                    } else {
                        $_SESSION['error'] = "Error subscribing: " . $conn->error;
                    }
                    $stmt->close();
                } else {
                    $_SESSION['error'] = "Error preparing statement: " . $conn->error;
                }
            }
        } else {
            $_SESSION['error'] = "Error preparing statement: " . $conn->error;
        }
    }
    header("Location: subscribe.php");
    exit();
}

// Get messages from session and clear them
$success = isset($_SESSION['success']) ? $_SESSION['success'] : null;
$error = isset($_SESSION['error']) ? $_SESSION['error'] : null;
unset($_SESSION['success']);
unset($_SESSION['error']);

// Get subscription status
$subscription_status = null;
$subscribed_email = null;

$stmt = $conn->prepare("SELECT status, email FROM subscribers WHERE email = ?");
if ($stmt) {
    $stmt->bind_param("s", $user['email']);
    $stmt->execute();
    $result = $stmt->get_result();
    if($row = $result->fetch_assoc()) {
        $subscription_status = $row['status'];
        $subscribed_email = $row['email'];
    }
    $stmt->close();
}

// Get messages for dropdown
$messages = array();
$stmt = $conn->prepare("SELECT * FROM contact_queries WHERE user_id = ? AND admin_reply IS NOT NULL ORDER BY updated_at DESC LIMIT 5");
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $messages = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Newsletter Subscription - QuickTrip Car Rental</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .mail-icon {
            position: relative;
            margin-right: 1rem;
            color: #fff;
            font-size: 1.5rem;
            cursor: pointer;
            text-decoration: none;
        }
        .mail-icon:hover {
            color: var(--primary-color);
        }
        .notification-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background-color: var(--primary-color);
            color: white;
            border-radius: 50%;
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
            min-width: 1.5rem;
            text-align: center;
        }
        .dropdown-menu {
            background-color: #1a1a1a;
            border: 1px solid rgba(255,255,255,0.1);
            min-width: 300px;
            padding: 0;
            margin-top: 0.5rem;
            box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.2);
        }
        .dropdown-item {
            color: #fff;
            padding: 1rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .dropdown-item:last-child {
            border-bottom: none;
        }
        .dropdown-item:hover {
            background-color: rgba(255,255,255,0.1);
            color: #fff;
        }
        .dropdown-item .message-subject {
            font-weight: 600;
            margin-bottom: 0.25rem;
            color: var(--primary-color);
        }
        .dropdown-item .message-reply {
            font-size: 0.875rem;
            color: #adb5bd;
            line-height: 1.4;
        }
        .nav__btns {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .nav__btns .btn {
            margin: 0;
            padding: 0.5rem 1.5rem;
            font-weight: 500;
            background-color: var(--primary-color);
            border: none;
            transition: all 0.3s ease;
        }
        .nav__btns .btn:hover {
            background-color: var(--primary-color-dark);
        }
    </style>
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

    <div class="section__container">
        <div class="newsletter-form">
            <div class="newsletter-title">
                <i class="ri-mail-send-line"></i>
                <h2>Subscribe to Our Newsletter</h2>
            </div>

            <?php if(isset($success)): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <?php if(isset($error)): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if($subscription_status): ?>
                <div class="subscription-status">
                    <div class="alert alert-info">
                        <p>You are currently subscribed with: <strong><?php echo htmlspecialchars($subscribed_email); ?></strong></p>
                        <p>Status: <strong><?php echo ucfirst(htmlspecialchars($subscription_status)); ?></strong></p>
                    </div>
                </div>
            <?php else: ?>
                <form method="POST" action="" class="newsletter-form">
                    <div class="form-group mb-3">
                        <label for="email" class="form-label">Email Address</label>
                        <input type="email" class="form-control" id="email" name="email" 
                               value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        <div class="form-text">We'll never share your email with anyone else.</div>
                    </div>
                    <button type="submit" name="subscribe" class="btn btn-primary">
                        <i class="ri-send-plane-fill"></i> Subscribe Now
                    </button>
                </form>
            <?php endif; ?>
        </div>
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
<?php
// Fetch footer content from admin if not already fetched
if (!isset($footer_content)) {
    $footer_query = "SELECT * FROM footer_content ORDER BY id DESC LIMIT 1";
    $footer_result = mysqli_query($conn, $footer_query);
    $footer_content = mysqli_fetch_assoc($footer_result);
}
?>
    <!-- Footer -->
    <footer class="footer bg-dark text-white" style="margin-top: 31rem; padding-top: 5rem; padding-bottom: 1rem;">
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
</body>
</html> 
