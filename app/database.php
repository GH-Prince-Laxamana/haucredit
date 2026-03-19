<?php
require_once 'error.php';
require_once 'query_builder_functions.php';

$requirements_map = require_once "config/requirements_map.php";
$org_options = require_once "config/org_options.php";
$activity_types = require_once "config/activity_types.php";
$series_options = require_once "config/series_options.php";

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

    function attachRequirementsToEvent(mysqli $conn, int $event_id, string $start_datetime): void
    {
        $templatesSelect = fetchAll(
            $conn,
            "
                SELECT req_template_id, default_due_offset_days, default_due_basis
                FROM requirement_templates
                WHERE is_active = 1
                ORDER BY req_template_id ASC
                "
        );

        $eventRequirementsInsertSql = "
                INSERT INTO event_requirements (
                    event_id, req_template_id, submission_status, review_status, deadline
                ) VALUES (?, ?, 'pending', 'not_reviewed', ?)
                ON DUPLICATE KEY UPDATE
                    deadline = VALUES(deadline),
                    updated_at = CURRENT_TIMESTAMP
                ";

        foreach ($templatesSelect as $tpl) {
            $tpl_id = (int) $tpl['req_template_id'];
            $offset_days = $tpl['default_due_offset_days'];
            $basis = $tpl['default_due_basis'];

            $deadline = null;

            if ($offset_days !== null && $basis !== 'manual') {
                $eventStart = new DateTime($start_datetime);

                if ($basis === 'before_start') {
                    $eventStart->modify("-{$offset_days} days");
                } elseif ($basis === 'after_start') {
                    $eventStart->modify("+{$offset_days} days");
                }

                $deadline = $eventStart->format('Y-m-d H:i:s');
            }

            execQuery($conn, $eventRequirementsInsertSql, "iis", [$event_id, $tpl_id, $deadline]);
        }
    }

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

        /* ================= EVENTS CORE ================= */
        "CREATE TABLE IF NOT EXISTS events (
            event_id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            organizing_body TEXT NOT NULL,
            nature VARCHAR(255) NOT NULL,
            event_name VARCHAR(255) NOT NULL,

            event_status ENUM('Draft','Pending Review','Approved','Rejected','Completed') NOT NULL DEFAULT 'Draft',
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
            background VARCHAR(100) NOT NULL,
            activity_type VARCHAR(150) NOT NULL,
            series VARCHAR(100) NULL,

            FOREIGN KEY (event_id) REFERENCES events(event_id) ON DELETE CASCADE,
            UNIQUE KEY uniq_event_type (event_id)
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

        "CREATE TABLE IF NOT EXISTS event_metrics (
            event_metrics_id INT AUTO_INCREMENT PRIMARY KEY,
            event_id INT NOT NULL,
            target_metric VARCHAR(255) NOT NULL,
            actual_metric VARCHAR(255) NULL,
            FOREIGN KEY (event_id) REFERENCES events(event_id) ON DELETE CASCADE,
            UNIQUE KEY uniq_event_metrics (event_id)
        );",

        /* ================= MASTER REQUIREMENT LIST ================= */
        "CREATE TABLE IF NOT EXISTS requirement_templates (
            req_template_id INT AUTO_INCREMENT PRIMARY KEY,
            req_name VARCHAR(255) NOT NULL UNIQUE,
            req_desc TEXT NULL,
            template_url VARCHAR(255) NULL,
            default_due_offset_days INT NOT NULL DEFAULT 7,
            default_due_basis ENUM('before_start','after_start','manual') NOT NULL DEFAULT 'before_start',
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        /* ================= REQUIREMENTS ATTACHED TO EVENTS ================= */
        "CREATE TABLE IF NOT EXISTS event_requirements (
            event_req_id INT AUTO_INCREMENT PRIMARY KEY,
            event_id INT NOT NULL,
            req_template_id INT NOT NULL,

            submission_status ENUM('pending','uploaded') NOT NULL DEFAULT 'pending',
            review_status ENUM('not_reviewed','approved','rejected','needs_revision') NOT NULL DEFAULT 'not_reviewed',

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

    /* ================= DEFAULT REQUIREMENT TEMPLATES ================= */
    $default_requirements = [
        'Approval Letter from Dean',
        'Program Flow and/or Itinerary',
        'Parental Consent',
        'Letter of Undertaking',
        'Planned Budget',
        'List of Participants',
        'CHEd Certificate of Compliance',
        'Student Organization Intake Form (OCES Annex A Form)',
        'Request Letter for Collection/Selling',
        'Medical Clearance of Participants',
        'Risk Assessment Plan with Emergency Contacts and Emergency Map',
        'Visitors and Vehicle Lists'
    ];

    $default_requirements_descs = [
        'Approval Letter from Dean' => "Submit this program flow with the adviser's noting signature and approval of your College/School Dean. For Uniwide Institutions, address it to Ms. Iris Ann Castro (OSA Director) through Mr. Paul Ernest D. Carreon (Student Activities Coordinator), and submit it without their signature. There is no need to place the names of Mr. Carreon and Ms. Castro on the approval.",
        'Program Flow and/or Itinerary' => 'If the program is spontaneous (meaning that it does not have a program flow), discuss in outline the guidelines of the event. For Off-Campus Activities, include the Travel Itinerary with the stopovers and indicate the places where you assemble, stop, and arrive.',
        'Parental Consent' => 'Upload as one PDF file. Shall be individually notarized.',
        'Letter of Undertaking' => 'Shall be signed by the adviser. The Person-in-Charge shall always be an employee of the university.',
        'Planned Budget' => 'Discuss the source of budget and projected spending for all resources needed for the activity.',
        'List of Participants' => 'List and sort all students, employees, and guests attending, together with their roles in the activity.',
        'CHEd Certificate of Compliance' => 'Shall be notarized. Please view template provided.',
        'Student Organization Intake Form (OCES Annex A Form)' => 'Form required by the Office of Community Extension Services.',
        'Request Letter for Collection/Selling' => 'A letter approved by the College/School Dean should be uploaded here.',
        'Medical Clearance of Participants' => 'Medical clearance issued by a licensed physician confirming participants are fit for the activity.',
        'Risk Assessment Plan with Emergency Contacts and Emergency Map' => 'Provide a risk assessment plan including emergency contacts and a map showing the nearest police station, hospital, and LGU units.',
        'Visitors and Vehicle Lists' => 'List of visitors and vehicles entering the campus including names and plate numbers.'
    ];

    $requirements_templates = [
        'Approval Letter from Dean' => 'https://docs.google.com/document/d/1cfTUM6YD0Lpf6DCZl0LjNTTeeBXtAmgUgM2eQBj7QOI/edit',
        'Program Flow and/or Itinerary' => 'https://docs.google.com/document/d/1cfTUM6YD0Lpf6DCZl0LjNTTeeBXtAmgUgM2eQBj7QOI/edit',
        'Parental Consent' => 'https://docs.google.com/document/d/1rCQbqIH1YFUxekaTCkIYTx3wxUnEKoHUxofG5c7K_1s/edit',
        'Letter of Undertaking' => 'https://docs.google.com/document/d/1vNsUTnyTeYo9sF_p6nvJCOOouWt7O7Du8m9OxbC4LZc/edit',
        'Planned Budget' => null,
        'List of Participants' => null,
        'CHEd Certificate of Compliance' => 'https://docs.google.com/document/d/1gdHMH0iFZpS3OFwoG8w1r8DZoMh_oeXB4nN22kQt21o/edit',
        'Student Organization Intake Form (OCES Annex A Form)' => 'https://docs.google.com/document/d/1WKsTW9acn0s9jXj4TANrkJBp3WIe9Ilh/edit',
        'Request Letter for Collection/Selling' => 'https://docs.google.com/document/d/1uA5CrIyGeVlrcw8dBQCpKN2XzyvSOtHU81FmY4XZ6ic/edit',
        'Medical Clearance of Participants' => null,
        'Risk Assessment Plan with Emergency Contacts and Emergency Map' => null,
        'Visitors and Vehicle Lists' => 'https://docs.google.com/document/d/12GynKf48JzB1hPn-xelzkDNYMfDXw3LLqwYkNlavRog/edit'
    ];

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

        execQuery(
            $conn,
            $templateUpsertSql,
            "sssis",
            [$req_name, $req_desc, $template_url, $offset_days, $basis]
        );
    }

    /* ================= SAMPLE SYSTEM EVENT ================= */
    $checkSystemEventSql = "
                            SELECT event_id
                            FROM events
                            WHERE is_system_event = 1
                            LIMIT 1
                            ";

    $existingEvent = fetchOne($conn, $checkSystemEventSql);

    if (!$existingEvent) {
        $countActiveTemplatesSql = "
                                    SELECT COUNT(*) AS total
                                    FROM requirement_templates
                                    WHERE is_active = 1
                                 ";

        $activeTemplateCountRow = fetchOne($conn, $countActiveTemplatesSql);
        $docs_total = (int) ($activeTemplateCountRow['total'] ?? 0);

        $user_id = $admin_user_id;
        $event_name = "Sample Debug Event";
        $organizing_body = json_encode(["HAU OSA"]);
        $nature = "Test Nature";
        $event_status = "Pending Review";
        $admin_remarks = null;
        $docs_uploaded = 0;
        $is_system_event = 1;

        $background = "OSA-Initiated Activity";
        $activity_type = "On-campus Activity";
        $series = null;

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

        /* Insert into events core */
        $insertEventSql = "
                        INSERT INTO events (
                            user_id, organizing_body, nature, event_name,
                            event_status, admin_remarks, docs_total, docs_uploaded, is_system_event
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ";

        $insertEventStmt = execQuery(
            $conn,
            $insertEventSql,
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
        $insertEventTypeSql = "
                            INSERT INTO event_type (event_id, background, activity_type, series)
                            VALUES (?, ?, ?, ?)
                            ";

        execQuery(
            $conn,
            $insertEventTypeSql,
            "isss",
            [$event_id, $background, $activity_type, $series]
        );

        /* Insert into event_dates */
        $insertEventDatesSql = "
                            INSERT INTO event_dates (event_id, start_datetime, end_datetime)
                            VALUES (?, ?, ?)
                            ";

        execQuery(
            $conn,
            $insertEventDatesSql,
            "iss",
            [$event_id, $start_datetime, $end_datetime]
        );

        /* Insert into event_participants */
        $insertEventParticipantsSql = "
                                    INSERT INTO event_participants (event_id, participants, participant_range, has_visitors)
                                    VALUES (?, ?, ?, ?)
                                    ";

        execQuery(
            $conn,
            $insertEventParticipantsSql,
            "isss",
            [$event_id, $participants, $participant_range, $has_visitors]
        );

        /* Insert into event_location */
        $insertEventLocationSql = "
                                INSERT INTO event_location (event_id, venue_platform, distance)
                                VALUES (?, ?, ?)
                                ";

        execQuery(
            $conn,
            $insertEventLocationSql,
            "iss",
            [$event_id, $venue_platform, $distance]
        );

        /* Insert into event_logistics */
        $insertEventLogisticsSql = "
                                    INSERT INTO event_logistics (event_id, extraneous, collect_payments, overnight)
                                    VALUES (?, ?, ?, ?)
                                    ";

        execQuery(
            $conn,
            $insertEventLogisticsSql,
            "issi",
            [$event_id, $extraneous, $collect_payments, $overnight]
        );

        /* Insert into event metric */
        $insertEventMetricSql = "
                                    INSERT INTO event_metrics (event_id, target_metric)
                                    VALUES (?, ?)
                                    ";

        execQuery(
            $conn,
            $insertEventMetricSql,
            "is",
            [$event_id, $target_metric]
        );

        attachRequirementsToEvent($conn, $event_id, $start_datetime);

        /* Insert independent calendar entry linked to event */
        $notes = "Default debug event";

        $insertCalendarEntrySql = "
                                INSERT INTO calendar_entries
                                (user_id, event_id, title, start_datetime, end_datetime, notes)
                                VALUES (?, ?, ?, ?, ?, ?)
                                ";

        execQuery(
            $conn,
            $insertCalendarEntrySql,
            "iissss",
            [$user_id, $event_id, $event_name, $start_datetime, $end_datetime, $notes]
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