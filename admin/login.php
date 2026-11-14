<?php
header("Location: ../mainlogin.php");
exit();

session_name('quicktrip_session');
session_start();
require_once('../config/database.php');

if(isset($_POST['login'])) {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    
    if(empty($email) || empty($password)) {
        $error = "Please enter both email and password";
    } else {
        // Use mysqli_real_escape_string for additional safety
        $email = mysqli_real_escape_string($conn, $email);
        
        // Create the query
        $query = "SELECT id, name, email, password FROM users WHERE email = '$email' AND is_admin = 1";
        $result = mysqli_query($conn, $query);
        
        if($result && mysqli_num_rows($result) > 0) {
            $admin = mysqli_fetch_assoc($result);
            
            // Check if password matches MD5, SHA256, or bcrypt
            $md5_hash = md5($password);
            $sha2_hash = hash('sha256', $password);
            if (
                (strlen($admin['password']) === 32 && $md5_hash === $admin['password']) || // MD5
                (strlen($admin['password']) === 64 && $sha2_hash === $admin['password']) || // SHA256
                (password_verify($password, $admin['password'])) // bcrypt
            ) {
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_name'] = $admin['name'];
                $_SESSION['admin_email'] = $admin['email'];
                
                // Free the result before redirecting
                mysqli_free_result($result);
                
                header("Location: dashboard.php");
                exit();
            } else {
                $error = "Invalid email or password";
            }
        } else {
            $error = "Invalid email or password";
        }
        
        // Only free result if it exists
        if($result) {
            mysqli_free_result($result);
        }
    }
}
?>
