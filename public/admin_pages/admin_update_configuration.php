<?php
session_start();
require_once __DIR__ . '/../../app/database.php';

requireAdmin();

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    popup_error("Invalid request.");
}

if (
    empty($_POST["csrf_token"]) ||
    empty($_SESSION["csrf_token"]) ||
    !hash_equals($_SESSION["csrf_token"], $_POST["csrf_token"])
) {
    popup_error("Invalid CSRF token.");
}

$config_type = trim($_POST["config_type"] ?? "");
$action = trim($_POST["action"] ?? "");

if ($config_type === "" || $action === "") {
    $_SESSION["error"] = "Missing configuration action.";
    header("Location: admin_configurations.php");
    exit();
}

$allowed_basis = ['before_start', 'after_start', 'before_end', 'after_end', 'manual'];
$handled = false;

function redirect_config(): void
{
    header("Location: admin_configurations.php");
    exit();
}

function ensurePositiveId(int $id, string $label): void
{
    if ($id <= 0) {
        throw new Exception("Invalid {$label}.");
    }
}

function ensureNotBlank(string $value, string $label): void
{
    if ($value === "") {
        throw new Exception("{$label} is required.");
    }
}

function ensureValidDueBasis(string $basis, array $allowed_basis): void
{
    if (!in_array($basis, $allowed_basis, true)) {
        throw new Exception("Invalid due basis.");
    }
}

function ensureNonNegative(int $value, string $label): void
{
    if ($value < 0) {
        throw new Exception("{$label} cannot be negative.");
    }
}

function normalizeNullableText(string $value): ?string
{
    $value = trim($value);
    return $value === "" ? null : $value;
}

function throwIfInUse(mysqli $conn, string $sql, string $types, array $params, string $message): void
{
    $row = fetchOne($conn, $sql, $types, $params);
    $count = (int) ($row['total'] ?? 0);

    if ($count > 0) {
        throw new Exception($message);
    }
}

