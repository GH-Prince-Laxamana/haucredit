<?php
session_start();
include("../app/database.php");

$self = htmlspecialchars($_SERVER["PHP_SELF"], ENT_QUOTES, "UTF-8");

$error = "";

if (isset($_SESSION["user_id"])) {
    header("Location: home.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $stud_num = trim($_POST["stud_num"] ?? "");
    $password = trim($_POST["password"] ?? "");

    if (empty($stud_num) || empty($password)) {
        $error = "Please fill in all fields.";

    } else {

        $stmt = mysqli_prepare(
            $conn,
            "SELECT user_id, user_name, user_password
             FROM users 
             WHERE stud_num = ?"
        );

        mysqli_stmt_bind_param($stmt, "s", $stud_num);
        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);

        if ($row = mysqli_fetch_assoc($result)) {

            if ($password === $row["user_password"]) { //password_verify($password, $row["user_password"]) if using $hash

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
    <title>Sign In</title>
</head>

<body>
    <h1>Sign In</h1>

    <form action="<?= $self ?>" method="post">
        <p>
            <label for="stud_num">Student No:</label><br>
            <input type="text" id="stud_num" name="stud_num" required>
        </p>

        <p>
            <label for="password">Password:</label><br>
            <input type="password" id="password" name="password" required>
        </p>
        <p><a href="forgot_password.php">Forgot Password?</a></p>

        <p>
            <button type="submit">Log In</button>
        </p>
    </form>

    <?php if (!empty($error)): ?>
        <p style="color: red;"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <p>Do not have an account? <a href="register.php">Register here</a></p>
</body>

</html>

<?php mysqli_close($conn); ?>