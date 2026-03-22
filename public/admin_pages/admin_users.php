<?php
session_start();
require_once __DIR__ . '/../../app/database.php';
require_once APP_PATH . "security_headers.php";
send_security_headers();

requireAdmin();

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

// ==================== HELPER FUNCTIONS ====================

/**
 * HELPER: normalizeRoleClass
 * PURPOSE: Convert user role to CSS class name for styling
 * USAGE: Converts "admin" → "admin", "user" → "user" for badge styling
 * 
 * @param string $role The user role string
 * @return string CSS class-friendly role name
 */
function normalizeRoleClass(string $role): string
{
    return strtolower(str_replace(' ', '-', trim($role)));
}

// ==================== FETCH USER SUMMARY COUNTS ====================
// Aggregate query: count total users and break down by role
// Used for summary statistics cards at top of page
$summarySql = "
    SELECT
        COUNT(*) AS total_users,
        SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) AS total_admins,
        SUM(CASE WHEN role = 'user' THEN 1 ELSE 0 END) AS total_standard_users
    FROM users
";

$summary = fetchOne($conn, $summarySql, "", []);

// ==================== FETCH ORGANIZATION OPTIONS ====================
// Query for all active organizations (for add user form and org filter dropdown)
// Sorted by sort_order first, then by name for consistent display
$orgOptionsSql = "
    SELECT org_name
    FROM config_org_options
    WHERE is_active = 1
    ORDER BY sort_order ASC, org_name ASC
";

$orgOptions = fetchAll($conn, $orgOptionsSql, "", []);

// ==================== FETCH USER LIST WITH EVENT COUNTS ====================
// Complex query: get all users with their event statistics by status
// JOINs with events table (LEFT JOIN for users with no events) and aggregates by status
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

// ============= FILTER: SEARCH =============
// If search query provided, filter users by multiple fields with LIKE
// Searchable fields: username, email, student number, organization, role
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

// ============= FILTER: ORGANIZATION =============
// If organization filter selected, show only users from that organization
if ($org_filter !== '') {
    $fetchUsersSql .= " AND u.org_body = ?";
    $params[] = $org_filter;
    $types .= "s";
}

// ==================== GROUP AND SORT USERS ====================
// Group by user fields to aggregate event counts, then sort for display
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

// ==================== EXTRACT SUMMARY COUNTS ====================
// Convert null counts to 0 for safe arithmetic
$total_users = (int) ($summary['total_users'] ?? 0);
$total_admins = (int) ($summary['total_admins'] ?? 0);
$total_standard_users = (int) ($summary['total_standard_users'] ?? 0);

// ==================== CALCULATE USERS WITH EVENTS ====================
// Count how many users have created at least one event
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
    <link rel="stylesheet" href="<?= PUBLIC_URL ?>assets/styles/layout.css" />
    <link rel="stylesheet" href="<?= PUBLIC_URL ?>assets/styles/home_styles.css" />
    <link rel="stylesheet" href="<?= PUBLIC_URL ?>assets/styles/admin_users.css" />
</head>

