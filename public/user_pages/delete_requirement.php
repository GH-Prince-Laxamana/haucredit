<?php
/**
 * Requirement Document Deletion Handler
 * 
 * This module handles the removal of uploaded requirement documents from events.
 * Documents can only be deleted for events in specific statuses (Pending Review, Needs Revision).
 * When a document is deleted, the requirement reverts to "Pending" status for re-upload.
 * 
 * Security & Data Integrity:
 * - Verifies user ownership of the event
 * - Prevents deletion of Narrative Reports (special handling required)
 * - Only allows deletion for events in editable statuses
 * - Ensures a file actually exists before attempting deletion
 * - Uses transactions to maintain database consistency
 * - Deletes physical files from disk only after database commit succeeds
 * 
 * Behavior:
 * - Requirement status changes from "Uploaded" back to "Pending"
 * - Review metadata (reviewer, remarks, review_date) is cleared
 * - Document counters are recalculated to reflect the change
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

if ($event_req_id <= 0) {
    popup_error("Invalid request.");
}

// ============================================================================
// OWNERSHIP, STATUS & PERMISSION VALIDATION
// ============================================================================

/**
 * Verify that:
 * 1. The requirement exists in the system
 * 2. The logged-in user owns the event associated with the requirement
 * 3. The event has NOT been archived (active events only)
 * 4. The requirement is not a Narrative Report (must use dedicated page)
 * 5. The event is in a status that allows document removal
 */

// Query to fetch requirement details and verify ownership and event status
// Uses INNER JOINs to ensure all related records exist
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

// Verify the requirement exists and user has access permission
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

// ============================================================================
// EVENT STATUS VALIDATION
// ============================================================================

// Define which event statuses allow document removal
// Pending Review: Event submitted, user can fix if needed
// Needs Revision: Event returned for fixes, user can re-upload documents
const ALLOWED_REMOVE_STATUSES = ['Pending Review', 'Needs Revision'];

// Enforce that the event is in a state that permits deleting documents
// Users cannot remove documents from Draft, Approved, or Rejected events
if (!in_array($event_status, ALLOWED_REMOVE_STATUSES, true)) {
    popup_error("Document removal is not allowed for events with status: " . $event_status);
}

// ============================================================================
// FILE PATH COLLECTION & EXISTENCE VALIDATION
// ============================================================================

/**
 * Collect all file paths associated with this requirement BEFORE deletion.
 * These files need to be removed from disk after the database deletion commits.
 * 
 * Verify that:
 * - At least one file record exists (prevent deletion-of-nothing)
 * - File path is not null or empty (data integrity check)
 */

// Query all file records currently associated with this requirement
// A requirement should have only one current file (is_current = 1)
// but we handle multiple for safety
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

// Verify that at least one file exists to delete
// Prevents error if user somehow tries to delete a requirement with no files
if (empty($fileRows)) {
    popup_error("No uploaded file found for this requirement.");
}

// Extract file paths from query results
// Store these for later deletion from disk (after transaction commit)
$filePaths = [];
foreach ($fileRows as $row) {
    if (!empty($row['file_path'])) {
        $filePaths[] = $row['file_path'];
    }
}

// ============================================================================
// DATABASE OPERATIONS & TRANSACTION MANAGEMENT
// ============================================================================

/**
 * Use an ACID transaction to ensure consistent state during document deletion.
 * If any step fails, ALL changes roll back, leaving the requirement in a consistent state.
 */

try {
    // Begin atomic transaction - all following operations complete together or not at all
    $conn->begin_transaction();

    // ===== STEP 1: DELETE FILE RECORDS FROM DATABASE =====
    
    // Remove all file records associated with this requirement
    // This deletes the requirement_files entries (but not the files on disk yet)
    // Files are deleted from disk later, after transaction commits
    execQuery(
        $conn,
        "
        DELETE FROM requirement_files
        WHERE event_req_id = ?
        ",
        "i",
        [$event_req_id]
    );

    // ===== STEP 2: RESET REQUIREMENT STATUS =====
    
    /**
     * Reset the requirement to "Pending" status after file deletion
     * This allows the user to re-upload the document.
     * 
     * Also clear review metadata to reflect that this is now a fresh requirement:
     * - submission_status: 'Uploaded' -> 'Pending' (no file currently uploaded)
     * - review_status: Any previous status -> 'Not Reviewed' (reset for new submission)
     * - reviewed_at: Clear the review timestamp
     * - reviewer_id: Clear the reviewer information
     * - remarks: Clear any previous review remarks/feedback
     */
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

    // ===== STEP 3: RECALCULATE DOCUMENT COUNTERS =====
    
    /**
     * Update the event's document tracking counters
     * These show progress: "X of Y requirements completed"
     */
    
    // Count total number of requirements for this event
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

    // Count number of uploaded (Uploaded status) requirements
    // Since we just changed this requirement to Pending, count reflects the deletion
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

    // Update event counters with new values
    // docs_total: total requirements (including the one we just reset)
    // docs_uploaded: count decreased by 1 (this requirement is now Pending)
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