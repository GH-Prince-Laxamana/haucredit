<?php
session_start();
require_once "../app/database.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit();
}

$user_id = (int) $_SESSION["user_id"];
$event_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($event_id <= 0) {
    popup_error("Invalid event ID.");
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

/* ================= EVENT DATA FETCH ================= */
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
    popup_error("Event not found or you don't have permission to view it.");
}

/* ================= EVENT STATE ================= */
$is_archived = !empty($event['archived_at']);

$event_status = $event['event_status'] ?? 'Draft';
$is_completed = ($event_status === 'Completed');
$is_approved = ($event_status === 'Approved');
$is_draft = ($event_status === 'Draft');
$is_pending_review = ($event_status === 'Pending Review');
$is_needs_revision = ($event_status === 'Needs Revision');

/* editable states */
$can_edit_event = !$is_archived && in_array($event_status, ['Draft', 'Pending Review', 'Needs Revision'], true);
$can_modify_documents = !$is_archived && in_array($event_status, ['Draft', 'Pending Review', 'Needs Revision', 'Approved'], true);

$days_remaining = null;
if ($is_archived) {
    $archived_time = strtotime($event['archived_at']);
    $expiry_time = $archived_time + (30 * 24 * 60 * 60);
    $days_remaining = ceil(($expiry_time - time()) / 86400);
}

$status_banner = getEventStatusBanner($event_status, $event['admin_remarks'] ?? null);

/* ================= REQUIRED DOCUMENTS FETCH ================= */
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

$required_docs = fetchAll($conn, $fetchRequiredDocsSql, "i", [$event_id]);

$required_docs = array_map(function ($doc) {
    $submission_status = $doc['submission_status'] ?? 'Pending';
    $review_status = $doc['review_status'] ?? 'Not Reviewed';

    $doc['is_narrative_report'] = (($doc['req_name'] ?? '') === 'Narrative Report');
    $doc['display_submission_status'] = formatSubmissionStatus($submission_status);
    $doc['display_review_status'] = formatReviewStatus($review_status);

    return $doc;
}, $required_docs);

/* ================= PROGRESS CALCULATION ================= */
$total_docs = (int) ($event['docs_total'] ?? 0);
$uploaded_docs = (int) ($event['docs_uploaded'] ?? 0);
$pending_docs = max(0, $total_docs - $uploaded_docs);
$progress_percentage = $total_docs ? round(($uploaded_docs / $total_docs) * 100) : 0;

$pct = max(0, min(100, (int) $progress_percentage));
$hue = ($pct / 100) * 120;
$progress_color = "hsl($hue, 70%, 45%)";

/* ================= ORGANIZING BODY FORMAT ================= */
$organizing_body_display = $event['organizing_body'] ?? '';
$decoded_orgs = json_decode($organizing_body_display, true);
if (is_array($decoded_orgs)) {
    $organizing_body_display = implode(", ", $decoded_orgs);
}

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
    <link rel="stylesheet" href="assets/styles/layout.css" />
    <link rel="stylesheet" href="assets/styles/view_event.css" />
</head>

