<?php
/**
 * =====================================================
 * EVENT MANAGEMENT DETAIL PAGE (ADMIN)
 * =====================================================
 * 
 * Purpose:
 * Comprehensive admin interface for reviewing and managing a single event submission.
 * Displays all event details (dates, logistics, metrics, participants), requirement/document
 * review interface with approval/revision workflows, and admin decision controls.
 * 
 * Key Features:
 * 1. Event header with status badge, submitter info, and admin remarks
 * 2. Detail cards: Submission details, Schedule & Logistics, Metrics
 * 3. Requirement review section with upload status, file preview, and approval buttons
 * 4. Document management: View uploads, template previews, pre-event requirements
 * 5. Narrative report handling: Special case with custom review page link
 * 6. Progress tracker: Visual indicator of document completion (uploaded/total)
 * 7. Event decision controls: Approve, return for revision, mark complete
 * 8. Two-step approval: Pre-event docs + narrative report must both be approved before completion
 * 
 * Access Control:
 * Requires admin role (enforced by requireAdmin() function from database.php)
 * 
 * Database Information:
 * Uses MySQLi prepared statements via fetchOne() and fetchAll() helper functions
 * Two major queries: event data (12-table join) and requirements (with files & narratives)
 * 
 * URL Parameters:
 * - id: Event ID (required, integer, must be > 0)
 * 
 * Page Variables (Set Below):
 * - $event: Array of complete event data with related details
 * - $requirements: Array of requirement records (documents, narratives, reviews)
 * - $event_status: Current event status ('Pending Review', 'Needs Revision', 'Approved', 'Completed')
 * - $narrativeApproved: Boolean - whether narrative report is approved and uploaded
 * - $allPreEventReviewedOkay: Boolean - whether all pre-event requirements are uploaded+approved
 * - $progress_percentage: Percent of documents uploaded (0-100)
 * 
 * Workflow:
 * 1. Admin clicks "Manage" from admin_events.php
 * 2. Event details load with all related data
 * 3. Admin reviews requirements: uploads, approval status, remarks
 * 4. Admin can approve/reject individual requirements
 * 5. Admin leaves remarks at event level
 * 6. Once all requirements approved, admin can transition event status:
 *    - Pending Review → Approve (if all pre-docs approved)
 *    - → Approved (after approval, awaiting narrative)
 *    - → Completed (after narrative approved)
 * 7. Or can mark "Needs Revision" to send back to user
 */

session_start();
require_once __DIR__ . '/../../app/database.php';

requireAdmin();

// Extract admin user ID from session for audit logging (if implemented)
// Used to track which admin performed reviews
$admin_user_id = (int) $_SESSION["user_id"];

// Extract event ID from URL parameter (?id=123)
// Cast to int to prevent SQL injection, add null coalescing for safety
$event_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

// Validate event ID: must be positive integer (0 or negative indicates missing/invalid parameter)
// Shows error popup and redirects to home if invalid
// popup_error() terminates script execution with error display
if ($event_id <= 0) {
    popup_error("Invalid event.", PUBLIC_URL . 'index.php');
}

/* ================= HELPER FUNCTIONS ================= */
function normalizeStatusClass(string $status): string
{
    return strtolower(str_replace(' ', '-', trim($status)));
}

function formatDateTimeValue(?string $value): string
{
    if (empty($value)) {
        return 'N/A';
    }

    $ts = strtotime($value);
    return $ts ? date('F j, Y g:i A', $ts) : 'N/A';
}

/**
 * Build document preview URL based on upload or template source
 * 
 * Purpose:
 * Determine which URL to use for document preview modal (uploaded file or template).
 * Priority: Actual upload > Template URL > No preview available message
 * 
 * @param array $doc Document record from requirements query (contains file_path, template_url)
 * @return array Three-element array: [preview_url, no_template_message, has_upload_boolean]
 *               - preview_url: URL to display in iframe, empty if no preview
 *               - no_template_msg: Error message if no preview available
 *               - has_upload: Boolean indicating if this file was uploaded by user vs. template
 * 
 * Logic:
 * 1. If document has file_path → Use uploaded file (user-provided)
 *    - Prepends '../' to file_path (accounts for PHP being in admin_pages/ subdirectory)
 *    - Strips leading '/' from path to avoid double slashes
 * 2. Else if document has template_url → Use template (admin-provided blank)
 * 3. Else → No template available (user never provided file, no template created)
 * 
 * Usage:
 * Called when rendering document preview button to determine iframe source
 * 
 * Example Return:
 * [
 *   '../uploads/documents/2026-03-22-event-123-requirement-1.pdf',  // preview_url
 *   '',                                                               // no_template_msg (empty)
 *   true                                                              // has_upload (file was uploaded)
 * ]
 */
function buildPreviewUrl(array $doc): array
{
    $has_upload = !empty($doc['file_path']);
    $preview_url = '';
    $no_template_msg = '';

    if ($has_upload) {
        $preview_url = '../' . ltrim($doc['file_path'], '/');
    } elseif (!empty($doc['template_url'])) {
        $preview_url = $doc['template_url'];
    } else {
        $no_template_msg = 'No template available for this document.';
    }

    return [$preview_url, $no_template_msg, $has_upload];
}

