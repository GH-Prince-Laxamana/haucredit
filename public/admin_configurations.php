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

/* ================= CSRF ================= */
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION["csrf_token"];

/* ================= FLASH MESSAGES ================= */
$success = $_SESSION["success"] ?? "";
$error = $_SESSION["error"] ?? "";
unset($_SESSION["success"], $_SESSION["error"]);

/* ================= HELPERS ================= */
function normalizeActiveClass($is_active): string {
    return ((int) $is_active === 1) ? 'active' : 'inactive';
}

/* ================= LOAD DATA ================= */
$background_options = fetchAll($conn, "SELECT background_id, background_name, is_active, sort_order, updated_at FROM config_background_options ORDER BY is_active DESC, sort_order ASC, background_name ASC", "", []);
$org_options = fetchAll($conn, "SELECT org_option_id, org_name, is_active, sort_order, updated_at FROM config_org_options ORDER BY is_active DESC, sort_order ASC, org_name ASC", "", []);
$activity_types = fetchAll($conn, "SELECT activity_type_id, activity_type_name, is_active, sort_order, updated_at FROM config_activity_types ORDER BY is_active DESC, sort_order ASC, activity_type_name ASC", "", []);
$series_options = fetchAll($conn, "SELECT series_option_id, series_name, is_active, sort_order, updated_at FROM config_series_options ORDER BY is_active DESC, sort_order ASC, series_name ASC", "", []);
$requirement_templates = fetchAll($conn, "SELECT req_template_id, req_name, req_desc, template_url, default_due_offset_days, default_due_basis, is_active, created_at, updated_at FROM requirement_templates ORDER BY is_active DESC, req_name ASC", "", []);
$requirements_map_rows = fetchAll($conn, "
    SELECT crm.config_map_id, crm.background_id, crm.activity_type_id, crm.req_template_id, crm.is_active, crm.updated_at,
           cbo.background_name, cat.activity_type_name, rt.req_name
    FROM config_requirements_map crm
    INNER JOIN config_background_options cbo ON crm.background_id = cbo.background_id
    INNER JOIN config_activity_types cat ON crm.activity_type_id = cat.activity_type_id
    INNER JOIN requirement_templates rt ON crm.req_template_id = rt.req_template_id
    ORDER BY cbo.background_name ASC, cat.activity_type_name ASC, rt.req_name ASC
", "", []);

$summary = [
    'background_total' => count($background_options),
    'org_total'        => count($org_options),
    'activity_total'   => count($activity_types),
    'series_total'     => count($series_options),
    'template_total'   => count($requirement_templates),
    'mapping_total'    => count($requirements_map_rows),
];

$grouped_map = [];
foreach ($requirements_map_rows as $row) {
    $grouped_map[$row['background_name']][$row['activity_type_name']][] = $row;
}

$basis_options = ['before_start', 'after_start', 'before_end', 'after_end', 'manual'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Configurations - HAUCREDIT</title>
    <link rel="stylesheet" href="assets/styles/layout.css">
    <link rel="stylesheet" href="assets/styles/home_styles.css">
    <style>
        /* ===== STAT CARDS ===== */
        .config-stats {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 16px;
            margin-bottom: 24px;
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

        /* ===== SECTION CARDS ===== */
        .config-section {
            background: white;
            border-radius: var(--radius);
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            margin-bottom: 24px;
            overflow: hidden;
        }

        .section-header {
            padding: 16px 20px;
            background: #fdfcfa;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .section-header h2 {
            font-size: 15px;
            font-weight: 700;
            color: var(--burgundy);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .section-body { padding: 20px; }

        /* ===== ADD FORM ROW ===== */
        .add-form-row {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 14px 16px;
            background: #faf9f6;
            border: 1px solid var(--border);
            border-radius: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .add-form-row .form-input {
            flex: 1;
            min-width: 160px;
        }

        .add-form-row .form-input-sm {
            width: 90px;
            flex-shrink: 0;
        }

        /* ===== FORM INPUTS ===== */
        .form-input {
            padding: 8px 12px;
            border: 1px solid var(--border-fields);
            border-radius: 8px;
            font-size: 13px;
            font-family: inherit;
            background: white;
            color: var(--text-primary);
            transition: all 0.2s;
            width: 100%;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--gold);
            box-shadow: 0 0 0 3px rgba(194,161,77,0.12);
        }

        textarea.form-input {
            resize: vertical;
            min-height: 70px;
        }

        /* ===== CONFIG ITEMS GRID ===== */
        .config-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 16px;
        }

        .config-card {
            background: white;
            border-radius: 10px;
            border: 1px solid var(--border);
            overflow: hidden;
            transition: all 0.2s ease;
        }

        .config-card:hover {
            box-shadow: 0 4px 16px rgba(0,0,0,0.08);
        }

        .config-card-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 14px;
            background: #fdfcfa;
            border-bottom: 1px solid var(--border);
        }

        .config-type-tag {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-secondary);
        }

        /* Status badges */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 3px 9px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
        }

        .status-badge::before {
            content: '';
            width: 5px;
            height: 5px;
            border-radius: 50%;
            background: currentColor;
            opacity: 0.8;
        }

        .badge-active   { background: rgba(16,185,129,0.1); color: #059669; }
        .badge-inactive { background: rgba(156,163,175,0.1); color: #6b7280; }

        .config-card-body {
            padding: 14px;
        }

        .config-title {
            font-size: 14px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 6px;
        }

        .config-meta {
            font-size: 12px;
            color: var(--text-secondary);
            margin-bottom: 2px;
        }

        .config-card-footer {
            padding: 12px 14px;
            border-top: 1px solid var(--border);
            background: #fdfcfa;
            display: grid;
            gap: 8px;
        }

        .config-edit-grid {
            display: grid;
            gap: 6px;
        }

        .config-actions {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }

        /* ===== BUTTONS ===== */
        .btn-sm {
            display: inline-flex;
            align-items: center;
            padding: 5px 12px;
            border-radius: 7px;
            font-size: 12px;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            border: none;
            transition: all 0.18s;
            text-decoration: none;
        }

        .btn-save {
            background: #f5f2ed;
            border: 1px solid var(--border);
            color: var(--text-secondary);
        }

        .btn-save:hover { background: var(--gold); color: white; border-color: var(--gold); }

        .btn-deactivate {
            background: rgba(245,158,11,0.1);
            border: 1px solid rgba(245,158,11,0.2);
            color: #d97706;
        }

        .btn-deactivate:hover { background: #d97706; color: white; }

        .btn-activate {
            background: rgba(16,185,129,0.1);
            border: 1px solid rgba(16,185,129,0.2);
            color: #059669;
        }

        .btn-activate:hover { background: #059669; color: white; }

        .btn-delete {
            background: rgba(239,68,68,0.08);
            border: 1px solid rgba(239,68,68,0.2);
            color: #dc2626;
        }

        .btn-delete:hover { background: #dc2626; color: white; }

        .btn-add {
            background: var(--burgundy);
            color: white;
            border: none;
            white-space: nowrap;
        }

        .btn-add:hover { background: #3a0010; }

        /* ===== MAPPING SECTION ===== */
        .mapping-add-form {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr auto;
            gap: 10px;
            align-items: end;
            padding: 14px 16px;
            background: #faf9f6;
            border: 1px solid var(--border);
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .mapping-group {
            background: #faf9f6;
            border: 1px solid var(--border);
            border-radius: 10px;
            margin-bottom: 14px;
            overflow: hidden;
        }

        .mapping-group-header {
            padding: 10px 16px;
            background: #f0ede8;
            border-bottom: 1px solid var(--border);
            font-size: 13px;
            font-weight: 700;
            color: var(--burgundy);
        }

        .mapping-activity {
            padding: 12px 16px;
            border-bottom: 1px solid var(--border);
        }

        .mapping-activity:last-child { border-bottom: none; }

        .mapping-activity-title {
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: var(--text-secondary);
            margin-bottom: 8px;
        }

        .mapping-row {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 7px 0;
            border-bottom: 1px solid #f0ede8;
        }

        .mapping-row:last-child { border-bottom: none; }

        .mapping-req-name {
            flex: 1;
            font-size: 13px;
            color: var(--text-primary);
        }

        .mapping-actions {
            display: flex;
            gap: 6px;
            align-items: center;
        }

        /* ===== TEMPLATE GRID ===== */
        .template-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 16px;
        }

        .template-card {
            background: white;
            border-radius: 10px;
            border: 1px solid var(--border);
            overflow: hidden;
        }

        .template-card-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 14px;
            background: #fdfcfa;
            border-bottom: 1px solid var(--border);
        }

        .template-card-body { padding: 14px; }

        .template-title {
            font-size: 14px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 8px;
        }

        .template-meta {
            font-size: 12px;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 4px;
        }

        .template-meta i { color: var(--gold); font-size: 11px; }

        .template-desc {
            margin-top: 10px;
            padding: 10px;
            background: #faf9f6;
            border-radius: 8px;
            font-size: 12px;
            color: var(--text-secondary);
            border: 1px solid var(--border);
        }

        .template-card-footer {
            padding: 12px 14px;
            border-top: 1px solid var(--border);
            background: #fdfcfa;
            display: grid;
            gap: 8px;
        }


        /* ===== COLLAPSIBLE SECTIONS ===== */
        .section-header {
            cursor: pointer;
            user-select: none;
        }

        .section-header:hover {
            background: #f5f2ed;
        }

        .section-toggle {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            border-radius: 8px;
            background: #f0ede8;
            color: var(--text-secondary);
            font-size: 12px;
            transition: all 0.2s ease;
            flex-shrink: 0;
        }

        .config-section.collapsed .section-toggle {
            transform: rotate(-90deg);
        }

        .section-body {
            transition: none;
        }

        .config-section.collapsed .section-body {
            display: none;
        }

        .section-meta {
            font-size: 12px;
            color: var(--text-secondary);
            font-weight: 500;
        }
        /* ===== RESPONSIVE ===== */
        @media (max-width: 1200px) { .config-stats { grid-template-columns: repeat(3, 1fr); } }
        @media (max-width: 900px)  {
            .config-stats { grid-template-columns: repeat(2, 1fr); }
            .mapping-add-form { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 480px)  {
            .config-stats { grid-template-columns: 1fr; }
            .mapping-add-form { grid-template-columns: 1fr; }
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
                <h1>Configurations</h1>
                <p>Manage backgrounds, organizations, activity types, series, templates, and requirement mappings.</p>
            </div>
        </div>

        <!-- Notices -->
        <?php if ($success !== ""): ?>
            <div class="notice success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if ($error !== ""): ?>
            <div class="notice error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- Stat Cards -->
        <div class="config-stats">
            <div class="stat-card">
                <div class="stat-number"><?= $summary['background_total'] ?></div>
                <div class="stat-label">Backgrounds</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $summary['org_total'] ?></div>
                <div class="stat-label">Organizations</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $summary['activity_total'] ?></div>
                <div class="stat-label">Activity Types</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $summary['series_total'] ?></div>
                <div class="stat-label">Series Options</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $summary['template_total'] ?></div>
                <div class="stat-label">Req. Templates</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $summary['mapping_total'] ?></div>
                <div class="stat-label">Req. Mappings</div>
            </div>
        </div>

        <!-- ===== BACKGROUND OPTIONS ===== -->
        <div class="config-section">
            <div class="section-header" onclick="toggleSection(this)">
                <h2><i class="fa-solid fa-layer-group"></i> Background Options</h2>
                <div style="display:flex;align-items:center;gap:10px;">
                    <div class="section-toggle"><i class="fa-solid fa-chevron-down"></i></div>
                </div>
            </div>
            <div class="section-body">
                <form action="admin_update_configuration.php" method="POST" class="add-form-row">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <input type="hidden" name="config_type" value="background">
                    <input type="text" name="background_name" class="form-input" placeholder="Add new background..." required>
                    <input type="number" name="sort_order" class="form-input form-input-sm" placeholder="Sort" value="0">
                    <button type="submit" name="action" value="add_background" class="btn-sm btn-add">Add</button>
                </form>

                <div class="config-grid">
                    <?php foreach ($background_options as $bg):
                        $active = (int)($bg['is_active'] ?? 0) === 1;
                    ?>
                    <div class="config-card">
                        <div class="config-card-top">
                            <span class="config-type-tag">Background</span>
                            <span class="status-badge <?= $active ? 'badge-active' : 'badge-inactive' ?>">
                                <?= $active ? 'Active' : 'Inactive' ?>
                            </span>
                        </div>
                        <div class="config-card-body">
                            <div class="config-title"><?= htmlspecialchars($bg['background_name']) ?></div>
                            <div class="config-meta">Sort order: <?= (int)($bg['sort_order'] ?? 0) ?></div>
                        </div>
                        <div class="config-card-footer">
                            <form action="admin_update_configuration.php" method="POST" class="config-edit-grid">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                <input type="hidden" name="config_type" value="background">
                                <input type="hidden" name="background_id" value="<?= (int)$bg['background_id'] ?>">
                                <input type="text" name="background_name" value="<?= htmlspecialchars($bg['background_name']) ?>" class="form-input">
                                <input type="number" name="sort_order" value="<?= (int)($bg['sort_order'] ?? 0) ?>" class="form-input">
                                <div class="config-actions">
                                    <button type="submit" name="action" value="update_background" class="btn-sm btn-save">Save</button>
                                    <?php if ($active): ?>
                                        <button type="submit" name="action" value="deactivate_background" class="btn-sm btn-deactivate">Deactivate</button>
                                    <?php else: ?>
                                        <button type="submit" name="action" value="activate_background" class="btn-sm btn-activate">Activate</button>
                                    <?php endif; ?>
                                    <button type="submit" name="action" value="delete_background" class="btn-sm btn-delete"
                                            onclick="return confirm('Delete this background?');">Delete</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- ===== ORGANIZATIONS ===== -->
        <div class="config-section">
            <div class="section-header" onclick="toggleSection(this)">
                <h2><i class="fa-solid fa-building-columns"></i> Organizations</h2>
                <div style="display:flex;align-items:center;gap:10px;">
                    <div class="section-toggle"><i class="fa-solid fa-chevron-down"></i></div>
                </div>
            </div>
            <div class="section-body">
                <form action="admin_update_configuration.php" method="POST" class="add-form-row">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <input type="hidden" name="config_type" value="org">
                    <input type="text" name="org_name" class="form-input" placeholder="Add new organization..." required>
                    <input type="number" name="sort_order" class="form-input form-input-sm" placeholder="Sort" value="0">
                    <button type="submit" name="action" value="add_org" class="btn-sm btn-add">Add</button>
                </form>

                <div class="config-grid">
                    <?php foreach ($org_options as $org):
                        $active = (int)($org['is_active'] ?? 0) === 1;
                    ?>
                    <div class="config-card">
                        <div class="config-card-top">
                            <span class="config-type-tag">Organization</span>
                            <span class="status-badge <?= $active ? 'badge-active' : 'badge-inactive' ?>">
                                <?= $active ? 'Active' : 'Inactive' ?>
                            </span>
                        </div>
                        <div class="config-card-body">
                            <div class="config-title"><?= htmlspecialchars($org['org_name']) ?></div>
                            <div class="config-meta">Sort order: <?= (int)($org['sort_order'] ?? 0) ?></div>
                        </div>
                        <div class="config-card-footer">
                            <form action="admin_update_configuration.php" method="POST" class="config-edit-grid">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                <input type="hidden" name="config_type" value="org">
                                <input type="hidden" name="org_option_id" value="<?= (int)$org['org_option_id'] ?>">
                                <input type="text" name="org_name" value="<?= htmlspecialchars($org['org_name']) ?>" class="form-input">
                                <input type="number" name="sort_order" value="<?= (int)($org['sort_order'] ?? 0) ?>" class="form-input">
                                <div class="config-actions">
                                    <button type="submit" name="action" value="update_org" class="btn-sm btn-save">Save</button>
                                    <?php if ($active): ?>
                                        <button type="submit" name="action" value="deactivate_org" class="btn-sm btn-deactivate">Deactivate</button>
                                    <?php else: ?>
                                        <button type="submit" name="action" value="activate_org" class="btn-sm btn-activate">Activate</button>
                                    <?php endif; ?>
                                    <button type="submit" name="action" value="delete_org" class="btn-sm btn-delete"
                                            onclick="return confirm('Delete this organization?');">Delete</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- ===== ACTIVITY TYPES ===== -->
        <div class="config-section">
            <div class="section-header" onclick="toggleSection(this)">
                <h2><i class="fa-solid fa-layer-group"></i> Activity Types</h2>
                <div style="display:flex;align-items:center;gap:10px;">
                    <div class="section-toggle"><i class="fa-solid fa-chevron-down"></i></div>
                </div>
            </div>
            <div class="section-body">
                <form action="admin_update_configuration.php" method="POST" class="add-form-row">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <input type="hidden" name="config_type" value="activity_type">
                    <input type="text" name="activity_type_name" class="form-input" placeholder="Add new activity type..." required>
                    <input type="number" name="sort_order" class="form-input form-input-sm" placeholder="Sort" value="0">
                    <button type="submit" name="action" value="add_activity_type" class="btn-sm btn-add">Add</button>
                </form>

                <div class="config-grid">
                    <?php foreach ($activity_types as $type):
                        $active = (int)($type['is_active'] ?? 0) === 1;
                    ?>
                    <div class="config-card">
                        <div class="config-card-top">
                            <span class="config-type-tag">Activity Type</span>
                            <span class="status-badge <?= $active ? 'badge-active' : 'badge-inactive' ?>">
                                <?= $active ? 'Active' : 'Inactive' ?>
                            </span>
                        </div>
                        <div class="config-card-body">
                            <div class="config-title"><?= htmlspecialchars($type['activity_type_name']) ?></div>
                            <div class="config-meta">Sort order: <?= (int)($type['sort_order'] ?? 0) ?></div>
                        </div>
                        <div class="config-card-footer">
                            <form action="admin_update_configuration.php" method="POST" class="config-edit-grid">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                <input type="hidden" name="config_type" value="activity_type">
                                <input type="hidden" name="activity_type_id" value="<?= (int)$type['activity_type_id'] ?>">
                                <input type="text" name="activity_type_name" value="<?= htmlspecialchars($type['activity_type_name']) ?>" class="form-input">
                                <input type="number" name="sort_order" value="<?= (int)($type['sort_order'] ?? 0) ?>" class="form-input">
                                <div class="config-actions">
                                    <button type="submit" name="action" value="update_activity_type" class="btn-sm btn-save">Save</button>
                                    <?php if ($active): ?>
                                        <button type="submit" name="action" value="deactivate_activity_type" class="btn-sm btn-deactivate">Deactivate</button>
                                    <?php else: ?>
                                        <button type="submit" name="action" value="activate_activity_type" class="btn-sm btn-activate">Activate</button>
                                    <?php endif; ?>
                                    <button type="submit" name="action" value="delete_activity_type" class="btn-sm btn-delete"
                                            onclick="return confirm('Delete this activity type?');">Delete</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- ===== SERIES OPTIONS ===== -->
        <div class="config-section">
            <div class="section-header" onclick="toggleSection(this)">
                <h2><i class="fa-solid fa-diagram-project"></i> Series Options</h2>
                <div style="display:flex;align-items:center;gap:10px;">
                    <div class="section-toggle"><i class="fa-solid fa-chevron-down"></i></div>
                </div>
            </div>
            <div class="section-body">
                <form action="admin_update_configuration.php" method="POST" class="add-form-row">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <input type="hidden" name="config_type" value="series">
                    <input type="text" name="series_name" class="form-input" placeholder="Add new series option..." required>
                    <input type="number" name="sort_order" class="form-input form-input-sm" placeholder="Sort" value="0">
                    <button type="submit" name="action" value="add_series" class="btn-sm btn-add">Add</button>
                </form>

                <div class="config-grid">
                    <?php foreach ($series_options as $series):
                        $active = (int)($series['is_active'] ?? 0) === 1;
                    ?>
                    <div class="config-card">
                        <div class="config-card-top">
                            <span class="config-type-tag">Series</span>
                            <span class="status-badge <?= $active ? 'badge-active' : 'badge-inactive' ?>">
                                <?= $active ? 'Active' : 'Inactive' ?>
                            </span>
                        </div>
                        <div class="config-card-body">
                            <div class="config-title"><?= htmlspecialchars($series['series_name']) ?></div>
                            <div class="config-meta">Sort order: <?= (int)($series['sort_order'] ?? 0) ?></div>
                        </div>
                        <div class="config-card-footer">
                            <form action="admin_update_configuration.php" method="POST" class="config-edit-grid">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                <input type="hidden" name="config_type" value="series">
                                <input type="hidden" name="series_option_id" value="<?= (int)$series['series_option_id'] ?>">
                                <input type="text" name="series_name" value="<?= htmlspecialchars($series['series_name']) ?>" class="form-input">
                                <input type="number" name="sort_order" value="<?= (int)($series['sort_order'] ?? 0) ?>" class="form-input">
                                <div class="config-actions">
                                    <button type="submit" name="action" value="update_series" class="btn-sm btn-save">Save</button>
                                    <?php if ($active): ?>
                                        <button type="submit" name="action" value="deactivate_series" class="btn-sm btn-deactivate">Deactivate</button>
                                    <?php else: ?>
                                        <button type="submit" name="action" value="activate_series" class="btn-sm btn-activate">Activate</button>
                                    <?php endif; ?>
                                    <button type="submit" name="action" value="delete_series" class="btn-sm btn-delete"
                                            onclick="return confirm('Delete this series option?');">Delete</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- ===== REQUIREMENT MAPPING ===== -->
        <div class="config-section">
            <div class="section-header" onclick="toggleSection(this)">
                <h2><i class="fa-solid fa-table-cells-large"></i> Requirement Mapping</h2>
                <div style="display:flex;align-items:center;gap:10px;">
                    <div class="section-toggle"><i class="fa-solid fa-chevron-down"></i></div>
                </div>
            </div>
            <div class="section-body">
                <form action="admin_update_configuration.php" method="POST" class="mapping-add-form">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <input type="hidden" name="config_type" value="mapping">
                    <select name="background_id" class="form-input" required>
                        <option value="">Select background</option>
                        <?php foreach ($background_options as $bg): ?>
                            <option value="<?= (int)$bg['background_id'] ?>"><?= htmlspecialchars($bg['background_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="activity_type_id" class="form-input" required>
                        <option value="">Select activity type</option>
                        <?php foreach ($activity_types as $type): ?>
                            <option value="<?= (int)$type['activity_type_id'] ?>"><?= htmlspecialchars($type['activity_type_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="req_template_id" class="form-input" required>
                        <option value="">Select requirement template</option>
                        <?php foreach ($requirement_templates as $tpl): ?>
                            <option value="<?= (int)$tpl['req_template_id'] ?>"><?= htmlspecialchars($tpl['req_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" name="action" value="add_mapping" class="btn-sm btn-add">Add Mapping</button>
                </form>

                <?php foreach ($grouped_map as $background => $type_map): ?>
                <div class="mapping-group">
                    <div class="mapping-group-header"><?= htmlspecialchars($background) ?></div>
                    <?php foreach ($type_map as $activity_type_name => $map_rows): ?>
                    <div class="mapping-activity">
                        <div class="mapping-activity-title"><?= htmlspecialchars($activity_type_name) ?></div>
                        <?php foreach ($map_rows as $map):
                            $map_active = (int)($map['is_active'] ?? 0) === 1;
                        ?>
                        <div class="mapping-row">
                            <span class="mapping-req-name"><?= htmlspecialchars($map['req_name']) ?></span>
                            <span class="status-badge <?= $map_active ? 'badge-active' : 'badge-inactive' ?>">
                                <?= $map_active ? 'Active' : 'Inactive' ?>
                            </span>
                            <form action="admin_update_configuration.php" method="POST" class="mapping-actions">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                <input type="hidden" name="config_type" value="mapping">
                                <input type="hidden" name="config_map_id" value="<?= (int)$map['config_map_id'] ?>">
                                <?php if ($map_active): ?>
                                    <button type="submit" name="action" value="deactivate_mapping" class="btn-sm btn-deactivate">Deactivate</button>
                                <?php else: ?>
                                    <button type="submit" name="action" value="activate_mapping" class="btn-sm btn-activate">Activate</button>
                                <?php endif; ?>
                                <button type="submit" name="action" value="delete_mapping" class="btn-sm btn-delete"
                                        onclick="return confirm('Delete this mapping?');">Delete</button>
                            </form>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- ===== REQUIREMENT TEMPLATES ===== -->
        <div class="config-section">
            <div class="section-header" onclick="toggleSection(this)">
                <h2><i class="fa-solid fa-file-circle-check"></i> Requirement Templates & Deadline Rules</h2>
                <div style="display:flex;align-items:center;gap:10px;">
                    <div class="section-toggle"><i class="fa-solid fa-chevron-down"></i></div>
                </div>
            </div>
            <div class="section-body">
                <div class="template-grid">
                    <?php foreach ($requirement_templates as $tpl):
                        $active = (int)($tpl['is_active'] ?? 0) === 1;
                    ?>
                    <div class="template-card">
                        <div class="template-card-top">
                            <span class="config-type-tag"><?= htmlspecialchars($tpl['default_due_basis'] ?? 'manual') ?></span>
                            <span class="status-badge <?= $active ? 'badge-active' : 'badge-inactive' ?>">
                                <?= $active ? 'Active' : 'Inactive' ?>
                            </span>
                        </div>
                        <div class="template-card-body">
                            <div class="template-title"><?= htmlspecialchars($tpl['req_name']) ?></div>
                            <div class="template-meta">
                                <i class="fa-solid fa-clock"></i>
                                <?= (int)($tpl['default_due_offset_days'] ?? 0) ?> day(s) offset
                            </div>
                            <div class="template-meta">
                                <i class="fa-solid fa-link"></i>
                                <?= !empty($tpl['template_url']) ? 'Template linked' : 'No template link' ?>
                            </div>
                            <?php if (!empty($tpl['req_desc'])): ?>
                                <div class="template-desc"><?= nl2br(htmlspecialchars($tpl['req_desc'])) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="template-card-footer">
                            <form action="admin_update_configuration.php" method="POST" class="config-edit-grid">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                <input type="hidden" name="config_type" value="template">
                                <input type="hidden" name="req_template_id" value="<?= (int)$tpl['req_template_id'] ?>">
                                <input type="text" name="req_name" value="<?= htmlspecialchars($tpl['req_name']) ?>" class="form-input" placeholder="Requirement name">
                                <textarea name="req_desc" class="form-input" rows="2"><?= htmlspecialchars($tpl['req_desc'] ?? '') ?></textarea>
                                <input type="text" name="template_url" value="<?= htmlspecialchars($tpl['template_url'] ?? '') ?>" class="form-input" placeholder="Template URL">
                                <input type="number" name="default_due_offset_days" min="0" value="<?= (int)($tpl['default_due_offset_days'] ?? 0) ?>" class="form-input" placeholder="Days offset">
                                <select name="default_due_basis" class="form-input">
                                    <?php foreach ($basis_options as $basis): ?>
                                        <option value="<?= htmlspecialchars($basis) ?>" <?= (($tpl['default_due_basis'] ?? '') === $basis) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($basis) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="config-actions">
                                    <button type="submit" name="action" value="update_template" class="btn-sm btn-save">Save</button>
                                    <?php if ($active): ?>
                                        <button type="submit" name="action" value="deactivate_template" class="btn-sm btn-deactivate">Deactivate</button>
                                    <?php else: ?>
                                        <button type="submit" name="action" value="activate_template" class="btn-sm btn-activate">Activate</button>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <!-- Add New Template Card -->
                    <div class="template-card">
                        <div class="template-card-top">
                            <span class="config-type-tag">New Template</span>
                        </div>
                        <div class="template-card-body">
                            <div class="template-title">Add Requirement Template</div>
                        </div>
                        <div class="template-card-footer">
                            <form action="admin_update_configuration.php" method="POST" class="config-edit-grid">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                <input type="hidden" name="config_type" value="template">
                                <input type="text" name="req_name" class="form-input" placeholder="Requirement name" required>
                                <textarea name="req_desc" class="form-input" rows="2" placeholder="Description"></textarea>
                                <input type="text" name="template_url" class="form-input" placeholder="Template URL">
                                <input type="number" name="default_due_offset_days" min="0" value="7" class="form-input" placeholder="Days offset">
                                <select name="default_due_basis" class="form-input">
                                    <?php foreach ($basis_options as $basis): ?>
                                        <option value="<?= htmlspecialchars($basis) ?>"><?= htmlspecialchars($basis) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="config-actions">
                                    <button type="submit" name="action" value="add_template" class="btn-sm btn-add">Add Template</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php include 'assets/includes/footer.php'; ?>
    </main>
</div>

<script>
    function toggleSection(header) {
        const section = header.closest('.config-section');
        section.classList.toggle('collapsed');
    }

    // Collapse all sections by default except the first
    document.addEventListener('DOMContentLoaded', function () {
        const sections = document.querySelectorAll('.config-section');
        sections.forEach((section, index) => {
            if (index > 0) section.classList.add('collapsed');
        });
    });
</script>
<script src="../app/script/layout.js?v=1"></script>
</body>
</html>