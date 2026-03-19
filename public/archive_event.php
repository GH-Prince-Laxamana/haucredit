<?php
session_start();
require_once "../app/database.php";

if (!isset($_SESSION["user_id"])) {
  header("Location: index.php");
  exit();
}

if (isset($_POST['action'])) {
  $event_id = (int) $_POST['event_id'];
  $user_id = (int) $_SESSION['user_id'];
  $action = $_POST['action'];

  if ($action === 'archive' || $action === 'restore') {
    $archiveValue = ($action === 'archive') ? 'NOW()' : 'NULL';
    $redirect = ($action === 'archive') ? 'archived_events.php' : 'my_events.php';

    $sql = "
      UPDATE events
      SET archived_at = $archiveValue
      WHERE event_id = ? AND user_id = ?
    ";

    execQuery($conn, $sql, "ii", [$event_id, $user_id]);

    header("Location: $redirect");
    exit();
  }
}
?>