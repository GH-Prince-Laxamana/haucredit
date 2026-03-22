<?php
session_start();
require_once __DIR__ . '/../../app/database.php';

requireAdmin();

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    popup_error("Invalid request.");
}

$admin_user_id = (int) $_SESSION["user_id"];
$event_id = isset($_POST["event_id"]) ? (int) $_POST["event_id"] : 0;
$action = trim($_POST["action"] ?? "");
$admin_remarks = trim($_POST["admin_remarks"] ?? "");

if ($event_id <= 0) {
    popup_error("Invalid event.", PUBLIC_URL . 'index.php');
}

$allowed_actions = ["save_remarks", "needs_revision", "approve", "complete"];
if (!in_array($action, $allowed_actions, true)) {
    popup_error("Invalid action.");
}

/* ================= FETCH EVENT ================= */
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

$current_status = $event["event_status"] ?? "";

/* ================= HELPERS ================= */
function allPreEventRequirementsApproved(mysqli $conn, int $event_id): bool
{
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

        if ($req_name === "Narrative Report") {
            continue;
        }

        if (($row["submission_status"] ?? "") !== "Uploaded") {
            return false;
        }

        if (($row["review_status"] ?? "") !== "Approved") {
            return false;
        }
    }

    return true;
}

function isNarrativeApproved(mysqli $conn, int $event_id): bool
{
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

    if (!$row) {
        return false;
    }

    return (($row["submission_status"] ?? "") === "Uploaded")
        && (($row["review_status"] ?? "") === "Approved");
}

try {
    $conn->begin_transaction();

    if ($action === "save_remarks") {
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

    if ($action === "needs_revision") {
        if (!in_array($current_status, ["Pending Review", "Approved", "Needs Revision"], true)) {
            throw new Exception("This event cannot be marked as Needs Revision from its current status.");
        }

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

    if ($action === "approve") {
        if (!in_array($current_status, ["Pending Review", "Needs Revision"], true)) {
            throw new Exception("Only Pending Review or Needs Revision events can be approved.");
        }

        if (!allPreEventRequirementsApproved($conn, $event_id)) {
            throw new Exception("All pre-event requirements must be uploaded and approved before approving the event.");
        }

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

    if ($action === "complete") {
        if ($current_status !== "Approved") {
            throw new Exception("Only Approved events can be marked as Completed.");
        }

        if (!isNarrativeApproved($conn, $event_id)) {
            throw new Exception("Narrative Report must be uploaded and approved before completing the event.");
        }

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

header("Location: admin_manage_event.php?id=" . $event_id);
exit();
?>