<?php
require_once 'error.php';
require_once 'query_builder_functions.php';
require_once 'database.php';

$requirements_map = require_once "config/requirements_map.php";
$org_options = require_once "config/org_options.php";
$activity_types = require_once "config/activity_types.php";
$series_options = require_once "config/series_options.php";
$requirement_list = require_once "config/requirement_list.php";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    if (!isset($conn) || !($conn instanceof mysqli)) {
        throw new Exception("Database connection is not available.");
    }

    $conn->begin_transaction();

    /* ================= DEFAULT REQUIREMENT TEMPLATES ================= */
    $default_requirements = $requirement_list['default_requirements'] ?? [];
    $default_requirements_descs = $requirement_list['default_requirements_descs'] ?? [];
    $requirements_templates = $requirement_list['requirements_templates'] ?? [];

    $templateUpsertSql = "
        INSERT INTO requirement_templates (
            req_name,
            req_desc,
            template_url,
            default_due_offset_days,
            default_due_basis,
            is_active
        )
        VALUES (?, ?, ?, ?, ?, 1)
        ON DUPLICATE KEY UPDATE
            req_desc = VALUES(req_desc),
            template_url = VALUES(template_url),
            default_due_offset_days = VALUES(default_due_offset_days),
            default_due_basis = VALUES(default_due_basis),
            is_active = VALUES(is_active),
            updated_at = CURRENT_TIMESTAMP
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

    /* ================= DERIVE BACKGROUND OPTIONS FROM REQUIREMENTS MAP ================= */
    $background_options = array_keys($requirements_map);

    /* ================= SEED CONFIG: ORGANIZATIONS ================= */
    foreach ($org_options as $index => $org_name) {
        execQuery(
            $conn,
            "
            INSERT INTO config_org_options (
                org_name,
                is_active,
                sort_order
            )
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
            INSERT INTO config_background_options (
                background_name,
                is_active,
                sort_order
            )
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
            INSERT INTO config_activity_types (
                activity_type_name,
                is_active,
                sort_order
            )
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
            INSERT INTO config_series_options (
                series_name,
                is_active,
                sort_order
            )
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

        foreach ($activityMap as $activity_type_name => $req_names) {
            $activityTypeRow = fetchOne(
                $conn,
                "
                SELECT activity_type_id
                FROM config_activity_types
                WHERE activity_type_name = ?
                LIMIT 1
                ",
                "s",
                [$activity_type_name]
            );

            if (!$activityTypeRow) {
                continue;
            }

            $activity_type_id = (int) $activityTypeRow['activity_type_id'];

            foreach ($req_names as $req_name) {
                $templateRow = fetchOne(
                    $conn,
                    "
                    SELECT req_template_id
                    FROM requirement_templates
                    WHERE req_name = ?
                    LIMIT 1
                    ",
                    "s",
                    [$req_name]
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

    $conn->commit();

} catch (Exception $e) {
    if (isset($conn) && $conn instanceof mysqli) {
        try {
            $conn->rollback();
        } catch (Exception $rollbackError) {
            // Ignore rollback failure.
        }
    }

    popup_error("Configuration Seeding Error: " . $e->getMessage());
}
?>