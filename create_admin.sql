-- Add is_admin column if it doesn't exist
ALTER TABLE users ADD COLUMN IF NOT EXISTS is_admin TINYINT(1) DEFAULT 0;

-- Create new admin account
INSERT INTO users (name, email, password, is_admin) 
VALUES ('MainAdmin', 'mainadmin@carrental.com', MD5('Admin@123'), 1); 