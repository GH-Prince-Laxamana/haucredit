<?php
session_start();
require_once("../app/database.php");

require_once("../app/security_headers.php");
send_security_headers();

$self = htmlspecialchars($_SERVER["PHP_SELF"], ENT_QUOTES, "UTF-8");

$error = "";
$success = "";

$token = trim($_GET["token"] ?? "");
$email = trim($_GET["email"] ?? "");

if ($token === "" || $email === "") {
    $error = "Invalid reset link.";
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $token = trim($_POST["token"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $new = trim($_POST["new_password"] ?? "");
    $confirm = trim($_POST["confirm_password"] ?? "");

    if ($token === "" || $email === "") {
        $error = "Invalid reset link.";
    } elseif ($new === "" || $confirm === "") {
        $error = "Please fill in all fields.";
    } elseif ($new !== $confirm) {
        $error = "Passwords do not match.";
    } elseif (
        strlen($new) < 8 ||
        !preg_match('/[A-Z]/', $new) ||
        !preg_match('/[a-z]/', $new) ||
        !preg_match('/[0-9]/', $new)
    ) {
        $error = "Password must be at least 8 characters including uppercase, lowercase, and numeric.";
    } else {
        $u = mysqli_prepare($conn, "SELECT user_id FROM users WHERE user_email = ? LIMIT 1");
        mysqli_stmt_bind_param($u, "s", $email);
        mysqli_stmt_execute($u);
        $ures = mysqli_stmt_get_result($u);
        $user = mysqli_fetch_assoc($ures);
        mysqli_stmt_close($u);

        if (!$user) {
            $error = "Invalid reset link.";
        } else {
            $user_id = (int)$user["user_id"];

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
                $hash = password_hash($new, PASSWORD_DEFAULT);

                $upd = mysqli_prepare($conn, "UPDATE users SET user_password = ? WHERE user_id = ?");
                mysqli_stmt_bind_param($upd, "si", $hash, $user_id);
                mysqli_stmt_execute($upd);
                mysqli_stmt_close($upd);

                $del = mysqli_prepare($conn, "DELETE FROM password_resets WHERE user_id = ?");
                mysqli_stmt_bind_param($del, "i", $user_id);
                mysqli_stmt_execute($del);
                mysqli_stmt_close($del);

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
    <link rel="stylesheet" href="../CSS/styles.css">
</head>
<body>

<div class="container">
    <div class="left-panel">
        <h1>HAUCREDIT</h1>
        <p><b>Compliance & Records Engine</b> for Documentation and Institutional Tracking.</p>
        <ul>
            <li>Centralized Event Monitoring</li>
            <li>Automated OSA Checklists</li>
            <li>Secure Document Repository</li>
        </ul>
    </div>

    <div class="right-panel">
        <div class="card">
            <h2>Reset Password</h2>
            <div class="subtitle">Create a new password for your account.</div>

            <form action="<?= $self ?>" method="post">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                <input type="hidden" name="email" value="<?= htmlspecialchars($email) ?>">

                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password" placeholder="Enter new password" required>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm new password" required>
                </div>

                <button type="submit">Update Password</button>
            </form>

            <?php if ($error !== ""): ?>
                <div class="notice error"><?= htmlspecialchars($error) ?></div>
                <div class="link">Back to <a href="index.php">Log In</a></div>
            <?php endif; ?>

            <?php if ($success !== ""): ?>
                <div class="notice success"><?= htmlspecialchars($success) ?></div>
                <div class="link">Go to <a href="index.php">Log In</a></div>
            <?php endif; ?>

            <?php if ($error === "" && $success === ""): ?>
                <div class="link">Back to <a href="index.php">Log In</a></div>
            <?php endif; ?>
        </div>
    </div>
</div>

</body>
</html>
