<?php
session_start();
require_once "../app/database.php";

// ===== SECURITY HEADERS =====
require_once "../app/security_headers.php";
send_security_headers();

// ===== SELF-REFERENCING URL =====
$self = htmlspecialchars($_SERVER["PHP_SELF"], ENT_QUOTES, "UTF-8");

// ===== VARIABLE INITIALIZATION =====
$error = "";
$success = "";

// ===== GET PARAMETERS =====
$token = trim($_GET["token"] ?? "");
$email = trim($_GET["email"] ?? "");

// ===== VALIDATE GET PARAMETERS =====
if ($token === "" || $email === "") {
    $error = "Invalid reset link.";
}

// ===== POST REQUEST HANDLING =====
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $token = trim($_POST["token"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $new = trim($_POST["new_password"] ?? "");
    $confirm = trim($_POST["confirm_password"] ?? "");

    // ===== VALIDATION: REQUIRED PARAMETERS =====
    if ($token === "" || $email === "") {
        $error = "Invalid reset link.";

        // ===== VALIDATION: REQUIRED FIELDS =====
    } elseif ($new === "" || $confirm === "") {
        $error = "Please fill in all fields.";

        // ===== VALIDATION: PASSWORD MATCH =====
    } elseif ($new !== $confirm) {
        $error = "Passwords do not match.";

        // ===== VALIDATION: PASSWORD STRENGTH =====
    } elseif (
        strlen($new) < 8 ||
        !preg_match('/[A-Z]/', $new) ||
        !preg_match('/[a-z]/', $new) ||
        !preg_match('/[0-9]/', $new)
    ) {
        $error = "Password must be at least 8 characters including uppercase, lowercase, and numeric.";

    } else {
        // ===== VERIFY USER EXISTS =====
        $fetchUserByEmailSql = "
            SELECT user_id
            FROM users
            WHERE user_email = ?
            LIMIT 1
        ";

        $user = fetchOne(
            $conn,
            $fetchUserByEmailSql,
            "s",
            [$email]
        );

        if (!$user) {
            $error = "Invalid reset link.";
        } else {
            $user_id = (int) $user["user_id"];

            // ===== VERIFY RESET TOKEN =====
            $fetchLatestResetTokenSql = "
                SELECT token_hash, expires_at
                FROM password_resets
                WHERE user_id = ?
                ORDER BY id DESC
                LIMIT 1
            ";

            $reset = fetchOne(
                $conn,
                $fetchLatestResetTokenSql,
                "i",
                [$user_id]
            );

            if (!$reset) {
                $error = "Reset request not found or already used.";
            } elseif (strtotime($reset["expires_at"]) < time()) {
                $error = "Reset link has expired. Please request again.";
            } elseif (!password_verify($token, $reset["token_hash"])) {
                $error = "Invalid reset link.";
            } else {
                // ===== UPDATE PASSWORD =====
                $hash = password_hash($new, PASSWORD_DEFAULT);

                $updateUserPasswordSql = "
                    UPDATE users
                    SET user_password = ?
                    WHERE user_id = ?
                ";

                execQuery(
                    $conn,
                    $updateUserPasswordSql,
                    "si",
                    [$hash, $user_id]
                );

                // ===== CLEANUP RESET TOKENS =====
                $deleteResetTokensSql = "
                    DELETE FROM password_resets
                    WHERE user_id = ?
                ";

                execQuery(
                    $conn,
                    $deleteResetTokensSql,
                    "i",
                    [$user_id]
                );

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