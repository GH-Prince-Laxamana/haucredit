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
$checkStmt = $conn->prepare("
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
");
$checkStmt->bind_param("ii", $event_req_id, $_SESSION['user_id']);
$checkStmt->execute();
$reqRow = $checkStmt->get_result()->fetch_assoc();

if (!$reqRow) {
    popup_error("Requirement not found or access denied.");
}

$event_id = (int) $reqRow['event_id'];

/* ================= GET CURRENT FILE ================= */
$fileStmt = $conn->prepare("
    SELECT req_file_id, file_path
    FROM requirement_files
    WHERE event_req_id = ?
      AND is_current = 1
    LIMIT 1
");
$fileStmt->bind_param("i", $event_req_id);
$fileStmt->execute();
$fileRes = $fileStmt->get_result();
$currentFile = $fileRes->fetch_assoc();

if (!$currentFile) {
    popup_error("No uploaded file found for this requirement.");
}

$req_file_id = (int) $currentFile['req_file_id'];
$file_path = $currentFile['file_path'];
$full_path = __DIR__ . "/../" . ltrim($file_path, "/");

try {
    $conn->begin_transaction();

    /* ================= DELETE FILE RECORD ================= */
    $deleteFileStmt = $conn->prepare("
        DELETE FROM requirement_files
        WHERE req_file_id = ?
          AND event_req_id = ?
        LIMIT 1
    ");
    $deleteFileStmt->bind_param("ii", $req_file_id, $event_req_id);
    $deleteFileStmt->execute();

    /* ================= RESET REQUIREMENT STATUS ================= */
    $resetReqStmt = $conn->prepare("
        UPDATE event_requirements
        SET submission_status = 'pending',
            review_status = 'not_reviewed',
            reviewed_at = NULL,
            reviewer_id = NULL,
            remarks = NULL,
            updated_at = CURRENT_TIMESTAMP
        WHERE event_req_id = ?
    ");
    $resetReqStmt->bind_param("i", $event_req_id);
    $resetReqStmt->execute();

    /* ================= RECALCULATE EVENT COUNTS ================= */
    $countStmt = $conn->prepare("
        SELECT COUNT(*) AS total_docs
        FROM event_requirements
        WHERE event_id = ?
    ");
    $countStmt->bind_param("i", $event_id);
    $countStmt->execute();
    $total_docs = (int) ($countStmt->get_result()->fetch_assoc()['total_docs'] ?? 0);

    $uploadedStmt = $conn->prepare("
        SELECT COUNT(*) AS uploaded_docs
        FROM event_requirements
        WHERE event_id = ?
          AND submission_status = 'uploaded'
    ");
    $uploadedStmt->bind_param("i", $event_id);
    $uploadedStmt->execute();
    $uploaded_docs = (int) ($uploadedStmt->get_result()->fetch_assoc()['uploaded_docs'] ?? 0);

    $updateEventStmt = $conn->prepare("
        UPDATE events
        SET docs_total = ?,
            docs_uploaded = ?,
            updated_at = CURRENT_TIMESTAMP
        WHERE event_id = ?
    ");
    $updateEventStmt->bind_param("iii", $total_docs, $uploaded_docs, $event_id);
    $updateEventStmt->execute();

    $conn->commit();

    /* ================= DELETE PHYSICAL FILE AFTER COMMIT ================= */
    if (is_file($full_path)) {
        @unlink($full_path);
    }

} catch (Exception $e) {
    $conn->rollback();
    popup_error("Failed to remove uploaded document: " . $e->getMessage());
}

$redirect_url = $_SERVER['HTTP_REFERER'] ?? ('view_event.php?id=' . $event_id);
header("Location: " . $redirect_url);
exit();
?>