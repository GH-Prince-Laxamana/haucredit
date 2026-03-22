<?php
session_start();
require_once __DIR__ . '/../../app/database.php';

requireAdmin();

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    popup_error("Invalid request.");
}

$admin_user_id = (int) $_SESSION["user_id"];
$event_id = isset($_POST["event_id"]) ? (int) $_POST["event_id"] : 0;
// Extract action (save_remarks, needs_revision, approve, complete)
$action = trim($_POST["action"] ?? "");
// Extract admin remarks/feedback text (optional)
$admin_remarks = trim($_POST["admin_remarks"] ?? "");

if ($event_id <= 0) {
    popup_error("Invalid event.", PUBLIC_URL . 'index.php');
}

$allowed_actions = ["save_remarks", "needs_revision", "approve", "complete"];
if (!in_array($action, $allowed_actions, true)) {
    popup_error("Invalid action.");
}

/* ================= FETCH EVENT ================= */
// Retrieve event record from database
// Only fetches non-archived events (archived_at IS NULL)
$event = fetchOne(
    $conn,
    "
    SELECT
        event_id,
        event_status,
        archived_at
    FROM events
    WHERE event_id = ?
      AND archived_at IS NULL
    LIMIT 1
    ",
    "i",
    [$event_id]
);

if (!$event) {
    popup_error("Event not found.");
}

// Extract current event status (Draft, Pending Review, Approved, Needs Revision, Completed)
$current_status = $event["event_status"] ?? "";

/* ================= HELPER FUNCTIONS ================= */

/**
 * Check All Pre-Event Requirements Approved
 * 
 * Validates that all requirements EXCEPT Narrative Report are:
 * 1. Submitted/Uploaded by user
 * 2. Reviewed and Approved by admin
 * 
 * Used before transitioning event to Approved status.
 * Narrative Report is excluded (checked separately with isNarrativeApproved).
 * 
 * @param mysqli $conn Database connection
 * @param int $event_id Event ID to check
 * @return bool True if all pre-event requirements uploaded and approved
 */
function allPreEventRequirementsApproved(mysqli $conn, int $event_id): bool
{
    // Fetch all requirements for this event (except Narrative Report status is checked separately)
    $rows = fetchAll(
        $conn,
        "
        SELECT
            rt.req_name,
            er.submission_status,
            er.review_status
        FROM event_requirements er
        INNER JOIN requirement_templates rt
            ON er.req_template_id = rt.req_template_id
        WHERE er.event_id = ?
        ",
        "i",
        [$event_id]
    );

    foreach ($rows as $row) {
        $req_name = $row["req_name"] ?? "";

        // Skip Narrative Report (checked separately in isNarrativeApproved)
        if ($req_name === "Narrative Report") {
            continue;
        }

        // Requirement must be uploaded
        if (($row["submission_status"] ?? "") !== "Uploaded") {
            return false;  // Found a requirement that isn't uploaded
        }

        // Requirement must be approved by admin
        if (($row["review_status"] ?? "") !== "Approved") {
            return false;  // Found a requirement that isn't approved
        }
    }

    // All pre-event requirements (except Narrative) are uploaded and approved
    return true;
}

/**
 * Check Narrative Report Approved
 * 
 * Validates that the Narrative Report requirement is:
 * 1. Submitted/Uploaded by user
 * 2. Reviewed and Approved by admin
 * 
 * Used before transitioning event to Completed status.
 * Separate from pre-event requirements (which are checked by allPreEventRequirementsApproved).
 * 
 * @param mysqli $conn Database connection
 * @param int $event_id Event ID to check
 * @return bool True if narrative report submitted and approved
 */
function isNarrativeApproved(mysqli $conn, int $event_id): bool
{
    // Fetch Narrative Report requirement for this event
    $row = fetchOne(
        $conn,
        "
        SELECT
            er.submission_status,
            er.review_status
        FROM event_requirements er
        INNER JOIN requirement_templates rt
            ON er.req_template_id = rt.req_template_id
        WHERE er.event_id = ?
          AND rt.req_name = 'Narrative Report'
        LIMIT 1
        ",
        "i",
        [$event_id]
    );

    // If no Narrative Report found for event, return false
    if (!$row) {
        return false;
    }

    // Check both conditions: uploaded AND approved
    return (($row["submission_status"] ?? "") === "Uploaded")
        && (($row["review_status"] ?? "") === "Approved");
}