/**
 * Parse metric string into structured data
 * 
 * Purpose:
 * Extract percentage and label from metric format strings.
 * Expected format: "99% Attendance" or "85% Completion"
 * Returns structured array or null if parsing fails (invalid format).
 * 
 * @param string|null $metric Raw metric string from database or null
 * @return array|null Associative array with keys:
 *                   - percent: Integer percentage (0-100)
 *                   - label: Human-readable metric name (e.g., "Attendance")
 *                   - normalized_label: Lowercase, whitespace-normalized version for comparison
 *                   Returns null if metric is empty or doesn't match expected format
 * 
 * Validation:
 * - Uses regex: /^(100|[1-9]?\d)\%\s+(.+)$/
 *   - ^(100|[1-9]?\d): Matches percentage 0-100 (prevents "101%")
 *   - \%: Literal percent sign
 *   - \s+: Whitespace separator (requires at least one space)
 *   - (.+)$: One or more characters for label
 * - Rejects if percentage is present but label is empty
 * 
 * Normalized Label:
 * - Converts to lowercase for case-insensitive comparison
 * - Replaces multiple spaces with single space using preg_replace
 * - Enables matching "Attendance" with "ATTENDANCE" or "attendance"
 * 
 * Example Returns:
 * parseMetric("90% Attendance") → 
 *   ['percent' => 90, 'label' => 'Attendance', 'normalized_label' => 'attendance']
 * parseMetric("Invalid") → null
 * parseMetric(null) → null
 * parseMetric("101% Impossible") → null (percent > 100)
 */
function parseMetric(?string $metric): ?array
{
    if (empty($metric)) {
        return null;
    }

    $metric = trim($metric);

    if (!preg_match('/^(100|[1-9]?\d)\%\s+(.+)$/', $metric, $matches)) {
        return null;
    }

    $percent = (int) $matches[1];
    $label = trim($matches[2]);

    if ($label === '') {
        return null;
    }

    return [
        'percent' => $percent,
        'label' => $label,
        'normalized_label' => strtolower(preg_replace('/\s+/', ' ', $label))
    ];
}

/**
 * Determine metric achievement status by comparing target vs. actual
 * 
 * Purpose:
 * Evaluate whether event achieved its target metric.
 * Compares target metric (90% Attendance) with actual metric (92% Attendance).
 * Returns status label and CSS class for display styling.
 * 
 * @param string|null $target_metric Target metric string (e.g., "90% Attendance")
 * @param string|null $actual_metric Actual metric achieved (e.g., "92% Attendance")
 * @return array Always returns array with keys:
 *               - label: Human-readable status message
 *               - class: CSS class for styling (metric-neutral, metric-mismatch, metric-achieved, etc)
 * 
 * Achievement Logic:
 * 1. If either metric null/empty → "Not Yet Evaluated" (class: metric-neutral)
 *    Indicates metrics not set up for this event
 * 
 * 2. If metrics set but labels don't match → "Metric Type Mismatch" (class: metric-mismatch)
 *    Example: Target "90% Attendance" vs Actual "85% Completion" (different metrics)
 *    Indicates inconsistency in metric configuration
 * 
 * 3. If actual_percent >= target_percent → "Target Achieved" (class: metric-achieved)
 *    Example: Target "90% Attendance", Actual "92% Attendance" (92 >= 90 ✓)
 *    Green status showing success
 * 
 * 4. Otherwise → "Target Not Achieved" (class: metric-not-achieved)
 *    Example: Target "90% Attendance", Actual "85% Attendance" (85 < 90 ✗)
 *    Red/warning status showing shortfall
 * 
 * CSS Classes used for styling:
 * - metric-neutral: Gray background (not set up)
 * - metric-mismatch: Orange background (configuration error)
 * - metric-achieved: Green background (success)
 * - metric-not-achieved: Red background (failure)
 */
function getMetricAchievementStatus(?string $target_metric, ?string $actual_metric): array
{
    $target = parseMetric($target_metric);
    $actual = parseMetric($actual_metric);

    if ($target === null || $actual === null) {
        return [
            'label' => 'Not Yet Evaluated',
            'class' => 'metric-neutral'
        ];
    }

    if ($target['normalized_label'] !== $actual['normalized_label']) {
        return [
            'label' => 'Metric Type Mismatch',
            'class' => 'metric-mismatch'
        ];
    }

    if ($actual['percent'] >= $target['percent']) {
        return [
            'label' => 'Target Achieved',
            'class' => 'metric-achieved'
        ];
    }

    return [
        'label' => 'Target Not Achieved',
        'class' => 'metric-not-achieved'
    ];
}

/**
 * Check if admin can review (approve/reject) this requirement
 * 
 * Purpose:
 * Determine if "Approve" and "Needs Revision" buttons should be shown for a requirement.
 * Prevents reviewing non-uploaded documents or documents in non-reviewable event states.
 * 
 * @param array $doc Document record (must contain 'submission_status' key)
 * @param string $event_status Current event status (e.g., 'Pending Review', 'Completed')
 * @return bool True if requirement can be reviewed, false otherwise
 * 
 * Conditions (ALL must be true):
 * 1. Requirement submission_status = 'Uploaded' (user must have submitted file)
 * 2. Event status NOT in ['Completed', 'Draft']
 *    - Completed events: All requirements already reviewed, no changes allowed
 *    - Draft events: Not yet submitted, not reviewable
 * 
 * Reasoning:
 * - Can only review uploaded documents (empty submissions have no file to evaluate)
 * - Can only review in states where event management is active (not completed/archived)
 * 
 * Returns:
 * True: Show approve/review buttons for this requirement
 * False: Hide buttons, show read-only view
 */
function canReviewRequirement(array $doc, string $event_status): bool
{
    if (($doc['submission_status'] ?? '') !== 'Uploaded') {
        return false;
    }

    if (in_array($event_status, ['Completed', 'Draft'], true)) {
        return false;
    }

    return true;
}

