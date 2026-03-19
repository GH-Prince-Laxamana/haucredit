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

$user_id = (int) $_SESSION["user_id"];
$event_id = isset($_GET["event_id"]) ? (int) $_GET["event_id"] : 0;

if ($event_id <= 0) {
    popup_error("Invalid event.");
}

/* ================= HELPERS ================= */
function formatSubmissionStatus(string $status): string
{
    return match ($status) {
        'uploaded' => 'Submitted',
        'pending' => 'Pending',
        default => ucwords(str_replace('_', ' ', $status))
    };
}

function formatReviewStatus(string $status): string
{
    return match ($status) {
        'not_reviewed' => 'Not Reviewed',
        'needs_revision' => 'Needs Revision',
        default => ucwords(str_replace('_', ' ', $status))
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

/* ================= FETCH NARRATIVE REPORT REQUIREMENT ================= */
$sql = "
    SELECT
        e.event_id,
        e.event_name,
        e.event_status,
        e.archived_at,

        er.event_req_id,
        er.submission_status,
        er.review_status,
        er.deadline,
        er.reviewed_at,
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
$narrative = $row["narrative"] ?? "";
$video_documentation_link = $row["video_documentation_link"] ?? "";
$target_metric = $row["target_metric"] ?? "";
$actual_metric = $row["actual_metric"] ?? "";

/* ================= HANDLE SUBMISSION ================= */
if (isset($_POST["save_narrative_report"])) {
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

        $checkMetricSql = "
            SELECT event_id
            FROM event_metrics
            WHERE event_id = ?
            LIMIT 1
        ";
        $metricExists = fetchOne($conn, $checkMetricSql, "i", [$event_id]);

        if ($metricExists) {
            $updateMetricSql = "
                UPDATE event_metrics
                SET actual_metric = ?
                WHERE event_id = ?
            ";
            execQuery($conn, $updateMetricSql, "si", [$actual_metric !== '' ? $actual_metric : null, $event_id]);
        } else {
            $insertMetricSql = "
                INSERT INTO event_metrics (event_id, target_metric, actual_metric)
                VALUES (?, ?, ?)
            ";
            execQuery($conn, $insertMetricSql, "iss", [$event_id, $target_metric, $actual_metric !== '' ? $actual_metric : null]);
        }

        $updateRequirementSql = "
            UPDATE event_requirements
            SET
                submission_status = 'uploaded',
                updated_at = CURRENT_TIMESTAMP
            WHERE event_req_id = ?
        ";

        execQuery($conn, $updateRequirementSql, "i", [$event_req_id]);

        $countUploadedSql = "
            SELECT COUNT(*) AS uploaded_total
            FROM event_requirements
            WHERE event_id = ?
              AND submission_status = 'uploaded'
        ";
        $uploadedRes = fetchOne($conn, $countUploadedSql, "i", [$event_id]);
        $docs_uploaded = (int) ($uploadedRes["uploaded_total"] ?? 0);

        $countTotalSql = "
            SELECT COUNT(*) AS total_docs
            FROM event_requirements
            WHERE event_id = ?
        ";
        $totalRes = fetchOne($conn, $countTotalSql, "i", [$event_id]);
        $docs_total = (int) ($totalRes["total_docs"] ?? 0);

        $updateEventCountsSql = "
            UPDATE events
            SET docs_uploaded = ?, docs_total = ?, updated_at = CURRENT_TIMESTAMP
            WHERE event_id = ?
        ";
        execQuery($conn, $updateEventCountsSql, "iii", [$docs_uploaded, $docs_total, $event_id]);

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
    <link rel="stylesheet" href="assets/styles/layout.css">
    <link rel="stylesheet" href="assets/styles/ce_styles.css">
    <link rel="stylesheet" href="assets/styles/view_event.css">
    <link rel="stylesheet" href="assets/styles/nr_styles.css">
</head>

<body>
    <div class="app">
        <div class="sidebar-overlay" id="sidebarOverlay" hidden></div>

        <?php include 'assets/includes/general_nav.php'; ?>

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

                <div class="status-banner">
                    <div class="status-content">
                        <h3>Status Overview</h3>
                    </div>
                    <div class="card-body">
                        <div class="detail-grid">
                            <div class="detail-item">
                                <label>Submission Status</label>
                                <p><?= htmlspecialchars(formatSubmissionStatus($row['submission_status'] ?? 'pending')) ?>
                                </p>
                            </div>
                            <div class="detail-item">
                                <label>Review Status</label>
                                <p><?= htmlspecialchars(formatReviewStatus($row['review_status'] ?? 'not_reviewed')) ?>
                                </p>
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
                                <textarea name="narrative" id="narrative"
                                    rows="12"><?= htmlspecialchars($narrative) ?></textarea>
                            </div>

                            <div class="field long-field">
                                <label for="actual_metric" class="field-title">Actual Metric</label>
                                <small class="hint">
                                    <span class="hint-important">Format:</span> percentage followed by a description.
                                    Example: 82% Satisfaction Rating
                                </small>
                                <input type="text" name="actual_metric" id="actual_metric"
                                    value="<?= htmlspecialchars($actual_metric) ?>"
                                    placeholder="82% Satisfaction Rating">
                            </div>

                            <div class="field long-field">
                                <label for="video_documentation_link" class="field-title">Video Documentation
                                    Link</label>
                                <small class="hint">
                                    Paste a valid link to Google Drive, YouTube, Facebook, or another approved source.
                                </small>
                                <input type="url" name="video_documentation_link" id="video_documentation_link"
                                    value="<?= htmlspecialchars($video_documentation_link) ?>"
                                    placeholder="https://...">
                            </div>
                        </div>
                    </div>

                    <div class="step-actions">
                        <a href="view_event.php?id=<?= (int) $event_id ?>" class="btn-secondary">Cancel</a>
                        <button type="submit" name="save_narrative_report" class="btn-primary">
                            Save Narrative Report
                        </button>
                    </div>
                </form>

                <?php include 'assets/includes/footer.php'; ?>
            </section>
        </main>
    </div>

    <script src="../app/script/layout.js?v=1"></script>
</body>

</html>