<?php
// ===== SESSION INITIALIZATION =====
// Start the session to access user authentication data
session_start();

// ===== DATABASE CONNECTION =====
// Include the database connection file to establish a connection to the database
require_once "../app/database.php";

// ===== AUTHENTICATION CHECK =====
// Verify that the user is logged in by checking for the user_id in the session
// If not logged in, redirect to the login page
if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit();
}

// ===== USER DATA EXTRACTION =====
// Retrieve the user ID and username from the session for use in queries and display
$user_id = $_SESSION["user_id"];
$username = htmlspecialchars($_SESSION["username"], ENT_QUOTES, "UTF-8");

// ===== EVENT ID VALIDATION =====
// Get the event ID from the query string parameters
$event_id = $_GET['id'] ?? null;

// If no event ID is provided, display an error and stop execution
if (!$event_id) {
    popup_error("Invalid event ID.");
}

// ===== EVENT DATA FETCH =====
// Prepare and execute a query to fetch the event details for the logged-in user
$stmt = $conn->prepare("
    SELECT *
    FROM events
    WHERE event_id = ? AND user_id = ?
    LIMIT 1
");
$stmt->bind_param("ii", $event_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$event = $result->fetch_assoc();

// ===== ARCHIVE STATUS CHECK =====
// Determine if the event is archived based on the archived_at timestamp
$is_archived = !empty($event['archived_at']);

// ===== DAYS REMAINING CALCULATION =====
// If archived, calculate the days remaining before permanent deletion (30 days from archive)
$days_remaining = null;
if ($is_archived) {
    $archived_time = strtotime($event['archived_at']);
    $expiry_time = $archived_time + (30 * 24 * 60 * 60); // 30 days in seconds
    $days_remaining = ceil(($expiry_time - time()) / 86400); // Convert to days
}

// ===== EVENT EXISTENCE VALIDATION =====
// If no event is found, display an error and stop execution
if (!$event) {
    popup_error("Event not found or you don't have permission to view it.");
}

// ===== REQUIRED DOCUMENTS FETCH =====
// Prepare and execute a query to fetch all required documents for this event
$stmt_docs = $conn->prepare("
    SELECT req_id, req_name, req_desc, file_path, template_url, doc_status
    FROM requirements
    WHERE event_id = ?
    ORDER BY created_at ASC
");
$stmt_docs->bind_param("i", $event_id);
$stmt_docs->execute();
$res_docs = $stmt_docs->get_result();

// ===== DOCUMENTS ARRAY POPULATION =====
// Initialize an array to hold the required documents
$required_docs = [];
while ($row = $res_docs->fetch_assoc()) {
    $required_docs[] = $row;
}

// ===== DOCUMENT STATUS NORMALIZATION =====
// Map the document status to user-friendly labels
$required_docs = array_map(function ($doc) {
    $doc['doc_status'] = ($doc['doc_status'] === 'uploaded') ? 'Uploaded' : 'Pending';
    return $doc;
}, $required_docs);

// ===== PROGRESS CALCULATION =====
// Calculate total documents, uploaded count, pending count, and progress percentage
$total_docs = count($required_docs);
$uploaded_docs = count(array_filter($required_docs, fn($doc) => $doc['doc_status'] === 'Uploaded'));
$pending_docs = $total_docs - $uploaded_docs;
$progress_percentage = $total_docs ? round(($uploaded_docs / $total_docs) * 100) : 0;

$pct = max(0, min(100, (int) $progress_percentage)); // clamp 0–100

$hue = ($pct / 100) * 120; // 0 = red, 120 = green

$progress_color = "hsl($hue, 70%, 45%)";

// ===== DOCUMENT MESSAGES DEFINITION =====
// Define messages for documents without templates
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
        <!-- Sidebar overlay for mobile navigation -->
        <div class="sidebar-overlay" id="sidebarOverlay" hidden></div>

        <!-- Include the general navigation component -->
        <?php include 'assets/includes/general_nav.php' ?>

        <main class="main">
            <header class="topbar">
                <!-- Hamburger menu button for mobile -->
                <button class="hamburger" id="menuBtn" type="button" aria-label="Open menu">☰</button>

                <div class="title-wrap">
                    <!-- Display the event name as the page title -->
                    <h1><?= htmlspecialchars($event['event_name']) ?></h1>
                    <p>Event Details and Compliance Status</p>
                </div>

                <div class="action-btns">
                    <!-- Form for archiving or restoring the event -->
                    <form method="POST" action="archive_event.php" class="inline-form" data-confirm="<?= $is_archived
                        ? 'Restore this event?'
                        : 'Archive this event? You can restore it for 30 days.' ?>">

                        <input type="hidden" name="event_id" value="<?= $event['event_id'] ?>">

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

                    <!-- Edit button for non-archived events -->
                    <?php if (!$is_archived): ?>
                        <a href="create_event.php?id=<?= $event['event_id'] ?>" class="btn-primary">
                            Edit Event
                        </a>
                    <?php endif; ?>
                </div>
            </header>

            <section class="content view-event-page">
                <!-- Back button -->
                <div class="action-btns">
                    <button type="button" class="btn-secondary" onclick="history.back()">
                        Back
                    </button>
                </div>

                <!-- Status Banner displaying event status or archive info -->
                <div class="status-banner status-<?= strtolower(str_replace(' ', '-', $event['event_status'])) ?>">
                    <div class="status-content">
                        <?php if (!$is_archived): ?>
                            <h3>Event Status: <?= htmlspecialchars($event['event_status']) ?></h3>
                            <p>Your event is currently under review by the Office of Student Affairs</p>
                        <?php else: ?>
                            <h3>This event is archived</h3>
                            <p>
                                You can view the event but editing and uploads are disabled.<br>
                                This event will be permanently deleted in
                                <strong><?= max($days_remaining, 0) ?> days</strong>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- LEFT COLUMN: Event details and documents -->
                <div class="col-left">

                    <!-- Event Classification Section -->
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

                    <!-- Basic Information Section -->
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

                    <!-- Schedule & Logistics Section -->
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

                    <!-- Document Checklist & Preview Section -->
                    <section class="detail-card">
                        <div class="card-header">
                            <h2><i class="fa-solid fa-file-circle-question"></i> Required Documents</h2>
                            <span class="badge badge-success"><?= $uploaded_docs ?>/<?= $total_docs ?> Uploaded</span>
                        </div>

                        <div class="card-body">
                            <div class="doc-checklist">
                                <p class="doc-help">
                                    Upload your documents in PDF or DOCX format. If no file is uploaded, you may preview
                                    or download the template.
                                </p>
                                <?php foreach ($required_docs as $doc):
                                    // Determine if the document has an uploaded file
                                    $has_upload = !empty($doc['file_path']);
                                    // Set the view URL to the uploaded file or template
                                    $view_url = $has_upload ? $doc['file_path'] : $doc['template_url'];

                                    // Convert Google Docs template to exportable PDF if no upload
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
                                            <button class="btn-file" onclick="previewDocument(
                                                    '<?= htmlspecialchars($has_upload ? '../' . $doc['file_path'] : $doc['template_url'], ENT_QUOTES) ?>',
                                                    '<?= htmlspecialchars($doc['req_name'] . ($has_upload ? ' (Uploaded)' : ' Template'), ENT_QUOTES) ?>',
                                                    '<?= htmlspecialchars(!$has_upload && empty($doc['template_url']) ? 'No template available for this document.' : '', ENT_QUOTES) ?>'
                                                )">
                                                View
                                            </button>

                                            <!-- Upload / Replace for non-archived, non-uploaded docs -->
                                            <?php if (!$is_archived && $doc['doc_status'] !== 'Uploaded'): ?>
                                                <form action="create_requirement.php" method="POST"
                                                    enctype="multipart/form-data" class="upload-form">
                                                    <input type="hidden" name="req_id" value="<?= $doc['req_id'] ?>">
                                                    <label class="btn-file">
                                                        Upload
                                                        <input type="file" name="document" hidden required
                                                            onchange="this.form.submit()">
                                                    </label>
                                                </form>
                                            <?php endif; ?>

                                            <!-- Delete Uploaded File for non-archived docs -->
                                            <?php if ($has_upload && !$is_archived): ?>
                                                <form data-confirm="Remove uploaded document?" action="delete_requirement.php"
                                                    method="POST">
                                                    <input type="hidden" name="req_id" value="<?= $doc['req_id'] ?>">
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

                <!-- RIGHT COLUMN: Progress Tracker -->
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
                                <span><i class="fa-regular fa-file-lines"></i> Total Documents</span>
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

                <!-- Danger Zone for archived events -->
                <?php if ($is_archived): ?>
                    <section class="danger-zone">
                        <h3>Danger Zone</h3>
                        <p>Permanently delete this archived event. This cannot be undone.</p>

                        <form method="POST" action="delete_event.php"
                            data-confirm="Permanently delete this event? This cannot be undone.">
                            <input type="hidden" name="event_id" value="<?= $event['event_id'] ?>">
                            <button class="btn-primary btn-danger">Delete Permanently</button>
                        </form>
                    </section>
                <?php endif; ?>

                <!-- Include the footer -->
                <?php include 'assets/includes/footer.php' ?>
            </section>
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

    <!-- Include layout script -->
    <script src="../app/script/layout.js?v=1"></script>
    <script>
        // ===== DOCUMENT PREVIEW FUNCTION =====
        // Function to open the modal and display a document or template
        function previewDocument(url, name, noTemplateMsg = '') {
            const modal = document.getElementById('docPreviewModal');
            const frame = document.getElementById('docFrame');
            const title = document.getElementById('modalTitle');

            // Set the modal title
            title.textContent = name;

            // If no URL or a no-template message is provided, hide the iframe and show the message
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
                // Show the iframe and set its source
                frame.style.display = 'block';
                let msgEl = document.getElementById('modalMessage');
                if (msgEl) msgEl.textContent = '';

                frame.style.display = 'block';
                frame.src = url;
            }

            // Activate the modal
            modal.classList.add("active");
        }

        // ===== CLOSE PREVIEW FUNCTION =====
        // Function to close the modal and stop any loading iframe
        function closePreview() {
            const modal = document.getElementById('docPreviewModal');
            const frame = document.getElementById('docFrame');

            // Deactivate the modal
            modal.classList.remove('active');

            // Clear the iframe source to stop loading
            frame.src = "";
        }

        // ===== ESCAPE KEY HANDLER =====
        // Listen for the Escape key to close the modal
        document.addEventListener("keydown", function (e) {
            if (e.key === "Escape") {
                closePreview();
            }
        });
    </script>

</body>

</html>