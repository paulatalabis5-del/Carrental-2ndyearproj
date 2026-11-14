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

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Get success message from session if it exists
if(isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']); // Clear the message after showing it
}

// Clean up duplicate testimonials
$cleanup_query = "
    DELETE t1 FROM testimonials t1
    INNER JOIN testimonials t2 
    WHERE t1.id > t2.id 
    AND t1.user_id = t2.user_id 
    AND t1.content = t2.content 
    AND t1.rating = t2.rating 
    AND t1.created_at = t2.created_at
";
mysqli_query($conn, $cleanup_query);

// Handle testimonial submission
if(isset($_POST['submit_testimonial'])) {
    $content = trim($_POST['message']);
    $rating = (int)$_POST['rating'];
    
    if(empty($content)) {
        $error_message = "Please enter your testimonial message.";
    } elseif($rating < 1 || $rating > 5) {
        $error_message = "Please select a rating between 1 and 5.";
    } else {
        // Check for recent duplicate submissions
        $check_query = "
            SELECT COUNT(*) as count 
            FROM testimonials 
            WHERE user_id = ? 
            AND content = ? 
            AND rating = ? 
            AND created_at > NOW() - INTERVAL 1 MINUTE
        ";
        $check_stmt = mysqli_prepare($conn, $check_query);
        mysqli_stmt_bind_param($check_stmt, "isi", $user_id, $content, $rating);
        mysqli_stmt_execute($check_stmt);
        $result = mysqli_stmt_get_result($check_stmt);
        $row = mysqli_fetch_assoc($result);
        
        if($row['count'] > 0) {
            $error_message = "You have already submitted this testimonial. Please wait a moment before submitting another one.";
        } else {
            // Insert testimonial with 'approved' status by default
            $query = "INSERT INTO testimonials (user_id, content, rating, status) VALUES (?, ?, ?, 'approved')";
            $stmt = mysqli_prepare($conn, $query);
            if($stmt) {
                mysqli_stmt_bind_param($stmt, "isi", $user_id, $content, $rating);
                if(mysqli_stmt_execute($stmt)) {
                    $_SESSION['success_message'] = "Thank you! Your testimonial has been submitted successfully.";
                    header("Location: testimonials.php");
                    exit();
                } else {
                    $error_message = "Error submitting testimonial: " . mysqli_error($conn);
                }
                mysqli_stmt_close($stmt);
            } else {
                $error_message = "Error preparing statement: " . mysqli_error($conn);
            }
        }
        mysqli_stmt_close($check_stmt);
    }
}

// Update the queries to show all testimonials (no longer need to check for pending status)
$query = "
    SELECT t.*, u.name as user_name, u.profile_picture
    FROM testimonials t
    JOIN users u ON t.user_id = u.id
    WHERE t.user_id = ?
    ORDER BY t.created_at DESC
";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$user_testimonials = mysqli_stmt_get_result($stmt);
mysqli_stmt_close($stmt);

// Fetch testimonials from other users
$query = "
    SELECT t.*, u.name as user_name, u.profile_picture
    FROM testimonials t
    JOIN users u ON t.user_id = u.id
    WHERE t.user_id != ? AND t.hidden = 0
    ORDER BY t.created_at DESC
    LIMIT 10
";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$other_testimonials = mysqli_stmt_get_result($stmt);
mysqli_stmt_close($stmt);

// Get unread messages count
$query = "SELECT COUNT(*) as unread_count FROM contact_queries WHERE user_id = ? AND admin_reply IS NOT NULL AND is_read = 0";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$unread_count = $result->fetch_assoc()['unread_count'];

