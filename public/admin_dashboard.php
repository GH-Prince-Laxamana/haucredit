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

function normalizeStatusClass(string $status): string {
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
    WHERE archived_at IS NULL AND is_system_event = 0
";
$statusCounts = fetchOne($conn, $countStatusesSql);

$countUsersSql = "SELECT COUNT(*) AS total_users FROM users WHERE role = 'user'";
$userCountRow = fetchOne($conn, $countUsersSql);

$countUpcomingReviewSql = "
    SELECT COUNT(*) AS total
    FROM events e
    LEFT JOIN event_dates ed ON e.event_id = ed.event_id
    WHERE e.archived_at IS NULL AND e.is_system_event = 0
      AND e.event_status IN ('Pending Review', 'Needs Revision')
      AND ed.start_datetime IS NOT NULL AND ed.start_datetime >= NOW()
";
$reviewDeadlineRow = fetchOne($conn, $countUpcomingReviewSql);

/* ================= RECENT EVENT SUBMISSIONS ================= */
$fetchRecentEventsSql = "
    SELECT e.event_id, e.event_name, e.event_status, e.docs_total, e.docs_uploaded,
           e.created_at, e.updated_at, e.organizing_body,
           u.user_name, u.org_body,
           ed.start_datetime, el.venue_platform
    FROM events e
    INNER JOIN users u ON e.user_id = u.user_id
    LEFT JOIN event_dates ed ON e.event_id = ed.event_id
    LEFT JOIN event_location el ON e.event_id = el.event_id
    WHERE e.archived_at IS NULL AND e.is_system_event = 0
      AND e.event_status IN ('Pending Review', 'Needs Revision', 'Approved')
    ORDER BY e.created_at DESC LIMIT 5
";
$recent_events = fetchAll($conn, $fetchRecentEventsSql);

/* ================= EVENTS REQUIRING ATTENTION ================= */
$fetchAttentionEventsSql = "
    SELECT e.event_id, e.event_name, e.event_status, e.docs_total, e.docs_uploaded,
           e.updated_at, u.user_name, u.org_body, ed.start_datetime
    FROM events e
    INNER JOIN users u ON e.user_id = u.user_id
    LEFT JOIN event_dates ed ON e.event_id = ed.event_id
    WHERE e.archived_at IS NULL AND e.is_system_event = 0
      AND e.event_status IN ('Pending Review', 'Needs Revision')
    ORDER BY
        CASE e.event_status WHEN 'Needs Revision' THEN 1 WHEN 'Pending Review' THEN 2 ELSE 3 END,
        CASE WHEN ed.start_datetime IS NULL THEN 1 ELSE 0 END,
        ed.start_datetime ASC, e.updated_at ASC
    LIMIT 6
";
$attention_events = fetchAll($conn, $fetchAttentionEventsSql);

/* ================= USERS PREVIEW ================= */
$fetchUsersPreviewSql = "
    SELECT u.user_id, u.user_name, u.user_email, u.org_body, u.user_reg_date,
           COUNT(e.event_id) AS total_events
    FROM users u
    LEFT JOIN events e ON u.user_id = e.user_id AND e.archived_at IS NULL AND e.is_system_event = 0
    WHERE u.role = 'user'
    GROUP BY u.user_id, u.user_name, u.user_email, u.org_body, u.user_reg_date
    ORDER BY u.user_reg_date DESC LIMIT 5
";
$users_preview = fetchAll($conn, $fetchUsersPreviewSql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - HAUCREDIT</title>
    <link rel="stylesheet" href="assets/styles/layout.css">
    <link rel="stylesheet" href="assets/styles/home_styles.css">
    <style>
        /* ===== STAT CARDS ===== */
        .dashboard-stats {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: white;
            border-radius: var(--radius);
            padding: 20px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 14px;
            transition: all 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.10);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: rgba(75, 0, 20, 0.08);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: var(--burgundy);
            flex-shrink: 0;
        }

        .stat-number {
            font-size: 26px;
            font-weight: 800;
            color: var(--burgundy);
            line-height: 1;
            margin-bottom: 4px;
        }

        .stat-label {
            font-size: 12px;
            color: var(--text-secondary);
            font-weight: 500;
        }

        /* ===== DASHBOARD GRID ===== */
        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 24px;
            margin-bottom: 24px;
        }

        /* ===== CARDS ===== */
        .dashboard-card {
            background: white;
            border-radius: var(--radius);
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .card-header {
            padding: 16px 20px;
            background: #fdfcfa;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-header h2 {
            font-size: 16px;
            font-weight: 700;
            color: var(--burgundy);
            margin: 0;
        }

        .card-header a {
            color: var(--gold);
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
        }

        .card-header a:hover { text-decoration: underline; }

        /* ===== EVENT ROWS ===== */
        .event-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 20px;
            border-bottom: 1px solid var(--border);
            gap: 12px;
            text-decoration: none;
            transition: background 0.15s;
        }

        .event-row:last-child { border-bottom: none; }
        .event-row:hover { background: #faf9f6; }

        .event-row-info { flex: 1; min-width: 0; }

        .event-row-title {
            font-weight: 700;
            font-size: 14px;
            color: var(--text-primary);
            margin-bottom: 3px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .event-row-meta {
            font-size: 12px;
            color: var(--text-secondary);
        }

        /* ===== STATUS BADGES ===== */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            white-space: nowrap;
            flex-shrink: 0;
        }

        .status-badge::before {
            content: '';
            width: 5px;
            height: 5px;
            border-radius: 50%;
            background: currentColor;
            opacity: 0.7;
        }

        .badge-pending-review  { background: rgba(245,158,11,0.12); color: #d97706; }
        .badge-needs-revision  { background: rgba(239,68,68,0.12);  color: #dc2626; }
        .badge-approved        { background: rgba(16,185,129,0.12); color: #059669; }
        .badge-completed       { background: rgba(59,130,246,0.12); color: #2563eb; }
        .badge-draft           { background: rgba(156,163,175,0.1); color: #6b7280; }

        /* ===== USER ROWS ===== */
        .user-row {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 20px;
            border-bottom: 1px solid var(--border);
        }

        .user-row:last-child { border-bottom: none; }

        .user-avatar {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: var(--burgundy);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 15px;
            flex-shrink: 0;
        }

        .user-row-info { flex: 1; }

        .user-row-name {
            font-weight: 700;
            font-size: 14px;
            color: var(--text-primary);
            margin-bottom: 2px;
        }

        .user-row-meta {
            font-size: 12px;
            color: var(--text-secondary);
        }

        .user-events-badge {
            font-size: 11px;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 10px;
            background: rgba(75,0,20,0.07);
            color: var(--burgundy);
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 1200px) {
            .dashboard-stats { grid-template-columns: repeat(3, 1fr); }
        }
        @media (max-width: 900px) {
            .dashboard-stats { grid-template-columns: repeat(2, 1fr); }
            .dashboard-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 480px) {
            .dashboard-stats { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="app">
    <div class="sidebar-overlay" id="sidebarOverlay" hidden></div>
    <?php include 'assets/includes/admin_nav.php'; ?>

    <main class="main">
        <div class="topbar">
            <button class="hamburger" id="menuBtn" type="button" aria-label="Open menu">☰</button>
            <div class="title-wrap">
                <h1>Admin Dashboard</h1>
                <p>Welcome back, <?= $admin_name ?></p>
            </div>
            <div class="home-top-actions">
                <a class="btn-primary" href="create_event.php">
                    <i class="fa-solid fa-plus"></i> Create Event
                </a>
            </div>
        </div>

        <!-- Stat Cards -->
        <div class="dashboard-stats">
            <div class="stat-card">
                <div class="stat-icon"><i class="fa-solid fa-hourglass-half"></i></div>
                <div>
                    <div class="stat-number"><?= (int)($statusCounts['pending_review_count'] ?? 0) ?></div>
                    <div class="stat-label">Pending Review</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fa-solid fa-rotate-right"></i></div>
                <div>
                    <div class="stat-number"><?= (int)($statusCounts['needs_revision_count'] ?? 0) ?></div>
                    <div class="stat-label">Needs Revision</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fa-solid fa-circle-check"></i></div>
                <div>
                    <div class="stat-number"><?= (int)($statusCounts['approved_count'] ?? 0) ?></div>
                    <div class="stat-label">Approved</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fa-solid fa-flag-checkered"></i></div>
                <div>
                    <div class="stat-number"><?= (int)($statusCounts['completed_count'] ?? 0) ?></div>
                    <div class="stat-label">Completed</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fa-solid fa-users"></i></div>
                <div>
                    <div class="stat-number"><?= (int)($userCountRow['total_users'] ?? 0) ?></div>
                    <div class="stat-label">Users</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fa-solid fa-bell"></i></div>
                <div>
                    <div class="stat-number"><?= (int)($reviewDeadlineRow['total'] ?? 0) ?></div>
                    <div class="stat-label">Review Queue</div>
                </div>
            </div>
        </div>

        <!-- Dashboard Grid -->
        <div class="dashboard-grid">
            <!-- Left: Recent Submissions -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h2><i class="fa-solid fa-clock-rotate-left" style="margin-right:8px;opacity:0.6;"></i>Recent Submissions</h2>
                    <a href="admin_events.php">View All →</a>
                </div>
                <?php if (!empty($recent_events)): ?>
                    <?php foreach ($recent_events as $event):
                        $status = $event['event_status'];
                        $badge = match($status) {
                            'Pending Review' => 'badge-pending-review',
                            'Needs Revision' => 'badge-needs-revision',
                            'Approved'       => 'badge-approved',
                            'Completed'      => 'badge-completed',
                            default          => 'badge-draft',
                        };
                    ?>
                    <a class="event-row" href="admin_manage_event.php?id=<?= (int)$event['event_id'] ?>">
                        <div class="event-row-info">
                            <div class="event-row-title"><?= htmlspecialchars($event['event_name']) ?></div>
                            <div class="event-row-meta">
                                <?= htmlspecialchars($event['user_name']) ?> &bull;
                                <?= htmlspecialchars($event['org_body']) ?>
                                <?php if (!empty($event['start_datetime'])): ?>
                                    &bull; <?= date('M j, Y', strtotime($event['start_datetime'])) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <span class="status-badge <?= $badge ?>"><?= htmlspecialchars($status) ?></span>
                    </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="padding:32px;text-align:center;color:var(--text-secondary);font-size:14px;">No recent submissions.</div>
                <?php endif; ?>
            </div>

            <!-- Right: Attention + Users -->
            <div>
                <!-- Events Requiring Attention -->
                <div class="dashboard-card" style="margin-bottom:20px;">
                    <div class="card-header">
                        <h2><i class="fa-solid fa-triangle-exclamation" style="margin-right:8px;opacity:0.6;"></i>Needs Attention</h2>
                        <a href="admin_events.php?status=Pending+Review">View All →</a>
                    </div>
                    <?php if (!empty($attention_events)): ?>
                        <?php foreach ($attention_events as $event):
                            $status = $event['event_status'];
                            $badge = match($status) {
                                'Pending Review' => 'badge-pending-review',
                                'Needs Revision' => 'badge-needs-revision',
                                default          => 'badge-draft',
                            };
                        ?>
                        <a class="event-row" href="admin_manage_event.php?id=<?= (int)$event['event_id'] ?>">
                            <div class="event-row-info">
                                <div class="event-row-title"><?= htmlspecialchars($event['event_name']) ?></div>
                                <div class="event-row-meta">
                                    <?= htmlspecialchars($event['org_body']) ?>
                                    <?php if (!empty($event['start_datetime'])): ?>
                                        &bull; <?= date('M j', strtotime($event['start_datetime'])) ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <span class="status-badge <?= $badge ?>"><?= htmlspecialchars($status) ?></span>
                        </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="padding:24px;text-align:center;color:var(--text-secondary);font-size:14px;">All caught up!</div>
                    <?php endif; ?>
                </div>

                <!-- Users Preview -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h2><i class="fa-solid fa-users" style="margin-right:8px;opacity:0.6;"></i>Recent Users</h2>
                        <a href="admin_users.php">View All →</a>
                    </div>
                    <?php if (!empty($users_preview)): ?>
                        <?php foreach ($users_preview as $user):
                            $initial = strtoupper(substr($user['user_name'] ?? 'U', 0, 1));
                        ?>
                        <div class="user-row">
                            <div class="user-avatar"><?= $initial ?></div>
                            <div class="user-row-info">
                                <div class="user-row-name"><?= htmlspecialchars($user['user_name']) ?></div>
                                <div class="user-row-meta"><?= htmlspecialchars($user['org_body'] ?? '—') ?></div>
                            </div>
                            <span class="user-events-badge"><?= (int)$user['total_events'] ?> events</span>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="padding:24px;text-align:center;color:var(--text-secondary);font-size:14px;">No users found.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php include 'assets/includes/footer.php'; ?>
    </main>
</div>
<script src="../app/script/layout.js?v=1"></script>
</body>
</html>