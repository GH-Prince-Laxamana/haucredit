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

$current_admin_id = (int) $_SESSION["user_id"];
$search = trim($_GET['search'] ?? '');
$org_filter = trim($_GET['org'] ?? '');

/* ================= HELPERS ================= */
function normalizeRoleClass(string $role): string
{
    return strtolower(str_replace(' ', '-', $role));
}

/* ================= SUMMARY COUNTS ================= */
$summarySql = "
    SELECT
        COUNT(*) AS total_users,
        SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) AS total_admins,
        SUM(CASE WHEN role = 'user' THEN 1 ELSE 0 END) AS total_standard_users
    FROM users
";

$summary = fetchOne($conn, $summarySql, "", []);

/* ================= ORG OPTIONS ================= */
$orgOptionsSql = "
    SELECT DISTINCT org_body
    FROM users
    WHERE org_body IS NOT NULL
      AND org_body != ''
    ORDER BY org_body ASC
";

$orgOptions = fetchAll($conn, $orgOptionsSql, "", []);

/* ================= USER LIST ================= */
$fetchUsersSql = "
    SELECT
        u.user_id,
        u.user_name,
        u.user_email,
        u.stud_num,
        u.org_body,
        u.role,
        u.profile_pic,
        u.user_reg_date,

        COUNT(e.event_id) AS total_events,
        SUM(CASE WHEN e.event_status = 'Draft' THEN 1 ELSE 0 END) AS draft_count,
        SUM(CASE WHEN e.event_status = 'Pending Review' THEN 1 ELSE 0 END) AS pending_review_count,
        SUM(CASE WHEN e.event_status = 'Needs Revision' THEN 1 ELSE 0 END) AS needs_revision_count,
        SUM(CASE WHEN e.event_status = 'Approved' THEN 1 ELSE 0 END) AS approved_count,
        SUM(CASE WHEN e.event_status = 'Completed' THEN 1 ELSE 0 END) AS completed_count

    FROM users u
    LEFT JOIN events e
        ON u.user_id = e.user_id
       AND e.archived_at IS NULL
       AND e.is_system_event = 0
    WHERE 1=1
";

$params = [];
$types = "";

if ($search !== '') {
    $fetchUsersSql .= "
        AND (
            u.user_name LIKE ?
            OR u.user_email LIKE ?
            OR u.stud_num LIKE ?
            OR u.org_body LIKE ?
            OR u.role LIKE ?
        )
    ";
    $like = "%" . $search . "%";
    array_push($params, $like, $like, $like, $like, $like);
    $types .= "sssss";
}

if ($org_filter !== '') {
    $fetchUsersSql .= " AND u.org_body = ?";
    $params[] = $org_filter;
    $types .= "s";
}

$fetchUsersSql .= "
    GROUP BY
        u.user_id,
        u.user_name,
        u.user_email,
        u.stud_num,
        u.org_body,
        u.role,
        u.profile_pic,
        u.user_reg_date
    ORDER BY
        CASE u.role
            WHEN 'admin' THEN 1
            WHEN 'user' THEN 2
            ELSE 3
        END,
        u.user_reg_date DESC,
        u.user_name ASC
";

$users = fetchAll($conn, $fetchUsersSql, $types, $params);

/* ================= DERIVED COUNTS ================= */
$total_users = (int) ($summary['total_users'] ?? 0);
$total_admins = (int) ($summary['total_admins'] ?? 0);
$total_standard_users = (int) ($summary['total_standard_users'] ?? 0);

