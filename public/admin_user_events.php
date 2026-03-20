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

/* ================= FETCH USER ================= */
$user = fetchOne(
    $conn,
    "
    SELECT
        user_id,
        user_name,
        user_email,
        stud_num,
        org_body,
        role,
        profile_pic,
        user_reg_date
    FROM users
    WHERE user_id = ?
    LIMIT 1
    ",
    "i",
    [$user_id]
);

if (!$user) {
    popup_error("User not found.");
}

/* ================= USER EVENT COUNTS ================= */
$countSql = "
    SELECT
        COUNT(*) AS total_events,
        SUM(CASE WHEN event_status = 'Draft' THEN 1 ELSE 0 END) AS draft_count,
        SUM(CASE WHEN event_status = 'Pending Review' THEN 1 ELSE 0 END) AS pending_review_count,
        SUM(CASE WHEN event_status = 'Needs Revision' THEN 1 ELSE 0 END) AS needs_revision_count,
        SUM(CASE WHEN event_status = 'Approved' THEN 1 ELSE 0 END) AS approved_count,
        SUM(CASE WHEN event_status = 'Completed' THEN 1 ELSE 0 END) AS completed_count
    FROM events
    WHERE user_id = ?
      AND archived_at IS NULL
      AND is_system_event = 0
";

$counts = fetchOne($conn, $countSql, "i", [$user_id]);

/* ================= FETCH USER EVENTS ================= */
$fetchEventsSql = "
    SELECT
        e.event_id,
        e.event_name,
        e.nature,
        e.organizing_body,
        e.event_status,
        e.admin_remarks,
        e.docs_total,
        e.docs_uploaded,
        e.created_at,
        e.updated_at,

        et.activity_type_id,
        et.background_id,
        cat.activity_type_name AS activity_type,
        cbo.background_name AS background,

        ed.start_datetime,
        ed.end_datetime,

        el.venue_platform
    FROM events e
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
    WHERE e.user_id = ?
      AND e.archived_at IS NULL
      AND e.is_system_event = 0
";

$params = [$user_id];
$types = "i";

