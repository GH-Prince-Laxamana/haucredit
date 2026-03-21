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
function normalizeActiveClass($is_active): string
{
    return ((int) $is_active === 1) ? 'active' : 'inactive';
}

/* ================= LOAD BACKGROUNDS ================= */
$background_options = fetchAll(
    $conn,
    "
    SELECT background_id, background_name, is_active, sort_order, updated_at
    FROM config_background_options
    ORDER BY is_active DESC, sort_order ASC, background_name ASC
    ",
    "",
    []
);

/* ================= LOAD ORGANIZATIONS ================= */
$org_options = fetchAll(
    $conn,
    "
    SELECT org_option_id, org_name, is_active, sort_order, updated_at
    FROM config_org_options
    ORDER BY is_active DESC, sort_order ASC, org_name ASC
    ",
    "",
    []
);

/* ================= LOAD ACTIVITY TYPES ================= */
$activity_types = fetchAll(
    $conn,
    "
    SELECT activity_type_id, activity_type_name, is_active, sort_order, updated_at
    FROM config_activity_types
    ORDER BY is_active DESC, sort_order ASC, activity_type_name ASC
    ",
    "",
    []
);

/* ================= LOAD SERIES OPTIONS ================= */
$series_options = fetchAll(
    $conn,
    "
    SELECT series_option_id, series_name, is_active, sort_order, updated_at
    FROM config_series_options
    ORDER BY is_active DESC, sort_order ASC, series_name ASC
    ",
    "",
    []
);

/* ================= LOAD REQUIREMENT TEMPLATES ================= */
$requirement_templates = fetchAll(
    $conn,
    "
    SELECT
        req_template_id,
        req_name,
        req_desc,
        template_url,
        default_due_offset_days,
        default_due_basis,
        is_active,
        created_at,
        updated_at
    FROM requirement_templates
    ORDER BY is_active DESC, req_name ASC
    ",
    "",
    []
);

/* ================= LOAD REQUIREMENT MAPPING ================= */
$requirements_map_rows = fetchAll(
    $conn,
    "
    SELECT
        crm.config_map_id,
        crm.background_id,
        crm.activity_type_id,
        crm.req_template_id,
        crm.is_active,
        crm.updated_at,

        cbo.background_name,
        cat.activity_type_name,
        rt.req_name
    FROM config_requirements_map crm
    INNER JOIN config_background_options cbo
        ON crm.background_id = cbo.background_id
    INNER JOIN config_activity_types cat
        ON crm.activity_type_id = cat.activity_type_id
    INNER JOIN requirement_templates rt
        ON crm.req_template_id = rt.req_template_id
    ORDER BY
        cbo.background_name ASC,
        cat.activity_type_name ASC,
        rt.req_name ASC
    ",
    "",
    []
);

/* ================= SUMMARY COUNTS ================= */
$summary = [
    'background_total' => count($background_options),
    'org_total' => count($org_options),
    'activity_total' => count($activity_types),
    'series_total' => count($series_options),
    'template_total' => count($requirement_templates),
    'mapping_total' => count($requirements_map_rows)
];

/* ================= MAPPING DISPLAY GROUPING ================= */
$grouped_map = [];
foreach ($requirements_map_rows as $row) {
    $background = $row['background_name'];
    $activity = $row['activity_type_name'];

    if (!isset($grouped_map[$background])) {
        $grouped_map[$background] = [];
    }

    if (!isset($grouped_map[$background][$activity])) {
        $grouped_map[$background][$activity] = [];
    }

    $grouped_map[$background][$activity][] = $row;
}

