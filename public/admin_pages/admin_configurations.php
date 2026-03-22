<?php
/**
 * Admin Configuration Management Page
 * 
 * This page allows administrators to manage all system configuration options:
 * - Background types (participation, service, student, etc.)
 * - Organizations/bodies (departments, clubs, organizations)
 * - Activity types (on-campus, off-campus, etc.)
 * - Series options (recurring event types)
 * - Requirement templates (deliverables needed for events)
 * - Requirements mapping (which requirements apply to which background/activity combinations)
 * 
 * Key Features:
 * - Expandable/collapsible sections for each config type
 * - Add, edit, delete, and toggle active status for all config items
 * - Multi-level requirements mapping (background → activity → requirements)
 * - CSRF token protection on all forms
 * - Flash message display for success/error feedback
 * - Confirmation dialogs for destructive actions
 * - Summary cards showing totals for each config type
 * 
 * Access Control:
 * - Admin-only page (requireAdmin() gate prevents non-admin access)
 * - Requires valid session
 * - All modifications sent to admin_update_configuration.php handler
 * 
 * Data Flow:
 * 1. Load all configuration data from database
 * 2. Calculate summary statistics
 * 3. Group requirements map for hierarchical display
 * 4. Render form sections for each config type
 * 5. Forms POST to admin_update_configuration.php with action parameter
 */

// ========== SESSION AND SECURITY INITIALIZATION ==========
// Start session to access user info and CSRF tokens
session_start();

// Load database connection and configuration files
require_once __DIR__ . '/../../app/database.php';
// Load security header middleware
require_once APP_PATH . "security_headers.php";

// Send security headers (CSP, etc.) to prevent XSS and other attacks
send_security_headers();

// ========== ACCESS CONTROL ==========
// Gate: Only allow logged-in admin users
// Non-admin users are redirected to appropriate page
requireAdmin();

/* ========================================
   CSRF TOKEN MANAGEMENT SECTION
   ======================================== */

/**
 * CSRF (Cross-Site Request Forgery) Protection
 * 
 * Purpose:
 * - Prevent attackers from making modifications using the admin's session
 * - Ensures form submissions come from this page, not external sites
 * 
 * Implementation:
 * - Generate a random token on first page load
 * - Store token in session (server-side)
 * - Include token as hidden form field on this page
 * - Verify token matches session value when form is submitted
 * 
 * Token Generation:
 * - bin2hex(random_bytes(32)): Creates 64-character hex string
 * - random_bytes(32): Cryptographically secure random values
 * - Unique per session for this user
 */
if (empty($_SESSION["csrf_token"])) {
    // Generate new CSRF token if none exists
    // E.g., "5a3c9d2e7f1b4a6c9e2d7f1b4a6c9e2d7f1b4a6c9e2d7f1b4a6c9e2d7f1b4a"
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}
// Store token for use in forms
$csrf_token = $_SESSION["csrf_token"];

/* ========================================
   FLASH MESSAGE HANDLING SECTION
   ======================================== */

/**
 * Flash Messages for User Feedback
 * 
 * Purpose:
 * - Show success/error messages after form submission
 * - Messages are stored in session and displayed once
 * 
 * Implementation:
 * - Get messages from $_SESSION (set by admin_update_configuration.php)
 * - Display them if present
 * - Immediately unset them from session
 * - Prevents messages from showing on subsequent page views
 * 
 * Message Sources:
 * - Success: Config item added, deleted, updated
 * - Error: Validation failure, database error, permission denied
 * 
 * Used in: HTML section with class "alert" for styling
 */
$success = $_SESSION["success"] ?? "";  // Empty string if no success message
$error = $_SESSION["error"] ?? "";      // Empty string if no error message
// Remove messages from session so they don't appear again on refresh
unset($_SESSION["success"], $_SESSION["error"]);

/* ========================================
   HELPER FUNCTIONS SECTION
   ======================================== */

