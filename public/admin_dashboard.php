<?php
session_start();
require_once "../app/database.php";
require_once "../app/security_headers.php";
require_once "../app/query_builder_functions.php";
send_security_headers();

if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit();
}

if (($_SESSION["role"] ?? "") !== "admin") {
    popup_error("Access denied.");
}

$admin_name = htmlspecialchars($_SESSION["username"] ?? "Admin", ENT_QUOTES, "UTF-8");

// ========== SUMMARY COUNTS ==========
$summarySql = "
    SELECT
        COUNT(DISTINCT e.event_id) AS total_events,
        COUNT(DISTINCT u.user_id) AS total_users,
        SUM(CASE WHEN e.event_status = 'Pending Review' THEN 1 ELSE 0 END) AS pending_approvals,
        ROUND(AVG(CASE 
            WHEN e.docs_total > 0 THEN (e.docs_uploaded / e.docs_total) * 100 
            ELSE 0 
        END), 0) AS avg_compliance
    FROM events e
    LEFT JOIN users u ON e.user_id = u.user_id
    WHERE e.archived_at IS NULL
      AND e.is_system_event = 0
      AND u.role = 'user'
";
$summary = fetchOne($conn, $summarySql);

$total_events     = (int) ($summary['total_events'] ?? 0);
$total_users      = (int) ($summary['total_users'] ?? 0);
$pending_approvals = (int) ($summary['pending_approvals'] ?? 0);
$avg_compliance   = (int) ($summary['avg_compliance'] ?? 0);

// ========== OVERDUE REQUIREMENTS ==========
$overdueSql = "
    SELECT COUNT(*) AS overdue_count
    FROM event_requirements er
    INNER JOIN events e ON er.event_id = e.event_id
    WHERE e.archived_at IS NULL
      AND e.is_system_event = 0
      AND er.deadline < NOW()
      AND er.submission_status = 'Pending'
";
$overdue = fetchOne($conn, $overdueSql);
$overdue_count = (int) ($overdue['overdue_count'] ?? 0);

// ========== RECENT SUBMISSIONS ==========
$recentSql = "
    SELECT
        e.event_id,
        e.event_name,
        u.org_body AS organization,
        rt.req_name AS requirement,
        er.updated_at AS submitted_at,
        er.review_status,
        er.submission_status
    FROM event_requirements er
    INNER JOIN events e ON er.event_id = e.event_id
    INNER JOIN users u ON e.user_id = u.user_id
    INNER JOIN requirement_templates rt ON er.req_template_id = rt.req_template_id
    WHERE e.archived_at IS NULL
      AND e.is_system_event = 0
    ORDER BY er.updated_at DESC
    LIMIT 5
";
$recent = fetchAll($conn, $recentSql);

// ========== UPCOMING DEADLINES (events starting soon) ==========
$deadlinesSql = "
    SELECT
        e.event_id,
        e.event_name,
        u.org_body AS organization,
        e.event_status,
        ed.start_datetime AS deadline_date
    FROM events e
    INNER JOIN users u ON e.user_id = u.user_id
    LEFT JOIN event_dates ed ON e.event_id = ed.event_id
    WHERE e.archived_at IS NULL
      AND e.is_system_event = 0
      AND ed.start_datetime IS NOT NULL
      AND ed.start_datetime >= NOW()
    ORDER BY ed.start_datetime ASC
    LIMIT 4
";
$deadlines = fetchAll($conn, $deadlinesSql);

// ========== USER MANAGEMENT ==========
$usersSql = "
    SELECT
        user_id,
        user_name,
        user_email,
        role,
        user_reg_date
    FROM users
    WHERE role = 'user'
    ORDER BY user_reg_date DESC
    LIMIT 4
