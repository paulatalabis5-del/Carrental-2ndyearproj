<?php
require_once('../../config/database.php');

if(isset($_POST['submit'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);
    
    if(empty($name) || empty($email) || empty($subject) || empty($message)) {
        $error = "All fields are required";
    } elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format";
    } else {
        $query = "INSERT INTO contact_queries (user_id, name, email, subject, message, status) VALUES (NULL, ?, ?, ?, ?, 'pending')";
        $stmt = mysqli_prepare($conn, $query);
        if (!$stmt) {
            $error = "Database error: " . mysqli_error($conn);
        } else {
            mysqli_stmt_bind_param($stmt, "ssss", $name, $email, $subject, $message);
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
    <div class="contact-form mt-5">
        <div class="contact-title">
            <h2><i class="ri-customer-service-line"></i> Contact Us</h2>
            <p>Have a question or need help? Fill out the form below and we'll get back to you soon!</p>
        </div>
        <?php if(isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        <?php if(isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        <form method="POST" action="">
            <div class="mb-3">
                <label for="name" class="form-label">Name</label>
                <input type="text" class="form-control" id="name" name="name" required maxlength="100">
            </div>
            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" class="form-control" id="email" name="email" required maxlength="100">
            </div>
            <div class="mb-3">
                <label for="subject" class="form-label">Subject</label>
                <input type="text" class="form-control" id="subject" name="subject" required maxlength="100">
            </div>
            <div class="mb-3">
                <label for="message" class="form-label">Message</label>
                <textarea class="form-control" id="message" name="message" rows="5" required maxlength="1000"></textarea>
            </div>
            <button type="submit" name="submit" class="btn btn-primary">Send Message</button>
        </form>
    </div>
    <footer class="footer mt-5">
        <div class="container text-center py-3">
            <p class="mb-0">&copy; <?php echo date('Y'); ?> QuickTrip Car Rental. All rights reserved.</p>
        </div>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 