<body>
    <div class="app">
        <div class="sidebar-overlay" id="sidebarOverlay" hidden></div>

        <?php include PUBLIC_PATH . 'assets/includes/admin_nav.php'; ?>

        <main class="main">
            <header class="topbar">
                <button class="hamburger" id="menuBtn" type="button" aria-label="Open menu">☰</button>

                <div class="title-wrap">
                    <h1>Users</h1>
                    <p>Manage user accounts, roles, and access.</p>
                </div>
            </header>

            <!-- ==================== MAIN CONTENT SECTION ==================== -->
            <section class="content admin-users-page">
                <?php if ($success !== ""): ?>
                    <div class="notice success"><?= htmlspecialchars($success) ?></div>
                <?php endif; ?>

                <?php if ($error !== ""): ?>
                    <div class="notice error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <!-- Stat Cards -->
                <section class="users-stats">
                    <article class="stat-card">
                        <div class="stat-number"><?= $total_users ?></div>
                        <div class="stat-label">Total Accounts</div>
                    </article>

                    <article class="stat-card">
                        <div class="stat-number"><?= $total_admins ?></div>
                        <div class="stat-label">Admins</div>
                    </article>

                    <article class="stat-card">
                        <div class="stat-number"><?= $total_standard_users ?></div>
                        <div class="stat-label">Standard Users</div>
                    </article>

                    <article class="stat-card">
                        <div class="stat-number"><?= $total_with_events ?></div>
                        <div class="stat-label">With Events</div>
                    </article>
                </section>

                <!-- ==================== ADD USER FORM ==================== -->
                <!-- Form for creating new user accounts -->
                <section class="add-user-card">
                    <div class="card-header">
                        <h2>Add User</h2>
                    </div>

                    <div class="card-body">
                        <form method="POST" action="admin_update_user.php" class="add-user-form" id="addUserForm"
                            novalidate>
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                            <input type="hidden" name="action" value="add_user">

                            <input type="text" id="user_name" name="user_name" class="form-input" placeholder="Username"
                                minlength="4" maxlength="50" pattern="[A-Za-z0-9._-]{4,50}"
                                title="Username must be 4-50 characters and may contain letters, numbers, dot, underscore, and hyphen only."
                                required>

                            <input type="email" id="user_email" name="user_email" class="form-input"
                                placeholder="name@student.hau.edu.ph"
                                pattern="^[A-Za-z0-9._%+-]+@student\.hau\.edu\.ph$"
                                title="Email must be a valid HAU student email." required>

                            <input type="text" id="stud_num" name="stud_num" class="form-input" placeholder="20XXXXXX"
                                inputmode="numeric" minlength="8" maxlength="20" pattern="[0-9]{8,20}"
                                title="Student number must contain 8 to 20 digits only." required>

                            <select name="org_body" id="org_body" class="form-input" required>
                                <option value="">Select organization</option>
                                <?php foreach ($orgOptions as $org): ?>
                                    <option value="<?= htmlspecialchars($org['org_name']) ?>">
                                        <?= htmlspecialchars($org['org_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <input type="password" id="user_password" name="user_password" class="form-input"
                                placeholder="Temporary password" minlength="8" maxlength="255"
                                pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9]).{8,}"
                                title="Password must be at least 8 characters and include uppercase, lowercase, and number."
                                required>

                            <input type="password" id="confirm_password" name="confirm_password" class="form-input"
                                placeholder="Confirm password" minlength="8" maxlength="255" required>

                            <select name="role" id="role" class="form-input full-span" required>
                                <option value="user">Standard User</option>
                                <option value="admin">Admin</option>
                            </select>

                            <div id="addUserFormError" class="notice error form-error-hidden"></div>

                            <div class="form-actions">
                                <button type="submit" class="btn-primary">Create User</button>
                            </div>
                        </form>
                    </div>
                </section>

                <!-- ==================== SEARCH AND FILTER TOOLBAR ==================== -->
                <!-- Contains search input and organization filter dropdown -->
                <section class="users-toolbar">
                    <form method="GET" class="toolbar-search" id="usersFilterForm">
                        <div class="search-wrap">
                            <i class="fa-solid fa-magnifying-glass"></i>
                            <input type="text" id="searchInput" name="search" class="search-input"
                                placeholder="Search by name, email, student number, org, role..."
                                value="<?= htmlspecialchars($search) ?>">
                        </div>

                        <select name="org" id="orgSelect" class="filter-select auto-submit-filter">
                            <option value="">All Organizations</option>
                            <?php foreach ($orgOptions as $org): ?>
                                <?php $org_value = $org['org_name'] ?? ''; ?>
                                <option value="<?= htmlspecialchars($org_value) ?>" <?= $org_filter === $org_value ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($org_value) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <?php if ($search !== '' || $org_filter !== ''): ?>
                            <a href="admin_users.php" class="btn-filter">
                                <i class="fa-solid fa-xmark"></i> Clear
                            </a>
                        <?php endif; ?>
                    </form>
                </section>

                <!-- Users Grid -->
                <section class="users-grid">
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
                            $role_class = (($user['role'] ?? 'user') === 'admin') ? 'role-admin' : 'role-user';
                            ?>
                            <article class="user-card">
                                <div class="user-card-top">
                                    <span class="org-tag"><?= htmlspecialchars($user['org_body'] ?? 'No organization') ?></span>
                                    <span class="role-badge <?= htmlspecialchars($role_class) ?>">
                                        <?= htmlspecialchars(ucfirst($user['role'] ?? 'user')) ?>
                                    </span>
                                </div>

                                <div class="user-card-body">
                                    <div class="user-profile-row">
                                        <img src="<?= PUBLIC_URL ?>assets/profiles/<?= htmlspecialchars($profile_pic) ?>"
                                            alt="<?= htmlspecialchars($user['user_name']) ?>" class="user-avatar">

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
                                                Registered
                                                <?= !empty($user['user_reg_date']) ? date('M j, Y', strtotime($user['user_reg_date'])) : 'N/A' ?>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="event-summary">
                                        <div class="event-summary-title">
                                            Event Summary — <?= $total_events ?> total
                                        </div>

                                        <div class="summary-row"><span>Draft</span><span
                                                class="summary-val"><?= $draft_count ?></span></div>
                                        <div class="summary-row"><span>Pending Review</span><span
                                                class="summary-val"><?= $pending_review_count ?></span></div>
                                        <div class="summary-row"><span>Needs Revision</span><span
                                                class="summary-val"><?= $needs_revision_count ?></span></div>
                                        <div class="summary-row"><span>Approved</span><span
                                                class="summary-val"><?= $approved_count ?></span></div>
                                        <div class="summary-row"><span>Completed</span><span
                                                class="summary-val"><?= $completed_count ?></span></div>
                                    </div>
                                </div>

                                <div class="user-card-footer">
                                    <span class="user-id-tag">User ID #<?= (int) $user['user_id'] ?></span>

                                    <div class="footer-actions">
                                        <?php if (($user['role'] ?? '') !== 'admin'): ?>
                                            <a href="admin_user_events.php?user_id=<?= (int) $user['user_id'] ?>"
                                                class="btn-secondary btn-smaller">
                                                View Events
                                            </a>
                                        <?php endif; ?>

                                        <?php if (!$is_self && ($user['role'] ?? '') === 'user'): ?>
                                            <form method="POST" action="admin_update_user.php" class="inline-form">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                                <input type="hidden" name="user_id" value="<?= (int) $user['user_id'] ?>">
                                                <input type="hidden" name="action" value="make_admin">
                                                <button type="submit" class="btn-primary btn-smaller">Promote</button>
                                            </form>
                                        <?php endif; ?>

                                        <?php if (!$is_self && ($user['role'] ?? '') === 'admin'): ?>
                                            <form method="POST" action="admin_update_user.php" class="inline-form"
                                                data-confirm="Set this admin back to standard user?">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                                <input type="hidden" name="user_id" value="<?= (int) $user['user_id'] ?>">
                                                <input type="hidden" name="action" value="make_user">
                                                <button type="submit" class="btn-secondary btn-smaller">Set as User</button>
                                            </form>
                                        <?php endif; ?>

                                        <?php if (!$is_self): ?>
                                            <form method="POST" action="admin_update_user.php" class="inline-form"
                                                data-confirm="Delete this user? This will also delete all their related records.">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                                <input type="hidden" name="user_id" value="<?= (int) $user['user_id'] ?>">
                                                <input type="hidden" name="action" value="delete_user">
                                                <button type="submit" class="btn-primary btn-smaller btn-danger">Delete</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fa-solid fa-users-slash"></i>
                            <p>No users found. Try adjusting your search or filters.</p>
                        </div>
                    <?php endif; ?>
                </section>
            </section>

            <?php include PUBLIC_PATH . 'assets/includes/footer.php'; ?>
        </main>
    </div>

    <script src="<?= APP_URL ?>script/layout.js?v=1"></script>
    <script>
        (function () {
            const filterForm = document.getElementById('usersFilterForm');
            const orgSelect = document.getElementById('orgSelect');
            const searchInput = document.getElementById('searchInput');

            // ============= ORGANIZATION DROPDOWN AUTO-SUBMIT =============
            // When organization filter changes, submit the form immediately
            if (orgSelect) {
                orgSelect.addEventListener('change', function () {
                    filterForm.submit();
                });
            }

            // ============= SEARCH INPUT DEBOUNCED AUTO-SUBMIT =============
            // Submit form after user stops typing for 500ms (debounce to avoid excessive requests)
            if (searchInput) {
                let searchTimer;

                // On input: start/reset debounce timer
                searchInput.addEventListener('input', function () {
                    clearTimeout(searchTimer);
                    searchTimer = setTimeout(function () {
                        filterForm.submit();
                    }, 500);
                });

                // On Enter key: submit immediately without delay
                searchInput.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        clearTimeout(searchTimer);
                        filterForm.submit();
                    }
                });
            }

            // ============= CONFIRMATION DIALOGS FOR DESTRUCTIVE ACTIONS =============
            // Find all forms with data-confirm attribute (forms that need confirmation)
            document.querySelectorAll('form[data-confirm]').forEach(form => {
                form.addEventListener('submit', function (e) {
                    // Show confirm dialog with message from data-confirm attribute
                    // If user clicks Cancel, prevent form submission
                    if (!confirm(this.dataset.confirm)) {
                        e.preventDefault();
                    }
                });
            });
        })();
    </script>
</body>

</html>