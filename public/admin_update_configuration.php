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

$req_template_id = isset($_POST["req_template_id"]) ? (int) $_POST["req_template_id"] : 0;
$action = trim($_POST["action"] ?? "");

if ($req_template_id <= 0) {
    popup_error("Invalid requirement template.");
}

$allowed_actions = ["update_deadline_rule", "activate_template", "deactivate_template"];
if (!in_array($action, $allowed_actions, true)) {
    popup_error("Invalid action.");
}

$template = fetchOne(
    $conn,
    "
    SELECT req_template_id, req_name, is_active
    FROM requirement_templates
    WHERE req_template_id = ?
    LIMIT 1
    ",
    "i",
    [$req_template_id]
);

if (!$template) {
    popup_error("Requirement template not found.");
}

try {
    $conn->begin_transaction();

    if ($action === "update_deadline_rule") {
        $default_due_offset_days = isset($_POST["default_due_offset_days"]) ? (int) $_POST["default_due_offset_days"] : 0;
        $default_due_basis = trim($_POST["default_due_basis"] ?? "");

        $allowed_basis = ['before_start', 'after_start', 'before_end', 'after_end', 'manual'];
        if (!in_array($default_due_basis, $allowed_basis, true)) {
            throw new Exception("Invalid due basis.");
        }

        if ($default_due_offset_days < 0) {
            throw new Exception("Due offset cannot be negative.");
        }

        execQuery(
            $conn,
            "
            UPDATE requirement_templates
            SET
                default_due_offset_days = ?,
                default_due_basis = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE req_template_id = ?
            ",
            "isi",
            [$default_due_offset_days, $default_due_basis, $req_template_id]
        );
    }

    if ($action === "activate_template") {
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

    $conn->commit();

} catch (Exception $e) {
    $conn->rollback();
    popup_error("Failed to update configuration: " . $e->getMessage());
}

header("Location: admin_configurations.php");
exit();
?>