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

$event_id = isset($_GET["event_id"]) ? (int) $_GET["event_id"] : 0;

if ($event_id <= 0) {
    popup_error("Invalid event.");
}

/* ================= HELPERS ================= */
function formatDateTimeValue(?string $value): string
{
    if (empty($value)) {
        return 'N/A';
    }

    $ts = strtotime($value);
    return $ts ? date('F j, Y g:i A', $ts) : 'N/A';
}

function normalizeStatusClass(string $status): string
{
    return strtolower(str_replace(' ', '-', $status));
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

/* ================= FETCH NARRATIVE REPORT ================= */
$sql = "
    SELECT
        e.event_id,
        e.event_name,
        e.event_status,
        e.admin_remarks,
        e.archived_at,

        u.user_name,
        u.user_email,
        u.org_body,

        er.event_req_id,
        er.submission_status,
        er.review_status,
        er.deadline,
        er.reviewed_at,
        er.reviewer_id,
        er.remarks,

        em.target_metric,
        em.actual_metric,

        nrd.narrative_report_id,
        nrd.narrative,
        nrd.video_documentation_link,
        nrd.submitted_at

    FROM events e
    INNER JOIN users u
        ON e.user_id = u.user_id
    INNER JOIN event_requirements er
        ON e.event_id = er.event_id
    INNER JOIN requirement_templates rt
        ON er.req_template_id = rt.req_template_id
    LEFT JOIN narrative_report_details nrd
        ON er.event_req_id = nrd.event_req_id
    LEFT JOIN event_metrics em
        ON e.event_id = em.event_id
    WHERE e.event_id = ?
      AND e.archived_at IS NULL
      AND rt.req_name = 'Narrative Report'
    LIMIT 1
";

$row = fetchOne($conn, $sql, "i", [$event_id]);

if (!$row) {
    popup_error("Narrative Report not found for this event.");
}

$metric_status = getMetricAchievementStatus(
    $row['target_metric'] ?? null,
    $row['actual_metric'] ?? null
);

$can_review = !in_array(($row['event_status'] ?? ''), ['Draft', 'Completed'], true)
    && (($row['submission_status'] ?? '') === 'Uploaded');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Narrative Report - HAUCREDIT</title>
    <link rel="stylesheet" href="assets/styles/layout.css">
    <link rel="stylesheet" href="assets/styles/view_event.css">
    <link rel="stylesheet" href="assets/styles/nr_styles.css">
</head>
<body>
    <div class="app">
        <div class="sidebar-overlay" id="sidebarOverlay" hidden></div>

        <?php include 'assets/includes/admin_nav.php'; ?>

        <main class="main">
            <header class="topbar">
                <button class="hamburger" id="menuBtn" type="button" aria-label="Open menu">☰</button>

                <div class="title-wrap">
                    <h1>Review Narrative Report</h1>
                    <p><?= htmlspecialchars($row['event_name'] ?? '') ?></p>
                </div>

                <div class="action-btns">
                    <a href="admin_manage_event.php?id=<?= (int) $event_id ?>" class="btn-secondary">Back to Event</a>
                </div>
            </header>

            <section class="content view-event-page">
                <div class="status-banner status-<?= htmlspecialchars(normalizeStatusClass($row['event_status'] ?? 'Pending Review')) ?>">
                    <div class="status-content">
                        <h3>Event Status: <?= htmlspecialchars($row['event_status'] ?? 'N/A') ?></h3>
                        <p>
                            Submitted by <strong><?= htmlspecialchars($row['user_name'] ?? 'Unknown User') ?></strong>
                            (<?= htmlspecialchars($row['org_body'] ?? 'No organization') ?>)
                            <?php if (!empty($row['admin_remarks'])): ?>
                                <br><strong>Event Remarks:</strong> <?= htmlspecialchars($row['admin_remarks']) ?>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>

                <div class="col-left">
                    <section class="detail-card">
                        <div class="card-header">
                            <h2><i class="fa-solid fa-user"></i> Submission Overview</h2>
                        </div>
                        <div class="card-body">
                            <div class="detail-grid">
                                <div class="detail-item">
                                    <label>Submitted By</label>
                                    <p><?= htmlspecialchars($row['user_name'] ?? 'N/A') ?></p>
                                </div>
                                <div class="detail-item">
                                    <label>Email</label>
                                    <p><?= htmlspecialchars($row['user_email'] ?? 'N/A') ?></p>
                                </div>
                                <div class="detail-item">
                                    <label>Organization</label>
                                    <p><?= htmlspecialchars($row['org_body'] ?? 'N/A') ?></p>
                                </div>
                                <div class="detail-item">
                                    <label>Submission Status</label>
                                    <p><?= htmlspecialchars($row['submission_status'] ?? 'Pending') ?></p>
                                </div>
                                <div class="detail-item">
                                    <label>Review Status</label>
                                    <p><?= htmlspecialchars($row['review_status'] ?? 'Not Reviewed') ?></p>
                                </div>
                                <div class="detail-item">
                                    <label>Deadline</label>
                                    <p><?= formatDateTimeValue($row['deadline'] ?? null) ?></p>
                                </div>
                                <div class="detail-item">
                                    <label>Submitted At</label>
                                    <p><?= formatDateTimeValue($row['submitted_at'] ?? null) ?></p>
                                </div>
                                <div class="detail-item">
                                    <label>Reviewed At</label>
                                    <p><?= formatDateTimeValue($row['reviewed_at'] ?? null) ?></p>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="detail-card">
                        <div class="card-header">
                            <h2><i class="fa-solid fa-file-lines"></i> Narrative Content</h2>
                        </div>
                        <div class="card-body">
                            <div class="detail-grid">
                                <div class="detail-item full-width">
                                    <label>Narrative Report</label>
                                    <p>
                                        <?php if (!empty($row['narrative'])): ?>
                                            <?= nl2br(htmlspecialchars($row['narrative'])) ?>
                                        <?php else: ?>
                                            No narrative submitted yet.
                                        <?php endif; ?>
                                    </p>
                                </div>

                                <div class="detail-item full-width">
                                    <label>Video Documentation Link</label>
                                    <p>
                                        <?php if (!empty($row['video_documentation_link'])): ?>
                                            <a href="<?= htmlspecialchars($row['video_documentation_link']) ?>" target="_blank" rel="noopener noreferrer">
                                                Open Link
                                            </a>
                                        <?php else: ?>
                                            No link submitted.
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="detail-card">
                        <div class="card-header">
                            <h2><i class="fa-solid fa-chart-column"></i> Metrics Review</h2>
                        </div>
                        <div class="card-body">
                            <div class="detail-grid">
                                <div class="detail-item">
                                    <label>Target Metric</label>
                                    <p><?= !empty($row['target_metric']) ? htmlspecialchars($row['target_metric']) : 'N/A' ?></p>
                                </div>
                                <div class="detail-item">
                                    <label>Actual Metric</label>
                                    <p><?= !empty($row['actual_metric']) ? htmlspecialchars($row['actual_metric']) : 'N/A' ?></p>
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
                            <h2><i class="fa-solid fa-comment-dots"></i> Requirement Remarks</h2>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($row['remarks'])): ?>
                                <div class="doc-remarks-box">
                                    <strong>Current Remarks</strong>
                                    <p><?= nl2br(htmlspecialchars($row['remarks'])) ?></p>
                                </div>
                            <?php else: ?>
                                <p>No remarks yet.</p>
                            <?php endif; ?>

                            <?php if ($can_review): ?>
                                <form action="admin_update_narrative_review.php" method="POST">
                                    <input type="hidden" name="event_id" value="<?= (int) $event_id ?>">
                                    <input type="hidden" name="event_req_id" value="<?= (int) $row['event_req_id'] ?>">

                                    <div class="field long-field">
                                        <label class="field-title">Admin Remarks</label>
                                        <textarea name="remarks" rows="4" placeholder="Write narrative report review remarks here..."><?= htmlspecialchars($row['remarks'] ?? '') ?></textarea>
                                    </div>

                                    <div class="step-actions" style="justify-content:flex-end; flex-wrap:wrap;">
                                        <button type="submit" name="action" value="save_remarks" class="btn-secondary">
                                            Save Remarks
                                        </button>
                                        <button type="submit" name="action" value="needs_revision" class="btn-primary btn-danger">
                                            Mark Needs Revision
                                        </button>
                                        <button type="submit" name="action" value="approve" class="btn-primary">
                                            Approve Narrative
                                        </button>
                                    </div>
                                </form>
                            <?php else: ?>
                                <p>This Narrative Report cannot be reviewed in the current event state.</p>
                            <?php endif; ?>
                        </div>
                    </section>
                </div>

                <div class="col-right">
                    <section class="detail-card">
                        <div class="card-header">
                            <h2><i class="fa-solid fa-circle-info"></i> Review Guidance</h2>
                        </div>
                        <div class="card-body">
                            <div class="tracker-list">
                                <div class="t-row">
                                    <span>Submission Status</span>
                                    <span class="t-score"><?= htmlspecialchars($row['submission_status'] ?? 'Pending') ?></span>
                                </div>
                                <div class="t-row">
                                    <span>Review Status</span>
                                    <span class="t-score"><?= htmlspecialchars($row['review_status'] ?? 'Not Reviewed') ?></span>
                                </div>
                                <div class="t-row">
                                    <span>Review Available</span>
                                    <span class="t-score"><?= $can_review ? 'Yes' : 'No' ?></span>
                                </div>
                            </div>

                            <div style="margin-top: 1rem;">
                                <p>
                                    Approving the Narrative Report keeps the requirement as approved.
                                    Once the event itself is already <strong>Approved</strong>, the admin may then mark the whole event as <strong>Completed</strong> from the event management page.
                                </p>
                            </div>
                        </div>
                    </section>
                </div>

                <?php include 'assets/includes/footer.php'; ?>
            </section>
        </main>
    </div>

    <script src="../app/script/layout.js?v=1"></script>
</body>
</html>