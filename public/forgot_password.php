<?php
session_start();
require_once("../app/database.php");

require_once("../app/security_headers.php");
send_security_headers();

$self = htmlspecialchars($_SERVER["PHP_SELF"], ENT_QUOTES, "UTF-8");

$msg = "";
$dev_link = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");

    if ($email === "") {
        $msg = "Please enter your student email.";
    } else {
        // Find user by email
        $stmt = mysqli_prepare($conn, "SELECT user_id FROM users WHERE user_email = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($res);
        mysqli_stmt_close($stmt);

        // Always show generic success for privacy
        $msg = "If that email exists, a reset link will be generated.";

        if ($user) {
            $user_id = (int)$user["user_id"];

            $token = bin2hex(random_bytes(32));
            $token_hash = password_hash($token, PASSWORD_DEFAULT);
            $expires_at = date("Y-m-d H:i:s", time() + 30 * 60);

            $del = mysqli_prepare($conn, "DELETE FROM password_resets WHERE user_id = ?");
            mysqli_stmt_bind_param($del, "i", $user_id);
            mysqli_stmt_execute($del);
            mysqli_stmt_close($del);

            $ins = mysqli_prepare(
                $conn,
                "INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, ?)"
            );
            mysqli_stmt_bind_param($ins, "iss", $user_id, $token_hash, $expires_at);
            mysqli_stmt_execute($ins);
            mysqli_stmt_close($ins);

            $dev_link = "reset_password.php?token=" . urlencode($token) . "&email=" . urlencode($email);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Forgot Password</title>
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
            <h2>Forgot Password</h2>
            <div class="subtitle">Enter your HAU student email to reset your password.</div>

            <form action="<?= $self ?>" method="post">
                <div class="form-group">
                    <label for="email">Student Email</label>
                    <input type="email" id="email" name="email" placeholder="name@student.hau.edu.ph" required>
                </div>

                <button type="submit">Generate Reset Link</button>
            </form>

            <?php if ($msg !== ""): ?>
                <div class="notice success">
                    <?= htmlspecialchars($msg) ?>
                    <?php if ($dev_link !== ""): ?>
                        <br><br><b>DEV LINK:</b>
                        <a href="<?= htmlspecialchars($dev_link) ?>">
                            <?= htmlspecialchars($dev_link) ?>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="link">
                Back to <a href="index.php">Log In</a>
            </div>
        </div>
    </div>
</div>

</body>
</html>
