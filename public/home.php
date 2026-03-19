<?php
// Start session to manage user authentication
session_start();
require_once "../app/database.php";
require_once "../app/security_headers.php";
send_security_headers();

// ===== AUTHENTICATION CHECK =====
// Ensure user is logged in before displaying dashboard
if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit();
}

// ===== USER DATA EXTRACTION =====
// Retrieve and sanitize user information from session
$user_id = $_SESSION['user_id'];
$username = htmlspecialchars($_SESSION["username"], ENT_QUOTES, "UTF-8");
$org_body = htmlspecialchars($_SESSION["org_body"], ENT_QUOTES, "UTF-8");

/* ================= EVENTS QUERY ================= */
// Fetch active events with document progress for the current user
// Now JOINs with event_dates (for start/end times) and event_location (for venue)
// Includes calculation of event phase based on completion and timing
$stmt = $conn->prepare("
    SELECT 
        e.event_id,
        e.event_name,
        ed.start_datetime,
        ed.end_datetime,
        el.venue_platform,
        COUNT(r.req_id) AS total_docs,
        SUM(CASE WHEN rs.doc_status = 'uploaded' THEN 1 ELSE 0 END) AS uploaded_docs,
        CASE
            WHEN SUM(CASE WHEN rs.doc_status = 'uploaded' THEN 1 ELSE 0 END) < COUNT(r.req_id)
                THEN 'pending'
            WHEN ed.end_datetime >= NOW()
                THEN 'active'
            ELSE 'completed'
        END AS event_phase
    FROM events e
    LEFT JOIN event_dates ed ON e.event_id = ed.event_id
    LEFT JOIN event_location el ON e.event_id = el.event_id
    LEFT JOIN requirements r ON r.event_id = e.event_id
    LEFT JOIN requirement_status rs ON r.req_id = rs.req_id
    WHERE e.user_id = ? AND e.archived_at IS NULL AND ed.end_datetime >= NOW()
    GROUP BY e.event_id
    ORDER BY ed.start_datetime ASC
    LIMIT 4
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$events = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

/* ================= PROGRESS CALCULATIONS ================= */
// Calculate overall compliance progress across all active events
$total_docs_all = array_sum(array_column($events, 'total_docs'));
$uploaded_docs_all = array_sum(array_column($events, 'uploaded_docs'));
$overall_progress = $total_docs_all ? round(($uploaded_docs_all / $total_docs_all) * 100) : 0;

// Determine if "View All" link should be shown for events
$show_view_all = count($events) > 3;
$show_limit_events = array_slice($events, 0, 3);

/* ================= EVENT PHASE COUNTS ================= */
// Count events by their current phase for potential future use
$active = $pending = $completed = 0;
foreach ($show_limit_events as $e) {
    switch ($e['event_phase']) {
        case 'active':
            $active++;
            break;
        case 'pending':
            $pending++;
            break;
        case 'completed':
            $completed++;
            break;
    }
}

/* ================= DEADLINES QUERY ================= */
// Fetch upcoming deadlines for pending requirements
// Now uses requirement_status.deadline instead of calendar_entries.start_datetime
$deadline_stmt = $conn->prepare("
    SELECT 
        e.event_id,
        r.req_name,
        r.req_desc,
        e.event_name,
        rs.deadline
    FROM requirements r
    JOIN events e ON r.event_id = e.event_id
    JOIN requirement_status rs ON r.req_id = rs.req_id
    WHERE e.user_id = ? AND e.archived_at IS NULL AND rs.doc_status = 'pending' AND rs.deadline IS NOT NULL
    ORDER BY rs.deadline ASC
");
$deadline_stmt->bind_param("i", $user_id);
$deadline_stmt->execute();
$deadlines = $deadline_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Determine if "View All" link should be shown for deadlines
$show_view_all_deadlines = count($deadlines) > 3;
$show_limit_deadlines = array_slice($deadlines, 0, 3);

/* ================= ARCHIVED EVENTS COUNT ================= */
// Count total archived events for the user (unchanged, as it only queries events)
$archived_stmt = $conn->prepare("SELECT COUNT(*) AS total FROM events WHERE user_id = ? AND archived_at IS NOT NULL");
$archived_stmt->bind_param("i", $user_id);
$archived_stmt->execute();
$archived_events = $archived_stmt->get_result()->fetch_assoc()['total'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HAUCREDIT - Dashboard</title>
    <link rel="stylesheet" href="assets/styles/layout.css" />
    <link rel="stylesheet" href="assets/styles/home_styles.css" />
</head>

<body>
    <div class="app">
        <!-- Sidebar overlay for mobile navigation -->
        <div class="sidebar-overlay" id="sidebarOverlay" hidden></div>
        
        <?php include 'assets/includes/general_nav.php' ?>

        <main class="main">
            <!-- ===== PAGE HEADER ===== -->
            <header class="topbar">
                <button class="hamburger" id="menuBtn" type="button" aria-label="Open menu">☰</button>
                <div class="title-wrap">
                    <h1>Dashboard</h1>
                    <p><?= $username ?></p>
                    <p><?= $org_body ?></p>
                </div>
                <div class="home-top-actions">
                    <a class="btn-primary" href="create_event.php">
                        <i class="fa-solid fa-plus"></i> Create Event
                    </a>
                </div>
            </header>

            <!-- ===== DASHBOARD CONTENT ===== -->
            <section class="home-content">
                
                <!-- ===== STATISTICS GRID ===== -->
                <section class="home-stats-grid">
                    <?php
                    // Define statistics cards with their data and links
                    $stats = [
                        ['count' => count($events), 'label' => 'Active Events', 'icon' => 'fa-regular fa-calendar-days', 'link' => 'my_events.php'],
                        ['count' => count($deadlines), 'label' => 'Upcoming Deadlines', 'icon' => 'fa-solid fa-list-check', 'link' => 'requirements.php#req-deadlines'],
                        ['count' => $overall_progress . '%', 'label' => 'Compliance Progress', 'icon' => 'fa-solid fa-circle-check', 'link' => 'requirements.php'],
                        ['count' => $archived_events, 'label' => 'Archived Events', 'icon' => 'fa-regular fa-folder-open', 'link' => 'archived_events.php']
                    ];

                    // Render each statistic card
                    foreach ($stats as $s): ?>
                        <a href="<?= $s['link'] ?>" class="home-stat-link">
                            <article class="home-stat-card">
                                <div class="home-stat-header">
                                    <div class="home-stat-icon"><i class="<?= $s['icon'] ?>"></i></div>
                                </div>
                                <p class="home-stat-value"><?= $s['count'] ?></p>
                                <p class="home-stat-label"><?= $s['label'] ?></p>
                            </article>
                        </a>
                    <?php endforeach; ?>
                </section>

                <!-- ===== ACTIVE EVENTS SECTION ===== -->
                <section class="home-section">
                    <header class="home-section-header">
                        <h2 class="home-section-title">Active Events</h2>
                        <?php if ($show_view_all): ?>
                            <a href="my_events.php" class="btn-secondary btn-smaller">View All</a>
                        <?php endif; ?>
                    </header>

                    <ul class="events-table">
                        <?php if ($show_limit_events): ?>
                            <?php foreach ($show_limit_events as $event):
                                // Calculate progress percentage for this event
                                $progress = $event['total_docs'] ? round(($event['uploaded_docs'] / $event['total_docs']) * 100) : 0;
                                // Determine progress bar color based on completion level
                                $progress_color = $progress >= 75 ? '#2e7d32' : ($progress >= 40 ? '#f9a825' : '#d32f2f');
                                ?>
                                <a class="event-card-container" href="view_event.php?id=<?= $event['event_id'] ?>">
                                    <article class="event-card">
                                        <div class="event-main">
                                            <div class="event-info">
                                                <div class="event-title"><?= htmlspecialchars($event['event_name']) ?></div>
                                                <div class="event-sub">
                                                    <?= htmlspecialchars($event['venue_platform']) ?> •
                                                    <time datetime="<?= htmlspecialchars($event['start_datetime']) ?>">
                                                        <?= date("F j, g:i A", strtotime($event['start_datetime'])) ?>
                                                    </time>
                                                </div>
                                            </div>

                                            <div class="event-progress">
                                                <div class="home-progress-bar-mini">
                                                    <div class="home-progress-fill-mini"
                                                        style="width: <?= $progress ?>%; background: <?= $progress_color ?>">
                                                    </div>
                                                </div>
                                                <span class="home-progress-text"><?= $progress ?>%</span>
                                            </div>
                                        </div>
                                        <span class="home-status-badge <?= htmlspecialchars($event['event_phase']) ?>">
                                            <?= ucfirst($event['event_phase']) ?>
                                        </span>
                                    </article>
                                </a>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li>No active events after <?= date("F j") ?>.</li>
                        <?php endif; ?>
                    </ul>
                </section>

                <!-- ===== UPCOMING DEADLINES SECTION ===== -->
                <section class="home-section">
                    <header class="home-section-header">
                        <h2 class="home-section-title">Upcoming Deadlines</h2>
                        <?php if ($show_view_all_deadlines): ?>
                            <a href="my_events.php" class="btn-secondary btn-smaller">View All</a>
                        <?php endif; ?>
                    </header>

                    <ul>
                        <?php if ($show_limit_deadlines): ?>
                            <?php foreach ($show_limit_deadlines as $d): ?>
                                <li>
                                    <a class="req-card" href="view_event.php?id=<?= $d['event_id'] ?>">
                                        <div class="req-item">
                                            <div class="req-title">
                                                <?= htmlspecialchars($d['req_name']) ?>
                                                <?php if (!empty($d['req_desc'])): ?>
                                                    <span class="tooltip-icon">
                                                        <i class="fa-regular fa-circle-question"></i>
                                                        <span class="tooltip-text"><?= htmlspecialchars($d['req_desc']) ?></span>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="req-sub">
                                                From <?= htmlspecialchars($d['event_name']) ?> •
                                                <strong>
                                                    <time datetime="<?= htmlspecialchars($d['deadline']) ?>">
                                                        <?= date("g:i A", strtotime($d['deadline'])) ?>
                                                    </time>
                                                </strong>
                                            </div>
                                        </div>
                                        <span class="status pending">Pending</span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li>No upcoming deadlines.</li>
                        <?php endif; ?>
                    </ul>
                </section>
            </section>

            <?php include 'assets/includes/footer.php' ?>
        </main>
    </div>

    <script src="../app/script/layout.js?v=1"></script>
</body>
</html>