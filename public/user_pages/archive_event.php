<?php
session_start();
require_once __DIR__ . '/../../app/database.php';

// ==================== SECURITY CHECK ====================
// Verify current user is logged in; if not, redirect to login page
requireLogin();

if (isset($_POST['action'])) {
  $event_id = (int) $_POST['event_id'];
  $user_id = (int) $_SESSION['user_id'];
  $action = $_POST['action'];

  if ($action === 'archive' || $action === 'restore') {
    // ============= DETERMINE ARCHIVE VALUE AND REDIRECT =============
    // For 'archive': set archived_at to NOW() (current timestamp)
    // For 'restore': set archived_at to NULL (unarchive the event)
    $archiveValue = ($action === 'archive') ? 'NOW()' : 'NULL';
    
    // Determine redirect target based on action
    // 'archive' → archived_events.php (show archived events)
    // 'restore' → my_events.php (show active events)
    $redirect = ($action === 'archive') ? 'archived_events.php' : 'my_events.php';

    // ============= DATABASE UPDATE =============
    // SQL query to update event's archived_at field
    // WHERE clause ensures:
    //   - Only the target event is updated
    //   - User can only archive/restore their own events (security check)
    $sql = "
      UPDATE events
      SET archived_at = $archiveValue
      WHERE event_id = ? AND user_id = ?
    ";

    execQuery($conn, $sql, "ii", [$event_id, $user_id]);

    // ============= REDIRECT TO APPROPRIATE PAGE =============
    // After successful update, redirect to the appropriate page
    // This is a POST-Redirect-Get pattern to prevent form resubmission
    header("Location: $redirect");
    exit();
  }
}
?>