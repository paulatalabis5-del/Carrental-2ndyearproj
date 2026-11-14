# Car Rental System

A PHP-based car rental system with user and admin panels.

## Front-page preview without a database

The root `index.html` is the standalone homepage for GitHub Pages. It includes
its styles and vehicle illustrations, so it can be served without PHP or
database access.

To view the front page with the VS Code **Live Server** extension:

1. Open `frontpage/index.html` in VS Code.
2. Right-click inside the file and select **Open with Live Server**.

This additional static preview uses the shared stylesheet and images in `user/`
and displays sample pickup instructions and vehicles. The PHP application
remains the database-powered version of the page.

## Features

### User Panel
- User Registration and Login
- Car Booking
- Booking History
- Profile Management
- Testimonials

### Admin Panel
- Vehicle Brand Management
- Vehicle Management
- Booking Management
- User Management
- Testimonial Management

## Setup Instructions

1. **Database Setup**
   - Create a MySQL database
   - Import the `database.sql` file to create the required tables

2. **Configuration**
   - Navigate to `config/database.php`
   - Update the database credentials according to your setup:
     ```php
     $host = "localhost";
     $dbname = "car_rental";
     $username = "your_username";
     $password = "your_password";
     ```

3. **Server Requirements**
   - PHP 7.4 or higher
   - MySQL 5.7 or higher
   - Apache/Nginx web server

4. **Installation**
   - Clone or download the repository
   - Place the files in your web server directory
   - Configure your web server to point to the project directory
   - Access the application through your web browser

## Directory Structure

```
├── config/
│   └── database.php
├── user/
│   ├── login.php
│   ├── register.php
│   ├── dashboard.php
│   ├── car_booking.php
│   ├── booking_history.php
│   ├── testimonials.php
│   ├── profile.php
│   └── logout.php
├── admin/
│   └── [admin panel files]
├── database.sql
└── README.md
```

## Security Features
- Password Hashing
- Session Management
- SQL Injection Prevention
- XSS Protection

## License
This project is licensed under the MIT License. 
