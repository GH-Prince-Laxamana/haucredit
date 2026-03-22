<?php
session_start();
require_once __DIR__ . '/../../app/database.php';

requireAdmin();

$admin_name = htmlspecialchars($_SESSION["username"] ?? "Admin", ENT_QUOTES, "UTF-8");

$status_filter = trim($_GET['status'] ?? 'all');
$org_filter = trim($_GET['org'] ?? '');
$search = trim($_GET['search'] ?? '');
$filter = trim($_GET['filter'] ?? '');
$view = trim($_GET['view'] ?? '');
$sort = trim($_GET['sort'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));

$per_page = 10;
$offset = ($page - 1) * $per_page;

/* ================= HELPERS ================= */
function normalizeStatusClass(string $status): string
{
    return strtolower(str_replace(' ', '-', trim($status)));
}

function isValidStatusFilter(string $status): bool
{
    $allowed = ['all', 'Pending Review', 'Needs Revision', 'Approved', 'Completed'];
    return in_array($status, $allowed, true);
}

function buildQueryString(array $overrides = []): string
{
    $params = array_merge($_GET, $overrides);

    foreach ($params as $key => $value) {
        if ($value === '' || $value === null) {
            unset($params[$key]);
        }
    }

    return http_build_query($params);
}

if (!isValidStatusFilter($status_filter)) {
    $status_filter = 'all';
}

/* ================= PAGE TITLE ================= */
$page_title = "Event Management";
$page_subtitle = "Review and manage all submitted events.";

if ($filter === 'attention') {
    $page_title = "Events Requiring Attention";
    $page_subtitle = "Pending review and revision items that need admin action.";
} elseif ($view === 'recent') {
    $page_title = "Recent Event Submissions";
    $page_subtitle = "Newest submitted events from organizations.";
} elseif ($status_filter === 'Pending Review' && $sort === 'start_asc') {
    $page_title = "Upcoming Review Deadlines";
    $page_subtitle = "Pending review events sorted by nearest schedule.";
}

/* ================= SUMMARY COUNTS ================= */
$statusCountsSql = "
    SELECT
        COUNT(*) AS total_events,
        SUM(CASE WHEN e.event_status = 'Pending Review' THEN 1 ELSE 0 END) AS pending_review_count,
        SUM(CASE WHEN e.event_status = 'Needs Revision' THEN 1 ELSE 0 END) AS needs_revision_count,
        SUM(CASE WHEN e.event_status = 'Approved' THEN 1 ELSE 0 END) AS approved_count,
        SUM(CASE WHEN e.event_status = 'Completed' THEN 1 ELSE 0 END) AS completed_count
    FROM events e
    WHERE e.archived_at IS NULL
      AND e.is_system_event = 0
      AND e.event_status <> 'Draft'
";
$statusCounts = fetchOne($conn, $statusCountsSql);

/* ================= ORG FILTER OPTIONS ================= */
$orgOptionsSql = "
    SELECT DISTINCT u.org_body
    FROM users u
    INNER JOIN events e
        ON u.user_id = e.user_id
    WHERE e.archived_at IS NULL
      AND e.is_system_event = 0
      AND e.event_status <> 'Draft'
      AND u.role = 'user'
      AND u.org_body IS NOT NULL
      AND u.org_body != ''
    ORDER BY u.org_body ASC
";
$orgOptions = fetchAll($conn, $orgOptionsSql);

/* ================= BASE SQL ================= */
$baseSql = "
    FROM events e
    INNER JOIN users u
        ON e.user_id = u.user_id
    LEFT JOIN event_type et
        ON e.event_id = et.event_id
    LEFT JOIN config_activity_types cat
        ON et.activity_type_id = cat.activity_type_id
    LEFT JOIN config_background_options cbo
        ON et.background_id = cbo.background_id
    LEFT JOIN event_dates ed
        ON e.event_id = ed.event_id
    LEFT JOIN event_location el
        ON e.event_id = el.event_id
    WHERE e.archived_at IS NULL
      AND e.is_system_event = 0
      AND e.event_status <> 'Draft'
