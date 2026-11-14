<?php
// Get the current page name for active menu highlighting
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!-- Sidebar -->
<div class="sidebar">
    <div class="sidebar-header">
        <img src="../user/image/logo.jpg" alt="QuickTrip" class="sidebar-logo">
        <div class="header-text">
            <span class="brand-name">QuickTrip</span>
            <span class="admin-text">Admin</span>
        </div>
    </div>
    <style>
        .sidebar-header {
            display: flex;
            align-items: center;
            gap: 5px;
            padding: 10px;
        }
        .sidebar-logo {
            width: 3rem;
            height: 3rem;
            object-fit: cover;
            border-radius: 50%;
        }
        .header-text {
            display: flex;
            flex-direction: column;
            line-height: 1.5;
            margin-top: 3px;
        }
        .brand-name {
            color: #ff6b00;
            font-weight: bold;
            font-size: 1.5rem;
            font-family: 'Playfair Display', serif;
            line-height: 1;
        }
        .admin-text {
            color: #ff6b00;
            font-size: 1.3rem;
            font-family: 'Playfair Display', serif;
        }
    </style>
    <ul class="sidebar-menu">
        <li>
            <a href="dashboard.php" <?php echo ($current_page == 'dashboard.php') ? 'class="active"' : ''; ?>>
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
        </li>
        <li>
            <a href="vehicle_brands.php" <?php echo ($current_page == 'vehicle_brands.php') ? 'class="active"' : ''; ?>>
                <i class="fas fa-car"></i> Vehicle Brands
            </a>
        </li>
        <li>
            <a href="vehicles.php" <?php echo ($current_page == 'vehicles.php') ? 'class="active"' : ''; ?>>
                <i class="fas fa-car-side"></i> Vehicles
            </a>
        </li>
        <li>
            <a href="bookings.php" <?php echo ($current_page == 'bookings.php') ? 'class="active"' : ''; ?>>
                <i class="fas fa-calendar-check"></i> Bookings
            </a>
        </li>
        <li>
            <a href="payments.php" <?php echo ($current_page == 'payments.php') ? 'class="active"' : ''; ?>>
                <i class="fas fa-money-check-alt"></i> Payments
            </a>
        </li>
        <li>
            <a href="pickup_instructions.php" <?php echo ($current_page == 'pickup_instructions.php') ? 'class="active"' : ''; ?>>
                <i class="fas fa-info-circle"></i> Instructions
            </a>
        </li>
        <li>
            <a href="users.php" <?php echo ($current_page == 'users.php') ? 'class="active"' : ''; ?>>
                <i class="fas fa-users"></i> Users
            </a>
        </li>
        <li>
            <a href="testimonials.php" <?php echo ($current_page == 'testimonials.php') ? 'class="active"' : ''; ?>>
                <i class="fas fa-comment-alt"></i> Testimonials
            </a>
        </li>
        <li>
            <a href="contact_queries.php" <?php echo ($current_page == 'contact_queries.php') ? 'class="active"' : ''; ?>>
                <i class="fas fa-envelope"></i> Contact Queries
            </a>
        </li>
        <li>
            <a href="subscribers.php" <?php echo ($current_page == 'subscribers.php') ? 'class="active"' : ''; ?>>
                <i class="fas fa-user-plus"></i> Subscribers
            </a>
        </li>
        <li>
            <a href="footer_content.php" <?php echo ($current_page == 'footer_content.php') ? 'class="active"' : ''; ?>>
                <i class="fas fa-share-alt"></i> Footer Content
            </a>
        </li>
        <li>
            <a href="logout.php">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </li>
    </ul>
</div>