// Check if user has any completed bookings
$completed_query = "SELECT COUNT(*) as completed_count FROM bookings WHERE user_id = ? AND status = 'completed'";
$stmt = mysqli_prepare($conn, $completed_query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$completed_count = mysqli_fetch_assoc($result)['completed_count'];
mysqli_stmt_close($stmt);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Testimonials - QuickTrip Car Rental</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet" />
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            padding-top: 80px;
            background-color: var(--extra-light);
        }
        .testimonials-container {
            max-width: 1000px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        .testimonial-card {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            margin-bottom: 1rem;
            overflow: hidden;
        }
        .testimonial-card .card-header {
            background: #1a1a1a;
            color: #fff;
            padding: 1rem 1.5rem;
            border-bottom: none;
        }
        .testimonial-card .card-body {
            padding: 1.5rem;
            color: #333;
        }
        .rating {
            color: #ffc107;
            font-size: 1.2rem;
        }
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.875rem;
            font-weight: 500;
        }
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }
        .status-approved {
            background-color: #d4edda;
            color: #155724;
        }
        .testimonial-form {
            background: #fff;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
            color: #333;
        }
        .testimonial-form h3 {
            color: #333;
            margin-bottom: 1.5rem;
        }
        .testimonial-form label {
            color: #333;
            font-weight: 500;
        }
        .testimonial-form textarea {
            color: #333;
        }
        .rating-input {
            display: flex;
            gap: 0.5rem;
            margin: 1rem 0;
        }
        .rating-input button {
            background: none;
            border: 2px solid #ffc107;
            color: #ffc107;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .rating-input button:hover,
        .rating-input button.active {
            background: #ffc107;
            color: #fff;
        }
        .testimonial-message {
            font-size: 1.1rem;
            line-height: 1.6;
            color: #444;
        }
        .testimonial-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #eee;
            color: #666;
            font-size: 0.9rem;
        }
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

    <div class="testimonials-container">
        <!-- Submit Testimonial Form -->
        <?php if($completed_count > 0): ?>
        <div class="testimonial-form">
            <h3><i class="ri-chat-quote-line"></i> Submit Your Testimonial</h3>
            <?php if($success_message): ?>
                <div class="alert alert-success">
                    <i class="ri-checkbox-circle-line"></i> <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>
            <?php if($error_message): ?>
                <div class="alert alert-danger">
                    <i class="ri-error-warning-line"></i> <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>
            <form method="POST" action="">
                <div class="mb-3">
                    <label class="form-label">Your Rating</label>
                    <div class="rating-input" id="ratingInput">
                        <?php for($i = 1; $i <= 5; $i++): ?>
                            <span class="rating-star" data-rating="<?php echo $i; ?>" style="font-size:2rem;cursor:pointer;color:#ccc;">&#9733;</span>
                        <?php endfor; ?>
                        <input type="hidden" name="rating" id="selected-rating" value="">
                        <span id="selected-rating-number" style="margin-left:10px;font-weight:bold;"></span>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="message" class="form-label">Your Message</label>
                    <textarea class="form-control" id="message" name="message" rows="4" required></textarea>
                </div>
                <button type="submit" name="submit_testimonial" class="btn btn-primary">
                    <i class="ri-send-plane-line"></i> Submit Testimonial
                </button>
            </form>
        </div>
        <?php else: ?>
        <div class="alert alert-info text-center mb-4">
            You can submit a testimonial after you have completed a booking.
        </div>
        <?php endif; ?>

        <!-- User's Testimonials -->
        <div class="testimonial-card">
            <div class="card-header">
                <h3><i class="ri-message-2-line"></i> Your Testimonials</h3>
            </div>
            <div class="card-body">
                <?php 
                if(mysqli_num_rows($user_testimonials) == 0): 
                ?>
                    <p class="text-muted text-center">You haven't submitted any testimonials yet.</p>
                <?php else: ?>
                    <?php while($testimonial = mysqli_fetch_assoc($user_testimonials)): ?>
                        <div class="testimonial-item mb-4">
                            <div class="d-flex align-items-center mb-2 gap-2">
                                <img src="<?php echo !empty($testimonial['profile_picture']) ? '../' . htmlspecialchars($testimonial['profile_picture']) : 'https://ui-avatars.com/api/?name=' . urlencode($testimonial['user_name']); ?>" alt="Profile Picture" class="rounded-circle" style="width:32px;height:32px;object-fit:cover;">
                                <span class="fw-bold"><?php echo htmlspecialchars($testimonial['user_name']); ?></span>
                            </div>
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="rating">
                                    <?php for($i = 0; $i < $testimonial['rating']; $i++): ?>
                                        <i class="ri-star-fill"></i>
                                    <?php endfor; ?>
                                </div>
                                <span class="status-badge status-approved">
                                    Approved
                                </span>
                            </div>
                            <p class="testimonial-message mt-2"><?php echo htmlspecialchars($testimonial['content']); ?></p>
                            <div class="testimonial-meta">
                                <span><i class="ri-time-line"></i> <?php echo date('M d, Y', strtotime($testimonial['created_at'])); ?></span>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Other Users' Testimonials -->
        <div class="testimonial-card mt-4">
            <div class="card-header">
                <h3><i class="ri-chat-quote-line"></i> What Others Say</h3>
            </div>
            <div class="card-body">
                <?php if(mysqli_num_rows($other_testimonials) == 0): ?>
                    <p class="text-muted text-center">No testimonials available yet.</p>
                <?php else: ?>
                    <?php while($testimonial = mysqli_fetch_assoc($other_testimonials)): ?>
                        <div class="testimonial-item mb-4">
                            <div class="d-flex align-items-center mb-2 gap-2">
                                <img src="<?php echo !empty($testimonial['profile_picture']) ? '../' . htmlspecialchars($testimonial['profile_picture']) : 'https://ui-avatars.com/api/?name=' . urlencode($testimonial['user_name']); ?>" alt="Profile Picture" class="rounded-circle" style="width:32px;height:32px;object-fit:cover;">
                                <span class="fw-bold"><?php echo htmlspecialchars($testimonial['user_name']); ?></span>
                            </div>
                            <div class="rating">
                                <?php for($i = 0; $i < $testimonial['rating']; $i++): ?>
                                    <i class="ri-star-fill"></i>
                                <?php endfor; ?>
                            </div>
                            <p class="testimonial-message mt-2"><?php echo htmlspecialchars($testimonial['content']); ?></p>
                            <div class="testimonial-meta">
                                <span><i class="ri-user-line"></i> <?php echo htmlspecialchars($testimonial['user_name']); ?></span>
                                <span><i class="ri-time-line"></i> <?php echo date('M d, Y', strtotime($testimonial['created_at'])); ?></span>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php endif; ?>
            </div>
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

        // Testimonial rating stars
        document.querySelectorAll('.rating-star').forEach(star => {
            star.addEventListener('click', function() {
                const rating = parseInt(this.getAttribute('data-rating'));
                document.getElementById('selected-rating').value = rating;
                document.getElementById('selected-rating-number').textContent = rating;
                document.querySelectorAll('.rating-star').forEach(s => {
                    s.style.color = parseInt(s.getAttribute('data-rating')) <= rating ? '#ffc107' : '#ccc';
                });
            });
        });
        // If editing, show the selected rating
        window.addEventListener('DOMContentLoaded', () => {
            const selected = document.getElementById('selected-rating').value;
            if(selected) {
                document.getElementById('selected-rating-number').textContent = selected;
                document.querySelectorAll('.rating-star').forEach(s => {
                    s.style.color = parseInt(s.getAttribute('data-rating')) <= selected ? '#ffc107' : '#ccc';
                });
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
