<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit();
}

$username = htmlspecialchars($_SESSION["username"], ENT_QUOTES, "UTF-8");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>HAUcredit</title>
</head>
<body>

    <h1>Welcome, <?= $username ?>!</h1>

    <form action="logout.php" method="post">
        <button type="submit">Logout</button>
    </form>

</body>
</html>
