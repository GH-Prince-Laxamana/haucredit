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
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>HAUCredit - Dashboard</title>
    <link rel="stylesheet" href="../app/css/layouts.css" />
</head>
<body>
    <div class="app">

    <aside class="sidebar">
      <div class="brand">
        <div class="avatar" aria-hidden="true"></div>
      </div>

      <nav class="nav">
        <a class="nav-item active" href="home.php">
          <span class="icon" aria-hidden="true"></span>
          <span>Dashboard</span>
        </a>

        <a class="nav-item" href="create_event.php">
          <span class="icon" aria-hidden="true"></span>
          <span>Create Event</span>
        </a>

        <a class="nav-item" href="#">
          <span class="icon" aria-hidden="true"></span>
          <span>Calendar</span>
        </a>

        <a class="nav-item" href="#">
          <span class="icon" aria-hidden="true"></span>
          <span>About Us</span>
        </a>
      </nav>

      <div class="account">
        <button class="account-btn" type="button">
          <span class="user-dot" aria-hidden="true"></span>
          <span>Account Name</span>
        </button>
      </div>
    </aside>

    <main class="main">
      <header class="topbar">
        <div class="title-wrap">
          <h1>Dashboard</h1>
          <p>Text here</p>
        </div>

        <div class="top-actions">
          <button class="icon-btn" type="button" aria-label="Notifications">
            <img src="../app/img/bell.png" alt="" class="icon-img">
          </button>

          <button class="primary-btn" type="button">
            <span class="plus" aria-hidden="true">+</span>
            Create Event
          </button>
        </div>
      </header>

      <section class="content">
        <div class="cards">
          <div class="card"></div>
          <div class="card"></div>
          <div class="card"></div>
          <div class="card"></div>
        </div>
      </section>
    </main>

  </div>
</body>
</html>