/**
 * Normalizes the is_active database flag to CSS class name
 * 
 * Database stores: 1 (active) or 0 (inactive)
 * HTML needs: CSS class for styling (.active vs .inactive)
 * 
 * @param mixed $is_active - The is_active value from database (1 or 0)
 * @returns string - CSS class name: "active" or "inactive"
 * 
 * Implementation:
 * - Cast to int to handle various types (string "1", int 1, null)
 * - Strict comparison === 1 ensures only explicit 1 maps to 'active'
 * - All other values map to 'inactive' (0, null, "0", false, etc.)
 * 
 * Usage:
 * <span class="status-badge <?= normalizeActiveClass($item['is_active']) ?>">
 * 
 * Note: Function name is mis-used here; should return "active"/"inactive"
 * but is named "normalizeActiveClass" suggesting it returns class names
 */
function normalizeActiveClass($is_active): string
{
    // Strictly check if value equals 1 (active)
    return ((int) $is_active === 1) ? 'active' : 'inactive';
}

/* ========================================
   CONFIGURATION DATA LOADING SECTION
   ======================================== */

/**
 * Load Background Options
 * 
 * Backgrounds are the primary categories for events:
 * - Participation: Student organization/club activities
 * - Service: Community service events
 * - Student Development: Professional development
 * - etc.
 * 
 * Sorting Logic:
 * - is_active DESC: Active items appear first (more important)
 * - sort_order ASC: Custom sort within active/inactive groups
 * - background_name ASC: Alphabetical as final tiebreaker
 * 
 * Used by: Event creation form, filtration, requirements mapping
 */
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

/**
 * Load Organization/Body Options
 * 
 * Organizations represent departments, clubs, or bodies that run events:
 * - Finance Club
 * - Ethnic Studies Department
 * - Student Senate
 * - etc.
 * 
 * Used by: Event creation form (multi-select organizing bodies)
 * 
 * Sorting: Active first, then custom order, then alphabetical
 */
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

/**
 * Load Activity Type Options
 * 
 * Activity types describe how events are conducted:
 * - On-campus
 * - Off-campus
 * - Virtual
 * - Hybrid
 * - etc.
 * 
 * Impact:
 * - Determines if event shows off-campus fields in form
 * - Determines if event shows visitor fields in form
 * - Links to requirements in mapping table
 * 
 * Sorting: Active first, then custom order, then alphabetical
 */
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

/**
 * Load Series Options
 * 
 * Series represent recurring or grouped events:
 * - Monthly Meetings
 * - Weekly Workshops
 * - Annual Conference
 * - etc.
 * 
 * Note: Series selection only appears if background is "Participation"
 * Allows events to be grouped together chronologically
 * 
 * Sorting: Active first, then custom order, then alphabetical
 */
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

/**
 * Load Requirement Templates
 * 
 * Requirement templates define deliverables needed for events:
 * - Narrative Report (reflection on event)
 * - Photo Documentation
 * - List of Attendees
 * - etc.
 * 
 * Each template has:
 * - req_name: Display name
 * - req_desc: Description of requirement
 * - template_url: Link to submission or guidelines
 * - default_due_basis: When deadline is calculated (before_start, after_end, etc.)
 * - default_due_offset_days: How many days before/after basis date
 * 
 * Sorting: Active templates first, then alphabetically
 */
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

/**
 * Load Requirements Mapping
 * 
 * Mapping defines which requirements apply to which event types
 * 
 * Link: background → activity_type → requirement
 * Example: "Participation" + "On-Campus" → "Narrative Report"
 * 
 * Structure:
 * - config_requirements_map (crm): The mapping entries
 * - config_background_options (cbo): To get background names
 * - config_activity_types (cat): To get activity type names
 * - requirement_templates (rt): To get requirement names
 * 
 * INNER JOINs ensure only valid mappings are shown
 * (all referenced records must exist)
 * 
 * Sorting: By background name, then activity type, then requirement name
 * Provides hierarchical, organized display
 */
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

/* ========================================
   SUMMARY STATISTICS SECTION
   ======================================== */

/**
 * Calculate summary counts for display on page
 * 
 * Shows admin at-a-glance how many of each config type exist
 * Displayed as stat cards at top of page
 * 
 * Each count is simple array length (all items, active and inactive)
 */
