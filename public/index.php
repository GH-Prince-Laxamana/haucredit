<?php
session_start();
require_once "../app/database.php";

require_once("../app/security_headers.php");
send_security_headers();

// ===== SELF-REFERENCING FORM ACTION =====
$self = htmlspecialchars($_SERVER["PHP_SELF"], ENT_QUOTES, "UTF-8");

// ===== ERROR MESSAGE VARIABLE =====
$error = "";

// ===== REDIRECT IF ALREADY LOGGED IN =====
if (isset($_SESSION["user_id"])) {
    header("Location: home.php");
    exit();
}

// ===== FORM SUBMISSION HANDLING =====
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $stud_num = trim($_POST["stud_num"] ?? "");
    $password = trim($_POST["password"] ?? "");

    // ===== INPUT VALIDATION =====
    if ($stud_num === "" || $password === "") {
        $error = "Please fill in all fields.";
    } else {

        // ===== USER LOOKUP =====
        $fetchUserByStudentNumberSql = "
            SELECT user_id, user_name, user_password, org_body
            FROM users
            WHERE stud_num = ?
            LIMIT 1
        ";

        $row = fetchOne(
            $conn,
            $fetchUserByStudentNumberSql,
            "s",
            [$stud_num]
        );

        // ===== PASSWORD VERIFICATION =====
        if ($row) {
            $stored = $row["user_password"];
            $login_ok = false;

            if (password_verify($password, $stored)) {
                $login_ok = true;
            } elseif ($password === $stored) {
                // Fallback for plain text passwords (migration support)
                $login_ok = true;

                $new_hash = password_hash($password, PASSWORD_DEFAULT);

                $updateUserPasswordSql = "
                    UPDATE users
                    SET user_password = ?
                    WHERE user_id = ?
                ";

                execQuery(
                    $conn,
                    $updateUserPasswordSql,
                    "si",
                    [$new_hash, $row["user_id"]]
                );
            }

            // ===== SUCCESSFUL LOGIN =====
            if ($login_ok) {
                session_regenerate_id(true);

                $_SESSION["user_id"] = $row["user_id"];
                $_SESSION["username"] = $row["user_name"];
                $_SESSION["org_body"] = $row["org_body"] ?? "";

                header("Location: home.php");
                exit();
            } else {
                $error = "Invalid student number or password.";
            }

        } else {
            $error = "Invalid student number or password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>HAUCREDIT - Log In</title>
    <link rel="stylesheet" href="assets/styles/layout.css" />
    <link rel="stylesheet" href="assets/styles/auth_styles.css" />
</head>

<body>
    <!-- ===== NAVIGATION BAR ===== -->
    <div class="navbar">
        <div class="navbar-brand">
            <img class="navbar-mark" src="assets/images/FavLogo.png" alt="HAUCREDIT mark">
            <div class="navbar-title">HAU<span class="accent">CREDIT</span></div>
        </div>

        <div class="navlinks">
            <a class="active" href="index.php">Login</a>
            <a href="register.php">Register</a>
            <a href="about.php">About</a>
        </div>
    </div>

    <!-- ===== AUTHENTICATION CONTAINER ===== -->
    <div class="auth-container">
        <!-- ===== LEFT PANEL: BRANDING ===== -->
        <div class="auth-left-panel">
            <div class="brand-title">
                <h1 class="brand-name">HAU<span class="brand-accent">CREDIT</span></h1>

                <p class="brand-tagline">
                    <b>Compliance & Records Engine</b> for Documentation and Institutional Tracking.
                </p>
            </div>

            <!-- Feature list for user awareness -->
            <ul>
                <li>Centralized Event Monitoring</li>
                <li>Automated OSA Checklists</li>
                <li>Secure Document Repository</li>
            </ul>
        </div>

        <!-- ===== RIGHT PANEL: LOGIN FORM ===== -->
        <div class="auth-right-panel">
            <div class="auth-card">
                <h2>User Log In</h2>
                <div class="subtitle">For recognized student organizations only.</div>

                <!-- ===== LOGIN FORM ===== -->
                <form action="<?= $self ?>" method="post" autocomplete="off">
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

                <!-- ===== ERROR MESSAGE DISPLAY ===== -->
                <?php if ($error !== ""): ?>
                        <div class="notice error"><?= htmlspecialchars($error, ENT_QUOTES, "UTF-8") ?></div>
                <?php endif; ?>

                <!-- ===== REGISTRATION LINK ===== -->
                <div class="link">
                    Don’t Have an Account? <a href="register.php">Register Now!</a>
                </div>
            </div>
        </div>
    </div>

    <?php include 'assets/includes/footer.php' ?>
</body>

</html>