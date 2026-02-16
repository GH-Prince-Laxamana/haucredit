<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Create Event</title>
  <link rel="stylesheet" href="../app/css/layout.css" />
</head>
<body>
  <div class="app">

    <!-- SIDEBAR -->
    <aside class="sidebar">
      <div class="brand">
        <div class="avatar" aria-hidden="true"></div>
      </div>

      <nav class="nav">
        <a class="nav-item" href="home.php">
          <span class="icon" aria-hidden="true"></span>
          <span>Dashboard</span>
        </a>

        <a class="nav-item active" href="create_event.php">
          <span class="icon" aria-hidden="true"></span>
          <span>Create Event</span>
        </a>

        <a class="nav-item" href="calendar.php">
          <span class="icon" aria-hidden="true"></span>
          <span>Calendar</span>
        </a>

        <a class="nav-item" href="about.php">
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
          <h1>Create Event</h1>
          <p>Text here</p>
        </div>
      </header>

      <section class="content create-event">

        <!-- Accordion: Basic Information -->
        <details class="acc" open>
          <summary class="acc-head">
            <span class="acc-left">
              <span class="acc-dot" aria-hidden="true"></span>
              <span class="acc-text">
                <span class="acc-title">Basic Information</span>
                <span class="acc-sub">Text Here</span>
              </span>
            </span>
            <span class="acc-chevron" aria-hidden="true"></span>
          </summary>

          <div class="acc-body">
            <div class="form-grid">
              <div class="field"></div>
              <div class="field"></div>
              <div class="field"></div>

              <div class="field-row">
                <div class="field"></div>
                <div class="field"></div>
              </div>
            </div>
          </div>
        </details>

        <!-- Accordion: Event Classification -->
        <details class="acc">
          <summary class="acc-head">
            <span class="acc-left">
              <span class="acc-dot" aria-hidden="true"></span>
              <span class="acc-text">
                <span class="acc-title">Event Classification</span>
                <span class="acc-sub">Text Here</span>
              </span>
            </span>
            <span class="acc-chevron" aria-hidden="true"></span>
          </summary>

          <div class="acc-body">
            <div class="form-grid">
              <div class="field"></div>
              <div class="field"></div>
              <div class="field"></div>
            </div>
          </div>
        </details>

        <!-- Accordion: Schedule & Logistics -->
        <details class="acc">
          <summary class="acc-head">
            <span class="acc-left">
              <span class="acc-dot" aria-hidden="true"></span>
              <span class="acc-text">
                <span class="acc-title">Schedule &amp; Logistics</span>
                <span class="acc-sub">Text Here</span>
              </span>
            </span>
            <span class="acc-chevron" aria-hidden="true"></span>
          </summary>

          <div class="acc-body">
            <div class="form-grid">
              <div class="field-row">
                <div class="field"></div>
                <div class="field"></div>
              </div>
              <div class="field"></div>
              <div class="field"></div>
            </div>
          </div>
        </details>
      </section>
    </main>
  </div>
</body>
</html>