<?php
session_start();
require_once "../app/database.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit();
}

if (($_SESSION["role"] ?? "") !== "admin") {
    popup_error("Access denied.");
}

$admin_name = htmlspecialchars($_SESSION["username"] ?? "Admin", ENT_QUOTES, "UTF-8");

/* ================= HELPERS ================= */
function normalizeStatusClass(string $status): string
{
    return strtolower(str_replace(' ', '-', $status));
}

/* ================= SUMMARY COUNTS ================= */
$countStatusesSql = "
    SELECT
        SUM(CASE WHEN event_status = 'Pending Review' THEN 1 ELSE 0 END) AS pending_review_count,
        SUM(CASE WHEN event_status = 'Needs Revision' THEN 1 ELSE 0 END) AS needs_revision_count,
        SUM(CASE WHEN event_status = 'Approved' THEN 1 ELSE 0 END) AS approved_count,
        SUM(CASE WHEN event_status = 'Completed' THEN 1 ELSE 0 END) AS completed_count
    FROM events
    WHERE archived_at IS NULL
      AND is_system_event = 0
";

$statusCounts = fetchOne($conn, $countStatusesSql);

$countUsersSql = "
    SELECT COUNT(*) AS total_users
    FROM users
    WHERE role = 'user'
";
$userCountRow = fetchOne($conn, $countUsersSql);

$countUpcomingReviewSql = "
    SELECT COUNT(*) AS total
    FROM events e
    LEFT JOIN event_dates ed ON e.event_id = ed.event_id
    WHERE e.archived_at IS NULL
      AND e.is_system_event = 0
      AND e.event_status IN ('Pending Review', 'Needs Revision')
      AND ed.start_datetime IS NOT NULL
      AND ed.start_datetime >= NOW()
";
$reviewDeadlineRow = fetchOne($conn, $countUpcomingReviewSql);

/* ================= RECENT EVENT SUBMISSIONS ================= */
$fetchRecentEventsSql = "
    SELECT
        e.event_id,
        e.event_name,
        e.event_status,
        e.docs_total,
        e.docs_uploaded,
        e.created_at,
        e.updated_at,
        e.organizing_body,

        u.user_name,
        u.org_body,

        ed.start_datetime,
        el.venue_platform
    FROM events e
    INNER JOIN users u
        ON e.user_id = u.user_id
    LEFT JOIN event_dates ed
        ON e.event_id = ed.event_id
    LEFT JOIN event_location el
        ON e.event_id = el.event_id
    WHERE e.archived_at IS NULL
      AND e.is_system_event = 0
      AND e.event_status IN ('Pending Review', 'Needs Revision', 'Approved')
    ORDER BY e.created_at DESC
    LIMIT 5
";

$recent_events = fetchAll($conn, $fetchRecentEventsSql);

/* ================= EVENTS REQUIRING ATTENTION ================= */
$fetchAttentionEventsSql = "
    SELECT
        e.event_id,
        e.event_name,
        e.event_status,
        e.docs_total,
        e.docs_uploaded,
        e.updated_at,

        u.user_name,
        u.org_body,

        ed.start_datetime
    FROM events e
    INNER JOIN users u
        ON e.user_id = u.user_id
    LEFT JOIN event_dates ed
        ON e.event_id = ed.event_id
    WHERE e.archived_at IS NULL
      AND e.is_system_event = 0
      AND e.event_status IN ('Pending Review', 'Needs Revision')
    ORDER BY
        CASE e.event_status
            WHEN 'Needs Revision' THEN 1
            WHEN 'Pending Review' THEN 2
            ELSE 3
        END,
        CASE WHEN ed.start_datetime IS NULL THEN 1 ELSE 0 END,
        ed.start_datetime ASC,
        e.updated_at ASC
    LIMIT 6
";

$attention_events = fetchAll($conn, $fetchAttentionEventsSql);

/* ================= USERS PREVIEW ================= */
$fetchUsersPreviewSql = "
    SELECT
        u.user_id,
        u.user_name,
        u.user_email,
        u.org_body,
        u.user_reg_date,
        COUNT(e.event_id) AS total_events
    FROM users u
    LEFT JOIN events e
        ON u.user_id = e.user_id
       AND e.archived_at IS NULL
       AND e.is_system_event = 0
    WHERE u.role = 'user'
    GROUP BY u.user_id, u.user_name, u.user_email, u.org_body, u.user_reg_date
    ORDER BY u.user_reg_date DESC
    LIMIT 5
";

$users_preview = fetchAll($conn, $fetchUsersPreviewSql);

