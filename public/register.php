<?php
session_start();
include("../app/database.php");

$self = htmlspecialchars($_SERVER["PHP_SELF"], ENT_QUOTES, "UTF-8");

$error = "";
$success = "";

if (isset($_SESSION["user_id"])) {
    header("Location: home.php");
    exit();
}

$username = $email = $stud_num = $org_body = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $username = trim($_POST["username"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $stud_num = trim($_POST["stud_num"] ?? "");
    $org_body = trim($_POST["org_body"] ?? "");
    $password = trim($_POST["password"] ?? "");
    $confirm = trim($_POST["confirm_password"] ?? "");

    if (empty($username) || empty($email) || empty($stud_num) || empty($org_body) || empty($password) || empty($confirm)) {
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
        // $hash = password_hash($password, PASSWORD_DEFAULT); //for encrypting, maybe not use yet

        $stmt = mysqli_prepare(
            $conn,
            "INSERT INTO users 
            (user_name, user_password, user_email, stud_num, org_body) 
            VALUES (?, ?, ?, ?, ?)"
        );

        mysqli_stmt_bind_param(
            $stmt,
            "sssss",
            $username,
            $password, //change to $hash if using encrypted passwords, NOTE: forgot password feature not yet implemented
            $email,
            $stud_num,
            $org_body
        );

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
    <title>Register</title>
</head>

<body>
    <h1>Register New Account</h1>

    <form action="<?= $self ?>" method="post">

        <p>
            <label for="username">Username:</label><br>
            <input type="text" id="username" name="username" value="<?= htmlspecialchars($username) ?>" required>
        </p>

        <p>
            <label for="email">Student Email:</label><br>
            <input type="email" id="email" name="email" value="<?= htmlspecialchars($email) ?>" required>
        </p>

        <p>
            <label for="stud_num">Student Number:</label><br>
            <input type="text" id="stud_num" name="stud_num" value="<?= htmlspecialchars($stud_num) ?>" required>
        </p>

        <p>
            <label for="org_body">Organizing Body:</label><br>
            <input list="org_list" id="org_body" name="org_body" value="<?= htmlspecialchars($org_body) ?>" required>
            <datalist id="org_list">
                <!-- temp selections -->
                <option value="SOC">
                <option value="SAS">
                <option value="SEA">
                <option value="CCJEF">
                <option value="SHTM">
            </datalist>
        </p>

        <p>
            <label for="password">Password:</label><br>
            <input type="password" id="password" name="password" required>
        </p>

        <p>
            <label for="confirm_password">Confirm Password:</label><br>
            <input type="password" id="confirm_password" name="confirm_password" required>
        </p>

        <p>
            <button type="submit">Register</button>
        </p>

    </form>

    <?php if (!empty($error)): ?>
        <p style="color: red;"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <p><?= htmlspecialchars($success) ?></p>
    <?php endif; ?>

    <p>Already a member? <a href="index.php">Log in</a></p>
</body>

</html>

<?php mysqli_close($conn); ?>