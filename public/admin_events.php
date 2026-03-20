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
$org_filter = trim($_GET['org'] ?? '');
$search = trim($_GET['search'] ?? '');

/* ================= HELPERS ================= */
function normalizeStatusClass(string $status): string
{
    return strtolower(str_replace(' ', '-', $status));
}

function isValidStatusFilter(string $status): bool
{
    $allowed = ['all', 'Draft', 'Pending Review', 'Needs Revision', 'Approved', 'Completed'];
    return in_array($status, $allowed, true);
}

if (!isValidStatusFilter($status_filter)) {
    $status_filter = 'all';
}

/* ================= SUMMARY COUNTS ================= */
$statusCountsSql = "
    SELECT
        COUNT(*) AS total_events,
        SUM(CASE WHEN e.event_status = 'Draft' THEN 1 ELSE 0 END) AS draft_count,
        SUM(CASE WHEN e.event_status = 'Pending Review' THEN 1 ELSE 0 END) AS pending_review_count,
        SUM(CASE WHEN e.event_status = 'Needs Revision' THEN 1 ELSE 0 END) AS needs_revision_count,
        SUM(CASE WHEN e.event_status = 'Approved' THEN 1 ELSE 0 END) AS approved_count,
        SUM(CASE WHEN e.event_status = 'Completed' THEN 1 ELSE 0 END) AS completed_count
    FROM events e
    WHERE e.archived_at IS NULL
      AND e.is_system_event = 0
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
      AND u.role = 'user'
      AND u.org_body IS NOT NULL
      AND u.org_body != ''
    ORDER BY u.org_body ASC
";

$orgOptions = fetchAll($conn, $orgOptionsSql);

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

        et.activity_type,
        et.background,

        ed.start_datetime,
        ed.end_datetime,

        el.venue_platform
    FROM events e
    INNER JOIN users u
        ON e.user_id = u.user_id
    LEFT JOIN event_type et
        ON e.event_id = et.event_id
    LEFT JOIN event_dates ed
        ON e.event_id = ed.event_id
    LEFT JOIN event_location el
        ON e.event_id = el.event_id
    WHERE e.archived_at IS NULL
      AND e.is_system_event = 0
";

$params = [];
$types = "";

if ($status_filter !== 'all') {
    $fetchEventsSql .= " AND e.event_status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if ($org_filter !== '') {
    $fetchEventsSql .= " AND u.org_body = ?";
    $params[] = $org_filter;
    $types .= "s";
}

if ($search !== '') {
    $fetchEventsSql .= "
        AND (
            e.event_name LIKE ?
            OR u.user_name LIKE ?
            OR u.user_email LIKE ?
            OR u.org_body LIKE ?
            OR et.activity_type LIKE ?
            OR et.background LIKE ?
            OR el.venue_platform LIKE ?
        )
    ";
    $like = "%" . $search . "%";
    array_push($params, $like, $like, $like, $like, $like, $like, $like);
    $types .= "sssssss";
}

$fetchEventsSql .= "
    ORDER BY
        CASE e.event_status
            WHEN 'Needs Revision' THEN 1
            WHEN 'Pending Review' THEN 2
            WHEN 'Approved' THEN 3
            WHEN 'Completed' THEN 4
            WHEN 'Draft' THEN 5
            ELSE 6
        END,
        CASE WHEN ed.start_datetime IS NULL THEN 1 ELSE 0 END,
        ed.start_datetime ASC,
        e.created_at DESC
";

$events = fetchAll($conn, $fetchEventsSql, $types, $params);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Admin Events - HAUCREDIT</title>
    <link rel="stylesheet" href="assets/styles/layout.css" />
    <link rel="stylesheet" href="assets/styles/home_styles.css" />
    <link rel="stylesheet" href="assets/styles/my_events.css" />
</head>