/**
 * Check if ongoing event can be approved by admin
 * 
 * Purpose:
 * Determine if "Approve Event" button should be enabled in decision form.
 * Only available during specific stages of review workflow.
 * 
 * @param string $event_status Current event status from database
 * @return bool True if event can be moved to "Approved" status, false otherwise
 * 
 * Valid Approval States:
 * - 'Pending Review': Initial submission state → can approve
 * - 'Needs Revision': Resubmitted after feedback → can approve again
 * 
 * Invalid States (no approval button):
 * - 'Approved': Already approved, awaiting narrative feedback
 * - 'Completed': Workflow finished, no further state changes
 * 
 * Workflow Progression:
 * Pending Review → Approve → Approved → (after narrative) → Complete
 * Needs Revision ↗ (from "Approve" button)
 */
function canApproveEvent(string $event_status): bool
{
    return in_array($event_status, ['Pending Review', 'Needs Revision'], true);
}

/**
 * Check if event can be marked as "Needs Revision"
 * 
 * Purpose:
 * Determine if "Mark Needs Revision" button should be available.
 * Multiple states allow returning event to user for fixes/changes.
 * 
 * @param string $event_status Current event status
 * @return bool True if event can be marked as 'Needs Revision', false otherwise
 * 
 * Valid States for Return:
 * - 'Pending Review': Initial review, needed changes discovered
 * - 'Approved': After approval review, need user to fix something
 * - 'Needs Revision': Already in revision state, can mark again for more changes
 * 
 * Invalid States (no button):
 * - 'Completed': Event finished, no more changes allowed
 * - 'Draft': Not yet submitted by user
 * 
 * Business Logic:
 * Admin can request revisions at any point except completed/draft states.
 * This allows iterative feedback: Submit → Review → Revision → Resubmit → Approve
 */
function canReturnEventForRevision(string $event_status): bool
{
    return in_array($event_status, ['Pending Review', 'Approved', 'Needs Revision'], true);
}

/**
 * Check if event can be marked as "Completed"
 * 
 * Purpose:
 * Determine if "Mark as Completed" button should be available.
 * Only available after ALL requirements approved (including narrative).
 * 
 * @param string $event_status Current event status
 * @param bool $narrativeApproved Whether narrative report is approved+uploaded
 * @return bool True if event can be marked 'Completed', false otherwise
 * 
 * Both Conditions Required:
 * 1. event_status === 'Approved' (pre-event requirements already approved)
 * 2. $narrativeApproved === true (narrative report approved and submitted)
 * 
 * Workflow:
 * 1. Admin approves pre-event requirements (docs, logistics, etc) → Status: Approved
 * 2. User submits narrative report after event occurs
 * 3. Admin reviews and approves narrative (done on separate page: admin_review_narrative.php)
 * 4. When narrative approved, $narrativeApproved becomes true
 * 5. NOW "Complete" button becomes available
 * 6. Admin clicks "Complete" → Status: Completed (event closed)
 * 
 * Reasoning:
 * Ensures full event lifecycle completion before marking done:
 * - Pre-event planning approved
 * - Event actually occurred (proven by narrative/documentation)
 * - Outcomes documented and reviewed
 */
function canCompleteEvent(string $event_status, bool $narrativeApproved): bool
{
    return $event_status === 'Approved' && $narrativeApproved;
}

/* ================= FETCH EVENT DATA ================= */

/**
 * Database Query: Fetch Complete Event Data
 * 
 * Purpose:
 * Retrieve all event information needed for admin management page.
 * Single query joins 12 tables to avoid N+1 select problem.
 * Fetches event core data, user info, event type/background/series, dates/logistics, and metrics.
 * 
 * Query Structure:
 * - INNER JOIN users: Get submitter name/email/organization (mandatory, all events have user)
 * - LEFT JOINs for optional data: event_type, backgrounds, activity types, dates, participants,
 *   location, logistics, metrics (events may not have all of these set)
 * 
 * Key Filters:
 * - WHERE e.event_id = ?: Match specific event being managed
 * - AND e.archived_at IS NULL: Exclude deleted/archived events
 * - LIMIT 1: Optimization, only one event per ID
 * 
 * SELECT Columns (44 total):
 * Core event: event_id, user_id, event_name, event_status, docs_total, docs_uploaded,
 *            organizing_body, nature, admin_remarks, is_system_event, created_at, updated_at, archived_at
 * User: user_name, user_email, org_body
 * Type info: background_id (FK), activity_type_id (FK), series_option_id (FK)
 * Config lookups: background_name, activity_type_name, series_name
 * Dates: start_datetime, end_datetime
 * Participants: participants, participant_range, has_visitors
 * Location: venue_platform, distance
 * Logistics: extraneous, collect_payments, overnight
 * Metrics: target_metric, actual_metric (achievement evaluation)
 * 
 * Return:
 * Single row associative array with all event details, or empty if event not found
 */
$fetchEventSql = "
    SELECT
        e.event_id,
        e.user_id,
        e.organizing_body,
        e.nature,
        e.event_name,
        e.event_status,
        e.admin_remarks,
        e.docs_total,
        e.docs_uploaded,
        e.is_system_event,
        e.created_at,
        e.updated_at,
        e.archived_at,

        u.user_name,
        u.user_email,
        u.org_body,

        et.background_id,
        et.activity_type_id,
        et.series_option_id,

        cbo.background_name AS background,
        cat.activity_type_name AS activity_type,
        cso.series_name AS series,

        ed.start_datetime,
        ed.end_datetime,

        ep.participants,
        ep.participant_range,
        ep.has_visitors,

        el.venue_platform,
        el.distance,

        elg.extraneous,
        elg.collect_payments,
        elg.overnight,

        em.target_metric,
        em.actual_metric

    FROM events e
    INNER JOIN users u
        ON e.user_id = u.user_id
    LEFT JOIN event_type et
        ON e.event_id = et.event_id
    LEFT JOIN config_background_options cbo
        ON et.background_id = cbo.background_id
    LEFT JOIN config_activity_types cat
        ON et.activity_type_id = cat.activity_type_id
    LEFT JOIN config_series_options cso
        ON et.series_option_id = cso.series_option_id
    LEFT JOIN event_dates ed
        ON e.event_id = ed.event_id
    LEFT JOIN event_participants ep
        ON e.event_id = ep.event_id
    LEFT JOIN event_location el
        ON e.event_id = el.event_id
    LEFT JOIN event_logistics elg
        ON e.event_id = elg.event_id
    LEFT JOIN event_metrics em
        ON e.event_id = em.event_id
    WHERE e.event_id = ?
      AND e.archived_at IS NULL
    LIMIT 1
