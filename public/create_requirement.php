<?php
session_start();
require_once "../app/database.php";

// ===== AUTHENTICATION CHECK =====
// Verify user is logged in before allowing file upload
if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit();
}

// ===== REQUEST METHOD VALIDATION =====
// Only POST requests are allowed for file uploads
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    popup_error("Invalid request.");
}

// ===== INPUT VALIDATION =====
// Extract and validate required form data
$req_id = $_POST['req_id'] ?? null;

if (!$req_id || !isset($_FILES['document'])) {
    popup_error("Missing data.");
}

// ===== FILE REFERENCE =====
// Store file upload data for easier access
$file = $_FILES['document'];

// ===== FILE TYPE VALIDATION =====
// Define allowed file extensions and MIME types
$allowed_extensions = ['pdf'];

// Extract file extension from uploaded filename
$file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

// Validate extension against whitelist
if (!in_array($file_extension, $allowed_extensions)) {
    popup_error("Invalid file type.");
}

// ===== MIME TYPE VALIDATION =====
// Use fileinfo library to verify actual file content type
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

// Define allowed MIME types (more secure than extension-only check)
$allowed_mime_types = [
    'application/pdf'
];

if (!in_array($mime_type, $allowed_mime_types)) {
    popup_error("Invalid file type.");
}

// ===== FILE SIZE VALIDATION =====
// Enforce maximum file size limit (100MB)
$max_file_size = 100 * 1024 * 1024; // 100MB in bytes

if ($file['size'] > $max_file_size) {
    popup_error("File too large. Max 100MB.");
}

// ===== UPLOAD DIRECTORY SETUP =====
// Define upload directory path
$upload_dir = __DIR__ . "/../uploads/requirements/";

// Create directory if it doesn't exist
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// ===== GENERATE UNIQUE FILENAME =====
// Create unique filename to prevent collisions and avoid exposing original filenames
$unique_filename = uniqid("req_") . "." . $file_extension;

// Define both relative and absolute file paths
$file_relative_path = "uploads/requirements/" . $unique_filename;
$file_full_path = $upload_dir . $unique_filename;

// ===== FILE MOVE OPERATION =====
// Move uploaded file from temporary location to permanent storage
if (!move_uploaded_file($file['tmp_name'], $file_full_path)) {
    popup_error("Upload failed.");
}

// ===== DELETE PREVIOUS FILE (if exists) =====
// Retrieve the previous file path before updating the record
$old_file_stmt = $conn->prepare("SELECT file_path FROM requirements WHERE req_id = ?");
$old_file_stmt->bind_param("i", $req_id);
$old_file_stmt->execute();
$old_file_result = $old_file_stmt->get_result()->fetch_assoc();

// If a previous file exists, delete it to avoid storage bloat
if ($old_file_result && !empty($old_file_result['file_path'])) {
    $old_file_full_path = __DIR__ . "/../" . $old_file_result['file_path'];

    // Only delete if file actually exists on disk
    if (file_exists($old_file_full_path)) {
        unlink($old_file_full_path);
    }
}

// ===== DATABASE UPDATE =====
// Update requirement record with new file path and upload metadata
$update_stmt = $conn->prepare("
    UPDATE requirements
    SET file_path = ?, doc_status = 'uploaded', uploaded_at = NOW()
    WHERE req_id = ?
");

$update_stmt->bind_param("si", $file_relative_path, $req_id);
$update_stmt->execute();

// ===== REDIRECT =====
// Return user to previous page (referer) after successful upload
header("Location: " . $_SERVER['HTTP_REFERER']);
exit();