<?php
session_start();
require_once "../app/database.php";
require_once "../app/security_headers.php";
send_security_headers();

if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit();
}

$username = htmlspecialchars($_SESSION["username"], ENT_QUOTES, "UTF-8");
$org_body = htmlspecialchars($_SESSION["org_body"], ENT_QUOTES, "UTF-8");

$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("
    SELECT event_id, event_name, start_datetime, end_datetime, venue_platform
    FROM events
    WHERE user_id = ?
    /*remove comment in the future (to show only actual active events) AND NOW() BETWEEN start_datetime AND end_datetime */
    LIMIT 4
");

$stmt->bind_param("i", $user_id);
$stmt->execute();

$events = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$show_view_all = count($events) > 3;
$active_events = array_slice($events, 0, 3);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">    <title>HAUCREDIT - Dashboard</title>
    <link rel="stylesheet" href="assets/styles/layout.css" />
    <link rel="stylesheet" href="assets/styles/home_styles.css" />
</head>
<body>
<div class="app">
    <div class="sidebar-overlay" id="sidebarOverlay" hidden></div>

    <?php include 'assets/includes/general_nav.php' ?>

    <main class="main">
        <header class="topbar">
            <button class="hamburger" id="menuBtn" type="button" aria-label="Open menu">☰</button>

            <div class="title-wrap">
                <h1>Dashboard</h1>
                <p><?= $org_body ?></p>
            </div>

            <div class="home-top-actions">
                <button class="home-icon-btn" type="button" aria-label="Notifications">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                    </svg>
                    <span class="home-notification-badge">3</span>
                </button>
                <button class="home-primary-btn" type="button">
                    <span aria-hidden="true">+</span>
                    Create Event
                </button>
            </div>
        </header>

        <section class="home-content">
            <!-- STATS -->
            <section class="home-stats-grid">
                <article class="home-stat-card">
                    <div class="home-stat-header">
                        <div class="home-stat-icon blue">
                            <svg width="22" height="22" fill="white" viewBox="0 0 24 24">
                                <path d="M19 4h-1V2h-2v2H8V2H6v2H5c-1.11 0-1.99.9-1.99 2L3 20c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 16H5V10h14v10zM9 14H7v-2h2v2zm4 0h-2v-2h2v2zm4 0h-2v-2h2v2zm-8 4H7v-2h2v2zm4 0h-2v-2h2v2zm4 0h-2v-2h2v2z"/>
                            </svg>
                        </div>
                    </div>
                    <p class="home-stat-value"><?= count($events) ?></p>
                    <p class="home-stat-label">Active Events</p>
                </article>

                <article class="home-stat-card">
                    <div class="home-stat-header">
                        <div class="home-stat-icon amber">
                            <svg width="22" height="22" fill="white" viewBox="0 0 24 24">
                                <path d="M12 5.99L19.53 19H4.47L12 5.99M12 2L1 21h22L12 2zm1 14h-2v2h2v-2zm0-6h-2v4h2v-4z"/>
                            </svg>
                        </div>
                    </div>
                    <p class="home-stat-value">3</p>
                    <p class="home-stat-label">Upcoming Deadlines</p>
                </article>

                <article class="home-stat-card">
                    <div class="home-stat-header">
                        <div class="home-stat-icon green">
                            <svg width="22" height="22" fill="white" viewBox="0 0 24 24">
                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                            </svg>
                        </div>
                    </div>
                    <p class="home-stat-value">82%</p>
                    <p class="home-stat-label">Compliance Progress</p>
                </article>

                <article class="home-stat-card">
                    <div class="home-stat-header">
                        <div class="home-stat-icon purple">
                            <svg width="22" height="22" fill="white" viewBox="0 0 24 24">
                                <path d="M20 6h-8l-2-2H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2zm0 12H4V8h16v10z"/>
                            </svg>
                        </div>
                    </div>
                    <p class="home-stat-value">24</p>
                    <p class="home-stat-label">Archived Events</p>
                </article>
            </section>

            <!-- ACTIVE EVENTS -->
            <section class="home-section">
                <header class="home-section-header">
                    <h2 class="home-section-title">Active Events</h2>
                    <?php if ($show_view_all): ?>
                        <a href="active_events.php" class="home-view-all">View All →</a>
                    <?php endif; ?>
                </header>

                <ul class="events-table">
                    <?php if (!empty($active_events)): ?>
                        <?php foreach ($active_events as $event): ?>
                            <li>
                                <article class="home-event-row">
                                    <div>
                                        <h3 class="home-event-name"><?= htmlspecialchars($event['event_name']) ?></h3>
                                        <p class="home-event-date">
                                            <time datetime="<?= htmlspecialchars($event['start_datetime']) ?>">
                                                <?= htmlspecialchars(date("F j, Y", strtotime($event['start_datetime']))) ?>
                                            </time>
                                            • <?= htmlspecialchars($event['venue_platform']) ?>
                                        </p>
                                    </div>

                                    <div class="home-event-progress">
                                        <div class="home-progress-bar-mini">
                                            <div class="home-progress-fill-mini" style="width: 50%"></div>
                                        </div>
                                        <span class="home-progress-text">50%</span>
                                    </div>

                                    <span class="home-status-badge active">Active</span>
                                    <a href="view_event.php?id=<?= $event['event_id'] ?>" class="home-btn-view">View</a>
                                </article>
                            </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li>No active events.</li>
                    <?php endif; ?>
                </ul>
            </section>

            <!-- DEADLINES -->
            <section class="home-section">
                <header class="home-section-header">
                    <h2 class="home-section-title">Upcoming Deadlines</h2>
                </header>

                <ul>
                    <li>
                        <article class="home-deadline-item">
                            <div class="home-deadline-info">
                                <h3>Event Name - Requirement Name</h3>
                                <p>Form code description or requirement details</p>
                            </div>
                            <div class="home-deadline-date">
                                <strong><time datetime="2026-03-20">Month Day</time></strong>
                                <span>X days left</span>
                            </div>
                        </article>
                    </li>
                    <li>
                        <article class="home-deadline-item">
                            <div class="home-deadline-info">
                                <h3>Event Name - Requirement Name</h3>
                                <p>Form code description or requirement details</p>
                            </div>
                            <div class="home-deadline-date">
                                <strong><time datetime="2026-03-20">Month Day</time></strong>
                                <span>X days left</span>
                            </div>
                        </article>
                    </li>
                    <li>
                        <article class="home-deadline-item">
                            <div class="home-deadline-info">
                                <h3>Event Name - Requirement Name</h3>
                                <p>Form code description or requirement details</p>
                            </div>
                            <div class="home-deadline-date">
                                <strong><time datetime="2026-03-20">Month Day</time></strong>
                                <span>X days left</span>
                            </div>
                        </article>
                    </li>
                </ul>
            </section>
        </section>

        <?php include 'assets/includes/footer.php' ?>
    </main>
</div>

<script src="assets/script/layout.js?v=1"></script>
</body>
</html>