";

// Execute event query with provided event_id parameter
// Returns single associative array with all event data
$event = fetchOne($conn, $fetchEventSql, "i", [$event_id]);

// Check if event was found in database
// If not found or already archived, show error popup and terminate
// popup_error() displays message and exits, optionally redirecting user
if (!$event) {
    popup_error("Event not found.");
}

/* ================= FETCH REQUIREMENTS ================= */

/**
 * Database Query: Fetch All Requirements for Event
 * 
 * Purpose:
 * Retrieve all requirements/documents for event with uploaded files and review status.
 * Joins requirement templates (names, descriptions, templates) with submission status,
 * file uploads, and narrative report content if applicable.
 * 
 * Query Structure:
 * - INNER JOIN requirement_templates: Get requirement metadata (name, description, template URL)
 * - LEFT JOIN requirement_files: Get uploaded file if exists (is_current = 1 ensures latest version)
 * - LEFT JOIN narrative_report_details: Get narrative content if this is narrative requirement
 * 
 * Key Logic:
 * - is_current = 1 filter: When multiple file versions exist, gets only the current one
 * - LEFT JOINs allow requirements without uploads to still show (as "Pending" status)
 * 
 * Sorting Strategy:
 * 1. CASE WHEN deadline IS NULL THEN 1 ELSE 0 END: Push NO-deadline items down
 *    Requirements with deadlines sort to top (users should prioritize time-limited items)
 * 2. deadline ASC: Sort by deadline ascending (nearest deadline first)
 * 3. req_name ASC: Alphabetical tiebreaker for same deadline/null
 * 
 * Result: Requirements ordered by urgency (deadline proximity) and name
 * 
 * SELECT Columns (20 total):
 * Requirement core: event_req_id, submission_status, review_status, deadline, remarks
 * Review tracking: reviewed_at (when admin approved), reviewer_id (which admin)
 * Template data: req_name (e.g., "Research Paper"), req_desc, template_url
 * File data: req_file_id, file_path, original_file_name, file_type, file_size, uploaded_at
 * Narrative data: narrative_report_id, narrative (essay), video_documentation_link, submitted_at
 * 
 * Return:
 * Array of associative arrays, one per requirement, with combined data
 * Example: First row has req_name='Research Paper', file_path='uploads/...', etc.
 */
$fetchRequirementsSql = "
    SELECT
        er.event_req_id,
        er.submission_status,
        er.review_status,
        er.deadline,
        er.reviewed_at,
        er.reviewer_id,
        er.remarks,
        er.created_at,
        er.updated_at,

        rt.req_name,
        rt.req_desc,
        rt.template_url,

        rf.req_file_id,
        rf.file_path,
        rf.original_file_name,
        rf.file_type,
        rf.file_size,
        rf.uploaded_at,

        nrd.narrative_report_id,
        nrd.narrative,
        nrd.video_documentation_link,
        nrd.submitted_at

    FROM event_requirements er
    INNER JOIN requirement_templates rt
        ON er.req_template_id = rt.req_template_id
    LEFT JOIN requirement_files rf
        ON er.event_req_id = rf.event_req_id
       AND rf.is_current = 1
    LEFT JOIN narrative_report_details nrd
        ON er.event_req_id = nrd.event_req_id
    WHERE er.event_id = ?
    ORDER BY
        CASE WHEN er.deadline IS NULL THEN 1 ELSE 0 END,
        er.deadline ASC,
        rt.req_name ASC
";

// Execute requirements query with provided event_id parameter
// Returns array of requirement records sorted by deadline urgency
$requirements = fetchAll($conn, $fetchRequirementsSql, "i", [$event_id]);

/* ================= EVENT LOGIC FLAGS ================= */

/**
 * Build logic flags for page rendering and button visibility
 * 
 * Purpose:
 * Calculate progress metrics, approval status, and determine which action buttons show.
 * Sets variables used throughout HTML template for conditional rendering.
 */

// Current event status extracted from database
// Used throughout page to determine visibility of action buttons/forms
$event_status = $event['event_status'] ?? 'Pending Review';

// Document progress: Total and uploaded counts from event record
// These are maintained by system when users upload/delete requirements
$total_docs = (int) ($event['docs_total'] ?? 0);
$uploaded_docs = (int) ($event['docs_uploaded'] ?? 0);
$pending_docs = max(0, $total_docs - $uploaded_docs);  // Never negative due to max()

// Calculate progress percentage for visual indicator
// If no documents required ($total_docs = 0), shows 0% not 100% (not applicable state)
$progress_percentage = $total_docs > 0 ? round(($uploaded_docs / $total_docs) * 100) : 0;

// Generate HSL color for progress ring based on percentage
// Maps percentage (0-100) to hue (0-120 degrees): red → yellow → green
// Formula: (percentage / 100) * 120 = hue value
// Saturation and lightness fixed for consistent appearance
$pct = max(0, min(100, (int) $progress_percentage));  // Clamp 0-100
$hue = ($pct / 100) * 120;  // 0% = 0 (red), 100% = 120 (green)
$progress_color = "hsl($hue, 70%, 45%)";  // CSS HSL color for progress ring

