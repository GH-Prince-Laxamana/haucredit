<?php
session_start();
require_once "../app/database.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Invalid request.");
}

$req_id = $_POST['req_id'] ?? null;

if (!$req_id || !isset($_FILES['document'])) {
    die("Missing data.");
}

$file = $_FILES['document'];

$allowed = ['pdf','doc','docx'];
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

if (!in_array($ext,$allowed)) {
    die("Invalid file type.");
}

$upload_dir = "../uploads/requirements/";

if (!is_dir($upload_dir)) {
    mkdir($upload_dir,0777,true);
}

$filename = uniqid("req_") . "." . $ext;
$path = $upload_dir . $filename;

if (!move_uploaded_file($file['tmp_name'],$path)) {
    die("Upload failed.");
}

$stmt = $conn->prepare("
UPDATE requirements
SET file_path = ?, doc_status = 'uploaded', uploaded_at = NOW()
WHERE req_id = ?
");

$stmt->bind_param("si",$path,$req_id);
$stmt->execute();

header("Location: " . $_SERVER['HTTP_REFERER']);
exit();