try {
    $conn->begin_transaction();

    /* ==================== ACTION: SAVE REMARKS ==================== */
    // Save admin feedback/remarks without changing event status
    // Can be used at any point in the workflow for documentation
    if ($action === "save_remarks") {
        // Update event remarks field and timestamp
        // Store null if remarks are empty (to distinguish from empty string)
        execQuery(
            $conn,
            "
            UPDATE events
            SET
                admin_remarks = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE event_id = ?
            ",
            "si",
            [$admin_remarks !== "" ? $admin_remarks : null, $event_id]
        );
    }

    /* ==================== ACTION: MARK NEEDS REVISION ==================== */
    // Return event to user for corrections/improvements
    // Resets workflow to allow user to resubmit documents or revise submissions
    if ($action === "needs_revision") {
        // Only allow from specific statuses: can't go backwards from Draft or Completed
        if (!in_array($current_status, ["Pending Review", "Approved", "Needs Revision"], true)) {
            throw new Exception("This event cannot be marked as Needs Revision from its current status.");
        }

        // Update event status to "Needs Revision" and store admin remarks
        execQuery(
            $conn,
            "
            UPDATE events
            SET
                event_status = 'Needs Revision',
                admin_remarks = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE event_id = ?
            ",
            "si",
            [$admin_remarks !== "" ? $admin_remarks : null, $event_id]
        );
    }

    /* ==================== ACTION: APPROVE EVENT ==================== */
    // Advance event from pending/revision to approved status
    // Indicates all pre-event requirements are in order and event can proceed
    // Narrative Report is not required for approval (only for completion)
    if ($action === "approve") {
        // Only allow from statuses that indicate pending review
        // Cannot approve Draft (incomplete) or Completed (already done) events
        if (!in_array($current_status, ["Pending Review", "Needs Revision"], true)) {
            throw new Exception("Only Pending Review or Needs Revision events can be approved.");
        }

        // Verify all pre-event requirements (documents, etc.) are uploaded and approved
        // Narrative Report checked separately as it's required for completion, not approval
        if (!allPreEventRequirementsApproved($conn, $event_id)) {
            throw new Exception("All pre-event requirements must be uploaded and approved before approving the event.");
        }

        // Update event status to "Approved" and store admin remarks
        execQuery(
            $conn,
            "
            UPDATE events
            SET
                event_status = 'Approved',
                admin_remarks = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE event_id = ?
            ",
            "si",
            [$admin_remarks !== "" ? $admin_remarks : null, $event_id]
        );
    }

    /* ==================== ACTION: COMPLETE EVENT ==================== */
    // Mark event as fully completed (final state in workflow)
    // Indicates all requirements including narrative report are approved
    if ($action === "complete") {
        // Only allow from Approved status
        // Event must be approved before it can be completed
        if ($current_status !== "Approved") {
            throw new Exception("Only Approved events can be marked as Completed.");
        }

        // Verify Narrative Report is submitted and approved
        // This is the final requirement check before marking event complete
        if (!isNarrativeApproved($conn, $event_id)) {
            throw new Exception("Narrative Report must be uploaded and approved before completing the event.");
        }

        // Update event status to "Completed" and store admin remarks
        execQuery(
            $conn,
            "
            UPDATE events
            SET
                event_status = 'Completed',
                admin_remarks = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE event_id = ?
            ",
            "si",
            [$admin_remarks !== "" ? $admin_remarks : null, $event_id]
        );
    }

    $conn->commit();

} catch (Exception $e) {
    $conn->rollback();
    popup_error("Failed to update event status: " . $e->getMessage());
}

// ==================== FINAL REDIRECT ====================
// Redirect back to event management page to see updated status
// Shows success message from session or displays error if validation failed
header("Location: admin_manage_event.php?id=" . $event_id);
exit();
?>