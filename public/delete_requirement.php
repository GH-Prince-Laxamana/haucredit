<?php
session_start();
require_once "../app/database.php";

// ===== AUTHENTICATION CHECK =====
// Ensure user is logged in before allowing requirement deletion
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// ===== INPUT VALIDATION =====
// Extract requirement ID from POST data
$req_id = $_POST['req_id'] ?? null;

// Validate that req_id is provided
if (!$req_id) {
    popup_error("Invalid request.");
}

// ===== RETRIEVE FILE PATH =====
// Query database to get the file path associated with this requirement
$stmt = $conn->prepare("SELECT file_path FROM requirements WHERE req_id = ?");
$stmt->bind_param("i", $req_id);
$stmt->execute();
$res = $stmt->get_result();
$doc = $res->fetch_assoc();

// ===== FILE CLEANUP =====
// If a file path exists, delete the physical file from the server
if ($doc && !empty($doc['file_path'])) {

    // Construct full file path relative to current directory
    $file = __DIR__ . "/../" . $doc['file_path'];

    // Only delete if the file actually exists on disk
    if (is_file($file)) {
        unlink($file);
    }

    // ===== RESET REQUIREMENT STATUS =====
    // Update the requirement record to remove file reference and reset status
    $stmt = $conn->prepare("
        UPDATE requirements
        SET file_path = NULL,
            doc_status = 'pending'
        WHERE req_id = ?
    ");
    $stmt->bind_param("i", $req_id);
    $stmt->execute();
}

// ===== REDIRECT =====
// Return user to the previous page after processing
header("Location: " . $_SERVER['HTTP_REFERER']);
exit();