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

// Handle form submission
if(isset($_POST['update_profile'])) {
    $name = mysqli_real_escape_string($conn, trim($_POST['name']));
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    $phone = mysqli_real_escape_string($conn, trim($_POST['phone']));
$province = mysqli_real_escape_string($conn, trim($_POST['province']));
$municipality = mysqli_real_escape_string($conn, trim($_POST['municipality']));
$barangay = mysqli_real_escape_string($conn, trim($_POST['barangay']));
$address = $province . ', ' . $municipality . ', ' . $barangay;
    $current_password = trim($_POST['current_password']);
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);

    // Get current user data
    $query = "SELECT * FROM users WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    if ($stmt === false) {
        die("Error preparing statement: " . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$user) {
        $error_message = "User not found!";
    } else {
        // Check if email already exists for another user
        $email_check_query = "SELECT id FROM users WHERE email = ? AND id != ?";
        $stmt = mysqli_prepare($conn, $email_check_query);
        mysqli_stmt_bind_param($stmt, "si", $email, $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        
        if(mysqli_stmt_num_rows($stmt) > 0) {
            $error_message = "Email already exists!";
        } else {
            // If changing password
            if(!empty($current_password)) {
                if(md5($current_password) !== $user['password']) {
                    $error_message = "Current password is incorrect!";
                } elseif($new_password !== $confirm_password) {
                    $error_message = "New passwords do not match!";
                } elseif(strlen($new_password) < 6) {
                    $error_message = "Password must be at least 6 characters long!";
                } else {
                    $hashed_password = md5($new_password);
                    // Handle profile picture upload
                    $profile_picture = $user['profile_picture'] ?? '';
                    if(isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
                        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
                        $filename = $_FILES['profile_picture']['name'];
                        $file_ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                        if(in_array($file_ext, $allowed)) {
                            $upload_dir = '../uploads/profile_pictures/';
                            if (!file_exists($upload_dir)) {
                                mkdir($upload_dir, 0777, true);
                            }
                            $new_filename = uniqid('profile_') . '.' . $file_ext;
                            $destination = $upload_dir . $new_filename;
                            if(move_uploaded_file($_FILES['profile_picture']['tmp_name'], $destination)) {
                                $profile_picture = 'uploads/profile_pictures/' . $new_filename;
                            }
                        }
                    }
                    $update_query = "UPDATE users SET name = ?, email = ?, phone = ?, address = ?, profile_picture = ?, password = ? WHERE id = ?";
                    $stmt = mysqli_prepare($conn, $update_query);
                    mysqli_stmt_bind_param($stmt, "ssssssi", $name, $email, $phone, $address, $profile_picture, $hashed_password, $user_id);
                    
                    if(mysqli_stmt_execute($stmt)) {
                        $_SESSION['user_name'] = $name;
                        $_SESSION['user_email'] = $email;
                        $success_message = "Profile updated successfully!";
                    } else {
                        $error_message = "Error updating profile: " . mysqli_error($conn);
                    }
                }
            } else {
                // Update without password change
                // Handle profile picture upload
                $profile_picture = $user['profile_picture'] ?? '';
                if(isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
                    $allowed = ['jpg', 'jpeg', 'png', 'webp'];
                    $filename = $_FILES['profile_picture']['name'];
                    $file_ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                    if(in_array($file_ext, $allowed)) {
                        $upload_dir = '../uploads/profile_pictures/';
                        if (!file_exists($upload_dir)) {
                            mkdir($upload_dir, 0777, true);
                        }
                        $new_filename = uniqid('profile_') . '.' . $file_ext;
                        $destination = $upload_dir . $new_filename;
                        if(move_uploaded_file($_FILES['profile_picture']['tmp_name'], $destination)) {
                            $profile_picture = 'uploads/profile_pictures/' . $new_filename;
                        }
                    }
                }
                $update_query = "UPDATE users SET name = ?, email = ?, phone = ?, address = ?, profile_picture = ? WHERE id = ?";
                $stmt = mysqli_prepare($conn, $update_query);
                mysqli_stmt_bind_param($stmt, "sssssi", $name, $email, $phone, $address, $profile_picture, $user_id);
                
                if(mysqli_stmt_execute($stmt)) {
                    $_SESSION['user_name'] = $name;
                    $_SESSION['user_email'] = $email;
                    $success_message = "Profile updated successfully!";
                } else {
                    $error_message = "Error updating profile: " . mysqli_error($conn);
                }
            }
            
            mysqli_stmt_close($stmt);
        }
    }
}

