<?php
session_name('quicktrip_session');
session_start();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
header('Pragma: no-cache');
require_once('../config/database.php');

// Handle AJAX request to mark messages as read
if (isset($_POST['mark_read']) && $_POST['mark_read'] == '1') {
    if(!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit();
    }
    $user_id = $_SESSION['user_id'];
    $update_query = "UPDATE contact_queries SET is_read = 1 WHERE user_id = ? AND admin_reply IS NOT NULL AND is_read = 0";
    $update_stmt = mysqli_prepare($conn, $update_query);
    mysqli_stmt_bind_param($update_stmt, "i", $user_id);
    if (mysqli_stmt_execute($update_stmt)) {
        echo json_encode(['success' => true]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update messages']);
    }
    exit();
}

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

// Get unread messages count and fetch admin replies for inbox modal
$query = "SELECT COUNT(*) as unread_count FROM contact_queries WHERE user_id = ? AND admin_reply IS NOT NULL AND is_read = 0";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$unread_count = $result->fetch_assoc()['unread_count'];

// Fetch admin replies for inbox modal
$inbox_query = "SELECT id, subject, message, admin_reply, created_at FROM contact_queries WHERE user_id = ? AND admin_reply IS NOT NULL ORDER BY created_at DESC";
$inbox_stmt = mysqli_prepare($conn, $inbox_query);
mysqli_stmt_bind_param($inbox_stmt, "i", $user_id);
mysqli_stmt_execute($inbox_stmt);
$inbox_result = mysqli_stmt_get_result($inbox_stmt);

$inbox_messages = [];
while ($row = $inbox_result->fetch_assoc()) {
    $inbox_messages[] = $row;
}

// Handle form submission
if(isset($_POST['submit'])) {
    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);
    
    if(empty($subject) || empty($message)) {
        $error = "All fields are required";
    } else {
        $query = "INSERT INTO contact_queries (user_id, name, email, subject, message, status) VALUES (?, ?, ?, ?, ?, 'pending')";
        $stmt = mysqli_prepare($conn, $query);
        if (!$stmt) {
            $error = "Database error: " . mysqli_error($conn);
            // Log the query and error for debugging
            error_log("Query: " . $query);
            error_log("MySQL Error: " . mysqli_error($conn));
        } else {
            mysqli_stmt_bind_param($stmt, "issss", $user_id, $user['name'], $user['email'], $subject, $message);
            if(mysqli_stmt_execute($stmt)) {
                $success = "Your message has been sent successfully!";
            } else {
                $error = "Error sending message: " . mysqli_error($conn);
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us - QuickTrip Car Rental</title>
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
        .contact-form {
            max-width: 800px;
            margin: 2rem auto;
            padding: 2rem;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .contact-title {
            text-align: center;
            margin-bottom: 2rem;
            color: #000;
        }
        .contact-title h2 {
            color: #000;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        .contact-title h2 i {
            color: var(--primary-color);
        }
        .contact-form-content {
            display: grid;
            gap: 1.5rem;
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
        <div class="contact-form">
            <h2 style="color: #000;" class="contact-title" style="display: flex; justify-content: flex-end; align-items: center; gap: 0.5rem;">
                <i class="ri-customer-service-2-line"></i> Contact Us
                <a href="#" class="mail-icon" id="inboxToggle" title="Inbox" style="margin-left: auto;">
                    <i class="ri-mail-line"></i>
                    <?php if ($unread_count > 0): ?>
                        <span class="notification-badge"><?php echo $unread_count; ?></span>
                    <?php endif; ?>
                </a>
            </h2>
            
            <?php if(isset($success)): ?>
                <div class="alert alert-success">
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <?php if(isset($error)): ?>
                <div class="alert alert-danger">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="mb-3">
                    <label for="name" class="form-label">Name</label>
                <input type="text" class="form-control" id="name" name="name" 
                       value="<?php echo htmlspecialchars($user['name']); ?>" readonly>
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                <input type="email" class="form-control" id="email" name="email" 
                       value="<?php echo htmlspecialchars($user['email']); ?>" readonly>
                </div>

                <div class="mb-3">
                    <label for="subject" class="form-label">Subject</label>
                    <input type="text" class="form-control" id="subject" name="subject" required>
                </div>

                <div class="mb-3">
                    <label for="message" class="form-label">Message</label>
                    <textarea class="form-control" id="message" name="message" rows="5" required></textarea>
                </div>

                <button type="submit" name="submit" class="btn btn-primary">
                    <i class="ri-send-plane-fill"></i> Send Message
                </button>
            </form>
        </div>
    </div>

    <!-- Removed Ticket System Section as per new inbox design -->
    <!-- Inbox modal and icon will show admin replies instead -->

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

    <!-- Modal for Inbox Messages -->
    <div class="modal fade" id="inboxModal" tabindex="-1" aria-labelledby="inboxModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content" style="background-color: #1a1a1a; color: white;">
          <div class="modal-header">
            <h5 class="modal-title" id="inboxModalLabel">Inbox Messages</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body" id="inboxMessagesContainer">
            <p>Loading messages...</p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          </div>
        </div>
      </div>
    </div>

    <script>
        // Inbox modal toggle and load messages from embedded PHP data
        const inboxToggle = document.getElementById('inboxToggle');
        const inboxModal = new bootstrap.Modal(document.getElementById('inboxModal'));
        const inboxMessagesContainer = document.getElementById('inboxMessagesContainer');

        // Embedded inbox messages data from PHP
        const inboxMessages = <?php echo json_encode($inbox_messages); ?>;

        inboxToggle.addEventListener('click', function(e) {
            e.preventDefault();
            inboxModal.show();
            loadInboxMessages();

            // Send AJAX request to mark messages as read
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'mark_read=1'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Hide the unread count badge
                    const badge = document.querySelector('.notification-badge');
                    if (badge) {
                        badge.style.display = 'none';
                    }
                }
            })
            .catch(error => {
                console.error('Error marking messages as read:', error);
            });
        });

        function loadInboxMessages() {
            if (inboxMessages.length === 0) {
                inboxMessagesContainer.innerHTML = '<p>No messages from admin.</p>';
                return;
            }
            let html = '<ul class="list-group">';
            inboxMessages.forEach(msg => {
                html += `
                    <li class="list-group-item bg-dark text-white mb-2">
                        <h6>${escapeHtml(msg.subject)}</h6>
                        <p><strong>Your message:</strong> ${escapeHtml(msg.message)}</p>
                        <p><strong>Admin reply:</strong> ${escapeHtml(msg.admin_reply)}</p>
                        <small class="text-muted">${msg.created_at}</small>
                    </li>
                `;
            });
            html += '</ul>';
            inboxMessagesContainer.innerHTML = html;
        }

        function escapeHtml(text) {
            var map = {
                '&': '&amp;',
                '<': '<',
                '>': '>',
                '"': '"',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, function(m) { return map[m]; });
        }
    </script>
</body>
</html>
