<?php
header("Location: ../mainlogin.php");
exit();

session_name('quicktrip_session');
session_start();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
header('Pragma: no-cache');
require_once('../config/database.php');

if(isset($_POST['login'])) {
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
    
    // Always clear $frozen_reason unless account is frozen
    $frozen_reason = null;
    
    if($user) {
        // Check if account is frozen
        if (!empty($user['is_frozen']) && $user['is_frozen'] == 1) {
            $frozen_reason = $user['freeze_reason'];
            $error = "Account is frozen";
        } else {
            // Check if password is using old MD5 hash
            if(strlen($user['password']) === 32) { // MD5 hash is 32 characters
                // Verify with MD5 for backward compatibility
                if(md5($password) === $user['password']) {
                    // Update to new password hash
                    $new_hash = password_hash($password, PASSWORD_DEFAULT);
                    $update_query = "UPDATE users SET password = ? WHERE id = ?";
                    $update_stmt = mysqli_prepare($conn, $update_query);
                    mysqli_stmt_bind_param($update_stmt, "si", $new_hash, $user['id']);
                    mysqli_stmt_execute($update_stmt);
                    mysqli_stmt_close($update_stmt);
                    
                    // Login successful
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_name'] = $user['name'];
                    header("Location: index.php");
                    exit();
                } else {
                    $error = "Invalid email or password";
                }
            } 
            // Check if password matches new hash
            elseif(password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_name'] = $user['name'];
                header("Location: index.php");
                exit();
            } else {
                $error = "Invalid email or password";
            }
        }
    } else {
        $error = "Invalid email or password";
    }

    if(isset($error)) {
        error_log('Login error: ' . $error);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Login - QuickTrip</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <link href="../admin/css/admin-style.css" rel="stylesheet">
    <!-- Bootstrap CSS for modal -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="login-page">
        <div class="login-card">
            <div class="login-header">
                <img src="image/logo.jpg" alt="QuickTrip">
                <h1>QuickTrip</h1>
                <p>User Login</p>
            </div>
            
            <form class="login-form" action="login.php" method="POST">
                <?php if(isset($error)): ?>
                    <div class="alert alert-danger">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <div class="input-group">
                    <i class="fas fa-envelope"></i>
                    <input type="email" class="form-control" name="email" placeholder="Enter your email" required value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>">
                </div>
                
                <div class="input-group">
                    <i class="fas fa-lock"></i>
                    <input type="password" class="form-control" name="password" placeholder="Enter your password" required>
                </div>
                
                <button type="submit" name="login" class="btn-login">
                    <i class="fas fa-sign-in-alt"></i> Login
                </button>

                <div class="text-center mt-3">
                    <a href="register.php" style="color: var(--text-light); text-decoration: none;">Don't have an account? Register here</a>
                </div>
            </form>
        </div>
    </div>

    <?php if(isset($frozen_reason)): ?>
    <!-- Frozen Account Modal -->
    <div class="modal fade" id="frozenModal" tabindex="-1" aria-labelledby="frozenModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header bg-danger text-white">
            <h5 class="modal-title" id="frozenModalLabel">Account Frozen</h5>
          </div>
          <div class="modal-body">
            <p>Your account is frozen due to: <strong><?php echo htmlspecialchars($frozen_reason); ?></strong></p>
            <p>Please walk in to company to unfreeze your account.</p>
          </div>
          <div class="modal-footer">
            <a href="logout.php" class="btn btn-danger">Logout</a>
          </div>
        </div>
      </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var frozenModal = new bootstrap.Modal(document.getElementById('frozenModal'));
        frozenModal.show();
    });
    </script>
    <?php else: ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <?php endif; ?>
</body>
</html> 