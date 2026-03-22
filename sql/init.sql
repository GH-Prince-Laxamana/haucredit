-- Initialize HAUCredit Database
-- This script runs automatically when the MySQL container starts

-- Create the database if it doesn't exist
CREATE DATABASE IF NOT EXISTS `haucredit_db` 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

-- Create the user if it doesn't exist (for MySQL 8.0+)
CREATE USER IF NOT EXISTS 'dbuser'@'%' IDENTIFIED BY 'dbpassword';

-- Grant all privileges on haucredit_db to dbuser
GRANT ALL PRIVILEGES ON `haucredit_db`.* TO 'dbuser'@'%';

-- Also grant privileges on all databases for safety
GRANT ALL PRIVILEGES ON *.* TO 'dbuser'@'%' WITH GRANT OPTION;

-- Flush privileges to apply changes
FLUSH PRIVILEGES;
