<?php
session_start();
require_once "../app/database.php";

// ===== SECURITY HEADERS =====
// Include security headers to protect against common web vulnerabilities
require_once "../app/security_headers.php";
send_security_headers();

// ===== SELF-REFERENCING URL =====
// Generate a safe, self-referencing URL for the form action to prevent XSS
$self = htmlspecialchars($_SERVER["PHP_SELF"], ENT_QUOTES, "UTF-8");

// ===== VARIABLE INITIALIZATION =====
// Initialize error and success message variables
$error = "";
$success = "";

// ===== GET PARAMETERS =====
// Retrieve and sanitize token and email from URL query parameters
$token = trim($_GET["token"] ?? "");
$email = trim($_GET["email"] ?? "");

// ===== VALIDATE GET PARAMETERS =====
// Check if required parameters are present
if ($token === "" || $email === "") {
    $error = "Invalid reset link.";
}

// ===== POST REQUEST HANDLING =====
// Process form submission for password reset
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Extract and sanitize form input values
    $token = trim($_POST["token"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $new = trim($_POST["new_password"] ?? "");
    $confirm = trim($_POST["confirm_password"] ?? "");

    // ===== VALIDATION: REQUIRED PARAMETERS =====
    // Ensure token and email are provided
    if ($token === "" || $email === "") {
        $error = "Invalid reset link.";

    // ===== VALIDATION: REQUIRED FIELDS =====
    // Check if password fields are filled
    } elseif ($new === "" || $confirm === "") {
        $error = "Please fill in all fields.";

    // ===== VALIDATION: PASSWORD MATCH =====
    // Ensure new password and confirmation match
    } elseif ($new !== $confirm) {
        $error = "Passwords do not match.";

    // ===== VALIDATION: PASSWORD STRENGTH =====
    // Enforce password complexity requirements
    } elseif (
        strlen($new) < 8 ||
        !preg_match('/[A-Z]/', $new) ||
        !preg_match('/[a-z]/', $new) ||
        !preg_match('/[0-9]/', $new)
    ) {
        $error = "Password must be at least 8 characters including uppercase, lowercase, and numeric.";

    } else {
        // ===== VERIFY USER EXISTS =====
        // Check if user with provided email exists
        $u = mysqli_prepare($conn, "SELECT user_id FROM users WHERE user_email = ? LIMIT 1");
        mysqli_stmt_bind_param($u, "s", $email);
        mysqli_stmt_execute($u);
        $ures = mysqli_stmt_get_result($u);
        $user = mysqli_fetch_assoc($ures);
        mysqli_stmt_close($u);

        if (!$user) {
            $error = "Invalid reset link.";
        } else {
            $user_id = (int) $user["user_id"];

            // ===== VERIFY RESET TOKEN =====
            // Fetch the most recent reset token for the user
            $r = mysqli_prepare(
                $conn,
                "SELECT token_hash, expires_at FROM password_resets
                 WHERE user_id = ?
                 ORDER BY id DESC
                 LIMIT 1"
            );
            mysqli_stmt_bind_param($r, "i", $user_id);
            mysqli_stmt_execute($r);
            $rres = mysqli_stmt_get_result($r);
            $reset = mysqli_fetch_assoc($rres);
            mysqli_stmt_close($r);

            if (!$reset) {
                $error = "Reset request not found or already used.";
            } elseif (strtotime($reset["expires_at"]) < time()) {
                $error = "Reset link has expired. Please request again.";
            } elseif (!password_verify($token, $reset["token_hash"])) {
                $error = "Invalid reset link.";
            } else {
                // ===== UPDATE PASSWORD =====
                // Hash the new password and update user record
                $hash = password_hash($new, PASSWORD_DEFAULT);

                $upd = mysqli_prepare($conn, "UPDATE users SET user_password = ? WHERE user_id = ?");
                mysqli_stmt_bind_param($upd, "si", $hash, $user_id);
                mysqli_stmt_execute($upd);
                mysqli_stmt_close($upd);

                // ===== CLEANUP RESET TOKENS =====
                // Delete all reset tokens for the user after successful reset
                $del = mysqli_prepare($conn, "DELETE FROM password_resets WHERE user_id = ?");
                mysqli_stmt_bind_param($del, "i", $user_id);
                mysqli_stmt_execute($del);
                mysqli_stmt_close($del);

                // Clear error and set success message
                $error = "";
                $success = "Password updated! You can now log in.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Reset Password</title>
    <link rel="stylesheet" href="assets/styles/layout.css" />
    <link rel="stylesheet" href="assets/styles/auth_styles.css" />
</head>

<body>
    <!-- ===== AUTHENTICATION CONTAINER ===== -->
    <!-- Main container for the password reset page layout -->
    <div class="auth-container">
        <!-- ===== LEFT PANEL ===== -->
        <!-- Branding and feature highlights -->
        <div class="auth-left-panel">
            <div class="brand-title">
                <h1 class="brand-name">HAU<span class="brand-accent">CREDIT</span></h1>
                <p class="brand-tagline">Compliance & Records Engine for Documentation and Institutional Tracking.</p>
            </div>

            <ul>
                <li>Centralized Event Monitoring</li>
                <li>Automated OSA Checklists</li>
                <li>Secure Document Repository</li>
            </ul>
        </div>

        <!-- ===== RIGHT PANEL ===== -->
        <!-- Password reset form and messages -->
        <div class="auth-right-panel">
            <div class="auth-card">
                <h2>Reset Password</h2>
                <div class="subtitle">Create a new password for your account.</div>

                <!-- ===== PASSWORD RESET FORM ===== -->
                <!-- Form for entering new password with hidden token and email -->
                <form action="<?= $self ?>" method="post">
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                    <input type="hidden" name="email" value="<?= htmlspecialchars($email) ?>">

                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <input type="password" id="new_password" name="new_password" placeholder="Enter new password"
                            required>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirm Password</label>
                        <input type="password" id="confirm_password" name="confirm_password"
                            placeholder="Confirm new password" required>
                    </div>

                    <button type="submit">Update Password</button>
                </form>

                <!-- ===== ERROR MESSAGE ===== -->
                <!-- Display error message if reset fails -->
                <?php if ($error !== ""): ?>
                    <div class="notice error"><?= htmlspecialchars($error) ?></div>
                    <div class="link">Back to <a href="index.php">Log In</a></div>
                <?php endif; ?>

                <!-- ===== SUCCESS MESSAGE ===== -->
                <!-- Display success message if reset succeeds -->
                <?php if ($success !== ""): ?>
                    <div class="notice success"><?= htmlspecialchars($success) ?></div>
                    <div class="link">Go to <a href="index.php">Log In</a></div>
                <?php endif; ?>

                <!-- ===== DEFAULT LINK ===== -->
                <!-- Default link to login page when no messages are shown -->
                <?php if ($error === "" && $success === ""): ?>
                    <div class="link">Back to <a href="index.php">Log In</a></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ===== FOOTER ===== -->
    <!-- Include the site footer -->
    <?php include 'assets/includes/footer.php' ?>
</body>

</html>