try {
    $conn->begin_transaction();

    /* ================= BACKGROUND OPTIONS ================= */
    if ($config_type === "background") {
        $background_id = (int) ($_POST["background_id"] ?? 0);
        $background_name = trim($_POST["background_name"] ?? "");
        $sort_order = (int) ($_POST["sort_order"] ?? 0);

        if ($action === "add_background") {
            ensureNotBlank($background_name, "Background name");

            execQuery(
                $conn,
                "
                INSERT INTO config_background_options (background_name, is_active, sort_order)
                VALUES (?, 1, ?)
                ",
                "si",
                [$background_name, $sort_order]
            );

            $_SESSION["success"] = "Background added successfully.";
            $handled = true;
        }

        if ($action === "update_background") {
            ensurePositiveId($background_id, "background");
            ensureNotBlank($background_name, "Background name");

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

            $_SESSION["success"] = "Background updated successfully.";
            $handled = true;
        }

        if ($action === "activate_background") {
            ensurePositiveId($background_id, "background");

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

            $_SESSION["success"] = "Background activated successfully.";
            $handled = true;
        }

        if ($action === "deactivate_background") {
            ensurePositiveId($background_id, "background");

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

            $_SESSION["success"] = "Background deactivated successfully.";
            $handled = true;
        }

        if ($action === "delete_background") {
            ensurePositiveId($background_id, "background");

            throwIfInUse(
                $conn,
                "
                SELECT COUNT(*) AS total
                FROM config_requirements_map
                WHERE background_id = ?
                ",
                "i",
                [$background_id],
                "Cannot delete this background because it is still used in requirement mappings."
            );

            execQuery(
                $conn,
                "
                DELETE FROM config_background_options
                WHERE background_id = ?
                ",
                "i",
                [$background_id]
            );

            $_SESSION["success"] = "Background deleted successfully.";
            $handled = true;
        }
    }

    /* ================= ORGANIZATIONS ================= */
    if ($config_type === "org") {
        $org_option_id = (int) ($_POST["org_option_id"] ?? 0);
        $org_name = trim($_POST["org_name"] ?? "");
        $sort_order = (int) ($_POST["sort_order"] ?? 0);

        if ($action === "add_org") {
            ensureNotBlank($org_name, "Organization name");

            execQuery(
                $conn,
                "
                INSERT INTO config_org_options (org_name, is_active, sort_order)
                VALUES (?, 1, ?)
                ",
                "si",
                [$org_name, $sort_order]
            );

            $_SESSION["success"] = "Organization added successfully.";
            $handled = true;
        }

        if ($action === "update_org") {
            ensurePositiveId($org_option_id, "organization");
            ensureNotBlank($org_name, "Organization name");

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

            $_SESSION["success"] = "Organization updated successfully.";
            $handled = true;
        }

        if ($action === "activate_org") {
            ensurePositiveId($org_option_id, "organization");

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

            $_SESSION["success"] = "Organization activated successfully.";
            $handled = true;
        }

        if ($action === "deactivate_org") {
            ensurePositiveId($org_option_id, "organization");

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

            $_SESSION["success"] = "Organization deactivated successfully.";
            $handled = true;
        }

        if ($action === "delete_org") {
            ensurePositiveId($org_option_id, "organization");

            throwIfInUse(
                $conn,
                "
                SELECT COUNT(*) AS total
                FROM users
                WHERE org_body = (
                    SELECT org_name
                    FROM config_org_options
                    WHERE org_option_id = ?
                )
                ",
                "i",
                [$org_option_id],
                "Cannot delete this organization because it is still assigned to one or more users."
            );

            execQuery(
                $conn,
                "
                DELETE FROM config_org_options
                WHERE org_option_id = ?
                ",
                "i",
                [$org_option_id]
            );

            $_SESSION["success"] = "Organization deleted successfully.";
            $handled = true;
        }
    }

    /* ================= ACTIVITY TYPES ================= */
    if ($config_type === "activity_type") {
        $activity_type_id = (int) ($_POST["activity_type_id"] ?? 0);
        $activity_type_name = trim($_POST["activity_type_name"] ?? "");
        $sort_order = (int) ($_POST["sort_order"] ?? 0);

        if ($action === "add_activity_type") {
            ensureNotBlank($activity_type_name, "Activity type name");

            execQuery(
                $conn,
                "
                INSERT INTO config_activity_types (activity_type_name, is_active, sort_order)
                VALUES (?, 1, ?)
                ",
                "si",
                [$activity_type_name, $sort_order]
            );

            $_SESSION["success"] = "Activity type added successfully.";
            $handled = true;
        }

        if ($action === "update_activity_type") {
            ensurePositiveId($activity_type_id, "activity type");
            ensureNotBlank($activity_type_name, "Activity type name");

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

            $_SESSION["success"] = "Activity type updated successfully.";
            $handled = true;
        }

        if ($action === "activate_activity_type") {
            ensurePositiveId($activity_type_id, "activity type");

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

            $_SESSION["success"] = "Activity type activated successfully.";
            $handled = true;
        }

        if ($action === "deactivate_activity_type") {
            ensurePositiveId($activity_type_id, "activity type");

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

            $_SESSION["success"] = "Activity type deactivated successfully.";
            $handled = true;
        }

        if ($action === "delete_activity_type") {
            ensurePositiveId($activity_type_id, "activity type");

            throwIfInUse(
                $conn,
                "
                SELECT COUNT(*) AS total
                FROM config_requirements_map
                WHERE activity_type_id = ?
                ",
                "i",
                [$activity_type_id],
                "Cannot delete this activity type because it is still used in requirement mappings."
            );

            execQuery(
                $conn,
                "
                DELETE FROM config_activity_types
                WHERE activity_type_id = ?
                ",
                "i",
                [$activity_type_id]
            );

            $_SESSION["success"] = "Activity type deleted successfully.";
            $handled = true;
        }
    }

    /* ================= SERIES OPTIONS ================= */
    if ($config_type === "series") {
        $series_option_id = (int) ($_POST["series_option_id"] ?? 0);
        $series_name = trim($_POST["series_name"] ?? "");
        $sort_order = (int) ($_POST["sort_order"] ?? 0);

        if ($action === "add_series") {
            ensureNotBlank($series_name, "Series name");

            execQuery(
                $conn,
                "
                INSERT INTO config_series_options (series_name, is_active, sort_order)
                VALUES (?, 1, ?)
                ",
                "si",
                [$series_name, $sort_order]
            );

            $_SESSION["success"] = "Series option added successfully.";
            $handled = true;
        }

        if ($action === "update_series") {
            ensurePositiveId($series_option_id, "series option");
            ensureNotBlank($series_name, "Series name");

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

            $_SESSION["success"] = "Series option updated successfully.";
            $handled = true;
        }

        if ($action === "activate_series") {
            ensurePositiveId($series_option_id, "series option");

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

            $_SESSION["success"] = "Series option activated successfully.";
            $handled = true;
        }

        if ($action === "deactivate_series") {
            ensurePositiveId($series_option_id, "series option");

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

            $_SESSION["success"] = "Series option deactivated successfully.";
            $handled = true;
        }

        if ($action === "delete_series") {
            ensurePositiveId($series_option_id, "series option");

            execQuery(
                $conn,
                "
                DELETE FROM config_series_options
                WHERE series_option_id = ?
                ",
                "i",
                [$series_option_id]
            );

            $_SESSION["success"] = "Series option deleted successfully.";
            $handled = true;
        }
    }

    /* ================= REQUIREMENT MAPPING ================= */
    if ($config_type === "mapping") {
        $config_map_id = (int) ($_POST["config_map_id"] ?? 0);
        $background_id = (int) ($_POST["background_id"] ?? 0);
        $activity_type_id = (int) ($_POST["activity_type_id"] ?? 0);
        $req_template_id = (int) ($_POST["req_template_id"] ?? 0);

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

            $_SESSION["success"] = "Requirement mapping added successfully.";
            $handled = true;
        }

        if ($action === "activate_mapping") {
            ensurePositiveId($config_map_id, "mapping");

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

            $_SESSION["success"] = "Requirement mapping activated successfully.";
            $handled = true;
        }

        if ($action === "deactivate_mapping") {
            ensurePositiveId($config_map_id, "mapping");

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

            $_SESSION["success"] = "Requirement mapping deactivated successfully.";
            $handled = true;
        }

        if ($action === "delete_mapping") {
            ensurePositiveId($config_map_id, "mapping");

            execQuery(
                $conn,
                "
                DELETE FROM config_requirements_map
                WHERE config_map_id = ?
                ",
                "i",
                [$config_map_id]
            );

            $_SESSION["success"] = "Requirement mapping deleted successfully.";
            $handled = true;
        }
    }

    /* ================= REQUIREMENT TEMPLATES ================= */
    if ($config_type === "template") {
        $req_template_id = (int) ($_POST["req_template_id"] ?? 0);
        $req_name = trim($_POST["req_name"] ?? "");
        $req_desc = normalizeNullableText($_POST["req_desc"] ?? "");
        $template_url = normalizeNullableText($_POST["template_url"] ?? "");
        $default_due_offset_days = (int) ($_POST["default_due_offset_days"] ?? 0);
        $default_due_basis = trim($_POST["default_due_basis"] ?? "");

        if ($action === "add_template") {
            ensureNotBlank($req_name, "Requirement name");
            ensureValidDueBasis($default_due_basis, $allowed_basis);
            ensureNonNegative($default_due_offset_days, "Due offset");

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

            $_SESSION["success"] = "Requirement template added successfully.";
            $handled = true;
        }

        if ($action === "update_template") {
            ensurePositiveId($req_template_id, "requirement template");
            ensureNotBlank($req_name, "Requirement name");
            ensureValidDueBasis($default_due_basis, $allowed_basis);
            ensureNonNegative($default_due_offset_days, "Due offset");

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

            $_SESSION["success"] = "Requirement template updated successfully.";
            $handled = true;
        }

        if ($action === "activate_template") {
            ensurePositiveId($req_template_id, "requirement template");

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

            $_SESSION["success"] = "Requirement template activated successfully.";
            $handled = true;
        }

        if ($action === "deactivate_template") {
            ensurePositiveId($req_template_id, "requirement template");

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

            $_SESSION["success"] = "Requirement template deactivated successfully.";
            $handled = true;
        }

        if ($action === "delete_template") {
            ensurePositiveId($req_template_id, "requirement template");

            throwIfInUse(
                $conn,
                "
                SELECT COUNT(*) AS total
                FROM config_requirements_map
                WHERE req_template_id = ?
                ",
                "i",
                [$req_template_id],
                "Cannot delete this requirement template because it is still used in requirement mappings."
            );

            throwIfInUse(
                $conn,
                "
                SELECT COUNT(*) AS total
                FROM event_requirements
                WHERE req_template_id = ?
                ",
                "i",
                [$req_template_id],
                "Cannot delete this requirement template because it is already used by event requirements."
            );

            execQuery(
                $conn,
                "
                DELETE FROM requirement_templates
                WHERE req_template_id = ?
                ",
                "i",
                [$req_template_id]
            );

            $_SESSION["success"] = "Requirement template deleted successfully.";
            $handled = true;
        }
    }

    if (!$handled) {
        throw new Exception("Invalid configuration action.");
    }

    $conn->commit();
} catch (Throwable $e) {
    try {
        $conn->rollback();
    } catch (Throwable $rollbackError) {
    }

    $_SESSION["error"] = "Failed to update configuration: " . $e->getMessage();
}

redirect_config();