<?php
require_once 'error.php';

// Ensure MySQLi throws exceptions for errors.
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$db_server = "127.0.0.1";
$db_user = "root";
$db_pass = "";
$db_name = "haucredit_db";

try {
    /* Connect to MySQL and apply UTF-8 */
    $conn = new mysqli($db_server, $db_user, $db_pass);
    $conn->set_charset("utf8mb4");

    /* Ensure database exists and switch to it */
    $conn->query("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $conn->select_db($db_name);

    /* List of required tables; order avoids FK race conditions */
    $tables = [
        /* USERS */
        "CREATE TABLE IF NOT EXISTS users (
            user_id INT AUTO_INCREMENT PRIMARY KEY,
            user_name VARCHAR(50) NOT NULL UNIQUE,
            user_password VARCHAR(255) NOT NULL,
            user_email VARCHAR(100) NOT NULL UNIQUE,
            stud_num VARCHAR(50) NOT NULL UNIQUE,
            org_body VARCHAR(200) NOT NULL,
            profile_pic VARCHAR(255) DEFAULT 'default.jpg',
            user_reg_date DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        /* PASSWORD RESET */
        "CREATE TABLE IF NOT EXISTS password_resets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            token_hash VARCHAR(255) NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        /* EVENTS CORE */
        "CREATE TABLE IF NOT EXISTS events (
            event_id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            organizing_body TEXT NOT NULL,
            nature VARCHAR(255) NOT NULL,
            event_name VARCHAR(255) NOT NULL,
            event_status ENUM('Draft','Pending Review','Completed') NOT NULL DEFAULT 'Draft',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            archived_at DATETIME NULL,
            is_system_event TINYINT(1) DEFAULT NULL,
            FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        /* EVENT TYPE */
        "CREATE TABLE IF NOT EXISTS event_type (
            event_type_id INT AUTO_INCREMENT PRIMARY KEY,
            event_id INT NOT NULL,
            background VARCHAR(100) NOT NULL,
            activity_type VARCHAR(150) NOT NULL,
            series VARCHAR(100) NULL,
            FOREIGN KEY (event_id) REFERENCES events(event_id) ON DELETE CASCADE,
            UNIQUE KEY uniq_event_type(event_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        /* EVENT DATES */
        "CREATE TABLE IF NOT EXISTS event_dates (
            event_dates_id INT AUTO_INCREMENT PRIMARY KEY,
            event_id INT NOT NULL,
            start_datetime DATETIME NOT NULL,
            end_datetime DATETIME NOT NULL,
            FOREIGN KEY (event_id) REFERENCES events(event_id) ON DELETE CASCADE,
            UNIQUE KEY uniq_event_dates(event_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        /* EVENT PARTICIPANTS */
        "CREATE TABLE IF NOT EXISTS event_participants (
            event_participants_id INT AUTO_INCREMENT PRIMARY KEY,
            event_id INT NOT NULL,
            participants VARCHAR(255) NOT NULL,
            participant_range VARCHAR(50) NULL,
            has_visitors ENUM('Yes','No') NULL,
            FOREIGN KEY (event_id) REFERENCES events(event_id) ON DELETE CASCADE,
            UNIQUE KEY uniq_event_participants(event_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        /* EVENT LOCATION */
        "CREATE TABLE IF NOT EXISTS event_location (
            event_location_id INT AUTO_INCREMENT PRIMARY KEY,
            event_id INT NOT NULL,
            venue_platform VARCHAR(255) NOT NULL,
            distance VARCHAR(100) NULL,
            FOREIGN KEY (event_id) REFERENCES events(event_id) ON DELETE CASCADE,
            UNIQUE KEY uniq_event_location(event_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        /* EVENT LOGISTICS */
        "CREATE TABLE IF NOT EXISTS event_logistics (
            event_logistics_id INT AUTO_INCREMENT PRIMARY KEY,
            event_id INT NOT NULL,
            extraneous ENUM('Yes','No') NOT NULL,
            collect_payments ENUM('Yes','No') NOT NULL,
            overnight TINYINT(1) NULL,
            FOREIGN KEY (event_id) REFERENCES events(event_id) ON DELETE CASCADE,
            UNIQUE KEY uniq_event_logistics(event_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        //* REQUIREMENTS CORE */

        
        "CREATE TABLE IF NOT EXISTS requirement_templates (
    req_template_id INT AUTO_INCREMENT PRIMARY KEY,
    req_name VARCHAR(255) NOT NULL UNIQUE,
    req_desc TEXT NULL,
    template_url VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        "CREATE TABLE IF NOT EXISTS event_requirements (
    event_req_id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    req_template_id INT NOT NULL,
    FOREIGN KEY (event_id) REFERENCES events(event_id) ON DELETE CASCADE,
    FOREIGN KEY (req_template_id) REFERENCES requirement_templates(req_template_id) ON DELETE CASCADE,
    UNIQUE KEY uniq_event_req (event_id, req_template_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        "CREATE TABLE IF NOT EXISTS requirement_files (
    req_file_id INT AUTO_INCREMENT PRIMARY KEY,
    event_req_id INT NOT NULL,
    file_path VARCHAR(255) NULL,
    template_url VARCHAR(255) NULL,
    uploaded_at DATETIME NULL,
    FOREIGN KEY (event_req_id) REFERENCES event_requirements(event_req_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

"CREATE TABLE IF NOT EXISTS requirement_status ( req_status_id INT AUTO_INCREMENT PRIMARY KEY, event_req_id INT NOT NULL, doc_status ENUM('pending','uploaded') DEFAULT 'pending', deadline DATETIME NULL, reviewed_at DATETIME NULL, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, FOREIGN KEY (event_req_id) REFERENCES event_requirements(event_req_id) ON DELETE CASCADE, UNIQUE KEY uniq_req_status(event_req_id) ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",



        /* EVENT METRICS */
        "CREATE TABLE IF NOT EXISTS event_metrics (
            event_metrics_id INT AUTO_INCREMENT PRIMARY KEY,
            event_id INT NOT NULL,
            target_metric VARCHAR(255) NOT NULL,
            actual_metric VARCHAR(255) NULL,
            metric_requirement_id INT NULL,
            FOREIGN KEY (event_id) REFERENCES events(event_id) ON DELETE CASCADE,
            FOREIGN KEY (metric_requirement_id) REFERENCES event_requirements(event_req_id) ON DELETE SET NULL,
            UNIQUE KEY uniq_event_metrics(event_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        /* EVENT DOCUMENTS */
        "CREATE TABLE IF NOT EXISTS event_documents (
            event_id INT PRIMARY KEY,
            docs_total INT DEFAULT 0,
            docs_uploaded INT DEFAULT 0,
            FOREIGN KEY (event_id) REFERENCES events(event_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        /* CALENDAR */
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
    ];

    /* Execute table definitions */
    foreach ($tables as $table) {
        $conn->query($table);
    }



    /* Create initial admin user if not present */
    $checkAdmin = $conn->query("SELECT 1 FROM users WHERE user_id = 1 LIMIT 1");
    if (!$checkAdmin->fetch_assoc()) {
        $adminPass = password_hash("203", PASSWORD_DEFAULT);
        $conn->query("
            INSERT INTO users
            (user_name,user_password,user_email,stud_num,org_body,profile_pic,user_reg_date)
            VALUES
            ('admin','$adminPass','admin@hau.edu.ph','203','SOC','default.jpg',NOW())
        ");
    }

    /* Insert sample system event if missing */
    $checkEvent = $conn->query("SELECT event_id FROM events WHERE is_system_event = 1 LIMIT 1");
    if (!$checkEvent->fetch_assoc()) {
        $user_id = 1;
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
        $has_visitors = "No";
        $event_status = "Pending Review";
        $is_system_event = 1;
        $actual_metric = null;

        /* Base event row */
        $stmt = $conn->prepare("
            INSERT INTO events (user_id, organizing_body, nature, event_name, event_status, is_system_event)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("issssi", $user_id, $organizing_body, $nature, $event_name, $event_status, $is_system_event);
        $stmt->execute();
        $event_id = $conn->insert_id;

        /* Related event child rows */
        $stmt = $conn->prepare("
            INSERT INTO event_type (event_id, background, activity_type, series)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->bind_param("isss", $event_id, $background, $activity_type, $series);
        $stmt->execute();

        $stmt = $conn->prepare("
            INSERT INTO event_dates (event_id, start_datetime, end_datetime)
            VALUES (?, ?, ?)
        ");
        $stmt->bind_param("iss", $event_id, $start_datetime, $end_datetime);
        $stmt->execute();

        $stmt = $conn->prepare("
            INSERT INTO event_participants (event_id, participants, participant_range, has_visitors)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->bind_param("isss", $event_id, $participants, $participant_range, $has_visitors);
        $stmt->execute();

        $stmt = $conn->prepare("
            INSERT INTO event_location (event_id, venue_platform, distance)
            VALUES (?, ?, ?)
        ");
        $stmt->bind_param("iss", $event_id, $venue_platform, $distance);
        $stmt->execute();

        $stmt = $conn->prepare("
            INSERT INTO event_logistics (event_id, extraneous, collect_payments, overnight)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->bind_param("isss", $event_id, $extraneous, $collect_payments, $overnight);
        $stmt->execute();

        $stmt = $conn->prepare("
            INSERT INTO event_metrics (event_id, target_metric, actual_metric)
            VALUES (?, ?, ?)
        ");
        $stmt->bind_param("iss", $event_id, $target_metric, $actual_metric);
        $stmt->execute();
        $stmt = $conn->prepare("
    INSERT INTO event_documents (event_id, docs_total, docs_uploaded)
    VALUES (?, 2, 0)
");
        $stmt->bind_param("i", $event_id);
        $stmt->execute();

        /* Requirement definitions and default records */
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
            'Program Flow and/or Itinerary' => 'If the program is spontaneous (meaning that it does not have a program flow), discuss in outline the guidelines of the event. For Off-Campus Activities, include the Travel Itinerary with the stopovers and indicate the places where you assemble, stop, and arrive (this is different from the event program flow/guidelines)',
            'Parental Consent' => 'Upload as one PDF File. Shall be individually notarized.',
            'Letter of Undertaking' => 'Shall be signed by the adviser. The Person-in-Charge shall always be an employee of the university.',
            'Planned Budget' => 'Discuss the source of budget, projected spending for all resources needed for the activity.',
            'List of Participants' => 'List and sort all students, employees, and guests to attend with their roles to the activity.',
            'CHEd Certificate of Compliance' => 'Shall be notarized. Please view template provided.',
            'Student Organization Intake Form (OCES Annex A Form)' => 'Form required by the Office of Community Extension Services.',
            'Request Letter for Collection/Selling' => 'A letter approved by the College/School Dean should be uploaded here. If you are a uniwide student group, address it to Ms. Iris Ann Castro (OSA Director) through Mr. Paul Ernest D. Carreon (Student Activities Coordinator) submit it without their signature. No need to place our names on the approval.',
            'Medical Clearance of Participants' => 'Medical clearance issued by a licensed physician confirming participants are fit for the activity.',
            'Risk Assessment Plan with Emergency Contacts and Emergency Map' => 'Provide a risk assessment plan including emergency contacts and a map showing the nearest police station, hospital, and LGU units.',
            'Visitors and Vehicle Lists' => 'List of visitors and vehicles entering the campus including names and plate numbers.',
        ];
        $requirements_templates = [
            'Approval Letter from Dean' => 'https://docs.google.com/document/d/1cfTUM6YD0Lpf6DCZl0LjNTTeeBXtAmgUgM2eQBj7QOI/edit',
            'Program Flow and/or Itinerary' => 'https://docs.google.com/document/d/1cfTUM6YD0Lpf6DCZl0LjNTTeeBXtAmgUgM2eQBj7QOI/edit',
            'Parental Consent' => 'https://docs.google.com/document/d/1rCQbqIH1YFUxekaTCkIYTx3wxUnEKoHUxofG5c7K_1s/edit',
            'Letter of Undertaking' => 'https://docs.google.com/document/d/1vNsUTnyTeYo9sF_p6nvJCOOouWt7O7Du8m9OxbC4LZc/edit',
            'Planned Budget' => '',
            'List of Participants' => '',
            'CHEd Certificate of Compliance' => 'https://docs.google.com/document/d/1gdHMH0iFZpS3OFwoG8w1r8DZoMh_oeXB4nN22kQt21o/edit',
            'Student Organization Intake Form (OCES Annex A Form)' => 'https://docs.google.com/document/d/1WKsTW9acn0s9jXj4TANrkJBp3WIe9Ilh/edit',
            'Request Letter for Collection/Selling' => 'https://docs.google.com/document/d/1uA5CrIyGeVlrcw8dBQCpKN2XzyvSOtHU81FmY4XZ6ic/edit',
            'Medical Clearance of Participants' => '',
            'Risk Assessment Plan with Emergency Contacts and Emergency Map' => '',
            'Visitors and Vehicle Lists' => 'https://docs.google.com/document/d/12GynKf48JzB1hPn-xelzkDNYMfDXw3LLqwYkNlavRog/edit',
            '' => 'https://drive.google.com/file/d/1WKsTW9acn0s9jXj4TANrkJBp3WIe9Ilh/view'
        ];

        // $req_stmt = $conn->prepare("INSERT INTO requirements (event_id, req_name, req_desc) VALUES (?, ?, ?)");
        // $file_stmt = $conn->prepare("INSERT INTO requirement_files (req_id, template_url) VALUES (?, ?)");
        // $status_stmt = $conn->prepare("INSERT INTO requirement_status (req_id, doc_status) VALUES (?, ?)");

        // foreach ($default_requirements as $req_name) {
        //     $req_desc = $default_requirements_descs[$req_name] ?? '';
        //     $template_url = $requirements_templates[$req_name] ?? '';

        //     $req_stmt->bind_param("iss", $event_id, $req_name, $req_desc);
        //     $req_stmt->execute();
        //     $req_id = $conn->insert_id;

        //     $file_stmt->bind_param("is", $req_id, $template_url);
        //     $file_stmt->execute();

        //     $status_stmt->bind_param("is", $req_id, "pending");
        //     $status_stmt->execute();
        // }

        /* INSERT GLOBAL REQUIREMENT TEMPLATES (RUN ONCE) */
        $checkTemplates = $conn->query("SELECT 1 FROM requirement_templates LIMIT 1");

        if (!$checkTemplates->fetch_assoc()) {

            $stmt = $conn->prepare("
        INSERT INTO requirement_templates (req_name, req_desc, template_url)
        VALUES (?, ?, ?)
    ");

            foreach ($default_requirements as $req_name) {
                $req_desc = $default_requirements_descs[$req_name] ?? '';
                $template_url = $requirements_templates[$req_name] ?? '';

                $stmt->bind_param("sss", $req_name, $req_desc, $template_url);
                $stmt->execute();
            }
        }

        $getTemplates = $conn->query("SELECT req_template_id, template_url FROM requirement_templates");

        $link_stmt = $conn->prepare("
    INSERT INTO event_requirements (event_id, req_template_id)
    VALUES (?, ?)
");

        $status_stmt = $conn->prepare("
    INSERT INTO requirement_status (event_req_id, doc_status)
    VALUES (?, 'pending')
");

        $file_stmt = $conn->prepare("
    INSERT INTO requirement_files (event_req_id, template_url)
    VALUES (?, ?)
");

        while ($tpl = $getTemplates->fetch_assoc()) {

            $tpl_id = $tpl['req_template_id'];
            $template_url = $tpl['template_url'] ?? null;

            $link_stmt->bind_param("ii", $event_id, $tpl_id);
            $link_stmt->execute();

            $event_req_id = $conn->insert_id;

            $status_stmt->bind_param("i", $event_req_id);
            $status_stmt->execute();

            $file_stmt->bind_param("is", $event_req_id, $template_url);
            $file_stmt->execute();
        }

        /* Calendar entry for sample event */
        $stmt = $conn->prepare("
    INSERT INTO calendar_entries
    (user_id, event_id, title, start_datetime, end_datetime, notes)
    VALUES (?, ?, ?, ?, ?, ?)
");

        $notes = "Default debug event";

        $stmt->bind_param("iissss", $user_id, $event_id, $event_name, $start_datetime, $end_datetime, $notes);
        $stmt->execute();
    }

} catch (Exception $e) {
    popup_error("Database Error: " . $e->getMessage());
}