if ($status_filter !== 'all') {
    $fetchEventsSql .= " AND e.event_status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if ($search !== '') {
    $fetchEventsSql .= "
        AND (
            e.event_name LIKE ?
            OR e.nature LIKE ?
            OR cat.activity_type_name LIKE ?
            OR cbo.background_name LIKE ?
            OR el.venue_platform LIKE ?
        )
    ";
    $like = "%" . $search . "%";
    array_push($params, $like, $like, $like, $like, $like);
    $types .= "sssss";
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
    <title>User Events - HAUCREDIT</title>
    <link rel="stylesheet" href="assets/styles/layout.css" />
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
                    <h1>User Events</h1>
                    <p><?= htmlspecialchars($user['user_name']) ?> •
                        <?= htmlspecialchars($user['org_body'] ?? 'No organization') ?></p>
                </div>

                <div class="action-btns">
                    <a href="admin_users.php" class="btn-secondary">Back to Users</a>
                </div>
            </header>

            <section class="content my-events-page">
                <section class="detail-card" style="margin-bottom: 1.25rem;">
                    <div class="card-body">
                        <div style="display:flex; gap:1rem; align-items:center;">
                            <img src="assets/profiles/<?= htmlspecialchars(!empty($user['profile_pic']) ? $user['profile_pic'] : 'default.jpg') ?>"
                                alt="<?= htmlspecialchars($user['user_name']) ?>"
                                style="width:72px; height:72px; border-radius:50%; object-fit:cover;">

                            <div>
                                <h2 style="margin:0 0 .35rem 0;"><?= htmlspecialchars($user['user_name']) ?></h2>
                                <div class="event-meta">
                                    <div class="meta-row">
                                        <span class="meta-icon"><i class="fa-solid fa-envelope"></i></span>
                                        <span><?= htmlspecialchars($user['user_email']) ?></span>
                                    </div>
                                    <div class="meta-row">
                                        <span class="meta-icon"><i class="fa-solid fa-id-card"></i></span>
                                        <span><?= htmlspecialchars($user['stud_num']) ?></span>
                                    </div>
                                    <div class="meta-row">
                                        <span class="meta-icon"><i class="fa-solid fa-building-columns"></i></span>
                                        <span><?= htmlspecialchars($user['org_body'] ?? 'No organization') ?></span>
                                    </div>
                                    <div class="meta-row">
                                        <span class="meta-icon"><i class="fa-solid fa-user-shield"></i></span>
                                        <span><?= htmlspecialchars(ucfirst($user['role'] ?? 'user')) ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <div class="summary-strip">
                    <div class="summary-card">
                        <span class="summary-num"><?= (int) ($counts['total_events'] ?? 0) ?></span>
                        <span class="summary-label">Total Events</span>
                    </div>

                    <div class="summary-card">
                        <span class="summary-num"><?= (int) ($counts['draft_count'] ?? 0) ?></span>
                        <span class="summary-label">Draft</span>
                    </div>

                    <div class="summary-card">
                        <span class="summary-num"><?= (int) ($counts['pending_review_count'] ?? 0) ?></span>
                        <span class="summary-label">Pending Review</span>
                    </div>

                    <div class="summary-card">
                        <span class="summary-num"><?= (int) ($counts['needs_revision_count'] ?? 0) ?></span>
                        <span class="summary-label">Needs Revision</span>
                    </div>

                    <div class="summary-card">
                        <span class="summary-num"><?= (int) ($counts['approved_count'] ?? 0) ?></span>
                        <span class="summary-label">Approved</span>
                    </div>

                    <div class="summary-card">
                        <span class="summary-num"><?= (int) ($counts['completed_count'] ?? 0) ?></span>
                        <span class="summary-label">Completed</span>
                    </div>
                </div>

                <div class="list-toolbar" style="display:block;">
                    <form method="GET" class="search-wrap" style="margin-bottom: 1rem;">
                        <input type="hidden" name="user_id" value="<?= (int) $user_id ?>">

                        <span class="search-icon">
                            <i class="fa-solid fa-magnifying-glass"></i>
                        </span>

                        <input type="text" name="search" class="search-input" placeholder="Search this user's events..."
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

                        <button type="submit" class="btn-primary">Apply</button>

                        <?php if ($status_filter !== 'all' || $search !== ''): ?>
                            <a href="admin_user_events.php?user_id=<?= (int) $user_id ?>" class="btn-secondary">Reset</a>
                        <?php endif; ?>
                    </form>
                </div>

                <div class="events-grid">
                    <?php if (!empty($events)): ?>
                        <?php foreach ($events as $event): ?>
                            <?php
                            $docs_total = (int) ($event['docs_total'] ?? 0);
                            $docs_uploaded = (int) ($event['docs_uploaded'] ?? 0);
                            $pct = $docs_total > 0 ? round(($docs_uploaded / $docs_total) * 100) : 0;

                            $pct_color_ref = max(0, min(100, (int) $pct));
                            $hue = ($pct_color_ref / 100) * 120;
                            $progress_color = "hsl($hue, 70%, 45%)";

                            $status_class = normalizeStatusClass($event['event_status'] ?? 'Draft');

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
                                            <span class="meta-icon"><i class="fa-solid fa-building-columns"></i></span>
                                            <span><?= htmlspecialchars($org_clean) ?></span>
                                        </div>

                                        <div class="meta-row">
                                            <span class="meta-icon"><i class="fa-solid fa-layer-group"></i></span>
                                            <span><?= htmlspecialchars($event['background'] ?? 'N/A') ?></span>
                                        </div>

                                        <div class="meta-row">
                                            <span class="meta-icon"><i class="fa-solid fa-location-dot"></i></span>
                                            <span><?= htmlspecialchars($event['venue_platform'] ?? 'No venue set') ?></span>
                                        </div>

                                        <div class="meta-row">
                                            <span class="meta-icon"><i class="fa-solid fa-calendar"></i></span>
                                            <span>
                                                <?php if (!empty($event['start_datetime'])): ?>
                                                    <?= date('M j, Y', strtotime($event['start_datetime'])) ?>
                                                <?php else: ?>
                                                    No schedule set
                                                <?php endif; ?>
                                            </span>
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
                                            <strong>Admin Remarks</strong>
                                            <p><?= nl2br(htmlspecialchars($event['admin_remarks'])) ?></p>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <footer class="event-card-footer">
                                    <span class="event-created">
                                        Created <?= date('M j, Y', strtotime($event['created_at'])) ?>
                                    </span>

                                    <div class="card-actions">
                                        <a href="admin_manage_event.php?id=<?= (int) $event['event_id'] ?>"
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
                            <p>This user has no matching events for the current filter.</p>
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