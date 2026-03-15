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

    /* ---------- VERIFY EVENT BELONGS TO USER ---------- */

    $check = $conn->prepare("
        SELECT event_id
        FROM events
        WHERE event_id = ? AND user_id = ?
    ");
    $check->bind_param("ii", $event_id, $user_id);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows === 0) {
        popup_error("Unauthorized action.");
    }

    /* ---------- GET FILE PATHS FROM REQUIREMENTS ---------- */

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

    while ($row = $res->fetch_assoc()) {

        $file = "../" . $row['file_path'];

        if (is_file($file)) {
            unlink($file);
        }
    }

    try {

        /* ---------- START TRANSACTION ---------- */
        $conn->begin_transaction();

        /* ---------- DELETE EVENT (REQs auto delete via FK CASCADE) ---------- */

        $deleteEvent = $conn->prepare("
            DELETE FROM events
            WHERE event_id = ?
            AND user_id = ?
            AND archived_at IS NOT NULL
        ");

        $deleteEvent->bind_param("ii", $event_id, $user_id);
        $deleteEvent->execute();

        /* ---------- COMMIT ---------- */

        $conn->commit();

    } catch (Exception $e) {

        $conn->rollback();
        popup_error("Deletion failed.");

    }

    header("Location: home.php");
    exit();
}
?>