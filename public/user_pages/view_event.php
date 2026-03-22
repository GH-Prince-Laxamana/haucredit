<?php
/**
 * VIEW EVENT PAGE - Event Details & Compliance Document Management
 * 
 * Purpose: Display comprehensive event details, compliance requirements, document upload status,
 * and submission/review tracking for a single event. Users can preview requirements, upload documents,
 * and view narrative report details.
 * 
 * Page Features:
 * - Event metadata display (classification, basic info, schedule, logistics)
 * - Required documents list with submission/review status tracking
 * - Document preview and upload interface
 * - Narrative report viewing/editing (with special handling)
 * - Progress tracker with percent completion
 * - Archive/restore/delete functionality
 * - Admin remarks display (when event needs revision)
 * 
 */

session_start();
require_once __DIR__ . '/../../app/database.php';

requireLogin();

$user_id = (int) $_SESSION["user_id"];
$event_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($event_id <= 0) {
    popup_error("Invalid event.", PUBLIC_URL . 'index.php');
}

/* ================= HELPERS ================= */
function formatSubmissionStatus(string $status): string
{
    return match ($status) {
        'Uploaded' => 'Submitted',
        'Pending' => 'Pending',
        default => $status
    };
}

function formatReviewStatus(string $status): string
{
    return match ($status) {
        'Not Reviewed' => 'Not Reviewed',
        'Needs Revision' => 'Needs Revision',
        'Approved' => 'Approved',
        default => $status
    };
}

function formatDateTimeValue(?string $value): string
{
    if (empty($value)) {
        return 'N/A';
    }

    $ts = strtotime($value);
    return $ts ? date('F j, Y g:i A', $ts) : 'N/A';
}

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
            'class' => 'metric-mismatch',
            'details' => 'Target uses "' . $target['label'] . '" but actual uses "' . $actual['label'] . '".'
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

function getEventStatusBanner(string $event_status, ?string $admin_remarks = null): array
{
    $title = "Event Status: " . $event_status;
    $message = '';
    $class = strtolower(str_replace(' ', '-', $event_status));

    switch ($event_status) {
        case 'Draft':
            $message = "This event is currently saved as a draft. It is not yet submitted for admin review.";
            break;

        case 'Pending Review':
            $message = "This event has been submitted and is currently awaiting admin review.";
            break;

        case 'Needs Revision':
            $message = "This event needs revisions based on admin review. Please check the remarks and update the necessary details or documents.";
            break;

        case 'Approved':
            $message = "This event has been approved. You may now proceed with the activity and complete the remaining post-event requirements when applicable.";
            break;

        case 'Completed':
            $message = "This event is completed. All required event processing, including the Narrative Report review, has been finalized.";
            break;

        default:
            $message = "This event is currently in the system.";
            break;
    }

    if (!empty($admin_remarks)) {
        $message .= "<br><strong>Admin remarks:</strong> " . htmlspecialchars($admin_remarks);
    }

    return [
        'title' => $title,
        'message' => $message,
        'class' => $class
    ];
}

/**
 * ===== DATA FETCHING & PROCESSING =====
 * 
 * This section loads event data from database, applies business logic transformations,
 * and prepares display-ready variables for the HTML template.
 */

// ==== FETCH EVENT DATA ====
// Query: Multi-table LEFT JOIN to assemble complete event profile
// Includes: Core event fields + type classification + dates + participants + location + logistics + metrics
// Security: Parameterized query with user_id check (only owner sees their events)
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
      AND e.user_id = ?
    LIMIT 1
";

$event = fetchOne($conn, $fetchEventSql, "ii", [$event_id, $user_id]);

if (!$event) {
    popup_error("Event not found or you don't have permission to view it.", PUBLIC_URL . 'index.php');
}

/**
 * EVENT STATE DETERMINATION
 * 
 * Derive boolean flags from event_status and archived_at to control UI visibility and editing permissions.
 * These state flags are used throughout the template to show/hide sections and enforce business rules.
 */

// ==== ARCHIVED STATE ====
// Check if event has been archived (soft delete with 30-day expiry)
$is_archived = !empty($event['archived_at']);

// ==== STATUS FLAGS ====
// Determine current event status (default: 'Draft' if missing)
$event_status = $event['event_status'] ?? 'Draft';
$is_completed = ($event_status === 'Completed');
$is_approved = ($event_status === 'Approved');
$is_draft = ($event_status === 'Draft');
$is_pending_review = ($event_status === 'Pending Review');
$is_needs_revision = ($event_status === 'Needs Revision');

