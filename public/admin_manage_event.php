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

$admin_user_id = (int) $_SESSION["user_id"];
$event_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($event_id <= 0) {
    popup_error("Invalid event ID.");
}

/* ================= HELPERS ================= */
function normalizeStatusClass(string $status): string
{
    return strtolower(str_replace(' ', '-', $status));
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

function canApproveEvent(string $event_status): bool
{
    return in_array($event_status, ['Pending Review', 'Needs Revision'], true);
}

function canReturnEventForRevision(string $event_status): bool
{
    return in_array($event_status, ['Pending Review', 'Approved', 'Needs Revision'], true);
}

function canCompleteEvent(string $event_status, bool $narrativeApproved): bool
{
    return $event_status === 'Approved' && $narrativeApproved;
}

/* ================= FETCH EVENT DATA ================= */
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

$event = fetchOne($conn, $fetchEventSql, "i", [$event_id]);

if (!$event) {
    popup_error("Event not found.");
}

/* ================= FETCH REQUIREMENTS ================= */
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

$requirements = fetchAll($conn, $fetchRequirementsSql, "i", [$event_id]);

/* ================= EVENT LOGIC FLAGS ================= */
$event_status = $event['event_status'] ?? 'Pending Review';

$total_docs = (int) ($event['docs_total'] ?? 0);
$uploaded_docs = (int) ($event['docs_uploaded'] ?? 0);
$pending_docs = max(0, $total_docs - $uploaded_docs);
$progress_percentage = $total_docs > 0 ? round(($uploaded_docs / $total_docs) * 100) : 0;

$pct = max(0, min(100, (int) $progress_percentage));
$hue = ($pct / 100) * 120;
$progress_color = "hsl($hue, 70%, 45%)";

$organizing_body_display = $event['organizing_body'] ?? '';
$decoded_orgs = json_decode($organizing_body_display, true);
if (is_array($decoded_orgs)) {
    $organizing_body_display = implode(", ", $decoded_orgs);
}

$metric_status = getMetricAchievementStatus(
    $event['target_metric'] ?? null,
    $event['actual_metric'] ?? null
);

$narrativeApproved = false;
$allPreEventReviewedOkay = true;

foreach ($requirements as $doc) {
    $isNarrative = (($doc['req_name'] ?? '') === 'Narrative Report');

    if ($isNarrative) {
        if (($doc['review_status'] ?? '') === 'Approved' && ($doc['submission_status'] ?? '') === 'Uploaded') {
            $narrativeApproved = true;
        }
        continue;
    }

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
    <link rel="stylesheet" href="assets/styles/layout.css" />
    <link rel="stylesheet" href="assets/styles/view_event.css" />
</head>

<body>
    <div class="app">
        <div class="sidebar-overlay" id="sidebarOverlay" hidden></div>

        <?php include 'assets/includes/admin_nav.php'; ?>

        <main class="main">
            <header class="topbar">
                <button class="hamburger" id="menuBtn" type="button" aria-label="Open menu">☰</button>

                <div class="title-wrap">
                    <h1><?= htmlspecialchars($event['event_name']) ?></h1>
                    <p>Admin Event Management</p>
                </div>

                <div class="action-btns">
                    <a href="admin_events.php" class="btn-secondary">Back to Events</a>
                </div>
            </header>

            <section class="content view-event-page">
                <div class="status-banner status-<?= htmlspecialchars(normalizeStatusClass($event_status)) ?>">
                    <div class="status-content">
                        <h3>Event Status: <?= htmlspecialchars($event_status) ?></h3>
                        <p>
                            Submitted by <strong><?= htmlspecialchars($event['user_name'] ?? 'Unknown User') ?></strong>
                            (<?= htmlspecialchars($event['org_body'] ?? 'No organization') ?>)
                            <?php if (!empty($event['admin_remarks'])): ?>
                                <br><strong>Current Admin Remarks:</strong>
                                <?= htmlspecialchars($event['admin_remarks']) ?>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>

                <div class="col-left">
                    <section class="detail-card">
                        <div class="card-header">
                            <h2><i class="fa-solid fa-user"></i> Submission Details</h2>
                            <span
                                class="badge badge-primary"><?= htmlspecialchars($event['activity_type'] ?? 'N/A') ?></span>
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
                            </div>
                        </div>
                    </section>

                    <section class="detail-card">
                        <div class="card-header">
                            <h2><i class="fa-solid fa-file-circle-question"></i> Requirement Review</h2>
                            <span class="badge badge-success"><?= $uploaded_docs ?>/<?= $total_docs ?> Uploaded</span>
                        </div>

                        <div class="card-body">
                            <div class="doc-checklist">
                                <?php foreach ($requirements as $doc): ?>
                                    <?php
                                    $is_narrative = (($doc['req_name'] ?? '') === 'Narrative Report');
                                    $has_narrative_content = !empty($doc['narrative']) || !empty($doc['video_documentation_link']);
                                    [$preview_url, $no_template_msg, $has_upload] = buildPreviewUrl($doc);
                                    $can_review = canReviewRequirement($doc, $event_status);
                                    ?>

                                    <div
                                        class="doc-item status-<?= htmlspecialchars(normalizeStatusClass($doc['submission_status'] ?? 'Pending')) ?>">
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
                                                <?= htmlspecialchars($doc['submission_status'] ?? 'Pending') ?> •
                                                <?= htmlspecialchars($doc['review_status'] ?? 'Not Reviewed') ?>
                                            </div>

                                            <?php if (!empty($doc['deadline'])): ?>
                                                <div class="doc-status doc-deadline">
                                                    Deadline: <?= formatDateTimeValue($doc['deadline']) ?>
                                                </div>
                                            <?php endif; ?>

                                            <?php if (!empty($doc['reviewed_at'])): ?>
                                                <div class="doc-status">
                                                    Reviewed: <?= formatDateTimeValue($doc['reviewed_at']) ?>
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

                                                <?php if (!$has_narrative_content): ?>
                                                    <div class="doc-status">No Narrative Report content submitted yet.</div>
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
                                            <?php if ($is_narrative): ?>
                                                <a href="admin_review_narrative.php?event_id=<?= (int) $event_id ?>"
                                                    class="btn-file">
                                                    Review Narrative
                                                </a>
                                            <?php else: ?>
                                                <button type="button" class="btn-file" onclick="previewDocument(
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
                                                    <input type="hidden" name="event_req_id"
                                                        value="<?= (int) $doc['event_req_id'] ?>">
                                                    <input type="hidden" name="action" value="approve">
                                                    <button type="submit" class="btn-file">Approve</button>
                                                </form>

                                                <form action="admin_update_requirement.php" method="POST" class="inline-form">
                                                    <input type="hidden" name="event_id" value="<?= (int) $event_id ?>">
                                                    <input type="hidden" name="event_req_id"
                                                        value="<?= (int) $doc['event_req_id'] ?>">
                                                    <input type="hidden" name="action" value="needs_revision">
                                                    <button type="submit" class="btn-file btn-danger">Needs Revision</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <?php if ($can_review): ?>
                                        <form action="admin_update_requirement.php" method="POST" class="detail-card"
                                            style="margin-top: .75rem;">
                                            <input type="hidden" name="event_id" value="<?= (int) $event_id ?>">
                                            <input type="hidden" name="event_req_id" value="<?= (int) $doc['event_req_id'] ?>">

                                            <div class="card-body">
                                                <div class="field long-field">
                                                    <label class="field-title">Admin Remarks for
                                                        <?= htmlspecialchars($doc['req_name']) ?></label>
                                                    <textarea name="remarks" rows="3"
                                                        placeholder="Write remarks or requested changes here..."><?= htmlspecialchars($doc['remarks'] ?? '') ?></textarea>
                                                </div>

                                                <div class="step-actions" style="justify-content:flex-end;">
                                                    <button type="submit" name="action" value="save_remarks"
                                                        class="btn-secondary">Save Remarks</button>
                                                    <button type="submit" name="action" value="needs_revision"
                                                        class="btn-primary btn-danger">Save & Mark Needs Revision</button>
                                                </div>
                                            </div>
                                        </form>
                                    <?php endif; ?>
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
                                <span class="ring-number"><?= $progress_percentage ?>%</span>
                            </div>
                        </div>

                        <div class="tracker-list">
                            <div class="t-row"><span>Total Documents</span><span
                                    class="t-score"><?= $total_docs ?></span></div>
                            <div class="t-row"><span>Uploaded</span><span class="t-score"><?= $uploaded_docs ?></span>
                            </div>
                            <div class="t-row"><span>Pending</span><span class="t-score"><?= $pending_docs ?></span>
                            </div>
                            <div class="t-row"><span>Completion</span><span
                                    class="t-score"><?= $progress_percentage ?>%</span></div>
                        </div>
                    </aside>

                    <section class="detail-card" style="margin-top: 1rem;">
                        <div class="card-header">
                            <h2><i class="fa-solid fa-gavel"></i> Event Decision</h2>
                        </div>

                        <div class="card-body">
                            <form action="admin_update_event_status.php" method="POST">
                                <input type="hidden" name="event_id" value="<?= (int) $event_id ?>">

                                <div class="field long-field">
                                    <label class="field-title">Event-Level Admin Remarks</label>
                                    <textarea name="admin_remarks" rows="4"
                                        placeholder="Write final remarks for this event..."><?= htmlspecialchars($event['admin_remarks'] ?? '') ?></textarea>
                                </div>

                                <div class="step-actions" style="justify-content:flex-end; flex-wrap:wrap;">
                                    <button type="submit" name="action" value="save_remarks" class="btn-secondary">
                                        Save Remarks
                                    </button>

                                    <?php if (canReturnEventForRevision($event_status)): ?>
                                        <button type="submit" name="action" value="needs_revision"
                                            class="btn-primary btn-danger">
                                            Mark Event Needs Revision
                                        </button>
                                    <?php endif; ?>

                                    <?php if (canApproveEvent($event_status)): ?>
                                        <button type="submit" name="action" value="approve" class="btn-primary">
                                            Approve Event
                                        </button>
                                    <?php endif; ?>

                                    <?php if (canCompleteEvent($event_status, $narrativeApproved)): ?>
                                        <button type="submit" name="action" value="complete" class="btn-primary">
                                            Mark as Completed
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </form>

                            <div class="tracker-list" style="margin-top: 1rem;">
                                <div class="t-row">
                                    <span>Pre-event Requirements</span>
                                    <span
                                        class="t-score"><?= $allPreEventReviewedOkay ? 'Ready' : 'Incomplete' ?></span>
                                </div>
                                <div class="t-row">
                                    <span>Narrative Report Approved</span>
                                    <span class="t-score"><?= $narrativeApproved ? 'Yes' : 'No' ?></span>
                                </div>
                                <div class="t-row">
                                    <span>Current Status</span>
                                    <span class="t-score"><?= htmlspecialchars($event_status) ?></span>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>

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