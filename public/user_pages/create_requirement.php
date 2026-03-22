<?php
/**
 * Requirement Document Upload Handler
 * 
 * This module handles the upload and storage of requirement documents for events.
 * It validates file integrity, checks ownership permissions, and manages the file
 * lifecycle including old file deletion and document counter updates.
 * 
 * Flow:
 * 1. Validate request method and POST data
 * 2. Verify ownership and access permissions
 * 3. Validate file type, MIME type, and size
 * 4. Save file to disk
 * 5. Start transaction to update database
 * 6. Update requirement record and document counts
 * 7. Commit transaction and clean up old files
 */

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

// Extract uploaded file from global FILES array
$file = $_FILES['document'];

// ============================================================================
// FILE UPLOAD STRUCTURE VALIDATION
// ============================================================================

// Check that the file array has the expected structure (not an array of errors)
// This validation catches various edge cases and malformed upload attempts
if (!isset($file['error']) || is_array($file['error'])) {
    popup_error("Invalid upload data.");
}

// Check that the upload completed without PHP-level errors
// UPLOAD_ERR_OK (0) indicates successful upload from client
if ($file['error'] !== UPLOAD_ERR_OK) {
    popup_error("Upload failed.");
}

// ============================================================================
// OWNERSHIP & PERMISSION VALIDATION
// ============================================================================

/**
 * Verify that:
 * 1. The requirement exists in the system
 * 2. The logged-in user owns the event associated with the requirement
 * 3. The event has not been archived
 * 4. The requirement is not a Narrative Report (must use dedicated page)
 * 5. The event is in an allowed status for document upload
 */

// Query the database to fetch requirement details and verify ownership
// Uses INNER JOINs to enforce existence of all related records
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

// Terminate if requirement doesn't exist or user doesn't have permission
if (!$owned_requirement) {
    popup_error("Requirement not found or access denied.");
}

// Extract validated event information for later use
$event_id = (int) $owned_requirement['event_id'];
$event_status = $owned_requirement['event_status'] ?? '';
$req_name = $owned_requirement['req_name'] ?? '';

// ============================================================================
// REQUIREMENT TYPE VALIDATION
// ============================================================================

// Narrative Reports have a dedicated submission page - block uploads here
// This requirement type requires special handling and rich text content
if ($req_name === 'Narrative Report') {
    popup_error("Narrative Report must be submitted through the Narrative Report page.");
}

// ============================================================================
// EVENT STATUS VALIDATION
// ============================================================================

// Define which event statuses allow document uploads
// Draft: Not yet submitted, user can still make changes and upload docs
// Needs Revision: Returned for fixes, allows re-uploading documents
const ALLOWED_UPLOAD_STATUSES = ['Pending Review', 'Needs Revision'];

// Enforce that the event is in a state that allows uploading documents
if (!in_array($event_status, ALLOWED_UPLOAD_STATUSES, true)) {
    popup_error("Document uploads are not allowed for events with status: " . $event_status);
}

// ============================================================================
// FILE FORMAT & TYPE VALIDATION
// ============================================================================

/**
 * Whitelist allowed file extensions to prevent malicious uploads
 * Only PDF files are permitted for requirement documents
 */
$allowed_extensions = ['pdf'];

// Extract the file extension from the uploaded filename
// Convert to lowercase to handle case-insensitive comparison
$file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

// Verify that the file extension is in the approved list
if (!in_array($file_extension, $allowed_extensions, true)) {
    popup_error("Invalid file type. Only PDF files are allowed.");
}

// ============================================================================
// MIME TYPE VALIDATION
// ============================================================================

/**
 * Validate the actual MIME type of the uploaded file using fileinfo
 * This checks the file's content, not just the filename extension
 * Prevents attacks where malicious files are disguised with safe extensions
 */

// Initialize fileinfo resource to detect MIME types
$finfo = finfo_open(FILEINFO_MIME_TYPE);

// Detect the actual MIME type of the uploaded file content
// This examines the file's magic bytes/signature for accurate detection
$mime_type = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

// Define whitelist of acceptable MIME types
// Should match file extensions (PDF extension -> PDF MIME type)
$allowed_mime_types = ['application/pdf'];

// Terminate upload if MIME type doesn't match whitelist
if (!in_array($mime_type, $allowed_mime_types, true)) {
    popup_error("Invalid file type. Only PDF files are allowed.");
}

// ============================================================================
// FILE SIZE VALIDATION
// ============================================================================

// Set maximum file size limit to prevent storage abuse and timeout issues
// 100MB is a reasonable limit for PDF documents
$max_file_size = 100 * 1024 * 1024; // 100 MB in bytes

// Verify that the uploaded file doesn't exceed the size limit
if ((int) $file['size'] > $max_file_size) {
    popup_error("File too large. Max 100MB.");
}

// ============================================================================
// FILE STORAGE SETUP
// ============================================================================

// Define the target directory for file uploads
// Uses relative path from the application's public directory
$upload_dir = __DIR__ . "/../uploads/requirements/";

// Create the upload directory if it doesn't already exist
// Recursive creation (true) creates parent directories as needed
// Mode 0777 allows all users to access the directory (restrictive in production)
if (!is_dir($upload_dir) && !mkdir($upload_dir, 0777, true)) {
    popup_error("Failed to create upload directory.");
}

