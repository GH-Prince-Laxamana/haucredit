<?php
session_start();
require_once "../app/database.php";
require_once "../app/security_headers.php";
send_security_headers();

if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit();
}

if (($_SESSION["role"] ?? "") !== "admin") {
    popup_error("Access denied.");
}

/* ================= HELPERS ================= */
function normalizeActiveClass($is_active): string
{
    return ((int) $is_active === 1) ? 'approved' : 'needs-revision';
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
                    <h1>Configurations</h1>
                    <p>Manage backgrounds, organizations, activity types, series options, templates, and requirement
                        mappings.</p>
                </div>
            </header>

            <section class="content my-events-page">
                <div class="summary-strip">
                    <div class="summary-card">
                        <span class="summary-num"><?= $summary['background_total'] ?></span>
                        <span class="summary-label">Backgrounds</span>
                    </div>

                    <div class="summary-card">
                        <span class="summary-num"><?= $summary['org_total'] ?></span>
                        <span class="summary-label">Organizations</span>
                    </div>

                    <div class="summary-card">
                        <span class="summary-num"><?= $summary['activity_total'] ?></span>
                        <span class="summary-label">Activity Types</span>
                    </div>

                    <div class="summary-card">
                        <span class="summary-num"><?= $summary['series_total'] ?></span>
                        <span class="summary-label">Series Options</span>
                    </div>

                    <div class="summary-card">
                        <span class="summary-num"><?= $summary['template_total'] ?></span>
                        <span class="summary-label">Requirement Templates</span>
                    </div>

                    <div class="summary-card">
                        <span class="summary-num"><?= $summary['mapping_total'] ?></span>
                        <span class="summary-label">Requirement Mappings</span>
                    </div>
                </div>

                <section class="detail-card" style="margin-bottom: 1.25rem;">
                    <div class="card-header">
                        <h2><i class="fa-solid fa-layer-group"></i> Background Options</h2>
                    </div>
                    <div class="card-body">
                        <form action="admin_update_configuration.php" method="POST" class="search-wrap"
                            style="margin-bottom:1rem;">
                            <input type="hidden" name="config_type" value="background">
                            <input type="text" name="background_name" class="search-input"
                                placeholder="Add new background..." required>
                            <input type="number" name="sort_order" class="search-input" placeholder="Sort"
                                style="max-width:120px;" value="0">
                            <button type="submit" name="action" value="add_background" class="btn-primary">Add</button>
                        </form>

                        <div class="events-grid">
                            <?php foreach ($background_options as $background): ?>
                                <?php $status_class = normalizeActiveClass($background['is_active'] ?? 0); ?>
                                <article class="event-card">
                                    <div class="event-card-top">
                                        <span class="event-type-tag">Background</span>
                                        <span class="event-status status-<?= htmlspecialchars($status_class) ?>">
                                            <span class="status-dot"></span>
                                            <span
                                                class="status-text"><?= ((int) $background['is_active'] === 1) ? 'Active' : 'Inactive' ?></span>
                                        </span>
                                    </div>

                                    <div class="event-card-body">
                                        <h3 class="event-title"><?= htmlspecialchars($background['background_name']) ?></h3>

                                        <div class="event-meta">
                                            <div class="meta-row">
                                                <span class="meta-icon"><i class="fa-solid fa-sort"></i></span>
                                                <span>Sort Order: <?= (int) ($background['sort_order'] ?? 0) ?></span>
                                            </div>
                                        </div>
                                    </div>

                                    <footer class="event-card-footer" style="display:block;">
                                        <form action="admin_update_configuration.php" method="POST"
                                            style="display:grid; gap:.75rem;">
                                            <input type="hidden" name="config_type" value="background">
                                            <input type="hidden" name="background_id"
                                                value="<?= (int) $background['background_id'] ?>">

                                            <input type="text" name="background_name"
                                                value="<?= htmlspecialchars($background['background_name']) ?>"
                                                class="search-input">
                                            <input type="number" name="sort_order"
                                                value="<?= (int) ($background['sort_order'] ?? 0) ?>" class="search-input">

                                            <div class="card-actions" style="flex-wrap:wrap; gap:.5rem;">
                                                <button type="submit" name="action" value="update_background"
                                                    class="btn-secondary btn-edit">Save</button>

                                                <?php if ((int) $background['is_active'] === 1): ?>
                                                    <button type="submit" name="action" value="deactivate_background"
                                                        class="btn-primary btn-danger">Deactivate</button>
                                                <?php else: ?>
                                                    <button type="submit" name="action" value="activate_background"
                                                        class="btn-primary btn-view">Activate</button>
                                                <?php endif; ?>

                                                <button type="submit" name="action" value="delete_background"
                                                    class="btn-secondary"
                                                    onclick="return confirm('Delete this background?');">Delete</button>
                                            </div>
                                        </form>
                                    </footer>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </section>

                <section class="detail-card" style="margin-bottom: 1.25rem;">
                    <div class="card-header">
                        <h2><i class="fa-solid fa-building-columns"></i> Organizations</h2>
                    </div>
                    <div class="card-body">
                        <form action="admin_update_configuration.php" method="POST" class="search-wrap"
                            style="margin-bottom:1rem;">
                            <input type="hidden" name="config_type" value="org">
                            <input type="text" name="org_name" class="search-input"
                                placeholder="Add new organization..." required>
                            <input type="number" name="sort_order" class="search-input" placeholder="Sort"
                                style="max-width:120px;" value="0">
                            <button type="submit" name="action" value="add_org" class="btn-primary">Add</button>
                        </form>

                        <div class="events-grid">
                            <?php foreach ($org_options as $org): ?>
                                <?php $status_class = normalizeActiveClass($org['is_active'] ?? 0); ?>
                                <article class="event-card">
                                    <div class="event-card-top">
                                        <span class="event-type-tag">Org</span>
                                        <span class="event-status status-<?= htmlspecialchars($status_class) ?>">
                                            <span class="status-dot"></span>
                                            <span
                                                class="status-text"><?= ((int) $org['is_active'] === 1) ? 'Active' : 'Inactive' ?></span>
                                        </span>
                                    </div>

                                    <div class="event-card-body">
                                        <h3 class="event-title"><?= htmlspecialchars($org['org_name']) ?></h3>

                                        <div class="event-meta">
                                            <div class="meta-row">
                                                <span class="meta-icon"><i class="fa-solid fa-sort"></i></span>
                                                <span>Sort Order: <?= (int) ($org['sort_order'] ?? 0) ?></span>
                                            </div>
                                        </div>
                                    </div>

                                    <footer class="event-card-footer" style="display:block;">
                                        <form action="admin_update_configuration.php" method="POST"
                                            style="display:grid; gap:.75rem;">
                                            <input type="hidden" name="config_type" value="org">
                                            <input type="hidden" name="org_option_id"
                                                value="<?= (int) $org['org_option_id'] ?>">

                                            <input type="text" name="org_name"
                                                value="<?= htmlspecialchars($org['org_name']) ?>" class="search-input">
                                            <input type="number" name="sort_order"
                                                value="<?= (int) ($org['sort_order'] ?? 0) ?>" class="search-input">

                                            <div class="card-actions" style="flex-wrap:wrap; gap:.5rem;">
                                                <button type="submit" name="action" value="update_org"
                                                    class="btn-secondary btn-edit">Save</button>

                                                <?php if ((int) $org['is_active'] === 1): ?>
                                                    <button type="submit" name="action" value="deactivate_org"
                                                        class="btn-primary btn-danger">Deactivate</button>
                                                <?php else: ?>
                                                    <button type="submit" name="action" value="activate_org"
                                                        class="btn-primary btn-view">Activate</button>
                                                <?php endif; ?>

                                                <button type="submit" name="action" value="delete_org" class="btn-secondary"
                                                    onclick="return confirm('Delete this organization?');">Delete</button>
                                            </div>
                                        </form>
                                    </footer>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </section>

                <section class="detail-card" style="margin-bottom: 1.25rem;">
                    <div class="card-header">
                        <h2><i class="fa-solid fa-layer-group"></i> Activity Types</h2>
                    </div>
                    <div class="card-body">
                        <form action="admin_update_configuration.php" method="POST" class="search-wrap"
                            style="margin-bottom:1rem;">
                            <input type="hidden" name="config_type" value="activity_type">
                            <input type="text" name="activity_type_name" class="search-input"
                                placeholder="Add new activity type..." required>
                            <input type="number" name="sort_order" class="search-input" placeholder="Sort"
                                style="max-width:120px;" value="0">
                            <button type="submit" name="action" value="add_activity_type"
                                class="btn-primary">Add</button>
                        </form>

                        <div class="events-grid">
                            <?php foreach ($activity_types as $type): ?>
                                <?php $status_class = normalizeActiveClass($type['is_active'] ?? 0); ?>
                                <article class="event-card">
                                    <div class="event-card-top">
                                        <span class="event-type-tag">Type</span>
                                        <span class="event-status status-<?= htmlspecialchars($status_class) ?>">
                                            <span class="status-dot"></span>
                                            <span
                                                class="status-text"><?= ((int) $type['is_active'] === 1) ? 'Active' : 'Inactive' ?></span>
                                        </span>
                                    </div>

                                    <div class="event-card-body">
                                        <h3 class="event-title"><?= htmlspecialchars($type['activity_type_name']) ?></h3>
                                    </div>

                                    <footer class="event-card-footer" style="display:block;">
                                        <form action="admin_update_configuration.php" method="POST"
                                            style="display:grid; gap:.75rem;">
                                            <input type="hidden" name="config_type" value="activity_type">
                                            <input type="hidden" name="activity_type_id"
                                                value="<?= (int) $type['activity_type_id'] ?>">

                                            <input type="text" name="activity_type_name"
                                                value="<?= htmlspecialchars($type['activity_type_name']) ?>"
                                                class="search-input">
                                            <input type="number" name="sort_order"
                                                value="<?= (int) ($type['sort_order'] ?? 0) ?>" class="search-input">

                                            <div class="card-actions" style="flex-wrap:wrap; gap:.5rem;">
                                                <button type="submit" name="action" value="update_activity_type"
                                                    class="btn-secondary btn-edit">Save</button>

                                                <?php if ((int) $type['is_active'] === 1): ?>
                                                    <button type="submit" name="action" value="deactivate_activity_type"
                                                        class="btn-primary btn-danger">Deactivate</button>
                                                <?php else: ?>
                                                    <button type="submit" name="action" value="activate_activity_type"
                                                        class="btn-primary btn-view">Activate</button>
                                                <?php endif; ?>

                                                <button type="submit" name="action" value="delete_activity_type"
                                                    class="btn-secondary"
                                                    onclick="return confirm('Delete this activity type?');">Delete</button>
                                            </div>
                                        </form>
                                    </footer>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </section>

                <section class="detail-card" style="margin-bottom: 1.25rem;">
                    <div class="card-header">
                        <h2><i class="fa-solid fa-diagram-project"></i> Series Options</h2>
                    </div>
                    <div class="card-body">
                        <form action="admin_update_configuration.php" method="POST" class="search-wrap"
                            style="margin-bottom:1rem;">
                            <input type="hidden" name="config_type" value="series">
                            <input type="text" name="series_name" class="search-input"
                                placeholder="Add new series option..." required>
                            <input type="number" name="sort_order" class="search-input" placeholder="Sort"
                                style="max-width:120px;" value="0">
                            <button type="submit" name="action" value="add_series" class="btn-primary">Add</button>
                        </form>

                        <div class="events-grid">
                            <?php foreach ($series_options as $series): ?>
                                <?php $status_class = normalizeActiveClass($series['is_active'] ?? 0); ?>
                                <article class="event-card">
                                    <div class="event-card-top">
                                        <span class="event-type-tag">Series</span>
                                        <span class="event-status status-<?= htmlspecialchars($status_class) ?>">
                                            <span class="status-dot"></span>
                                            <span
                                                class="status-text"><?= ((int) $series['is_active'] === 1) ? 'Active' : 'Inactive' ?></span>
                                        </span>
                                    </div>

                                    <div class="event-card-body">
                                        <h3 class="event-title"><?= htmlspecialchars($series['series_name']) ?></h3>
                                    </div>

                                    <footer class="event-card-footer" style="display:block;">
                                        <form action="admin_update_configuration.php" method="POST"
                                            style="display:grid; gap:.75rem;">
                                            <input type="hidden" name="config_type" value="series">
                                            <input type="hidden" name="series_option_id"
                                                value="<?= (int) $series['series_option_id'] ?>">

                                            <input type="text" name="series_name"
                                                value="<?= htmlspecialchars($series['series_name']) ?>"
                                                class="search-input">
                                            <input type="number" name="sort_order"
                                                value="<?= (int) ($series['sort_order'] ?? 0) ?>" class="search-input">

                                            <div class="card-actions" style="flex-wrap:wrap; gap:.5rem;">
                                                <button type="submit" name="action" value="update_series"
                                                    class="btn-secondary btn-edit">Save</button>

                                                <?php if ((int) $series['is_active'] === 1): ?>
                                                    <button type="submit" name="action" value="deactivate_series"
                                                        class="btn-primary btn-danger">Deactivate</button>
                                                <?php else: ?>
                                                    <button type="submit" name="action" value="activate_series"
                                                        class="btn-primary btn-view">Activate</button>
                                                <?php endif; ?>

                                                <button type="submit" name="action" value="delete_series"
                                                    class="btn-secondary"
                                                    onclick="return confirm('Delete this series option?');">Delete</button>
                                            </div>
                                        </form>
                                    </footer>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </section>

                <section class="detail-card" style="margin-bottom: 1.25rem;">
                    <div class="card-header">
                        <h2><i class="fa-solid fa-table-cells-large"></i> Requirement Mapping</h2>
                    </div>
                    <div class="card-body">
                        <form action="admin_update_configuration.php" method="POST" class="search-wrap"
                            style="margin-bottom:1rem; flex-wrap:wrap;">
                            <input type="hidden" name="config_type" value="mapping">

                            <select name="background_id" class="search-input" style="max-width:220px;" required>
                                <option value="">Select background</option>
                                <?php foreach ($background_options as $background): ?>
                                    <option value="<?= (int) $background['background_id'] ?>">
                                        <?= htmlspecialchars($background['background_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <select name="activity_type_id" class="search-input" style="max-width:260px;" required>
                                <option value="">Select activity type</option>
                                <?php foreach ($activity_types as $type): ?>
                                    <option value="<?= (int) $type['activity_type_id'] ?>">
                                        <?= htmlspecialchars($type['activity_type_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <select name="req_template_id" class="search-input" style="max-width:300px;" required>
                                <option value="">Select requirement template</option>
                                <?php foreach ($requirement_templates as $tpl): ?>
                                    <option value="<?= (int) $tpl['req_template_id'] ?>">
                                        <?= htmlspecialchars($tpl['req_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <button type="submit" name="action" value="add_mapping" class="btn-primary">Add
                                Mapping</button>
                        </form>

                        <?php foreach ($grouped_map as $background => $type_map): ?>
                            <section class="detail-card" style="margin-bottom: 1rem;">
                                <div class="card-header">
                                    <h2><?= htmlspecialchars($background) ?></h2>
                                </div>
                                <div class="card-body">
                                    <?php foreach ($type_map as $activity_type_name => $map_rows): ?>
                                        <div class="doc-remarks-box" style="margin-bottom: 1rem;">
                                            <strong><?= htmlspecialchars($activity_type_name) ?></strong>

                                            <div style="margin-top:.75rem; display:grid; gap:.5rem;">
                                                <?php foreach ($map_rows as $map): ?>
                                                    <?php $status_class = normalizeActiveClass($map['is_active'] ?? 0); ?>
                                                    <div class="t-row" style="gap:.75rem; align-items:center;">
                                                        <span><?= htmlspecialchars($map['req_name']) ?></span>

                                                        <span class="event-status status-<?= htmlspecialchars($status_class) ?>">
                                                            <span class="status-dot"></span>
                                                            <span
                                                                class="status-text"><?= ((int) $map['is_active'] === 1) ? 'Active' : 'Inactive' ?></span>
                                                        </span>

                                                        <form action="admin_update_configuration.php" method="POST"
                                                            style="display:inline-flex; gap:.5rem; margin-left:auto;">
                                                            <input type="hidden" name="config_type" value="mapping">
                                                            <input type="hidden" name="config_map_id"
                                                                value="<?= (int) $map['config_map_id'] ?>">

                                                            <?php if ((int) $map['is_active'] === 1): ?>
                                                                <button type="submit" name="action" value="deactivate_mapping"
                                                                    class="btn-secondary btn-edit">Deactivate</button>
                                                            <?php else: ?>
                                                                <button type="submit" name="action" value="activate_mapping"
                                                                    class="btn-primary btn-view">Activate</button>
                                                            <?php endif; ?>

                                                            <button type="submit" name="action" value="delete_mapping"
                                                                class="btn-primary btn-danger"
                                                                onclick="return confirm('Delete this mapping?');">Delete</button>
                                                        </form>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </section>
                        <?php endforeach; ?>
                    </div>
                </section>

                <section class="detail-card">
                    <div class="card-header">
                        <h2><i class="fa-solid fa-file-circle-check"></i> Requirement Templates & Deadline Rules</h2>
                    </div>

                    <div class="card-body">
                        <div class="events-grid">
                            <?php foreach ($requirement_templates as $tpl): ?>
                                <?php $status_class = normalizeActiveClass($tpl['is_active'] ?? 0); ?>

                                <article class="event-card">
                                    <div class="event-card-top">
                                        <span class="event-type-tag">
                                            <?= htmlspecialchars($tpl['default_due_basis'] ?? 'manual') ?>
                                        </span>

                                        <span class="event-status status-<?= htmlspecialchars($status_class) ?>">
                                            <span class="status-dot"></span>
                                            <span class="status-text">
                                                <?= ((int) ($tpl['is_active'] ?? 0) === 1) ? 'Active' : 'Inactive' ?>
                                            </span>
                                        </span>
                                    </div>

                                    <div class="event-card-body">
                                        <h3 class="event-title"><?= htmlspecialchars($tpl['req_name']) ?></h3>

                                        <div class="event-meta">
                                            <div class="meta-row">
                                                <span class="meta-icon"><i class="fa-solid fa-clock"></i></span>
                                                <span><?= (int) ($tpl['default_due_offset_days'] ?? 0) ?> day(s)</span>
                                            </div>

                                            <div class="meta-row">
                                                <span class="meta-icon"><i class="fa-solid fa-link"></i></span>
                                                <span><?= !empty($tpl['template_url']) ? 'Template linked' : 'No template link' ?></span>
                                            </div>

                                            <div class="meta-row">
                                                <span class="meta-icon"><i class="fa-solid fa-calendar-plus"></i></span>
                                                <span>
                                                    Updated
                                                    <?= !empty($tpl['updated_at']) ? date('M j, Y', strtotime($tpl['updated_at'])) : 'N/A' ?>
                                                </span>
                                            </div>
                                        </div>

                                        <?php if (!empty($tpl['req_desc'])): ?>
                                            <div class="doc-remarks-box" style="margin-top: 1rem;">
                                                <strong>Description</strong>
                                                <p><?= nl2br(htmlspecialchars($tpl['req_desc'])) ?></p>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <footer class="event-card-footer" style="display:block;">
                                        <form action="admin_update_configuration.php" method="POST"
                                            style="display:grid; gap:.75rem;">
                                            <input type="hidden" name="config_type" value="template">
                                            <input type="hidden" name="req_template_id"
                                                value="<?= (int) $tpl['req_template_id'] ?>">

                                            <input type="text" name="req_name"
                                                value="<?= htmlspecialchars($tpl['req_name']) ?>" class="search-input">
                                            <textarea name="req_desc" class="search-input"
                                                rows="3"><?= htmlspecialchars($tpl['req_desc'] ?? '') ?></textarea>
                                            <input type="text" name="template_url"
                                                value="<?= htmlspecialchars($tpl['template_url'] ?? '') ?>"
                                                class="search-input" placeholder="Template URL">
                                            <input type="number" name="default_due_offset_days" min="0"
                                                value="<?= (int) ($tpl['default_due_offset_days'] ?? 0) ?>"
                                                class="search-input">

                                            <select name="default_due_basis" class="search-input">
                                                <?php foreach ($basis_options as $basis): ?>
                                                    <option value="<?= htmlspecialchars($basis) ?>"
                                                        <?= (($tpl['default_due_basis'] ?? '') === $basis) ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($basis) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>

                                            <div class="card-actions" style="flex-wrap:wrap; gap:.5rem;">
                                                <button type="submit" name="action" value="update_template"
                                                    class="btn-secondary btn-edit">Save</button>

                                                <?php if ((int) ($tpl['is_active'] ?? 0) === 1): ?>
                                                    <button type="submit" name="action" value="deactivate_template"
                                                        class="btn-primary btn-danger">Deactivate</button>
                                                <?php else: ?>
                                                    <button type="submit" name="action" value="activate_template"
                                                        class="btn-primary btn-view">Activate</button>
                                                <?php endif; ?>
                                            </div>
                                        </form>
                                    </footer>
                                </article>
                            <?php endforeach; ?>

                            <article class="event-card">
                                <div class="event-card-body">
                                    <h3 class="event-title">Add Requirement Template</h3>

                                    <form action="admin_update_configuration.php" method="POST"
                                        style="display:grid; gap:.75rem;">
                                        <input type="hidden" name="config_type" value="template">

                                        <input type="text" name="req_name" class="search-input"
                                            placeholder="Requirement name" required>
                                        <textarea name="req_desc" class="search-input" rows="3"
                                            placeholder="Description"></textarea>
                                        <input type="text" name="template_url" class="search-input"
                                            placeholder="Template URL">
                                        <input type="number" name="default_due_offset_days" min="0" value="7"
                                            class="search-input">

                                        <select name="default_due_basis" class="search-input">
                                            <?php foreach ($basis_options as $basis): ?>
                                                <option value="<?= htmlspecialchars($basis) ?>">
                                                    <?= htmlspecialchars($basis) ?></option>
                                            <?php endforeach; ?>
                                        </select>

                                        <button type="submit" name="action" value="add_template" class="btn-primary">Add
                                            Template</button>
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
</body>

</html>