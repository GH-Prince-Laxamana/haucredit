<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit();
}

require_once("../app/security_headers.php");
send_security_headers();

$username = htmlspecialchars($_SESSION["username"], ENT_QUOTES, "UTF-8");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>HAUcredit - Home</title>
    <link rel="stylesheet" href="../CSS/styles.css">
</head>
<body>

<div class="navbar">
    <div class="logo"></div>

    <div class="navlinks">
        <a href="home.php">Dashboard</a>
        <a href="#">My Events</a>
        <a href="#">Accreditation</a>
        <a href="#">About Us</a>
        <a href="#">Contact Us</a>
    </div>

    <div class="search">
        <input type="text" placeholder="Search">
    </div>
</div>

<div class="home-wrap">
    <h1>Welcome, <?= $username ?>!</h1>

    <form action="logout.php" method="post">
        <button class="logout-btn" type="submit">Logout</button>
    </form>
</div>

</body>
</html>
