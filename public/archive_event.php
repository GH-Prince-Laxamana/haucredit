<?php
// ===== SESSION INITIALIZATION =====
// Start the session to access user authentication data
session_start();

// ===== DATABASE CONNECTION =====
// Include the database connection file to establish a connection to the database
require_once "../app/database.php";

// ===== AUTHENTICATION CHECK =====
// Verify that the user is logged in by checking for the user_id in the session
// If not logged in, redirect to the login page and stop execution
if (!isset($_SESSION["user_id"])) {
  header("Location: index.php");
  exit();
}

// ===== ACTION PROCESSING =====
// Check if an action has been submitted via POST (either 'archive' or 'restore')
if (isset($_POST['action'])) {

  // ===== INPUT EXTRACTION =====
  // Retrieve the event ID from the POST data and cast it to an integer for safety
  // Also get the user ID from the session
  $event_id = (int) $_POST['event_id'];
  $user_id  = $_SESSION['user_id'];

  // ===== ARCHIVE ACTION =====
  // If the action is 'archive', update the event to set the archived_at timestamp to the current time
  if ($_POST['action'] === 'archive') {

    // Prepare and execute the SQL statement to archive the event
    $stmt = $conn->prepare("
        UPDATE events
        SET archived_at = NOW()
        WHERE event_id = ? AND user_id = ?
    ");
    $stmt->bind_param("ii", $event_id, $user_id);
    $stmt->execute();

    // Redirect to the archived events page after successful archiving
    header("Location: archived_events.php");
    exit();
  }

  // ===== RESTORE ACTION =====
  // If the action is 'restore', update the event to clear the archived_at timestamp (set to NULL)
  if ($_POST['action'] === 'restore') {

    // Prepare and execute the SQL statement to restore the event
    $stmt = $conn->prepare("
        UPDATE events
        SET archived_at = NULL
        WHERE event_id = ? AND user_id = ?
    ");
    $stmt->bind_param("ii", $event_id, $user_id);
    $stmt->execute();

    // Redirect to the my events page after successful restoration
    header("Location: my_events.php");
    exit();
  }
}
?>