// Get current user data for display
$query = "SELECT * FROM users WHERE id = '$user_id'";
$result = mysqli_query($conn, $query);
if($result === false) {
    die("Error fetching user: " . mysqli_error($conn));
}
$user = mysqli_fetch_assoc($result);
mysqli_free_result($result);

// Get unread messages count
$query = "SELECT COUNT(*) as unread_count FROM contact_queries WHERE user_id = ? AND admin_reply IS NOT NULL AND is_read = 0";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$unread_count = $result->fetch_assoc()['unread_count'];

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Profile - QuickTrip Car Rental</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet" />
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            padding-top: 80px;
        }
        nav {
            background-color: var(--primary-color-dark);
        }
        .nav__links a.active {
            color: var(--primary-color) !important;
        }
        .profile-container {
            margin-top: 2rem;
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

    <div class="profile-container">
        <div class="profile-card">
            <div class="card-header d-flex align-items-center gap-3">
                <div>
                    <img src="<?php echo !empty($user['profile_picture']) ? '../' . htmlspecialchars($user['profile_picture']) : 'https://ui-avatars.com/api/?name=' . urlencode($user['name']); ?>" alt="Profile Picture" class="rounded-circle" style="width:60px;height:60px;object-fit:cover;">
                </div>
                <h3 class="mb-0"><i class="ri-user-line"></i> My Profile</h3>
            </div>
            <div class="card-body">
                <?php if($success_message): ?>
                    <div class="alert alert-success">
                        <i class="ri-checkbox-circle-line"></i>
                        <?php echo htmlspecialchars($success_message); ?>
                    </div>
                <?php endif; ?>
                <?php if($error_message): ?>
                    <div class="alert alert-danger">
                        <i class="ri-error-warning-line"></i>
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="name" class="form-label">Name</label>
                        <input type="text" maxlength="50" class="form-control" id="name" name="name" 
                               value="<?php echo htmlspecialchars($user['name']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="phone" class="form-label">Phone Number</label>
                        <div class="input-group">
                            <span class="input-group-text" id="phonePrefix">+63</span>
                            <input type="text" maxlength="11" pattern="\d{11}" title="Enter 11 digit phone number" class="form-control" id="phone" name="phone" value="<?php echo isset($user['phone']) ? htmlspecialchars(preg_replace('/^\+63/', '', $user['phone'])) : ''; ?>" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <div class="row g-2">
                            <div class="col">
                                <select class="form-select" id="province" name="province" required>
                                    <option value="" disabled <?php echo empty($user['province']) ? 'selected' : ''; ?>>Select Province</option>
                                    <option value="Laguna" <?php echo (isset($user['province']) && $user['province'] === 'Laguna') ? 'selected' : ''; ?>>Laguna</option>
                                </select>
                            </div>
                            <div class="col">
                                <select class="form-select" id="municipality" name="municipality" required>
                                    <option value="" disabled <?php echo empty($user['municipality']) ? 'selected' : ''; ?>>Select Municipality/City</option>
                                    <option value="Santa Cruz" <?php echo (isset($user['municipality']) && $user['municipality'] === 'Santa Cruz') ? 'selected' : ''; ?>>Santa Cruz</option>
                                </select>
                            </div>
                            <div class="col">
                                <select class="form-select" id="barangay" name="barangay" required>
                                    <option value="" disabled <?php echo empty($user['barangay']) ? 'selected' : ''; ?>>Select Barangay</option>
                                    <option value="Alipit" <?php echo (isset($user['barangay']) && $user['barangay'] === 'Alipit') ? 'selected' : ''; ?>>Alipit</option>
                                    <option value="Bagumbayan" <?php echo (isset($user['barangay']) && $user['barangay'] === 'Bagumbayan') ? 'selected' : ''; ?>>Bagumbayan</option>
                                    <option value="Bubukal" <?php echo (isset($user['barangay']) && $user['barangay'] === 'Bubukal') ? 'selected' : ''; ?>>Bubukal</option>
                                    <option value="Calios" <?php echo (isset($user['barangay']) && $user['barangay'] === 'Calios') ? 'selected' : ''; ?>>Calios</option>
                                    <option value="Duhat" <?php echo (isset($user['barangay']) && $user['barangay'] === 'Duhat') ? 'selected' : ''; ?>>Duhat</option>
                                    <option value="Gatid" <?php echo (isset($user['barangay']) && $user['barangay'] === 'Gatid') ? 'selected' : ''; ?>>Gatid</option>
                                    <option value="Jasaan" <?php echo (isset($user['barangay']) && $user['barangay'] === 'Jasaan') ? 'selected' : ''; ?>>Jasaan</option>
                                    <option value="Labuin" <?php echo (isset($user['barangay']) && $user['barangay'] === 'Labuin') ? 'selected' : ''; ?>>Labuin</option>
                                    <option value="Malinao" <?php echo (isset($user['barangay']) && $user['barangay'] === 'Malinao') ? 'selected' : ''; ?>>Malinao</option>
                                    <option value="Oogong" <?php echo (isset($user['barangay']) && $user['barangay'] === 'Oogong') ? 'selected' : ''; ?>>Oogong</option>
                                    <option value="Pagsawitan" <?php echo (isset($user['barangay']) && $user['barangay'] === 'Pagsawitan') ? 'selected' : ''; ?>>Pagsawitan</option>
                                    <option value="Palasan" <?php echo (isset($user['barangay']) && $user['barangay'] === 'Palasan') ? 'selected' : ''; ?>>Palasan</option>
                                    <option value="Patimbao" <?php echo (isset($user['barangay']) && $user['barangay'] === 'Patimbao') ? 'selected' : ''; ?>>Patimbao</option>
                                    <option value="Poblacion I" <?php echo (isset($user['barangay']) && $user['barangay'] === 'Poblacion I') ? 'selected' : ''; ?>>Poblacion I</option>
                                    <option value="Poblacion II" <?php echo (isset($user['barangay']) && $user['barangay'] === 'Poblacion II') ? 'selected' : ''; ?>>Poblacion II</option>
                                    <option value="Poblacion III" <?php echo (isset($user['barangay']) && $user['barangay'] === 'Poblacion III') ? 'selected' : ''; ?>>Poblacion III</option>
                                    <option value="Poblacion IV" <?php echo (isset($user['barangay']) && $user['barangay'] === 'Poblacion IV') ? 'selected' : ''; ?>>Poblacion IV</option>
                                    <option value="Poblacion V" <?php echo (isset($user['barangay']) && $user['barangay'] === 'Poblacion V') ? 'selected' : ''; ?>>Poblacion V</option>
                                    <option value="San Jose" <?php echo (isset($user['barangay']) && $user['barangay'] === 'San Jose') ? 'selected' : ''; ?>>San Jose</option>
                                    <option value="San Juan" <?php echo (isset($user['barangay']) && $user['barangay'] === 'San Juan') ? 'selected' : ''; ?>>San Juan</option>
                                    <option value="San Pablo Norte" <?php echo (isset($user['barangay']) && $user['barangay'] === 'San Pablo Norte') ? 'selected' : ''; ?>>San Pablo Norte</option>
                                    <option value="San Pablo Sur" <?php echo (isset($user['barangay']) && $user['barangay'] === 'San Pablo Sur') ? 'selected' : ''; ?>>San Pablo Sur</option>
                                    <option value="Santisima Cruz" <?php echo (isset($user['barangay']) && $user['barangay'] === 'Santisima Cruz') ? 'selected' : ''; ?>>Santisima Cruz</option>
                                    <option value="Santo Angel Central" <?php echo (isset($user['barangay']) && $user['barangay'] === 'Santo Angel Central') ? 'selected' : ''; ?>>Santo Angel Central</option>
                                    <option value="Santo Angel Norte" <?php echo (isset($user['barangay']) && $user['barangay'] === 'Santo Angel Norte') ? 'selected' : ''; ?>>Santo Angel Norte</option>
                                    <option value="Santo Angel Sur" <?php echo (isset($user['barangay']) && $user['barangay'] === 'Santo Angel Sur') ? 'selected' : ''; ?>>Santo Angel Sur</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" 
                               value="<?php echo htmlspecialchars($user['email']); ?>" required readonly>
                    </div>
                    <div class="mb-3">
                        <label for="profile_picture" class="form-label">Profile Picture</label>
                        <input type="file" class="form-control" id="profile_picture" name="profile_picture" accept="image/*">
                    </div>
                    <hr>
                    <div class="password-section">
                        <h5><i class="ri-lock-line"></i> Change Password (optional)</h5>
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Current Password (must be at least 6 characters long)</label>
                            <input type="password" class="form-control" id="current_password" name="current_password">
                        </div>
                        <div class="mb-3">
                            <label for="new_password" class="form-label">New Password (must be at least 6 characters long)</label>
                            <input type="password" class="form-control" id="new_password" name="new_password">
                        </div>
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm New Password (must be at least 6 characters long)</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                        </div>
                    </div>
                    <div class="d-grid">
                        <button type="submit" name="update_profile" class="btn btn-primary">
                            <i class="ri-save-line"></i> Update Profile
                        </button>
                    </div>
                </form>
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

        // Example data for municipalities and barangays
        const municipalities = {
            'Laguna': ['Santa Cruz']
        };

        const barangays = {
            'Santa Cruz': ['Alipit', 'Bagumbayan', 'Bubukal', 'Calios', 'Duhat', 'Gatid', 'Jasaan', 'Labuin', 'Malinao', 'Oogong', 'Pagsawitan', 'Palasan', 'Patimbao', 'Poblacion I', 'Poblacion II', 'Poblacion III', 'Poblacion IV', 'Poblacion V', 'San Jose', 'San Juan', 'San Pablo Norte', 'San Pablo Sur', 'Santisima Cruz', 'Santo Angel Central', 'Santo Angel Norte', 'Santo Angel Sur']
        };

        const provinceSelect = document.getElementById('province');
        const municipalitySelect = document.getElementById('municipality');
        const barangaySelect = document.getElementById('barangay');

        function populateMunicipalities() {
            const selectedProvince = provinceSelect.value;
            municipalitySelect.innerHTML = '<option value="">Select Municipality/City</option>';
            barangaySelect.innerHTML = '<option value="">Select Barangay</option>';
            if (selectedProvince && municipalities[selectedProvince]) {
                municipalities[selectedProvince].forEach(mun => {
                    const option = document.createElement('option');
                    option.value = mun;
                    option.textContent = mun;
                    municipalitySelect.appendChild(option);
                });
            }
            // If user address contains municipality, select it
            <?php if (!empty($user['address'])): ?>
            const userAddress = <?php echo json_encode($user['address']); ?>;
            municipalities[selectedProvince]?.forEach(mun => {
                if(userAddress.includes(mun)) {
                    municipalitySelect.value = mun;
                    populateBarangays();
                }
            });
            <?php endif; ?>
        }

        function populateBarangays() {
            const selectedMunicipality = municipalitySelect.value;
            barangaySelect.innerHTML = '<option value="">Select Barangay</option>';
            if (selectedMunicipality && barangays[selectedMunicipality]) {
                barangays[selectedMunicipality].forEach(bar => {
                    const option = document.createElement('option');
                    option.value = bar;
                    option.textContent = bar;
                    barangaySelect.appendChild(option);
                });
            }
            // If user address contains barangay, select it
            <?php if (!empty($user['address'])): ?>
            const userAddress = <?php echo json_encode($user['address']); ?>;
            barangays[municipalitySelect.value]?.forEach(bar => {
                if(userAddress.includes(bar)) {
                    barangaySelect.value = bar;
                }
            });
            <?php endif; ?>
        }

        provinceSelect.addEventListener('change', populateMunicipalities);
        municipalitySelect.addEventListener('change', populateBarangays);

        // Initialize selects on page load
        window.addEventListener('DOMContentLoaded', () => {
            if(provinceSelect.value) {
                populateMunicipalities();
            }
            // Set selects to user's saved address as default after population
            <?php if (!empty($user['province'])): ?>
                provinceSelect.value = <?php echo json_encode($user['province']); ?>;
            <?php endif; ?>
            <?php if (!empty($user['municipality'])): ?>
                municipalitySelect.value = <?php echo json_encode($user['municipality']); ?>;
            <?php endif; ?>
            <?php if (!empty($user['barangay'])): ?>
                barangaySelect.value = <?php echo json_encode($user['barangay']); ?>;
            <?php endif; ?>
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