";
$users = fetchAll($conn, $usersSql);
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
        /* Dashboard-specific styles */
        .dashboard-stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
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
            gap: 16px;
            transition: all 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.10);
        }

        .stat-icon {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            background: rgba(75, 0, 20, 0.08);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            color: var(--burgundy);
            flex-shrink: 0;
        }

        .stat-number {
            font-size: 30px;
            font-weight: 800;
            color: var(--burgundy);
            line-height: 1;
            margin-bottom: 4px;
        }

        .stat-label {
            font-size: 13px;
            color: var(--text-secondary);
            font-weight: 500;
        }
        .overdue-alert {
            background: linear-gradient(135deg, #fdf2f2 0%, #fff8f0 50%, #ffffff 100%);
            border: 1px solid rgba(220, 38, 38, 0.15);
            padding: 16px 20px;
            border-radius: var(--radius);
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            box-shadow: 0 2px 8px rgba(220, 38, 38, 0.06);
        }

        .overdue-alert strong {
            color: rgb(75, 0, 20);
            font-size: 18px;
        }
        .overdue-alert .btn-outline {
            background: transparent;
            border: 1px solid rgb(75, 0, 20);
            color: rgb(75, 0, 20);
            padding: 8px 20px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.2s;
        }
        .overdue-alert .btn-outline:hover {
            background: #dc2626;
            color: white;
        }
        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 24px;
            margin-bottom: 24px;
        }
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
            font-size: 18px;
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
        .card-header a:hover {
            text-decoration: underline;
        }

        /* Table styles with clean borders */
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        .data-table th,
        .data-table td {
            padding: 12px 16px;
            text-align: left;
            border-bottom: 1px solid var(--border);
            font-size: 14px;
            vertical-align: middle;  /* ← add this line */
        }
        .data-table th {
            background: #fafafa;
            font-weight: 700;
            color: var(--text-secondary);
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 0.5px;
            border-bottom: 2px solid var(--border);
        }
        .data-table tr:last-child td {
            border-bottom: none;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-pending-review {
            background: rgba(245, 158, 11, 0.1);
            color: #f59e0b;
        }
        .status-approved {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
        }
        .status-needs-revision {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
        }
        .status-completed {
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
        }
        .status-pending {
            background: rgba(156, 163, 175, 0.1);
            color: #6b7280;
        }
        .action-buttons {
            display: flex;
            gap: 8px;
        }
        .btn-sm {
            padding: 4px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
        }
        .btn-approve {
            background: #10b981;
            color: white;
        }
        .btn-reject {
            background: #ef4444;
            color: white;
        }
        .btn-view {
            background: #f1f5f9;
            color: var(--text-secondary);
        }
        .btn-sm:hover {
            transform: translateY(-1px);
            filter: brightness(0.95);
        }

        /* Deadlines list */
        .deadline-item {
            padding: 12px 16px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
        }
        .deadline-item:last-child {
            border-bottom: none;
        }
        .deadline-info {
            flex: 1;
        }
        .deadline-title {
            font-weight: 600;
            color: var(--burgundy);
            margin-bottom: 4px;
        }
        .deadline-meta {
            font-size: 12px;
            color: var(--text-secondary);
        }
        .deadline-date {
            font-size: 12px;
            font-weight: 600;
            color: #dc2626;
        }

        /* User list */
        .user-item {
            padding: 12px 16px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
        }
        .user-item:last-child {
            border-bottom: none;
        }
        .user-info {
            flex: 1;
        }
        .user-name {
            font-weight: 600;
            color: var(--burgundy);
        }
        .user-role {
            font-size: 12px;
            color: var(--gold);
        }
        .user-login {
            font-size: 11px;
            color: var(--text-secondary);
        }
        .user-status {
            font-size: 12px;
            padding: 2px 8px;
            border-radius: 12px;
            background: #d1fae5;
            color: #065f46;
        }

        .events-table td {
            vertical-align: middle;
            padding: 16px;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
            align-items: center;
            justify-content: flex-start;
        }

        .action-buttons .btn-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
        }

        .data-table td {
            vertical-align: middle;  /* add this */
        }

        .data-table td:last-child {  /* the Actions column */
            vertical-align: middle;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
            align-items: center;  /* ensures buttons sit on the same baseline */
        }
        @media (max-width: 1024px) {
            .dashboard-stats {
                grid-template-columns: repeat(2, 1fr);
            }
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }
        @media (max-width: 640px) {
            .dashboard-stats {
                grid-template-columns: 1fr;
            }
            .data-table th, .data-table td {
                padding: 8px 12px;
            }
            .action-buttons {
                flex-direction: column;
                gap: 4px;
            }
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

        <!-- Stats Cards with Icons -->
        <div class="dashboard-stats">
            <div class="stat-card">
                <div class="stat-icon"><i class="fa-regular fa-calendar-days"></i></div>
                <div>
                    <div class="stat-number"><?= $total_events ?></div>
                    <div class="stat-label">Total Events</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fa-solid fa-users"></i></div>
                <div>
                    <div class="stat-number"><?= $total_users ?></div>
                    <div class="stat-label">Total Users</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fa-solid fa-hourglass-half"></i></div>
                <div>
                    <div class="stat-number"><?= $pending_approvals ?></div>
                    <div class="stat-label">Pending Approvals</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fa-solid fa-chart-line"></i></div>
                <div>
                    <div class="stat-number"><?= $avg_compliance ?>%</div>
                    <div class="stat-label">Overall Compliance</div>
                </div>
            </div>
        </div>

        <!-- Overdue Alert -->
        <?php if ($overdue_count > 0): ?>
        <div class="overdue-alert">
            <div>
                <strong><?= $overdue_count ?> requirements are overdue</strong>
                <span style="margin-left: 8px; font-size: 14px;">Action needed</span>
            </div>
            <a href="admin_events.php?filter=overdue" class="btn-outline">View Details</a>
        </div>
        <?php endif; ?>

        <!-- Main Content Grid -->
        <div class="dashboard-grid">
            <!-- Left Column: Recent Submissions Table -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h2>Recent Submissions</h2>
                    <a href="admin_events.php">View All →</a>
                </div>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Event</th>
                                <th>Organization</th>
                                <th>Requirement</th>
                                <th>Submitted</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($recent)): ?>
                                <?php foreach ($recent as $row): ?>
                                    <?php
                                    // Determine display status
                                    $status_text = '';
                                    if (!empty($row['review_status']) && $row['review_status'] !== 'Not Reviewed') {
                                        $status_text = $row['review_status'];
                                    } else {
                                        $status_text = $row['submission_status'] === 'Uploaded' ? 'Pending Review' : 'Pending Upload';
                                    }
                                    $status_class = '';
                                    switch ($status_text) {
                                        case 'Pending Review': $status_class = 'status-pending-review'; break;
                                        case 'Approved': $status_class = 'status-approved'; break;
                                        case 'Needs Revision': $status_class = 'status-needs-revision'; break;
                                        case 'Completed': $status_class = 'status-completed'; break;
                                        default: $status_class = 'status-pending';
                                    }
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['event_name']) ?></td>
                                        <td><?= htmlspecialchars($row['organization']) ?></td>
                                        <td><?= htmlspecialchars($row['requirement']) ?></td>
                                        <td><?= date('M j, g:i a', strtotime($row['submitted_at'])) ?></td>
                                        <td><span class="status-badge <?= $status_class ?>"><?= $status_text ?></span></td>
                                        <td class="action-buttons">
                                            <?php if ($status_text === 'Pending Review'): ?>
                                                <a href="manage_event.php?id=<?= $row['event_id'] ?>&action=approve" class="btn-sm btn-approve">Approve</a>
                                                <a href="manage_event.php?id=<?= $row['event_id'] ?>&action=reject" class="btn-sm btn-reject">Reject</a>
                                            <?php endif; ?>
                                            <a href="manage_event.php?id=<?= $row['event_id'] ?>" class="btn-sm btn-view">View</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="6" style="text-align: center;">No recent submissions</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Right Column: Upcoming Deadlines + User Management -->
            <div>
                <!-- Upcoming Deadlines -->
                <div class="dashboard-card" style="margin-bottom: 24px;">
                    <div class="card-header">
                        <h2>Upcoming Deadlines</h2>
                        <a href="admin_events.php?filter=upcoming">View All →</a>
                    </div>
                    <div>
                        <?php if (!empty($deadlines)): ?>
                            <?php foreach ($deadlines as $dl): ?>
                                <div class="deadline-item">
                                    <div class="deadline-info">
                                        <div class="deadline-title"><?= htmlspecialchars($dl['event_name']) ?></div>
                                        <div class="deadline-meta"><?= htmlspecialchars($dl['organization']) ?> • <?= htmlspecialchars($dl['event_status']) ?></div>
                                    </div>
                                    <div class="deadline-date">
                                        Starts <?= date('M j', strtotime($dl['deadline_date'])) ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div style="padding: 20px; text-align: center; color: var(--text-secondary);">No upcoming deadlines</div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- User Management -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h2>User Management</h2>
                        <a href="admin_users.php">View All →</a>
                    </div>
                    <div>
                        <?php if (!empty($users)): ?>
                            <?php foreach ($users as $user): ?>
                                <div class="user-item">
                                    <div class="user-info">
                                        <div class="user-name"><?= htmlspecialchars($user['user_name']) ?></div>
                                        <div class="user-role"><?= htmlspecialchars($user['role']) ?></div>
                                        <div class="user-login">Registered: <?= date('M j, Y', strtotime($user['user_reg_date'])) ?></div>
                                    </div>
                                    <div class="user-status">Active</div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div style="padding: 20px; text-align: center; color: var(--text-secondary);">No users found</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <?php include 'assets/includes/footer.php'; ?>
    </main>
</div>

<script src="../app/script/layout.js?v=1"></script>
</body>
</html>