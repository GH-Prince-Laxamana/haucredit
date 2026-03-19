<?php
session_start();
require_once "../app/database.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit();
}

$user_id = (int) $_SESSION["user_id"];
$username = htmlspecialchars($_SESSION["username"], ENT_QUOTES, "UTF-8");

$event_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($event_id <= 0) {
    popup_error("Invalid event ID.");
}

/* ================= EVENT DATA FETCH ================= */
$stmt = $conn->prepare("
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

        et.background,
        et.activity_type,
        et.series,

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
");
$stmt->bind_param("ii", $event_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$event = $result->fetch_assoc();

if (!$event) {
    popup_error("Event not found or you don't have permission to view it.");
}

$is_archived = !empty($event['archived_at']);

$days_remaining = null;
if ($is_archived) {
    $archived_time = strtotime($event['archived_at']);
    $expiry_time = $archived_time + (30 * 24 * 60 * 60);
    $days_remaining = ceil(($expiry_time - time()) / 86400);
}

/* ================= REQUIRED DOCUMENTS FETCH ================= */
$stmt_docs = $conn->prepare("
    SELECT
        er.event_req_id,
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
        rf.uploaded_at

    FROM event_requirements er
    INNER JOIN requirement_templates rt
        ON er.req_template_id = rt.req_template_id
    LEFT JOIN requirement_files rf
        ON er.event_req_id = rf.event_req_id
       AND rf.is_current = 1
    WHERE er.event_id = ?
    ORDER BY er.created_at ASC, rt.req_name ASC
");
$stmt_docs->bind_param("i", $event_id);
$stmt_docs->execute();
$res_docs = $stmt_docs->get_result();

$required_docs = [];
while ($row = $res_docs->fetch_assoc()) {
    $required_docs[] = $row;
}

$required_docs = array_map(function ($doc) {
    $doc['display_status'] = ($doc['submission_status'] === 'uploaded') ? 'Uploaded' : 'Pending';
    return $doc;
}, $required_docs);

/* ================= PROGRESS CALCULATION ================= */
$total_docs = count($required_docs);
$uploaded_docs = count(array_filter($required_docs, fn($doc) => $doc['submission_status'] === 'uploaded'));
$pending_docs = $total_docs - $uploaded_docs;
$progress_percentage = $total_docs ? round(($uploaded_docs / $total_docs) * 100) : 0;

$pct = max(0, min(100, (int) $progress_percentage));
$hue = ($pct / 100) * 120;
$progress_color = "hsl($hue, 70%, 45%)";

/* ================= DOCUMENT MESSAGES ================= */
$doc_messages = [
    'Planned Budget' => 'Budget template is not available. Please contact the organizer.',
    'List of Participants' => 'No participant list template yet.',
    'Student Organization Intake Form (OCES Annex A Form)' => 'Form will be provided upon request.'
];

/* ================= ORGANIZING BODY FORMAT ================= */
$organizing_body_display = $event['organizing_body'] ?? '';
$decoded_orgs = json_decode($organizing_body_display, true);
if (is_array($decoded_orgs)) {
    $organizing_body_display = implode(", ", $decoded_orgs);
}
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
                            <button type="submit" class="btn-primary btn-restore">
                                Restore Event
                            </button>
                        <?php else: ?>
                            <input type="hidden" name="action" value="archive">
                            <button type="submit" class="btn-primary btn-danger">
                                Archive Event
                            </button>
                        <?php endif; ?>
                    </form>

                    <?php if (!$is_archived): ?>
                        <a href="create_event.php?id=<?= (int) $event['event_id'] ?>" class="btn-primary">
                            Edit Event
                        </a>
                    <?php endif; ?>
                </div>
            </header>

            <section class="content view-event-page">
                <div class="action-btns">
                    <button type="button" class="btn-secondary" onclick="history.back()">
                        Back
                    </button>
                </div>

                <div class="status-banner status-<?= strtolower(str_replace(' ', '-', $event['event_status'])) ?>">
                    <div class="status-content">
                        <?php if (!$is_archived): ?>
                            <h3>Event Status: <?= htmlspecialchars($event['event_status']) ?></h3>
                            <p>
                                Your event is currently under review by the Office of Student Affairs.
                                <?php if (!empty($event['admin_remarks'])): ?>
                                    <br><strong>Admin remarks:</strong> <?= htmlspecialchars($event['admin_remarks']) ?>
                                <?php endif; ?>
                            </p>
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
                                    <p>
                                        <?= !empty($event['start_datetime']) ? date('F j, Y g:i A', strtotime($event['start_datetime'])) : 'N/A' ?>
                                    </p>
                                </div>
                                <div class="detail-item">
                                    <label>End Date & Time</label>
                                    <p>
                                        <?= !empty($event['end_datetime']) ? date('F j, Y g:i A', strtotime($event['end_datetime'])) : 'N/A' ?>
                                    </p>
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

                                <?php foreach ($required_docs as $doc):
                                    $has_upload = !empty($doc['file_path']);
                                    $display_status = $doc['display_status'];

                                    $preview_url = '';
                                    $no_template_msg = '';

                                    if ($has_upload) {
                                        $preview_url = '../' . ltrim($doc['file_path'], '/');
                                    } elseif (!empty($doc['template_url'])) {
                                        $preview_url = $doc['template_url'];
                                    } else {
                                        $no_template_msg = $doc_messages[$doc['req_name']] ?? 'No template available for this document.';
                                    }
                                    ?>
                                    <div class="doc-item status-<?= strtolower($display_status) ?>">

                                        <div class="doc-checkbox">
                                            <?php if ($display_status === 'Uploaded'): ?>
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

                                            <span class="doc-status"><?= htmlspecialchars($display_status) ?></span>

                                            <?php if (!empty($doc['review_status'])): ?>
                                                <div class="doc-status">
                                                   <?= htmlspecialchars(ucwords(str_replace('_', ' ', $doc['review_status']))) ?>
                                                </div>
                                            <?php endif; ?>

                                            <?php if (!empty($doc['deadline'])): ?>
                                                <div class="doc-deadline">
                                                    Deadline: <?= date('F j, Y g:i A', strtotime($doc['deadline'])) ?>
                                                </div>
                                            <?php endif; ?>

                                            <?php if (!empty($doc['remarks'])): ?>
                                                <div class="doc-remarks">
                                                    Remarks: <?= htmlspecialchars($doc['remarks']) ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <div class="doc-actions">
                                            <button type="button" class="btn-file" onclick="previewDocument(
                                                    '<?= htmlspecialchars($preview_url, ENT_QUOTES) ?>',
                                                    '<?= htmlspecialchars($doc['req_name'] . ($has_upload ? ' (Uploaded)' : ' Template'), ENT_QUOTES) ?>',
                                                    '<?= htmlspecialchars($no_template_msg, ENT_QUOTES) ?>'
                                                )">
                                                View
                                            </button>

                                            <?php if (!$is_archived && $display_status !== 'Uploaded'): ?>
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

                                            <?php if ($has_upload && !$is_archived): ?>
                                                <form data-confirm="Remove uploaded document?" action="delete_requirement.php"
                                                    method="POST">
                                                    <input type="hidden" name="event_req_id"
                                                        value="<?= (int) $doc['event_req_id'] ?>">
                                                    <button type="submit" class="btn-file btn-danger">Remove</button>
                                                </form>
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
            </div>
        </div>
    </div>

    <script src="../app/script/layout.js?v=1"></script>
    <script>
        function previewDocument(url, name, noTemplateMsg = '') {
            const modal = document.getElementById('docPreviewModal');
            const frame = document.getElementById('docFrame');
            const title = document.getElementById('modalTitle');

            title.textContent = name;

            if (!url || noTemplateMsg) {
                frame.style.display = 'none';

                let msgEl = document.getElementById('modalMessage');
                if (!msgEl) {
                    msgEl = document.createElement('p');
                    msgEl.id = 'modalMessage';
                    msgEl.style.padding = '1rem';
                    msgEl.style.textAlign = 'center';
                    msgEl.style.color = '#555';
                    document.querySelector('#docPreviewModal .modal-body').appendChild(msgEl);
                }
                msgEl.textContent = noTemplateMsg;
            } else {
                let msgEl = document.getElementById('modalMessage');
                if (msgEl) msgEl.textContent = '';

                frame.style.display = 'block';
                frame.src = url;
            }

            modal.classList.add("active");
        }

        function closePreview() {
            const modal = document.getElementById('docPreviewModal');
            const frame = document.getElementById('docFrame');

            modal.classList.remove('active');
            frame.src = "";
        }

        document.addEventListener("keydown", function (e) {
            if (e.key === "Escape") {
                closePreview();
            }
        });
    </script>
</body>

</html>