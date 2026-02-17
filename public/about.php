<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>About Us</title>
  <link rel="stylesheet" href="assets/styles/layout.css"/>
</head>
<body>
  <div class="app">

    <!-- SIDEBAR -->
    <aside class="sidebar">
      <div class="brand">
        <div class="avatar" aria-hidden="true"></div>
      </div>

      <nav class="nav">
        <a class="nav-item" href="index.html">
          <span class="icon" aria-hidden="true"></span>
          <span>Dashboard</span>
        </a>

        <a class="nav-item" href="create_event.php">
          <span class="icon" aria-hidden="true"></span>
          <span>Create Event</span>
        </a>

        <a class="nav-item" href="calendar.php">
          <span class="icon" aria-hidden="true"></span>
          <span>Calendar</span>
        </a>

        <a class="nav-item active" href="about.php">
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
