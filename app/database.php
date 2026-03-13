<?php
/* ================= DATABASE SETUP ================= */

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$db_server = "127.0.0.1";
$db_user = "root";
$db_pass = "";
$db_name = "haucredit_db";

try {

    /* ================= CONNECT TO MYSQL ================= */

    $conn = new mysqli($db_server, $db_user, $db_pass);
    $conn->set_charset("utf8mb4");

    /* ================= CREATE DATABASE ================= */

    $conn->query("CREATE DATABASE IF NOT EXISTS `$db_name`
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $conn->select_db($db_name);

    /* ================= CREATE TABLES ================= */

    $tables = [

        /* ================= USERS TABLE ================= */

        "CREATE TABLE IF NOT EXISTS users (
        user_id INT AUTO_INCREMENT PRIMARY KEY,
        user_name VARCHAR(50) NOT NULL UNIQUE,
        user_password VARCHAR(255) NOT NULL,
        user_email VARCHAR(100) NOT NULL UNIQUE,
        stud_num VARCHAR(50) NOT NULL UNIQUE,
        org_body VARCHAR(200) NOT NULL,
        profile_pic VARCHAR(255) DEFAULT 'default.png',
        user_reg_date DATETIME NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",


        /* ================= PASSWORD RESET ================= */

        "CREATE TABLE IF NOT EXISTS password_resets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        token_hash VARCHAR(255) NOT NULL,
        expires_at DATETIME NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",


        /* ================= EVENTS TABLE ================= */

        "CREATE TABLE IF NOT EXISTS events (
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
        event_status ENUM('Draft','Pending Review','Completed') NOT NULL DEFAULT 'Draft',
        docs_total INT DEFAULT 0,
        docs_uploaded INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        archived_at DATETIME NULL,
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",


        /* ================= CALENDAR ================= */

        "CREATE TABLE IF NOT EXISTS calendar_entries (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",


        /* ================= REQUIREMENTS ================= */
        "CREATE TABLE IF NOT EXISTS requirements (
        req_id INT AUTO_INCREMENT PRIMARY KEY,
        event_id INT NOT NULL,
        req_name VARCHAR(255) NOT NULL,
        req_desc TEXT NULL,
        file_path VARCHAR(255) NULL,
        template_url VARCHAR(255) NULL,
        doc_status ENUM('pending','uploaded') DEFAULT 'pending',
        uploaded_at DATETIME NULL,
        reviewed_at DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

        INDEX idx_event (event_id),
        FOREIGN KEY (event_id) REFERENCES events(event_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",


        /* ================= ARCHIVED EVENTS ================= */

        "CREATE TABLE IF NOT EXISTS archived_events (
        archive_id INT AUTO_INCREMENT PRIMARY KEY,
        event_id INT NOT NULL,
        archived_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

        FOREIGN KEY (event_id) REFERENCES events(event_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",


        /* ================= NOTIFICATIONS ================= */

        "CREATE TABLE IF NOT EXISTS notifications (
        notification_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        message TEXT NOT NULL,
        is_read TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
    ];

    foreach ($tables as $table) {
        $conn->query($table);
    }

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

    /* ================= CREATE DEFAULT EVENT + REQUIREMENTS ================= */

    // Only create if event_id = 1 doesn't exist
    $checkEvent = $conn->query("SELECT 1 FROM events WHERE event_id = 1 LIMIT 1");
    if (!$checkEvent->fetch_assoc()) {

        $user_id = 1; // default admin

        $event_name = "Sample Debug Event";
        $organizing_body = json_encode(["HAU OSA"]);
        $background = "OSA-Initiated Activity";
        $activity_type = "On-campus Activity";
        $series = null;
        $nature = "Test Nature";
        $start_datetime = date('Y-m-d H:i:s', strtotime('+1 day 09:00'));
        $end_datetime = date('Y-m-d H:i:s', strtotime('+1 day 12:00'));
        $participants = "10 students";
        $venue_platform = "Main Auditorium";
        $extraneous = "No";
        $collect_payments = "No";
        $target_metric = "50% Turnout";
        $distance = null;
        $participant_range = null;
        $overnight = 0;
        $event_status = "Draft";

        $stmt = $conn->prepare("
        INSERT INTO events (
            user_id, organizing_body, background, activity_type, series,
            nature, event_name, start_datetime, end_datetime,
            participants, venue_platform, extraneous, collect_payments,
            target_metric, distance, participant_range, overnight, event_status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

        $stmt->bind_param(
            "isssssssssssssssis",
            $user_id,
            $organizing_body,
            $background,
            $activity_type,
            $series,
            $nature,
            $event_name,
            $start_datetime,
            $end_datetime,
            $participants,
            $venue_platform,
            $extraneous,
            $collect_payments,
            $target_metric,
            $distance,
            $participant_range,
            $overnight,
            $event_status
        );

        $stmt->execute();
        $event_id = $conn->insert_id;

        // ================= CREATE DEFAULT REQUIREMENTS =================
        $default_requirements = [
            "Approval Letter from Dean",
            "Program Flow and/or Itinerary"
        ];

        $requirements_templates = [
            'Approval Letter from Dean' => 'https://docs.google.com/document/d/1cfTUM6YD0Lpf6DCZl0LjNTTeeBXtAmgUgM2eQBj7QOI/edit?tab=t.0',
            'Program Flow and/or Itinerary' => 'https://docs.google.com/document/d/1cfTUM6YD0Lpf6DCZl0LjNTTeeBXtAmgUgM2eQBj7QOI/edit?tab=t.0'
        ];

        $req_stmt = $conn->prepare("
        INSERT INTO requirements (event_id, req_name, template_url, doc_status, created_at)
        VALUES (?, ?, ?, 'pending', NOW())
    ");

        foreach ($default_requirements as $req_name) {
            $template_url = $requirements_templates[$req_name] ?? null;
            $req_stmt->bind_param("iss", $event_id, $req_name, $template_url);
            $req_stmt->execute();
        }

        $docs_total = count($default_requirements);
        $conn->query("UPDATE events SET docs_total = $docs_total, docs_uploaded = 0 WHERE event_id = $event_id");

        // ================= CREATE CALENDAR ENTRY =================
        $cal_stmt = $conn->prepare("
        INSERT INTO calendar_entries
        (user_id, event_id, title, start_datetime, end_datetime, notes)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
        $notes = "Default debug event";
        $cal_stmt->bind_param(
            "iissss",
            $user_id,
            $event_id,
            $event_name,
            $start_datetime,
            $end_datetime,
            $notes
        );
        $cal_stmt->execute();
    }

} catch (Exception $e) {
    die("Database Error: " . $e->getMessage());
}
?>