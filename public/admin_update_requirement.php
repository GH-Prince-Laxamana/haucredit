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

$admin_user_id = (int) $_SESSION["user_id"];
$event_id = isset($_POST["event_id"]) ? (int) $_POST["event_id"] : 0;
$event_req_id = isset($_POST["event_req_id"]) ? (int) $_POST["event_req_id"] : 0;
$action = trim($_POST["action"] ?? "");
$remarks = trim($_POST["remarks"] ?? "");

if ($event_id <= 0 || $event_req_id <= 0) {
    popup_error("Invalid request data.");
}

$allowed_actions = ["approve", "needs_revision", "save_remarks"];
if (!in_array($action, $allowed_actions, true)) {
    popup_error("Invalid action.");
}

/* ================= FETCH REQUIREMENT + EVENT ================= */
$row = fetchOne(
    $conn,
    "
    SELECT
        er.event_req_id,
        er.event_id,
        er.submission_status,
        er.review_status,
        er.remarks,
        rt.req_name,
        e.event_status,
        e.archived_at
    FROM event_requirements er
    INNER JOIN requirement_templates rt
        ON er.req_template_id = rt.req_template_id
    INNER JOIN events e
        ON er.event_id = e.event_id
    WHERE er.event_req_id = ?
      AND er.event_id = ?
      AND e.archived_at IS NULL
    LIMIT 1
    ",
    "ii",
    [$event_req_id, $event_id]
);

if (!$row) {
    popup_error("Requirement not found.");
}

$event_status = $row["event_status"] ?? "";
$submission_status = $row["submission_status"] ?? "";
$req_name = $row["req_name"] ?? "";

/* ================= VALIDATE WORKFLOW ================= */
if (in_array($event_status, ["Draft", "Completed"], true)) {
    popup_error("Requirements cannot be reviewed while the event status is {$event_status}.");
}

if ($submission_status !== "Uploaded" && $action !== "save_remarks") {
    popup_error("Only uploaded requirements can be reviewed.");
}

try {
    $conn->begin_transaction();

    if ($action === "save_remarks") {
        execQuery(
            $conn,
            "
            UPDATE event_requirements
            SET
                remarks = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE event_req_id = ?
            ",
            "si",
            [$remarks !== "" ? $remarks : null, $event_req_id]
        );
    }

    if ($action === "approve") {
        execQuery(
            $conn,
            "
            UPDATE event_requirements
            SET
                review_status = 'Approved',
                reviewed_at = NOW(),
                reviewer_id = ?,
                remarks = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE event_req_id = ?
            ",
            "isi",
            [$admin_user_id, $remarks !== "" ? $remarks : null, $event_req_id]
        );
    }

    if ($action === "needs_revision") {
        execQuery(
            $conn,
            "
            UPDATE event_requirements
            SET
                review_status = 'Needs Revision',
                reviewed_at = NOW(),
                reviewer_id = ?,
                remarks = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE event_req_id = ?
            ",
            "isi",
            [$admin_user_id, $remarks !== "" ? $remarks : null, $event_req_id]
        );

        /* If a requirement needs revision, event should also reflect Needs Revision
           unless already Completed */
        execQuery(
            $conn,
            "
            UPDATE events
            SET
                event_status = 'Needs Revision',
                updated_at = CURRENT_TIMESTAMP
            WHERE event_id = ?
              AND event_status <> 'Completed'
            ",
            "i",
            [$event_id]
        );
    }

    $conn->commit();

} catch (Exception $e) {
    $conn->rollback();
    popup_error("Failed to update requirement: " . $e->getMessage());
}

header("Location: admin_manage_event.php?id=" . $event_id);
exit();
?>