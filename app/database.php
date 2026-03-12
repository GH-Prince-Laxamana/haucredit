<?php
/* ================= DATABASE SETUP ================= */

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$db_server = "localhost";
$db_user   = "root";
$db_pass   = "";
$db_name   = "haucredit_db";

try {

    /* ================= CONNECT TO MYSQL ================= */

    $conn = new mysqli($db_server, $db_user, $db_pass);

    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    $conn->set_charset("utf8mb4");

    /* ================= CREATE DATABASE ================= */

    $conn->query("CREATE DATABASE IF NOT EXISTS `$db_name`
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    $conn->select_db($db_name);

    /* ================= CREATE TABLES ================= */

    $sql = "

    /* ================= USERS TABLE ================= */

    CREATE TABLE IF NOT EXISTS users (
        user_id INT AUTO_INCREMENT PRIMARY KEY,
        user_name VARCHAR(50) NOT NULL UNIQUE,
        user_password VARCHAR(255) NOT NULL,
        user_email VARCHAR(100) NOT NULL UNIQUE,
        stud_num VARCHAR(50) NOT NULL UNIQUE,
        org_body VARCHAR(200) NOT NULL,
        profile_pic VARCHAR(255) DEFAULT 'default.png',
        user_reg_date DATETIME NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


    /* ================= PASSWORD RESET ================= */

    CREATE TABLE IF NOT EXISTS password_resets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        token_hash VARCHAR(255) NOT NULL,
        expires_at DATETIME NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


    /* ================= EVENTS TABLE ================= */

    CREATE TABLE IF NOT EXISTS events (
        event_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        organizing_body TEXT NOT NULL,
        background VARCHAR(100) NOT NULL,
        activity_type VARCHAR(150) NOT NULL,
        series VARCHAR(100) NULL,
        nature VARCHAR(255) NOT NULL,
        event_name VARCHAR(255) NOT NULL,
        start_datetime DATETIME NOT NULL,
        end_datetime DATETIME NOT NULL,
        participants VARCHAR(255) NOT NULL,
        venue_platform VARCHAR(255) NOT NULL,
        extraneous ENUM('Yes','No') NOT NULL,
        collect_payments ENUM('Yes','No') NOT NULL,
        target_metric VARCHAR(255) NOT NULL,
        distance VARCHAR(100) NULL,
        participant_range VARCHAR(50) NULL,
        overnight TINYINT(1) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


    /* ================= CALENDAR ================= */

    CREATE TABLE IF NOT EXISTS calendar_entries (
        entry_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        event_id INT NULL,
        title VARCHAR(255) NOT NULL,
        start_datetime DATETIME NOT NULL,
        end_datetime DATETIME NULL,
        notes TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
        FOREIGN KEY (event_id) REFERENCES events(event_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


    /* ================= REQUIREMENTS ================= */

    CREATE TABLE IF NOT EXISTS requirements (
        req_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        event_id INT NOT NULL,
        req_name VARCHAR(255) NOT NULL,
        req_desc TEXT NULL,
        req_date DATE NOT NULL,
        req_done TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

        INDEX idx_event (event_id),
        INDEX idx_user (user_id),

        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
        FOREIGN KEY (event_id) REFERENCES events(event_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


    /* ================= EVENT PROGRESS ================= */

    CREATE TABLE IF NOT EXISTS event_progress (
        progress_id INT AUTO_INCREMENT PRIMARY KEY,
        event_id INT NOT NULL,
        req_id INT NOT NULL,
        status ENUM('pending','submitted','approved','rejected') DEFAULT 'pending',
        file_path VARCHAR(255) NULL,
        submitted_at DATETIME NULL,
        reviewed_at DATETIME NULL,

        FOREIGN KEY (event_id) REFERENCES events(event_id) ON DELETE CASCADE,
        FOREIGN KEY (req_id) REFERENCES requirements(req_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


    /* ================= ARCHIVED EVENTS ================= */

    CREATE TABLE IF NOT EXISTS archived_events (
        archive_id INT AUTO_INCREMENT PRIMARY KEY,
        event_id INT NOT NULL,
        archived_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

        FOREIGN KEY (event_id) REFERENCES events(event_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


    /* ================= NOTIFICATIONS ================= */

    CREATE TABLE IF NOT EXISTS notifications (
        notification_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        message TEXT NOT NULL,
        is_read TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    ";

    $conn->multi_query($sql);

    /* ================= CLEAR MULTI QUERY RESULTS ================= */

    while ($conn->more_results() && $conn->next_result()) {}

    /* ================= CREATE DEFAULT ADMIN ================= */

    $checkAdmin = $conn->query("SELECT 1 FROM users WHERE user_id = 1 LIMIT 1");

    if (!$checkAdmin->fetch_assoc()) {

        $adminPass = password_hash("203", PASSWORD_DEFAULT);

        $conn->query("
        INSERT INTO users
        (user_name,user_password,user_email,stud_num,org_body,profile_pic,user_reg_date)
        VALUES
        ('admin','$adminPass','admin@hau.edu.ph','203','SOC','default.png',NOW())
        ");
    }

} catch (Exception $e) {

    die("Database Error: " . $e->getMessage());

}
?>