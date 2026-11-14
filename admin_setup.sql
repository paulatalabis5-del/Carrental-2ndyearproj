-- Add is_admin column if it doesn't exist
ALTER TABLE users ADD COLUMN IF NOT EXISTS is_admin TINYINT(1) DEFAULT 0;

-- Create admin account
INSERT INTO users (name, email, password, is_admin) 
VALUES ('Admin', 'admin@admin.com', MD5('admin123'), 1); 