/* ================= REVIEW DEADLINES ================= */
$fetchReviewDeadlinesSql = "
    SELECT
        e.event_id,
        e.event_name,
        e.event_status,
        u.user_name,
        u.org_body,
        ed.start_datetime
    FROM events e
    INNER JOIN users u
        ON e.user_id = u.user_id
    LEFT JOIN event_dates ed
        ON e.event_id = ed.event_id
    WHERE e.archived_at IS NULL
      AND e.is_system_event = 0
      AND e.event_status IN ('Pending Review', 'Needs Revision')
      AND ed.start_datetime IS NOT NULL
    ORDER BY ed.start_datetime ASC
    LIMIT 5
";

$review_deadlines = fetchAll($conn, $fetchReviewDeadlinesSql);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Admin Dashboard - HAUCREDIT</title>
    <link rel="stylesheet" href="assets/styles/layout.css" />
    <link rel="stylesheet" href="assets/styles/home_styles.css" />
    <link rel="stylesheet" href="assets/styles/admin_dashboard.css" />
</head>

<body>
    <div class="app">
        <div class="sidebar-overlay" id="sidebarOverlay" hidden></div>

        <?php include 'assets/includes/admin_nav.php'; ?>

        <main class="main">
            <header class="topbar">
                <button class="hamburger" id="menuBtn" type="button" aria-label="Open menu">☰</button>

                <div class="title-wrap">
                    <h1>Admin Dashboard</h1>
                    <p>Welcome back, <?= $admin_name ?></p>
                </div>
            </header>

            <section class="home-content">

                <section class="home-stats-grid">
                    <article class="home-stat-card">
                        <div class="home-stat-header">
                            <div class="home-stat-icon"><i class="fa-solid fa-hourglass-half"></i></div>
                        </div>
                        <p class="home-stat-value"><?= (int) ($statusCounts['pending_review_count'] ?? 0) ?></p>
                        <p class="home-stat-label">Pending Review</p>
                    </article>

                    <article class="home-stat-card">
                        <div class="home-stat-header">
                            <div class="home-stat-icon"><i class="fa-solid fa-rotate-right"></i></div>
                        </div>
                        <p class="home-stat-value"><?= (int) ($statusCounts['needs_revision_count'] ?? 0) ?></p>
                        <p class="home-stat-label">Needs Revision</p>
                    </article>

                    <article class="home-stat-card">
                        <div class="home-stat-header">
                            <div class="home-stat-icon"><i class="fa-solid fa-circle-check"></i></div>
                        </div>
                        <p class="home-stat-value"><?= (int) ($statusCounts['approved_count'] ?? 0) ?></p>
                        <p class="home-stat-label">Approved</p>
                    </article>

                    <article class="home-stat-card">
                        <div class="home-stat-header">
                            <div class="home-stat-icon"><i class="fa-solid fa-flag-checkered"></i></div>
                        </div>
                        <p class="home-stat-value"><?= (int) ($statusCounts['completed_count'] ?? 0) ?></p>
                        <p class="home-stat-label">Completed</p>
                    </article>

                    <article class="home-stat-card">
                        <div class="home-stat-header">
                            <div class="home-stat-icon"><i class="fa-solid fa-users"></i></div>
                        </div>
                        <p class="home-stat-value"><?= (int) ($userCountRow['total_users'] ?? 0) ?></p>
                        <p class="home-stat-label">Users</p>
                    </article>

                    <article class="home-stat-card">
                        <div class="home-stat-header">
                            <div class="home-stat-icon"><i class="fa-solid fa-calendar-exclamation"></i></div>
                        </div>
                        <p class="home-stat-value"><?= (int) ($reviewDeadlineRow['total'] ?? 0) ?></p>
                        <p class="home-stat-label">Review Queue</p>
                    </article>
                </section>

                <section class="home-section">
                    <header class="home-section-header">
                        <h2 class="home-section-title">Recent Event Submissions</h2>
                        <a href="admin_events.php" class="btn-secondary btn-smaller">View All</a>
                    </header>

                    <ul class="events-table">
                        <?php if (!empty($recent_events)): ?>
                            <?php foreach ($recent_events as $event): ?>
                                <?php
                                $total_docs = (int) ($event['docs_total'] ?? 0);
                                $uploaded_docs = (int) ($event['docs_uploaded'] ?? 0);
                                $progress = $total_docs > 0 ? round(($uploaded_docs / $total_docs) * 100) : 0;
                                $status_class = normalizeStatusClass($event['event_status'] ?? 'Pending Review');
                                ?>
                                <li>
                                    <a class="event-card-container" href="manage_event.php?id=<?= (int) $event['event_id'] ?>">
                                        <article class="event-card">
                                            <div class="event-main">
                                                <div class="event-info">
                                                    <div class="event-title"><?= htmlspecialchars($event['event_name']) ?></div>
                                                    <div class="event-sub">
                                                        <?= htmlspecialchars($event['user_name']) ?> •
                                                        <?= htmlspecialchars($event['org_body']) ?>
                                                    </div>
                                                    <div class="event-sub">
                                                        <?= htmlspecialchars($event['venue_platform'] ?? 'No venue set') ?> •
                                                        <?php if (!empty($event['start_datetime'])): ?>
                                                            <?= date("F j, g:i A", strtotime($event['start_datetime'])) ?>
                                                        <?php else: ?>
                                                            No schedule set
                                                        <?php endif; ?>
                                                    </div>
                                                </div>

                                                <div class="event-progress">
                                                    <span
                                                        class="home-progress-text"><?= $uploaded_docs ?>/<?= $total_docs ?></span>
                                                </div>
                                            </div>

                                            <span class="home-status-badge <?= htmlspecialchars($status_class) ?>">
                                                <?= htmlspecialchars($event['event_status']) ?>
                                            </span>
                                        </article>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li>No recent event submissions.</li>
                        <?php endif; ?>
                    </ul>
                </section>

                <section class="home-section">
                    <header class="home-section-header">
                        <h2 class="home-section-title">Events Requiring Attention</h2>
                        <a href="admin_events.php?filter=attention" class="btn-secondary btn-smaller">Open Queue</a>
                    </header>

                    <ul class="events-table">
                        <?php if (!empty($attention_events)): ?>
                            <?php foreach ($attention_events as $event): ?>
                                <?php $status_class = normalizeStatusClass($event['event_status'] ?? 'Pending Review'); ?>
                                <li>
                                    <a class="event-card-container" href="manage_event.php?id=<?= (int) $event['event_id'] ?>">
                                        <article class="event-card">
                                            <div class="event-main">
                                                <div class="event-info">
                                                    <div class="event-title"><?= htmlspecialchars($event['event_name']) ?></div>
                                                    <div class="event-sub">
                                                        <?= htmlspecialchars($event['user_name']) ?> •
                                                        <?= htmlspecialchars($event['org_body']) ?>
                                                    </div>
                                                    <div class="event-sub">
                                                        Starts:
                                                        <?php if (!empty($event['start_datetime'])): ?>
                                                            <?= date("F j, Y g:i A", strtotime($event['start_datetime'])) ?>
                                                        <?php else: ?>
                                                            No schedule set
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>

                                            <span class="home-status-badge <?= htmlspecialchars($status_class) ?>">
                                                <?= htmlspecialchars($event['event_status']) ?>
                                            </span>
                                        </article>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li>No events currently require attention.</li>
                        <?php endif; ?>
                    </ul>
                </section>

                <section class="home-section">
                    <header class="home-section-header">
                        <h2 class="home-section-title">Users Preview</h2>
                        <a href="admin_users.php" class="btn-secondary btn-smaller">View All</a>
                    </header>

                    <ul class="events-table">
                        <?php if (!empty($users_preview)): ?>
                            <?php foreach ($users_preview as $user): ?>
                                <li>
                                    <a class="event-card-container" href="admin_users.php">
                                        <article class="event-card">
                                            <div class="event-main">
                                                <div class="event-info">
                                                    <div class="event-title"><?= htmlspecialchars($user['user_name']) ?></div>
                                                    <div class="event-sub"><?= htmlspecialchars($user['user_email']) ?></div>
                                                    <div class="event-sub">
                                                        <?= htmlspecialchars($user['org_body']) ?> •
                                                        <?= (int) $user['total_events'] ?> events
                                                    </div>
                                                </div>
                                            </div>
                                        </article>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li>No users found.</li>
                        <?php endif; ?>
                    </ul>
                </section>

                <section class="home-section">
                    <header class="home-section-header">
                        <h2 class="home-section-title">Upcoming Review Deadlines</h2>
                        <a href="admin_events.php?sort=start" class="btn-secondary btn-smaller">View All</a>
                    </header>

                    <ul>
                        <?php if (!empty($review_deadlines)): ?>
                            <?php foreach ($review_deadlines as $item): ?>
                                <?php $status_class = normalizeStatusClass($item['event_status'] ?? 'Pending Review'); ?>
                                <li>
                                    <a class="req-card" href="manage_event.php?id=<?= (int) $item['event_id'] ?>">
                                        <div class="req-item">
                                            <div class="req-title"><?= htmlspecialchars($item['event_name']) ?></div>
                                            <div class="req-sub">
                                                <?= htmlspecialchars($item['user_name']) ?> •
                                                <?= htmlspecialchars($item['org_body']) ?> •
                                                <?php if (!empty($item['start_datetime'])): ?>
                                                    <strong><?= date("F j, g:i A", strtotime($item['start_datetime'])) ?></strong>
                                                <?php else: ?>
                                                    <strong>No schedule set</strong>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <span class="status <?= htmlspecialchars($status_class) ?>">
                                            <?= htmlspecialchars($item['event_status']) ?>
                                        </span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li>No upcoming review deadlines.</li>
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