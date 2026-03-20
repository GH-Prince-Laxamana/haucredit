<?php
require_once 'error.php';
require_once 'query_builder_functions.php';
require_once 'database.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    if (!isset($conn) || !($conn instanceof mysqli)) {
        throw new Exception("Database connection is not available.");
    }

    $conn->begin_transaction();

    /* ================= DEFAULT DEBUG USER ================= */
    $existingUser = fetchOne(
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

    if (!$existingUser) {
        $userPass = password_hash("123", PASSWORD_DEFAULT);

        $insertUserStmt = execQuery(
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
                'testuser',
                $userPass,
                'testuser@hau.edu.ph',
                '123',
                'SOC',
                'user',
                'default.jpg'
            ]
        );

        $default_user_id = (int) $insertUserStmt->insert_id;
    } else {
        $default_user_id = (int) $existingUser['user_id'];
    }

    /* ================= SAMPLE DEBUG EVENT ================= */
    $existingEvent = fetchOne(
        $conn,
        "
        SELECT event_id
        FROM events
        WHERE is_system_event = 1
          AND event_name = ?
        LIMIT 1
        ",
        "s",
        ['Sample Debug Event']
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

        $start_datetime = date('Y-m-d 09:00:00', strtotime('+1 day'));
        $end_datetime = date('Y-m-d 12:00:00', strtotime('+1 day'));

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
            WHERE background_name = ?
              AND is_active = 1
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
            WHERE activity_type_name = ?
              AND is_active = 1
            LIMIT 1
            ",
            "s",
            [$activity_type_name]
        );

        if (!$backgroundRow) {
            throw new Exception("Background option not found or inactive: {$background_name}");
        }

        if (!$activityTypeRow) {
            throw new Exception("Activity type not found or inactive: {$activity_type_name}");
        }

        $background_id = (int) $backgroundRow['background_id'];
        $activity_type_id = (int) $activityTypeRow['activity_type_id'];

        $series_option_id = null;
        if ($series_name !== null && $series_name !== '') {
            $seriesRow = fetchOne(
                $conn,
                "
                SELECT series_option_id
                FROM config_series_options
                WHERE series_name = ?
                  AND is_active = 1
                LIMIT 1
                ",
                "s",
                [$series_name]
            );

            if ($seriesRow) {
                $series_option_id = (int) $seriesRow['series_option_id'];
            }
        }

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

        /* ================= INSERT EVENTS CORE ================= */
        $insertEventStmt = execQuery(
            $conn,
            "
            INSERT INTO events (
                user_id,
                organizing_body,
                nature,
                event_name,
                event_status,
                admin_remarks,
                docs_total,
                docs_uploaded,
                is_system_event
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
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

        $event_id = (int) $insertEventStmt->insert_id;

        /* ================= INSERT EVENT TYPE ================= */
        execQuery(
            $conn,
            "
            INSERT INTO event_type (
                event_id,
                background_id,
                activity_type_id,
                series_option_id
            )
            VALUES (?, ?, ?, ?)
            ",
            "iiii",
            [$event_id, $background_id, $activity_type_id, $series_option_id]
        );

        /* ================= INSERT EVENT DATES ================= */
        execQuery(
            $conn,
            "
            INSERT INTO event_dates (
                event_id,
                start_datetime,
                end_datetime
            )
            VALUES (?, ?, ?)
            ",
            "iss",
            [$event_id, $start_datetime, $end_datetime]
        );

        /* ================= INSERT EVENT PARTICIPANTS ================= */
        execQuery(
            $conn,
            "
            INSERT INTO event_participants (
                event_id,
                participants,
                participant_range,
                has_visitors
            )
            VALUES (?, ?, ?, ?)
            ",
            "isss",
            [$event_id, $participants, $participant_range, $has_visitors]
        );

        /* ================= INSERT EVENT LOCATION ================= */
        execQuery(
            $conn,
            "
            INSERT INTO event_location (
                event_id,
                venue_platform,
                distance
            )
            VALUES (?, ?, ?)
            ",
            "iss",
            [$event_id, $venue_platform, $distance]
        );

        /* ================= INSERT EVENT LOGISTICS ================= */
        execQuery(
            $conn,
            "
            INSERT INTO event_logistics (
                event_id,
                extraneous,
                collect_payments,
                overnight
            )
            VALUES (?, ?, ?, ?)
            ",
            "issi",
            [$event_id, $extraneous, $collect_payments, $overnight]
        );

        /* ================= INSERT EVENT METRICS ================= */
        execQuery(
            $conn,
            "
            INSERT INTO event_metrics (
                event_id,
                target_metric,
                actual_metric
            )
            VALUES (?, ?, ?)
            ",
            "iss",
            [$event_id, $target_metric, null]
        );

        /* ================= ASSIGN MAPPED REQUIREMENTS ================= */
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
                event_id,
                req_template_id,
                submission_status,
                review_status,
                deadline
            )
            VALUES (?, ?, 'Pending', 'Not Reviewed', ?)
            ON DUPLICATE KEY UPDATE
                deadline = VALUES(deadline),
                updated_at = CURRENT_TIMESTAMP
        ";

        foreach ($mappedTemplates as $tpl) {
            $tpl_id = (int) $tpl['req_template_id'];
            $req_name = (string) ($tpl['req_name'] ?? '');
            $offset_days = isset($tpl['default_due_offset_days']) ? (int) $tpl['default_due_offset_days'] : null;
            $basis = $tpl['default_due_basis'] ?? 'manual';

            $deadline = null;

            if ($offset_days !== null && $basis !== 'manual') {
                $baseDate = null;

                if (in_array($basis, ['before_start', 'after_start'], true)) {
                    $baseDate = new DateTime($start_datetime);
                } elseif (in_array($basis, ['before_end', 'after_end'], true)) {
                    $baseDate = new DateTime($end_datetime);
                }

                if ($baseDate instanceof DateTime) {
                    if (in_array($basis, ['before_start', 'before_end'], true)) {
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
                    WHERE event_id = ?
                      AND req_template_id = ?
                    LIMIT 1
                    ",
                    "ii",
                    [$event_id, $tpl_id]
                );

                if ($eventReqRow) {
                    execQuery(
                        $conn,
                        "
                        INSERT INTO narrative_report_details (
                            event_req_id,
                            narrative,
                            video_documentation_link,
                            submitted_at
                        )
                        VALUES (?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE
                            narrative = VALUES(narrative),
                            video_documentation_link = VALUES(video_documentation_link),
                            submitted_at = VALUES(submitted_at),
                            updated_at = CURRENT_TIMESTAMP
                        ",
                        "isss",
                        [
                            (int) $eventReqRow['event_req_id'],
                            null,
                            null,
                            null
                        ]
                    );
                }
            }
        }

        /* ================= INSERT CALENDAR ENTRY ================= */
        execQuery(
            $conn,
            "
            INSERT INTO calendar_entries (
                user_id,
                event_id,
                title,
                start_datetime,
                end_datetime,
                notes
            )
            VALUES (?, ?, ?, ?, ?, ?)
            ",
            "iissss",
            [
                $user_id,
                $event_id,
                $event_name,
                $start_datetime,
                $end_datetime,
                "Default debug event"
            ]
        );
    }

    $conn->commit();

} catch (Exception $e) {
    if (isset($conn) && $conn instanceof mysqli) {
        try {
            $conn->rollback();
        } catch (Exception $rollbackError) {
            // Ignore rollback failure.
        }
    }

    popup_error("Debug Seeding Error: " . $e->getMessage());
}
?>