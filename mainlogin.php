<?php
session_name('quicktrip_session');
session_start();
require_once('config/database.php');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['login'])) {
        $email = trim($_POST['email']);
        $password = trim($_POST['password']);

        $query = "SELECT * FROM users WHERE email = ?";
        $stmt = mysqli_prepare($conn, $query);
        if ($stmt === false) {
            die("Error preparing statement: " . mysqli_error($conn));
        }
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($result === false) {
            die("Error executing query: " . mysqli_error($conn));
        }
        $user = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if ($user) {
            if (!empty($user['is_frozen']) && $user['is_frozen'] == 1) {
                $error = "Account is frozen: " . htmlspecialchars($user['freeze_reason']);
            } else {
                if (strlen($user['password']) === 32) {
                    if (md5($password) === $user['password']) {
                        $new_hash = password_hash($password, PASSWORD_DEFAULT);
                        $update_query = "UPDATE users SET password = ? WHERE id = ?";
                        $update_stmt = mysqli_prepare($conn, $update_query);
                        mysqli_stmt_bind_param($update_stmt, "si", $new_hash, $user['id']);
                        mysqli_stmt_execute($update_stmt);
                        mysqli_stmt_close($update_stmt);

                        if ($user['is_admin'] == 1) {
                            $_SESSION['admin_id'] = $user['id'];
                            $_SESSION['admin_email'] = $user['email'];
                            $_SESSION['admin_name'] = $user['name'];
                            header("Location: admin/dashboard.php");
                        } else {
                            $_SESSION['user_id'] = $user['id'];
                            $_SESSION['user_email'] = $user['email'];
                            $_SESSION['user_name'] = $user['name'];
                            header("Location: user/index.php");
                        }
                        exit();
                    } else {
                        $error = "Invalid email or password";
                    }
                } elseif (password_verify($password, $user['password'])) {
                    if ($user['is_admin'] == 1) {
                        $_SESSION['admin_id'] = $user['id'];
                        $_SESSION['admin_email'] = $user['email'];
                        $_SESSION['admin_name'] = $user['name'];
                        header("Location: admin/dashboard.php");
                    } else {
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_email'] = $user['email'];
                        $_SESSION['user_name'] = $user['name'];
                        header("Location: user/index.php");
                    }
                    exit();
                } else {
                    $error = "Invalid email or password";
                }
            }
        } else {
            $error = "Invalid email or password";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Login - QuickTrip</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet" />
    
    <style>
        body {
            background-color: #f0f2f5;
            font-family: 'Roboto', sans-serif;
            margin: 0;
            padding: 0;
            background-color: #d35400;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        .login-container {
            width: 400px;
            margin: 0;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            padding: 2rem 2.5rem 3rem 2.5rem;
        }

         img {
            display: block;
            margin-left: auto;
            margin-right: auto;
            width: 100px;
            height: 100px;
            border-radius: 50%;
            margin-bottom: 1rem;
          }
        h1 {
            text-align: center;
            margin-bottom: 1rem;
            color: #d35400;
            font-weight: 700;
        }
        .form-group {
            margin-bottom: 1.25rem;
            position: relative;
        }
        .input-group {
            position: relative;
        }
        .input-group i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
            font-size: 1.1rem;
        }
        .form-control {
            width: 85%;
            padding: 0.6rem 0.75rem 0.6rem 2.8rem;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 1rem;
            transition: border-color 0.3s;
            background-color: #f7f9fc;
        }
        .form-control:focus {
            border-color: #D35400;
            outline: none;
            background-color: #fff;
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
            transition: background-color 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        .btn-login:hover {
            background-color: #D35400;
        }
        .btn-login i {
            font-size: 1.2rem;
        }
        .error-message {
            color: #c0392b;
            margin-bottom: 1rem;
            font-weight: 700;
            text-align: center;
        }
        .register-link {
            margin-top: 1rem;
            text-align: center;
            font-size: 0.9rem;
        }
        .register-link a {
            color: #D35400;
            text-decoration: none;
            font-weight: 600;
        }
        .register-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <img src="user/image/logo.jpg" alt="QuickTrip Logo" />
                <h1>QuickTrip</h1>
            </div>
            <?php if ($error): ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <form method="POST" action="">
                <div class="form-group input-group">
                    <i class="fas fa-envelope"></i>
                    <input type="email" name="email" placeholder="Enter your email" class="form-control" required />
                </div>
                <div class="form-group input-group">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="password" placeholder="Enter your password" class="form-control" required />
                </div>
                <button type="submit" name="login" class="btn-login">
                    <i class="fas fa-sign-in-alt"></i> Login
                </button>
                <div class="register-link">
                    <a href="user/register.php">Don't have an account? Register here</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
