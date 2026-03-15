<?php
session_start();
require_once "../app/database.php";

if (!isset($_SESSION["user_id"])) {
  header("Location: index.php");
  exit();
}

if (isset($_POST['event_id'])) {

  $event_id = (int) $_POST['event_id'];
  $user_id = $_SESSION['user_id'];

  /* ---------- ARCHIVE EVENT ---------- */
  if (isset($_POST['archive_event'])) {

    $stmt = $conn->prepare("
            UPDATE events
            SET archived_at = NOW()
            WHERE event_id = ? AND user_id = ?
        ");

    $stmt->bind_param("ii", $event_id, $user_id);
    $stmt->execute();

    header("Location: archived_events.php");
    exit();
  }

  /* ---------- RESTORE EVENT ---------- */
  if (isset($_POST['restore_event'])) {

    $stmt = $conn->prepare("
            UPDATE events
            SET archived_at = NULL
            WHERE event_id = ? AND user_id = ?
        ");

    $stmt->bind_param("ii", $event_id, $user_id);
    $stmt->execute();

    header("Location: my_events.php");
    exit();
  }
}
?>