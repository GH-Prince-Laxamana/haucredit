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

$status_filter = trim($_GET['status'] ?? 'all');
$search = trim($_GET['search'] ?? '');
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

$allowed_statuses = ['all', 'Draft', 'Pending Review', 'Needs Revision', 'Approved', 'Completed'];
if (!in_array($status_filter, $allowed_statuses)) {
    $status_filter = 'all';
}

$summarySql = "
    SELECT
        COUNT(*) AS total_events,
        SUM(CASE WHEN event_status = 'Pending Review' THEN 1 ELSE 0 END) AS pending_review,
        SUM(CASE WHEN event_status = 'Needs Revision' THEN 1 ELSE 0 END) AS needs_revision,
        SUM(CASE WHEN event_status = 'Approved' THEN 1 ELSE 0 END) AS approved,
        SUM(CASE WHEN event_status = 'Completed' THEN 1 ELSE 0 END) AS completed
    FROM events
    WHERE archived_at IS NULL
      AND is_system_event = 0
";
$summary = fetchOne($conn, $summarySql);

$total_events   = (int) ($summary['total_events'] ?? 0);
$pending_review = (int) ($summary['pending_review'] ?? 0);
$needs_revision = (int) ($summary['needs_revision'] ?? 0);
$approved       = (int) ($summary['approved'] ?? 0);
$completed      = (int) ($summary['completed'] ?? 0);
$pending_total  = $pending_review + $needs_revision;

$baseSql = "
    FROM events e
    INNER JOIN users u ON e.user_id = u.user_id
    LEFT JOIN event_dates ed ON e.event_id = ed.event_id
    WHERE e.archived_at IS NULL
      AND e.is_system_event = 0
";

$params = [];
$types = "";

if ($status_filter !== 'all') {
    $baseSql .= " AND e.event_status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if ($search !== '') {
    $baseSql .= " AND (e.event_name LIKE ? OR u.user_name LIKE ? OR u.org_body LIKE ?)";
    $like = "%" . $search . "%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= "sss";
}

$countSql = "SELECT COUNT(*) AS total " . $baseSql;
$countResult = fetchOne($conn, $countSql, $types, $params);
$total_rows = (int) ($countResult['total'] ?? 0);
$total_pages = ceil($total_rows / $per_page);

$eventsSql = "
    SELECT
        e.event_id,
        e.event_name,
        e.event_status,
        u.user_name AS managed_by,
        u.org_body AS organization,
        ed.start_datetime,
        (SELECT COUNT(*) FROM event_requirements er WHERE er.event_id = e.event_id AND er.submission_status = 'Pending') AS pending_requirements,
        (SELECT COUNT(*) FROM event_requirements er WHERE er.event_id = e.event_id AND er.review_status = 'Approved') AS approved_requirements,
        (SELECT COUNT(*) FROM event_requirements er WHERE er.event_id = e.event_id) AS total_requirements
    " . $baseSql . "
    ORDER BY e.created_at DESC
    LIMIT ? OFFSET ?
";

$params[] = $per_page;
$params[] = $offset;
$types .= "ii";

