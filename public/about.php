<?php
session_start(); // <-- MUST be at the top

include "../app/database.php";

if (!isset($_SESSION["user_id"])) {
  header("Location: index.php");
  exit();
}

$username = htmlspecialchars($_SESSION["username"], ENT_QUOTES, "UTF-8");
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>About Us</title>
  <link rel="stylesheet" href="assets/styles/layout.css" />
</head>

<body>
  <div class="app">

    <!-- SIDEBAR -->
    <?= include 'assets/includes/general_nav.php' ?>

    <!-- MAIN -->
    <main class="main">
      <header class="topbar">
        <div class="title-wrap">
          <h1>About Us</h1>
          <p>Text Here</p>
        </div>

      </header>

      <section class="content about-page">
        <div class="about-grid">
          <article class="about-card">
            <div class="about-img" aria-hidden="true"></div>
            <div class="about-body"></div>
          </article>

          <article class="about-card">
            <div class="about-img" aria-hidden="true"></div>
            <div class="about-body"></div>
          </article>

          <article class="about-card">
            <div class="about-img" aria-hidden="true"></div>
            <div class="about-body"></div>
          </article>

          <article class="about-card">
            <div class="about-img" aria-hidden="true"></div>
            <div class="about-body"></div>
          </article>
        </div>
      </section>
    </main>

  </div>
</body>

</html>