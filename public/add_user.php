<?php
/**
 * add_user.php
 * Legacy redirect endpoint — all adds now go through users_action.php.
 * Kept for backward compatibility; redirects immediately.
 */

session_start();
require_once "../app/database.php";

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit('Forbidden');
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name   = trim($_POST['user_name']     ?? '');
    $email  = trim($_POST['user_email']    ?? '');
    $stud   = trim($_POST['stud_num']      ?? '');
    $org    = trim($_POST['org_body']      ?? '');
    $role   = trim($_POST['role']          ?? 'User');
    $status = trim($_POST['status']        ?? 'Pending');
    $pass   = trim($_POST['user_password'] ?? '');

    if ($name && $email && $stud && $org && $pass) {
        $hash = password_hash($pass, PASSWORD_DEFAULT);

        $allowed_roles    = ['User', 'Moderator', 'Admin'];
        $allowed_statuses = ['Pending', 'Approved'];
        if (!in_array($role, $allowed_roles, true))      $role   = 'User';
        if (!in_array($status, $allowed_statuses, true)) $status = 'Pending';

        $stmt = $conn->prepare("
            INSERT INTO users
                (user_name, user_password, user_email, stud_num, org_body, role, status, user_reg_date)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->bind_param('sssssss', $name, $hash, $email, $stud, $org, $role, $status);

        try {
            $stmt->execute();
        } catch (mysqli_sql_exception $e) {
            // silently continue; duplicate entry
        }
    }
}

header("Location: users.php");
exit();