<body>
    <div class="app">
        <div class="sidebar-overlay" id="sidebarOverlay" hidden></div>

        <?php include 'assets/includes/general_nav.php' ?>

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
                        <?php else: ?>
                            <h3>This event is archived</h3>
                            <p>
                                You can view the event but editing and uploads are disabled.<br>
                                This event will be permanently deleted in
                                <strong><?= max((int) $days_remaining, 0) ?> days</strong>
                            </p>
                        <?php endif; ?>
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
                                <p class="doc-help">
                                    Upload your documents in PDF format. If no file is uploaded, you may preview
                                    or download the template when available.
                                </p>

                                <?php foreach ($required_docs as $doc): ?>
                                    <?php
                                    $is_narrative_report = !empty($doc['is_narrative_report']);
                                    $has_narrative_content = !empty($doc['narrative']) || !empty($doc['video_documentation_link']);
                                    [$preview_url, $no_template_msg, $has_upload] = buildPreviewUrl($doc);
                                    ?>
                                    <div
                                        class="doc-item status-<?= strtolower(str_replace(' ', '-', $doc['submission_status'] ?? 'Pending')) ?>">

                                        <div class="doc-checkbox">
                                            <?php if (($doc['submission_status'] ?? 'Pending') === 'Uploaded'): ?>
                                                <i class="fa-solid fa-file-circle-check"></i>
                                            <?php else: ?>
                                                <i class="fa-solid fa-hourglass-half"></i>
                                            <?php endif; ?>
                                        </div>

                                        <div class="doc-info">
                                            <h4>
                                                <?= htmlspecialchars($doc['req_name'] ?? '') ?>
                                                <?php if (!empty($doc['req_desc'])): ?>
                                                    <span class="tooltip-icon">
                                                        <i class="fa-regular fa-circle-question"></i>
                                                        <span class="tooltip-text">
                                                            <?= htmlspecialchars($doc['req_desc']) ?>
                                                        </span>
                                                    </span>
                                                <?php endif; ?>
                                            </h4>

                                            <div class="doc-status">
                                                <?= htmlspecialchars($doc['display_submission_status']) ?> •
                                                <?= htmlspecialchars($doc['display_review_status']) ?>
                                            </div>

                                            <?php if (!empty($doc['deadline'])): ?>
                                                <div class="doc-status doc-deadline">
                                                    Deadline: <?= formatDateTimeValue($doc['deadline']) ?>
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
                                                    <div class="doc-status">
                                                        Submitted: <?= formatDateTimeValue($doc['submitted_at']) ?>
                                                    </div>
                                                <?php endif; ?>

                                                <?php if (!empty($doc['video_documentation_link'])): ?>
                                                    <div class="doc-meta-line">
                                                        Video Link:
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
                                                    <div class="doc-status">No submission yet.</div>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <?php if ($has_upload && !empty($doc['original_file_name'])): ?>
                                                    <div class="doc-status">
                                                        File: <?= htmlspecialchars($doc['original_file_name']) ?>
                                                    </div>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>

                                        <div class="doc-actions">
                                            <?php if ($is_narrative_report): ?>
                                                <a href="narrative_report_submission.php?event_id=<?= (int) $event_id ?>"
                                                    class="btn-file">
                                                    <?= ($doc['submission_status'] ?? 'Pending') === 'Uploaded' ? 'View / Edit' : 'Open' ?>
                                                </a>
                                            <?php else: ?>
                                                <button type="button" class="btn-file" onclick="previewDocument(
                                                        '<?= htmlspecialchars($preview_url, ENT_QUOTES) ?>',
                                                        '<?= htmlspecialchars($doc['req_name'] . ($has_upload ? ' (Uploaded)' : ' Template'), ENT_QUOTES) ?>',
                                                        '<?= htmlspecialchars($no_template_msg, ENT_QUOTES) ?>'
                                                    )">
                                                    View
                                                </button>

                                                <?php if ($can_modify_documents && ($doc['submission_status'] ?? 'Pending') !== 'Uploaded'): ?>
                                                    <form action="create_requirement.php" method="POST"
                                                        enctype="multipart/form-data" class="upload-form">
                                                        <input type="hidden" name="event_req_id"
                                                            value="<?= (int) $doc['event_req_id'] ?>">
                                                        <label class="btn-file">
                                                            Upload
                                                            <input type="file" name="document" hidden required
                                                                onchange="this.form.submit()">
                                                        </label>
                                                    </form>
                                                <?php endif; ?>

                                                <?php if ($has_upload && $can_modify_documents): ?>
                                                    <form data-confirm="Remove uploaded document?" action="delete_requirement.php"
                                                        method="POST">
                                                        <input type="hidden" name="event_req_id"
                                                            value="<?= (int) $doc['event_req_id'] ?>">
                                                        <button type="submit" class="btn-file btn-danger">Remove</button>
                                                    </form>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
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

                <?php include 'assets/includes/footer.php' ?>
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

    <script src="../app/script/layout.js?v=1"></script>
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