<?php
session_start();
require_once __DIR__ . '/../app/database.php';

require_once("../app/security_headers.php");
send_security_headers();

// Verify that the user is not already logged in; redirect them if they are
redirectIfLoggedIn();

// ===== PAGE VARIABLES =====
// Generate the self-referencing form action URL with proper HTML escaping
$self = htmlspecialchars($_SERVER["PHP_SELF"], ENT_QUOTES, "UTF-8");

// Initialize message variable to display feedback to the user
$msg = "";

// Initialize development link variable (used only for demonstration purposes)
$dev_link = "";

// ===== FORM SUBMISSION HANDLING =====
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Extract and sanitize the email input from the form submission
    $email = trim($_POST["email"] ?? "");

    // ===== EMAIL VALIDATION =====
    // Verify that the email field is not empty
    if ($email === "") {
        $msg = "Please enter your student email.";
    } else {
        $msg = "If that email exists, a reset link will be generated.";

        // ===== DATABASE LOOKUP: FIND USER BY EMAIL =====
        // Query the database to check if a user account exists with the provided email
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

        // ===== TOKEN GENERATION & STORAGE =====
        // Only proceed if a matching user account was found in the database
        if ($user) {
            // Extract and cast the user ID to ensure proper type handling
            $user_id = (int) $user["user_id"];

            // Generate a cryptographically secure random token for password reset
            $token = bin2hex(random_bytes(32));

            // Hash the token using bcrypt to store a secure version in the database
            $token_hash = password_hash($token, PASSWORD_DEFAULT);

            // Set token expiration to 30 minutes from the current time
            $expires_at = date("Y-m-d H:i:s", time() + 30 * 60);

            // ===== CLEANUP: REMOVE EXPIRED OR PREVIOUS RESET TOKENS =====
            // Delete any existing password reset tokens for this user to prevent token reuse
            $deleteOldResetTokensSql = "
                DELETE FROM password_resets
                WHERE user_id = ?
            ";

            execQuery(
                $conn,
                $deleteOldResetTokensSql,
                "i",
                [$user_id]
            );

            // ===== CREATE & STORE NEW RESET TOKEN =====
            // Insert the new password reset token record into the database
            $insertResetTokenSql = "
                INSERT INTO password_resets (user_id, token_hash, expires_at)
                VALUES (?, ?, ?)
            ";

            execQuery(
                $conn,
                $insertResetTokenSql,
                "iss",
                [$user_id, $token_hash, $expires_at]
            );

            // ===== DEVELOPMENT LINK GENERATION =====
            // Build the password reset link with the token and email (for development/demo use only)
            // In production, this link should be sent via email instead of displayed on-screen
            $dev_link = "reset_password.php?token=" . urlencode($token) . "&email=" . urlencode($email);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Forgot Password</title>
    <link rel="stylesheet" href="assets/styles/layout.css" />
    <link rel="stylesheet" href="assets/styles/auth_styles.css" />
</head>

<body>
    <div class="auth-container">
        <!-- ===== LEFT PANEL: BRANDING AND FEATURES ===== -->
        <div class="auth-left-panel">
            <div class="brand-title">
                <h1 class="brand-name">HAU<span class="brand-accent">CREDIT</span></h1>
                <p class="brand-tagline">Compliance & Records Engine for Documentation and Institutional Tracking.</p>
            </div>

            <!-- Feature list for user awareness -->
            <ul>
                <li>Centralized Event Monitoring</li>
                <li>Automated OSA Checklists</li>
                <li>Secure Document Repository</li>
            </ul>
        </div>

        <!-- ===== RIGHT PANEL: PASSWORD RESET FORM ===== -->
        <div class="auth-right-panel">
            <div class="auth-card">
                <h2>Forgot Password</h2>
                <div class="subtitle">Enter your HAU student email to reset your password.</div>

                <!-- ===== PASSWORD RESET FORM ===== -->
                <form action="<?= $self ?>" method="post" autocomplete="off">
                    <div class="form-group">
                        <label for="email">Student Email</label>
                        <input type="email" id="email" name="email" placeholder="name@student.hau.edu.ph" required>
                    </div>

                    <button type="submit">Generate Reset Link</button>
                </form>

                <!-- ===== USER FEEDBACK MESSAGE ===== -->
                <p class="notice-msg"><?= htmlspecialchars($msg, ENT_QUOTES, "UTF-8") ?></p>

                <!-- ===== DEVELOPMENT LINK DISPLAY (DEMO ONLY) ===== -->
                <!-- Show reset link for development testing when form has been submitted -->
                <!-- Note: In production, this link should be sent via email instead -->
                <?php if ($msg !== ""): ?>
                    <div class="notice success">
                        <!-- Display development link only if one was generated (user found in database) -->
                        <?php if ($dev_link !== ""): ?>
                            <b>DEV LINK (FOR DEMO PURPOSES ONLY): </b>
                            <!-- Link to password reset page with token and email parameters -->
                            <!-- Both URL parameters are properly encoded to handle special characters -->
                            <a class="dev-link" href="<?= htmlspecialchars($dev_link, ENT_QUOTES, "UTF-8") ?>">
                                <?= htmlspecialchars($dev_link, ENT_QUOTES, "UTF-8") ?>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- ===== NAVIGATION LINK ===== -->
                <div class="link">
                    Back to <a href="index.php">Log In</a>
                </div>
            </div>
        </div>
    </div>

    <?php include 'assets/includes/footer.php' ?>
</body>

</html>