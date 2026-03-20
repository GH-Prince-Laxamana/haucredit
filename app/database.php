<?php
require_once 'error.php';
require_once 'query_builder_functions.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$db_server = "127.0.0.1";
$db_user = "root";
$db_pass = "";
$db_name = "haucredit_db";

try {
    $conn = new mysqli($db_server, $db_user, $db_pass);
    $conn->set_charset("utf8mb4");

    $conn->query("
        CREATE DATABASE IF NOT EXISTS `$db_name`
        CHARACTER SET utf8mb4
        COLLATE utf8mb4_unicode_ci
    ");

    $conn->select_db($db_name);
    $conn->begin_transaction();

    $tables = [

        /* ================= USERS ================= */
        "CREATE TABLE IF NOT EXISTS users (
            user_id INT AUTO_INCREMENT PRIMARY KEY,
            user_name VARCHAR(50) NOT NULL UNIQUE,
            user_password VARCHAR(255) NOT NULL,
            user_email VARCHAR(100) NOT NULL UNIQUE,
            stud_num VARCHAR(50) NOT NULL UNIQUE,
            org_body VARCHAR(200) NOT NULL,
            role ENUM('admin','user') NOT NULL DEFAULT 'user',
            profile_pic VARCHAR(255) NOT NULL DEFAULT 'default.jpg',
            user_reg_date DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        /* ================= PASSWORD RESETS ================= */
        "CREATE TABLE IF NOT EXISTS password_resets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            token_hash VARCHAR(255) NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
            INDEX idx_password_resets_user (user_id),
            INDEX idx_password_resets_expires (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        /* ================= CONFIG: ORGANIZATIONS ================= */
        "CREATE TABLE IF NOT EXISTS config_org_options (
            org_option_id INT AUTO_INCREMENT PRIMARY KEY,
            org_name VARCHAR(255) NOT NULL UNIQUE,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        /* ================= CONFIG: BACKGROUNDS ================= */
        "CREATE TABLE IF NOT EXISTS config_background_options (
            background_id INT AUTO_INCREMENT PRIMARY KEY,
            background_name VARCHAR(100) NOT NULL UNIQUE,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        /* ================= CONFIG: ACTIVITY TYPES ================= */
        "CREATE TABLE IF NOT EXISTS config_activity_types (
            activity_type_id INT AUTO_INCREMENT PRIMARY KEY,
            activity_type_name VARCHAR(255) NOT NULL UNIQUE,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        /* ================= CONFIG: SERIES OPTIONS ================= */
        "CREATE TABLE IF NOT EXISTS config_series_options (
            series_option_id INT AUTO_INCREMENT PRIMARY KEY,
            series_name VARCHAR(255) NOT NULL UNIQUE,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        /* ================= EVENTS CORE ================= */
        "CREATE TABLE IF NOT EXISTS events (
            event_id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            organizing_body JSON NOT NULL,
            nature VARCHAR(255) NOT NULL,
            event_name VARCHAR(255) NOT NULL,
            event_status ENUM('Draft','Pending Review','Needs Revision','Approved','Completed') NOT NULL DEFAULT 'Draft',
            admin_remarks TEXT NULL,
            docs_total INT NOT NULL DEFAULT 0,
            docs_uploaded INT NOT NULL DEFAULT 0,
            is_system_event TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            archived_at DATETIME NULL,
            FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
            INDEX idx_events_user (user_id),
            INDEX idx_events_status (event_status),
            INDEX idx_events_system (is_system_event)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        /* ================= EVENT TYPE ================= */
        "CREATE TABLE IF NOT EXISTS event_type (
            event_type_id INT AUTO_INCREMENT PRIMARY KEY,
            event_id INT NOT NULL,
            background_id INT NOT NULL,
            activity_type_id INT NOT NULL,
            series_option_id INT NULL,
            FOREIGN KEY (event_id) REFERENCES events(event_id) ON DELETE CASCADE,
            FOREIGN KEY (background_id) REFERENCES config_background_options(background_id) ON DELETE RESTRICT,
            FOREIGN KEY (activity_type_id) REFERENCES config_activity_types(activity_type_id) ON DELETE RESTRICT,
            FOREIGN KEY (series_option_id) REFERENCES config_series_options(series_option_id) ON DELETE SET NULL,
            UNIQUE KEY uniq_event_type (event_id),
            INDEX idx_event_type_background (background_id),
            INDEX idx_event_type_activity (activity_type_id),
            INDEX idx_event_type_series (series_option_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        /* ================= EVENT DATES ================= */
        "CREATE TABLE IF NOT EXISTS event_dates (
            event_dates_id INT AUTO_INCREMENT PRIMARY KEY,
            event_id INT NOT NULL,
            start_datetime DATETIME NOT NULL,
            end_datetime DATETIME NOT NULL,
            FOREIGN KEY (event_id) REFERENCES events(event_id) ON DELETE CASCADE,
            UNIQUE KEY uniq_event_dates (event_id),
            INDEX idx_event_dates_start (start_datetime),
            INDEX idx_event_dates_end (end_datetime)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        /* ================= EVENT PARTICIPANTS ================= */
        "CREATE TABLE IF NOT EXISTS event_participants (
            event_participants_id INT AUTO_INCREMENT PRIMARY KEY,
            event_id INT NOT NULL,
            participants VARCHAR(255) NOT NULL,
            participant_range VARCHAR(50) NULL,
            has_visitors ENUM('Yes','No') NULL,
            FOREIGN KEY (event_id) REFERENCES events(event_id) ON DELETE CASCADE,
            UNIQUE KEY uniq_event_participants (event_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        /* ================= EVENT LOCATION ================= */
        "CREATE TABLE IF NOT EXISTS event_location (
            event_location_id INT AUTO_INCREMENT PRIMARY KEY,
            event_id INT NOT NULL,
            venue_platform VARCHAR(255) NOT NULL,
            distance VARCHAR(100) NULL,
            FOREIGN KEY (event_id) REFERENCES events(event_id) ON DELETE CASCADE,
            UNIQUE KEY uniq_event_location (event_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        /* ================= EVENT LOGISTICS ================= */
        "CREATE TABLE IF NOT EXISTS event_logistics (
            event_logistics_id INT AUTO_INCREMENT PRIMARY KEY,
            event_id INT NOT NULL,
            extraneous ENUM('Yes','No') NOT NULL,
            collect_payments ENUM('Yes','No') NOT NULL,
            overnight TINYINT(1) NULL,
            FOREIGN KEY (event_id) REFERENCES events(event_id) ON DELETE CASCADE,
            UNIQUE KEY uniq_event_logistics (event_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        /* ================= EVENT METRICS ================= */
        "CREATE TABLE IF NOT EXISTS event_metrics (
            event_metrics_id INT AUTO_INCREMENT PRIMARY KEY,
            event_id INT NOT NULL,
            target_metric VARCHAR(255) NOT NULL,
            actual_metric VARCHAR(255) NULL,
            FOREIGN KEY (event_id) REFERENCES events(event_id) ON DELETE CASCADE,
            UNIQUE KEY uniq_event_metrics (event_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        /* ================= MASTER REQUIREMENT LIST ================= */
        "CREATE TABLE IF NOT EXISTS requirement_templates (
            req_template_id INT AUTO_INCREMENT PRIMARY KEY,
            req_name VARCHAR(255) NOT NULL UNIQUE,
            req_desc TEXT NULL,
            template_url VARCHAR(255) NULL,
            default_due_offset_days INT NOT NULL DEFAULT 7,
            default_due_basis ENUM('before_start','after_start','before_end','after_end','manual') NOT NULL DEFAULT 'before_start',
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        /* ================= CONFIG: REQUIREMENTS MAP ================= */
        "CREATE TABLE IF NOT EXISTS config_requirements_map (
            config_map_id INT AUTO_INCREMENT PRIMARY KEY,
            background_id INT NOT NULL,
            activity_type_id INT NOT NULL,
            req_template_id INT NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (background_id) REFERENCES config_background_options(background_id) ON DELETE CASCADE,
            FOREIGN KEY (activity_type_id) REFERENCES config_activity_types(activity_type_id) ON DELETE CASCADE,
            FOREIGN KEY (req_template_id) REFERENCES requirement_templates(req_template_id) ON DELETE CASCADE,
            UNIQUE KEY uniq_config_map (background_id, activity_type_id, req_template_id),
            INDEX idx_config_map_background (background_id),
            INDEX idx_config_map_activity_type (activity_type_id),
            INDEX idx_config_map_req_template (req_template_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        /* ================= REQUIREMENTS ATTACHED TO EVENTS ================= */
        "CREATE TABLE IF NOT EXISTS event_requirements (
            event_req_id INT AUTO_INCREMENT PRIMARY KEY,
            event_id INT NOT NULL,
            req_template_id INT NOT NULL,
            submission_status ENUM('Pending','Uploaded') NOT NULL DEFAULT 'Pending',
            review_status ENUM('Not Reviewed','Approved','Needs Revision') NOT NULL DEFAULT 'Not Reviewed',
            deadline DATETIME NULL,
            reviewed_at DATETIME NULL,
            reviewer_id INT NULL,
            remarks TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (event_id) REFERENCES events(event_id) ON DELETE CASCADE,
            FOREIGN KEY (req_template_id) REFERENCES requirement_templates(req_template_id) ON DELETE CASCADE,
            FOREIGN KEY (reviewer_id) REFERENCES users(user_id) ON DELETE SET NULL,
            UNIQUE KEY uniq_event_req (event_id, req_template_id),
            INDEX idx_event_req_event (event_id),
            INDEX idx_event_req_review (review_status),
            INDEX idx_event_req_submit (submission_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        /* ================= FILES FOR EVENT REQUIREMENTS ================= */
        "CREATE TABLE IF NOT EXISTS requirement_files (
            req_file_id INT AUTO_INCREMENT PRIMARY KEY,
            event_req_id INT NOT NULL,
            file_path VARCHAR(255) NOT NULL,
            original_file_name VARCHAR(255) NULL,
            file_type VARCHAR(100) NULL,
            file_size BIGINT NULL,
            uploaded_by INT NULL,
            uploaded_at DATETIME NULL,
            is_current TINYINT(1) NOT NULL DEFAULT 1,
            FOREIGN KEY (event_req_id) REFERENCES event_requirements(event_req_id) ON DELETE CASCADE,
            FOREIGN KEY (uploaded_by) REFERENCES users(user_id) ON DELETE SET NULL,
            INDEX idx_req_file_event_req (event_req_id),
            INDEX idx_req_file_current (is_current)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        /* ================= NARRATIVE REPORT DETAILS ================= */
        "CREATE TABLE IF NOT EXISTS narrative_report_details (
            narrative_report_id INT AUTO_INCREMENT PRIMARY KEY,
            event_req_id INT NOT NULL UNIQUE,
            narrative TEXT NULL,
            video_documentation_link VARCHAR(500) NULL,
            submitted_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (event_req_id) REFERENCES event_requirements(event_req_id) ON DELETE CASCADE
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
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
            FOREIGN KEY (event_id) REFERENCES events(event_id) ON DELETE SET NULL,
            INDEX idx_calendar_user (user_id),
            INDEX idx_calendar_event (event_id),
            INDEX idx_calendar_start (start_datetime)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
    ];

    foreach ($tables as $tableSql) {
        $conn->query($tableSql);
    }

    /* ================= DEFAULT ADMIN ONLY ================= */
    $existingAdmin = fetchOne(
        $conn,
        "
        SELECT user_id
        FROM users
        WHERE user_name = ?
        LIMIT 1
        ",
        "s",
        ['admin']
    );

    if (!$existingAdmin) {
        $adminPass = password_hash("203", PASSWORD_DEFAULT);

        execQuery(
            $conn,
            "
            INSERT INTO users
            (
                user_name,
                user_password,
                user_email,
                stud_num,
                org_body,
                role,
                profile_pic,
                user_reg_date
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ",
            "sssssss",
            [
                'admin',
                $adminPass,
                'admin@hau.edu.ph',
                '203',
                'SOC',
                'admin',
                'default.jpg'
            ]
        );
    }

    $conn->commit();

} catch (Exception $e) {
    if (isset($conn) && $conn instanceof mysqli) {
        try {
            $conn->rollback();
        } catch (Exception $rollbackError) {
            // ignore rollback failure
        }
    }

    popup_error("Database Error: " . $e->getMessage());
}
?>