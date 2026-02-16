<?php

$db_server = "localhost";
$db_user   = "root";
$db_pass   = "";
$db_name   = "haucredit_db";

/* Connect to MySQL */
$conn = mysqli_connect($db_server, $db_user, $db_pass);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

/* Create Database */
if (!mysqli_query($conn, "CREATE DATABASE IF NOT EXISTS `$db_name`")) {
    die("Database creation failed: " . mysqli_error($conn));
}

/* Select Database */
mysqli_select_db($conn, $db_name);

/* Create Tables */
$sql = "
CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    user_name VARCHAR(50) NOT NULL UNIQUE,
    user_password VARCHAR(255) NOT NULL,
    user_email VARCHAR(100) NOT NULL UNIQUE,
    stud_num VARCHAR(50) NOT NULL UNIQUE,
    org_body VARCHAR(200) NOT NULL,
    user_reg_date DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS events (
    event_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    organizing_body VARCHAR(100) NOT NULL,
    background VARCHAR(100) NOT NULL,
    activity_type VARCHAR(150) NOT NULL,
    series VARCHAR(100) NULL,
    nature VARCHAR(255) NOT NULL,
    event_name VARCHAR(255) NOT NULL,
    start_datetime DATETIME NOT NULL,
    end_datetime DATETIME NOT NULL,
    number_of_participants INT NOT NULL,
    venue_platform VARCHAR(255) NOT NULL,
    is_extraneous ENUM('Yes', 'No') NOT NULL,
    target_metric VARCHAR(255) NOT NULL,
    distance VARCHAR(100) NULL,
    participant_range VARCHAR(50) NULL,
    overnight ENUM('Yes', 'No') NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS event_requirements (
    requirement_id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    requirement_name VARCHAR(255) NOT NULL,
    is_required BOOLEAN DEFAULT TRUE,
    is_submitted BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (event_id) REFERENCES events(event_id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

/* Use multi_query for multiple SQL statements */
if (mysqli_multi_query($conn, $sql)) {
    do {
        // flush results
    } while (mysqli_next_result($conn));
} else {
    die("Table creation failed: " . mysqli_error($conn));
}

/* Insert default admin */
$insert_sql = "
INSERT IGNORE INTO users
(user_name, user_password, user_email, stud_num, org_body, user_reg_date)
VALUES
('admin', '203', 'admin@hau.edu.ph', '203', 'SOC', NOW())
";

if (mysqli_query($conn, $insert_sql)) {
    echo "Setup completed successfully!";
} else {
    echo "Insert error: " . mysqli_error($conn);
}

mysqli_close($conn);
?>
