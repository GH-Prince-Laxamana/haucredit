<?php
session_start();
require_once "../app/database.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit();
}

if (($_SESSION["role"] ?? "") !== "admin") {
    popup_error("Access denied.");
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    popup_error("Invalid request.");
}

$config_type = trim($_POST["config_type"] ?? "");
$action = trim($_POST["action"] ?? "");

if ($config_type === "" || $action === "") {
    popup_error("Missing configuration action.");
}

$allowed_basis = ['before_start', 'after_start', 'before_end', 'after_end', 'manual'];

try {
    $conn->begin_transaction();

    /* ================= BACKGROUND OPTIONS ================= */
    if ($config_type === "background") {
        $background_id = isset($_POST["background_id"]) ? (int) $_POST["background_id"] : 0;
        $background_name = trim($_POST["background_name"] ?? "");
        $sort_order = isset($_POST["sort_order"]) ? (int) $_POST["sort_order"] : 0;

        if (in_array($action, ["add_background", "update_background"], true) && $background_name === "") {
            throw new Exception("Background name is required.");
        }

        if ($action === "add_background") {
            execQuery(
                $conn,
                "
                INSERT INTO config_background_options (background_name, is_active, sort_order)
                VALUES (?, 1, ?)
                ",
                "si",
                [$background_name, $sort_order]
            );
        }

        if ($action === "update_background") {
            if ($background_id <= 0) {
                throw new Exception("Invalid background.");
            }

            execQuery(
                $conn,
                "
                UPDATE config_background_options
                SET
                    background_name = ?,
                    sort_order = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE background_id = ?
                ",
                "sii",
                [$background_name, $sort_order, $background_id]
            );
        }

        if ($action === "activate_background") {
            if ($background_id <= 0) {
                throw new Exception("Invalid background.");
            }

            execQuery(
                $conn,
                "
                UPDATE config_background_options
                SET
                    is_active = 1,
                    updated_at = CURRENT_TIMESTAMP
                WHERE background_id = ?
                ",
                "i",
                [$background_id]
            );
        }

        if ($action === "deactivate_background") {
            if ($background_id <= 0) {
                throw new Exception("Invalid background.");
            }

            execQuery(
                $conn,
                "
                UPDATE config_background_options
                SET
                    is_active = 0,
                    updated_at = CURRENT_TIMESTAMP
                WHERE background_id = ?
                ",
                "i",
                [$background_id]
            );
        }

        if ($action === "delete_background") {
            if ($background_id <= 0) {
                throw new Exception("Invalid background.");
            }

            execQuery(
                $conn,
                "
                DELETE FROM config_background_options
                WHERE background_id = ?
                ",
                "i",
                [$background_id]
            );
        }
    }

    /* ================= ORGANIZATIONS ================= */
    if ($config_type === "org") {
        $org_option_id = isset($_POST["org_option_id"]) ? (int) $_POST["org_option_id"] : 0;
        $org_name = trim($_POST["org_name"] ?? "");
        $sort_order = isset($_POST["sort_order"]) ? (int) $_POST["sort_order"] : 0;

        if (in_array($action, ["add_org", "update_org"], true) && $org_name === "") {
            throw new Exception("Organization name is required.");
        }

        if ($action === "add_org") {
            execQuery(
                $conn,
                "
                INSERT INTO config_org_options (org_name, is_active, sort_order)
                VALUES (?, 1, ?)
                ",
                "si",
                [$org_name, $sort_order]
            );
        }

        if ($action === "update_org") {
            if ($org_option_id <= 0) {
                throw new Exception("Invalid organization.");
            }

            execQuery(
                $conn,
                "
                UPDATE config_org_options
                SET
                    org_name = ?,
                    sort_order = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE org_option_id = ?
                ",
                "sii",
                [$org_name, $sort_order, $org_option_id]
            );
        }

        if ($action === "activate_org") {
            if ($org_option_id <= 0) {
                throw new Exception("Invalid organization.");
            }

            execQuery(
                $conn,
                "
                UPDATE config_org_options
                SET
                    is_active = 1,
                    updated_at = CURRENT_TIMESTAMP
                WHERE org_option_id = ?
                ",
                "i",
                [$org_option_id]
            );
        }

        if ($action === "deactivate_org") {
            if ($org_option_id <= 0) {
                throw new Exception("Invalid organization.");
            }

            execQuery(
                $conn,
                "
                UPDATE config_org_options
                SET
                    is_active = 0,
                    updated_at = CURRENT_TIMESTAMP
                WHERE org_option_id = ?
                ",
                "i",
                [$org_option_id]
            );
        }

        if ($action === "delete_org") {
            if ($org_option_id <= 0) {
                throw new Exception("Invalid organization.");
            }

            execQuery(
                $conn,
                "
                DELETE FROM config_org_options
                WHERE org_option_id = ?
                ",
                "i",
                [$org_option_id]
            );
        }
    }

    /* ================= ACTIVITY TYPES ================= */
    if ($config_type === "activity_type") {
        $activity_type_id = isset($_POST["activity_type_id"]) ? (int) $_POST["activity_type_id"] : 0;
        $activity_type_name = trim($_POST["activity_type_name"] ?? "");
        $sort_order = isset($_POST["sort_order"]) ? (int) $_POST["sort_order"] : 0;

        if (in_array($action, ["add_activity_type", "update_activity_type"], true) && $activity_type_name === "") {
            throw new Exception("Activity type name is required.");
        }

        if ($action === "add_activity_type") {
            execQuery(
                $conn,
                "
                INSERT INTO config_activity_types (activity_type_name, is_active, sort_order)
                VALUES (?, 1, ?)
                ",
                "si",
                [$activity_type_name, $sort_order]
            );
        }

        if ($action === "update_activity_type") {
            if ($activity_type_id <= 0) {
                throw new Exception("Invalid activity type.");
            }

            execQuery(
                $conn,
                "
                UPDATE config_activity_types
                SET
                    activity_type_name = ?,
                    sort_order = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE activity_type_id = ?
                ",
                "sii",
                [$activity_type_name, $sort_order, $activity_type_id]
            );
        }

        if ($action === "activate_activity_type") {
            if ($activity_type_id <= 0) {
                throw new Exception("Invalid activity type.");
            }

            execQuery(
                $conn,
                "
                UPDATE config_activity_types
                SET
                    is_active = 1,
                    updated_at = CURRENT_TIMESTAMP
                WHERE activity_type_id = ?
                ",
                "i",
                [$activity_type_id]
            );
        }

        if ($action === "deactivate_activity_type") {
            if ($activity_type_id <= 0) {
                throw new Exception("Invalid activity type.");
            }

            execQuery(
                $conn,
                "
                UPDATE config_activity_types
                SET
                    is_active = 0,
                    updated_at = CURRENT_TIMESTAMP
                WHERE activity_type_id = ?
                ",
                "i",
                [$activity_type_id]
            );
        }

        if ($action === "delete_activity_type") {
            if ($activity_type_id <= 0) {
                throw new Exception("Invalid activity type.");
            }

            execQuery(
                $conn,
                "
                DELETE FROM config_activity_types
                WHERE activity_type_id = ?
                ",
                "i",
                [$activity_type_id]
            );
        }
    }

    /* ================= SERIES OPTIONS ================= */
    if ($config_type === "series") {
        $series_option_id = isset($_POST["series_option_id"]) ? (int) $_POST["series_option_id"] : 0;
        $series_name = trim($_POST["series_name"] ?? "");
        $sort_order = isset($_POST["sort_order"]) ? (int) $_POST["sort_order"] : 0;

        if (in_array($action, ["add_series", "update_series"], true) && $series_name === "") {
            throw new Exception("Series name is required.");
        }

        if ($action === "add_series") {
            execQuery(
                $conn,
                "
                INSERT INTO config_series_options (series_name, is_active, sort_order)
                VALUES (?, 1, ?)
                ",
                "si",
                [$series_name, $sort_order]
            );
        }

        if ($action === "update_series") {
            if ($series_option_id <= 0) {
                throw new Exception("Invalid series option.");
            }

            execQuery(
                $conn,
                "
                UPDATE config_series_options
                SET
                    series_name = ?,
                    sort_order = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE series_option_id = ?
                ",
                "sii",
                [$series_name, $sort_order, $series_option_id]
            );
        }

        if ($action === "activate_series") {
            if ($series_option_id <= 0) {
                throw new Exception("Invalid series option.");
            }

            execQuery(
                $conn,
                "
                UPDATE config_series_options
                SET
                    is_active = 1,
                    updated_at = CURRENT_TIMESTAMP
                WHERE series_option_id = ?
                ",
                "i",
                [$series_option_id]
            );
        }

        if ($action === "deactivate_series") {
            if ($series_option_id <= 0) {
                throw new Exception("Invalid series option.");
            }

            execQuery(
                $conn,
                "
                UPDATE config_series_options
                SET
                    is_active = 0,
                    updated_at = CURRENT_TIMESTAMP
                WHERE series_option_id = ?
                ",
                "i",
                [$series_option_id]
            );
        }

        if ($action === "delete_series") {
            if ($series_option_id <= 0) {
                throw new Exception("Invalid series option.");
            }

            execQuery(
                $conn,
                "
                DELETE FROM config_series_options
                WHERE series_option_id = ?
                ",
                "i",
                [$series_option_id]
            );
        }
    }

    /* ================= REQUIREMENT MAPPING ================= */
    if ($config_type === "mapping") {
        $config_map_id = isset($_POST["config_map_id"]) ? (int) $_POST["config_map_id"] : 0;
        $background_id = isset($_POST["background_id"]) ? (int) $_POST["background_id"] : 0;
        $activity_type_id = isset($_POST["activity_type_id"]) ? (int) $_POST["activity_type_id"] : 0;
        $req_template_id = isset($_POST["req_template_id"]) ? (int) $_POST["req_template_id"] : 0;

        if ($action === "add_mapping") {
            if ($background_id <= 0 || $activity_type_id <= 0 || $req_template_id <= 0) {
                throw new Exception("Background, activity type, and requirement template are required.");
            }

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

        if ($action === "activate_mapping") {
            if ($config_map_id <= 0) {
                throw new Exception("Invalid mapping.");
            }

            execQuery(
                $conn,
                "
                UPDATE config_requirements_map
                SET
                    is_active = 1,
                    updated_at = CURRENT_TIMESTAMP
                WHERE config_map_id = ?
                ",
                "i",
                [$config_map_id]
            );
        }

        if ($action === "deactivate_mapping") {
            if ($config_map_id <= 0) {
                throw new Exception("Invalid mapping.");
            }

            execQuery(
                $conn,
                "
                UPDATE config_requirements_map
                SET
                    is_active = 0,
                    updated_at = CURRENT_TIMESTAMP
                WHERE config_map_id = ?
                ",
                "i",
                [$config_map_id]
            );
        }

        if ($action === "delete_mapping") {
            if ($config_map_id <= 0) {
                throw new Exception("Invalid mapping.");
            }

            execQuery(
                $conn,
                "
                DELETE FROM config_requirements_map
                WHERE config_map_id = ?
                ",
                "i",
                [$config_map_id]
            );
        }
    }

    /* ================= REQUIREMENT TEMPLATES ================= */
    if ($config_type === "template") {
        $req_template_id = isset($_POST["req_template_id"]) ? (int) $_POST["req_template_id"] : 0;
        $req_name = trim($_POST["req_name"] ?? "");
        $req_desc = trim($_POST["req_desc"] ?? "");
        $template_url = trim($_POST["template_url"] ?? "");
        $default_due_offset_days = isset($_POST["default_due_offset_days"]) ? (int) $_POST["default_due_offset_days"] : 0;
        $default_due_basis = trim($_POST["default_due_basis"] ?? "");

        if (in_array($action, ["add_template", "update_template"], true)) {
            if ($req_name === "") {
                throw new Exception("Requirement name is required.");
            }

            if (!in_array($default_due_basis, $allowed_basis, true)) {
                throw new Exception("Invalid due basis.");
            }

            if ($default_due_offset_days < 0) {
                throw new Exception("Due offset cannot be negative.");
            }

            if ($template_url === "") {
                $template_url = null;
            }

            if ($req_desc === "") {
                $req_desc = null;
            }
        }

        if ($action === "add_template") {
            execQuery(
                $conn,
                "
                INSERT INTO requirement_templates (
                    req_name,
                    req_desc,
                    template_url,
                    default_due_offset_days,
                    default_due_basis,
                    is_active
                )
                VALUES (?, ?, ?, ?, ?, 1)
                ",
                "sssis",
                [$req_name, $req_desc, $template_url, $default_due_offset_days, $default_due_basis]
            );
        }

        if ($action === "update_template") {
            if ($req_template_id <= 0) {
                throw new Exception("Invalid requirement template.");
            }

            execQuery(
                $conn,
                "
                UPDATE requirement_templates
                SET
                    req_name = ?,
                    req_desc = ?,
                    template_url = ?,
                    default_due_offset_days = ?,
                    default_due_basis = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE req_template_id = ?
                ",
                "sssisi",
                [$req_name, $req_desc, $template_url, $default_due_offset_days, $default_due_basis, $req_template_id]
            );
        }

        if ($action === "activate_template") {
            if ($req_template_id <= 0) {
                throw new Exception("Invalid requirement template.");
            }

            execQuery(
                $conn,
                "
                UPDATE requirement_templates
                SET
                    is_active = 1,
                    updated_at = CURRENT_TIMESTAMP
                WHERE req_template_id = ?
                ",
                "i",
                [$req_template_id]
            );
        }

        if ($action === "deactivate_template") {
            if ($req_template_id <= 0) {
                throw new Exception("Invalid requirement template.");
            }

            execQuery(
                $conn,
                "
                UPDATE requirement_templates
                SET
                    is_active = 0,
                    updated_at = CURRENT_TIMESTAMP
                WHERE req_template_id = ?
                ",
                "i",
                [$req_template_id]
            );
        }
    }

    $conn->commit();

} catch (Exception $e) {
    $conn->rollback();
    popup_error("Failed to update configuration: " . $e->getMessage());
}

header("Location: admin_configurations.php");
exit();
?>