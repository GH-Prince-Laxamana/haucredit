<?php
session_start();
require_once "../app/database.php";
require_once "../app/security_headers.php";

send_security_headers();

// ===== AUTHENTICATION CHECK =====
if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit();
}

// ===== FETCH STATS FOR INITIAL RENDER =====
// Inactive = users with no last_login OR last_login > 30 days ago
$stats_result = $conn->query("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN (last_login IS NULL OR last_login < NOW() - INTERVAL 30 DAY) THEN 1 ELSE 0 END) AS inactive,
        SUM(CASE WHEN role = 'Admin' THEN 1 ELSE 0 END) AS admins,
        SUM(CASE WHEN status = 'Pending'  THEN 1 ELSE 0 END) AS pending_count,
        SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) AS approved_count,
        SUM(CASE WHEN status = 'Archived' THEN 1 ELSE 0 END) AS archived_count
    FROM users
");
$stats = $stats_result->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users – HAUCREDIT</title>
    <link rel="stylesheet" href="assets/styles/layout.css">
    <link rel="stylesheet" href="assets/styles/user.css">
</head>
<body>
<div class="app">

    <div class="sidebar-overlay" id="sidebarOverlay" hidden></div>
    <?php include 'assets/includes/general_nav.php'; ?>

    <main class="main">

        <!-- ===== TOPBAR ===== -->
        <header class="topbar">
            <button class="hamburger" id="menuBtn" type="button" aria-label="Open menu">☰</button>
            <div class="title-wrap">
                <h1>Users</h1>
            </div>
            <button class="btn-primary btn-add-user" id="btnOpenAddModal">
                <i class="fa-solid fa-plus"></i> Add User
            </button>
        </header>

        <!-- ===== STAT CARDS ===== -->
        <div class="stat-cards">
            <div class="stat-card stat-card--gold">
                <div class="stat-card__icon"><i class="fa-solid fa-users"></i></div>
                <div class="stat-card__body">
                    <div class="stat-card__num" id="statTotal"><?= $stats['total'] ?></div>
                    <div class="stat-card__label">Total Users</div>
                </div>
            </div>
            <div class="stat-card stat-card--grey">
                <div class="stat-card__icon"><i class="fa-solid fa-user-slash"></i></div>
                <div class="stat-card__body">
                    <div class="stat-card__num" id="statInactive"><?= $stats['inactive'] ?></div>
                    <div class="stat-card__label">Inactive Users</div>
                </div>
            </div>
            <div class="stat-card stat-card--burgundy">
                <div class="stat-card__icon"><i class="fa-solid fa-user-shield"></i></div>
                <div class="stat-card__body">
                    <div class="stat-card__num" id="statAdmins"><?= $stats['admins'] ?></div>
                    <div class="stat-card__label">Admins</div>
                </div>
            </div>
        </div>

        <!-- ===== TABLE CARD ===== -->
        <div class="table-card">

            <!-- Tabs + Search row -->
            <div class="table-header">
                <div class="tab-group" id="tabGroup">
                    <button class="tab-btn active" data-status="all">
                        Users <span class="tab-badge tab-badge--gold" id="badgeAll"><?= $stats['total'] ?></span>
                    </button>
                    <button class="tab-btn" data-status="Pending">
                        Pending <span class="tab-count" id="badgePending"><?= $stats['pending_count'] ?></span>
                    </button>
                    <button class="tab-btn" data-status="Approved">
                        Approved <span class="tab-count" id="badgeApproved">· <?= $stats['approved_count'] ?></span>
                    </button>
                    <button class="tab-btn" data-status="Archived">
                        Archived <span class="tab-count" id="badgeArchived">· <?= $stats['archived_count'] ?></span>
                    </button>
                    <button class="tab-btn" data-status="Inactive">
                        Inactive
                    </button>
                </div>

                <div class="search-box">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" id="searchInput" placeholder="Search users..." autocomplete="off">
                </div>
            </div>

            <!-- Second toolbar: count info -->
            <div class="table-toolbar">
                <span class="showing-text" id="showingText">Loading…</span>
            </div>

            <!-- Table -->
            <div class="table-wrap">
                <table id="usersTable">
                    <thead>
                        <tr>
                            <th class="col-check"><input type="checkbox" id="checkAll"></th>
                            <th class="col-num">#</th>
                            <th class="col-name sortable" data-col="user_name">Name <i class="fa-solid fa-sort"></i></th>
                            <th class="col-email sortable" data-col="user_email">Email <i class="fa-solid fa-sort"></i></th>
                            <th class="col-org sortable" data-col="org_body">Organization <i class="fa-solid fa-sort"></i></th>
                            <th class="col-role sortable" data-col="role">Role <i class="fa-solid fa-sort"></i></th>
                            <th class="col-status sortable" data-col="status">Status <i class="fa-solid fa-sort"></i></th>
                            <th class="col-actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody">
                        <tr><td colspan="8" class="loading-row"><i class="fa-solid fa-spinner fa-spin"></i> Loading…</td></tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="pagination-bar" id="paginationBar"></div>
        </div>

    </main>
</div>

<!-- ============================
     ADD USER MODAL