$basis_options = ['before_start', 'after_start', 'before_end', 'after_end', 'manual'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Admin Configurations - HAUCREDIT</title>
    <link rel="stylesheet" href="assets/styles/layout.css" />
    <link rel="stylesheet" href="assets/styles/home_styles.css" />
    <link rel="stylesheet" href="assets/styles/admin_configurations.css" />
</head>

<body>
    <div class="app">
        <div class="sidebar-overlay" id="sidebarOverlay" hidden></div>

        <?php include 'assets/includes/admin_nav.php'; ?>

        <main class="main">
            <header class="topbar">
                <button class="hamburger" id="menuBtn" type="button" aria-label="Open menu">☰</button>

                <div class="title-wrap">
                    <h1>Configurations</h1>
                    <p>Manage backgrounds, organizations, activity types, series options, templates, and requirement
                        mappings.</p>
                </div>
            </header>

            <section class="content admin-config-page">
                <?php if ($success !== ""): ?>
                    <div class="notice success"><?= htmlspecialchars($success) ?></div>
                <?php endif; ?>

                <?php if ($error !== ""): ?>
                    <div class="notice error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <!-- Stat Cards -->
                <section class="config-stats">
                    <article class="stat-card">
                        <div class="stat-number"><?= $summary['background_total'] ?></div>
                        <div class="stat-label">Backgrounds</div>
                    </article>

                    <article class="stat-card">
                        <div class="stat-number"><?= $summary['org_total'] ?></div>
                        <div class="stat-label">Organizations</div>
                    </article>

                    <article class="stat-card">
                        <div class="stat-number"><?= $summary['activity_total'] ?></div>
                        <div class="stat-label">Activity Types</div>
                    </article>

                    <article class="stat-card">
                        <div class="stat-number"><?= $summary['series_total'] ?></div>
                        <div class="stat-label">Series Options</div>
                    </article>

                    <article class="stat-card">
                        <div class="stat-number"><?= $summary['template_total'] ?></div>
                        <div class="stat-label">Req. Templates</div>
                    </article>

                    <article class="stat-card">
                        <div class="stat-number"><?= $summary['mapping_total'] ?></div>
                        <div class="stat-label">Req. Mappings</div>
                    </article>
                </section>

                <!-- Background Options -->
                <section class="config-section">
                    <button type="button" class="section-header" onclick="toggleSection(this)">
                        <h2><i class="fa-solid fa-layer-group"></i> Background Options</h2>
                        <span class="section-toggle"><i class="fa-solid fa-chevron-down"></i></span>
                    </button>

                    <div class="section-body">
                        <form action="admin_update_configuration.php" method="POST" class="add-form-row">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                            <input type="hidden" name="config_type" value="background">
                            <input type="text" name="background_name" class="form-input"
                                placeholder="Add new background..." required>
                            <input type="number" name="sort_order" class="form-input form-input-sm" placeholder="Sort"
                                value="0">
                            <button type="submit" name="action" value="add_background"
                                class="btn-sm btn-add">Add</button>
                        </form>

                        <div class="config-grid">
                            <?php foreach ($background_options as $background): ?>
                                    <?php $active = ((int) ($background['is_active'] ?? 0) === 1); ?>
                                <article class="config-card">
                                    <div class="config-card-top">
                                        <span class="config-type-tag">Background</span>
                                        <span class="status-badge <?= $active ? 'badge-active' : 'badge-inactive' ?>">
                                                <?= $active ? 'Active' : 'Inactive' ?>
                                        </span>
                                    </div>

                                    <div class="config-card-body">
                                        <div class="config-title"><?= htmlspecialchars($background['background_name']) ?>
                                        </div>
                                        <div class="config-meta">Sort order: <?= (int) ($background['sort_order'] ?? 0) ?>
                                        </div>
                                    </div>

                                    <div class="config-card-footer">
                                        <form action="admin_update_configuration.php" method="POST"
                                            class="config-edit-grid">
                                            <input type="hidden" name="csrf_token"
                                                value="<?= htmlspecialchars($csrf_token) ?>">
                                            <input type="hidden" name="config_type" value="background">
                                            <input type="hidden" name="background_id"
                                                value="<?= (int) $background['background_id'] ?>">

                                            <input type="text" name="background_name"
                                                value="<?= htmlspecialchars($background['background_name']) ?>"
                                                class="form-input">
                                            <input type="number" name="sort_order"
                                                value="<?= (int) ($background['sort_order'] ?? 0) ?>" class="form-input">

                                            <div class="config-actions">
                                                <button type="submit" name="action" value="update_background"
                                                    class="btn-sm btn-save">Save</button>

                                                    <?php if ($active): ?>
                                                    <button type="submit" name="action" value="deactivate_background"
                                                        class="btn-sm btn-deactivate">Deactivate</button>
                                                    <?php else: ?>
                                                    <button type="submit" name="action" value="activate_background"
                                                        class="btn-sm btn-activate">Activate</button>
                                                    <?php endif; ?>

                                                <button type="submit" name="action" value="delete_background"
                                                    class="btn-sm btn-delete"
                                                    onclick="return confirm('Delete this background?');">Delete</button>
                                            </div>
                                        </form>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </section>

                <!-- Organizations -->
                <section class="config-section">
                    <button type="button" class="section-header" onclick="toggleSection(this)">
                        <h2><i class="fa-solid fa-building-columns"></i> Organizations</h2>
                        <span class="section-toggle"><i class="fa-solid fa-chevron-down"></i></span>
                    </button>

                    <div class="section-body">
                        <form action="admin_update_configuration.php" method="POST" class="add-form-row">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                            <input type="hidden" name="config_type" value="org">
                            <input type="text" name="org_name" class="form-input" placeholder="Add new organization..."
                                required>
                            <input type="number" name="sort_order" class="form-input form-input-sm" placeholder="Sort"
                                value="0">
                            <button type="submit" name="action" value="add_org" class="btn-sm btn-add">Add</button>
                        </form>

                        <div class="config-grid">
                            <?php foreach ($org_options as $org): ?>
                                    <?php $active = ((int) ($org['is_active'] ?? 0) === 1); ?>
                                <article class="config-card">
                                    <div class="config-card-top">
                                        <span class="config-type-tag">Organization</span>
                                        <span class="status-badge <?= $active ? 'badge-active' : 'badge-inactive' ?>">
                                                <?= $active ? 'Active' : 'Inactive' ?>
                                        </span>
                                    </div>

                                    <div class="config-card-body">
                                        <div class="config-title"><?= htmlspecialchars($org['org_name']) ?></div>
                                        <div class="config-meta">Sort order: <?= (int) ($org['sort_order'] ?? 0) ?></div>
                                    </div>

                                    <div class="config-card-footer">
                                        <form action="admin_update_configuration.php" method="POST"
                                            class="config-edit-grid">
                                            <input type="hidden" name="csrf_token"
                                                value="<?= htmlspecialchars($csrf_token) ?>">
                                            <input type="hidden" name="config_type" value="org">
                                            <input type="hidden" name="org_option_id"
                                                value="<?= (int) $org['org_option_id'] ?>">

                                            <input type="text" name="org_name"
                                                value="<?= htmlspecialchars($org['org_name']) ?>" class="form-input">
                                            <input type="number" name="sort_order"
                                                value="<?= (int) ($org['sort_order'] ?? 0) ?>" class="form-input">

                                            <div class="config-actions">
                                                <button type="submit" name="action" value="update_org"
                                                    class="btn-sm btn-save">Save</button>

                                                    <?php if ($active): ?>
                                                    <button type="submit" name="action" value="deactivate_org"
                                                        class="btn-sm btn-deactivate">Deactivate</button>
                                                    <?php else: ?>
                                                    <button type="submit" name="action" value="activate_org"
                                                        class="btn-sm btn-activate">Activate</button>
                                                    <?php endif; ?>

                                                <button type="submit" name="action" value="delete_org"
                                                    class="btn-sm btn-delete"
                                                    onclick="return confirm('Delete this organization?');">Delete</button>
                                            </div>
                                        </form>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </section>

                <!-- Activity Types -->
                <section class="config-section">
                    <button type="button" class="section-header" onclick="toggleSection(this)">
                        <h2><i class="fa-solid fa-layer-group"></i> Activity Types</h2>
                        <span class="section-toggle"><i class="fa-solid fa-chevron-down"></i></span>
                    </button>

                    <div class="section-body">
                        <form action="admin_update_configuration.php" method="POST" class="add-form-row">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                            <input type="hidden" name="config_type" value="activity_type">
                            <input type="text" name="activity_type_name" class="form-input"
                                placeholder="Add new activity type..." required>
                            <input type="number" name="sort_order" class="form-input form-input-sm" placeholder="Sort"
                                value="0">
                            <button type="submit" name="action" value="add_activity_type"
                                class="btn-sm btn-add">Add</button>
                        </form>

                        <div class="config-grid">
                            <?php foreach ($activity_types as $type): ?>
                                    <?php $active = ((int) ($type['is_active'] ?? 0) === 1); ?>
                                <article class="config-card">
                                    <div class="config-card-top">
                                        <span class="config-type-tag">Activity Type</span>
                                        <span class="status-badge <?= $active ? 'badge-active' : 'badge-inactive' ?>">
                                                <?= $active ? 'Active' : 'Inactive' ?>
                                        </span>
                                    </div>

                                    <div class="config-card-body">
                                        <div class="config-title"><?= htmlspecialchars($type['activity_type_name']) ?></div>
                                        <div class="config-meta">Sort order: <?= (int) ($type['sort_order'] ?? 0) ?></div>
                                    </div>

                                    <div class="config-card-footer">
                                        <form action="admin_update_configuration.php" method="POST"
                                            class="config-edit-grid">
                                            <input type="hidden" name="csrf_token"
                                                value="<?= htmlspecialchars($csrf_token) ?>">
                                            <input type="hidden" name="config_type" value="activity_type">
                                            <input type="hidden" name="activity_type_id"
                                                value="<?= (int) $type['activity_type_id'] ?>">

                                            <input type="text" name="activity_type_name"
                                                value="<?= htmlspecialchars($type['activity_type_name']) ?>"
                                                class="form-input">
                                            <input type="number" name="sort_order"
                                                value="<?= (int) ($type['sort_order'] ?? 0) ?>" class="form-input">

                                            <div class="config-actions">
                                                <button type="submit" name="action" value="update_activity_type"
                                                    class="btn-sm btn-save">Save</button>

                                                    <?php if ($active): ?>
                                                    <button type="submit" name="action" value="deactivate_activity_type"
                                                        class="btn-sm btn-deactivate">Deactivate</button>
                                                    <?php else: ?>
                                                    <button type="submit" name="action" value="activate_activity_type"
                                                        class="btn-sm btn-activate">Activate</button>
                                                    <?php endif; ?>

                                                <button type="submit" name="action" value="delete_activity_type"
                                                    class="btn-sm btn-delete"
                                                    onclick="return confirm('Delete this activity type?');">Delete</button>
                                            </div>
                                        </form>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </section>

                <!-- Series Options -->
                <section class="config-section">
                    <button type="button" class="section-header" onclick="toggleSection(this)">
                        <h2><i class="fa-solid fa-diagram-project"></i> Series Options</h2>
                        <span class="section-toggle"><i class="fa-solid fa-chevron-down"></i></span>
                    </button>

                    <div class="section-body">
                        <form action="admin_update_configuration.php" method="POST" class="add-form-row">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                            <input type="hidden" name="config_type" value="series">
                            <input type="text" name="series_name" class="form-input"
                                placeholder="Add new series option..." required>
                            <input type="number" name="sort_order" class="form-input form-input-sm" placeholder="Sort"
                                value="0">
                            <button type="submit" name="action" value="add_series" class="btn-sm btn-add">Add</button>
                        </form>

                        <div class="config-grid">
                            <?php foreach ($series_options as $series): ?>
                                    <?php $active = ((int) ($series['is_active'] ?? 0) === 1); ?>
                                <article class="config-card">
                                    <div class="config-card-top">
                                        <span class="config-type-tag">Series</span>
                                        <span class="status-badge <?= $active ? 'badge-active' : 'badge-inactive' ?>">
                                                <?= $active ? 'Active' : 'Inactive' ?>
                                        </span>
                                    </div>

                                    <div class="config-card-body">
                                        <div class="config-title"><?= htmlspecialchars($series['series_name']) ?></div>
                                        <div class="config-meta">Sort order: <?= (int) ($series['sort_order'] ?? 0) ?></div>
                                    </div>

                                    <div class="config-card-footer">
                                        <form action="admin_update_configuration.php" method="POST"
                                            class="config-edit-grid">
                                            <input type="hidden" name="csrf_token"
                                                value="<?= htmlspecialchars($csrf_token) ?>">
                                            <input type="hidden" name="config_type" value="series">
                                            <input type="hidden" name="series_option_id"
                                                value="<?= (int) $series['series_option_id'] ?>">

                                            <input type="text" name="series_name"
                                                value="<?= htmlspecialchars($series['series_name']) ?>" class="form-input">
                                            <input type="number" name="sort_order"
                                                value="<?= (int) ($series['sort_order'] ?? 0) ?>" class="form-input">

                                            <div class="config-actions">
                                                <button type="submit" name="action" value="update_series"
                                                    class="btn-sm btn-save">Save</button>

                                                    <?php if ($active): ?>
                                                    <button type="submit" name="action" value="deactivate_series"
                                                        class="btn-sm btn-deactivate">Deactivate</button>
                                                    <?php else: ?>
                                                    <button type="submit" name="action" value="activate_series"
                                                        class="btn-sm btn-activate">Activate</button>
                                                    <?php endif; ?>

                                                <button type="submit" name="action" value="delete_series"
                                                    class="btn-sm btn-delete"
                                                    onclick="return confirm('Delete this series option?');">Delete</button>
                                            </div>
                                        </form>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </section>

                <!-- Requirement Mapping -->
                <section class="config-section">
                    <button type="button" class="section-header" onclick="toggleSection(this)">
                        <h2><i class="fa-solid fa-table-cells-large"></i> Requirement Mapping</h2>
                        <span class="section-toggle"><i class="fa-solid fa-chevron-down"></i></span>
                    </button>

                    <div class="section-body">
                        <form action="admin_update_configuration.php" method="POST" class="mapping-add-form">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                            <input type="hidden" name="config_type" value="mapping">

                            <select name="background_id" class="form-input" required>
                                <option value="">Select background</option>
                                <?php foreach ($background_options as $background): ?>
                                    <option value="<?= (int) $background['background_id'] ?>">
                                            <?= htmlspecialchars($background['background_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <select name="activity_type_id" class="form-input" required>
                                <option value="">Select activity type</option>
                                <?php foreach ($activity_types as $type): ?>
                                    <option value="<?= (int) $type['activity_type_id'] ?>">
                                            <?= htmlspecialchars($type['activity_type_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <select name="req_template_id" class="form-input" required>
                                <option value="">Select requirement template</option>
                                <?php foreach ($requirement_templates as $tpl): ?>
                                    <option value="<?= (int) $tpl['req_template_id'] ?>">
                                            <?= htmlspecialchars($tpl['req_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <button type="submit" name="action" value="add_mapping" class="btn-sm btn-add">Add
                                Mapping</button>
                        </form>

                        <?php foreach ($grouped_map as $background => $type_map): ?>
                            <section class="mapping-group">
                                <div class="mapping-group-header"><?= htmlspecialchars($background) ?></div>

                                    <?php foreach ($type_map as $activity_type_name => $map_rows): ?>
                                    <div class="mapping-activity">
                                        <div class="mapping-activity-title"><?= htmlspecialchars($activity_type_name) ?></div>

                                                <?php foreach ($map_rows as $map): ?>
                                                        <?php $map_active = ((int) ($map['is_active'] ?? 0) === 1); ?>
                                            <div class="mapping-row">
                                                <span class="mapping-req-name"><?= htmlspecialchars($map['req_name']) ?></span>

                                                <span class="status-badge <?= $map_active ? 'badge-active' : 'badge-inactive' ?>">
                                                                <?= $map_active ? 'Active' : 'Inactive' ?>
                                                </span>

                                                <form action="admin_update_configuration.php" method="POST" class="mapping-actions">
                                                    <input type="hidden" name="csrf_token"
                                                        value="<?= htmlspecialchars($csrf_token) ?>">
                                                    <input type="hidden" name="config_type" value="mapping">
                                                    <input type="hidden" name="config_map_id"
                                                        value="<?= (int) $map['config_map_id'] ?>">

                                                                <?php if ($map_active): ?>
                                                        <button type="submit" name="action" value="deactivate_mapping"
                                                            class="btn-sm btn-deactivate">Deactivate</button>
                                                                <?php else: ?>
                                                        <button type="submit" name="action" value="activate_mapping"
                                                            class="btn-sm btn-activate">Activate</button>
                                                                <?php endif; ?>

                                                    <button type="submit" name="action" value="delete_mapping"
                                                        class="btn-sm btn-delete"
                                                        onclick="return confirm('Delete this mapping?');">Delete</button>
                                                </form>
                                            </div>
                                                <?php endforeach; ?>
                                    </div>
                                    <?php endforeach; ?>
                            </section>
                        <?php endforeach; ?>
                    </div>
                </section>

                <!-- Requirement Templates -->
                <section class="config-section">
                    <button type="button" class="section-header" onclick="toggleSection(this)">
                        <h2><i class="fa-solid fa-file-circle-check"></i> Requirement Templates & Deadline Rules</h2>
                        <span class="section-toggle"><i class="fa-solid fa-chevron-down"></i></span>
                    </button>

                    <div class="section-body">
                        <div class="template-grid">
                            <?php foreach ($requirement_templates as $tpl): ?>
                                    <?php $active = ((int) ($tpl['is_active'] ?? 0) === 1); ?>
                                <article class="template-card">
                                    <div class="template-card-top">
                                        <span
                                            class="config-type-tag"><?= htmlspecialchars($tpl['default_due_basis'] ?? 'manual') ?></span>
                                        <span class="status-badge <?= $active ? 'badge-active' : 'badge-inactive' ?>">
                                                <?= $active ? 'Active' : 'Inactive' ?>
                                        </span>
                                    </div>

                                    <div class="template-card-body">
                                        <div class="template-title"><?= htmlspecialchars($tpl['req_name']) ?></div>

                                        <div class="template-meta">
                                            <i class="fa-solid fa-clock"></i>
                                                <?= (int) ($tpl['default_due_offset_days'] ?? 0) ?> day(s) offset
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
                                        <form action="admin_update_configuration.php" method="POST"
                                            class="config-edit-grid">
                                            <input type="hidden" name="csrf_token"
                                                value="<?= htmlspecialchars($csrf_token) ?>">
                                            <input type="hidden" name="config_type" value="template">
                                            <input type="hidden" name="req_template_id"
                                                value="<?= (int) $tpl['req_template_id'] ?>">

                                            <input type="text" name="req_name"
                                                value="<?= htmlspecialchars($tpl['req_name']) ?>" class="form-input"
                                                placeholder="Requirement name">
                                            <textarea name="req_desc" class="form-input"
                                                rows="2"><?= htmlspecialchars($tpl['req_desc'] ?? '') ?></textarea>
                                            <input type="text" name="template_url"
                                                value="<?= htmlspecialchars($tpl['template_url'] ?? '') ?>"
                                                class="form-input" placeholder="Template URL">
                                            <input type="number" name="default_due_offset_days" min="0"
                                                value="<?= (int) ($tpl['default_due_offset_days'] ?? 0) ?>"
                                                class="form-input" placeholder="Days offset">

                                            <select name="default_due_basis" class="form-input">
                                                    <?php foreach ($basis_options as $basis): ?>
                                                    <option value="<?= htmlspecialchars($basis) ?>"
                                                        <?= (($tpl['default_due_basis'] ?? '') === $basis) ? 'selected' : '' ?>>
                                                                <?= htmlspecialchars($basis) ?>
                                                    </option>
                                                    <?php endforeach; ?>
                                            </select>

                                            <div class="config-actions">
                                                <button type="submit" name="action" value="update_template"
                                                    class="btn-sm btn-save">Save</button>

                                                    <?php if ($active): ?>
                                                    <button type="submit" name="action" value="deactivate_template"
                                                        class="btn-sm btn-deactivate">Deactivate</button>
                                                    <?php else: ?>
                                                    <button type="submit" name="action" value="activate_template"
                                                        class="btn-sm btn-activate">Activate</button>
                                                    <?php endif; ?>
                                            </div>
                                        </form>
                                    </div>
                                </article>
                            <?php endforeach; ?>

                            <article class="template-card">
                                <div class="template-card-top">
                                    <span class="config-type-tag">New Template</span>
                                </div>

                                <div class="template-card-body">
                                    <div class="template-title">Add Requirement Template</div>
                                </div>

                                <div class="template-card-footer">
                                    <form action="admin_update_configuration.php" method="POST"
                                        class="config-edit-grid">
                                        <input type="hidden" name="csrf_token"
                                            value="<?= htmlspecialchars($csrf_token) ?>">
                                        <input type="hidden" name="config_type" value="template">

                                        <input type="text" name="req_name" class="form-input"
                                            placeholder="Requirement name" required>
                                        <textarea name="req_desc" class="form-input" rows="2"
                                            placeholder="Description"></textarea>
                                        <input type="text" name="template_url" class="form-input"
                                            placeholder="Template URL">
                                        <input type="number" name="default_due_offset_days" min="0" value="7"
                                            class="form-input" placeholder="Days offset">

                                        <select name="default_due_basis" class="form-input">
                                            <?php foreach ($basis_options as $basis): ?>
                                                <option value="<?= htmlspecialchars($basis) ?>">
                                                    <?= htmlspecialchars($basis) ?></option>
                                            <?php endforeach; ?>
                                        </select>

                                        <div class="config-actions">
                                            <button type="submit" name="action" value="add_template"
                                                class="btn-sm btn-add">Add Template</button>
                                        </div>
                                    </form>
                                </div>
                            </article>
                        </div>
                    </div>
                </section>
            </section>

            <?php include 'assets/includes/footer.php'; ?>
        </main>
    </div>

    <script src="../app/script/layout.js?v=1"></script>
    <script>
        function toggleSection(headerButton) {
            const section = headerButton.closest('.config-section');
            section.classList.toggle('collapsed');
        }

        document.addEventListener('DOMContentLoaded', function () {
            const sections = document.querySelectorAll('.config-section');
            sections.forEach((section, index) => {
                if (index > 0) {
                    section.classList.add('collapsed');
                }
            });
        });

        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function (e) {
                const btn = e.submitter;
                if (!btn) return;

                const destructiveActions = [
                    'delete_background', 'delete_org', 'delete_activity_type', 'delete_series',
                    'delete_mapping', 'deactivate_background', 'deactivate_org',
                    'deactivate_activity_type', 'deactivate_series', 'deactivate_template'
                ];

                if (destructiveActions.includes(btn.value)) {
                    const label = btn.textContent.trim();
                    if (!confirm(`Are you sure you want to ${label.toLowerCase()} this item?`)) {
                        e.preventDefault();
                    }
                }
            });
        });
    </script>
</body>

</html>