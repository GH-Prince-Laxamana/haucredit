<?php

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$db_server = "localhost";
$db_user = "root";
$db_pass = "";
$db_name = "haucredit_db";

try {
    $conn = mysqli_connect($db_server, $db_user, $db_pass);

    mysqli_query($conn, "CREATE DATABASE IF NOT EXISTS `$db_name`");

    mysqli_select_db($conn, $db_name);

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

    CREATE TABLE IF NOT EXISTS password_resets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        token_hash VARCHAR(255) NOT NULL,
        expires_at DATETIME NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
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
        participants VARCHAR(255) NOT NULL,
        venue_platform VARCHAR(255) NOT NULL,
        is_extraneous ENUM('Yes', 'No') NOT NULL,
        target_metric VARCHAR(255) NOT NULL,
        distance VARCHAR(100) NULL,
        participant_range VARCHAR(50) NULL,
        overnight TINYINT(1) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(user_id)
            ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";

    mysqli_multi_query($conn, $sql);
    while (mysqli_more_results($conn) && mysqli_next_result($conn))
        ;

    $adminPass = password_hash("203", PASSWORD_DEFAULT);

    $insert = "
    INSERT IGNORE INTO users
    (user_name, user_password, user_email, stud_num, org_body, user_reg_date)
    VALUES
    ('admin', '$adminPass', 'admin@hau.edu.ph', '203', 'SOC', NOW())
    ";

    mysqli_query($conn, $insert);

} catch (mysqli_sql_exception $e) {
    die("Database Error: " . $e->getMessage());
}
?>