$total_with_events = 0;
foreach ($users as $user) {
    if ((int) ($user['total_events'] ?? 0) > 0) {
        $total_with_events++;
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Admin Users - HAUCREDIT</title>
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
                    <h1>Users</h1>
                    <p>Manage user accounts, roles, and access.</p>
                </div>
            </header>

            <section class="content my-events-page">
                <div class="summary-strip">
                    <div class="summary-card">
                        <span class="summary-num"><?= $total_users ?></span>
                        <span class="summary-label">Total Accounts</span>
                    </div>

                    <div class="summary-card">
                        <span class="summary-num"><?= $total_admins ?></span>
                        <span class="summary-label">Admins</span>
                    </div>

                    <div class="summary-card">
                        <span class="summary-num"><?= $total_standard_users ?></span>
                        <span class="summary-label">Standard Users</span>
                    </div>

                    <div class="summary-card">
                        <span class="summary-num"><?= $total_with_events ?></span>
                        <span class="summary-label">With Events</span>
                    </div>
                </div>

                <div class="list-toolbar" style="display:block;">
                    <form method="GET" class="search-wrap" style="margin-bottom: 1rem;">
                        <span class="search-icon">
                            <i class="fa-solid fa-magnifying-glass"></i>
                        </span>

                        <input type="text" name="search" class="search-input"
                            placeholder="Search by name, email, student number, org, role..."
                            value="<?= htmlspecialchars($search) ?>">

                        <select name="org" class="search-input" style="max-width: 240px;">
                            <option value="">All Organizations</option>
                            <?php foreach ($orgOptions as $org): ?>
                                <?php $org_value = $org['org_body'] ?? ''; ?>
                                <option value="<?= htmlspecialchars($org_value) ?>" <?= $org_filter === $org_value ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($org_value) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <button type="submit" class="btn-primary">Apply</button>

                        <?php if ($search !== '' || $org_filter !== ''): ?>
                            <a href="admin_users.php" class="btn-secondary">Reset</a>
                        <?php endif; ?>
                    </form>
                </div>

                <div class="events-grid">
                    <?php if (!empty($users)): ?>
                        <?php foreach ($users as $user): ?>
                            <?php
                            $profile_pic = !empty($user['profile_pic']) ? $user['profile_pic'] : 'default.jpg';
                            $total_events = (int) ($user['total_events'] ?? 0);
                            $draft_count = (int) ($user['draft_count'] ?? 0);
                            $pending_review_count = (int) ($user['pending_review_count'] ?? 0);
                            $needs_revision_count = (int) ($user['needs_revision_count'] ?? 0);
                            $approved_count = (int) ($user['approved_count'] ?? 0);
                            $completed_count = (int) ($user['completed_count'] ?? 0);

                            $is_self = ((int) $user['user_id'] === $current_admin_id);
                            $role_class = normalizeRoleClass($user['role'] ?? 'user');
                            ?>

                            <article class="event-card">
                                <div class="event-card-top">
                                    <span class="event-type-tag">
                                        <?= htmlspecialchars($user['org_body'] ?? 'No organization') ?>
                                    </span>

                                    <span class="event-status status-<?= htmlspecialchars($role_class) ?>">
                                        <span class="status-dot"></span>
                                        <span class="status-text">
                                            <?= htmlspecialchars(ucfirst($user['role'] ?? 'user')) ?>
                                        </span>
                                    </span>
                                </div>

                                <div class="event-card-body">
                                    <div style="display:flex; gap:1rem; align-items:center; margin-bottom:1rem;">
                                        <img src="assets/profiles/<?= htmlspecialchars($profile_pic) ?>"
                                            alt="<?= htmlspecialchars($user['user_name']) ?>"
                                            style="width:56px; height:56px; border-radius:50%; object-fit:cover;">

                                        <div>
                                            <h3 class="event-title" style="margin-bottom:.15rem;">
                                                <?= htmlspecialchars($user['user_name']) ?>
                                                <?php if ($is_self): ?>
                                                    <span style="font-size:.85rem; opacity:.7;">(You)</span>
                                                <?php endif; ?>
                                            </h3>

                                            <div class="event-meta">
                                                <div class="meta-row">
                                                    <span class="meta-icon"><i class="fa-solid fa-envelope"></i></span>
                                                    <span><?= htmlspecialchars($user['user_email']) ?></span>
                                                </div>
                                                <div class="meta-row">
                                                    <span class="meta-icon"><i class="fa-solid fa-id-card"></i></span>
                                                    <span><?= htmlspecialchars($user['stud_num']) ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="event-meta">
                                        <div class="meta-row">
                                            <span class="meta-icon"><i class="fa-solid fa-calendar-plus"></i></span>
                                            <span>
                                                Registered
                                                <?= !empty($user['user_reg_date']) ? date('M j, Y', strtotime($user['user_reg_date'])) : 'N/A' ?>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="doc-progress" style="margin-top:1rem;">
                                        <div class="doc-progress-label">
                                            <span>Event Summary</span>
                                            <span><?= $total_events ?> total events</span>
                                        </div>

                                        <div class="tracker-list" style="margin-top:.5rem;">
                                            <div class="t-row"><span>Draft</span><span
                                                    class="t-score"><?= $draft_count ?></span></div>
                                            <div class="t-row"><span>Pending Review</span><span
                                                    class="t-score"><?= $pending_review_count ?></span></div>
                                            <div class="t-row"><span>Needs Revision</span><span
                                                    class="t-score"><?= $needs_revision_count ?></span></div>
                                            <div class="t-row"><span>Approved</span><span
                                                    class="t-score"><?= $approved_count ?></span></div>
                                            <div class="t-row"><span>Completed</span><span
                                                    class="t-score"><?= $completed_count ?></span></div>
                                        </div>
                                    </div>
                                </div>

                                <footer class="event-card-footer" style="display:block;">
                                    <span class="event-created" style="display:block; margin-bottom:.75rem;">
                                        User ID #<?= (int) $user['user_id'] ?>
                                    </span>

                                    <div class="card-actions" style="flex-wrap:wrap; gap:.5rem;">
                                        <a href="admin_user_events.php?user_id=<?= (int) $user['user_id'] ?>"
                                            class="btn-secondary btn-edit">
                                            View Events
                                        </a>

                                        <?php if (!$is_self && ($user['role'] ?? '') === 'user'): ?>
                                            <form method="POST" action="admin_update_user.php">
                                                <input type="hidden" name="user_id" value="<?= (int) $user['user_id'] ?>">
                                                <input type="hidden" name="action" value="make_admin">
                                                <button type="submit" class="btn-primary btn-view">Promote to Admin</button>
                                            </form>
                                        <?php endif; ?>

                                        <?php if (!$is_self && ($user['role'] ?? '') === 'admin'): ?>
                                            <form method="POST" action="admin_update_user.php"
                                                data-confirm="Set this admin back to standard user?">
                                                <input type="hidden" name="user_id" value="<?= (int) $user['user_id'] ?>">
                                                <input type="hidden" name="action" value="make_user">
                                                <button type="submit" class="btn-secondary btn-edit">Set as User</button>
                                            </form>
                                        <?php endif; ?>

                                        <?php if (!$is_self): ?>
                                            <form method="POST" action="admin_update_user.php"
                                                data-confirm="Delete this user? This will also delete all their related records.">
                                                <input type="hidden" name="user_id" value="<?= (int) $user['user_id'] ?>">
                                                <input type="hidden" name="action" value="delete_user">
                                                <button type="submit" class="btn-primary btn-danger">Delete</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </footer>
                            </article>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state" style="grid-column: 1 / -1;">
                            <div class="empty-icon">
                                <i class="fa-solid fa-users-slash"></i>
                            </div>
                            <h3>No users found</h3>
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