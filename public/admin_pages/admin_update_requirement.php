<?php
session_start();
require_once __DIR__ . '/../../app/database.php';

requireAdmin();

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


// ==================== FETCH REQUIREMENT + EVENT ====================
// Query database for the specific requirement to review
// JOIN requirement_templates: get requirement name for display
// JOIN events: get event status to validate review eligibility
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

// ==================== VALIDATION: EVENT STATUS ====================
// Requirements cannot be reviewed if event is in Draft (not yet submitted) or Completed state
// These states don't require/allow requirement review workflows
if (in_array($event_status, ["Draft", "Completed"], true)) {
    popup_error("Requirements cannot be reviewed while the event status is {$event_status}.");
}

// ==================== VALIDATION: SUBMISSION STATUS ====================
// Only uploaded requirements can be reviewed (save_remarks is allowed anytime)
// If requirement not uploaded yet, return error unless just saving remarks
if ($submission_status !== "Uploaded" && $action !== "save_remarks") {
    popup_error("Only uploaded requirements can be reviewed.");
}


// ==================== TRANSACTION: UPDATE REQUIREMENT REVIEW ====================
// Use transactions to ensure data consistency: all updates succeed or all fail
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

    // =============== ACTION: APPROVE ===============
    // Mark requirement as reviewed and approved
    // Sets review status to 'Approved', records reviewer ID and timestamp
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

    // =============== ACTION: NEEDS REVISION ===============
    // Mark requirement as needing revision
    // Also cascades to event status: event returns to 'Needs Revision' (user must resubmit)
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