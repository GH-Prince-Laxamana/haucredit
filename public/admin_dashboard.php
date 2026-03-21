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
    return strtolower(str_replace(' ', '-', trim($status)));
}

function formatEventDate(?string $datetime): string
{
    if (empty($datetime)) {
        return "No schedule set";
    }

    return date("M j, Y g:i A", strtotime($datetime));
}

function getUserInitial(string $name): string
{
    $name = trim($name);
    return $name !== '' ? strtoupper(substr($name, 0, 1)) : 'U';
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
      AND e.event_status IN ('Needs Revision')
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
      AND e.event_status IN ('Pending Review')
      AND ed.start_datetime IS NOT NULL
    ORDER BY ed.start_datetime ASC
    LIMIT 5
";
$review_deadlines = fetchAll($conn, $fetchReviewDeadlinesSql);

$pending_review_count = (int) ($statusCounts['pending_review_count'] ?? 0);
$needs_revision_count = (int) ($statusCounts['needs_revision_count'] ?? 0);
$approved_count = (int) ($statusCounts['approved_count'] ?? 0);
$completed_count = (int) ($statusCounts['completed_count'] ?? 0);
$total_users = (int) ($userCountRow['total_users'] ?? 0);
$review_queue_count = (int) ($reviewDeadlineRow['total'] ?? 0);
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
                <!-- Stats -->
                <section class="dashboard-stats">
                    <article class="stat-card">
                        <div class="stat-icon"><i class="fa-solid fa-hourglass-half"></i></div>
                        <div>
                            <div class="stat-number"><?= $pending_review_count ?></div>
                            <div class="stat-label">Pending Review</div>
                        </div>
                    </article>

                    <article class="stat-card">
                        <div class="stat-icon"><i class="fa-solid fa-rotate-right"></i></div>
                        <div>
                            <div class="stat-number"><?= $needs_revision_count ?></div>
                            <div class="stat-label">Needs Revision</div>
                        </div>
                    </article>

                    <article class="stat-card">
                        <div class="stat-icon"><i class="fa-solid fa-circle-check"></i></div>
                        <div>
                            <div class="stat-number"><?= $approved_count ?></div>
                            <div class="stat-label">Approved</div>
                        </div>
                    </article>

                    <article class="stat-card">
                        <div class="stat-icon"><i class="fa-solid fa-flag-checkered"></i></div>
                        <div>
                            <div class="stat-number"><?= $completed_count ?></div>
                            <div class="stat-label">Completed</div>
                        </div>
                    </article>

                    <article class="stat-card">
                        <div class="stat-icon"><i class="fa-solid fa-users"></i></div>
                        <div>
                            <div class="stat-number"><?= $total_users ?></div>
                            <div class="stat-label">Users</div>
                        </div>
                    </article>

                    <article class="stat-card">
                        <div class="stat-icon"><i class="fa-solid fa-calendar-exclamation"></i></div>
                        <div>
                            <div class="stat-number"><?= $review_queue_count ?></div>
                            <div class="stat-label">Review Queue</div>
                        </div>
                    </article>
                </section>

                <?php if ($needs_revision_count > 0): ?>
                    <section class="overdue-alert">
                        <div>
                            <strong><?= $needs_revision_count ?> event<?= $needs_revision_count !== 1 ? 's' : '' ?> need
                                attention</strong>
                            <span class="overdue-alert-subtext">Events needing revision are waiting for admin
                                action.</span>
                        </div>
                        <a href="admin_events.php?status=Needs+Revision" class="btn-outline">Open Queue</a>
                    </section>
                <?php endif; ?>

                <section class="dashboard-grid">
                    <!-- Left column -->
                    <div>
                        <section class="dashboard-card">
                            <header class="card-header">
                                <h2>Recent Event Submissions</h2>
                                <a href="admin_events.php">View All →</a>
                            </header>

                            <div class="recent-events-list">
                                <?php if (!empty($recent_events)): ?>
                                    <?php foreach ($recent_events as $event): ?>
                                        <?php
                                        $total_docs = (int) ($event['docs_total'] ?? 0);
                                        $uploaded_docs = (int) ($event['docs_uploaded'] ?? 0);
                                        $progress = $total_docs > 0 ? round(($uploaded_docs / $total_docs) * 100) : 0;
                                        $status_class = normalizeStatusClass($event['event_status'] ?? 'Pending Review');
                                        ?>
                                        <a class="recent-event-item"
                                            href="admin_manage_event.php?id=<?= (int) $event['event_id'] ?>">
                                            <div class="recent-event-info">
                                                <div class="recent-event-title"><?= htmlspecialchars($event['event_name']) ?>
                                                </div>
                                                <div class="recent-event-meta">
                                                    <?= htmlspecialchars($event['user_name']) ?> •
                                                    <?= htmlspecialchars($event['org_body']) ?> •
                                                    <?= htmlspecialchars($event['venue_platform'] ?? 'No venue set') ?>
                                                </div>

                                                <div class="progress-mini">
                                                    <div class="progress-bar-mini">
                                                        <div class="progress-fill-mini" style="width: <?= $progress ?>%;"></div>
                                                    </div>
                                                    <span><?= $uploaded_docs ?>/<?= $total_docs ?></span>
                                                </div>
                                            </div>

                                            <div class="recent-event-side">
                                                <span class="status-badge-small status-<?= htmlspecialchars($status_class) ?>">
                                                    <?= htmlspecialchars($event['event_status']) ?>
                                                </span>
                                                <small><?= formatEventDate($event['start_datetime'] ?? null) ?></small>
                                            </div>
                                        </a>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="empty-state-small">
                                        <p>No recent event submissions.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </section>

                        <section class="dashboard-card attention-section">
                            <header class="card-header">
                                <h2>Events Requiring Attention</h2>
                                <a href="admin_events.php?status=Needs+Revision">Open Queue →</a>
                            </header>

                            <div class="attention-events">
                                <?php if (!empty($attention_events)): ?>
                                    <?php foreach ($attention_events as $event): ?>
                                        <?php
                                        $status = $event['event_status'] ?? 'Pending Review';
                                        $status_class = normalizeStatusClass($status);
                                        $card_class = $status === 'Needs Revision' ? 'urgent' : 'warning';
                                        ?>
                                        <a class="attention-card <?= $card_class ?>"
                                            href="admin_manage_event.php?id=<?= (int) $event['event_id'] ?>">
                                            <div class="attention-icon">
                                                <i
                                                    class="fa-solid <?= $status === 'Needs Revision' ? 'fa-triangle-exclamation' : 'fa-hourglass-half' ?>"></i>
                                            </div>

                                            <div class="attention-content">
                                                <h4><?= htmlspecialchars($event['event_name']) ?></h4>
                                                <p>
                                                    <?= htmlspecialchars($event['user_name']) ?> •
                                                    <?= htmlspecialchars($event['org_body']) ?>
                                                </p>
                                                <small>Starts: <?= formatEventDate($event['start_datetime'] ?? null) ?></small>
                                            </div>

                                            <div class="attention-meta">
                                                <span class="status-badge-small status-<?= htmlspecialchars($status_class) ?>">
                                                    <?= htmlspecialchars($status) ?>
                                                </span>
                                            </div>
                                        </a>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="empty-state-small">
                                        <p>No events currently require attention.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </section>
                    </div>

                    <!-- Right column -->
                    <div>
                        <section class="dashboard-card" style="margin-bottom: 24px;">
                            <header class="card-header">
                                <h2>Upcoming Review Deadlines</h2>
                                <a href="admin_events.php?status=Pending+Review">View All →</a>
                            </header>

                            <div>
                                <?php if (!empty($review_deadlines)): ?>
                                    <?php foreach ($review_deadlines as $item): ?>
                                        <?php $status_class = normalizeStatusClass($item['event_status'] ?? 'Pending Review'); ?>
                                        <a class="deadline-item"
                                            href="admin_manage_event.php?id=<?= (int) $item['event_id'] ?>">
                                            <div class="deadline-info">
                                                <div class="deadline-title"><?= htmlspecialchars($item['event_name']) ?></div>
                                                <div class="deadline-meta">
                                                    <?= htmlspecialchars($item['user_name']) ?> •
                                                    <?= htmlspecialchars($item['org_body']) ?>
                                                </div>
                                            </div>
                                            <div class="deadline-side">
                                                <span class="status-badge-small status-<?= htmlspecialchars($status_class) ?>">
                                                    <?= htmlspecialchars($item['event_status']) ?>
                                                </span>
                                                <div class="deadline-date">
                                                    <?= formatEventDate($item['start_datetime'] ?? null) ?></div>
                                            </div>
                                        </a>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="empty-state-small">
                                        <p>No upcoming review deadlines.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </section>

                        <section class="dashboard-card">
                            <header class="card-header">
                                <h2>Users Preview</h2>
                                <a href="admin_users.php">View All →</a>
                            </header>

                            <div class="users-grid">
                                <?php if (!empty($users_preview)): ?>
                                    <?php foreach ($users_preview as $user): ?>
                                        <a class="user-card" href="admin_users.php">
                                            <div class="user-avatar-initial">
                                                <?= htmlspecialchars(getUserInitial($user['user_name'] ?? 'U')) ?>
                                            </div>

                                            <div class="user-info">
                                                <div class="user-name"><?= htmlspecialchars($user['user_name']) ?></div>
                                                <div class="user-email"><?= htmlspecialchars($user['user_email']) ?></div>
                                                <div class="user-org"><?= htmlspecialchars($user['org_body']) ?></div>
                                                <small>
                                                    <?= (int) $user['total_events'] ?>
                                                    event<?= ((int) $user['total_events'] !== 1 ? 's' : '') ?> •
                                                    Registered <?= date('M j, Y', strtotime($user['user_reg_date'])) ?>
                                                </small>
                                            </div>
                                        </a>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="empty-state-small">
                                        <p>No users found.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </section>
                    </div>
                </section>

                <?php include 'assets/includes/footer.php'; ?>
            </section>
        </main>
    </div>

    <script src="../app/script/layout.js?v=1"></script>
</body>

</html>