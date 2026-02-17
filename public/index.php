<?php
session_start();
require_once("../app/database.php");

require_once("../app/security_headers.php");
send_security_headers();

$self = htmlspecialchars($_SERVER["PHP_SELF"], ENT_QUOTES, "UTF-8");
$error = "";

// If already logged in, go home
if (isset($_SESSION["user_id"])) {
    header("Location: home.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $stud_num = trim($_POST["stud_num"] ?? "");
    $password = trim($_POST["password"] ?? "");

    if ($stud_num === "" || $password === "") {
        $error = "Please fill in all fields.";
    } else {

        $stmt = mysqli_prepare(
            $conn,
            "SELECT user_id, user_name, user_password
             FROM users
             WHERE stud_num = ?
             LIMIT 1"
        );

        mysqli_stmt_bind_param($stmt, "s", $stud_num);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if ($row = mysqli_fetch_assoc($result)) {

            $stored = $row["user_password"];
            $login_ok = false;

            // ✅ Case 1: already hashed password
            if (password_verify($password, $stored)) {
                $login_ok = true;
            }

            // ✅ Case 2: old plaintext password (legacy accounts)
            elseif ($password === $stored) {
                $login_ok = true;

                // 🔁 Auto-upgrade to hashed password after successful login
                $new_hash = password_hash($password, PASSWORD_DEFAULT);

                $upd = mysqli_prepare($conn, "UPDATE users SET user_password = ? WHERE user_id = ?");
                mysqli_stmt_bind_param($upd, "si", $new_hash, $row["user_id"]);
                mysqli_stmt_execute($upd);
                mysqli_stmt_close($upd);
            }

            if ($login_ok) {
                session_regenerate_id(true);

                $_SESSION["user_id"] = $row["user_id"];
                $_SESSION["username"] = $row["user_name"];

                header("Location: home.php");
                exit();
            } else {
                $error = "Invalid student number or password.";
            }

        } else {
            $error = "Invalid student number or password.";
        }

        mysqli_stmt_close($stmt);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>HAUcredit - Log In</title>
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
            <h2>User Log In</h2>
            <div class="subtitle">For recognized student organizations only.</div>

            <form action="<?= $self ?>" method="post">
                <div class="form-group">
                    <label for="stud_num">Student No.</label>
                    <input type="text" id="stud_num" name="stud_num" placeholder="20XXXXXX" required>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="Enter password" required>
                    <div class="forgot-wrap">
                        <a class="forgot-link" href="forgot_password.php">Forgot Password?</a>
                    </div>
                </div>

                <button type="submit">Log In</button>
            </form>

            <?php if ($error !== ""): ?>
                <div class="notice error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <div class="link">
                Don’t Have an Account? <a href="register.php">Register Now!</a>
            </div>
        </div>
    </div>
</div>

</body>
</html>
