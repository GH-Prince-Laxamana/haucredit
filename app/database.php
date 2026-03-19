<?php
require_once 'error.php';

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
    $checkAdminStmt = $conn->prepare("
        SELECT user_id
        FROM users
        WHERE user_name = ?
        LIMIT 1
    ");
    $adminUserName = 'admin';
    $checkAdminStmt->bind_param("s", $adminUserName);
    $checkAdminStmt->execute();
    $existingAdmin = $checkAdminStmt->get_result()->fetch_assoc();

    if (!$existingAdmin) {
        $adminPass = password_hash("203", PASSWORD_DEFAULT);

        $insertAdminStmt = $conn->prepare("
            INSERT INTO users
            (user_name, user_password, user_email, stud_num, org_body, role, profile_pic, user_reg_date)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $user_name = 'admin';
        $user_email = 'admin@hau.edu.ph';
        $stud_num = '203';
        $org_body = 'SOC';
        $role = 'admin';
        $profile_pic = 'default.jpg';

        $insertAdminStmt->bind_param(
            "sssssss",
            $user_name,
            $adminPass,
            $user_email,
            $stud_num,
            $org_body,
            $role,
            $profile_pic
        );
        $insertAdminStmt->execute();
        $admin_user_id = $insertAdminStmt->insert_id;
    } else {
        $admin_user_id = (int) $existingAdmin['user_id'];
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

    $upsertTemplateStmt = $conn->prepare("
        INSERT INTO requirement_templates (req_name, req_desc, template_url, is_active)
        VALUES (?, ?, ?, 1)
        ON DUPLICATE KEY UPDATE
            req_desc = VALUES(req_desc),
            template_url = VALUES(template_url),
            is_active = VALUES(is_active)
    ");

    foreach ($default_requirements as $req_name) {
        $req_desc = $default_requirements_descs[$req_name] ?? null;
        $template_url = $requirements_templates[$req_name] ?? null;

        $upsertTemplateStmt->bind_param("sss", $req_name, $req_desc, $template_url);
        $upsertTemplateStmt->execute();
    }

    /* ================= SAMPLE SYSTEM EVENT ================= */
    $checkEventStmt = $conn->prepare("
        SELECT event_id
        FROM events
        WHERE is_system_event = 1
        LIMIT 1
    ");
    $checkEventStmt->execute();
    $existingEvent = $checkEventStmt->get_result()->fetch_assoc();

    if (!$existingEvent) {
        $activeTemplateCountResult = $conn->query("
            SELECT COUNT(*) AS total
            FROM requirement_templates
            WHERE is_active = 1
        ");
        $activeTemplateCountRow = $activeTemplateCountResult->fetch_assoc();
        $docs_total = (int) $activeTemplateCountRow['total'];

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

        /* Insert into events core */
        $insertEventStmt = $conn->prepare("
            INSERT INTO events (
                user_id, organizing_body, nature, event_name,
                event_status, admin_remarks, docs_total, docs_uploaded, is_system_event
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $insertEventStmt->bind_param(
            "isssssiii",
            $user_id,
            $organizing_body,
            $nature,
            $event_name,
            $event_status,
            $admin_remarks,
            $docs_total,
            $docs_uploaded,
            $is_system_event
        );
        $insertEventStmt->execute();
        $event_id = $insertEventStmt->insert_id;

        /* Insert into event_type */
        $insertEventTypeStmt = $conn->prepare("
            INSERT INTO event_type (event_id, background, activity_type, series)
            VALUES (?, ?, ?, ?)
        ");
        $insertEventTypeStmt->bind_param(
            "isss",
            $event_id,
            $background,
            $activity_type,
            $series
        );
        $insertEventTypeStmt->execute();

        /* Insert into event_dates */
        $insertEventDatesStmt = $conn->prepare("
            INSERT INTO event_dates (event_id, start_datetime, end_datetime)
            VALUES (?, ?, ?)
        ");
        $insertEventDatesStmt->bind_param(
            "iss",
            $event_id,
            $start_datetime,
            $end_datetime
        );
        $insertEventDatesStmt->execute();

        /* Insert into event_participants */
        $insertEventParticipantsStmt = $conn->prepare("
            INSERT INTO event_participants (event_id, participants, participant_range, has_visitors)
            VALUES (?, ?, ?, ?)
        ");
        $insertEventParticipantsStmt->bind_param(
            "isss",
            $event_id,
            $participants,
            $participant_range,
            $has_visitors
        );
        $insertEventParticipantsStmt->execute();

        /* Insert into event_location */
        $insertEventLocationStmt = $conn->prepare("
            INSERT INTO event_location (event_id, venue_platform, distance)
            VALUES (?, ?, ?)
        ");
        $insertEventLocationStmt->bind_param(
            "iss",
            $event_id,
            $venue_platform,
            $distance
        );
        $insertEventLocationStmt->execute();

        /* Insert into event_logistics */
        $insertEventLogisticsStmt = $conn->prepare("
            INSERT INTO event_logistics (event_id, extraneous, collect_payments, overnight)
            VALUES (?, ?, ?, ?)
        ");
        $insertEventLogisticsStmt->bind_param(
            "issi",
            $event_id,
            $extraneous,
            $collect_payments,
            $overnight
        );
        $insertEventLogisticsStmt->execute();

        /* Link all active requirement templates to this event */
        $getTemplatesResult = $conn->query("
            SELECT req_template_id
            FROM requirement_templates
            WHERE is_active = 1
            ORDER BY req_template_id ASC
        ");

        $linkRequirementStmt = $conn->prepare("
            INSERT INTO event_requirements (
                event_id, req_template_id, submission_status, review_status
            ) VALUES (?, ?, 'pending', 'not_reviewed')
        ");

        while ($tpl = $getTemplatesResult->fetch_assoc()) {
            $tpl_id = (int) $tpl['req_template_id'];

            $linkRequirementStmt->bind_param("ii", $event_id, $tpl_id);
            $linkRequirementStmt->execute();
        }

        /* Insert independent calendar entry linked to event */
        $notes = "Default debug event";

        $insertCalendarStmt = $conn->prepare("
            INSERT INTO calendar_entries
            (user_id, event_id, title, start_datetime, end_datetime, notes)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $insertCalendarStmt->bind_param(
            "iissss",
            $user_id,
            $event_id,
            $event_name,
            $start_datetime,
            $end_datetime,
            $notes
        );
        $insertCalendarStmt->execute();
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