// ==== EDITABLE STATE ====
// Determine if user can edit event fields and upload/modify documents
// Rule: Can only edit if NOT archived AND status allows edits (Draft, Pending Review, or Needs Revision)
// Completed and Approved events are read-only (documents can be uploaded post-event for Approved)
$can_edit_event = !$is_archived && in_array($event_status, ['Draft', 'Pending Review', 'Needs Revision'], true);

// Determine if user can upload/remove documents for requirements
// Same rule as event editing but also checked individually per document
$can_modify_documents = !$is_archived && in_array($event_status, ['Draft', 'Pending Review', 'Needs Revision'], true);

/**
 * canModifyRequirement - Check if user can modify a specific requirement/document
 * 
 * Business Rule: User can modify requirement IF:
 * 1. Global permission exists (can_modify_documents = true)
 * 2. AND document hasn't been approved by admin (review_status !== 'Approved')
 * 
 * Approved documents are locked from further changes (admin verified correctness).
 * 
 * @param array $doc - Requirement record with 'review_status' field
 * @param bool $can_modify_documents - Global permission flag
 * @return bool - True if user can modify this specific document
 */
function canModifyRequirement(array $doc, bool $can_modify_documents): bool
{
    // ==== CHECK 1: Global modification permission ====
    if (!$can_modify_documents) {
        return false; // User can't modify any documents
    }

    // ==== CHECK 2: Document-level lock (approved by admin) ====
    // Approved documents are locked from further changes
    if (($doc['review_status'] ?? '') === 'Approved') {
        return false; // This specific document is approved/locked
    }

    // ==== RETURN: All checks passed, user can modify ====
    return true;
}

// ==== ARCHIVE EXPIRY COUNTDOWN ====
// Calculate days remaining before archived event is permanently deleted
// Archived events have 30-day grace period for restoration
$days_remaining = null;
if ($is_archived) {
    // Parse archived_at timestamp
    $archived_time = strtotime($event['archived_at']);
    // Add 30 days (30 * 24 hours * 60 mins * 60 secs)
    $expiry_time = $archived_time + (30 * 24 * 60 * 60);
    // Calculate days left (ceil rounds up so partial day remaining shows as 1)
    $days_remaining = ceil(($expiry_time - time()) / 86400);
}

$status_banner = getEventStatusBanner($event_status, $event['admin_remarks'] ?? null);

/**
 * FETCH REQUIRED DOCUMENTS
 * 
 * Query: Multi-table query to fetch all requirements for this event with submission/review details.
 * JOINs: requirement_templates (definitions), requirement_files (user uploads), narrative_report_details (narrative/video).
 */
$fetchRequiredDocsSql = "
    SELECT
        er.event_req_id,
        er.event_id,
        er.submission_status,
        er.review_status,
        er.deadline,
        er.reviewed_at,
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

// Fetch all requirements for this event
// Returns: Documents with deadline, submission status, review status, uploaded file info, and narrative details
$required_docs = fetchAll($conn, $fetchRequiredDocsSql, "i", [$event_id]);

/**
 * TRANSFORM: Add display-ready fields to each requirement
 * 
 * Transform raw database fields into template-ready values:
 * - Add status flags for template conditionals (is_narrative_report)
 * - Add formatted status labels for display (display_submission_status, display_review_status)
 * - Keep all original fields intact
 */
$required_docs = array_map(function ($doc) {
    // Extract status values or use defaults if missing
    $submission_status = $doc['submission_status'] ?? 'Pending';
    $review_status = $doc['review_status'] ?? 'Not Reviewed';

    // ==== FLAG: Is this the Narrative Report (special handling)? ====
    // Narrative Report has unique editors/submission interface vs standard requirements
    $doc['is_narrative_report'] = (($doc['req_name'] ?? '') === 'Narrative Report');
    
    // ==== TRANSFORM: Format status for display ====
    // Convert internal status values to user-friendly labels
    $doc['display_submission_status'] = formatSubmissionStatus($submission_status);
    $doc['display_review_status'] = formatReviewStatus($review_status);

    return $doc;
}, $required_docs);

/**
 * PROGRESS CALCULATION
 * 
 * Calculate document upload progress percentage and derive color visualization.
 * Used for progress tracker ring and progress bar displays.
 */

// Extract total and uploaded document counts from event record
$total_docs = (int) ($event['docs_total'] ?? 0);
$uploaded_docs = (int) ($event['docs_uploaded'] ?? 0);