$events = fetchAll($conn, $eventsSql, $types, $params);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Events - HAUCREDIT</title>
    <link rel="stylesheet" href="assets/styles/layout.css">
    <link rel="stylesheet" href="assets/styles/home_styles.css">
    <style>
        /* ===== STAT CARDS ===== */
        .events-stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
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

        .btn-filter:hover {
            border-color: var(--gold);
            color: var(--text-primary);
        }

        /* ===== TABLE ===== */
        .events-table-container {
            background: white;
            border-radius: var(--radius);
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            overflow: hidden;
            margin-bottom: 20px;
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
        .events-table td:first-child {
            padding-left: 20px;
            width: 44px;
        }

        .events-table td {
            padding: 14px 16px;
            font-size: 14px;
            color: var(--text-primary);
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
        }

        .events-table tbody tr:last-child td { border-bottom: none; }
        .events-table tbody tr:hover td { background: #faf9f6; }

        .row-check {
            width: 16px;
            height: 16px;
            accent-color: var(--burgundy);
            cursor: pointer;
        }

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

        .event-name-text {
            font-weight: 700;
            color: var(--text-primary);
        }

        .org-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 7px;
            background: #f5f2ed;
            border: 1px solid var(--border);
            font-size: 12px;
            font-weight: 600;
            color: var(--text-secondary);
        }

        .org-badge i { font-size: 11px; color: var(--gold); }

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

        .req-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 11px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
        }

        .req-pending   { background: rgba(245,158,11,0.1);  color: #d97706; }
        .req-approved  { background: rgba(16,185,129,0.1);  color: #059669; }
        .req-rejected  { background: rgba(239,68,68,0.1);   color: #dc2626; }
        .req-neutral   { background: #f0ede8;                color: var(--text-secondary); }
        .req-completed { background: rgba(59,130,246,0.1);  color: #2563eb; }

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

        /* ===== PAGINATION ===== */
        .pagination-wrap {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 20px;
            background: white;
            border-radius: var(--radius);
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 12px;
        }

        .pagination-info {
            font-size: 13px;
            color: var(--text-secondary);
        }

        .pagination { display: flex; gap: 4px; }

        .page-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 34px;
            height: 34px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-secondary);
            background: #f5f2ed;
            border: 1px solid var(--border);
            transition: all 0.18s;
        }

        .page-btn:hover, .page-btn.active {
            background: var(--burgundy);
            color: white;
            border-color: var(--burgundy);
        }

        .page-btn.disabled { opacity: 0.35; pointer-events: none; }

        .empty-state {
            text-align: center;
            padding: 64px 20px;
            color: var(--text-secondary);
        }

        .empty-state i {
            font-size: 40px;
            color: #ccc;
            margin-bottom: 14px;
            display: block;
        }

        @media (max-width: 1100px) { .events-stats { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 768px) {
            .events-table th:nth-child(4),
            .events-table td:nth-child(4) { display: none; }
        }
        @media (max-width: 480px) { .events-stats { grid-template-columns: 1fr; } }
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
                <h1>Events</h1>
                <p>Manage and review all event submissions</p>
            </div>
            <div class="home-top-actions">
                <a class="btn-primary" href="create_event.php">
                    <i class="fa-solid fa-plus"></i> Create Event
                </a>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="events-stats">
            <div class="stat-card">
                <div>
                    <div class="stat-number"><?= $total_events ?></div>
                    <div class="stat-label">Total Events</div>
                </div>
            </div>
            <div class="stat-card">
                <div>
                    <div class="stat-number"><?= $pending_total ?></div>
                    <div class="stat-label">Pending Events</div>
                </div>
            </div>
            <div class="stat-card">
                <div>
                    <div class="stat-number"><?= $approved ?></div>
                    <div class="stat-label">Approved Events</div>
                </div>
            </div>
            <div class="stat-card">
                <div>
                    <div class="stat-number"><?= $completed ?></div>
                    <div class="stat-label">Completed Events</div>
                </div>
            </div>
        </div>

        <!-- Toolbar -->
        <div class="events-toolbar">
            <div class="toolbar-top">
                <?php
                $tabs = [
                    ['value' => 'all',            'label' => 'All',            'count' => $total_events,   'icon' => 'fa-solid fa-list'],
                    ['value' => 'Pending Review', 'label' => 'Pending',        'count' => $pending_review, 'icon' => 'fa-solid fa-hourglass-half'],
                    ['value' => 'Approved',       'label' => 'Approved',       'count' => $approved,       'icon' => 'fa-solid fa-circle-check'],
                    ['value' => 'Completed',      'label' => 'Completed',      'count' => $completed,      'icon' => 'fa-solid fa-flag-checkered'],
                    ['value' => 'Needs Revision', 'label' => 'Needs Revision', 'count' => $needs_revision, 'icon' => 'fa-solid fa-rotate-left'],
                    ['value' => 'Draft',          'label' => 'Draft',          'count' => null,            'icon' => 'fa-regular fa-file'],
                ];
                foreach ($tabs as $tab):
                    $active = $status_filter === $tab['value'] ? 'active' : '';
                    $url = '?status=' . urlencode($tab['value']) . ($search ? '&search=' . urlencode($search) : '');
                ?>
                    <a href="<?= $url ?>" class="tab-btn <?= $active ?>">
                        <i class="<?= $tab['icon'] ?>"></i>
                        <?= $tab['label'] ?>
                        <?php if ($tab['count'] !== null): ?>
                            <span class="tab-count"><?= $tab['count'] ?></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
            <div class="toolbar-search">
                <div class="search-wrap">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" id="searchInput" class="search-input"
                           placeholder="Search events, organizations, users..."
                           value="<?= htmlspecialchars($search) ?>">
                </div>
                <button class="btn-filter" id="applyFilters">
                    <i class="fa-solid fa-sliders"></i> Search
                </button>
                <?php if ($search): ?>
                    <a href="?status=<?= urlencode($status_filter) ?>" class="btn-filter">
                        <i class="fa-solid fa-xmark"></i> Clear
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Table -->
        <div class="events-table-container">
            <table class="events-table">
                <thead>
                    <tr>
                        <th><input type="checkbox" class="row-check" id="checkAll"></th>
                        <th>Event</th>
                        <th>Organization</th>
                        <th>Managed By</th>
                        <th>Start Date</th>
                        <th>Status</th>
                        <th>Requirements</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($events)): ?>
                        <?php
                        $dot_colors = ['#f59e0b','#10b981','#ef4444','#3b82f6','#8b5cf6','#ec4899','#06b6d4','#84cc16'];
                        $i = 0;
                        foreach ($events as $event):
                            $dot = $dot_colors[$i % count($dot_colors)];
                            $i++;

                            $status = $event['event_status'];
                            $badge_class = match($status) {
                                'Pending Review' => 'badge-pending-review',
                                'Needs Revision' => 'badge-needs-revision',
                                'Approved'       => 'badge-approved',
                                'Completed'      => 'badge-completed',
                                default          => 'badge-draft',
                            };

                            $pending_r  = (int)($event['pending_requirements'] ?? 0);
                            $approved_r = (int)($event['approved_requirements'] ?? 0);
                            $total_r    = (int)($event['total_requirements'] ?? 0);

                            if ($total_r == 0) {
                                $req_class = 'req-neutral';   $req_icon = 'fa-solid fa-minus';              $req_text = 'No requirements';
                            } elseif ($status === 'Completed') {
                                $req_class = 'req-completed'; $req_icon = 'fa-solid fa-folder-open';        $req_text = 'Completed';
                            } elseif ($approved_r === $total_r) {
                                $req_class = 'req-approved';  $req_icon = 'fa-solid fa-circle-check';       $req_text = '✓ All Approved';
                            } elseif ($pending_r > 0) {
                                $req_class = 'req-pending';   $req_icon = 'fa-solid fa-clock';              $req_text = "{$pending_r} Pending";
                            } elseif ($status === 'Needs Revision') {
                                $req_class = 'req-rejected';  $req_icon = 'fa-solid fa-triangle-exclamation'; $req_text = 'Needs Revision';
                            } else {
                                $req_class = 'req-neutral';   $req_icon = 'fa-solid fa-file-lines';         $req_text = "{$approved_r}/{$total_r} Approved";
                            }
                        ?>
                        <tr>
                            <td><input type="checkbox" class="row-check row-checkbox"></td>
                            <td>
                                <div class="event-name-cell">
                                    <span class="event-color-dot" style="background:<?= $dot ?>"></span>
                                    <span class="event-name-text"><?= htmlspecialchars($event['event_name']) ?></span>
                                </div>
                            </td>
                            <td>
                                <span class="org-badge">
                                    <i class="fa-solid fa-building-columns"></i>
                                    <?= htmlspecialchars($event['organization']) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($event['managed_by']) ?></td>
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
                            </td>
                            <td>
                                <span class="req-badge <?= $req_class ?>">
                                    <i class="<?= $req_icon ?>"></i> <?= $req_text ?>
                                </span>
                            </td>
                            <td>
                                <a href="manage_event.php?id=<?= $event['event_id'] ?>" class="btn-view">
                                    <i class="fa-solid fa-eye"></i> View
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8">
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

        <!-- Pagination -->
        <?php if ($total_pages > 0): ?>
        <div class="pagination-wrap">
            <div class="pagination-info">
                Showing <strong><?= $offset + 1 ?></strong> to <strong><?= min($offset + $per_page, $total_rows) ?></strong> of <strong><?= $total_rows ?></strong> events
            </div>
            <div class="pagination">
                <a href="?page=1&status=<?= urlencode($status_filter) ?>&search=<?= urlencode($search) ?>"
                   class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>"><i class="fa-solid fa-angles-left"></i></a>
                <a href="?page=<?= $page-1 ?>&status=<?= urlencode($status_filter) ?>&search=<?= urlencode($search) ?>"
                   class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>"><i class="fa-solid fa-chevron-left"></i></a>
                <?php
                $start_page = max(1, $page - 2);
                $end_page   = min($total_pages, $page + 2);
                for ($pg = $start_page; $pg <= $end_page; $pg++):
                ?>
                    <a href="?page=<?= $pg ?>&status=<?= urlencode($status_filter) ?>&search=<?= urlencode($search) ?>"
                       class="page-btn <?= $pg == $page ? 'active' : '' ?>"><?= $pg ?></a>
                <?php endfor; ?>
                <a href="?page=<?= $page+1 ?>&status=<?= urlencode($status_filter) ?>&search=<?= urlencode($search) ?>"
                   class="page-btn <?= $page >= $total_pages ? 'disabled' : '' ?>"><i class="fa-solid fa-chevron-right"></i></a>
                <a href="?page=<?= $total_pages ?>&status=<?= urlencode($status_filter) ?>&search=<?= urlencode($search) ?>"
                   class="page-btn <?= $page >= $total_pages ? 'disabled' : '' ?>"><i class="fa-solid fa-angles-right"></i></a>
            </div>
        </div>
        <?php endif; ?>

        <?php include 'assets/includes/footer.php'; ?>
    </main>
</div>

<script>
    document.getElementById('applyFilters').addEventListener('click', function () {
        const search = document.getElementById('searchInput').value;
        window.location.href = `?status=<?= urlencode($status_filter) ?>&search=${encodeURIComponent(search)}`;
    });

    document.getElementById('searchInput').addEventListener('keydown', function (e) {
        if (e.key === 'Enter') document.getElementById('applyFilters').click();
    });

    document.getElementById('checkAll').addEventListener('change', function () {
        document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = this.checked);
    });
</script>
<script src="../app/script/layout.js?v=1"></script>
</body>
</html>