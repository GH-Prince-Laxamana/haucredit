<?php
session_start();
require_once("../app/database.php");

require_once("../app/security_headers.php");
send_security_headers();

$self = htmlspecialchars($_SERVER["PHP_SELF"], ENT_QUOTES, "UTF-8");

$error = "";
$success = "";

if (isset($_SESSION["user_id"])) {
    header("Location: home.php");
    exit();
}

$username = $email = $stud_num = $org_body = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $username = trim($_POST["username"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $stud_num = trim($_POST["stud_num"] ?? "");
    $org_body = trim($_POST["org_body"] ?? "");
    $password = trim($_POST["password"] ?? "");
    $confirm = trim($_POST["confirm_password"] ?? "");

    if ($username === "" || $email === "" || $stud_num === "" || $org_body === "" || $password === "" || $confirm === "") {
        $error = "Please fill in all fields.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";

    } elseif (!preg_match('/@(student\.hau\.edu\.ph)$/i', $email)) {
        $error = "Email must be your HAU student email (e.g. @student.hau.edu.ph).";

    } elseif (strlen($stud_num) < 8) {
        $error = "Invalid student number.";

    } elseif ($password !== $confirm) {
        $error = "Passwords do not match.";

    } elseif (
        strlen($password) < 8 ||
        !preg_match('/[A-Z]/', $password) ||
        !preg_match('/[a-z]/', $password) ||
        !preg_match('/[0-9]/', $password)
    ) {
        $error = "Password must be at least 8 characters including a mix of uppercase (A-Z), lowercase (a-z), and numeric (0-9) characters";

    } else {

        // HASH PASSWORD
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = mysqli_prepare(
            $conn,
            "INSERT INTO users 
                (user_name, user_password, user_email, stud_num, org_body) 
             VALUES (?, ?, ?, ?, ?)"
        );

        mysqli_stmt_bind_param($stmt, "sssss", $username, $hash, $email, $stud_num, $org_body);

        try {
            mysqli_stmt_execute($stmt);
            $success = "Registration successful! You may now log in.";

            $username = $email = $stud_num = $org_body = "";

        } catch (mysqli_sql_exception) {
            $error = "Username, email, or student number already exists.";
        }

        mysqli_stmt_close($stmt);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>HAUcredit - Register</title>
    <link rel="stylesheet" href="../css/layout.css">
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
            <h2>Register Account</h2>
            <div class="subtitle">For recognized student organizations only.</div>

            <form action="<?= $self ?>" method="post">

                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username"
                           value="<?= htmlspecialchars($username) ?>" placeholder="Enter username" required>
                </div>

                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email"
                           value="<?= htmlspecialchars($email) ?>" placeholder="name@student.hau.edu.ph" required>
                </div>

                <div class="form-group">
                    <label for="stud_num">Student No.</label>
                    <input type="text" id="stud_num" name="stud_num"
                           value="<?= htmlspecialchars($stud_num) ?>" placeholder="20XXXXXX" required>
                </div>

                <div class="form-group">
                    <label for="org_body">Organizing Body</label>
                    <input list="org_list" id="org_body" name="org_body"
                           value="<?= htmlspecialchars($org_body) ?>" placeholder="Select/Type org" required>
                    <datalist id="org_list">
                        <option value="University Student Council (USC)">
                        <option value="SOC">
                        <option value="SAS">
                        <option value="SEA">
                        <option value="CCJEF">
                        <option value="SHTM">
                    </datalist>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="Minimum 8 characters" required>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm password" required>
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

</body>
</html>
