<?php
session_start();
require_once "../app/database.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit();
}

if (($_SESSION["role"] ?? "") !== "admin") {
    popup_error("Access denied.");
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    popup_error("Invalid request.");
}

$current_admin_id = (int) $_SESSION["user_id"];
$user_id = isset($_POST["user_id"]) ? (int) $_POST["user_id"] : 0;
$action = trim($_POST["action"] ?? "");

if ($user_id <= 0) {
    popup_error("Invalid user.");
}

$allowed_actions = ["make_admin", "make_user", "delete_user"];
if (!in_array($action, $allowed_actions, true)) {
    popup_error("Invalid action.");
}

$user = fetchOne(
    $conn,
    "
    SELECT user_id, user_name, role
    FROM users
    WHERE user_id = ?
    LIMIT 1
    ",
    "i",
    [$user_id]
);

if (!$user) {
    popup_error("User not found.");
}

if ($user_id === $current_admin_id) {
    popup_error("You cannot modify your own admin account from this page.");
}

try {
    $conn->begin_transaction();

    if ($action === "make_admin") {
        execQuery(
            $conn,
            "
            UPDATE users
            SET role = 'admin'
            WHERE user_id = ?
            ",
            "i",
            [$user_id]
        );
    }

    if ($action === "make_user") {
        execQuery(
            $conn,
            "
            UPDATE users
            SET role = 'user'
            WHERE user_id = ?
            ",
            "i",
            [$user_id]
        );
    }

    if ($action === "delete_user") {
        execQuery(
            $conn,
            "
            DELETE FROM users
            WHERE user_id = ?
            ",
            "i",
            [$user_id]
        );
    }

    $conn->commit();

} catch (Exception $e) {
    $conn->rollback();
    popup_error("Failed to update user: " . $e->getMessage());
}

header("Location: admin_users.php");
exit();
?>