$summary = [
    'background_total' => count($background_options),
    'org_total' => count($org_options),
    'activity_total' => count($activity_types),
    'series_total' => count($series_options),
    'template_total' => count($requirement_templates),
    'mapping_total' => count($requirements_map_rows)
];

/* ========================================
   REQUIREMENTS MAP GROUPING SECTION
   ======================================== */

/**
 * Group requirements map for hierarchical display
 * 
 * Raw data is flat: all mappings in a list
 * Need 2-level grouping for readable display:
 * - Level 1: Background type
 * - Level 2: Activity type under each background
 * - Items: Requirements under each activity type
 * 
 * Result Structure:
 * $grouped_map = [
 *   'Participation' => [
 *     'On-Campus' => [
 *       ['config_map_id' => 1, 'req_name' => 'Attendance List', ...],
 *       ['config_map_id' => 2, 'req_name' => 'Narrative Report', ...],
 *     ],
 *     'Off-Campus' => [
 *       ['config_map_id' => 3, 'req_name' => 'Narrative Report', ...],
 *     ],
 *   ],
 *   'Service' => [
 *     ...
 *   ]
 * ]
 * 
 * Algorithm:
 * 1. Iterate through flat requirements_map_rows
 * 2. For each row, create nested array structure if not exists
 * 3. Add row to innermost array
 * 4. Result is 3 levels: background → activity → requirements list
 */
