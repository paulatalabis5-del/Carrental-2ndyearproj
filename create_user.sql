CREATE USER 'car_rental'@'localhost' IDENTIFIED BY 'car_rental123';
GRANT ALL PRIVILEGES ON car_rental.* TO 'car_rental'@'localhost';
FLUSH PRIVILEGES; 