<?php
session_start();
require_once "../app/database.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION["user_id"];
$username = htmlspecialchars($_SESSION["username"], ENT_QUOTES, "UTF-8");

// Get event ID from query string
$event_id = $_GET['id'] ?? null;

if (!$event_id) {
    die("Invalid event ID.");
}

// Fetch the event info
$stmt = $conn->prepare("
    SELECT *
    FROM events
    WHERE event_id = ? AND user_id = ? AND archived_at IS NULL
    LIMIT 1
");
$stmt->bind_param("ii", $event_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$event = $result->fetch_assoc();

if (!$event) {
    die("Event not found or you don't have permission to view it.");
}

// Fetch required documents for this event
$stmt_docs = $conn->prepare("
    SELECT req_id, req_name, req_desc, file_path, template_url, doc_status
    FROM requirements
    WHERE event_id = ?
    ORDER BY created_at ASC
");
$stmt_docs->bind_param("i", $event_id);
$stmt_docs->execute();
$res_docs = $stmt_docs->get_result();

while ($row = $res_docs->fetch_assoc()) {
    $required_docs[] = $row;
}

$required_docs = array_map(function ($doc) {
    $doc['doc_status'] = ($doc['doc_status'] === 'uploaded') ? 'Uploaded' : 'Pending';
    return $doc;
}, $required_docs);

$total_docs = count($required_docs);
$uploaded_docs = count(array_filter($required_docs, fn($doc) => $doc['doc_status'] === 'Uploaded'));
$pending_docs = $total_docs - $uploaded_docs;
$progress_percentage = $total_docs ? round(($uploaded_docs / $total_docs) * 100) : 0;

$doc_messages = [
    'Planned Budget' => 'Budget template is not available. Please contact the organizer.',
    'List of Participants' => 'No participant list template yet.',
    'OCES Annex A Form' => 'Form will be provided upon request.'
];

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
                    <p>Event Details & Compliance Status</p>
                </div>

                <div class="action-btns">
                    <a href="create_event.php?id=<?= $event['event_id'] ?>" class="btn-secondary">
                        Edit Event
                    </a>

                    <form method="POST" action="archive_event.php" class="inline-form">
                        <input type="hidden" name="event_id" value="<?= $event['event_id'] ?>">

                        <button type="submit" name="archive_event" class="btn-danger"
                            onclick="return confirm('Archive this event? You can restore it for 30 days.')">
                            Archive Event
                        </button>
                    </form>
                </div>
            </header>

            <section class="content view-event-page">
                <div class="action-btns">
                    <button type="button" class="btn-secondary" onclick="history.back()">
                        Back
                    </button>
                </div>
                <!-- Status Banner (spans full width) -->
                <div class="status-banner status-<?= strtolower(str_replace(' ', '-', $event['event_status'])) ?>">
                    <div class="status-content">
                        <h3>Event Status: <?= htmlspecialchars($event['event_status']) ?></h3>
                        <p>Your event is currently under review by the Office of Student Affairs</p>
                    </div>
                </div>

                <!-- LEFT COLUMN -->
                <div class="col-left">

                    <!-- Event Classification -->
                    <section class="detail-card">
                        <div class="card-header">
                            <h2><i class="fa-solid fa-magnifying-glass-chart"></i> Event Classification</h2>
                            <span class="badge badge-primary"><?= htmlspecialchars($event['activity_type']) ?></span>
                        </div>
                        <div class="card-body">
                            <div class="detail-grid">
                                <div class="detail-item">
                                    <label>Organizing Body</label>
                                    <p><?= htmlspecialchars(is_array(json_decode($event['organizing_body'])) ? implode(", ", json_decode($event['organizing_body'])) : $event['organizing_body']) ?>
                                    </p>
                                </div>
                                <div class="detail-item">
                                    <label>Background</label>
                                    <p><?= htmlspecialchars($event['background']) ?></p>
                                </div>
                                <div class="detail-item">
                                    <label>Nature of Activity</label>
                                    <p><?= htmlspecialchars($event['nature']) ?></p>
                                </div>
                                <?php if (!empty($event['series'])): ?>
                                    <div class="detail-item">
                                        <label>Series</label>
                                        <p><?= htmlspecialchars($event['series']) ?></p>
                                    </div>
                                <?php endif; ?>
                                <div class="detail-item">
                                    <label>Expected Participants</label>
                                    <p><?= htmlspecialchars($event['participants']) ?> attendees</p>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Basic Information -->
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
                                    <p><?= htmlspecialchars($event['target_metric']) ?></p>
                                </div>
                                <div class="detail-item">
                                    <label>Extraneous Activity</label>
                                    <p><?= htmlspecialchars($event['extraneous']) ?></p>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Schedule & Logistics -->
                    <section class="detail-card">
                        <div class="card-header">
                            <h2><i class="fa-solid fa-calendar-days"></i> Schedule & Logistics</h2>
                        </div>
                        <div class="card-body">
                            <div class="detail-grid">
                                <div class="detail-item">
                                    <label>Start Date & Time</label>
                                    <p><?= date('F j, Y g:i A', strtotime($event['start_datetime'])) ?></p>
                                </div>
                                <div class="detail-item">
                                    <label>End Date & Time</label>
                                    <p><?= date('F j, Y g:i A', strtotime($event['end_datetime'])) ?></p>
                                </div>
                                <div class="detail-item full-width">
                                    <label>Venue / Platform</label>
                                    <p><?= htmlspecialchars($event['venue_platform']) ?></p>
                                </div>
                                <div class="detail-item full-width">
                                    <label>Participants</label>
                                    <p><?= htmlspecialchars($event['participants']) ?></p>
                                </div>
                                <div class="detail-item">
                                    <label>Payment Collection</label>
                                    <p><?= htmlspecialchars($event['collect_payments']) ?></p>
                                </div>

                                <?php if (strpos($event['activity_type'], 'Off-Campus') !== false): ?>
                                    <div class="detail-item">
                                        <label>Distance</label>
                                        <p><?= htmlspecialchars($event['distance']) ?></p>
                                    </div>
                                    <div class="detail-item">
                                        <label>Participant Range</label>
                                        <p><?= htmlspecialchars($event['participant_range']) ?></p>
                                    </div>
                                    <div class="detail-item">
                                        <label>Overnight / Duration &gt;12 hours</label>
                                        <p><?= $event['overnight'] == 1 ? 'Yes' : 'No' ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </section>

                    <!-- Document Checklist & Preview -->
                    <section class="detail-card">
                        <div class="card-header">
                            <h2><i class="fa-solid fa-file-circle-question"></i> Required Documents</h2>
                            <span class="badge badge-success"><?= $uploaded_docs ?>/<?= $total_docs ?> Uploaded</span>
                        </div>

                        <div class="card-body">
                            <div class="doc-checklist">
                                <p class="doc-help">
                                    Upload the required documents. If no file is uploaded, you may preview or download
                                    the
                                    template.
                                </p>
                                <?php foreach ($required_docs as $doc):
                                    $has_upload = !empty($doc['file_path']);
                                    $view_url = $has_upload ? $doc['file_path'] : $doc['template_url'];

                                    // Convert Google Docs template link to exportable PDF (only if no uploaded file)
                                    if (!$has_upload && !empty($doc['template_url']) && str_contains($doc['template_url'], 'docs.google.com')) {
                                        if (preg_match('/\/d\/([a-zA-Z0-9-_]+)/', $doc['template_url'], $matches)) {
                                            $doc_id = $matches[1];
                                            $view_url = "https://docs.google.com/document/d/$doc_id/export?format=pdf";
                                        }
                                    }

                                    ?>
                                    <div class="doc-item status-<?= strtolower($doc['doc_status']) ?>">

                                        <div class="doc-checkbox">
                                            <?php if ($doc['doc_status'] === 'Uploaded'): ?>
                                                <i class="fa-solid fa-file-circle-check" class="status-uploaded"></i>
                                            <?php else: ?>
                                                <i class="fa-solid fa-hourglass-half" class="status-pending"></i>
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

                                            <span class="doc-status"><?= ucfirst($doc['doc_status']) ?></span>
                                        </div>

                                        <div class="doc-actions">

                                            <!-- View Button -->
                                            <button class="btn-icon" onclick="previewDocument(
                                                    '<?= htmlspecialchars($has_upload ? $doc['file_path'] : $doc['template_url'], ENT_QUOTES) ?>',
                                                    '<?= htmlspecialchars($doc['req_name'] . ($has_upload ? ' (Uploaded)' : ' Template'), ENT_QUOTES) ?>',
                                                    '<?= htmlspecialchars(!$has_upload && empty($doc['template_url']) ? 'No template available for this document.' : '', ENT_QUOTES) ?>'
                                                )">
                                                View
                                            </button>

                                            <!-- Upload / Replace -->
                                            <?php if ($doc['doc_status'] !== 'Uploaded'): ?>
                                                <form action="create_requirement.php" method="POST"
                                                    enctype="multipart/form-data" class="upload-form">
                                                    <input type="hidden" name="req_id" value="<?= $doc['req_id'] ?>">
                                                    <label class="btn-icon">
                                                        Upload
                                                        <input type="file" name="document" hidden required
                                                            onchange="this.form.submit()">
                                                    </label>
                                                </form>
                                            <?php endif; ?>

                                            <!-- Delete Uploaded File -->
                                            <?php if ($has_upload): ?>
                                                <form action="delete_requirement.php" method="POST"
                                                    onsubmit="return confirm('Remove uploaded document?');">
                                                    <input type="hidden" name="req_id" value="<?= $doc['req_id'] ?>">
                                                    <button class="btn-danger">Remove</button>
                                                </form>
                                            <?php endif; ?>

                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </section>

                </div>

                <!-- RIGHT COLUMN -->
                <div class="col-right">

                    <!-- Progress Tracker -->
                    <aside class="tracker-card">
                        <h2>Progress Tracker</h2>
                        <div class="ring" style="--progress: <?= $progress_percentage ?>;">
                            <div class="ring-inner"
                                style="display: flex; align-items: center; justify-content: center;">
                                <span id="progressNumber"
                                    style="font-size: 28px; font-weight: bold; color: #4b0014;"><?= $progress_percentage ?>%</span>
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
                <!-- END RIGHT COLUMN -->

            </section>

            <?php include 'assets/includes/footer.php' ?>
        </main>
    </div>

    <!-- Document Preview Modal -->
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

    <script src="assets/script/layout.js?v=1"></script>
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
                frame.style.display = 'block';
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

            // stop document from continuing to load
            frame.src = "";

        }

        document.addEventListener("keydown", function (e) {
            if (e.key === "Escape") {
                closePreview();
            }
        });

        const progressNum = document.getElementById('progressNumber');
        if (<?= $progress_percentage ?> === 100) {
            progressNum.style.color = 'var(--green)';
        } else {
            progressNum.style.color = '#4b0014'; // default burgundy
        }

    </script>
</body>

</html>