// ============================================================================
// UNIQUE FILENAME GENERATION
// ============================================================================

/**
 * Generate a unique filename to store the file on disk
 * This prevents filename collisions and protects against path traversal attacks
 * Files are stored with random names; original filename stored separately in database
 */

// Create unique filename using uniqid() to prevent overwrites
// Format: req_{microtime_with_entropy}.{extension}
// Example: req_6656a7b8c5c0e3.12345.pdf
$unique_filename = uniqid("req_", true) . "." . $file_extension;

// Define the relative path (for database storage)
// Stored relative to public directory for easier portability
$file_relative_path = "uploads/requirements/" . $unique_filename;

// Define the absolute path (for disk operations)
// Full filesystem path for move_uploaded_file() and file operations
$file_full_path = $upload_dir . $unique_filename;

// ============================================================================
// FILE DISK STORAGE
// ============================================================================

/**
 * Save the file to disk BEFORE database operations
 * This ensures the file exists before we commit database records pointing to it
 * If database operation fails, we can safely delete the orphaned file
 */

// Move the uploaded file from temporary storage to permanent location
// move_uploaded_file() validates that the file came from HTTP upload
if (!move_uploaded_file($file['tmp_name'], $file_full_path)) {
    popup_error("Upload failed.");
}

// ============================================================================
// DATABASE OPERATIONS & TRANSACTION MANAGEMENT
// ============================================================================

/**
 * Start an ACID transaction to ensure data consistency
 * If any step fails, ALL changes roll back automatically
 * This prevents orphaned file records or incomplete updates
 */

try {
    $conn->begin_transaction();

    // ===== STEP 1: RETRIEVE EXISTING FILE RECORDS =====
    
    // Query all file records currently associated with this requirement
    // These are the "old" files that will be replaced by the new upload
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

    // Extract file paths from the query results
    // Store these for later deletion from disk (after commit succeeds)
    $old_paths = [];
    foreach ($oldFileRows as $row) {
        $old_paths[] = $row['file_path'];
    }

    // ===== STEP 2: DELETE EXISTING FILE RECORDS =====
    
    // Remove all file records associated with this requirement
    // This ensures only one active file per requirement (conflict prevention)
    // The old files on disk are deleted later, after transaction commits
    execQuery(
        $conn,
        "
        DELETE FROM requirement_files
        WHERE event_req_id = ?
        ",
        "i",
        [$event_req_id]
    );

    // ===== STEP 3: INSERT NEW FILE RECORD =====
    
    // Extract file metadata for database storage
    $original_file_name = $file['name'];  // Original filename provided by user
    $file_size = (int) $file['size'];     // File size in bytes
    $uploaded_by = (int) $_SESSION["user_id"];  // User ID of uploader

    // Create a new file record in the database
    // Stores both the location (file_path) and original filename
    // is_current = 1 indicates this is the active file (supports file versioning)
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

    // ===== STEP 4: UPDATE REQUIREMENT STATUS =====
    
    // Reset the requirement's review lifecycle after file upload
    // New upload = need for fresh review, so reset review status to 'Not Reviewed'
    // Clear review metadata (reviewer ID, review date, remarks) to reflect new upload
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

    // ===== STEP 5: RECALCULATE DOCUMENT COUNTERS =====
    
    // Query total number of requirements for this event
    // These counters track upload progress (showing X of Y requirements uploaded)
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

    // Query number of uploaded (completed) requirements for this event
    // Used to calculate completion percentage and progress indicators
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

    // Update the event record with recalculated document counts
    // docs_total: total requirements for this event
    // docs_uploaded: number of uploaded/submitted requirements
    // These are displayed to the user as progress indicators
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

    // ===== STEP 6: COMMIT TRANSACTION =====
    
    // All database changes have succeeded - commit them atomically
    // After this point, all changes are durable and visible to other connections
    $conn->commit();

    // ===== STEP 7: CLEANUP OLD FILES FROM DISK =====
    
    /**
     * Delete old file records from disk AFTER transaction commits
     * This ensures database is consistent before removing files
     * If transaction rolled back, old files are preserved (safe fallback)
     */
    foreach ($old_paths as $old_relative_path) {
        // Convert relative path to absolute disk path
        $old_full_path = __DIR__ . "/../" . ltrim($old_relative_path, "/");
        
        // Safely delete the old file from disk
        // @ suppresses warnings if file doesn't exist (non-critical)
        if (is_file($old_full_path)) {
            @unlink($old_full_path);
        }
    }

} catch (Exception $e) {
    $conn->rollback();

    // Delete the newly uploaded file from disk since database save failed
    // Prevents orphaned files occupying storage space
    if (is_file($file_full_path)) {
        @unlink($file_full_path);
    }

    popup_error("Failed to upload document: " . $e->getMessage());
}

// ============================================================================
// POST-UPLOAD REDIRECT
// ============================================================================

/**
 * Redirect user back to the referring page after successful upload
 * This maintains user context and shows the uploaded requirement in the list
 */

// Get the page the user came from (typically the requirements list or event view)
// Fallback to my_events.php if no referrer is available
$redirect_url = $_SERVER['HTTP_REFERER'] ?? 'my_events.php';

// Perform the redirect using HTTP 302 (temporary redirect)
header("Location: " . $redirect_url);
exit();
?>