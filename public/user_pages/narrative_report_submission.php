<?php
session_start();
require_once __DIR__ . '/../../app/database.php';

requireLogin();

$user_id = (int) $_SESSION["user_id"];
$event_id = isset($_GET["event_id"]) ? (int) $_GET["event_id"] : 0;

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

function getNarrativeBanner(string $event_status, ?string $admin_remarks = null, string $review_status = 'Not Reviewed'): array
{
    $class = strtolower(str_replace(' ', '-', $event_status));
    $title = 'Narrative Report Status';
    $message = '';

    if ($review_status === 'Approved') {
        $message = "This Narrative Report has already been approved. Editing is no longer available.";
    } else {
        switch ($event_status) {
            case 'Approved':
                $message = "This event is approved. You may now submit the Narrative Report and post-event details.";
                break;

            case 'Needs Revision':
                $message = "This Narrative Report or related event submission needs revision. Please review the remarks and update your submission.";
                break;

            case 'Completed':
                $message = "This event is already completed. Narrative Report editing is no longer available.";
                break;

            case 'Pending Review':
                $message = "This event is still in the pre-event review stage. Narrative Report submission is not yet available.";
                break;

            case 'Draft':
                $message = "This event is still in draft status. Narrative Report submission is not yet available.";
                break;

            default:
                $message = "Narrative Report status is currently unavailable.";
                break;
        }
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

/* ================= FETCH NARRATIVE REPORT REQUIREMENT ================= */
$sql = "
    SELECT
        e.event_id,
        e.event_name,
        e.event_status,
        e.admin_remarks,
        e.archived_at,

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
    INNER JOIN event_requirements er
        ON e.event_id = er.event_id
    INNER JOIN requirement_templates rt
        ON er.req_template_id = rt.req_template_id
    LEFT JOIN narrative_report_details nrd
        ON er.event_req_id = nrd.event_req_id
    LEFT JOIN event_metrics em
        ON e.event_id = em.event_id
    WHERE e.event_id = ?
      AND e.user_id = ?
      AND e.archived_at IS NULL
      AND rt.req_name = 'Narrative Report'
    LIMIT 1
";

$row = fetchOne($conn, $sql, "ii", [$event_id, $user_id]);

if (!$row) {
    popup_error("Narrative Report requirement not found for this event.");
}

$event_req_id = (int) $row["event_req_id"];
$event_name = $row["event_name"] ?? "";
$event_status = $row["event_status"] ?? "";
$narrative = $row["narrative"] ?? "";
$video_documentation_link = $row["video_documentation_link"] ?? "";
$target_metric = $row["target_metric"] ?? "";
$actual_metric = $row["actual_metric"] ?? "";
$is_archived = !empty($row['archived_at']);

/* ================= ENFORCE EDIT RULES ================= */
$allowed_narrative_statuses = ['Approved', 'Needs Revision'];

$narrative_review_status = $row['review_status'] ?? 'Not Reviewed';
$narrative_submission_status = $row['submission_status'] ?? 'Pending';

$can_edit_narrative = (
    !$is_archived
    && in_array($event_status, $allowed_narrative_statuses, true)
    && $narrative_review_status !== 'Approved'
);

$status_banner = getNarrativeBanner(
    $event_status,
    $row['admin_remarks'] ?? null,
    $narrative_review_status
);

/* ================= HANDLE SUBMISSION ================= */
if (isset($_POST["save_narrative_report"])) {
    if (!$can_edit_narrative) {
        popup_error("Narrative Report submission is not allowed while the event status is: " . $event_status);
    }

    $narrative = trim($_POST["narrative"] ?? "");
    $video_documentation_link = trim($_POST["video_documentation_link"] ?? "");
    $actual_metric = trim($_POST["actual_metric"] ?? "");

    if ($narrative === "" && $video_documentation_link === "" && $actual_metric === "") {
        popup_error("Please provide at least one of the following: narrative report, video documentation link, or actual metric.");
    }

    if ($video_documentation_link !== "" && !filter_var($video_documentation_link, FILTER_VALIDATE_URL)) {
        popup_error("Please enter a valid video documentation link.");
    }

    if ($actual_metric !== "" && !preg_match('/^(100|[1-9]?\d)\%\s+.+$/', $actual_metric)) {
        popup_error("Actual Metric must follow this format: 75% Satisfaction Rating");
    }

    try {
        $conn->begin_transaction();

        $upsertNarrativeSql = "
            INSERT INTO narrative_report_details (
                event_req_id,
                narrative,
                video_documentation_link,
                submitted_at
            ) VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                narrative = VALUES(narrative),
                video_documentation_link = VALUES(video_documentation_link),
                submitted_at = NOW(),
                updated_at = CURRENT_TIMESTAMP
        ";

        execQuery(
            $conn,
            $upsertNarrativeSql,
            "iss",
            [$event_req_id, $narrative, $video_documentation_link]
        );

        $metricExists = fetchOne(
            $conn,
            "
            SELECT event_id
            FROM event_metrics
            WHERE event_id = ?
            LIMIT 1
            ",
            "i",
            [$event_id]
        );

        if ($metricExists) {
            execQuery(
                $conn,
                "
                UPDATE event_metrics
                SET actual_metric = ?
                WHERE event_id = ?
                ",
                "si",
                [$actual_metric !== '' ? $actual_metric : null, $event_id]
            );
        } else {
            execQuery(
                $conn,
                "
                INSERT INTO event_metrics (event_id, target_metric, actual_metric)
                VALUES (?, ?, ?)
                ",
                "iss",
                [$event_id, $target_metric, $actual_metric !== '' ? $actual_metric : null]
            );
        }

        /* Reset requirement review cycle */
        execQuery(
            $conn,
            "
            UPDATE event_requirements
            SET
                submission_status = 'Uploaded',
                review_status = 'Not Reviewed',
                reviewed_at = NULL,
                reviewer_id = NULL,
                remarks = NULL,
                updated_at = CURRENT_TIMESTAMP
            WHERE event_req_id = ?
            ",
            "i",
            [$event_req_id]
        );

        $uploadedRes = fetchOne(
            $conn,
            "
            SELECT COUNT(*) AS uploaded_total
            FROM event_requirements
            WHERE event_id = ?
              AND submission_status = 'Uploaded'
            ",
            "i",
            [$event_id]
        );
        $docs_uploaded = (int) ($uploadedRes["uploaded_total"] ?? 0);

        $totalRes = fetchOne(
            $conn,
            "
            SELECT COUNT(*) AS total_docs
            FROM event_requirements
            WHERE event_id = ?
            ",
            "i",
            [$event_id]
        );
        $docs_total = (int) ($totalRes["total_docs"] ?? 0);

        execQuery(
            $conn,
            "
            UPDATE events
            SET docs_uploaded = ?, docs_total = ?, updated_at = CURRENT_TIMESTAMP
            WHERE event_id = ?
            ",
            "iii",
            [$docs_uploaded, $docs_total, $event_id]
        );

        $conn->commit();

        header("Location: view_event.php?id=" . $event_id);
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        popup_error("Failed to save narrative report: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Narrative Report Submission</title>
    <link rel="stylesheet" href="<?= PUBLIC_URL ?>assets/styles/layout.css" />
    <link rel="stylesheet" href="<?= PUBLIC_URL ?>assets/styles/ce_styles.css">
    <link rel="stylesheet" href="<?= PUBLIC_URL ?>assets/styles/view_event.css">
    <link rel="stylesheet" href="<?= PUBLIC_URL ?>assets/styles/nr_styles.css">
</head>

<body>
    <div class="app">
        <div class="sidebar-overlay" id="sidebarOverlay" hidden></div>

        <?php include PUBLIC_PATH . 'assets/includes/general_nav.php'; ?>

        <main class="main">
            <header class="topbar ce-topbar">
                <button class="hamburger" id="menuBtn" type="button" aria-label="Open menu">☰</button>

                <div class="title-wrap">
                    <h1>Narrative Report Submission</h1>
                    <p><?= htmlspecialchars($event_name) ?></p>
                </div>
            </header>

            <section class="content">
                <div class="action-btns" style="margin-bottom: 1rem;">
                    <a href="view_event.php?id=<?= (int) $event_id ?>" class="btn-secondary">Back</a>
                </div>

                <div class="status-banner status-<?= htmlspecialchars($narrative_review_status) ?>"
                    style="flex-direction: column;">
                    <div class="status-content">
                        <h3><?= htmlspecialchars($status_banner['title']) ?></h3>
                        <p><?= $status_banner['message'] ?></p>
                    </div>
                </div>

                <div class="card-body">
                    <div class="detail-grid">
                        <div class="detail-item">
                            <label>Submission Status</label>
                            <p><?= htmlspecialchars(formatSubmissionStatus($row['submission_status'] ?? 'Pending')) ?>
                            </p>
                        </div>
                        <div class="detail-item">
                            <label>Review Status</label>
                            <p><?= htmlspecialchars(formatReviewStatus($row['review_status'] ?? 'Not Reviewed')) ?></p>
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
                            <label>Target Metric</label>
                            <p><?= !empty($target_metric) ? htmlspecialchars($target_metric) : 'N/A' ?></p>
                        </div>
                        <div class="detail-item">
                            <label>Actual Metric</label>
                            <p><?= !empty($actual_metric) ? htmlspecialchars($actual_metric) : 'N/A' ?></p>
                        </div>

                        <?php if (!empty($row['remarks'])): ?>
                            <div class="detail-item full-width">
                                <label>Admin Remarks</label>
                                <p><?= nl2br(htmlspecialchars($row['remarks'])) ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <form method="POST" class="event-form">
                    <div class="acc open">
                        <div class="acc-head">
                            <span class="acc-left">
                                <span class="acc-text">
                                    <span class="acc-title">Narrative Report</span>
                                    <span class="acc-sub">Enter the written report, actual metric, and optional video
                                        link</span>
                                </span>
                            </span>
                        </div>

                        <div class="acc-body">
                            <div class="field long-field">
                                <label for="narrative" class="field-title">Narrative Report</label>
                                <small class="hint">
                                    Summarize what happened during the event, including key activities, outcomes, and
                                    highlights.
                                </small>
                                <textarea name="narrative" id="narrative" rows="12" <?= $can_edit_narrative ? '' : 'readonly' ?>>
                                <?= htmlspecialchars($narrative) ?> </textarea>
                            </div>

                            <div class="field long-field">
                                <label for="actual_metric" class="field-title">Actual Metric</label>
                                <small class="hint">
                                    <span class="hint-important">Format:</span> percentage followed by a description.
                                    Example: 82% Satisfaction Rating
                                </small>
                                <input type="text" name="actual_metric" id="actual_metric"
                                    value="<?= htmlspecialchars($actual_metric) ?>"
                                    placeholder="82% Satisfaction Rating" <?= $can_edit_narrative ? '' : 'readonly' ?>>
                            </div>

                            <div class="field long-field">
                                <label for="video_documentation_link" class="field-title">Video Documentation
                                    Link</label>
                                <small class="hint">
                                    Paste a valid link to Google Drive, YouTube, Facebook, or another approved source.
                                </small>
                                <input type="url" name="video_documentation_link" id="video_documentation_link"
                                    value="<?= htmlspecialchars($video_documentation_link) ?>" placeholder="https://..."
                                    <?= $can_edit_narrative ? '' : 'readonly' ?>>
                            </div>
                        </div>
                    </div>

                    <div class="step-actions">
                        <a href="view_event.php?id=<?= (int) $event_id ?>" class="btn-secondary">Cancel</a>

                        <?php if ($can_edit_narrative): ?>
                            <button type="submit" name="save_narrative_report" class="btn-primary">
                                Save Narrative Report
                            </button>
                        <?php endif; ?>
                    </div>
                </form>

                <?php include PUBLIC_PATH . 'assets/includes/footer.php'; ?>
            </section>
        </main>
    </div>

    <script src="<?= APP_URL ?>script/layout.js?v=1"></script>
</body>

</html>