// Parse organizing_body JSON field
// organizing_body stored as JSON array (e.g., ["Club A", "Club B", "Club C"])
// Decode and join with commas for display
// If not JSON, use as-is (fallback for corrupted/legacy data)
$organizing_body_display = $event['organizing_body'] ?? '';
$decoded_orgs = json_decode($organizing_body_display, true);
if (is_array($decoded_orgs)) {
    $organizing_body_display = implode(", ", $decoded_orgs);
}

// Evaluate metric achievement (target vs. actual)
// Uses helper function to compare target/actual metrics
// Returns label and CSS class for styling
$metric_status = getMetricAchievementStatus(
    $event['target_metric'] ?? null,
    $event['actual_metric'] ?? null
);

// Loop through requirements to determine approval status
// Sets two boolean flags used in decision control section
$narrativeApproved = false;  // Will be true if Narrative Report is approved
$allPreEventReviewedOkay = true;  // Will be false if ANY pre-event requirement is not approved

foreach ($requirements as $doc) {
    // Check if this requirement is the Narrative Report (special case)
    $isNarrative = (($doc['req_name'] ?? '') === 'Narrative Report');

    if ($isNarrative) {
        // Narrative is approved when: status='Approved' AND status='Uploaded'
        if (($doc['review_status'] ?? '') === 'Approved' && ($doc['submission_status'] ?? '') === 'Uploaded') {
            $narrativeApproved = true;
        }
        // Skip further checks for narrative (doesn't affect pre-event readiness)
        continue;
    }

    // Check pre-event requirements: must be both uploaded AND approved
    // If ANY pre-event requirement is not fully approved, flag fails
    if (($doc['submission_status'] ?? '') !== 'Uploaded' || ($doc['review_status'] ?? '') !== 'Approved') {
        $allPreEventReviewedOkay = false;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Manage Event - HAUCREDIT</title>
    <link rel="stylesheet" href="<?= PUBLIC_URL ?>assets/styles/layout.css" />
    <link rel="stylesheet" href="<?= PUBLIC_URL ?>assets/styles/admin_manage_event.css" />
</head>

<body>
    <div class="app">
        <div class="sidebar-overlay" id="sidebarOverlay" hidden></div>

        <?php include PUBLIC_PATH . 'assets/includes/admin_nav.php'; ?>

        <main class="main">
            <header class="topbar">
                <button class="hamburger" id="menuBtn" type="button" aria-label="Open menu">☰</button>

                <div class="title-wrap">
                    <h1><?= htmlspecialchars($event['event_name']) ?></h1>
                    <p>Admin Event Management</p>
                </div>

                <div class="home-top-actions">
                    <a href="admin_events.php" class="btn-secondary">Back to Events</a>
                </div>
            </header>

            <section class="content admin-manage-event-page">
                <section class="event-hero-card status-<?= htmlspecialchars(normalizeStatusClass($event_status)) ?>">
                    <div class="event-hero-main">
                        <div class="event-hero-status">
                            <span class="hero-status-badge status-<?= htmlspecialchars(normalizeStatusClass($event_status)) ?>">
                                <?= htmlspecialchars($event_status) ?>
                            </span>
                        </div>

                        <h2><?= htmlspecialchars($event['event_name']) ?></h2>

                        <p class="event-hero-subtitle">
                            Submitted by
                            <strong><?= htmlspecialchars($event['user_name'] ?? 'Unknown User') ?></strong>
                            •
                            <?= htmlspecialchars($event['org_body'] ?? 'No organization') ?>
                        </p>

                        <?php if (!empty($event['admin_remarks'])): ?>
                            <div class="hero-remarks">
                                <strong>Current Admin Remarks</strong>
                                <p><?= nl2br(htmlspecialchars($event['admin_remarks'])) ?></p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="event-hero-meta">
                        <div class="hero-meta-item">
                            <span class="hero-meta-label">Created</span>
                            <span class="hero-meta-value"><?= formatDateTimeValue($event['created_at'] ?? null) ?></span>
                        </div>
                        <div class="hero-meta-item">
                            <span class="hero-meta-label">Updated</span>
                            <span class="hero-meta-value"><?= formatDateTimeValue($event['updated_at'] ?? null) ?></span>
                        </div>
                        <div class="hero-meta-item">
                            <span class="hero-meta-label">Activity Type</span>
                            <span class="hero-meta-value"><?= htmlspecialchars($event['activity_type'] ?? 'N/A') ?></span>
                        </div>
                    </div>
                </section>

                <div class="manage-layout">
                    <div class="manage-main">
                        <section class="detail-card">
                            <div class="card-header">
                                <h2><i class="fa-solid fa-user"></i> Submission Details</h2>
                                <span class="badge badge-primary"><?= htmlspecialchars($event['activity_type'] ?? 'N/A') ?></span>
                            </div>

                            <div class="card-body">
                                <div class="detail-grid">
                                    <div class="detail-item">
                                        <label>Submitted By</label>
                                        <p><?= htmlspecialchars($event['user_name'] ?? 'N/A') ?></p>
                                    </div>
                                    <div class="detail-item">
                                        <label>Email</label>
                                        <p><?= htmlspecialchars($event['user_email'] ?? 'N/A') ?></p>
                                    </div>
                                    <div class="detail-item">
                                        <label>Organization</label>
                                        <p><?= htmlspecialchars($event['org_body'] ?? 'N/A') ?></p>
                                    </div>
                                    <div class="detail-item">
                                        <label>Organizing Body</label>
                                        <p><?= htmlspecialchars($organizing_body_display) ?></p>
                                    </div>
                                    <div class="detail-item">
                                        <label>Background</label>
                                        <p><?= htmlspecialchars($event['background'] ?? 'N/A') ?></p>
                                    </div>
                                    <div class="detail-item">
                                        <label>Nature</label>
                                        <p><?= htmlspecialchars($event['nature'] ?? 'N/A') ?></p>
                                    </div>
                                    <?php if (!empty($event['series'])): ?>
                                        <div class="detail-item">
                                            <label>Series</label>
                                            <p><?= htmlspecialchars($event['series']) ?></p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </section>

                        <section class="detail-card">
                            <div class="card-header">
                                <h2><i class="fa-solid fa-calendar-days"></i> Schedule & Logistics</h2>
                            </div>

                            <div class="card-body">
                                <div class="detail-grid">
                                    <div class="detail-item">
                                        <label>Start Date & Time</label>
                                        <p><?= formatDateTimeValue($event['start_datetime'] ?? null) ?></p>
                                    </div>
                                    <div class="detail-item">
                                        <label>End Date & Time</label>
                                        <p><?= formatDateTimeValue($event['end_datetime'] ?? null) ?></p>
                                    </div>
                                    <div class="detail-item full-width">
                                        <label>Venue / Platform</label>
                                        <p><?= htmlspecialchars($event['venue_platform'] ?? 'N/A') ?></p>
                                    </div>
                                    <div class="detail-item full-width">
                                        <label>Participants</label>
                                        <p><?= htmlspecialchars($event['participants'] ?? 'N/A') ?></p>
                                    </div>
                                    <div class="detail-item">
                                        <label>Payment Collection</label>
                                        <p><?= htmlspecialchars($event['collect_payments'] ?? 'N/A') ?></p>
                                    </div>
                                    <div class="detail-item">
                                        <label>Extraneous</label>
                                        <p><?= htmlspecialchars($event['extraneous'] ?? 'N/A') ?></p>
                                    </div>
                                    <div class="detail-item">
                                        <label>Visitors</label>
                                        <p><?= htmlspecialchars($event['has_visitors'] ?? 'N/A') ?></p>
                                    </div>
                                    <div class="detail-item">
                                        <label>Distance</label>
                                        <p><?= htmlspecialchars($event['distance'] ?? 'N/A') ?></p>
                                    </div>
                                    <div class="detail-item">
                                        <label>Participant Range</label>
                                        <p><?= htmlspecialchars($event['participant_range'] ?? 'N/A') ?></p>
                                    </div>
                                    <div class="detail-item">
                                        <label>Overnight</label>
                                        <p><?= ((string) ($event['overnight'] ?? '') === '1') ? 'Yes' : 'No' ?></p>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section class="detail-card">
                            <div class="card-header">
                                <h2><i class="fa-solid fa-chart-column"></i> Metrics</h2>
                            </div>

                            <div class="card-body">
                                <div class="detail-grid">
                                    <div class="detail-item">
                                        <label>Target Metric</label>
                                        <p><?= !empty($event['target_metric']) ? htmlspecialchars($event['target_metric']) : 'N/A' ?></p>
                                    </div>
                                    <div class="detail-item">
                                        <label>Actual Metric</label>
                                        <p><?= !empty($event['actual_metric']) ? htmlspecialchars($event['actual_metric']) : 'N/A' ?></p>
                                    </div>
                                    <div class="detail-item">
                                        <label>Metric Result</label>
                                        <p class="<?= htmlspecialchars($metric_status['class']) ?>">
                                            <?= htmlspecialchars($metric_status['label']) ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section class="detail-card">
                            <div class="card-header">
                                <h2><i class="fa-solid fa-file-circle-question"></i> Requirement Review</h2>
                                <span class="badge badge-success"><?= $uploaded_docs ?>/<?= $total_docs ?> Uploaded</span>
                            </div>

                            <div class="card-body">
                                <div class="requirement-list">
                                    <?php foreach ($requirements as $doc): ?>
                                        <?php
                                        $is_narrative = (($doc['req_name'] ?? '') === 'Narrative Report');
                                        $has_narrative_content = !empty($doc['narrative']) || !empty($doc['video_documentation_link']);
                                        [$preview_url, $no_template_msg, $has_upload] = buildPreviewUrl($doc);
                                        $can_review = canReviewRequirement($doc, $event_status);
                                        ?>
                                        <article class="requirement-card status-<?= htmlspecialchars(normalizeStatusClass($doc['submission_status'] ?? 'Pending')) ?>">
                                            <div class="requirement-top">
                                                <div class="requirement-title-wrap">
                                                    <h3>
                                                        <?= htmlspecialchars($doc['req_name'] ?? '') ?>
                                                        <?php if (!empty($doc['req_desc'])): ?>
                                                            <span class="tooltip-icon">
                                                                <i class="fa-regular fa-circle-question"></i>
                                                                <span class="tooltip-text">
                                                                    <?= htmlspecialchars($doc['req_desc']) ?>
                                                                </span>
                                                            </span>
                                                        <?php endif; ?>
                                                    </h3>

                                                    <div class="requirement-meta">
                                                        <span class="mini-badge"><?= htmlspecialchars($doc['submission_status'] ?? 'Pending') ?></span>
                                                        <span class="mini-badge review-<?= htmlspecialchars($doc['review_status'] ?? 'Not Reviewed') ?>"><?= htmlspecialchars($doc['review_status'] ?? 'Not Reviewed') ?></span>
                                                    </div>
                                                </div>

                                                <div class="requirement-actions">
                                                    <?php if ($is_narrative): ?>
                                                        <a href="admin_review_narrative.php?event_id=<?= (int) $event_id ?>" class="btn-file">Review Narrative</a>
                                                    <?php else: ?>
                                                        <button
                                                            type="button"
                                                            class="btn-file"
                                                            onclick="previewDocument(
                                                                '<?= htmlspecialchars($preview_url, ENT_QUOTES) ?>',
                                                                '<?= htmlspecialchars($doc['req_name'] . ($has_upload ? ' (Uploaded)' : ' Template'), ENT_QUOTES) ?>',
                                                                '<?= htmlspecialchars($no_template_msg, ENT_QUOTES) ?>'
                                                            )">
                                                            View
                                                        </button>
                                                    <?php endif; ?>

                                                    <?php if ($can_review): ?>
                                                        <form action="admin_update_requirement.php" method="POST" class="inline-form">
                                                            <input type="hidden" name="event_id" value="<?= (int) $event_id ?>">
                                                            <input type="hidden" name="event_req_id" value="<?= (int) $doc['event_req_id'] ?>">
                                                            <input type="hidden" name="action" value="approve">
                                                            <button type="submit" class="btn-file">Approve</button>
                                                        </form>

                                                        <form action="admin_update_requirement.php" method="POST" class="inline-form">
                                                            <input type="hidden" name="event_id" value="<?= (int) $event_id ?>">
                                                            <input type="hidden" name="event_req_id" value="<?= (int) $doc['event_req_id'] ?>">
                                                            <input type="hidden" name="action" value="needs_revision">
                                                            <button type="submit" class="btn-file btn-danger">Needs Revision</button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                            <div class="requirement-body">
                                                <?php if (!empty($doc['deadline'])): ?>
                                                    <div class="req-line">
                                                        <strong>Deadline:</strong> <?= formatDateTimeValue($doc['deadline']) ?>
                                                    </div>
                                                <?php endif; ?>

                                                <?php if (!empty($doc['reviewed_at'])): ?>
                                                    <div class="req-line">
                                                        <strong>Reviewed:</strong> <?= formatDateTimeValue($doc['reviewed_at']) ?>
                                                    </div>
                                                <?php endif; ?>

                                                <?php if (!empty($doc['remarks'])): ?>
                                                    <div class="doc-remarks-box">
                                                        <strong>Remarks</strong>
                                                        <p><?= nl2br(htmlspecialchars($doc['remarks'])) ?></p>
                                                    </div>
                                                <?php endif; ?>

                                                <?php if ($is_narrative): ?>
                                                    <?php if (!empty($doc['submitted_at'])): ?>
                                                        <div class="req-line">
                                                            <strong>Submitted:</strong> <?= formatDateTimeValue($doc['submitted_at']) ?>
                                                        </div>
                                                    <?php endif; ?>

                                                    <?php if (!empty($doc['video_documentation_link'])): ?>
                                                        <div class="req-line">
                                                            <strong>Video Link:</strong>
                                                            <a class="doc-inline-link"
                                                                href="<?= htmlspecialchars($doc['video_documentation_link']) ?>"
                                                                target="_blank" rel="noopener noreferrer">
                                                                Open Link
                                                            </a>
                                                        </div>
                                                    <?php endif; ?>

                                                    <?php if (!empty($doc['narrative'])): ?>
                                                        <div class="doc-narrative-preview">
                                                            <strong>Narrative Preview</strong>
                                                            <p><?= nl2br(htmlspecialchars(mb_strimwidth($doc['narrative'], 0, 300, '...'))) ?></p>
                                                        </div>
                                                    <?php endif; ?>

                                                    <?php if (!$has_narrative_content): ?>
                                                        <div class="req-line">No Narrative Report content submitted yet.</div>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <?php if ($has_upload && !empty($doc['original_file_name'])): ?>
                                                        <div class="req-line">
                                                            <strong>File:</strong> <?= htmlspecialchars($doc['original_file_name']) ?>
                                                        </div>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </div>

                                            <?php if ($can_review): ?>
                                                <form action="admin_update_requirement.php" method="POST" class="requirement-remarks-form">
                                                    <input type="hidden" name="event_id" value="<?= (int) $event_id ?>">
                                                    <input type="hidden" name="event_req_id" value="<?= (int) $doc['event_req_id'] ?>">

                                                    <div class="field long-field">
                                                        <label class="field-title">Admin Remarks for <?= htmlspecialchars($doc['req_name']) ?></label>
                                                        <textarea
                                                            name="remarks"
                                                            rows="3"
                                                            placeholder="Write remarks or requested changes here..."><?= htmlspecialchars($doc['remarks'] ?? '') ?></textarea>
                                                    </div>

                                                    <div class="remarks-actions">
                                                        <button type="submit" name="action" value="save_remarks" class="btn-secondary">
                                                            Save Remarks
                                                        </button>
                                                        <button type="submit" name="action" value="needs_revision" class="btn-primary btn-danger">
                                                            Save & Mark Needs Revision
                                                        </button>
                                                    </div>
                                                </form>
                                            <?php endif; ?>
                                        </article>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </section>
                    </div>

                    <aside class="manage-sidebar">
                        <section class="tracker-card">
                            <h2>Progress Tracker</h2>

                            <div class="ring" style="--progress: <?= $progress_percentage ?>; --progress-color: <?= $progress_color ?>;">
                                <div class="ring-inner">
                                    <span class="ring-number"><?= $progress_percentage ?>%</span>
                                </div>
                            </div>

                            <div class="tracker-list">
                                <div class="t-row"><span>Total Documents</span><span class="t-score"><?= $total_docs ?></span></div>
                                <div class="t-row"><span>Uploaded</span><span class="t-score"><?= $uploaded_docs ?></span></div>
                                <div class="t-row"><span>Pending</span><span class="t-score"><?= $pending_docs ?></span></div>
                                <div class="t-row"><span>Completion</span><span class="t-score"><?= $progress_percentage ?>%</span></div>
                            </div>
                        </section>

                        <section class="detail-card decision-card">
                            <div class="card-header">
                                <h2><i class="fa-solid fa-gavel"></i> Event Decision</h2>
                            </div>

                            <div class="card-body">
                                <div class="decision-status-grid">
                                    <div class="decision-status-item">
                                        <div class="decision-status-label">Pre-event Requirements</div>
                                        <div class="decision-status-value <?= $allPreEventReviewedOkay ? 'status-ready' : 'status-incomplete' ?>">
                                            <?php if ($allPreEventReviewedOkay): ?>
                                                <i class="fa-solid fa-circle-check"></i> Ready
                                            <?php else: ?>
                                                <i class="fa-solid fa-circle-xmark"></i> Incomplete
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="decision-status-item">
                                        <div class="decision-status-label">Narrative Report</div>
                                        <div class="decision-status-value <?= $narrativeApproved ? 'status-ready' : 'status-incomplete' ?>">
                                            <?php if ($narrativeApproved): ?>
                                                <i class="fa-solid fa-circle-check"></i> Approved
                                            <?php else: ?>
                                                <i class="fa-solid fa-circle-xmark"></i> Not Approved
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="decision-status-item full-width-status">
                                        <div class="decision-status-label">Current Event Status</div>
                                        <div class="decision-status-value status-badge-display">
                                            <span class="status-badge-decision status-<?= htmlspecialchars(normalizeStatusClass($event_status)) ?>">
                                                <?= htmlspecialchars($event_status) ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <form action="admin_update_event_status.php" method="POST" class="decision-form">
                                    <input type="hidden" name="event_id" value="<?= (int) $event_id ?>">

                                    <div class="field long-field">
                                        <label class="field-title">
                                            <i class="fa-solid fa-message"></i> Event-Level Admin Remarks
                                        </label>
                                        <textarea
                                            name="admin_remarks"
                                            rows="4"
                                            class="decision-textarea"
                                            placeholder="Write final remarks for this event..."><?= htmlspecialchars($event['admin_remarks'] ?? '') ?></textarea>
                                    </div>

                                    <div class="decision-actions">
                                        <button type="submit" name="action" value="save_remarks" class="btn-decision btn-save">
                                            <i class="fa-solid fa-floppy-disk"></i> Save Remarks
                                        </button>

                                        <?php if (canReturnEventForRevision($event_status)): ?>
                                            <button type="submit" name="action" value="needs_revision" class="btn-decision btn-revision">
                                                <i class="fa-solid fa-rotate-left"></i> Mark Needs Revision
                                            </button>
                                        <?php endif; ?>

                                        <?php if (canApproveEvent($event_status)): ?>
                                            <button type="submit" name="action" value="approve" class="btn-decision btn-approve">
                                                <i class="fa-solid fa-circle-check"></i> Approve Event
                                            </button>
                                        <?php endif; ?>

                                        <?php if (canCompleteEvent($event_status, $narrativeApproved)): ?>
                                            <button type="submit" name="action" value="complete" class="btn-decision btn-complete">
                                                <i class="fa-solid fa-flag-checkered"></i> Mark as Completed
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </form>
                            </div>
                        </section>
                    </aside>
                </div>

                <?php include PUBLIC_PATH . 'assets/includes/footer.php'; ?>
            </section>
        </main>
    </div>

    <div class="modal" id="docPreviewModal">
        <div class="modal-backdrop" onclick="closePreview()"></div>
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Document Preview</h3>
                <button class="modal-close" onclick="closePreview()">✕</button>
            </div>
            <div class="modal-body">
                <iframe id="docFrame" src="" frameborder="0"></iframe>
                <p id="modalMessage" style="display:none; padding:1rem; text-align:center; color:#555;"></p>
            </div>
        </div>
    </div>

    <!-- JavaScript: Load layout.js for sidebar and responsive behavior -->
    <script src="<?= APP_URL ?>script/layout.js?v=1"></script>
    
    <!-- JavaScript: Document Preview Modal Functions -->
    <script>
        /**
         * Open Document Preview Modal
         * 
         * Purpose:
         * Display file or template in modal dialog. Handles both HTML file preview
         * and error message display when file unavailable.
         * 
         * @param url URL to document for iframe (empty if no preview)
         * @param name Document name (shown in modal title)
         * @param noTemplateMsg Error message (shown if no preview available)
         */
        function previewDocument(url, name, noTemplateMsg = '') {
            const modal = document.getElementById('docPreviewModal');
            const frame = document.getElementById('docFrame');
            const title = document.getElementById('modalTitle');
            const msgEl = document.getElementById('modalMessage');

            // Set modal title to document name
            title.textContent = name;

            // If no URL or template message provided, show error
            if (!url || noTemplateMsg) {
                // Hide iframe (document not available)
                frame.style.display = 'none';
                frame.src = '';
                // Show error message
                msgEl.style.display = 'block';
                msgEl.textContent = noTemplateMsg || 'No preview available.';
            } else {
                // Hide error message
                msgEl.style.display = 'none';
                msgEl.textContent = '';
                // Show iframe with document
                frame.style.display = 'block';
                frame.src = url;
            }

            // Add active class to show modal (CSS transition/animation)
            modal.classList.add("active");
        }

        /**
         * Close Document Preview Modal
         * 
         * Purpose:
         * Clean up and hide the document preview modal. Clears iframe to prevent
         * loading/caching issues on subsequent opens.
         */
        function closePreview() {
            const modal = document.getElementById('docPreviewModal');
            const frame = document.getElementById('docFrame');
            const msgEl = document.getElementById('modalMessage');

            // Remove active class to hide modal
            modal.classList.remove('active');
            // Clear iframe src to stop loading/caching
            frame.src = "";
            // Hide error message
            msgEl.style.display = 'none';
            msgEl.textContent = '';
        }

        /**
         * Escape Key Listener: Close Modal
         * 
         * Allows user to press Escape key to close preview modal
         * Standard UX pattern for modal dialogs
         */
        document.addEventListener("keydown", function (e) {
            if (e.key === "Escape") {
                closePreview();
            }
        });
    </script>
</body>

</html>