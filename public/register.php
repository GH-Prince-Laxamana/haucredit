<?php
session_start();
require_once __DIR__ . '/../app/database.php';
require_once APP_PATH . "security_headers.php";
send_security_headers();

// ===== SELF-REFERENCING URL =====
$self = htmlspecialchars($_SERVER["PHP_SELF"], ENT_QUOTES, "UTF-8");

// ===== AUTHENTICATION CHECK =====
redirectIfLoggedIn();

// ===== LOAD ORGANIZATION OPTIONS FROM DB =====
$orgRows = fetchAll(
    $conn,
    "
    SELECT org_name
    FROM config_org_options
    WHERE is_active = 1
    ORDER BY sort_order ASC, org_name ASC
    "
);

$org_options = array_map(
    fn($row) => $row['org_name'],
    $orgRows
);

// ===== VARIABLE INITIALIZATION =====
$error = "";
$success = "";
$username = $email = $stud_num = $org_body = "";

// ===== POST REQUEST HANDLING =====
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["username"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $stud_num = trim($_POST["stud_num"] ?? "");
    $org_body = trim($_POST["org_body"] ?? "");
    $password = trim($_POST["password"] ?? "");
    $confirm = trim($_POST["confirm_password"] ?? "");

    // ===== VALIDATION: REQUIRED FIELDS =====
    if (
        $username === "" ||
        $email === "" ||
        $stud_num === "" ||
        $org_body === "" ||
        $password === "" ||
        $confirm === ""
    ) {
        $error = "Please fill in all fields.";

        // ===== VALIDATION: EMAIL FORMAT =====
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";

        // ===== VALIDATION: HAU STUDENT EMAIL ONLY =====
    } elseif (!preg_match('/@(student\.hau\.edu\.ph)$/i', $email)) {
        $error = "Email must be your HAU student email (e.g. @student.hau.edu.ph).";

        // ===== VALIDATION: STUDENT NUMBER LENGTH =====
    } elseif (strlen($stud_num) < 8) {
        $error = "Invalid student number.";

        // ===== VALIDATION: USERNAME LENGTH =====
    } elseif (strlen($username) < 4) {
        $error = "Username must be at least 4 characters.";

        // ===== VALIDATION: ORGANIZING BODY MUST EXIST IN CONFIG =====
    } elseif (!in_array($org_body, $org_options, true)) {
        $error = "Please select a valid organizing body.";

        // ===== VALIDATION: PASSWORD MATCH =====
    } elseif ($password !== $confirm) {
        $error = "Passwords do not match.";

        // ===== VALIDATION: PASSWORD STRENGTH =====
    } elseif (
        strlen($password) < 8 ||
        !preg_match('/[A-Z]/', $password) ||
        !preg_match('/[a-z]/', $password) ||
        !preg_match('/[0-9]/', $password)
    ) {
        $error = "Password must be at least 8 characters including a mix of uppercase (A-Z), lowercase (a-z), and numeric (0-9) characters.";

    } else {
        // ===== PASSWORD HASHING =====
        $hash = password_hash($password, PASSWORD_DEFAULT);

        // ===== DATABASE INSERTION =====
        $insertUserSql = "
            INSERT INTO users
                (user_name, user_password, user_email, stud_num, org_body, user_reg_date)
            VALUES (?, ?, ?, ?, ?, NOW())
        ";

        try {
            execQuery(
                $conn,
                $insertUserSql,
                "sssss",
                [$username, $hash, $email, $stud_num, $org_body]
            );

            $success = "Registration successful! You may now log in.";

            // Clear form fields after successful registration
            $username = $email = $stud_num = $org_body = "";

        } catch (mysqli_sql_exception) {
            $error = "Username, email, or student number already exists.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>HAUcredit - Register</title>
    <link rel="stylesheet" href="assets/styles/layout.css" />
    <link rel="stylesheet" href="assets/styles/auth_styles.css" />
</head>

<body>
    <div class="navbar">
        <div class="navbar-brand">
            <img class="navbar-mark" src="assets/images/haucredit_logo.png" alt="HAUCREDIT mark">
            <div class="navbar-title">HAU<span class="accent">CREDIT</span></div>
        </div>

        <div class="navlinks">
            <a href="index.php">Login</a>
            <a class="active" href="register.php">Register</a>
            <a href="about.php">About</a>
        </div>
    </div>

    <div class="auth-container">
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

        <div class="auth-right-panel">
            <div class="auth-card">
                <h2>Register Account</h2>
                <div class="subtitle">For recognized student organizations only.</div>

                <form action="<?= $self ?>" method="post">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" value="<?= htmlspecialchars($username) ?>"
                            placeholder="Enter username" required>
                    </div>

                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" value="<?= htmlspecialchars($email) ?>"
                            placeholder="name@student.hau.edu.ph" required>
                    </div>

                    <div class="form-group">
                        <label for="stud_num">Student No.</label>
                        <input type="text" id="stud_num" name="stud_num" value="<?= htmlspecialchars($stud_num) ?>"
                            placeholder="20XXXXXX" required>
                    </div>

                    <div class="form-group">
                        <label for="org_body">Organizing Body</label>
                        <input list="org_list" id="org_body" name="org_body" value="<?= htmlspecialchars($org_body) ?>"
                            placeholder="Search or select organization" required>
                        <datalist id="org_list">
                            <?php foreach ($org_options as $org): ?>
                                <option value="<?= htmlspecialchars($org, ENT_QUOTES, 'UTF-8') ?>">
                                <?php endforeach; ?>
                        </datalist>
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" placeholder="Minimum 8 characters"
                            required>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirm Password</label>
                        <input type="password" id="confirm_password" name="confirm_password"
                            placeholder="Confirm password" required>
                    </div>

                    <button type="submit">Create Account</button>
                </form>

                <?php if ($error !== ""): ?>
                    <div class="notice error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <?php if ($success !== ""): ?>
                    <div class="notice success"><?= htmlspecialchars($success) ?></div>
                <?php endif; ?>

                <div class="link">
                    Already a Member? <a href="index.php">Log In</a>
                </div>
            </div>
        </div>
    </div>

    <?php include 'assets/includes/footer.php' ?>
</body>

</html>