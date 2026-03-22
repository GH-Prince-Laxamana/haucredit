<?php
session_start();
require_once __DIR__ . '/../../app/database.php';

requireAdmin();

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    popup_error("Invalid request.");
}

$current_admin_id = (int) $_SESSION["user_id"];
$action = trim($_POST["action"] ?? "");

$allowed_actions = ["add_user", "make_admin", "make_user", "delete_user"];
if (!in_array($action, $allowed_actions, true)) {
    popup_error("Invalid action.");
}

try {
    $conn->begin_transaction();

    /* ================= ADD USER ================= */
    if ($action === "add_user") {
        $user_name = trim($_POST["user_name"] ?? "");
        $user_email = trim($_POST["user_email"] ?? "");
        $stud_num = trim($_POST["stud_num"] ?? "");
        $org_body = trim($_POST["org_body"] ?? "");
        $role = trim($_POST["role"] ?? "user");
        $user_password = trim($_POST["user_password"] ?? "");
        $confirm_password = trim($_POST["confirm_password"] ?? "");

        if (
            $user_name === "" ||
            $user_email === "" ||
            $stud_num === "" ||
            $org_body === "" ||
            $role === "" ||
            $user_password === "" ||
            $confirm_password === ""
        ) {
            throw new Exception("Please fill in all fields.");
        }

        if (!preg_match('/^[A-Za-z0-9._-]{4,50}$/', $user_name)) {
            throw new Exception("Username must be 4-50 characters and may contain letters, numbers, dot, underscore, and hyphen only.");
        }

        if (!filter_var($user_email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email format.");
        }

        if (!preg_match('/@student\.hau\.edu\.ph$/i', $user_email)) {
            throw new Exception("Email must be a valid HAU student email.");
        }

        if (!preg_match('/^[0-9]{8,20}$/', $stud_num)) {
            throw new Exception("Student number must contain 8 to 20 digits only.");
        }

        if (!in_array($role, ['admin', 'user'], true)) {
            throw new Exception("Invalid role selected.");
        }

        $orgExists = fetchOne(
            $conn,
            "
        SELECT org_name
        FROM config_org_options
        WHERE org_name = ?
          AND is_active = 1
        LIMIT 1
        ",
            "s",
            [$org_body]
        );

        if (!$orgExists) {
            throw new Exception("Please select a valid organizing body.");
        }

        if ($user_password !== $confirm_password) {
            throw new Exception("Passwords do not match.");
        }

        if (
            strlen($user_password) < 8 ||
            !preg_match('/[A-Z]/', $user_password) ||
            !preg_match('/[a-z]/', $user_password) ||
            !preg_match('/[0-9]/', $user_password)
        ) {
            throw new Exception("Password must be at least 8 characters including uppercase, lowercase, and numeric characters.");
        }

        $hash = password_hash($user_password, PASSWORD_DEFAULT);

        execQuery(
            $conn,
            "
        INSERT INTO users
            (user_name, user_password, user_email, stud_num, org_body, role, user_reg_date)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
        ",
            "ssssss",
            [$user_name, $hash, $user_email, $stud_num, $org_body, $role]
        );

        $_SESSION["success"] = "User account created successfully.";
    }

    /* ================= EXISTING USER ACTIONS ================= */
    if (in_array($action, ["make_admin", "make_user", "delete_user"], true)) {
        $user_id = isset($_POST["user_id"]) ? (int) $_POST["user_id"] : 0;

        if ($user_id <= 0) {
            throw new Exception("Invalid user.");
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
            throw new Exception("User not found.");
        }

        if ($user_id === $current_admin_id) {
            throw new Exception("You cannot modify your own admin account from this page.");
        }

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

            $_SESSION["success"] = "User promoted to admin.";
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

            $_SESSION["success"] = "Admin set back to standard user.";
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

            $_SESSION["success"] = "User deleted successfully.";
        }
    }

    $conn->commit();

} catch (Exception $e) {
    $conn->rollback();
    $_SESSION["error"] = $e->getMessage();
}

header("Location: admin_users.php");
exit();
?>