// Calculate pending count (never negative via max())
$pending_docs = max(0, $total_docs - $uploaded_docs);

// ==== PROGRESS PERCENT ====
// Formula: (uploaded / total) * 100, rounded to nearest integer
// Edge case: If total is 0, progress is 0% (avoid division by zero)
$progress_percentage = $total_docs ? round(($uploaded_docs / $total_docs) * 100) : 0;

// ==== PROGRESS COLOR (HSL gradient) ====
// Convert percent to hue value: 0% = red (0°), 100% = green (120°)
// This creates visual gradient from red→yellow→green as progress increases
$pct = max(0, min(100, (int) $progress_percentage));  // Clamp to 0-100 range
$hue = ($pct / 100) * 120;                             // Convert to hue (0-120 degrees)
$progress_color = "hsl($hue, 70%, 45%)";              // HSL color (saturation: 70%, lightness: 45%)

/**
 * ORGANIZING BODY FORMATTING
 * 
 * Database stores organizing body as JSON array, but display prefers comma-separated list.
 * Parse JSON and join with commas for readable output.
 */

// Start with raw organizing_body value (may be JSON or empty)
$organizing_body_display = $event['organizing_body'] ?? '';

// Attempt to decode as JSON array
$decoded_orgs = json_decode($organizing_body_display, true);

// If decode successful and is array, convert to comma-separated string
if (is_array($decoded_orgs)) {
    $organizing_body_display = implode(", ", $decoded_orgs);
}
// Otherwise display as-is (already string or empty)

/**
 * METRIC ACHIEVEMENT STATUS
 * 
 * Compare target vs actual event metrics and determine achievement.
 * Returns status info for "Basic Information" card display.
 */
$metric_status = getMetricAchievementStatus(
    $event['target_metric'] ?? null,
    $event['actual_metric'] ?? null
);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>View Event - HAUCREDIT</title>
    <link rel="stylesheet" href="<?= PUBLIC_URL ?>assets/styles/layout.css" />
    <link rel="stylesheet" href="<?= PUBLIC_URL ?>assets/styles/view_event.css" />
</head>

