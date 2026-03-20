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

$user_id = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;
$status_filter = trim($_GET['status'] ?? 'all');
$search = trim($_GET['search'] ?? '');

if ($user_id <= 0) {
    popup_error("Invalid user.");
}

/* ================= HELPERS ================= */
function normalizeStatusClass(string $status): string {
    return strtolower(str_replace(' ', '-', $status));
}

function isValidStatusFilter(string $status): bool {
    $allowed = ['all', 'Draft', 'Pending Review', 'Needs Revision', 'Approved', 'Completed'];
    return in_array($status, $allowed, true);
}

if (!isValidStatusFilter($status_filter)) $status_filter = 'all';

/* ================= FETCH USER ================= */
$user = fetchOne($conn, "
    SELECT user_id, user_name, user_email, stud_num, org_body, role, profile_pic, user_reg_date
    FROM users WHERE user_id = ? LIMIT 1
", "i", [$user_id]);

if (!$user) popup_error("User not found.");

/* ================= USER EVENT COUNTS ================= */
$counts = fetchOne($conn, "
    SELECT
        COUNT(*) AS total_events,
        SUM(CASE WHEN event_status = 'Draft'          THEN 1 ELSE 0 END) AS draft_count,
        SUM(CASE WHEN event_status = 'Pending Review' THEN 1 ELSE 0 END) AS pending_review_count,
        SUM(CASE WHEN event_status = 'Needs Revision' THEN 1 ELSE 0 END) AS needs_revision_count,
        SUM(CASE WHEN event_status = 'Approved'       THEN 1 ELSE 0 END) AS approved_count,
        SUM(CASE WHEN event_status = 'Completed'      THEN 1 ELSE 0 END) AS completed_count
    FROM events WHERE user_id = ? AND archived_at IS NULL AND is_system_event = 0
", "i", [$user_id]);

/* ================= FETCH USER EVENTS ================= */
$fetchEventsSql = "
    SELECT e.event_id, e.event_name, e.nature, e.organizing_body, e.event_status,
           e.admin_remarks, e.docs_total, e.docs_uploaded, e.created_at, e.updated_at,
           et.activity_type_id, et.background_id,
           cat.activity_type_name AS activity_type,
           cbo.background_name AS background,
           ed.start_datetime, ed.end_datetime,
           el.venue_platform
    FROM events e
    LEFT JOIN event_type et ON e.event_id = et.event_id
    LEFT JOIN config_activity_types cat ON et.activity_type_id = cat.activity_type_id
    LEFT JOIN config_background_options cbo ON et.background_id = cbo.background_id
    LEFT JOIN event_dates ed ON e.event_id = ed.event_id
    LEFT JOIN event_location el ON e.event_id = el.event_id
    WHERE e.user_id = ? AND e.archived_at IS NULL AND e.is_system_event = 0
";

$params = [$user_id]; $types = "i";

if ($status_filter !== 'all') {
    $fetchEventsSql .= " AND e.event_status = ?";
    $params[] = $status_filter; $types .= "s";
}

if ($search !== '') {
    $fetchEventsSql .= " AND (e.event_name LIKE ? OR e.nature LIKE ? OR cat.activity_type_name LIKE ? OR cbo.background_name LIKE ? OR el.venue_platform LIKE ?)";
    $like = "%" . $search . "%";
    array_push($params, $like, $like, $like, $like, $like);
    $types .= "sssss";
}

$fetchEventsSql .= "
    ORDER BY
        CASE e.event_status WHEN 'Needs Revision' THEN 1 WHEN 'Pending Review' THEN 2 WHEN 'Approved' THEN 3 WHEN 'Completed' THEN 4 WHEN 'Draft' THEN 5 ELSE 6 END,
        CASE WHEN ed.start_datetime IS NULL THEN 1 ELSE 0 END,
        ed.start_datetime ASC, e.created_at DESC
";

$events = fetchAll($conn, $fetchEventsSql, $types, $params);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Events - HAUCREDIT</title>
    <link rel="stylesheet" href="assets/styles/layout.css">
    <link rel="stylesheet" href="assets/styles/home_styles.css">
    <style>
        /* ===== USER PROFILE CARD ===== */
        .user-profile-card {
            background: white;
            border-radius: var(--radius);
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            padding: 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .user-profile-avatar {
            width: 68px;
            height: 68px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--border);
            flex-shrink: 0;
        }

        .user-profile-info { flex: 1; }

        .user-profile-name {
            font-size: 20px;
            font-weight: 800;
            color: var(--burgundy);
            margin-bottom: 8px;
        }

        .user-profile-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 14px;
        }

        .user-meta-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            color: var(--text-secondary);
        }

        .user-meta-item i { color: var(--gold); font-size: 12px; }

        .role-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
        }

        .role-badge::before {
            content: '';
            width: 5px;
            height: 5px;
            border-radius: 50%;
            background: currentColor;
            opacity: 0.7;
        }

        .role-admin { background: rgba(75,0,20,0.1);   color: var(--burgundy); }
        .role-user  { background: rgba(59,130,246,0.1); color: #2563eb; }

        /* ===== STAT CARDS ===== */
        .events-stats {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 16px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: white;
            border-radius: var(--radius);
            padding: 18px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
            transition: all 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.10);
        }

        .stat-number {
            font-size: 26px;
            font-weight: 800;
            color: var(--burgundy);
            line-height: 1;
            margin-bottom: 4px;
        }

        .stat-label {
            font-size: 11px;
            color: var(--text-secondary);
            font-weight: 500;
        }

        /* ===== TOOLBAR ===== */
        .events-toolbar {
            background: white;
            border-radius: var(--radius);
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            margin-bottom: 20px;
            overflow: hidden;
        }

        .toolbar-top {
            display: flex;
            align-items: center;
            padding: 0 20px;
            border-bottom: 1px solid var(--border);
            overflow-x: auto;
            scrollbar-width: none;
        }

        .toolbar-top::-webkit-scrollbar { display: none; }

        .tab-btn {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 14px 14px;
            border: none;
            border-bottom: 2px solid transparent;
            background: transparent;
            cursor: pointer;
            font-family: inherit;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-secondary);
            white-space: nowrap;
            transition: all 0.18s ease;
            text-decoration: none;
            margin-bottom: -1px;
        }

        .tab-btn:hover { color: var(--burgundy); }

        .tab-btn.active {
            color: var(--burgundy);
            border-bottom-color: var(--burgundy);
        }

        .tab-count {
            font-size: 11px;
            font-weight: 700;
            padding: 2px 7px;
            border-radius: 10px;
            background: #f0ede8;
            color: var(--text-secondary);
            line-height: 1.4;
        }

        .tab-btn.active .tab-count {
            background: rgba(75,0,20,0.08);
            color: var(--burgundy);
        }

        .toolbar-search {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 20px;
        }

        .search-wrap {
            position: relative;
            flex: 1;
        }

        .search-wrap i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #aaa;
            font-size: 13px;
            pointer-events: none;
        }

        .search-input {
            width: 100%;
            padding: 9px 12px 9px 34px;
            border: 1px solid var(--border-fields);
            border-radius: 9px;
            font-size: 13px;
            font-family: inherit;
            background: white;
            color: var(--text-primary);
            transition: all 0.2s;
        }

        .search-input:focus {
            outline: none;
            border-color: var(--gold);
            box-shadow: 0 0 0 3px rgba(194,161,77,0.12);
        }

        .btn-filter {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 9px 16px;
            border-radius: 9px;
            border: 1px solid var(--border-fields);
            background: white;
            color: var(--text-secondary);
            font-family: inherit;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            white-space: nowrap;
            text-decoration: none;
        }

        .btn-filter:hover { border-color: var(--gold); color: var(--text-primary); }

        /* ===== EVENTS TABLE ===== */
        .events-table-container {
            background: white;
            border-radius: var(--radius);
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            overflow: hidden;
            margin-bottom: 24px;
        }

        .events-table {
            width: 100%;
            border-collapse: collapse;
        }

        .events-table thead {
            background: #fdfcfa;
            border-bottom: 2px solid var(--border);
        }

        .events-table th {
            padding: 12px 16px;
            text-align: left;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: var(--text-secondary);
            white-space: nowrap;
        }

        .events-table th:first-child,
        .events-table td:first-child { padding-left: 20px; }

        .events-table td {
            padding: 14px 16px;
            font-size: 14px;
            color: var(--text-primary);
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
        }

        .events-table tbody tr:last-child td { border-bottom: none; }
        .events-table tbody tr:hover td { background: #faf9f6; }

        .event-name-cell {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .event-color-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .event-name-text { font-weight: 700; color: var(--text-primary); }

        /* Status badges */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 11px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }

        .status-badge::before {
            content: '';
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: currentColor;
            opacity: 0.7;
        }

        .badge-pending-review  { background: rgba(245,158,11,0.12); color: #d97706; }
        .badge-needs-revision  { background: rgba(239,68,68,0.12);  color: #dc2626; }
        .badge-approved        { background: rgba(16,185,129,0.12); color: #059669; }
        .badge-completed       { background: rgba(59,130,246,0.12); color: #2563eb; }
        .badge-draft           { background: rgba(156,163,175,0.1); color: #6b7280; }

        /* Progress bar */
        .progress-wrap { display: flex; align-items: center; gap: 8px; }

        .progress-bar {
            flex: 1;
            height: 6px;
            background: #e8e2da;
            border-radius: 4px;
            overflow: hidden;
            min-width: 60px;
        }

        .progress-fill {
            height: 100%;
            border-radius: 4px;
            background: var(--gold);
            transition: width 0.3s ease;
        }

        .progress-text {
            font-size: 11px;
            font-weight: 600;
            color: var(--text-secondary);
            white-space: nowrap;
        }

        /* View button */
        .btn-view {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 8px;
            background: #f5f2ed;
            border: 1px solid var(--border);
            color: var(--text-secondary);
            font-size: 12px;
            font-weight: 600;
            font-family: inherit;
            text-decoration: none;
            transition: all 0.18s ease;
        }

        .btn-view:hover {
            background: var(--burgundy);
            color: white;
            border-color: var(--burgundy);
        }

        /* ===== EMPTY STATE ===== */
        .empty-state {
            text-align: center;
            padding: 64px 20px;
            color: var(--text-secondary);
        }

        .empty-state i { font-size: 40px; color: #ccc; margin-bottom: 14px; display: block; }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 1100px) { .events-stats { grid-template-columns: repeat(3, 1fr); } }
        @media (max-width: 768px)  {
            .events-stats { grid-template-columns: repeat(2, 1fr); }
            .user-profile-meta { gap: 8px; }
            .events-table th:nth-child(4),
            .events-table td:nth-child(4) { display: none; }
        }
        @media (max-width: 480px)  {
            .events-stats { grid-template-columns: 1fr; }
            .user-profile-card { flex-direction: column; align-items: flex-start; }
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
                <h1>User Events</h1>
                <p><?= htmlspecialchars($user['user_name']) ?> &bull; <?= htmlspecialchars($user['org_body'] ?? 'No organization') ?></p>
            </div>
            <div class="home-top-actions">
                <a href="admin_users.php" class="btn-secondary">← Back to Users</a>
            </div>
        </div>

        <!-- User Profile Card -->
        <div class="user-profile-card">
            <img src="assets/profiles/<?= htmlspecialchars(!empty($user['profile_pic']) ? $user['profile_pic'] : 'default.jpg') ?>"
                 alt="<?= htmlspecialchars($user['user_name']) ?>"
                 class="user-profile-avatar">
            <div class="user-profile-info">
                <div class="user-profile-name"><?= htmlspecialchars($user['user_name']) ?></div>
                <div class="user-profile-meta">
                    <span class="user-meta-item">
                        <i class="fa-solid fa-envelope"></i>
                        <?= htmlspecialchars($user['user_email']) ?>
                    </span>
                    <span class="user-meta-item">
                        <i class="fa-solid fa-id-card"></i>
                        <?= htmlspecialchars($user['stud_num']) ?>
                    </span>
                    <span class="user-meta-item">
                        <i class="fa-solid fa-building-columns"></i>
                        <?= htmlspecialchars($user['org_body'] ?? 'No organization') ?>
                    </span>
                    <span class="user-meta-item">
                        <i class="fa-solid fa-calendar-plus"></i>
                        Registered <?= !empty($user['user_reg_date']) ? date('M j, Y', strtotime($user['user_reg_date'])) : 'N/A' ?>
                    </span>
                    <span class="role-badge <?= $user['role'] === 'admin' ? 'role-admin' : 'role-user' ?>">
                        <?= htmlspecialchars(ucfirst($user['role'] ?? 'user')) ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Stat Cards -->
        <div class="events-stats">
            <div class="stat-card">
                <div class="stat-number"><?= (int)($counts['total_events'] ?? 0) ?></div>
                <div class="stat-label">Total Events</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= (int)($counts['draft_count'] ?? 0) ?></div>
                <div class="stat-label">Draft</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= (int)($counts['pending_review_count'] ?? 0) ?></div>
                <div class="stat-label">Pending Review</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= (int)($counts['needs_revision_count'] ?? 0) ?></div>
                <div class="stat-label">Needs Revision</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= (int)($counts['approved_count'] ?? 0) ?></div>
                <div class="stat-label">Approved</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= (int)($counts['completed_count'] ?? 0) ?></div>
                <div class="stat-label">Completed</div>
            </div>
        </div>

        <!-- Toolbar -->
        <div class="events-toolbar">
            <div class="toolbar-top">
                <?php
                $tabs = [
                    ['value' => 'all',            'label' => 'All',            'count' => (int)($counts['total_events'] ?? 0),         'icon' => 'fa-solid fa-list'],
                    ['value' => 'Pending Review', 'label' => 'Pending',        'count' => (int)($counts['pending_review_count'] ?? 0),  'icon' => 'fa-solid fa-hourglass-half'],
                    ['value' => 'Approved',       'label' => 'Approved',       'count' => (int)($counts['approved_count'] ?? 0),        'icon' => 'fa-solid fa-circle-check'],
                    ['value' => 'Completed',      'label' => 'Completed',      'count' => (int)($counts['completed_count'] ?? 0),       'icon' => 'fa-solid fa-flag-checkered'],
                    ['value' => 'Needs Revision', 'label' => 'Needs Revision', 'count' => (int)($counts['needs_revision_count'] ?? 0),  'icon' => 'fa-solid fa-rotate-left'],
                    ['value' => 'Draft',          'label' => 'Draft',          'count' => (int)($counts['draft_count'] ?? 0),           'icon' => 'fa-regular fa-file'],
                ];
                foreach ($tabs as $tab):
                    $active = $status_filter === $tab['value'] ? 'active' : '';
                    $url = '?user_id=' . $user_id . '&status=' . urlencode($tab['value']) . ($search ? '&search=' . urlencode($search) : '');
                ?>
                    <a href="<?= $url ?>" class="tab-btn <?= $active ?>">
                        <i class="<?= $tab['icon'] ?>"></i>
                        <?= $tab['label'] ?>
                        <span class="tab-count"><?= $tab['count'] ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
            <div class="toolbar-search">
                <div class="search-wrap">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" id="searchInput" class="search-input"
                           placeholder="Search this user's events..."
                           value="<?= htmlspecialchars($search) ?>">
                </div>
                <button class="btn-filter" id="applyFilters">
                    <i class="fa-solid fa-sliders"></i> Search
                </button>
                <?php if ($search): ?>
                    <a href="?user_id=<?= $user_id ?>&status=<?= urlencode($status_filter) ?>" class="btn-filter">
                        <i class="fa-solid fa-xmark"></i> Clear
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Events Table -->
        <div class="events-table-container">
            <table class="events-table">
                <thead>
                    <tr>
                        <th>Event</th>
                        <th>Background</th>
                        <th>Venue</th>
                        <th>Start Date</th>
                        <th>Status</th>
                        <th>Documents</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($events)): ?>
                        <?php
                        $dot_colors = ['#f59e0b','#10b981','#ef4444','#3b82f6','#8b5cf6','#ec4899','#06b6d4','#84cc16'];
                        $i = 0;
                        foreach ($events as $event):
                            $dot = $dot_colors[$i % count($dot_colors)]; $i++;

                            $status = $event['event_status'];
                            $badge_class = match($status) {
                                'Pending Review' => 'badge-pending-review',
                                'Needs Revision' => 'badge-needs-revision',
                                'Approved'       => 'badge-approved',
                                'Completed'      => 'badge-completed',
                                default          => 'badge-draft',
                            };

                            $docs_total    = (int)($event['docs_total'] ?? 0);
                            $docs_uploaded = (int)($event['docs_uploaded'] ?? 0);
                            $pct           = $docs_total > 0 ? round(($docs_uploaded / $docs_total) * 100) : 0;
                            $hue           = ($pct / 100) * 120;
                            $progress_color = "hsl($hue, 70%, 45%)";

                            $org_clean = $event['organizing_body'] ?? '';
                            $decoded_orgs = json_decode($org_clean, true);
                            if (is_array($decoded_orgs)) {
                                $org_clean = implode(', ', $decoded_orgs);
                            } else {
                                $org_clean = str_replace(['[', ']', '"', "'"], '', $org_clean);
                                $org_clean = str_replace(',', ', ', $org_clean);
                            }
                        ?>
                        <tr>
                            <td>
                                <div class="event-name-cell">
                                    <span class="event-color-dot" style="background:<?= $dot ?>"></span>
                                    <div>
                                        <div class="event-name-text"><?= htmlspecialchars($event['event_name']) ?></div>
                                        <div style="font-size:11px;color:var(--text-secondary);margin-top:2px;">
                                            <?= htmlspecialchars($event['activity_type'] ?? 'N/A') ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td style="font-size:13px;color:var(--text-secondary);">
                                <?= htmlspecialchars($event['background'] ?? 'N/A') ?>
                            </td>
                            <td style="font-size:13px;color:var(--text-secondary);">
                                <?= htmlspecialchars($event['venue_platform'] ?? '—') ?>
                            </td>
                            <td>
                                <?php if (!empty($event['start_datetime'])): ?>
                                    <span style="display:inline-flex;align-items:center;gap:6px;font-size:13px;color:var(--text-secondary);">
                                        <i class="fa-regular fa-calendar"></i>
                                        <?= date('M j, Y', strtotime($event['start_datetime'])) ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color:#ccc;">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="status-badge <?= $badge_class ?>"><?= htmlspecialchars($status) ?></span>
                                <?php if (!empty($event['admin_remarks']) && $status === 'Needs Revision'): ?>
                                    <div style="font-size:11px;color:#dc2626;margin-top:4px;max-width:180px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"
                                         title="<?= htmlspecialchars($event['admin_remarks']) ?>">
                                        <?= htmlspecialchars($event['admin_remarks']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="progress-wrap">
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width:<?= $pct ?>%;background:<?= $progress_color ?>;"></div>
                                    </div>
                                    <span class="progress-text"><?= $docs_uploaded ?>/<?= $docs_total ?></span>
                                </div>
                            </td>
                            <td>
                                <a href="admin_manage_event.php?id=<?= (int)$event['event_id'] ?>" class="btn-view">
                                    <i class="fa-solid fa-eye"></i> Manage
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7">
                                <div class="empty-state">
                                    <i class="fa-regular fa-calendar-xmark"></i>
                                    <p>No events found<?= $search ? ' for "' . htmlspecialchars($search) . '"' : '' ?>.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php include 'assets/includes/footer.php'; ?>
    </main>
</div>

<script>
    document.getElementById('applyFilters').addEventListener('click', function () {
        const search = document.getElementById('searchInput').value;
        window.location.href = `?user_id=<?= $user_id ?>&status=<?= urlencode($status_filter) ?>&search=${encodeURIComponent(search)}`;
    });

    document.getElementById('searchInput').addEventListener('keydown', function (e) {
        if (e.key === 'Enter') document.getElementById('applyFilters').click();
    });
</script>
<script src="../app/script/layout.js?v=1"></script>
</body>
</html>