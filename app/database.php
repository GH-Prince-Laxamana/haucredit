<?php
require_once 'error.php';
require_once 'query_builder_functions.php';

$requirements_map = require_once "config/requirements_map.php";
$org_options = require_once "config/org_options.php";
$activity_types = require_once "config/activity_types.php";
$series_options = require_once "config/series_options.php";
$requirement_list = require_once "config/requirement_list.php";

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

    foreach ($tables as $table) {
        $conn->query($table);
    }

    /* ================= DERIVE BACKGROUND OPTIONS FROM REQUIREMENTS MAP ================= */
    $background_options = array_keys($requirements_map);

    /* ================= DEFAULT ADMIN ================= */
    $existingAdminSelect = fetchOne(
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

    if (!$existingAdminSelect) {
        $adminPass = password_hash("203", PASSWORD_DEFAULT);

        $stmt = execQuery(
            $conn,
            "
            INSERT INTO users
            (user_name, user_password, user_email, stud_num, org_body, role, profile_pic, user_reg_date)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ",
            "sssssss",
            ['admin', $adminPass, 'admin@hau.edu.ph', '203', 'SOC', 'admin', 'default.jpg']
        );

        $admin_user_id = $stmt->insert_id;
    } else {
        $admin_user_id = (int) $existingAdminSelect['user_id'];
    }

    /* ================= DEFAULT USER SEED ================= */
    $existingUserSelect = fetchOne(
        $conn,
        "
        SELECT user_id
        FROM users
        WHERE user_name = ?
        LIMIT 1
        ",
        "s",
        ['testuser']
    );

    if (!$existingUserSelect) {
        $userPass = password_hash("123", PASSWORD_DEFAULT);

        $stmt = execQuery(
            $conn,
            "
            INSERT INTO users
            (user_name, user_password, user_email, stud_num, org_body, role, profile_pic, user_reg_date)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ",
            "sssssss",
            [
                'testuser',
                $userPass,
                'testuser@hau.edu.ph',
                '123',
                'SOC',
                'user',
                'default.jpg'
            ]
        );

        $default_user_id = $stmt->insert_id;
    } else {
        $default_user_id = (int) $existingUserSelect['user_id'];
    }

    /* ================= DEFAULT REQUIREMENT TEMPLATES ================= */
    $default_requirements = $requirement_list['default_requirements'] ?? [];
    $default_requirements_descs = $requirement_list['default_requirements_descs'] ?? [];
    $requirements_templates = $requirement_list['requirements_templates'] ?? [];

    $templateUpsertSql = "
        INSERT INTO requirement_templates (
            req_name, req_desc, template_url, default_due_offset_days, default_due_basis, is_active
        )
        VALUES (?, ?, ?, ?, ?, 1)
        ON DUPLICATE KEY UPDATE
            req_desc = VALUES(req_desc),
            template_url = VALUES(template_url),
            default_due_offset_days = VALUES(default_due_offset_days),
            default_due_basis = VALUES(default_due_basis),
            is_active = VALUES(is_active)
    ";

    foreach ($default_requirements as $req_name) {
        $req_desc = $default_requirements_descs[$req_name] ?? null;
        $template_url = $requirements_templates[$req_name] ?? null;
        $offset_days = 7;
        $basis = 'before_start';

        if ($req_name === 'Narrative Report') {
            $basis = 'after_end';
        }

        execQuery(
            $conn,
            $templateUpsertSql,
            "sssis",
            [$req_name, $req_desc, $template_url, $offset_days, $basis]
        );
    }

    /* ================= SEED CONFIG: ORGANIZATIONS ================= */
    foreach ($org_options as $index => $org_name) {
        execQuery(
            $conn,
            "
            INSERT INTO config_org_options (org_name, is_active, sort_order)
            VALUES (?, 1, ?)
            ON DUPLICATE KEY UPDATE
                sort_order = VALUES(sort_order),
                is_active = 1,
                updated_at = CURRENT_TIMESTAMP
            ",
            "si",
            [$org_name, $index + 1]
        );
    }

    /* ================= SEED CONFIG: BACKGROUNDS ================= */
    foreach ($background_options as $index => $background_name) {
        execQuery(
            $conn,
            "
            INSERT INTO config_background_options (background_name, is_active, sort_order)
            VALUES (?, 1, ?)
            ON DUPLICATE KEY UPDATE
                sort_order = VALUES(sort_order),
                is_active = 1,
                updated_at = CURRENT_TIMESTAMP
            ",
            "si",
            [$background_name, $index + 1]
        );
    }

    /* ================= SEED CONFIG: ACTIVITY TYPES ================= */
    foreach ($activity_types as $index => $activity_type_name) {
        execQuery(
            $conn,
            "
            INSERT INTO config_activity_types (activity_type_name, is_active, sort_order)
            VALUES (?, 1, ?)
            ON DUPLICATE KEY UPDATE
                sort_order = VALUES(sort_order),
                is_active = 1,
                updated_at = CURRENT_TIMESTAMP
            ",
            "si",
            [$activity_type_name, $index + 1]
        );
    }

    /* ================= SEED CONFIG: SERIES OPTIONS ================= */
    foreach ($series_options as $index => $series_name) {
        execQuery(
            $conn,
            "
            INSERT INTO config_series_options (series_name, is_active, sort_order)
            VALUES (?, 1, ?)
            ON DUPLICATE KEY UPDATE
                sort_order = VALUES(sort_order),
                is_active = 1,
                updated_at = CURRENT_TIMESTAMP
            ",
            "si",
            [$series_name, $index + 1]
        );
    }

    /* ================= SEED CONFIG: REQUIREMENTS MAP ================= */
    foreach ($requirements_map as $background_name => $activityMap) {
        $backgroundRow = fetchOne(
            $conn,
            "
            SELECT background_id
            FROM config_background_options
            WHERE background_name = ?
            LIMIT 1
            ",
            "s",
            [$background_name]
        );

        if (!$backgroundRow) {
            continue;
        }

        $background_id = (int) $backgroundRow['background_id'];

        foreach ($activityMap as $activityTypeName => $reqNames) {
            $activityTypeRow = fetchOne(
                $conn,
                "
                SELECT activity_type_id
                FROM config_activity_types
                WHERE activity_type_name = ?
                LIMIT 1
                ",
                "s",
                [$activityTypeName]
            );

            if (!$activityTypeRow) {
                continue;
            }

            $activity_type_id = (int) $activityTypeRow['activity_type_id'];

            foreach ($reqNames as $reqName) {
                $templateRow = fetchOne(
                    $conn,
                    "
                    SELECT req_template_id
                    FROM requirement_templates
                    WHERE req_name = ?
                    LIMIT 1
                    ",
                    "s",
                    [$reqName]
                );

                if (!$templateRow) {
                    continue;
                }

                $req_template_id = (int) $templateRow['req_template_id'];

                execQuery(
                    $conn,
                    "
                    INSERT INTO config_requirements_map (
                        background_id,
                        activity_type_id,
                        req_template_id,
                        is_active
                    )
                    VALUES (?, ?, ?, 1)
                    ON DUPLICATE KEY UPDATE
                        is_active = 1,
                        updated_at = CURRENT_TIMESTAMP
                    ",
                    "iii",
                    [$background_id, $activity_type_id, $req_template_id]
                );
            }
        }
    }

    /* ================= SAMPLE SYSTEM EVENT ================= */
    $existingEvent = fetchOne(
        $conn,
        "
        SELECT event_id
        FROM events
        WHERE is_system_event = 1
        LIMIT 1
        "
    );

    if (!$existingEvent) {
        $user_id = $default_user_id;
        $event_name = "Sample Debug Event";
        $organizing_body = json_encode(["HAU OSA"], JSON_UNESCAPED_UNICODE);
        $nature = "Test Nature";
        $event_status = "Pending Review";
        $admin_remarks = null;
        $docs_uploaded = 0;
        $is_system_event = 1;

        $background_name = "OSA-Initiated Activity";
        $activity_type_name = "On-campus Activity";
        $series_name = null;

        $start_datetime = date('Y-m-d H:i:s', strtotime('+1 day 09:00'));
        $end_datetime = date('Y-m-d H:i:s', strtotime('+1 day 12:00'));

        $participants = "10 students";
        $participant_range = null;
        $has_visitors = "No";

        $venue_platform = "Main Auditorium";
        $distance = null;

        $extraneous = "No";
        $collect_payments = "No";
        $overnight = 0;

        $target_metric = "75% Satisfaction Rating";

        $backgroundRow = fetchOne(
            $conn,
            "
            SELECT background_id
            FROM config_background_options
            WHERE background_name = ? AND is_active = 1
            LIMIT 1
            ",
            "s",
            [$background_name]
        );

        $activityTypeRow = fetchOne(
            $conn,
            "
            SELECT activity_type_id
            FROM config_activity_types
            WHERE activity_type_name = ? AND is_active = 1
            LIMIT 1
            ",
            "s",
            [$activity_type_name]
        );

        $series_option_id = null;
        if ($series_name !== null && $series_name !== '') {
            $seriesRow = fetchOne(
                $conn,
                "
                SELECT series_option_id
                FROM config_series_options
                WHERE series_name = ? AND is_active = 1
                LIMIT 1
                ",
                "s",
                [$series_name]
            );

            if ($seriesRow) {
                $series_option_id = (int) $seriesRow['series_option_id'];
            }
        }

        if (!$backgroundRow || !$activityTypeRow) {
            throw new Exception("Sample event config references could not be resolved.");
        }

        $background_id = (int) $backgroundRow['background_id'];
        $activity_type_id = (int) $activityTypeRow['activity_type_id'];

        $docsTotalRow = fetchOne(
            $conn,
            "
            SELECT COUNT(*) AS total
            FROM config_requirements_map crm
            INNER JOIN requirement_templates rt
                ON rt.req_template_id = crm.req_template_id
            WHERE crm.background_id = ?
              AND crm.activity_type_id = ?
              AND crm.is_active = 1
              AND rt.is_active = 1
            ",
            "ii",
            [$background_id, $activity_type_id]
        );

        $docs_total = (int) ($docsTotalRow['total'] ?? 0);

        /* Insert into events core */
        $insertEventStmt = execQuery(
            $conn,
            "
            INSERT INTO events (
                user_id, organizing_body, nature, event_name,
                event_status, admin_remarks, docs_total, docs_uploaded, is_system_event
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ",
            "isssssiii",
            [
                $user_id,
                $organizing_body,
                $nature,
                $event_name,
                $event_status,
                $admin_remarks,
                $docs_total,
                $docs_uploaded,
                $is_system_event
            ]
        );
        $event_id = $insertEventStmt->insert_id;

        /* Insert into event_type */
        execQuery(
            $conn,
            "
            INSERT INTO event_type (event_id, background_id, activity_type_id, series_option_id)
            VALUES (?, ?, ?, ?)
            ",
            "iiii",
            [$event_id, $background_id, $activity_type_id, $series_option_id]
        );

        /* Insert into event_dates */
        execQuery(
            $conn,
            "
            INSERT INTO event_dates (event_id, start_datetime, end_datetime)
            VALUES (?, ?, ?)
            ",
            "iss",
            [$event_id, $start_datetime, $end_datetime]
        );

        /* Insert into event_participants */
        execQuery(
            $conn,
            "
            INSERT INTO event_participants (event_id, participants, participant_range, has_visitors)
            VALUES (?, ?, ?, ?)
            ",
            "isss",
            [$event_id, $participants, $participant_range, $has_visitors]
        );

        /* Insert into event_location */
        execQuery(
            $conn,
            "
            INSERT INTO event_location (event_id, venue_platform, distance)
            VALUES (?, ?, ?)
            ",
            "iss",
            [$event_id, $venue_platform, $distance]
        );

        /* Insert into event_logistics */
        execQuery(
            $conn,
            "
            INSERT INTO event_logistics (event_id, extraneous, collect_payments, overnight)
            VALUES (?, ?, ?, ?)
            ",
            "issi",
            [$event_id, $extraneous, $collect_payments, $overnight]
        );

        /* Insert into event_metrics */
        execQuery(
            $conn,
            "
            INSERT INTO event_metrics (event_id, target_metric)
            VALUES (?, ?)
            ",
            "is",
            [$event_id, $target_metric]
        );

        /* Assign only mapped requirements */
        $mappedTemplates = fetchAll(
            $conn,
            "
            SELECT
                rt.req_template_id,
                rt.req_name,
                rt.default_due_offset_days,
                rt.default_due_basis
            FROM config_requirements_map crm
            INNER JOIN requirement_templates rt
                ON rt.req_template_id = crm.req_template_id
            WHERE crm.background_id = ?
              AND crm.activity_type_id = ?
              AND crm.is_active = 1
              AND rt.is_active = 1
            ORDER BY rt.req_name ASC
            ",
            "ii",
            [$background_id, $activity_type_id]
        );

        $insertEventRequirementSql = "
            INSERT INTO event_requirements (
                event_id, req_template_id, submission_status, review_status, deadline
            )
            VALUES (?, ?, 'Pending', 'Not Reviewed', ?)
            ON DUPLICATE KEY UPDATE
                deadline = VALUES(deadline),
                updated_at = CURRENT_TIMESTAMP
        ";

        foreach ($mappedTemplates as $tpl) {
            $tpl_id = (int) $tpl['req_template_id'];
            $req_name = $tpl['req_name'] ?? '';
            $offset_days = $tpl['default_due_offset_days'];
            $basis = $tpl['default_due_basis'];

            $deadline = null;

            if ($offset_days !== null && $basis !== 'manual') {
                $baseDate = null;

                if (in_array($basis, ['before_start', 'after_start'], true)) {
                    $baseDate = new DateTime($start_datetime);
                } elseif (in_array($basis, ['before_end', 'after_end'], true)) {
                    $baseDate = new DateTime($end_datetime);
                }

                if ($baseDate) {
                    if ($basis === 'before_start' || $basis === 'before_end') {
                        $baseDate->modify("-{$offset_days} days");
                    } else {
                        $baseDate->modify("+{$offset_days} days");
                    }

                    $deadline = $baseDate->format('Y-m-d H:i:s');
                }
            }

            execQuery(
                $conn,
                $insertEventRequirementSql,
                "iis",
                [$event_id, $tpl_id, $deadline]
            );

            if ($req_name === 'Narrative Report') {
                $eventReqRow = fetchOne(
                    $conn,
                    "
                    SELECT event_req_id
                    FROM event_requirements
                    WHERE event_id = ? AND req_template_id = ?
                    LIMIT 1
                    ",
                    "ii",
                    [$event_id, $tpl_id]
                );

                if ($eventReqRow) {
                    execQuery(
                        $conn,
                        "
                        INSERT INTO narrative_report_details (event_req_id)
                        VALUES (?)
                        ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP
                        ",
                        "i",
                        [(int) $eventReqRow['event_req_id']]
                    );
                }
            }
        }

        /* Insert independent calendar entry linked to event */
        execQuery(
            $conn,
            "
            INSERT INTO calendar_entries
            (user_id, event_id, title, start_datetime, end_datetime, notes)
            VALUES (?, ?, ?, ?, ?, ?)
            ",
            "iissss",
            [$user_id, $event_id, $event_name, $start_datetime, $end_datetime, "Default debug event"]
        );
    }

    $conn->commit();

} catch (Exception $e) {
    if (isset($conn) && $conn instanceof mysqli) {
        try {
            $conn->rollback();
        } catch (Exception $rollbackError) {
            // Ignore rollback failure and show original error instead.
        }
    }

    popup_error("Database Error: " . $e->getMessage());
}
?>