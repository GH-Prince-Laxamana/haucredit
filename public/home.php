<?php
session_start();
require_once "../app/database.php";
require_once "../app/security_headers.php";
send_security_headers();

if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit();
}
/*remove comment in the future (to show only actual active events) AND NOW() BETWEEN start_datetime AND end_datetime */
$username = htmlspecialchars($_SESSION["username"], ENT_QUOTES, "UTF-8");
$org_body = htmlspecialchars($_SESSION["org_body"], ENT_QUOTES, "UTF-8");

$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("
        SELECT 
            e.event_id,
            e.event_name,
            e.start_datetime,
            e.end_datetime,
            e.venue_platform,

            COUNT(r.req_id) AS total_docs,
            SUM(CASE WHEN r.doc_status = 'uploaded' THEN 1 ELSE 0 END) AS uploaded_docs

        FROM events e
        LEFT JOIN requirements r ON r.event_id = e.event_id

        WHERE e.user_id = ? 
        AND e.archived_at IS NULL

        GROUP BY e.event_id
        ORDER BY e.start_datetime ASC
        LIMIT 4
");

$stmt->bind_param("i", $user_id);
$stmt->execute();

$events = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$total_docs_all = 0;
$uploaded_docs_all = 0;

foreach ($events as $e) {
    $total_docs_all += $e['total_docs'];
    $uploaded_docs_all += $e['uploaded_docs'];
}

$overall_progress = $total_docs_all
    ? round(($uploaded_docs_all / $total_docs_all) * 100)
    : 0;

$show_view_all = count($events) > 3;
$show_limit_events = array_slice($events, 0, 3);

$deadline_stmt = $conn->prepare("
    SELECT 
        e.event_id,
        r.req_name,
        r.req_desc,
        e.event_name,
        c.start_datetime AS deadline
    FROM requirements r
    JOIN events e ON r.event_id = e.event_id
    JOIN calendar_entries c ON c.event_id = e.event_id
    WHERE e.user_id = ? AND e.archived_at IS NULL
    AND r.doc_status = 'pending'
    ORDER BY c.start_datetime ASC;
");

$deadline_stmt->bind_param("i", $user_id);
$deadline_stmt->execute();

$deadlines = $deadline_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$show_view_all_deadlines = count($deadlines) > 3;
$show_limit_deadlines = array_slice($deadlines, 0, 3);

$archived_stmt = $conn->prepare("
    SELECT COUNT(*) as total
    FROM events
    WHERE user_id = ? AND archived_at IS NOT NULL
");
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
                    <a class="home-primary-btn" href="create_event.php">
                        <span aria-hidden="true"><i class="fa-solid fa-plus"></i></span>
                        Create Event
                    </a>
                </div>
            </header>

            <section class="home-content">
                <!-- STATS -->
                <section class="home-stats-grid">
                    <article class="home-stat-card">
                        <div class="home-stat-header">
                            <div class="home-stat-icon blue">
                                <i class="fa-regular fa-calendar-days"></i>
                            </div>
                        </div>
                        <p class="home-stat-value"><?= count($events) ?></p>
                        <p class="home-stat-label">Active Events</p>
                    </article>

                    <article class="home-stat-card">
                        <div class="home-stat-header">
                            <div class="home-stat-icon amber">
                                <i class="fa-solid fa-list-check"></i>
                            </div>
                        </div>
                        <p class="home-stat-value"><?= count($deadlines) ?></p>
                        <p class="home-stat-label">Upcoming Deadlines</p>
                    </article>

                    <article class="home-stat-card">
                        <div class="home-stat-header">
                            <div class="home-stat-icon green">
                                <i class="fa-solid fa-circle-check"></i>
                            </div>
                        </div>
                        <p class="home-stat-value"><?= $overall_progress ?>%</p>
                        <p class="home-stat-label">Compliance Progress</p>
                    </article>

                    <article class="home-stat-card">
                        <div class="home-stat-header">
                            <div class="home-stat-icon purple">
                                <i class="fa-regular fa-folder-open"></i>
                            </div>
                        </div>
                        <p class="home-stat-value"><?= $archived_events ?></p>
                        <p class="home-stat-label">Archived Events</p>
                    </article>
                </section>

                <!-- ACTIVE EVENTS -->
                <section class="home-section">
                    <header class="home-section-header">
                        <h2 class="home-section-title">Active Events</h2>
                        <?php if ($show_view_all): ?>
                            <a href="my_events.php" class="home-view-all">View All →</a>
                        <?php endif; ?>
                    </header>

                    <ul class="events-table">
                        <?php if (!empty($show_limit_events)): ?>
                            <?php foreach ($show_limit_events as $event):
                                $total = $event['total_docs'];
                                $uploaded = $event['uploaded_docs'];
                                $progress = $total ? round(($uploaded / $total) * 100) : 0;

                                $progress_color = '#d32f2f';
                                if ($progress >= 75)
                                    $progress_color = '#2e7d32';
                                elseif ($progress >= 40)
                                    $progress_color = '#f9a825';
                                ?>
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
                                                <div class="home-progress-fill-mini"
                                                    style="width: <?= $progress ?>%; background: <?= $progress_color ?>"></div>
                                            </div>
                                            <span class="home-progress-text"><?= $progress ?>%</span>
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
                        <?php if ($show_view_all_deadlines): ?>
                            <a href="requirements.php" class="home-view-all">View All →</a>
                        <?php endif; ?>
                    </header>

                    <ul>
                        <?php if (!empty($show_limit_deadlines)): ?>

                            <?php foreach ($show_limit_deadlines as $d):

                                $deadline = strtotime($d['deadline']);
                                $days_left = ceil(($deadline - time()) / 86400);

                                ?>

                                <li>
                                    <article class="home-deadline-item">

                                        <div class="home-deadline-info">
                                            <h3>
                                                <?= htmlspecialchars($d['event_name']) ?> -
                                                <?= htmlspecialchars($d['req_name']) ?>
                                            </h3>
                                            <p>
                                                <?= htmlspecialchars($d['req_desc'] ?? 'Requirement submission') ?>
                                            </p>
                                        </div>

                                        <div class="home-deadline-date">
                                            <strong>
                                                <time datetime="<?= htmlspecialchars($d['deadline']) ?>">
                                                    <?= date("F j", $deadline) ?>
                                                </time>
                                            </strong>

                                            <span>
                                                <?= $days_left ?>         <?= $days_left == 1 ? 'day' : 'days' ?> left
                                            </span>

                                        </div>
                                        <a href="view_event.php?id=<?= $d['event_id'] ?>" class="home-btn-view">View</a>
                                    </article>
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

    <script src="assets/script/layout.js?v=1"></script>
</body>

</html>