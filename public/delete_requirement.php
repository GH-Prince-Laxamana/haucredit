<?php
session_start();
require_once "../app/database.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    popup_error("Invalid request.");
}

$event_req_id = isset($_POST['event_req_id']) ? (int) $_POST['event_req_id'] : 0;

if ($event_req_id <= 0) {
    popup_error("Invalid request.");
}

/* ================= OWNERSHIP + REQUIREMENT CHECK ================= */
$checkRequirementOwnershipSql = "
    SELECT
        er.event_req_id,
        er.event_id,
        er.submission_status,
        e.user_id
    FROM event_requirements er
    INNER JOIN events e
        ON er.event_id = e.event_id
    WHERE er.event_req_id = ?
      AND e.user_id = ?
      AND e.archived_at IS NULL
    LIMIT 1
";

$reqRow = fetchOne(
    $conn,
    $checkRequirementOwnershipSql,
    "ii",
    [$event_req_id, $_SESSION['user_id']]
);

if (!$reqRow) {
    popup_error("Requirement not found or access denied.");
}

$event_id = (int) $reqRow['event_id'];

/* ================= GET CURRENT FILE ================= */
$fetchAllRequirementFilesSql = "
    SELECT file_path
    FROM requirement_files
    WHERE event_req_id = ?
      AND file_path IS NOT NULL
      AND file_path != ''
";

$fileRows = fetchAll(
    $conn,
    $fetchAllRequirementFilesSql,
    "i",
    [$event_req_id]
);

if (empty($fileRows)) {
    popup_error("No uploaded file found for this requirement.");
}

$filePaths = [];
foreach ($fileRows as $row) {
    $filePaths[] = $row['file_path'];
}

$currentFile = fetchOne(
    $conn,
    $fetchAllRequirementFilesSql,
    "i",
    [$event_req_id]
);

if (!$currentFile) {
    popup_error("No uploaded file found for this requirement.");
}

$req_file_id = (int) $currentFile['req_file_id'];
$file_path = $currentFile['file_path'];
$full_path = __DIR__ . "/../" . ltrim($file_path, "/");

try {
    $conn->begin_transaction();

    /* ================= DELETE FILE RECORD ================= */
    $deleteRequirementFilesSql = "
    DELETE FROM requirement_files
    WHERE event_req_id = ?
";

    execQuery(
        $conn,
        $deleteRequirementFilesSql,
        "i",
        [$event_req_id]
    );

    /* ================= RESET REQUIREMENT STATUS ================= */
    $resetRequirementStatusSql = "
        UPDATE event_requirements
        SET submission_status = 'pending',
            review_status = 'not_reviewed',
            reviewed_at = NULL,
            reviewer_id = NULL,
            remarks = NULL,
            updated_at = CURRENT_TIMESTAMP
        WHERE event_req_id = ?
    ";
    execQuery(
        $conn,
        $resetRequirementStatusSql,
        "i",
        [$event_req_id]
    );

    /* ================= RECALCULATE EVENT COUNTS ================= */
    $countEventRequirementsSql = "
        SELECT COUNT(*) AS total_docs
        FROM event_requirements
        WHERE event_id = ?
    ";
    $totalDocsRow = fetchOne(
        $conn,
        $countEventRequirementsSql,
        "i",
        [$event_id]
    );
    $total_docs = (int) ($totalDocsRow['total_docs'] ?? 0);

    $countUploadedRequirementsSql = "
        SELECT COUNT(*) AS uploaded_docs
        FROM event_requirements
        WHERE event_id = ?
          AND submission_status = 'uploaded'
    ";
    $uploadedDocsRow = fetchOne(
        $conn,
        $countUploadedRequirementsSql,
        "i",
        [$event_id]
    );
    $uploaded_docs = (int) ($uploadedDocsRow['uploaded_docs'] ?? 0);

    $updateEventDocumentCountsSql = "
        UPDATE events
        SET docs_total = ?,
            docs_uploaded = ?,
            updated_at = CURRENT_TIMESTAMP
        WHERE event_id = ?
    ";
    execQuery(
        $conn,
        $updateEventDocumentCountsSql,
        "iii",
        [$total_docs, $uploaded_docs, $event_id]
    );

    $conn->commit();

    /* ================= DELETE PHYSICAL FILE AFTER COMMIT ================= */
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