";

$params = [];
$types = "";

/* ================= DASHBOARD-DEEP-LINK FILTERS ================= */
if ($filter === 'attention') {
    $baseSql .= " AND e.event_status IN ('Pending Review', 'Needs Revision')";
} elseif ($filter === 'review_queue') {
    $baseSql .= " AND e.event_status IN ('Pending Review', 'Needs Revision')";
    $baseSql .= " AND ed.start_datetime IS NOT NULL";
}

/* ================= STATUS FILTER ================= */
if ($status_filter !== 'all') {
    $baseSql .= " AND e.event_status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

/* ================= ORG FILTER ================= */
if ($org_filter !== '') {
    $baseSql .= " AND u.org_body = ?";
    $params[] = $org_filter;
    $types .= "s";
}

/* ================= SEARCH FILTER ================= */
if ($search !== '') {
    $baseSql .= "
        AND (
            e.event_name LIKE ?
            OR u.user_name LIKE ?
            OR u.user_email LIKE ?
            OR u.org_body LIKE ?
            OR cat.activity_type_name LIKE ?
            OR cbo.background_name LIKE ?
            OR el.venue_platform LIKE ?
        )
    ";
    $like = "%" . $search . "%";
    array_push($params, $like, $like, $like, $like, $like, $like, $like);
    $types .= "sssssss";
}

/* ================= VIEW OVERRIDES ================= */
if ($view === 'recent') {
    $baseSql .= " AND e.event_status IN ('Pending Review', 'Needs Revision', 'Approved')";
}

/* ================= ORDER BY ================= */
$orderBy = "
    ORDER BY
        CASE e.event_status
            WHEN 'Needs Revision' THEN 1
            WHEN 'Pending Review' THEN 2
            WHEN 'Approved' THEN 3
            WHEN 'Completed' THEN 4
            ELSE 5
        END,
        CASE WHEN ed.start_datetime IS NULL THEN 1 ELSE 0 END,
        ed.start_datetime ASC,
        e.created_at DESC
";

if ($view === 'recent') {
    $orderBy = " ORDER BY e.created_at DESC ";
} elseif ($sort === 'start_asc') {
    $orderBy = "
        ORDER BY
            CASE WHEN ed.start_datetime IS NULL THEN 1 ELSE 0 END,
            ed.start_datetime ASC,
            e.created_at DESC
    ";
} elseif ($sort === 'start_desc') {
    $orderBy = "
        ORDER BY
            CASE WHEN ed.start_datetime IS NULL THEN 1 ELSE 0 END,
            ed.start_datetime DESC,
            e.created_at DESC
    ";
} elseif ($sort === 'updated_desc') {
    $orderBy = " ORDER BY e.updated_at DESC, e.created_at DESC ";
}

/* ================= TOTAL COUNT ================= */
$countSql = "SELECT COUNT(*) AS total " . $baseSql;
$countRow = fetchOne($conn, $countSql, $types, $params);
$total_rows = (int) ($countRow['total'] ?? 0);
$total_pages = max(1, (int) ceil($total_rows / $per_page));

/* ================= EVENT LIST ================= */
$fetchEventsSql = "
    SELECT
        e.event_id,
        e.event_name,
        e.event_status,
        e.docs_total,
        e.docs_uploaded,
        e.created_at,
        e.updated_at,
        e.organizing_body,
        e.admin_remarks,

        u.user_name,
        u.user_email,
        u.org_body,

        et.activity_type_id,
        et.background_id,
        cat.activity_type_name AS activity_type,
        cbo.background_name AS background,

        ed.start_datetime,
        ed.end_datetime,

        el.venue_platform
    " . $baseSql . "
    " . $orderBy . "
    LIMIT ? OFFSET ?
";

$listParams = $params;
$listTypes = $types . "ii";
$listParams[] = $per_page;
$listParams[] = $offset;

$events = fetchAll($conn, $fetchEventsSql, $listTypes, $listParams);

/* ================= SUMMARY VALUES ================= */
$total_events = (int) ($statusCounts['total_events'] ?? 0);
$pending_review_count = (int) ($statusCounts['pending_review_count'] ?? 0);
$needs_revision_count = (int) ($statusCounts['needs_revision_count'] ?? 0);
$approved_count = (int) ($statusCounts['approved_count'] ?? 0);
$completed_count = (int) ($statusCounts['completed_count'] ?? 0);
$pending_total = $pending_review_count + $needs_revision_count;

$showing_from = $total_rows > 0 ? $offset + 1 : 0;
$showing_to = min($offset + $per_page, $total_rows);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Admin Events - HAUCREDIT</title>
    <link rel="stylesheet" href="<?= PUBLIC_URL ?>assets/styles/layout.css" />
    <link rel="stylesheet" href="<?= PUBLIC_URL ?>assets/styles/home_styles.css" />
    <link rel="stylesheet" href="<?= PUBLIC_URL ?>assets/styles/admin_events.css" />
</head>

<body>
    <div class="app">
        <div class="sidebar-overlay" id="sidebarOverlay" hidden></div>

        <?php include PUBLIC_PATH . 'assets/includes/admin_nav.php'; ?>

        <main class="main">
            <header class="topbar">
                <button class="hamburger" id="menuBtn" type="button" aria-label="Open menu">☰</button>

                <div class="title-wrap">
                    <h1><?= htmlspecialchars($page_title) ?></h1>
                    <p><?= htmlspecialchars($page_subtitle) ?></p>
                </div>
            </header>

            <section class="content admin-events-page">
                <!-- Summary -->
                <section class="events-stats">
                    <article class="stat-card">
                        <div class="stat-icon"><i class="fa-solid fa-list"></i></div>
                        <div>
                            <div class="stat-number"><?= $total_events ?></div>
                            <div class="stat-label">Total Events</div>
                        </div>
                    </article>

                    <article class="stat-card">
                        <div class="stat-icon"><i class="fa-solid fa-hourglass-half"></i></div>
                        <div>
                            <div class="stat-number"><?= $pending_total ?></div>
                            <div class="stat-label">Pending Events</div>
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
                </section>

                <!-- Toolbar -->
                <section class="events-toolbar">
                    <div class="toolbar-top">
                        <?php
                        $tabs = [
                            ['value' => 'all', 'label' => 'All', 'count' => $total_events, 'icon' => 'fa-solid fa-list'],
                            ['value' => 'Pending Review', 'label' => 'Pending', 'count' => $pending_review_count, 'icon' => 'fa-solid fa-hourglass-half'],
                            ['value' => 'Needs Revision', 'label' => 'Needs Revision', 'count' => $needs_revision_count, 'icon' => 'fa-solid fa-rotate-left'],
                            ['value' => 'Approved', 'label' => 'Approved', 'count' => $approved_count, 'icon' => 'fa-solid fa-circle-check'],
                            ['value' => 'Completed', 'label' => 'Completed', 'count' => $completed_count, 'icon' => 'fa-solid fa-flag-checkered']
                        ];

                        foreach ($tabs as $tab):
                            $active = $status_filter === $tab['value'] ? 'active' : '';
                            $tabUrl = 'admin_events.php?' . buildQueryString([
                                'status' => $tab['value'],
                                'page' => 1
                            ]);
                            ?>
                            <a href="<?= htmlspecialchars($tabUrl) ?>" class="tab-btn <?= $active ?>">
                                <i class="<?= htmlspecialchars($tab['icon']) ?>"></i>
                                <?= htmlspecialchars($tab['label']) ?>
                                <span class="tab-count"><?= (int) $tab['count'] ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>

                    <div class="toolbar-search">
                        <form method="GET" class="toolbar-form" id="eventsFilterForm">
                            <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
                            <input type="hidden" name="view" value="<?= htmlspecialchars($view) ?>">
                            <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">
                            <input type="hidden" name="page" value="1">

                            <div class="search-wrap">
                                <i class="fa-solid fa-magnifying-glass"></i>
                                <input type="text" name="search" id="searchInput" class="search-input"
                                    placeholder="Search events, organizations, users, type, venue..."
                                    value="<?= htmlspecialchars($search) ?>">
                            </div>

                            <select name="status" class="toolbar-select auto-submit-filter">
                                <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Statuses</option>
                                <option value="Pending Review" <?= $status_filter === 'Pending Review' ? 'selected' : '' ?>>Pending Review</option>
                                <option value="Needs Revision" <?= $status_filter === 'Needs Revision' ? 'selected' : '' ?>>Needs Revision</option>
                                <option value="Approved" <?= $status_filter === 'Approved' ? 'selected' : '' ?>>Approved
                                </option>
                                <option value="Completed" <?= $status_filter === 'Completed' ? 'selected' : '' ?>>Completed
                                </option>
                            </select>

                            <select name="org" class="toolbar-select auto-submit-filter">
                                <option value="">All Organizations</option>
                                <?php foreach ($orgOptions as $org): ?>
                                    <?php $org_value = trim($org['org_body'] ?? ''); ?>
                                    <option value="<?= htmlspecialchars($org_value) ?>" <?= $org_filter === $org_value ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($org_value) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <?php if ($status_filter !== 'all' || $org_filter !== '' || $search !== '' || $filter !== '' || $view !== '' || $sort !== ''): ?>
                                <a href="admin_events.php" class="btn-filter">
                                    <i class="fa-solid fa-xmark"></i> Reset
                                </a>
                            <?php endif; ?>
                        </form>
                    </div>
                </section>

                <!-- Table -->
                <section class="events-table-container">
                    <table class="events-table">
                        <thead>
                            <tr>
                                <th>Event</th>
                                <th>Organization</th>
                                <th>Submitted By</th>
                                <th>Start Date</th>
                                <th>Status</th>
                                <th>Documents</th>
                                <th>Type</th>
                                <th>Actions</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php if (!empty($events)): ?>
                                <?php foreach ($events as $event): ?>
                                    <?php
                                    $docs_total = (int) ($event['docs_total'] ?? 0);
                                    $docs_uploaded = (int) ($event['docs_uploaded'] ?? 0);
                                    $status = $event['event_status'];
                                    $status_class = normalizeStatusClass($status);

                                    $badge_class = match ($status) {
                                        'Pending Review' => 'badge-pending-review',
                                        'Needs Revision' => 'badge-needs-revision',
                                        'Approved' => 'badge-approved',
                                        'Completed' => 'badge-completed'
                                    };

                                    $doc_text = $docs_total > 0
                                        ? "{$docs_uploaded}/{$docs_total}"
                                        : "0/0";
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="event-name-cell">
                                                <span
                                                    class="event-name-text"><?= htmlspecialchars($event['event_name']) ?></span>
                                                <?php if (!empty($event['admin_remarks']) && $status === 'Needs Revision'): ?>
                                                    <div class="event-inline-note">Has admin remarks</div>
                                                <?php endif; ?>
                                            </div>
                                        </td>

                                        <td>
                                            <span class="org-badge">
                                                <i class="fa-solid fa-building-columns"></i>
                                                <?= htmlspecialchars($event['org_body'] ?? 'No organization') ?>
                                            </span>
                                        </td>

                                        <td>
                                            <div class="submitted-by">
                                                <strong><?= htmlspecialchars($event['user_name'] ?? 'Unknown User') ?></strong>
                                                <small><?= htmlspecialchars($event['user_email'] ?? '') ?></small>
                                            </div>
                                        </td>

                                        <td>
                                            <?php if (!empty($event['start_datetime'])): ?>
                                                <span class="date-chip">
                                                    <i class="fa-regular fa-calendar"></i>
                                                    <?= date('M j, Y', strtotime($event['start_datetime'])) ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="muted-dash">—</span>
                                            <?php endif; ?>
                                        </td>

                                        <td>
                                            <span class="status-badge <?= htmlspecialchars($badge_class) ?>">
                                                <?= htmlspecialchars($status) ?>
                                            </span>
                                        </td>

                                        <td>
                                            <span
                                                class="req-badge <?= $docs_uploaded >= $docs_total && $docs_total > 0 ? 'req-approved' : 'req-pending' ?>">
                                                <i
                                                    class="fa-solid <?= $docs_uploaded >= $docs_total && $docs_total > 0 ? 'fa-circle-check' : 'fa-clock' ?>"></i>
                                                <?= htmlspecialchars($doc_text) ?>
                                            </span>
                                        </td>

                                        <td><?= htmlspecialchars($event['activity_type'] ?? 'N/A') ?></td>

                                        <td>
                                            <a href="admin_manage_event.php?id=<?= (int) $event['event_id'] ?>"
                                                class="btn-view">
                                                <i class="fa-solid fa-eye"></i> Manage
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8">
                                        <div class="empty-state">
                                            <i class="fa-regular fa-calendar-xmark"></i>
                                            <p>No events found. Try adjusting your filters.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </section>

                <!-- Pagination -->
                <?php if ($total_rows > 0): ?>
                    <section class="pagination-wrap">
                        <div class="pagination-info">
                            Showing <strong><?= $showing_from ?></strong> to <strong><?= $showing_to ?></strong> of
                            <strong><?= $total_rows ?></strong> events
                        </div>

                        <div class="pagination">
                            <?php
                            $prevPage = max(1, $page - 1);
                            $nextPage = min($total_pages, $page + 1);
                            $startPage = max(1, $page - 2);
                            $endPage = min($total_pages, $page + 2);
                            ?>

                            <a href="admin_events.php?<?= htmlspecialchars(buildQueryString(['page' => 1])) ?>"
                                class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>">
                                <i class="fa-solid fa-angles-left"></i>
                            </a>

                            <a href="admin_events.php?<?= htmlspecialchars(buildQueryString(['page' => $prevPage])) ?>"
                                class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>">
                                <i class="fa-solid fa-chevron-left"></i>
                            </a>

                            <?php for ($pg = $startPage; $pg <= $endPage; $pg++): ?>
                                <a href="admin_events.php?<?= htmlspecialchars(buildQueryString(['page' => $pg])) ?>"
                                    class="page-btn <?= $pg === $page ? 'active' : '' ?>">
                                    <?= $pg ?>
                                </a>
                            <?php endfor; ?>

                            <a href="admin_events.php?<?= htmlspecialchars(buildQueryString(['page' => $nextPage])) ?>"
                                class="page-btn <?= $page >= $total_pages ? 'disabled' : '' ?>">
                                <i class="fa-solid fa-chevron-right"></i>
                            </a>

                            <a href="admin_events.php?<?= htmlspecialchars(buildQueryString(['page' => $total_pages])) ?>"
                                class="page-btn <?= $page >= $total_pages ? 'disabled' : '' ?>">
                                <i class="fa-solid fa-angles-right"></i>
                            </a>
                        </div>
                    </section>
                <?php endif; ?>

                <?php include PUBLIC_PATH . 'assets/includes/footer.php'; ?>
            </section>
        </main>
    </div>

    <script src="<?= APP_URL ?>script/layout.js?v=1"></script>
    <script>
        (function () {
            const form = document.getElementById('eventsFilterForm');
            if (!form) return;

            const autoSubmitFields = form.querySelectorAll('.auto-submit-filter');
            const searchInput = document.getElementById('searchInput');

            autoSubmitFields.forEach(field => {
                field.addEventListener('change', function () {
                    form.submit();
                });
            });

            if (searchInput) {
                searchInput.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        form.submit();
                    }
                });
            }
        })();
    </script>
</body>

</html>