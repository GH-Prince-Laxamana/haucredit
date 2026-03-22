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


// ==================== TRANSACTION: USER MANAGEMENT ====================
// Use transactions to ensure data consistency: all updates succeed or all fail
try {
    $conn->begin_transaction();

    // =============== ACTION: ADD USER ===============
    // Create new user account with validation and password hashing
    if ($action === "add_user") {
        // ============= EXTRACT NEW USER DATA =============
        // Extract and trim form input fields
        $user_name = trim($_POST["user_name"] ?? "");
        $user_email = trim($_POST["user_email"] ?? "");
        $stud_num = trim($_POST["stud_num"] ?? "");
        $org_body = trim($_POST["org_body"] ?? "");
        $role = trim($_POST["role"] ?? "user");
        $user_password = trim($_POST["user_password"] ?? "");
        $confirm_password = trim($_POST["confirm_password"] ?? "");

        // ============= VALIDATION: REQUIRED FIELDS =============
        // All fields must be provided for user creation
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

        // ============= VALIDATION: USERNAME =============
        // Username format: 4-50 chars, alphanumeric plus dot, underscore, hyphen only
        // This prevents special characters that could cause issues
        if (!preg_match('/^[A-Za-z0-9._-]{4,50}$/', $user_name)) {
            throw new Exception("Username must be 4-50 characters and may contain letters, numbers, dot, underscore, and hyphen only.");
        }

        // ============= VALIDATION: EMAIL FORMAT =============
        // Basic email format validation using PHP filter
        if (!filter_var($user_email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email format.");
        }

        // ============= VALIDATION: HAU STUDENT EMAIL =============
        // Enforce HAU institutional email domain requirement
        // All users must register with official HAU student email
        if (!preg_match('/@student\.hau\.edu\.ph$/i', $user_email)) {
            throw new Exception("Email must be a valid HAU student email.");
        }

        // ============= VALIDATION: STUDENT NUMBER =============
        // Student number: 8-20 digits only (institution standard format)
        if (!preg_match('/^[0-9]{8,20}$/', $stud_num)) {
            throw new Exception("Student number must contain 8 to 20 digits only.");
        }

        // ============= VALIDATION: ROLE =============
        // Role must be either 'admin' or 'user'
        // Prevents invalid role assignments
        if (!in_array($role, ['admin', 'user'], true)) {
            throw new Exception("Invalid role selected.");
        }

        // ============= VALIDATION: ORGANIZATION EXISTS =============
        // Verify that selected organization is active and valid
        // Prevents assigning users to non-existent org bodies
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

        // ============= VALIDATION: PASSWORD MATCH =============
        // Ensure password and confirmation match
        if ($user_password !== $confirm_password) {
            throw new Exception("Passwords do not match.");
        }

        // ============= VALIDATION: PASSWORD STRENGTH =============
        // Password must be: 8+ chars, uppercase, lowercase, numeric
        // Enforces strong security policy for user accounts
        if (
            strlen($user_password) < 8 ||
            !preg_match('/[A-Z]/', $user_password) ||
            !preg_match('/[a-z]/', $user_password) ||
            !preg_match('/[0-9]/', $user_password)
        ) {
            throw new Exception("Password must be at least 8 characters including uppercase, lowercase, and numeric characters.");
        }

        // ============= PASSWORD HASHING =============
        // Use bcrypt via PASSWORD_DEFAULT for secure password storage
        // Never store plaintext passwords in database
        $hash = password_hash($user_password, PASSWORD_DEFAULT);

        // ============= INSERT NEW USER =============
        // Create new user record with hashed password and registration timestamp
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

        // ============= SUCCESS MESSAGE =============
        // Set session success message for display on redirect
        $_SESSION["success"] = "User account created successfully.";
    }

    // =============== ACTION: EXISTING USER OPERATIONS ===============
    // Operations on existing users: promote to admin, demote to user, or delete
    if (in_array($action, ["make_admin", "make_user", "delete_user"], true)) {
        // ============= EXTRACT USER ID =============
        // Get target user ID from POST
        $user_id = isset($_POST["user_id"]) ? (int) $_POST["user_id"] : 0;

        // ============= VALIDATION: USER ID =============
        // User ID must be positive
        if ($user_id <= 0) {
            throw new Exception("Invalid user.");
        }

        // ============= FETCH USER =============
        // Verify user exists before attempting modifications
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

        // ============= VALIDATION: USER EXISTS =============
        // Ensure target user was found in database
        if (!$user) {
            throw new Exception("User not found.");
        }

        // ============= VALIDATION: SELF-MODIFICATION PREVENTION =============
        // Admin cannot modify their own account from this page
        // Prevents accidental self-demotion or deletion
        if ($user_id === $current_admin_id) {
            throw new Exception("You cannot modify your own admin account from this page.");
        }

        // ============= ACTION: MAKE ADMIN ===============
        // Promote user to admin role
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

        // ============= ACTION: MAKE USER ===============
        // Demote admin back to standard user role
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

        // ============= ACTION: DELETE USER ===============
        // Remove user account from system
        // Note: This permanently deletes the user record
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