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
    return strtolower(str_replace(' ', '-', trim($status)));
}

function isValidStatusFilter(string $status): bool
{
    $allowed = ['all', 'Draft', 'Pending Review', 'Needs Revision', 'Approved', 'Completed'];
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

$selected_user_id = (int) $user['user_id'];

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

$total_events = (int) ($counts['total_events'] ?? 0);
$draft_count = (int) ($counts['draft_count'] ?? 0);
$pending_review_count = (int) ($counts['pending_review_count'] ?? 0);
$needs_revision_count = (int) ($counts['needs_revision_count'] ?? 0);
$approved_count = (int) ($counts['approved_count'] ?? 0);
$completed_count = (int) ($counts['completed_count'] ?? 0);

$profile_pic = !empty($user['profile_pic']) ? $user['profile_pic'] : 'default.jpg';
$role_class = (($user['role'] ?? 'user') === 'admin') ? 'role-admin' : 'role-user';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>User Events - HAUCREDIT</title>
    <link rel="stylesheet" href="assets/styles/layout.css" />
    <link rel="stylesheet" href="assets/styles/home_styles.css" />
    <link rel="stylesheet" href="assets/styles/admin_user_events.css" />
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
                        <?= htmlspecialchars($user['org_body'] ?? 'No organization') ?>
                    </p>
                </div>

                <div class="home-top-actions">
                    <a href="admin_users.php" class="btn-secondary">Back to Users</a>
                </div>
            </header>

            <section class="content admin-user-events-page">
                <!-- User Profile -->
                <section class="user-profile-card">
                    <img src="assets/profiles/<?= htmlspecialchars($profile_pic) ?>"
                        alt="<?= htmlspecialchars($user['user_name']) ?>" class="user-profile-avatar">

                    <div class="user-profile-info">
                        <div class="user-profile-name"><?= htmlspecialchars($user['user_name']) ?></div>

                        <div class="user-profile-meta">
                            <span class="user-meta-item">
                                <i class="fa-solid fa-envelope"></i>
                                <?= htmlspecialchars($user['user_email']) ?>
                            </span>

                            <span class="user-meta-item">
                                <i class="fa-solid fa-id-card"></i>
                                <?= htmlspecialchars($user['stud_num'] ?? 'N/A') ?>
                            </span>

                            <span class="user-meta-item">
                                <i class="fa-solid fa-building-columns"></i>
                                <?= htmlspecialchars($user['org_body'] ?? 'No organization') ?>
                            </span>

                            <span class="user-meta-item">
                                <i class="fa-solid fa-calendar-plus"></i>
                                Registered
                                <?= !empty($user['user_reg_date']) ? date('M j, Y', strtotime($user['user_reg_date'])) : 'N/A' ?>
                            </span>

                            <span class="role-badge <?= htmlspecialchars($role_class) ?>">
                                <?= htmlspecialchars(ucfirst($user['role'] ?? 'user')) ?>
                            </span>
                        </div>
                    </div>
                </section>

                <!-- Stats -->
                <section class="events-stats">
                    <article class="stat-card">
                        <div class="stat-number"><?= $total_events ?></div>
                        <div class="stat-label">Total Events</div>
                    </article>

                    <article class="stat-card">
                        <div class="stat-number"><?= $draft_count ?></div>
                        <div class="stat-label">Draft</div>
                    </article>

                    <article class="stat-card">
                        <div class="stat-number"><?= $pending_review_count ?></div>
                        <div class="stat-label">Pending Review</div>
                    </article>

                    <article class="stat-card">
                        <div class="stat-number"><?= $needs_revision_count ?></div>
                        <div class="stat-label">Needs Revision</div>
                    </article>

                    <article class="stat-card">
                        <div class="stat-number"><?= $approved_count ?></div>
                        <div class="stat-label">Approved</div>
                    </article>

                    <article class="stat-card">
                        <div class="stat-number"><?= $completed_count ?></div>
                        <div class="stat-label">Completed</div>
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
                            ['value' => 'Completed', 'label' => 'Completed', 'count' => $completed_count, 'icon' => 'fa-solid fa-flag-checkered'],
                            ['value' => 'Draft', 'label' => 'Draft', 'count' => $draft_count, 'icon' => 'fa-regular fa-file']
                        ];

                        foreach ($tabs as $tab):
                            $active = $status_filter === $tab['value'] ? 'active' : '';
                            $tabUrl = 'admin_user_events.php?user_id=' . $selected_user_id
                                . '&status=' . urlencode($tab['value'])
                                . '&search=' . urlencode($search);
                            ?>
                            <a href="<?= htmlspecialchars($tabUrl) ?>" class="tab-btn <?= $active ?>">
                                <i class="<?= htmlspecialchars($tab['icon']) ?>"></i>
                                <?= htmlspecialchars($tab['label']) ?>
                                <span class="tab-count"><?= (int) $tab['count'] ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>

                    <div class="toolbar-search">
                        <form method="GET" class="toolbar-form" id="userEventsFilterForm">
                            <input type="hidden" name="user_id" value="<?= $selected_user_id ?>">
                            <input type="hidden" name="page" value="1">

                            <div class="search-wrap">
                                <i class="fa-solid fa-magnifying-glass"></i>
                                <input type="text" name="search" id="searchInput" class="search-input"
                                    placeholder="Search this user's events..." value="<?= htmlspecialchars($search) ?>">
                            </div>

                            <select name="status" class="toolbar-select auto-submit-filter">
                                <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Statuses</option>
                                <option value="Draft" <?= $status_filter === 'Draft' ? 'selected' : '' ?>>Draft</option>
                                <option value="Pending Review" <?= $status_filter === 'Pending Review' ? 'selected' : '' ?>>Pending Review</option>
                                <option value="Needs Revision" <?= $status_filter === 'Needs Revision' ? 'selected' : '' ?>>Needs Revision</option>
                                <option value="Approved" <?= $status_filter === 'Approved' ? 'selected' : '' ?>>Approved
                                </option>
                                <option value="Completed" <?= $status_filter === 'Completed' ? 'selected' : '' ?>>Completed
                                </option>
                            </select>

                            <?php if ($status_filter !== 'all' || $search !== ''): ?>
                                <a href="admin_user_events.php?user_id=<?= $selected_user_id ?>" class="btn-filter">
                                    <i class="fa-solid fa-xmark"></i> Reset
                                </a>
                            <?php endif; ?>
                        </form>
                    </div>
                </section>

                <!-- Events Table -->
                <section class="events-table-container">
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
                                $dot_colors = ['#f59e0b', '#10b981', '#ef4444', '#3b82f6', '#8b5cf6', '#ec4899', '#06b6d4', '#84cc16'];
                                $i = 0;
                                ?>

                                <?php foreach ($events as $event): ?>
                                    <?php
                                    $dot = $dot_colors[$i % count($dot_colors)];
                                    $i++;

                                    $status = $event['event_status'] ?? 'Draft';
                                    $badge_class = match ($status) {
                                        'Pending Review' => 'badge-pending-review',
                                        'Needs Revision' => 'badge-needs-revision',
                                        'Approved' => 'badge-approved',
                                        'Completed' => 'badge-completed',
                                        default => 'badge-draft',
                                    };

                                    $docs_total = (int) ($event['docs_total'] ?? 0);
                                    $docs_uploaded = (int) ($event['docs_uploaded'] ?? 0);
                                    $pct = $docs_total > 0 ? round(($docs_uploaded / $docs_total) * 100) : 0;
                                    $hue = ($pct / 100) * 120;
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
                                                <span class="event-color-dot"
                                                    style="background:<?= htmlspecialchars($dot) ?>"></span>
                                                <div>
                                                    <div class="event-name-text"><?= htmlspecialchars($event['event_name']) ?>
                                                    </div>
                                                    <div class="event-subtext">
                                                        <?= htmlspecialchars($event['activity_type'] ?? 'N/A') ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>

                                        <td class="muted-cell">
                                            <?= htmlspecialchars($event['background'] ?? 'N/A') ?>
                                        </td>

                                        <td class="muted-cell">
                                            <?= htmlspecialchars($event['venue_platform'] ?? '—') ?>
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

                                            <?php if (!empty($event['admin_remarks']) && $status === 'Needs Revision'): ?>
                                                <div class="event-inline-note"
                                                    title="<?= htmlspecialchars($event['admin_remarks']) ?>">
                                                    <?= htmlspecialchars($event['admin_remarks']) ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>

                                        <td>
                                            <div class="progress-wrap">
                                                <div class="progress-bar">
                                                    <div class="progress-fill"
                                                        style="width:<?= $pct ?>%; background:<?= htmlspecialchars($progress_color) ?>;">
                                                    </div>
                                                </div>
                                                <span class="progress-text"><?= $docs_uploaded ?>/<?= $docs_total ?></span>
                                            </div>
                                        </td>

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
                                    <td colspan="7">
                                        <div class="empty-state">
                                            <i class="fa-regular fa-calendar-xmark"></i>
                                            <p>This user has no matching events for the current filter.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </section>

                <?php include 'assets/includes/footer.php'; ?>
            </section>
        </main>
    </div>

    <script src="../app/script/layout.js?v=1"></script>
    <script>
        (function () {
            const form = document.getElementById('userEventsFilterForm');
            if (!form) return;

            const autoSubmitFields = form.querySelectorAll('.auto-submit-filter');
            const searchInput = document.getElementById('searchInput');

            autoSubmitFields.forEach(field => {
                field.addEventListener('change', function () {
                    form.submit();
                });
            });

            if (searchInput) {
                let searchTimer;

                searchInput.addEventListener('input', function () {
                    clearTimeout(searchTimer);
                    searchTimer = setTimeout(function () {
                        form.submit();
                    }, 500);
                });

                searchInput.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        clearTimeout(searchTimer);
                        form.submit();
                    }
                });
            }
        })();
    </script>
</body>

</html>