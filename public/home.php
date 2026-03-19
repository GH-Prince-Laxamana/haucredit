<?php
session_start();
require_once "../app/database.php";
require_once "../app/security_headers.php";
send_security_headers();

if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit();
}

$user_id = (int) $_SESSION['user_id'];
$username = htmlspecialchars($_SESSION["username"], ENT_QUOTES, "UTF-8");
$org_body = htmlspecialchars($_SESSION["org_body"], ENT_QUOTES, "UTF-8");

/* ================= ALL ACTIVE / UPCOMING EVENTS QUERY =================
   Uses:
   - events
   - event_dates
   - event_location
   - event_metrics
   - docs_total / docs_uploaded from events
         AND (
            ed.end_datetime IS NULL
            OR ed.end_datetime >= NOW()
          )
*/
$stmt = $conn->prepare("
    SELECT
        e.event_id,
        e.event_name,
        e.docs_total,
        e.docs_uploaded,
        e.event_status,
        ed.start_datetime,
        ed.end_datetime,
        el.venue_platform,
        em.target_metric,
        CASE
            WHEN e.docs_total > 0 AND e.docs_uploaded < e.docs_total THEN 'pending'
            WHEN ed.end_datetime IS NOT NULL AND ed.end_datetime >= NOW() THEN 'active'
            ELSE 'completed'
        END AS event_phase
    FROM events e
    LEFT JOIN event_dates ed ON e.event_id = ed.event_id
    LEFT JOIN event_location el ON e.event_id = el.event_id
    LEFT JOIN event_metrics em ON e.event_id = em.event_id
    WHERE e.user_id = ?
      AND e.archived_at IS NULL
    ORDER BY
        CASE WHEN ed.start_datetime IS NULL THEN 1 ELSE 0 END,
        ed.start_datetime ASC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$all_events = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

/* ================= PROGRESS CALCULATIONS ================= */
$total_docs_all = array_sum(array_map(fn($e) => (int) ($e['docs_total'] ?? 0), $all_events));
$uploaded_docs_all = array_sum(array_map(fn($e) => (int) ($e['docs_uploaded'] ?? 0), $all_events));
$overall_progress = $total_docs_all > 0 ? round(($uploaded_docs_all / $total_docs_all) * 100) : 0;

/* ================= EVENT DISPLAY ================= */
$total_active_events = count($all_events);
$show_view_all = $total_active_events > 3;
$show_limit_events = array_slice($all_events, 0, 3);

/* ================= EVENT PHASE COUNTS ================= */
$active = 0;
$pending = 0;
$completed = 0;

foreach ($all_events as $e) {
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

/* ================= DEADLINES QUERY =================
   Uses:
   - event_requirements.deadline
   - event_requirements.submission_status
   - requirement_templates.req_name / req_desc
*/
$deadline_stmt = $conn->prepare("
    SELECT
        e.event_id,
        rt.req_name,
        rt.req_desc,
        e.event_name,
        er.deadline
    FROM event_requirements er
    INNER JOIN events e
        ON er.event_id = e.event_id
    INNER JOIN requirement_templates rt
        ON er.req_template_id = rt.req_template_id
    LEFT JOIN event_dates ed
        ON e.event_id = ed.event_id
    WHERE e.user_id = ?
      AND e.archived_at IS NULL
      AND er.submission_status = 'pending'
      AND er.deadline IS NOT NULL
      AND (
            ed.end_datetime IS NULL
            OR ed.end_datetime >= NOW()
          )
    ORDER BY er.deadline ASC
");
$deadline_stmt->bind_param("i", $user_id);
$deadline_stmt->execute();
$deadlines = $deadline_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$show_view_all_deadlines = count($deadlines) > 3;
$show_limit_deadlines = array_slice($deadlines, 0, 3);

/* ================= ARCHIVED EVENTS COUNT ================= */
$archived_stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM events
    WHERE user_id = ? AND archived_at IS NOT NULL
");
$archived_stmt->bind_param("i", $user_id);
$archived_stmt->execute();
$archived_events = (int) ($archived_stmt->get_result()->fetch_assoc()['total'] ?? 0);
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
                    <p><?= $username ?></p>
                    <p><?= $org_body ?></p>
                </div>
                <div class="home-top-actions">
                    <a class="btn-primary" href="create_event.php">
                        <i class="fa-solid fa-plus"></i> Create Event
                    </a>
                </div>
            </header>

            <section class="home-content">

                <section class="home-stats-grid">
                    <?php
                    $stats = [
                        [
                            'count' => $total_active_events,
                            'label' => 'Active Events',
                            'icon' => 'fa-regular fa-calendar-days',
                            'link' => 'my_events.php'
                        ],
                        [
                            'count' => count($deadlines),
                            'label' => 'Upcoming Deadlines',
                            'icon' => 'fa-solid fa-list-check',
                            'link' => 'requirements.php#req-deadlines'
                        ],
                        [
                            'count' => $overall_progress . '%',
                            'label' => 'Compliance Progress',
                            'icon' => 'fa-solid fa-circle-check',
                            'link' => 'requirements.php'
                        ],
                        [
                            'count' => $archived_events,
                            'label' => 'Archived Events',
                            'icon' => 'fa-regular fa-folder-open',
                            'link' => 'archived_events.php'
                        ]
                    ];

                    foreach ($stats as $s): ?>
                        <a href="<?= htmlspecialchars($s['link']) ?>" class="home-stat-link">
                            <article class="home-stat-card">
                                <div class="home-stat-header">
                                    <div class="home-stat-icon"><i class="<?= htmlspecialchars($s['icon']) ?>"></i></div>
                                </div>
                                <p class="home-stat-value"><?= htmlspecialchars((string) $s['count']) ?></p>
                                <p class="home-stat-label"><?= htmlspecialchars($s['label']) ?></p>
                            </article>
                        </a>
                    <?php endforeach; ?>
                </section>

                <section class="home-section">
                    <header class="home-section-header">
                        <h2 class="home-section-title">Active Events</h2>
                        <?php if ($show_view_all): ?>
                            <a href="my_events.php" class="btn-secondary btn-smaller">View All</a>
                        <?php endif; ?>
                    </header>

                    <ul class="events-table">
                        <?php if (!empty($show_limit_events)): ?>
                            <?php foreach ($show_limit_events as $event):
                                $total_docs = (int) ($event['docs_total'] ?? 0);
                                $uploaded_docs = (int) ($event['docs_uploaded'] ?? 0);
                                $progress = $total_docs > 0 ? round(($uploaded_docs / $total_docs) * 100) : 0;
                                $progress_color = $progress >= 75 ? '#2e7d32' : ($progress >= 40 ? '#f9a825' : '#d32f2f');
                            ?>
                                <li>
                                    <a class="event-card-container" href="view_event.php?id=<?= (int) $event['event_id'] ?>">
                                        <article class="event-card">
                                            <div class="event-main">
                                                <div class="event-info">
                                                    <div class="event-title"><?= htmlspecialchars($event['event_name'] ?? '') ?></div>
                                                    <div class="event-sub">
                                                        <?= htmlspecialchars($event['venue_platform'] ?? 'No venue set') ?> •
                                                        <?php if (!empty($event['start_datetime'])): ?>
                                                            <time datetime="<?= htmlspecialchars($event['start_datetime']) ?>">
                                                                <?= date("F j, g:i A", strtotime($event['start_datetime'])) ?>
                                                            </time>
                                                        <?php else: ?>
                                                            <span>No schedule set</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>

                                                <div class="event-progress">
                                                    <div class="home-progress-bar-mini">
                                                        <div
                                                            class="home-progress-fill-mini"
                                                            style="width: <?= $progress ?>%; background: <?= $progress_color ?>;">
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
                                </li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li>No active events after <?= date("F j") ?>.</li>
                        <?php endif; ?>
                    </ul>
                </section>

                <section class="home-section">
                    <header class="home-section-header">
                        <h2 class="home-section-title">Upcoming Deadlines</h2>
                        <?php if ($show_view_all_deadlines): ?>
                            <a href="requirements.php#req-deadlines" class="btn-secondary btn-smaller">View All</a>
                        <?php endif; ?>
                    </header>

                    <ul>
                        <?php if (!empty($show_limit_deadlines)): ?>
                            <?php foreach ($show_limit_deadlines as $d): ?>
                                <li>
                                    <a class="req-card" href="view_event.php?id=<?= (int) $d['event_id'] ?>">
                                        <div class="req-item">
                                            <div class="req-title">
                                                <?= htmlspecialchars($d['req_name'] ?? '') ?>
                                                <?php if (!empty($d['req_desc'])): ?>
                                                    <span class="tooltip-icon">
                                                        <i class="fa-regular fa-circle-question"></i>
                                                        <span class="tooltip-text"><?= htmlspecialchars($d['req_desc']) ?></span>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="req-sub">
                                                From <?= htmlspecialchars($d['event_name'] ?? '') ?> •
                                                <strong>
                                                    <time datetime="<?= htmlspecialchars($d['deadline']) ?>">
                                                        <?= date("F j, g:i A", strtotime($d['deadline'])) ?>
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