============================= -->
<div class="modal-overlay" id="addModal" role="dialog" aria-modal="true" aria-labelledby="addModalTitle">
    <div class="modal-box">
        <div class="modal-head">
            <h2 id="addModalTitle"><i class="fa-solid fa-user-plus"></i> Add New User</h2>
            <button class="modal-close" data-close="addModal" aria-label="Close">✕</button>
        </div>
        <form id="addForm" novalidate>
            <div class="form-grid">
                <div class="form-field">
                    <label for="add_user_name">Username *</label>
                    <input type="text" id="add_user_name" name="user_name" placeholder="e.g. jdoe" required>
                </div>
                <div class="form-field">
                    <label for="add_stud_num">Student No. *</label>
                    <input type="text" id="add_stud_num" name="stud_num" placeholder="20XXXXXX" required>
                </div>
                <div class="form-field form-field--full">
                    <label for="add_user_email">Email Address *</label>
                    <input type="email" id="add_user_email" name="user_email" placeholder="name@student.hau.edu.ph" required>
                </div>
                <div class="form-field form-field--full">
                    <label for="add_org_body">Organization *</label>
                    <input type="text" id="add_org_body" name="org_body" placeholder="e.g. SoC MAFIA" required>
                </div>
                <div class="form-field">
                    <label for="add_role">Role *</label>
                    <select id="add_role" name="role" required>
                        <option value="User">User</option>
                        <option value="Moderator">Moderator</option>
                        <option value="Admin">Admin</option>
                    </select>
                </div>
                <div class="form-field">
                    <label for="add_status">Status *</label>
                    <select id="add_status" name="status" required>
                        <option value="Pending">Pending</option>
                        <option value="Approved">Approved</option>
                    </select>
                </div>
                <div class="form-field form-field--full">
                    <label for="add_user_password">Password *</label>
                    <input type="password" id="add_user_password" name="user_password" placeholder="Minimum 8 characters" required>
                </div>
            </div>
            <div class="modal-error" id="addError" hidden></div>
            <div class="modal-actions">
                <button type="button" class="btn-secondary" data-close="addModal">Cancel</button>
                <button type="submit" class="btn-primary" id="btnAddSubmit">
                    <i class="fa-solid fa-check"></i> Create User
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ============================
     EDIT USER MODAL
============================= -->
<div class="modal-overlay" id="editModal" role="dialog" aria-modal="true" aria-labelledby="editModalTitle">
    <div class="modal-box">
        <div class="modal-head">
            <h2 id="editModalTitle"><i class="fa-solid fa-user-pen"></i> Edit User</h2>
            <button class="modal-close" data-close="editModal" aria-label="Close">✕</button>
        </div>
        <form id="editForm" novalidate>
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="user_id" id="edit_user_id">
            <div class="form-grid">
                <div class="form-field">
                    <label for="edit_user_name">Username *</label>
                    <input type="text" id="edit_user_name" name="user_name" required>
                </div>
                <div class="form-field">
                    <label for="edit_stud_num">Student No.</label>
                    <input type="text" id="edit_stud_num" name="stud_num">
                </div>
                <div class="form-field form-field--full">
                    <label for="edit_user_email">Email Address *</label>
                    <input type="email" id="edit_user_email" name="user_email" required>
                </div>
                <div class="form-field form-field--full">
                    <label for="edit_org_body">Organization</label>
                    <input type="text" id="edit_org_body" name="org_body">
                </div>
                <div class="form-field">
                    <label for="edit_role">Role *</label>
                    <select id="edit_role" name="role" required>
                        <option value="User">User</option>
                        <option value="Moderator">Moderator</option>
                        <option value="Admin">Admin</option>
                    </select>
                </div>
                <div class="form-field">
                    <label for="edit_status">Status *</label>
                    <select id="edit_status" name="status" required>
                        <option value="Pending">Pending</option>
                        <option value="Approved">Approved</option>
                        <option value="Archived">Archived</option>
                    </select>
                </div>
            </div>
            <div class="modal-error" id="editError" hidden></div>
            <div class="modal-actions">
                <button type="button" class="btn-secondary" data-close="editModal">Cancel</button>
                <button type="submit" class="btn-primary">
                    <i class="fa-solid fa-floppy-disk"></i> Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ============================
     CONFIRM DELETE MODAL
============================= -->
<div class="modal-overlay" id="deleteModal" role="dialog" aria-modal="true" aria-labelledby="deleteModalTitle">
    <div class="modal-box modal-box--sm">
        <div class="modal-head modal-head--danger">
            <h2 id="deleteModalTitle"><i class="fa-solid fa-triangle-exclamation"></i> Confirm Delete</h2>
            <button class="modal-close" data-close="deleteModal" aria-label="Close">✕</button>
        </div>
        <div class="modal-body-text">
            <p>Are you sure you want to delete <strong id="deleteUserName"></strong>?</p>
            <p class="text-muted">This action cannot be undone.</p>
        </div>
        <div class="modal-actions">
            <button type="button" class="btn-secondary" data-close="deleteModal">Cancel</button>
            <button type="button" class="btn-danger-solid" id="btnConfirmDelete">
                <i class="fa-solid fa-trash"></i> Delete
            </button>
        </div>
    </div>
</div>

<!-- Footer confirm overlay (from footer.php) -->
<?php include 'assets/includes/footer.php'; ?>

<script src="../app/script/layout.js?v=1"></script>
<script src="../app/script/users.js?v=4"></script>
</body>
</html>