<body>
    <div class="app">
        <div class="sidebar-overlay" id="sidebarOverlay" hidden></div>

        <?php include 'assets/includes/admin_nav.php'; ?>

        <main class="main">
            <header class="topbar">
                <button class="hamburger" id="menuBtn" type="button" aria-label="Open menu">☰</button>

                <div class="title-wrap">
                    <h1>Event Management</h1>
                    <p>Review and manage all submitted events.</p>
                </div>
            </header>

            <section class="content my-events-page">
                <div class="summary-strip">
                    <div class="summary-card">
                        <span class="summary-num"><?= (int) ($statusCounts['total_events'] ?? 0) ?></span>
                        <span class="summary-label">All Events</span>
                    </div>

                    <div class="summary-card">
                        <span class="summary-num"><?= (int) ($statusCounts['pending_review_count'] ?? 0) ?></span>
                        <span class="summary-label">Pending Review</span>
                    </div>

                    <div class="summary-card">
                        <span class="summary-num"><?= (int) ($statusCounts['needs_revision_count'] ?? 0) ?></span>
                        <span class="summary-label">Needs Revision</span>
                    </div>

                    <div class="summary-card">
                        <span class="summary-num"><?= (int) ($statusCounts['approved_count'] ?? 0) ?></span>
                        <span class="summary-label">Approved</span>
                    </div>

                    <div class="summary-card">
                        <span class="summary-num"><?= (int) ($statusCounts['completed_count'] ?? 0) ?></span>
                        <span class="summary-label">Completed</span>
                    </div>
                </div>

                <div class="list-toolbar" style="display:block;">
                    <form method="GET" class="search-wrap" style="margin-bottom: 1rem;">
                        <span class="search-icon">
                            <i class="fa-solid fa-magnifying-glass"></i>
                        </span>

                        <input type="text" name="search" class="search-input"
                            placeholder="Search by event, user, org, type, venue..."
                            value="<?= htmlspecialchars($search) ?>">

                        <select name="status" class="search-input" style="max-width: 220px;">
                            <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Statuses</option>
                            <option value="Draft" <?= $status_filter === 'Draft' ? 'selected' : '' ?>>Draft</option>
                            <option value="Pending Review" <?= $status_filter === 'Pending Review' ? 'selected' : '' ?>>
                                Pending Review</option>
                            <option value="Needs Revision" <?= $status_filter === 'Needs Revision' ? 'selected' : '' ?>>
                                Needs Revision</option>
                            <option value="Approved" <?= $status_filter === 'Approved' ? 'selected' : '' ?>>Approved
                            </option>
                            <option value="Completed" <?= $status_filter === 'Completed' ? 'selected' : '' ?>>Completed
                            </option>
                        </select>

                        <select name="org" class="search-input" style="max-width: 220px;">
                            <option value="">All Organizations</option>
                            <?php foreach ($orgOptions as $org): ?>
                                <?php $org_value = $org['org_body'] ?? ''; ?>
                                <option value="<?= htmlspecialchars($org_value) ?>" <?= $org_filter === $org_value ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($org_value) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <button type="submit" class="btn-primary">Apply</button>

                        <?php if ($status_filter !== 'all' || $org_filter !== '' || $search !== ''): ?>
                            <a href="admin_events.php" class="btn-secondary">Reset</a>
                        <?php endif; ?>
                    </form>

                    <div class="filter-tabs" id="filterTabs">
                        <a class="filter-tab <?= $status_filter === 'all' ? 'active' : '' ?>"
                            href="admin_events.php?status=all&org=<?= urlencode($org_filter) ?>&search=<?= urlencode($search) ?>">All</a>
                        <a class="filter-tab <?= $status_filter === 'Pending Review' ? 'active' : '' ?>"
                            href="admin_events.php?status=Pending+Review&org=<?= urlencode($org_filter) ?>&search=<?= urlencode($search) ?>">Pending
                            Review</a>
                        <a class="filter-tab <?= $status_filter === 'Needs Revision' ? 'active' : '' ?>"
                            href="admin_events.php?status=Needs+Revision&org=<?= urlencode($org_filter) ?>&search=<?= urlencode($search) ?>">Needs
                            Revision</a>
                        <a class="filter-tab <?= $status_filter === 'Approved' ? 'active' : '' ?>"
                            href="admin_events.php?status=Approved&org=<?= urlencode($org_filter) ?>&search=<?= urlencode($search) ?>">Approved</a>
                        <a class="filter-tab <?= $status_filter === 'Completed' ? 'active' : '' ?>"
                            href="admin_events.php?status=Completed&org=<?= urlencode($org_filter) ?>&search=<?= urlencode($search) ?>">Completed</a>
                    </div>
                </div>

                <div class="events-grid" id="eventsGrid">
                    <?php if (!empty($events)): ?>
                        <?php foreach ($events as $event): ?>
                            <?php
                            $docs_total = (int) ($event['docs_total'] ?? 0);
                            $docs_uploaded = (int) ($event['docs_uploaded'] ?? 0);
                            $pct = $docs_total > 0 ? round(($docs_uploaded / $docs_total) * 100) : 0;

                            $pct_color_ref = max(0, min(100, (int) $pct));
                            $hue = ($pct_color_ref / 100) * 120;
                            $progress_color = "hsl($hue, 70%, 45%)";

                            $status_class = normalizeStatusClass($event['event_status'] ?? 'Pending Review');

                            $org_clean = $event['organizing_body'] ?? '';
                            $decoded_orgs = json_decode($org_clean, true);
                            if (is_array($decoded_orgs)) {
                                $org_clean = implode(', ', $decoded_orgs);
                            } else {
                                $org_clean = str_replace(['[', ']', '"', "'"], '', $org_clean);
                                $org_clean = str_replace(',', ', ', $org_clean);
                            }
                            ?>

                            <article class="event-card">
                                <div class="event-card-top">
                                    <span class="event-type-tag">
                                        <?= htmlspecialchars($event['activity_type'] ?? 'N/A') ?>
                                    </span>

                                    <span class="event-status status-<?= htmlspecialchars($status_class) ?>">
                                        <span class="status-dot"></span>
                                        <span class="status-text">
                                            <?= htmlspecialchars($event['event_status']) ?>
                                        </span>
                                    </span>
                                </div>

                                <div class="event-card-body">
                                    <h3 class="event-title">
                                        <?= htmlspecialchars($event['event_name']) ?>
                                    </h3>

                                    <div class="event-meta">
                                        <div class="meta-row">
                                            <span class="meta-icon">
                                                <i class="fa-solid fa-user"></i>
                                            </span>
                                            <span><?= htmlspecialchars($event['user_name'] ?? 'Unknown User') ?></span>
                                        </div>

                                        <div class="meta-row">
                                            <span class="meta-icon">
                                                <i class="fa-solid fa-building-columns"></i>
                                            </span>
                                            <span><?= htmlspecialchars($event['org_body'] ?? 'No organization') ?></span>
                                        </div>

                                        <div class="meta-row">
                                            <span class="meta-icon">
                                                <i class="fa-solid fa-location-dot"></i>
                                            </span>
                                            <span><?= htmlspecialchars($event['venue_platform'] ?? 'No venue set') ?></span>
                                        </div>

                                        <div class="meta-row">
                                            <span class="meta-icon">
                                                <i class="fa-solid fa-calendar"></i>
                                            </span>
                                            <span>
                                                <?php if (!empty($event['start_datetime'])): ?>
                                                    <?= date('M j, Y', strtotime($event['start_datetime'])) ?>
                                                <?php else: ?>
                                                    No schedule set
                                                <?php endif; ?>
                                            </span>
                                        </div>

                                        <div class="meta-row">
                                            <span class="meta-icon">
                                                <i class="fa-solid fa-layer-group"></i>
                                            </span>
                                            <span><?= htmlspecialchars($event['background'] ?? 'N/A') ?></span>
                                        </div>
                                    </div>

                                    <div class="doc-progress">
                                        <div class="doc-progress-label">
                                            <span>Documents</span>
                                            <span><?= $docs_uploaded ?>/<?= $docs_total ?> uploaded</span>
                                        </div>

                                        <div class="progress-bar">
                                            <div class="progress-fill"
                                                style="width: <?= $pct ?>%; --progress-color: <?= $progress_color ?>">
                                            </div>
                                        </div>
                                    </div>

                                    <?php if (!empty($event['admin_remarks']) && $event['event_status'] === 'Needs Revision'): ?>
                                        <div class="doc-remarks-box" style="margin-top: 1rem;">
                                            <strong>Latest Remarks</strong>
                                            <p><?= nl2br(htmlspecialchars($event['admin_remarks'])) ?></p>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <footer class="event-card-footer">
                                    <span class="event-created">
                                        Submitted <?= date('M j, Y', strtotime($event['created_at'])) ?>
                                    </span>

                                    <div class="card-actions">
                                        <a href="manage_event.php?id=<?= (int) $event['event_id'] ?>"
                                            class="btn-primary btn-view">
                                            Manage Event
                                        </a>
                                    </div>
                                </footer>
                            </article>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state" style="grid-column: 1 / -1;">
                            <div class="empty-icon">
                                <i class="fa-solid fa-file-circle-xmark"></i>
                            </div>
                            <h3>No events found</h3>
                            <p>Try adjusting your search or filters.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <?php include 'assets/includes/footer.php' ?>
        </main>
    </div>

    <script src="../app/script/layout.js?v=1"></script>
</body>

</html>