$grouped_map = [];
foreach ($requirements_map_rows as $row) {
    // Extract grouping keys from row
    $background = $row['background_name'];
    $activity = $row['activity_type_name'];

    // Create background group if doesn't exist
    if (!isset($grouped_map[$background])) {
        $grouped_map[$background] = [];
    }

    // Create activity subgroup under background if doesn't exist
    if (!isset($grouped_map[$background][$activity])) {
        $grouped_map[$background][$activity] = [];
    }

    // Add this requirement mapping to the activity subgroup
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
    <link rel="stylesheet" href="<?= PUBLIC_URL ?>assets/styles/layout.css" />
    <link rel="stylesheet" href="<?= PUBLIC_URL ?>assets/styles/home_styles.css" />
    <link rel="stylesheet" href="<?= PUBLIC_URL ?>assets/styles/admin_configurations.css" />
</head>

<body>
    <div class="app">
        <div class="sidebar-overlay" id="sidebarOverlay" hidden></div>

        <?php include PUBLIC_PATH . 'assets/includes/admin_nav.php'; ?>

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

            <?php include PUBLIC_PATH . 'assets/includes/footer.php'; ?>
        </main>
    </div>

    <script src="<?= APP_URL ?>script/layout.js?v=1"></script>
    <script>
        /**
         * Admin Configuration Page JavaScript
         * 
         * Handles:
         * - Section expand/collapse functionality
         * - Confirmation dialogs for destructive actions
         * - Initial section state setup
         * 
         * Functions are called from HTML onclick handlers and event listeners
         */

        /* ========================================
           SECTION TOGGLE FUNCTIONALITY
           ======================================== */

        /**
         * Toggles section visibility (expand/collapse)
         * 
         * Called when user clicks a section header button
         * Toggles CSS class to show/hide the section body
         * 
         * @param {HTMLElement} headerButton - The clicked section header button
         * 
         * Implementation:
         * - Find parent .config-section element
         * - Toggle the "collapsed" class on it
         * - CSS handles showing/hiding based on class
         * - Uses closest() to traverse up DOM tree
         * 
         * HTML Usage:
         * <button onclick="toggleSection(this)">Section Title</button>
         */
        function toggleSection(headerButton) {
            // Find the .config-section that contains this button
            // closest() searches up the DOM tree starting from element
            const section = headerButton.closest('.config-section');
            
            // Toggle the "collapsed" class
            // If present, removes it (expands section)
            // If absent, adds it (collapses section)
            section.classList.toggle('collapsed');
        }

        /* ========================================
           INITIAL PAGE LOAD SETUP
           ======================================== */

        /**
         * Initialize page state on DOM load
         * 
         * Purpose:
         * - Set up sections to be collapsed by default (except first one)
         * - Improves UX by showing only first section open
         * - User can expand additional sections as needed
         * - Prevents overwhelming the page with all sections expanded
         * 
         * Process:
         * 1. Wait for DOM to fully load
         * 2. Get all config-section elements
         * 3. Skip first section (keep it open)
         * 4. Add "collapsed" class to sections 1+
         * 5. CSS display: none on collapsed sections
         */
        document.addEventListener('DOMContentLoaded', function () {
            // Get all config section elements on the page
            const sections = document.querySelectorAll('.config-section');
            
            // Iterate with index to skip first section
            sections.forEach((section, index) => {
                // Skip index 0 (first section stays open)
                if (index > 0) {
                    // Add collapsed class to collapse this section
                    // CSS will hide section.section-body when parent has collapsed class
                    section.classList.add('collapsed');
                }
            });
        });

        /* ========================================
           FORM SUBMISSION CONFIRMATION
           ======================================== */

        /**
         * Confirm destructive actions before form submission
         * 
         * Purpose:
         * - Prevent accidental deletion or deactivation of config items
         * - Double-checks that user actually wants to perform action
         * - Uses browser confirm() dialog
         * 
         * Process:
         * 1. Attach listener to all form submit events
         * 2. Check which button was clicked (e.submitter)
         * 3. If button is a destructive action, show confirm dialog
         * 4. Use button's text to personalize message
         * 5. If user declines, preventDefault() stops form submission
         * 6. If user confirms, form submits normally to backend
         * 
         * Rationale:
         * - Catches accidental clicks on delete/deactivate buttons
         * - Prevents data loss from double-clicks or muscle memory
         * - Browser confirm() is standard UX for destructive actions
         * - Message includes the button label for clarity
         * 
         * Example Flow:
         * 1. User clicks "Delete Background" button
         * 2. JavaScript intercepts form submit
         * 3. Shows: "Are you sure you want to delete background this item?"
         * 4. If "OK": Form submits to admin_update_configuration.php
         * 5. If "Cancel": Submission blocked, page stays put
         */
        document.querySelectorAll('form').forEach(form => {
            // Add submit event listener to each form
            form.addEventListener('submit', function (e) {
                // e.submitter is the button that triggered form submission
                // May be null if form submitted programmatically
                const btn = e.submitter;
                if (!btn) return;  // Exit if no button info available

                /**
                 * List of destructive action values
                 * These are the value attributes of buttons that modify/delete data
                 * 
                 * Pattern:
                 * - "delete_*": Remove item from database
                 * - "deactivate_*": Mark item as inactive (soft delete)
                 * 
                 * Non-destructive actions (not in list):
                 * - "add_*": Create new item (usually desired, no confirmation needed)
                 * - "update_*": Modify item (could be destructive but often just updates)
                 * - "activate_*": Enable item (opposite of deactivate, desired)
                 */
                const destructiveActions = [
                    'delete_background', 'delete_org', 'delete_activity_type', 'delete_series',
                    'delete_mapping', 'deactivate_background', 'deactivate_org',
                    'deactivate_activity_type', 'deactivate_series', 'deactivate_template'
                ];

                // Check if the clicked button is a destructive action
                if (destructiveActions.includes(btn.value)) {
                    // Get the button label and convert to lowercase for message
                    // Example: "Delete Background" → "delete background"
                    const label = btn.textContent.trim();
                    
                    /**
                     * Show confirmation dialog
                     * 
                     * Message format:
                     * "Are you sure you want to [action] this item?"
                     * 
                     * Example confirmations:
                     * "Are you sure you want to delete background this item?"
                     * "Are you sure you want to deactivate org this item?"
                     * 
                     * confirm() returns:
                     * - true: User clicked OK
                     * - false: User clicked Cancel
                     */
                    if (!confirm(`Are you sure you want to ${label.toLowerCase()} this item?`)) {
                        // User declined confirmation
                        // Prevent form submission
                        e.preventDefault();
                    }
                    // If user confirmed, nothing happens here
                    // Form submission continues normally
                }
            });
        });
    </script>
</body>

</html>