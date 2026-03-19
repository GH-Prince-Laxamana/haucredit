<?php
/**
 * users_action.php
 * Handles all user mutations: add, edit, delete, approve, archive, restore.
 * Returns JSON so JS can handle the response without full page reload.
 */

session_start();
require_once "../app/database.php";

header('Content-Type: application/json');

// ===== AUTHENTICATION CHECK =====
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthenticated']);
    exit();
}

$action = trim($_POST['action'] ?? '');

// ===== HELPER: json response =====
function respond(bool $ok, string $msg = '', array $extra = []): void
{
    echo json_encode(array_merge(['success' => $ok, 'message' => $msg], $extra));
    exit();
}

// ===== HELPER: refresh stats =====
function get_stats($conn): array
{
    return $conn->query("
        SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN (last_login IS NULL OR last_login < NOW() - INTERVAL 30 DAY) THEN 1 ELSE 0 END) AS inactive,
            SUM(CASE WHEN role = 'Admin'    THEN 1 ELSE 0 END) AS admins,
            SUM(CASE WHEN status = 'Pending'  THEN 1 ELSE 0 END) AS pending_count,
            SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) AS approved_count,
            SUM(CASE WHEN status = 'Archived' THEN 1 ELSE 0 END) AS archived_count
        FROM users
    ")->fetch_assoc();
}

switch ($action) {

    // =========================================================
    // ADD USER
    // =========================================================
    case 'add':
        $name     = trim($_POST['user_name']      ?? '');
        $email    = trim($_POST['user_email']     ?? '');
        $stud     = trim($_POST['stud_num']       ?? '');
        $org      = trim($_POST['org_body']       ?? '');
        $role     = trim($_POST['role']           ?? 'User');
        $status   = trim($_POST['status']         ?? 'Pending');
        $password = trim($_POST['user_password']  ?? '');

        // Validate
        if (!$name || !$email || !$stud || !$org || !$password) {
            respond(false, 'All fields are required.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            respond(false, 'Invalid email address.');
        }
        if (strlen($password) < 8) {
            respond(false, 'Password must be at least 8 characters.');
        }

        $allowed_roles    = ['User', 'Moderator', 'Admin'];
        $allowed_statuses = ['Pending', 'Approved'];
        if (!in_array($role, $allowed_roles, true))    $role   = 'User';
        if (!in_array($status, $allowed_statuses, true)) $status = 'Pending';

        $hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("
            INSERT INTO users
                (user_name, user_password, user_email, stud_num, org_body, role, status, user_reg_date)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->bind_param('sssssss', $name, $hash, $email, $stud, $org, $role, $status);

        try {
            $stmt->execute();
        } catch (mysqli_sql_exception $e) {
            respond(false, 'Username, email, or student number already exists.');
        }

        respond(true, 'User created successfully.', ['stats' => get_stats($conn)]);
        break;

    // =========================================================
    // EDIT USER
    // =========================================================
    case 'edit':
        $user_id  = (int) ($_POST['user_id']    ?? 0);
        $name     = trim($_POST['user_name']    ?? '');
        $email    = trim($_POST['user_email']   ?? '');
        $stud     = trim($_POST['stud_num']     ?? '');
        $org      = trim($_POST['org_body']     ?? '');
        $role     = trim($_POST['role']         ?? 'User');
        $status   = trim($_POST['status']       ?? 'Pending');

        if (!$user_id || !$name || !$email) {
            respond(false, 'Required fields are missing.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            respond(false, 'Invalid email address.');
        }

        $allowed_roles    = ['User', 'Moderator', 'Admin'];
        $allowed_statuses = ['Pending', 'Approved', 'Archived'];
        if (!in_array($role, $allowed_roles, true))    $role   = 'User';
        if (!in_array($status, $allowed_statuses, true)) $status = 'Pending';

        $stmt = $conn->prepare("
            UPDATE users
            SET user_name = ?, user_email = ?, stud_num = ?, org_body = ?, role = ?, status = ?
            WHERE user_id = ?
        ");
        $stmt->bind_param('ssssssi', $name, $email, $stud, $org, $role, $status, $user_id);

        try {
            $stmt->execute();
        } catch (mysqli_sql_exception $e) {
            respond(false, 'Username or email already in use by another account.');
        }

        respond(true, 'User updated successfully.', ['stats' => get_stats($conn)]);
        break;

    // =========================================================
    // DELETE USER
    // =========================================================
    case 'delete':
        $user_id = (int) ($_POST['user_id'] ?? 0);

        if (!$user_id) {
            respond(false, 'Invalid user ID.');
        }
        // Prevent deleting self
        if ($user_id === (int) $_SESSION['user_id']) {
            respond(false, 'You cannot delete your own account.');
        }

        $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();

        if ($stmt->affected_rows === 0) {
            respond(false, 'User not found.');
        }

        respond(true, 'User deleted.', ['stats' => get_stats($conn)]);
        break;

    // =========================================================
    // APPROVE USER
    // =========================================================
    case 'approve':
        $user_id = (int) ($_POST['user_id'] ?? 0);
        if (!$user_id) respond(false, 'Invalid user ID.');

        $stmt = $conn->prepare("UPDATE users SET status = 'Approved' WHERE user_id = ?");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();

        respond(true, 'User approved.', ['stats' => get_stats($conn)]);
        break;

    // =========================================================
    // ARCHIVE USER
    // =========================================================
    case 'archive':
        $user_id = (int) ($_POST['user_id'] ?? 0);
        if (!$user_id) respond(false, 'Invalid user ID.');
        if ($user_id === (int) $_SESSION['user_id']) {
            respond(false, 'You cannot archive your own account.');
        }

        $stmt = $conn->prepare("UPDATE users SET status = 'Archived' WHERE user_id = ?");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();

        respond(true, 'User archived.', ['stats' => get_stats($conn)]);
        break;

    // =========================================================
    // RESTORE USER
    // =========================================================
    case 'restore':
        $user_id = (int) ($_POST['user_id'] ?? 0);
        if (!$user_id) respond(false, 'Invalid user ID.');

        $stmt = $conn->prepare("UPDATE users SET status = 'Pending' WHERE user_id = ?");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();

        respond(true, 'User restored to Pending.', ['stats' => get_stats($conn)]);
        break;

    default:
        respond(false, 'Unknown action.');
}