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

if ($event_req_id <= 0 || !isset($_FILES['document'])) {
    popup_error("Missing data.");
}

$file = $_FILES['document'];

if (!isset($file['error']) || is_array($file['error'])) {
    popup_error("Invalid upload data.");
}

if ($file['error'] !== UPLOAD_ERR_OK) {
    popup_error("Upload failed.");
}

/* ================= VALIDATE OWNERSHIP + EVENT STATUS ================= */
$owned_requirement = fetchOne(
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
    [$event_req_id, $_SESSION["user_id"]]
);

if (!$owned_requirement) {
    popup_error("Requirement not found or access denied.");
}

$event_id = (int) $owned_requirement['event_id'];
$event_status = $owned_requirement['event_status'] ?? '';
$req_name = $owned_requirement['req_name'] ?? '';

/* ================= BLOCK NARRATIVE REPORT HERE ================= */
if ($req_name === 'Narrative Report') {
    popup_error("Narrative Report must be submitted through the Narrative Report page.");
}

/* ================= ENFORCE ALLOWED EVENT STATUSES ================= */
$allowed_upload_statuses = ['Pending Review', 'Needs Revision'];

if (!in_array($event_status, $allowed_upload_statuses, true)) {
    popup_error("Document uploads are not allowed for events with status: " . $event_status);
}

/* ================= FILE TYPE VALIDATION ================= */
$allowed_extensions = ['pdf'];
$file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

if (!in_array($file_extension, $allowed_extensions, true)) {
    popup_error("Invalid file type. Only PDF files are allowed.");
}

/* ================= MIME TYPE VALIDATION ================= */
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

$allowed_mime_types = ['application/pdf'];

if (!in_array($mime_type, $allowed_mime_types, true)) {
    popup_error("Invalid file type. Only PDF files are allowed.");
}

/* ================= FILE SIZE VALIDATION ================= */
$max_file_size = 100 * 1024 * 1024; // 100MB

if ((int) $file['size'] > $max_file_size) {
    popup_error("File too large. Max 100MB.");
}

/* ================= UPLOAD DIRECTORY SETUP ================= */
$upload_dir = __DIR__ . "/../uploads/requirements/";

if (!is_dir($upload_dir) && !mkdir($upload_dir, 0777, true)) {
    popup_error("Failed to create upload directory.");
}

/* ================= GENERATE UNIQUE FILENAME ================= */
$unique_filename = uniqid("req_", true) . "." . $file_extension;
$file_relative_path = "uploads/requirements/" . $unique_filename;
$file_full_path = $upload_dir . $unique_filename;

/* ================= SAVE FILE TO DISK FIRST ================= */
if (!move_uploaded_file($file['tmp_name'], $file_full_path)) {
    popup_error("Upload failed.");
}

try {
    $conn->begin_transaction();

    /* ================= GET OLD FILES ================= */
    $oldFileRows = fetchAll(
        $conn,
        "
        SELECT file_path
        FROM requirement_files
        WHERE event_req_id = ?
          AND file_path IS NOT NULL
          AND file_path != ''
        ",
        "i",
        [$event_req_id]
    );

    $old_paths = [];
    foreach ($oldFileRows as $row) {
        $old_paths[] = $row['file_path'];
    }

    /* ================= REMOVE OLD FILE ROWS ================= */
    execQuery(
        $conn,
        "
        DELETE FROM requirement_files
        WHERE event_req_id = ?
        ",
        "i",
        [$event_req_id]
    );

    /* ================= INSERT NEW FILE ================= */
    $original_file_name = $file['name'];
    $file_size = (int) $file['size'];
    $uploaded_by = (int) $_SESSION["user_id"];

    execQuery(
        $conn,
        "
        INSERT INTO requirement_files (
            event_req_id,
            file_path,
            original_file_name,
            file_type,
            file_size,
            uploaded_by,
            uploaded_at,
            is_current
        ) VALUES (?, ?, ?, ?, ?, ?, NOW(), 1)
        ",
        "isssii",
        [$event_req_id, $file_relative_path, $original_file_name, $mime_type, $file_size, $uploaded_by]
    );

    /* ================= RESET REQUIREMENT REVIEW CYCLE ================= */
    execQuery(
        $conn,
        "
        UPDATE event_requirements
        SET
            submission_status = 'Uploaded',
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

    /* ================= RECALCULATE EVENT DOC COUNTS ================= */
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
        SET docs_total = ?, docs_uploaded = ?, updated_at = CURRENT_TIMESTAMP
        WHERE event_id = ?
        ",
        "iii",
        [$total_docs, $uploaded_docs, $event_id]
    );

    $conn->commit();

    /* ================= DELETE OLD FILES FROM DISK AFTER COMMIT ================= */
    foreach ($old_paths as $old_relative_path) {
        $old_full_path = __DIR__ . "/../" . ltrim($old_relative_path, "/");
        if (is_file($old_full_path)) {
            @unlink($old_full_path);
        }
    }

} catch (Exception $e) {
    $conn->rollback();

    if (is_file($file_full_path)) {
        @unlink($file_full_path);
    }

    popup_error("Failed to upload document: " . $e->getMessage());
}

/* ================= REDIRECT BACK ================= */
$redirect_url = $_SERVER['HTTP_REFERER'] ?? 'my_events.php';
header("Location: " . $redirect_url);
exit();
?>