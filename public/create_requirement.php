<?php
session_start();
require_once "../app/database.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit();
}

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

/* ================= VALIDATE OWNERSHIP =================
   Ensure the requirement belongs to an event owned by the logged-in user
*/
$owner_stmt = $conn->prepare("
    SELECT
        er.event_req_id,
        er.event_id,
        e.user_id
    FROM event_requirements er
    INNER JOIN events e ON er.event_id = e.event_id
    WHERE er.event_req_id = ?
      AND e.user_id = ?
      AND e.archived_at IS NULL
    LIMIT 1
");
$owner_stmt->bind_param("ii", $event_req_id, $_SESSION["user_id"]);
$owner_stmt->execute();
$owned_requirement = $owner_stmt->get_result()->fetch_assoc();

if (!$owned_requirement) {
    popup_error("Requirement not found or access denied.");
}

$event_id = (int) $owned_requirement['event_id'];

/* ================= FILE TYPE VALIDATION ================= */
$allowed_extensions = ['pdf'];
$file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

if (!in_array($file_extension, $allowed_extensions, true)) {
    popup_error("Invalid file type.");
}

/* ================= MIME TYPE VALIDATION ================= */
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

$allowed_mime_types = ['application/pdf'];

if (!in_array($mime_type, $allowed_mime_types, true)) {
    popup_error("Invalid file type.");
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

/* ================= SAVE FILE FIRST ================= */
if (!move_uploaded_file($file['tmp_name'], $file_full_path)) {
    popup_error("Upload failed.");
}

try {
    $conn->begin_transaction();

    /* ================= MARK OLD FILES AS NOT CURRENT ================= */
    $old_files_stmt = $conn->prepare("
        SELECT file_path
        FROM requirement_files
        WHERE event_req_id = ?
          AND is_current = 1
    ");
    $old_files_stmt->bind_param("i", $event_req_id);
    $old_files_stmt->execute();
    $old_files_result = $old_files_stmt->get_result();

    $old_paths = [];
    while ($row = $old_files_result->fetch_assoc()) {
        if (!empty($row['file_path'])) {
            $old_paths[] = $row['file_path'];
        }
    }

    $mark_old_stmt = $conn->prepare("
        UPDATE requirement_files
        SET is_current = 0
        WHERE event_req_id = ?
          AND is_current = 1
    ");
    $mark_old_stmt->bind_param("i", $event_req_id);
    $mark_old_stmt->execute();

    /* ================= INSERT NEW CURRENT FILE ================= */
    $original_file_name = $file['name'];
    $file_size = (int) $file['size'];
    $uploaded_by = (int) $_SESSION["user_id"];

    $insert_file_stmt = $conn->prepare("
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
    ");
    $insert_file_stmt->bind_param(
        "isssii",
        $event_req_id,
        $file_relative_path,
        $original_file_name,
        $mime_type,
        $file_size,
        $uploaded_by
    );
    $insert_file_stmt->execute();

    /* ================= UPDATE REQUIREMENT STATUS ================= */
    $update_req_stmt = $conn->prepare("
        UPDATE event_requirements
        SET submission_status = 'uploaded',
            updated_at = CURRENT_TIMESTAMP
        WHERE event_req_id = ?
    ");
    $update_req_stmt->bind_param("i", $event_req_id);
    $update_req_stmt->execute();

    /* ================= RECALCULATE EVENT DOC COUNTS ================= */
    $count_stmt = $conn->prepare("
        SELECT COUNT(*) AS total_docs
        FROM event_requirements
        WHERE event_id = ?
    ");
    $count_stmt->bind_param("i", $event_id);
    $count_stmt->execute();
    $total_docs = (int) ($count_stmt->get_result()->fetch_assoc()['total_docs'] ?? 0);

    $uploaded_count_stmt = $conn->prepare("
        SELECT COUNT(*) AS uploaded_docs
        FROM event_requirements
        WHERE event_id = ?
          AND submission_status = 'uploaded'
    ");
    $uploaded_count_stmt->bind_param("i", $event_id);
    $uploaded_count_stmt->execute();
    $uploaded_docs = (int) ($uploaded_count_stmt->get_result()->fetch_assoc()['uploaded_docs'] ?? 0);

    $update_event_stmt = $conn->prepare("
        UPDATE events
        SET docs_total = ?, docs_uploaded = ?, updated_at = CURRENT_TIMESTAMP
        WHERE event_id = ?
    ");
    $update_event_stmt->bind_param("iii", $total_docs, $uploaded_docs, $event_id);
    $update_event_stmt->execute();

    $conn->commit();

    /* ================= DELETE OLD FILES FROM DISK =================
       After successful commit only
    */
    foreach ($old_paths as $old_relative_path) {
        $old_full_path = __DIR__ . "/../" . $old_relative_path;
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
$redirect_url = $_SERVER['HTTP_REFERER'] ?? 'requirements.php';
header("Location: " . $redirect_url);
exit();
?>