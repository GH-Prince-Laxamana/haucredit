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

$success = $_SESSION["success"] ?? "";
$error = $_SESSION["error"] ?? "";
unset($_SESSION["success"], $_SESSION["error"]);

/* ================= CSRF ================= */
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION["csrf_token"];

/* ================= HELPERS ================= */
function normalizeRoleClass(string $role): string {
    return strtolower(str_replace(' ', '-', trim($role)));
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

/* ================= ORG OPTIONS FROM CONFIG ================= */
$orgOptionsSql = "
    SELECT org_name FROM config_org_options
    WHERE is_active = 1
    ORDER BY sort_order ASC, org_name ASC
";
$orgOptions = fetchAll($conn, $orgOptionsSql, "", []);

/* ================= USER LIST ================= */
$fetchUsersSql = "
    SELECT
        u.user_id, u.user_name, u.user_email, u.stud_num, u.org_body,
        u.role, u.profile_pic, u.user_reg_date,
        COUNT(e.event_id) AS total_events,
        SUM(CASE WHEN e.event_status = 'Draft'          THEN 1 ELSE 0 END) AS draft_count,
        SUM(CASE WHEN e.event_status = 'Pending Review' THEN 1 ELSE 0 END) AS pending_review_count,
        SUM(CASE WHEN e.event_status = 'Needs Revision' THEN 1 ELSE 0 END) AS needs_revision_count,
        SUM(CASE WHEN e.event_status = 'Approved'       THEN 1 ELSE 0 END) AS approved_count,
        SUM(CASE WHEN e.event_status = 'Completed'      THEN 1 ELSE 0 END) AS completed_count
    FROM users u
    LEFT JOIN events e ON u.user_id = e.user_id AND e.archived_at IS NULL AND e.is_system_event = 0
    WHERE 1=1
";

$params = []; $types = "";

if ($search !== '') {
    $fetchUsersSql .= " AND (u.user_name LIKE ? OR u.user_email LIKE ? OR u.stud_num LIKE ? OR u.org_body LIKE ? OR u.role LIKE ?)";
    $like = "%" . $search . "%";
    array_push($params, $like, $like, $like, $like, $like);
    $types .= "sssss";
}

if ($org_filter !== '') {
    $fetchUsersSql .= " AND u.org_body = ?";
    $params[] = $org_filter; $types .= "s";
}

$fetchUsersSql .= "
    GROUP BY u.user_id, u.user_name, u.user_email, u.stud_num, u.org_body, u.role, u.profile_pic, u.user_reg_date
    ORDER BY CASE u.role WHEN 'admin' THEN 1 WHEN 'user' THEN 2 ELSE 3 END, u.user_reg_date DESC, u.user_name ASC
";

$users = fetchAll($conn, $fetchUsersSql, $types, $params);

/* ================= DERIVED COUNTS ================= */
$total_users          = (int)($summary['total_users'] ?? 0);
$total_admins         = (int)($summary['total_admins'] ?? 0);
$total_standard_users = (int)($summary['total_standard_users'] ?? 0);

$total_with_events = 0;
foreach ($users as $user) {
    if ((int)($user['total_events'] ?? 0) > 0) $total_with_events++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Users - HAUCREDIT</title>
    <link rel="stylesheet" href="assets/styles/layout.css">
    <link rel="stylesheet" href="assets/styles/home_styles.css">
    <style>
        /* ===== STAT CARDS ===== */
        .users-stats {
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
            transition: all 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.10);
        }

        .stat-number {
            font-size: 28px;
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

        /* ===== NOTICES ===== */
        .notice {
            padding: 12px 16px;
            border-radius: var(--radius);
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 16px;
        }

        .notice.success {
            background: rgba(16,185,129,0.1);
            color: #059669;
            border: 1px solid rgba(16,185,129,0.2);
        }

        .notice.error {
            background: rgba(239,68,68,0.1);
            color: #dc2626;
            border: 1px solid rgba(239,68,68,0.2);
        }

        /* ===== ADD USER CARD ===== */
        .add-user-card {
            background: white;
            border-radius: var(--radius);
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            margin-bottom: 20px;
            overflow: hidden;
        }

        .card-header {
            padding: 14px 20px;
            background: #fdfcfa;
            border-bottom: 1px solid var(--border);
        }

        .card-header h2 {
            font-size: 15px;
            font-weight: 700;
            color: var(--burgundy);
            margin: 0;
        }

        .card-body { padding: 20px; }

        .add-user-form {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .form-input {
            width: 100%;
            padding: 9px 12px;
            border: 1px solid var(--border-fields);
            border-radius: 9px;
            font-size: 13px;
            font-family: inherit;
            background: white;
            color: var(--text-primary);
            transition: all 0.2s;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--gold);
            box-shadow: 0 0 0 3px rgba(194,161,77,0.12);
        }

        .form-actions {
            grid-column: 1 / -1;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            align-items: center;
        }

        /* ===== TOOLBAR ===== */
        .users-toolbar {
            background: white;
            border-radius: var(--radius);
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            margin-bottom: 20px;
            overflow: hidden;
        }

        .toolbar-search {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 20px;
            flex-wrap: wrap;
        }

        .search-wrap {
            position: relative;
            flex: 1;
            min-width: 200px;
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

        .filter-select {
            padding: 9px 12px;
            border: 1px solid var(--border-fields);
            border-radius: 9px;
            font-size: 13px;
            font-family: inherit;
            background: white;
            color: var(--text-primary);
            cursor: pointer;
        }

        .filter-select:focus {
            outline: none;
            border-color: var(--gold);
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

        /* ===== USER CARDS GRID ===== */
        .users-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }

        .user-card {
            background: white;
            border-radius: var(--radius);
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transition: all 0.2s ease;
        }

        .user-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.10);
        }

        /* Card top bar */
        .user-card-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 16px;
            background: #fdfcfa;
            border-bottom: 1px solid var(--border);
        }

        .user-id-tag {
            font-size: 11px;
            font-weight: 600;
            color: var(--text-secondary);
        }

        /* Role badge */
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

        /* Card body */
        .user-card-body {
            padding: 16px;
            flex: 1;
        }

        .user-profile-row {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 14px;
        }

        .user-avatar {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--border);
            flex-shrink: 0;
        }

        .user-name {
            font-size: 15px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 2px;
        }

        .user-self-tag {
            font-size: 12px;
            color: var(--text-secondary);
            font-weight: 400;
        }

        .user-meta-row {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            color: var(--text-secondary);
            margin-bottom: 3px;
        }

        .user-meta-row i { color: var(--gold); font-size: 11px; width: 14px; }

        /* Event summary */
        .event-summary {
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid var(--border);
        }

        .event-summary-title {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-secondary);
            margin-bottom: 8px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 4px 0;
            font-size: 12px;
            color: var(--text-secondary);
            border-bottom: 1px solid #f5f2ed;
        }

        .summary-row:last-child { border-bottom: none; }

        .summary-val {
            font-weight: 700;
            color: var(--text-primary);
        }

        /* Card footer — actions stacked below */
        .user-card-footer {
            padding: 12px 16px;
            background: #fdfcfa;
            border-top: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .footer-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .btn-danger {
            background: rgba(239,68,68,0.08) !important;
            color: #dc2626 !important;
            border: 1px solid rgba(239,68,68,0.2) !important;
            box-shadow: none !important;
        }

        .btn-danger:hover {
            background: #dc2626 !important;
            color: white !important;
        }

        /* ===== EMPTY STATE ===== */
        .empty-state {
            text-align: center;
            padding: 64px 20px;
            color: var(--text-secondary);
            grid-column: 1 / -1;
        }

        .empty-state i {
            font-size: 40px;
            color: #ccc;
            margin-bottom: 14px;
            display: block;
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 1100px) { .users-stats { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 768px)  {
            .add-user-form { grid-template-columns: 1fr; }
            .add-user-form select[style] { grid-column: 1; }
            .users-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 480px)  { .users-stats { grid-template-columns: 1fr; } }
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
                <h1>Users</h1>
                <p>Manage user accounts, roles, and access.</p>
            </div>
        </div>

        <!-- Notices -->
        <?php if ($success !== ""): ?>
            <div class="notice success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if ($error !== ""): ?>
            <div class="notice error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- Stat Cards (no icons) -->
        <div class="users-stats">
            <div class="stat-card">
                <div class="stat-number"><?= $total_users ?></div>
                <div class="stat-label">Total Accounts</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $total_admins ?></div>
                <div class="stat-label">Admins</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $total_standard_users ?></div>
                <div class="stat-label">Standard Users</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $total_with_events ?></div>
                <div class="stat-label">With Events</div>
            </div>
        </div>

        <!-- Add User Card -->
        <div class="add-user-card">
            <div class="card-header">
                <h2>Add User</h2>
            </div>
            <div class="card-body">
                <form method="POST" action="admin_update_user.php" class="add-user-form" id="addUserForm" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <input type="hidden" name="action" value="add_user">

                    <!-- Row 1: Username + Email -->
                    <input type="text" name="user_name" class="form-input" placeholder="Username"
                           minlength="4" maxlength="50" pattern="[A-Za-z0-9._-]{4,50}" required>

                    <input type="email" name="user_email" class="form-input" placeholder="name@student.hau.edu.ph"
                           pattern="^[A-Za-z0-9._%+-]+@student\.hau\.edu\.ph$" required>

                    <!-- Row 2: Student number + Organization -->
                    <input type="text" name="stud_num" class="form-input" placeholder="20XXXXXX"
                           inputmode="numeric" minlength="8" maxlength="20" pattern="[0-9]{8,20}" required>

                    <select name="org_body" class="form-input" required>
                        <option value="">Select organization</option>
                        <?php foreach ($orgOptions as $org): ?>
                            <option value="<?= htmlspecialchars($org['org_name']) ?>">
                                <?= htmlspecialchars($org['org_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <!-- Row 3: Password + Confirm password -->
                    <input type="password" name="user_password" class="form-input"
                           placeholder="Temporary password" minlength="8" maxlength="255"
                           pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9]).{8,}" required>

                    <input type="password" name="confirm_password" class="form-input"
                           placeholder="Confirm password" minlength="8" maxlength="255" required>

                    <!-- Row 4: Role full width -->
                    <select name="role" class="form-input" style="grid-column: 1 / -1;" required>
                        <option value="user">Standard User</option>
                        <option value="admin">Admin</option>
                    </select>

                    <div id="addUserFormError" class="notice error" style="display:none; grid-column:1/-1; margin:0;"></div>

                    <div class="form-actions">
                        <button type="submit" class="btn-primary">Create User</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Search Toolbar -->
        <div class="users-toolbar">
            <div class="toolbar-search">
                <div class="search-wrap">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" id="searchInput" class="search-input"
                           placeholder="Search by name, email, student number, org, role..."
                           value="<?= htmlspecialchars($search) ?>">
                </div>
                <select id="orgSelect" class="filter-select">
                    <option value="">All Organizations</option>
                    <?php foreach ($orgOptions as $org):
                        $val = $org['org_name'] ?? '';
                    ?>
                        <option value="<?= htmlspecialchars($val) ?>" <?= $org_filter === $val ? 'selected' : '' ?>>
                            <?= htmlspecialchars($val) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button class="btn-filter" id="applyFilters">
                    <i class="fa-solid fa-sliders"></i> Search
                </button>
                <?php if ($search !== '' || $org_filter !== ''): ?>
                    <a href="admin_users.php" class="btn-filter">
                        <i class="fa-solid fa-xmark"></i> Clear
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Users Grid -->
        <div class="users-grid">
            <?php if (!empty($users)): ?>
                <?php foreach ($users as $user):
                    $profile_pic          = !empty($user['profile_pic']) ? $user['profile_pic'] : 'default.jpg';
                    $total_events         = (int)($user['total_events'] ?? 0);
                    $draft_count          = (int)($user['draft_count'] ?? 0);
                    $pending_review_count = (int)($user['pending_review_count'] ?? 0);
                    $needs_revision_count = (int)($user['needs_revision_count'] ?? 0);
                    $approved_count       = (int)($user['approved_count'] ?? 0);
                    $completed_count      = (int)($user['completed_count'] ?? 0);
                    $is_self              = ((int)$user['user_id'] === $current_admin_id);
                    $role_class           = $user['role'] === 'admin' ? 'role-admin' : 'role-user';
                ?>
                <div class="user-card">

                    <!-- Top: Org + Role badge -->
                    <div class="user-card-top">
                        <span class="org-tag"><?= htmlspecialchars($user['org_body'] ?? 'No organization') ?></span>
                        <span class="role-badge <?= $role_class ?>">
                            <?= htmlspecialchars(ucfirst($user['role'] ?? 'user')) ?>
                        </span>
                    </div>

                    <!-- Body -->
                    <div class="user-card-body">
                        <div class="user-profile-row">
                            <img src="assets/profiles/<?= htmlspecialchars($profile_pic) ?>"
                                 alt="<?= htmlspecialchars($user['user_name']) ?>"
                                 class="user-avatar">
                            <div>
                                <div class="user-name">
                                    <?= htmlspecialchars($user['user_name']) ?>
                                    <?php if ($is_self): ?>
                                        <span class="user-self-tag">(You)</span>
                                    <?php endif; ?>
                                </div>
                                <div class="user-meta-row">
                                    <i class="fa-solid fa-envelope"></i>
                                    <?= htmlspecialchars($user['user_email']) ?>
                                </div>
                                <div class="user-meta-row">
                                    <i class="fa-solid fa-id-card"></i>
                                    <?= htmlspecialchars($user['stud_num']) ?>
                                </div>
                                <div class="user-meta-row">
                                    <i class="fa-solid fa-building-columns"></i>
                                    <?= htmlspecialchars($user['org_body'] ?? 'No organization') ?>
                                </div>
                                <div class="user-meta-row">
                                    <i class="fa-solid fa-calendar-plus"></i>
                                    Registered <?= !empty($user['user_reg_date']) ? date('M j, Y', strtotime($user['user_reg_date'])) : 'N/A' ?>
                                </div>
                            </div>
                        </div>

                        <!-- Event Summary -->
                        <div class="event-summary">
                            <div class="event-summary-title">
                                Event Summary &mdash; <?= $total_events ?> total
                            </div>
                            <div class="summary-row"><span>Draft</span><span class="summary-val"><?= $draft_count ?></span></div>
                            <div class="summary-row"><span>Pending Review</span><span class="summary-val"><?= $pending_review_count ?></span></div>
                            <div class="summary-row"><span>Needs Revision</span><span class="summary-val"><?= $needs_revision_count ?></span></div>
                            <div class="summary-row"><span>Approved</span><span class="summary-val"><?= $approved_count ?></span></div>
                            <div class="summary-row"><span>Completed</span><span class="summary-val"><?= $completed_count ?></span></div>
                        </div>
                    </div>

                    <!-- Footer: actions stacked -->
                    <div class="user-card-footer">
                        <span class="user-id-tag">User ID #<?= (int)$user['user_id'] ?></span>
                        <div class="footer-actions">
                            <a href="admin_user_events.php?user_id=<?= (int)$user['user_id'] ?>" class="btn-secondary btn-smaller">
                                View Events
                            </a>

                            <?php if (!$is_self && ($user['role'] ?? '') === 'user'): ?>
                                <form method="POST" action="admin_update_user.php" style="margin:0;">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                    <input type="hidden" name="user_id" value="<?= (int)$user['user_id'] ?>">
                                    <input type="hidden" name="action" value="make_admin">
                                    <button type="submit" class="btn-primary btn-smaller">Promote</button>
                                </form>
                            <?php endif; ?>

                            <?php if (!$is_self && ($user['role'] ?? '') === 'admin'): ?>
                                <form method="POST" action="admin_update_user.php"
                                      data-confirm="Set this admin back to standard user?" style="margin:0;">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                    <input type="hidden" name="user_id" value="<?= (int)$user['user_id'] ?>">
                                    <input type="hidden" name="action" value="make_user">
                                    <button type="submit" class="btn-secondary btn-smaller">Set as User</button>
                                </form>
                            <?php endif; ?>

                            <?php if (!$is_self): ?>
                                <form method="POST" action="admin_update_user.php"
                                      data-confirm="Delete this user? This will also delete all their related records." style="margin:0;">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                    <input type="hidden" name="user_id" value="<?= (int)$user['user_id'] ?>">
                                    <input type="hidden" name="action" value="delete_user">
                                    <button type="submit" class="btn-primary btn-smaller btn-danger">Delete</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fa-solid fa-users-slash"></i>
                    <p>No users found. Try adjusting your search or filters.</p>
                </div>
            <?php endif; ?>
        </div>

        <?php include 'assets/includes/footer.php'; ?>
    </main>
</div>

<script>
    document.getElementById('applyFilters').addEventListener('click', function () {
        const search = document.getElementById('searchInput').value;
        const org    = document.getElementById('orgSelect').value;
        window.location.href = `?search=${encodeURIComponent(search)}&org=${encodeURIComponent(org)}`;
    });

    document.getElementById('searchInput').addEventListener('keydown', function (e) {
        if (e.key === 'Enter') document.getElementById('applyFilters').click();
    });

    document.querySelectorAll('form[data-confirm]').forEach(form => {
        form.addEventListener('submit', function (e) {
            if (!confirm(this.dataset.confirm)) e.preventDefault();
        });
    });
</script>
<script src="../app/script/layout.js?v=1"></script>
</body>
</html>