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
    <link rel="stylesheet" href="../app/css/layout.css" />
</head>
<body>
    <div class="app">
        <!-- SIDEBAR -->
        <aside class="sidebar">
            <div class="brand">
                <div class="avatar" aria-hidden="true"></div>
                <div class="brand-name">HAUCREDIT</div>
                <div class="brand-subtitle">Compliance Tracker</div>
            </div>

            <nav class="nav">
                <a class="nav-item active" href="dashboard.php">
                    <span class="icon" aria-hidden="true">
                        <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M3 13h1v7c0 1.103.897 2 2 2h12c1.103 0 2-.897 2-2v-7h1a1 1 0 0 0 .707-1.707l-9-9a.999.999 0 0 0-1.414 0l-9 9A1 1 0 0 0 3 13zm7 7v-5h4v5h-4zm2-15.586 6 6V15l.001 5H16v-5c0-1.103-.897-2-2-2h-4c-1.103 0-2 .897-2 2v5H6v-9.586l6-6z"/>
                        </svg>
                    </span>
                    <span>Dashboard</span>
                </a>

                <a class="nav-item" href="createEvent.php">
                    <span class="icon" aria-hidden="true">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                    </span>
                    <span>Create Event</span>
                </a>

                <a class="nav-item" href="calendar.php">
                    <span class="icon" aria-hidden="true">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                    </span>
                    <span>Calendar</span>
                </a>

                <a class="nav-item" href="about.php">
                    <span class="icon" aria-hidden="true">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </span>
                    <span>About Us</span>
                </a>
            </nav>

            <div class="account">
                <button class="account-btn" type="button">
                    <span class="user-dot" aria-hidden="true"></span>
                    <span><?php echo $username; ?></span>
                </button>
            </div>
        </aside>

        <!-- MAIN -->
        <main class="main">
            <header class="topbar">
                <div class="title-wrap">
                    <h1>Dashboard</h1>
                    <p>SAS Student Council • AY 2025-2026</p>
                </div>
                <div class="top-actions">
                    <button class="icon-btn" type="button" aria-label="Notifications">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                        </svg>
                        <span class="notification-badge">3</span>
                    </button>
                    <button class="primary-btn" type="button">
                        <span aria-hidden="true">+</span>
                        Create Event
                    </button>
                </div>
            </header>

            <section class="content">
                <!-- Stats Grid -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-header">
                            <div class="stat-icon blue">
                                <svg width="22" height="22" fill="white" viewBox="0 0 24 24">
                                    <path d="M19 4h-1V2h-2v2H8V2H6v2H5c-1.11 0-1.99.9-1.99 2L3 20c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 16H5V10h14v10zM9 14H7v-2h2v2zm4 0h-2v-2h2v2zm4 0h-2v-2h2v2zm-8 4H7v-2h2v2zm4 0h-2v-2h2v2zm4 0h-2v-2h2v2z"/>
                                </svg>
                            </div>
                        </div>
                        <div class="stat-value">8</div>
                        <div class="stat-label">Active Events</div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-header">
                            <div class="stat-icon amber">
                                <svg width="22" height="22" fill="white" viewBox="0 0 24 24">
                                    <path d="M12 5.99L19.53 19H4.47L12 5.99M12 2L1 21h22L12 2zm1 14h-2v2h2v-2zm0-6h-2v4h2v-4z"/>
                                </svg>
                            </div>
                        </div>
                        <div class="stat-value">3</div>
                        <div class="stat-label">Upcoming Deadlines</div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-header">
                            <div class="stat-icon green">
                                <svg width="22" height="22" fill="white" viewBox="0 0 24 24">
                                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                                </svg>
                            </div>
                        </div>
                        <div class="stat-value">82%</div>
                        <div class="stat-label">Compliance Progress</div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-header">
                            <div class="stat-icon purple">
                                <svg width="22" height="22" fill="white" viewBox="0 0 24 24">
                                    <path d="M20 6h-8l-2-2H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2zm0 12H4V8h16v10z"/>
                                </svg>
                            </div>
                        </div>
                        <div class="stat-value">24</div>
                        <div class="stat-label">Archived Events</div>
                    </div>
                </div>

                <!-- Active Events -->
                <div class="section">
                    <div class="section-header">
                        <h3 class="section-title">Active Events</h3>
                        <a href="#" class="view-all">View All →</a>
                    </div>
                    <div class="events-table">
                        <div class="event-row">
                            <div>
                                <div class="event-name">Event Name</div>
                                <div class="event-date">Event Date • Event Venue</div>
                            </div>
                            <div class="event-progress">
                                <div class="progress-bar-mini">
                                    <div class="progress-fill-mini" style="width: 75%"></div>
                                </div>
                                <span class="progress-text">75%</span>
                            </div>
                            <span class="status-badge active">Active</span>
                            <a href="#" class="btn-view">View</a>
                        </div>

                        <div class="event-row">
                            <div>
                                <div class="event-name">Event Name</div>
                                <div class="event-date">Event Date • Event Venue</div>
                            </div>
                            <div class="event-progress">
                                <div class="progress-bar-mini">
                                    <div class="progress-fill-mini" style="width: 45%"></div>
                                </div>
                                <span class="progress-text">45%</span>
                            </div>
                            <span class="status-badge pending">Pending</span>
                            <a href="#" class="btn-view">View</a>
                        </div>

                        <div class="event-row">
                            <div>
                                <div class="event-name">Event Name</div>
                                <div class="event-date">Event Date • Event Venue</div>
                            </div>
                            <div class="event-progress">
                                <div class="progress-bar-mini">
                                    <div class="progress-fill-mini" style="width: 30%"></div>
                                </div>
                                <span class="progress-text">30%</span>
                            </div>
                            <span class="status-badge upcoming">Upcoming</span>
                            <a href="#" class="btn-view">View</a>
                        </div>

                        <div class="event-row">
                            <div>
                                <div class="event-name">Event Name</div>
                                <div class="event-date">Event Date • Event Venue</div>
                            </div>
                            <div class="event-progress">
                                <div class="progress-bar-mini">
                                    <div class="progress-fill-mini" style="width: 60%"></div>
                                </div>
                                <span class="progress-text">60%</span>
                            </div>
                            <span class="status-badge active">Active</span>
                            <a href="#" class="btn-view">View</a>
                        </div>
                    </div>
                </div>

                <!-- Upcoming Deadlines -->
                <div class="section">
                    <div class="section-header">
                        <h3 class="section-title">Upcoming Deadlines</h3>
                    </div>
                    <div class="deadline-item">
                        <div class="deadline-info">
                            <h4>Event Name - Requirement Name</h4>
                            <p>Form code description or requirement details</p>
                        </div>
                        <div class="deadline-date">
                            <strong>Month Day</strong>
                            <span>X days left</span>
                        </div>
                    </div>

                    <div class="deadline-item">
                        <div class="deadline-info">
                            <h4>Event Name - Requirement Name</h4>
                            <p>Form code description or requirement details</p>
                        </div>
                        <div class="deadline-date">
                            <strong>Month Day</strong>
                            <span>X days left</span>
                        </div>
                    </div>

                    <div class="deadline-item">
                        <div class="deadline-info">
                            <h4>Event Name - Requirement Name</h4>
                            <p>Form code description or requirement details</p>
                        </div>
                        <div class="deadline-date">
                            <strong>Month Day</strong>
                            <span>X days left</span>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>
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
          <span><?= $username ?></span>
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

  <a href="logout.php">Logout</a>
</body>
</html>