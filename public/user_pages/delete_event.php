<?php
/**
 * Event Permanent Deletion Handler
 * 
 * This module handles the permanent deletion of archived events.
 * Only events with archived_at timestamps can be deleted to prevent accidental removal
 * of active events. Associated files stored on disk are cleaned up after successful deletion.
 * 
 * Security & Data Integrity:
 * - Verifies user ownership of the event
 * - Only allows deletion of archived events (soft-deleted)
 * - Uses database cascade deletes to remove all related records atomically
 * - Cleans up physical files from disk after database commit
 * - Rolls back all changes if deletion fails
 * 
 * Database Cascade Behavior:
 * Deleting an event cascades to:
 * - event_type: Deleted via ON DELETE CASCADE
 * - event_dates: Deleted via ON DELETE CASCADE
 * - event_participants: Deleted via ON DELETE CASCADE
 * - event_location: Deleted via ON DELETE CASCADE
 * - event_logistics: Deleted via ON DELETE CASCADE
 * - event_metrics: Deleted via ON DELETE CASCADE
 * - event_requirements: Deleted via ON DELETE CASCADE
 * - requirement_files: Deleted via ON DELETE CASCADE (through event_requirements)
 * - calendar_entries: event_id set to NULL via ON DELETE SET NULL (keeps calendar entry)
 */

session_start();
require_once __DIR__ . '/../../app/database.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['event_id'])) {
    popup_error("Invalid request.");
}

$event_id = (int) $_POST['event_id'];
$user_id = (int) $_SESSION['user_id'];

if ($event_id <= 0) {
    popup_error("Invalid event.", PUBLIC_URL . 'index.php');
}

// ============================================================================
// OWNERSHIP & ARCHIVE STATUS VERIFICATION
// ============================================================================

/**
 * Verify that:
 * 1. The event exists in the database
 * 2. The logged-in user owns the event (as creator)
 * 3. The event has been previously archived (soft-deleted)
 * 
 * This prevents accidental deletion of active events and ensures
 * only the event owner can delete their own events.
 * 
 * Only archived events (where archived_at IS NOT NULL) can be permanently deleted.
 * This provides a safety mechanism - events must be archived before permanent removal.
 */

// Query to check if the event exists, belongs to the current user,
// and has been previously archived
$checkArchivedEventSql = "
    SELECT event_id
    FROM events
    WHERE event_id = ?
      AND user_id = ?
      AND archived_at IS NOT NULL
    LIMIT 1
";

// Fetch the event record with all three validations (ID, ownership, archive status)
$eventRow = fetchOne(
    $conn,
    $checkArchivedEventSql,
    "ii",
    [$event_id, $user_id]
);

// Terminate if event doesn't exist, doesn't belong to user, or isn't archived
if (!$eventRow) {
    popup_error("Unauthorized action or event is not archived.");
}

// ============================================================================
// FILE PATH COLLECTION
// ============================================================================

/**
 * Collect all file paths associated with this event BEFORE deletion.
 * These files need to be removed from disk after the database deletion commits.
 * 
 * The relationship chain is:
 * events (being deleted)
 *   -> event_requirements (cascade deleted)
 *     -> requirement_files (cascade deleted, contains file paths)
 * 
 * We collect paths while records still exist, then delete them from disk
 * after database transaction succeeds.
 */

// Query to retrieve all file paths associated with this event
// Joins through event_requirements to requirement_files
// Only selects distinct paths that have actual values (non-null, non-empty)
$fetchEventFilePathsSql = "
    SELECT DISTINCT rf.file_path
    FROM requirement_files rf
    INNER JOIN event_requirements er
        ON rf.event_req_id = er.event_req_id
    INNER JOIN events e
        ON er.event_id = e.event_id
    WHERE e.event_id = ?
      AND e.user_id = ?
      AND rf.file_path IS NOT NULL
      AND rf.file_path != ''
";

// Fetch all file records to extract paths
$fileRows = fetchAll(
    $conn,
    $fetchEventFilePathsSql,
    "ii",
    [$event_id, $user_id]
);

// Extract file paths into a simple array for later disk cleanup
$filePaths = [];
foreach ($fileRows as $row) {
    $filePaths[] = $row['file_path'];
}

// ============================================================================
// DATABASE DELETION & TRANSACTION MANAGEMENT
// ============================================================================

/**
 * Use an ACID transaction to ensure all-or-nothing deletion semantics.
 * If any part of the deletion fails, the entire operation rolls back,
 * leaving the database in a consistent state.
 * 
 * Cascade Deletion Behavior:
 * When the events record is deleted, database CASCADE rules automatically
 * remove all dependent records:
 * - event_type (child of events)
 * - event_dates (child of events)
 * - event_participants (child of events)
 * - event_location (child of events)
 * - event_logistics (child of events)
 * - event_metrics (child of events)
 * - event_requirements (child of events) -> which cascades to requirement_files
 * - calendar_entries (has ON DELETE SET NULL, so event_id becomes NULL)
 */

try {
    // Begin atomic transaction - all changes succeed together or rollback together
    $conn->begin_transaction();

    // ===== STEP 1: DELETE THE EVENT RECORD =====
    
    // Delete the event record with triple verification:
    // - Matches the event ID we're deleting
    // - Belongs to the current user (security check)
    // - Has been archived (safety check - only delete truly archived events)
    // 
    // All related records cascade delete automatically via foreign key constraints
    $deleteArchivedEventSql = "
        DELETE FROM events
        WHERE event_id = ?
          AND user_id = ?
          AND archived_at IS NOT NULL
        LIMIT 1
    ";

    $deleteStmt = execQuery(
        $conn,
        $deleteArchivedEventSql,
        "ii",
        [$event_id, $user_id]
    );

    // Verify that exactly one event record was deleted
    // If affected_rows is 0, something went wrong (maybe concurrent deletion)
    // If affected_rows > 1, the LIMIT 1 was somehow ignored (shouldn't happen)
    if ($deleteStmt->affected_rows === 0) {
        throw new Exception("Event could not be deleted.");
    }

    $conn->commit();

    // ===== STEP 3: DELETE PHYSICAL FILES FROM DISK =====
    
    /**
     * Delete uploaded files from disk AFTER the database commit succeeds.
     * This ensures we don't orphan files if the database delete fails.
     * 
     * If this step fails (permissions, missing files), we continue anyway
     * because the database is already consistent. Orphaned files are
     * preferable to a partially-deleted event record.
     */
    foreach ($filePaths as $relativePath) {
        // Convert relative path to absolute filesystem path
        // relativePath example: "uploads/requirements/req_12345.pdf"
        // absolutePath example: "/var/www/html/public/uploads/requirements/req_12345.pdf"
        $fullPath = __DIR__ . "/../" . ltrim($relativePath, "/");

        if (is_file($fullPath)) {
            @unlink($fullPath);
        }
    }

} catch (Exception $e) {
    $conn->rollback();
    popup_error("Deletion failed: " . $e->getMessage());
}

header("Location: archived_events.php");
exit();
?>