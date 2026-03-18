<?php
session_start();
require_once "../app/database.php";

// ===== AUTHENTICATION CHECK =====
// Ensure user is logged in before allowing event deletion
if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit();
}

// ===== REQUEST VALIDATION =====
// Only process POST requests with event_id parameter
if (isset($_POST['event_id'])) {

    // Extract and sanitize event ID from POST data
    $event_id = (int) $_POST['event_id'];
    $user_id = $_SESSION['user_id'];

    // ===== OWNERSHIP VERIFICATION =====
    // Confirm the event belongs to the logged-in user to prevent unauthorized deletion
    $check = $conn->prepare("
        SELECT event_id
        FROM events
        WHERE event_id = ? AND user_id = ?
    ");
    $check->bind_param("ii", $event_id, $user_id);
    $check->execute();
    $result = $check->get_result();

    // If no matching event found, deny access
    if ($result->num_rows === 0) {
        popup_error("Unauthorized action.");
    }

    // ===== FILE CLEANUP =====
    // Retrieve all file paths associated with this event's requirements
    $files = $conn->prepare("
        SELECT file_path
        FROM requirements
        WHERE event_id = ?
        AND file_path IS NOT NULL
        AND file_path != ''
    ");

    $files->bind_param("i", $event_id);
    $files->execute();
    $res = $files->get_result();

    // Delete each physical file from the server to free up storage
    while ($row = $res->fetch_assoc()) {
        $file = "../" . $row['file_path'];

        // Only delete if file exists on disk
        if (is_file($file)) {
            unlink($file);
        }
    }

    // ===== DATABASE DELETION =====
    // Use transaction to ensure atomicity of deletion operations
    try {
        // Start transaction to group related operations
        $conn->begin_transaction();

        // Delete the event record (requirements will be auto-deleted via FK CASCADE)
        $deleteEvent = $conn->prepare("
            DELETE FROM events
            WHERE event_id = ?
            AND user_id = ?
            AND archived_at IS NOT NULL
        ");

        $deleteEvent->bind_param("ii", $event_id, $user_id);
        $deleteEvent->execute();

        // Commit transaction if all operations succeeded
        $conn->commit();

    } catch (Exception $e) {
        // Rollback transaction on any error to maintain data integrity
        $conn->rollback();
        popup_error("Deletion failed.");
    }

    // ===== REDIRECT =====
    // Send user back to home page after successful deletion
    header("Location: home.php");
    exit();
}
?>