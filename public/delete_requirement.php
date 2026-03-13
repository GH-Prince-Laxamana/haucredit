<?php
session_start();
require_once "../app/database.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$req_id = $_POST['req_id'] ?? null;

if (!$req_id) {
    die("Invalid request.");
}

// get file path
$stmt = $conn->prepare("SELECT file_path FROM requirements WHERE req_id = ?");
$stmt->bind_param("i", $req_id);
$stmt->execute();
$res = $stmt->get_result();
$doc = $res->fetch_assoc();

if ($doc && !empty($doc['file_path'])) {

    if (file_exists($doc['file_path'])) {
        unlink($doc['file_path']);
    }

    // reset requirement
    $stmt = $conn->prepare("
        UPDATE requirements
        SET file_path = NULL,
            doc_status = 'pending'
        WHERE req_id = ?
    ");
    $stmt->bind_param("i", $req_id);
    $stmt->execute();
}

header("Location: " . $_SERVER['HTTP_REFERER']);
exit();