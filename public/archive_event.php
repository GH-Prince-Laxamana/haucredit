<?php
session_start();
require_once "../app/database.php";

if (!isset($_SESSION["user_id"])) {
  header("Location: index.php");
  exit();
}

if (isset($_POST['action'])) {

  $event_id = (int) $_POST['event_id'];
  $user_id  = $_SESSION['user_id'];

  if ($_POST['action'] === 'archive') {

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

  if ($_POST['action'] === 'restore') {

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