<body>
    <div class="app">
        <div class="sidebar-overlay" id="sidebarOverlay" hidden></div>

        <?php include PUBLIC_PATH . 'assets/includes/general_nav.php' ?>

        <main class="main">
            <header class="topbar">
                <button class="hamburger" id="menuBtn" type="button" aria-label="Open menu">☰</button>

                <div class="title-wrap">
                    <h1><?= htmlspecialchars($event['event_name']) ?></h1>
                    <p>Event Details and Compliance Status</p>
                </div>

                <div class="action-btns">
                    <form method="POST" action="archive_event.php" class="inline-form" data-confirm="<?= $is_archived
                        ? 'Restore this event?'
                        : 'Archive this event? You can restore it for 30 days.' ?>">

                        <input type="hidden" name="event_id" value="<?= (int) $event['event_id'] ?>">

                        <?php if ($is_archived): ?>
                            <input type="hidden" name="action" value="restore">
                            <button type="submit" class="btn-primary btn-restore">Restore Event</button>
                        <?php else: ?>
                            <input type="hidden" name="action" value="archive">
                            <button type="submit" class="btn-primary btn-danger">Archive Event</button>
                        <?php endif; ?>
                    </form>

                    <?php if ($can_edit_event): ?>
                        <a href="create_event.php?id=<?= (int) $event['event_id'] ?>" class="btn-primary">Edit Event</a>
                    <?php endif; ?>
                </div>
            </header>

            <section class="content view-event-page">
                <div class="action-btns">
                    <button type="button" class="btn-secondary" onclick="history.back()">Back</button>
                </div>

                <div
                    class="status-banner status-<?= htmlspecialchars($is_archived ? 'archived' : $status_banner['class']) ?>">
                    <div class="status-content">
                        <?php if (!$is_archived): ?>
                            <h3><?= htmlspecialchars($status_banner['title']) ?></h3>
                            <p><?= $status_banner['message'] ?></p>

                            <?php if (!empty($event['admin_remarks'])): ?>
                                <div class="hero-remarks">
                                    <strong>Current Admin Remarks</strong>
                                    <p>
                                        <?= nl2br(htmlspecialchars($event['admin_remarks'])) ?>
                                    </p>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <h3>This event is archived</h3>
                            <p>
                                You can view the event but editing and uploads are disabled.<br>
                                This event will be permanently deleted in
                                <strong><?= max((int) $days_remaining, 0) ?> days</strong>
                            </p>
                        <?php endif; ?>
                    </div>

                    <div class="event-hero-meta">
                        <div class="hero-meta-item">
                            <span class="hero-meta-label">Created</span>
                            <span class="hero-meta-value">
                                <?= formatDateTimeValue($event['created_at'] ?? null) ?>
                            </span>
                        </div>
                        <div class="hero-meta-item">
                            <span class="hero-meta-label">Updated</span>
                            <span class="hero-meta-value">
                                <?= formatDateTimeValue($event['updated_at'] ?? null) ?>
                            </span>
                        </div>
                        <div class="hero-meta-item">
                            <span class="hero-meta-label">Activity Type</span>
                            <span class="hero-meta-value">
                                <?= htmlspecialchars($event['activity_type'] ?? 'N/A') ?>
                            </span>
                        </div>
                    </div>
                </div>

                <div class="col-left">

                    <section class="detail-card">
                        <div class="card-header">
                            <h2><i class="fa-solid fa-magnifying-glass-chart"></i> Event Classification</h2>
                            <span
                                class="badge badge-primary"><?= htmlspecialchars($event['activity_type'] ?? 'N/A') ?></span>
                        </div>
                        <div class="card-body">
                            <div class="detail-grid">
                                <div class="detail-item">
                                    <label>Organizing Body</label>
                                    <p><?= htmlspecialchars($organizing_body_display) ?></p>
                                </div>
                                <div class="detail-item">
                                    <label>Background</label>
                                    <p><?= htmlspecialchars($event['background'] ?? 'N/A') ?></p>
                                </div>
                                <div class="detail-item">
                                    <label>Nature of Activity</label>
                                    <p><?= htmlspecialchars($event['nature'] ?? '') ?></p>
                                </div>
                                <?php if (!empty($event['series'])): ?>
                                    <div class="detail-item">
                                        <label>Series</label>
                                        <p><?= htmlspecialchars($event['series']) ?></p>
                                    </div>
                                <?php endif; ?>
                                <div class="detail-item">
                                    <label>Expected Participants</label>
                                    <p><?= htmlspecialchars($event['participants'] ?? '') ?></p>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="detail-card">
                        <div class="card-header">
                            <h2><i class="fa-solid fa-circle-info"></i> Basic Information</h2>
                        </div>
                        <div class="card-body">
                            <div class="detail-grid">
                                <div class="detail-item full-width">
                                    <label>Event Name</label>
                                    <p><?= htmlspecialchars($event['event_name']) ?></p>
                                </div>
                                <div class="detail-item">
                                    <label>Target Metric</label>
                                    <p><?= !empty($event['target_metric']) ? htmlspecialchars($event['target_metric']) : 'N/A' ?>
                                    </p>
                                </div>
                                <div class="detail-item">
                                    <label>Actual Metric</label>
                                    <p><?= !empty($event['actual_metric']) ? htmlspecialchars($event['actual_metric']) : 'N/A' ?>
                                    </p>
                                </div>
                                <div class="detail-item">
                                    <label>Metric Result</label>
                                    <p class="<?= htmlspecialchars($metric_status['class']) ?>">
                                        <?= htmlspecialchars($metric_status['label']) ?>
                                    </p>
                                </div>
                                <div class="detail-item">
                                    <label>Extraneous Activity</label>
                                    <p><?= htmlspecialchars($event['extraneous'] ?? 'N/A') ?></p>
                                </div>
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
                                    <p><?= htmlspecialchars($event['participants'] ?? '') ?></p>
                                </div>
                                <div class="detail-item">
                                    <label>Payment Collection</label>
                                    <p><?= htmlspecialchars($event['collect_payments'] ?? 'N/A') ?></p>
                                </div>
                                <div class="detail-item">
                                    <label>Visitors Entering Campus</label>
                                    <p><?= htmlspecialchars($event['has_visitors'] ?? 'N/A') ?></p>
                                </div>

                                <?php if (!empty($event['activity_type']) && strpos($event['activity_type'], 'Off-Campus') !== false): ?>
                                    <div class="detail-item">
                                        <label>Distance</label>
                                        <p><?= htmlspecialchars($event['distance'] ?? 'N/A') ?></p>
                                    </div>
                                    <div class="detail-item">
                                        <label>Participant Range</label>
                                        <p><?= htmlspecialchars($event['participant_range'] ?? 'N/A') ?></p>
                                    </div>
                                    <div class="detail-item">
                                        <label>Overnight / Duration &gt;12 hours</label>
                                        <p><?= ((string) ($event['overnight'] ?? '') === '1') ? 'Yes' : 'No' ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </section>

                    <section class="detail-card">
                        <div class="card-header">
                            <h2><i class="fa-solid fa-file-circle-question"></i> Required Documents</h2>
                            <span class="badge badge-success"><?= $uploaded_docs ?>/<?= $total_docs ?> Uploaded</span>
                        </div>

                        <div class="card-body">
                            <div class="doc-checklist">
                                <div class="requirement-list user-requirement-list">
                                    <p class="doc-help">
                                        Upload your documents in PDF format. If no file is uploaded, you may preview
                                        or download the template when available.
                                    </p>

                                    <?php foreach ($required_docs as $doc): ?>
                                        <?php
                                        $is_narrative_report = !empty($doc['is_narrative_report']);
                                        $has_narrative_content = !empty($doc['narrative']) || !empty($doc['video_documentation_link']);
                                        [$preview_url, $no_template_msg, $has_upload] = buildPreviewUrl($doc);

                                        $submission_class = strtolower(str_replace(' ', '-', $doc['submission_status'] ?? 'Pending'));
                                        $review_class = strtolower(str_replace(' ', '-', $doc['display_review_status'] ?? 'Not Reviewed'));
                                        $can_modify_this_doc = canModifyRequirement($doc, $can_modify_documents);
                                        ?>
                                        <article
                                            class="requirement-card user-requirement-card status-<?= htmlspecialchars($submission_class) ?>">
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
                                                        <span
                                                            class="mini-badge submission-<?= htmlspecialchars($submission_class) ?>">
                                                            <?= htmlspecialchars($doc['display_submission_status']) ?>
                                                        </span>
                                                        <span
                                                            class="mini-badge review-<?= htmlspecialchars($review_class) ?>">
                                                            <?= htmlspecialchars($doc['display_review_status']) ?>
                                                        </span>
                                                    </div>
                                                </div>

                                                <div class="requirement-actions">
                                                    <?php if ($is_narrative_report): ?>
                                                        <?php
                                                        $narrative_locked = (($doc['review_status'] ?? '') === 'Approved') || !$can_modify_documents;
                                                        ?>
                                                        <a href="narrative_report_submission.php?event_id=<?= (int) $event_id ?>"
                                                            class="btn-file">
                                                            <?php if ($narrative_locked): ?>
                                                                View
                                                            <?php else: ?>
                                                                <?= ($doc['submission_status'] ?? 'Pending') === 'Uploaded' ? 'View / Edit' : 'Open' ?>
                                                            <?php endif; ?>
                                                        </a>
                                                    <?php else: ?>
                                                        <button type="button" class="btn-file" onclick="previewDocument(
                                                                '<?= htmlspecialchars($preview_url, ENT_QUOTES) ?>',
                                                                '<?= htmlspecialchars($doc['req_name'] . ($has_upload ? ' (Uploaded)' : ' Template'), ENT_QUOTES) ?>',
                                                                '<?= htmlspecialchars($no_template_msg, ENT_QUOTES) ?>'
                                                            )">
                                                            View
                                                        </button>

                                                        <?php if ($can_modify_this_doc && ($doc['submission_status'] ?? 'Pending') !== 'Uploaded'): ?>
                                                            <form action="create_requirement.php" method="POST"
                                                                enctype="multipart/form-data" class="inline-form">
                                                                <input type="hidden" name="event_req_id"
                                                                    value="<?= (int) $doc['event_req_id'] ?>">
                                                                <label class="btn-file">
                                                                    Upload
                                                                    <input type="file" name="document" hidden required
                                                                        onchange="this.form.submit()">
                                                                </label>
                                                            </form>
                                                        <?php endif; ?>

                                                        <?php if ($has_upload && $can_modify_this_doc): ?>
                                                            <form data-confirm="Remove uploaded document?"
                                                                action="delete_requirement.php" method="POST" class="inline-form">
                                                                <input type="hidden" name="event_req_id"
                                                                    value="<?= (int) $doc['event_req_id'] ?>">
                                                                <button type="submit" class="btn-file btn-danger">Remove</button>
                                                            </form>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                            <div class="requirement-body">
                                                <?php if (!empty($doc['deadline'])): ?>
                                                    <div class="req-line">
                                                        <strong>Deadline:</strong> <?= formatDateTimeValue($doc['deadline']) ?>
                                                    </div>
                                                <?php endif; ?>

                                                <?php if (!empty($doc['remarks'])): ?>
                                                    <div class="doc-remarks-box">
                                                        <strong>Remarks</strong>
                                                        <p><?= nl2br(htmlspecialchars($doc['remarks'])) ?></p>
                                                    </div>
                                                <?php endif; ?>

                                                <?php if ($is_narrative_report): ?>
                                                    <?php if (!empty($doc['submitted_at'])): ?>
                                                        <div class="req-line">
                                                            <strong>Submitted:</strong>
                                                            <?= formatDateTimeValue($doc['submitted_at']) ?>
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
                                                            <p><?= nl2br(htmlspecialchars(mb_strimwidth($doc['narrative'], 0, 300, '...'))) ?>
                                                            </p>
                                                        </div>
                                                    <?php endif; ?>

                                                    <?php if (!$has_narrative_content && empty($doc['template_url'])): ?>
                                                        <div class="req-line">No submission yet.</div>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <?php if ($has_upload && !empty($doc['original_file_name'])): ?>
                                                        <div class="req-line">
                                                            <strong>File:</strong>
                                                            <?= htmlspecialchars($doc['original_file_name']) ?>
                                                        </div>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </div>
                                        </article>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>

                <div class="col-right">
                    <aside class="tracker-card">
                        <h2>Progress Tracker</h2>
                        <div class="ring"
                            style="--progress: <?= $progress_percentage ?>; --progress-color: <?= $progress_color ?>;">
                            <div class="ring-inner">
                                <span class="ring-number" id="progressNumber"><?= $progress_percentage ?>%</span>
                            </div>
                        </div>
                        <div class="tracker-list">
                            <div class="t-row">
                                <span><i class="fa-regular fa-file"></i> Total Documents</span>
                                <span class="t-score"><?= $total_docs ?></span>
                            </div>

                            <div class="t-row">
                                <span><i class="fa-regular fa-circle-check"></i> Uploaded</span>
                                <span class="t-score"><?= $uploaded_docs ?></span>
                            </div>
                            <div class="t-row">
                                <span><i class="fa-regular fa-clock"></i> Pending</span>
                                <span class="t-score"><?= $pending_docs ?></span>
                            </div>
                            <div class="t-row">
                                <span><i class="fa-solid fa-chart-line"></i> Completion</span>
                                <span class="t-score"><?= $progress_percentage ?>%</span>
                            </div>
                        </div>
                    </aside>
                </div>

                <?php if ($is_archived): ?>
                    <section class="danger-zone">
                        <h3>Danger Zone</h3>
                        <p>Permanently delete this archived event. This cannot be undone.</p>

                        <form method="POST" action="delete_event.php"
                            data-confirm="Permanently delete this event? This cannot be undone.">
                            <input type="hidden" name="event_id" value="<?= (int) $event['event_id'] ?>">
                            <button class="btn-primary btn-danger">Delete Permanently</button>
                        </form>
                    </section>
                <?php endif; ?>

                <?php include PUBLIC_PATH . 'assets/includes/footer.php' ?>
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

    <script src="<?= APP_URL ?>script/layout.js?v=1"></script>
    <script>
        function previewDocument(url, name, noTemplateMsg = '') {
            const modal = document.getElementById('docPreviewModal');
            const frame = document.getElementById('docFrame');
            const title = document.getElementById('modalTitle');
            const msgEl = document.getElementById('modalMessage');

            title.textContent = name;

            if (!url || noTemplateMsg) {
                frame.style.display = 'none';
                frame.src = '';
                msgEl.style.display = 'block';
                msgEl.textContent = noTemplateMsg || 'No preview available.';
            } else {
                msgEl.style.display = 'none';
                msgEl.textContent = '';
                frame.style.display = 'block';
                frame.src = url;
            }

            modal.classList.add("active");
        }

        function closePreview() {
            const modal = document.getElementById('docPreviewModal');
            const frame = document.getElementById('docFrame');
            const msgEl = document.getElementById('modalMessage');

            modal.classList.remove('active');
            frame.src = "";
            msgEl.style.display = 'none';
            msgEl.textContent = '';
        }

        document.addEventListener("keydown", function (e) {
            if (e.key === "Escape") {
                closePreview();
            }
        });
    </script>
</body>

</html>