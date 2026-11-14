<?php
session_name('quicktrip_session');
session_start();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
header('Pragma: no-cache');
require_once('../config/database.php');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

if(isset($_POST['register'])) {
    // Initialize variables with default empty values
    $first_name = isset($_POST['first_name']) ? trim($_POST['first_name']) : '';
    $last_name = isset($_POST['last_name']) ? trim($_POST['last_name']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';
    $confirm_password = isset($_POST['confirm_password']) ? trim($_POST['confirm_password']) : '';
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
    $address = isset($_POST['address']) ? trim($_POST['address']) : '';
    
    // Validation
    $errors = array();
    
    if(empty($first_name)) {
        $errors[] = "First name is required";
    } elseif(strlen($first_name) > 50) {
        $errors[] = "First name must not exceed 50 characters";
    }
    if(empty($last_name)) {
        $errors[] = "Last name is required";
    } elseif(strlen($last_name) > 50) {
        $errors[] = "Last name must not exceed 50 characters";
    }
    if(empty($email)) {
        $errors[] = "Email is required";
    } elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    } elseif(strlen($email) > 30) {
        $errors[] = "Email must not exceed 30 characters";
    }
    if(empty($phone)) {
        $errors[] = "Phone number is required";
    } elseif(!preg_match('/^[0-9]{11}$/', $phone)) {
        $errors[] = "Phone number must be exactly 11 digits (numbers only, e.g. 09123456789)";
    }
    if(empty($address)) {
        $errors[] = "Address is required";
    } elseif(strlen($address) > 255) {
        $errors[] = "Address must not exceed 255 characters";
    }
    if(empty($password)) {
        $errors[] = "Password is required";
    } elseif(strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters";
    } elseif(!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password) || !preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = "Password must contain at least one letter, one number, and one symbol.";
    } elseif(strlen($password) > 30) {
        $errors[] = "Password must not exceed 30 characters";
    }
    if($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }
    // Check if email already exists using prepared statement
    if(empty($errors)) {
        $check_query = "SELECT id FROM users WHERE email = ?";
        $stmt = mysqli_prepare($conn, $check_query);
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if($result === false) {
            $errors[] = "Database error: " . mysqli_error($conn);
        } elseif(mysqli_num_rows($result) > 0) {
            $errors[] = "Email already exists";
        }
        mysqli_stmt_close($stmt);
    }
    // If no errors, proceed with registration
    if(empty($errors)) {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $full_name = $first_name . ' ' . $last_name;
        $phone_full = '+63' . $phone;
        $query = "INSERT INTO users (name, email, password, phone, address, is_admin) VALUES (?, ?, ?, ?, ?, 0)";
        $stmt = mysqli_prepare($conn, $query);
        if($stmt === false) {
            $errors[] = "Failed to prepare statement: " . mysqli_error($conn);
        } else {
            mysqli_stmt_bind_param($stmt, "sssss", $full_name, $email, $password_hash, $phone_full, $address);
            if(mysqli_stmt_execute($stmt)) {
                $_SESSION['success'] = "Registration successful! Please login.";
                header("Location: ../mainlogin.php");
                exit();
            } else {
                $errors[] = "Registration failed: " . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt);
        }
    }
}
// Initialize variables for form values
$form_first_name = isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : '';
$form_last_name = isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : '';
$form_email = isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '';
$form_phone = isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : '';
$form_address = isset($_POST['address']) ? htmlspecialchars($_POST['address']) : '';
// Let's check if we can connect to the database
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
// Let's check the users table structure
$table_check = mysqli_query($conn, "DESCRIBE users");
if($table_check === false) {
    die("Error checking table structure: " . mysqli_error($conn));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Registration - QuickTrip</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <link href="../admin/css/admin-style.css" rel="stylesheet">
    <style>
        .login-card {
            max-width: 500px;
            width: 100%;
            margin: 0 auto;
            padding: 2rem 2.5rem 2.5rem 2.5rem;
            box-sizing: border-box;
        }
        .login-header img {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            display: block;
            margin: 0 auto 1rem auto;
        }
        .login-header h1 {
            text-align: center;
            margin-bottom: 0.5rem;
        }
        .login-header p {
            text-align: center;
            margin-bottom: 1.5rem;
        }
        .login-form {
            width: 100%;
        }
        .input-group {
            display: flex;
            align-items: center;
            margin-bottom: 1.2rem;
        }
        .input-group i, .input-group span {
            margin-right: 10px;
            color: #D35400;
            min-width: 30px;
            text-align: center;
        }
        .input-group .form-control {
            flex-grow: 1;
            width: 400px;
            max-width: 400px;
            padding: 0.6rem 0.75rem;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 1rem;
            background-color: #f7f9fc;
        }
        .input-group .form-control:focus {
            border-color: #D35400;
            outline: none;
            background-color: #fff;
        }
        .input-group .toggle-password-wrapper, .toggle-password {
            display: none !important;
        }
        .form-text {
            font-size: 0.9em;
            color: #888;
            margin-left: 1px;
        }
        .btn-login {
            width: 100%;
            padding: 0.75rem;
            background-color: #D35400;
            border: none;
            color: white;
            font-weight: 700;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1.1rem;
            margin-top: 0.5rem;
            transition: background-color 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        .btn-login:hover {
            background-color: #b34700;
        }
        .alert {
            width: 100%;
            margin: 0 auto 1.5rem;
            padding: 1rem;
            border-radius: 8px;
            background-color: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }
        .alert ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .alert ul li {
            margin-bottom: 0.5rem;
        }
        .alert ul li:last-child {
            margin-bottom: 0;
        }
        .text-center.mt-3 {
            margin-top: 1.5rem !important;
        }
        @media (max-width: 600px) {
            .login-card {
                max-width: 98vw;
                padding: 1rem;
            }
            .input-group .form-control {
                max-width: 98vw;
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="login-page">
        <div class="login-card">
            <div class="login-header">
                <img src="image/logo.jpg" alt="QuickTrip">
                <h1>QuickTrip</h1>
                <p>Create Account</p>
            </div>
            
            <form class="login-form" method="POST" action="">
                <?php if(!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul>
                            <?php foreach($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                <div class="input-group">
                    <i class="fas fa-user"></i>
                    <input type="text" class="form-control" name="first_name" placeholder="Enter your first name" value="<?php echo $form_first_name; ?>" required maxlength="50">
                </div>
                <div class="input-group">
                    <i class="fas fa-user"></i>
                    <input type="text" class="form-control" name="last_name" placeholder="Enter your last name" value="<?php echo $form_last_name; ?>" required maxlength="50">
                </div>
                <div class="input-group">
                    <i class="fas fa-envelope"></i>
                    <input type="email" class="form-control" name="email" placeholder="Enter your email" value="<?php echo $form_email; ?>" required maxlength="30">
                </div>
                <div class="input-group">
                    <i class="fas fa-phone"></i>
                    <input type="text" class="form-control" name="phone" id="phone" placeholder="e.g. 09123456789" value="<?php echo $form_phone; ?>" required maxlength="11" pattern="^[0-9]{11}$">
                </div>
                <div class="input-group">
                    <i class="fas fa-map-marker-alt"></i>
                    <input type="text" class="form-control" name="address" placeholder="Enter your address" value="<?php echo $form_address; ?>" required maxlength="255">
                </div>
                <div class="input-group">
                    <i class="fas fa-lock"></i>
                    <input type="password" class="form-control" name="password" id="password" placeholder="Enter your password" required maxlength="30">
                    <span class="form-text">Password must be at least 6 characters.</span>
                </div>
                <div class="input-group">
                    <i class="fas fa-lock"></i>
                    <input type="password" class="form-control" name="confirm_password" id="confirm_password" placeholder="Confirm your password" required maxlength="30">
                </div>
                <button type="submit" name="register" class="btn-login">
                    <i class="fas fa-user-plus"></i> Create Account
                </button>
                <div class="text-center mt-3">
                    <a href="login.php" style="color: var(--text-light); text-decoration: none;">Already have an account? Login here</a>
                </div>
            </form>
        </div>
    </div>
    <script>
        // Toggle password visibility for main password
        const passwordInput = document.getElementById('password');
        const togglePassword = document.getElementById('togglePassword');
        togglePassword.addEventListener('click', function () {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            this.classList.toggle('fa-eye-slash');
        });
        // Toggle password visibility for confirm password
        const confirmPasswordInput = document.getElementById('confirm_password');
        const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');
        toggleConfirmPassword.addEventListener('click', function () {
            const type = confirmPasswordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            confirmPasswordInput.setAttribute('type', type);
            this.classList.toggle('fa-eye-slash');
        });
    </script>
</body>
</html> 