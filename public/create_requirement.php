<?php
session_start();
require_once "../app/database.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    popup_error("Invalid request.");
}

$req_id = $_POST['req_id'] ?? null;

if (!$req_id || !isset($_FILES['document'])) {
    popup_error("Missing data.");
}

$file = $_FILES['document'];

$allowed = ['pdf', 'doc', 'docx'];
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $file['tmp_name']);

$allowed_mime = [
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
];

if (!in_array($mime, $allowed_mime)) {
    popup_error("Invalid file type.");
}

if (!in_array($ext, $allowed)) {
    popup_error("Invalid file type.");
}

if ($file['size'] > 100 * 1024 * 1024) {
    popup_error("File too large. Max 100MB.");
}

$upload_dir = __DIR__ . "/../uploads/requirements/";

if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

$filename = uniqid("req_") . "." . $ext;

$relative_path = "uploads/requirements/" . $filename;
$full_path = $upload_dir . $filename;

if (!move_uploaded_file($file['tmp_name'], $full_path)) {
    popup_error("Upload failed.");
}

$old = $conn->prepare("SELECT file_path FROM requirements WHERE req_id = ?");
$old->bind_param("i", $req_id);
$old->execute();
$res = $old->get_result()->fetch_assoc();

if ($res && !empty($res['file_path'])) {

    $old_file = __DIR__ . "/../" . $res['file_path'];

    if (file_exists($old_file)) {
        unlink($old_file);
    }
}

$stmt = $conn->prepare("
UPDATE requirements
SET file_path = ?, doc_status = 'uploaded', uploaded_at = NOW()
WHERE req_id = ?
");

$stmt->bind_param("si", $relative_path, $req_id);
$stmt->execute();

header("Location: " . $_SERVER['HTTP_REFERER']);
exit();