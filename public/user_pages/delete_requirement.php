<?php
session_start();
require_once __DIR__ . '/../../app/database.php';
require_once APP_PATH . "security_headers.php";
send_security_headers();

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    popup_error("Invalid request.");
}

$event_req_id = isset($_POST['event_req_id']) ? (int) $_POST['event_req_id'] : 0;

if ($event_req_id <= 0) {
    popup_error("Invalid request.");
}

/* ================= OWNERSHIP + STATUS + REQUIREMENT CHECK ================= */
$reqRow = fetchOne(
    $conn,
    "
    SELECT
        er.event_req_id,
        er.event_id,
        er.submission_status,
        er.review_status,
        rt.req_name,
        e.user_id,
        e.event_status,
        e.archived_at
    FROM event_requirements er
    INNER JOIN events e
        ON er.event_id = e.event_id
    INNER JOIN requirement_templates rt
        ON er.req_template_id = rt.req_template_id
    WHERE er.event_req_id = ?
      AND e.user_id = ?
      AND e.archived_at IS NULL
    LIMIT 1
    ",
    "ii",
    [$event_req_id, $_SESSION['user_id']]
);

if (!$reqRow) {
    popup_error("Requirement not found or access denied.");
}

$event_id = (int) $reqRow['event_id'];
$event_status = $reqRow['event_status'] ?? '';
$req_name = $reqRow['req_name'] ?? '';

/* ================= BLOCK NARRATIVE REPORT HERE ================= */
if ($req_name === 'Narrative Report') {
    popup_error("Narrative Report must be managed through the Narrative Report page.");
}

/* ================= ENFORCE ALLOWED EVENT STATUSES ================= */
$allowed_remove_statuses = ['Pending Review', 'Needs Revision'];

if (!in_array($event_status, $allowed_remove_statuses, true)) {
    popup_error("Document removal is not allowed for events with status: " . $event_status);
}

/* ================= GET CURRENT FILES ================= */
$fileRows = fetchAll(
    $conn,
    "
    SELECT req_file_id, file_path
    FROM requirement_files
    WHERE event_req_id = ?
      AND file_path IS NOT NULL
      AND file_path != ''
    ",
    "i",
    [$event_req_id]
);

if (empty($fileRows)) {
    popup_error("No uploaded file found for this requirement.");
}

$filePaths = [];
foreach ($fileRows as $row) {
    if (!empty($row['file_path'])) {
        $filePaths[] = $row['file_path'];
    }
}

try {
    $conn->begin_transaction();

    /* ================= DELETE FILE RECORDS ================= */
    execQuery(
        $conn,
        "
        DELETE FROM requirement_files
        WHERE event_req_id = ?
        ",
        "i",
        [$event_req_id]
    );

    /* ================= RESET REQUIREMENT STATUS ================= */
    execQuery(
        $conn,
        "
        UPDATE event_requirements
        SET
            submission_status = 'Pending',
            review_status = 'Not Reviewed',
            reviewed_at = NULL,
            reviewer_id = NULL,
            remarks = NULL,
            updated_at = CURRENT_TIMESTAMP
        WHERE event_req_id = ?
        ",
        "i",
        [$event_req_id]
    );

    /* ================= RECALCULATE EVENT COUNTS ================= */
    $totalDocsRow = fetchOne(
        $conn,
        "
        SELECT COUNT(*) AS total_docs
        FROM event_requirements
        WHERE event_id = ?
        ",
        "i",
        [$event_id]
    );
    $total_docs = (int) ($totalDocsRow['total_docs'] ?? 0);

    $uploadedDocsRow = fetchOne(
        $conn,
        "
        SELECT COUNT(*) AS uploaded_docs
        FROM event_requirements
        WHERE event_id = ?
          AND submission_status = 'Uploaded'
        ",
        "i",
        [$event_id]
    );
    $uploaded_docs = (int) ($uploadedDocsRow['uploaded_docs'] ?? 0);

    execQuery(
        $conn,
        "
        UPDATE events
        SET
            docs_total = ?,
            docs_uploaded = ?,
            updated_at = CURRENT_TIMESTAMP
        WHERE event_id = ?
        ",
        "iii",
        [$total_docs, $uploaded_docs, $event_id]
    );

    $conn->commit();

    /* ================= DELETE PHYSICAL FILES AFTER COMMIT ================= */
    foreach ($filePaths as $relativePath) {
        $fullPath = __DIR__ . "/../" . ltrim($relativePath, "/");

        if (is_file($fullPath)) {
            @unlink($fullPath);
        }
    }

} catch (Exception $e) {
    $conn->rollback();
    popup_error("Failed to remove uploaded document: " . $e->getMessage());
}

$redirect_url = $_SERVER['HTTP_REFERER'] ?? ('view_event.php?id=' . $event_id);
header("Location: " . $redirect_url);
exit();
?>