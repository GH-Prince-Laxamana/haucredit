<?php
session_start();


if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit();
}

$username = htmlspecialchars($_SESSION["username"], ENT_QUOTES, "UTF-8");

// PLACEHOLDER DATA (Replace with actual database query)
$event = [
    'event_id' => 1,
    'organizing_body' => 'HAUSG CSC-SAS',
    'background' => 'Student-Initiated Activity',
    'activity_type' => 'Off-Campus Activity',
    'series' => '',
    'event_scope' => 'university',
    'expected_participants' => 150,
    'event_name' => 'SAS Leadership Summit 2026',
    'event_description' => 'A comprehensive leadership training program for SAS student leaders focusing on organizational management, event planning, and community engagement.',
    'contact_person' => 'Juan Dela Cruz',
    'contact_email' => 'juan.delacruz@student.hau.edu.ph',
    'nature' => 'Seminar-Workshop',
    'activity_name' => 'SAS Leadership Summit 2026: Building Tomorrow\'s Leaders',
    'start_date' => '2026-03-15',
    'start_time' => '08:00',
    'end_date' => '2026-03-15',
    'end_time' => '17:00',
    'collect_payments' => 'Yes',
    'num_participants' => '25 student leaders, 5 officers, 3 guest speakers',
    'venue_platform' => 'Baguio Country Club, Baguio City',
    'distance' => 'Rest of PH or Overseas',
    'participant_range' => '25 or more',
    'duration' => '0',
    'target_metric' => '85% Satisfaction Rating, 90% Attendance Rate',
    'is_extraneous' => 'No',
    'status' => 'Pending Review',
    'created_at' => '2026-02-15 10:30:00'
];

// Document checklist based on activity type
$required_docs = [
    ['name' => 'Approval Letter from Dean', 'code' => 'FM-SSA-SAO-8004.1', 'status' => 'Uploaded', 'url' => 'https://tinyurl.com/HAUStuActApprovalLetter'],
    ['name' => 'Program Flow/Itinerary', 'code' => 'FM-SSA-SAO-8004', 'status' => 'Uploaded', 'url' => 'https://tinyurl.com/HAUStudentActivityForm'],
    ['name' => 'Parental Consents', 'code' => 'FM-SSA-SAO-8004.8', 'status' => 'Pending', 'url' => 'https://tinyurl.com/HAUParentalConsentFormat'],
    ['name' => 'Letter of Undertaking', 'code' => 'FM-SSA-SAO-8004.10', 'status' => 'Uploaded', 'url' => 'https://tinyurl.com/formatUndertakingLetter'],
    ['name' => 'Planned Budget', 'code' => '', 'status' => 'Uploaded', 'url' => '#'],
    ['name' => 'List of Participants', 'code' => 'FM-SSA-SAO-8004.6', 'status' => 'Pending', 'url' => 'https://tinyurl.com/HAUStuActVisitorsList'],
    ['name' => 'CHEd Compliance Certificate', 'code' => 'FM-SSA-SAO-8004.9', 'status' => 'Pending', 'url' => 'https://tinyurl.com/CHEdComplianceCertFormat'],
];

// Progress calculation
$total_docs = count($required_docs);
$uploaded_docs = count(array_filter($required_docs, fn($doc) => $doc['status'] === 'Uploaded'));
$progress_percentage = round(($uploaded_docs / $total_docs) * 100);
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
                <a href="create_event.php?id=<?= $event['event_id'] ?>" class="btn-secondary">Edit Event</a>
                <button class="btn-danger" onclick="confirmDelete()">Delete</button>
            </div>
        </header>

        <section class="content view-event-page">

            <!-- Status Banner (spans full width) -->
            <div class="status-banner status-<?= strtolower(str_replace(' ', '-', $event['status'])) ?>">
                <div class="status-icon">ℹ</div>
                <div class="status-content">
                    <h3>Event Status: <?= htmlspecialchars($event['status']) ?></h3>
                    <p>Your event is currently under review by the Office of Student Affairs</p>
                </div>
            </div>

            <!-- LEFT COLUMN -->
            <div class="col-left">

                <!-- Event Classification -->
                <section class="detail-card">
                    <div class="card-header">
                        <h2>📋 Event Classification</h2>
                        <span class="badge badge-primary"><?= htmlspecialchars($event['activity_type']) ?></span>
                    </div>
                    <div class="card-body">
                        <div class="detail-grid">
                            <div class="detail-item">
                                <label>Organizing Body</label>
                                <p><?= htmlspecialchars($event['organizing_body']) ?></p>
                            </div>
                            <div class="detail-item">
                                <label>Background</label>
                                <p><?= htmlspecialchars($event['background']) ?></p>
                            </div>
                            <div class="detail-item">
                                <label>Event Scope</label>
                                <p><?= ucfirst($event['event_scope']) ?></p>
                            </div>
                            <div class="detail-item">
                                <label>Expected Participants</label>
                                <p><?= htmlspecialchars($event['expected_participants']) ?> attendees</p>
                            </div>
                            <?php if ($event['series']): ?>
                            <div class="detail-item">
                                <label>Series</label>
                                <p><?= htmlspecialchars($event['series']) ?></p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </section>

                <!-- Basic Information -->
                <section class="detail-card">
                    <div class="card-header">
                        <h2>📝 Basic Information</h2>
                    </div>
                    <div class="card-body">
                        <div class="detail-grid">
                            <div class="detail-item full-width">
                                <label>Event Description</label>
                                <p><?= htmlspecialchars($event['event_description']) ?></p>
                            </div>
                            <div class="detail-item">
                                <label>Contact Person</label>
                                <p><?= htmlspecialchars($event['contact_person']) ?></p>
                            </div>
                            <div class="detail-item">
                                <label>Contact Email</label>
                                <p><a href="mailto:<?= htmlspecialchars($event['contact_email']) ?>"><?= htmlspecialchars($event['contact_email']) ?></a></p>
                            </div>
                            <div class="detail-item">
                                <label>Nature of Activity</label>
                                <p><?= htmlspecialchars($event['nature']) ?></p>
                            </div>
                            <div class="detail-item">
                                <label>Target Metric</label>
                                <p><?= htmlspecialchars($event['target_metric']) ?></p>
                            </div>
                            <div class="detail-item">
                                <label>Extraneous Activity</label>
                                <p><?= htmlspecialchars($event['is_extraneous']) ?></p>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Schedule & Logistics -->
                <section class="detail-card">
                    <div class="card-header">
                        <h2>📅 Schedule & Logistics</h2>
                    </div>
                    <div class="card-body">
                        <div class="detail-grid">
                            <div class="detail-item">
                                <label>Start Date & Time</label>
                                <p><?= date('F j, Y', strtotime($event['start_date'])) ?> at <?= date('g:i A', strtotime($event['start_time'])) ?></p>
                            </div>
                            <div class="detail-item">
                                <label>End Date & Time</label>
                                <p><?= date('F j, Y', strtotime($event['end_date'])) ?> at <?= date('g:i A', strtotime($event['end_time'])) ?></p>
                            </div>
                            <div class="detail-item full-width">
                                <label>Venue/Platform</label>
                                <p><?= htmlspecialchars($event['venue_platform']) ?></p>
                            </div>
                            <div class="detail-item full-width">
                                <label>Participants</label>
                                <p><?= htmlspecialchars($event['num_participants']) ?></p>
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
                                <label>Duration (>12 hours)</label>
                                <p><?= $event['duration'] == 1 ? 'Yes' : 'No' ?></p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </section>

                <!-- Document Checklist & Preview -->
                <section class="detail-card">
                    <div class="card-header">
                        <h2>📄 Required Documents</h2>
                        <span class="badge badge-success"><?= $uploaded_docs ?>/<?= $total_docs ?> Uploaded</span>
                    </div>
                    <div class="card-body">
                        <div class="doc-checklist">
                            <?php foreach ($required_docs as $doc): ?>
                            <div class="doc-item status-<?= strtolower($doc['status']) ?>">
                                <div class="doc-checkbox">
                                    <?php if ($doc['status'] === 'Uploaded'): ?>
                                    <span class="check">✓</span>
                                    <?php elseif ($doc['status'] === 'Pending'): ?>
                                    <span class="pending">○</span>
                                    <?php endif; ?>
                                </div>
                                <div class="doc-info">
                                    <h4><?= htmlspecialchars($doc['name']) ?></h4>
                                    <?php if ($doc['code']): ?>
                                    <span class="doc-code"><?= htmlspecialchars($doc['code']) ?></span>
                                    <?php endif; ?>
                                    <span class="doc-status"><?= htmlspecialchars($doc['status']) ?></span>
                                </div>
                                <div class="doc-actions">
                                    <?php if ($doc['status'] === 'Uploaded'): ?>
                                    <button class="btn-icon" onclick="previewDocument('<?= htmlspecialchars($doc['url']) ?>', '<?= htmlspecialchars($doc['name']) ?>')" title="Preview">
                                        👁️
                                    </button>
                                    <a href="<?= htmlspecialchars($doc['url']) ?>" class="btn-icon" download title="Download">
                                        ⬇️
                                    </a>
                                    <?php else: ?>
                                    <a href="<?= htmlspecialchars($doc['url']) ?>" class="btn-icon" target="_blank" title="Download Template">
                                        📄
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </section>

            </div>
            <!-- END LEFT COLUMN -->

            <!-- RIGHT COLUMN -->
            <div class="col-right">

                <!-- Progress Tracker -->
                <aside class="tracker-card">
                    <h2>Progress Tracker</h2>
                    <div class="ring">
                        <div class="ring-inner" style="display: flex; align-items: center; justify-content: center;">
                            <span style="font-size: 28px; font-weight: bold; color: #4b0014;"><?= $progress_percentage ?>%</span>
                        </div>
                    </div>
                    <div class="tracker-list">
                        <div class="t-row"><span>Total Documents</span><span class="t-score"><?= $total_docs ?></span></div>
                        <div class="t-row"><span>Uploaded</span><span class="t-score"><?= $uploaded_docs ?></span></div>
                        <div class="t-row"><span>Pending</span><span class="t-score"><?= $total_docs - $uploaded_docs ?></span></div>
                        <div class="t-row"><span>Completion</span><span class="t-score"><?= $progress_percentage ?>%</span></div>
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
function previewDocument(url, name) {
    const modal = document.getElementById('docPreviewModal');
    const frame = document.getElementById('docFrame');
    const title = document.getElementById('modalTitle');
    
    title.textContent = name;
    
    // Use Google Docs Viewer for better PDF preview
    if (url.endsWith('.pdf') || url.includes('tinyurl')) {
        frame.src = `https://docs.google.com/viewer?url=${encodeURIComponent(url)}&embedded=true`;
    } else {
        frame.src = url;
    }
    
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closePreview() {
    const modal = document.getElementById('docPreviewModal');
    const frame = document.getElementById('docFrame');
    
    modal.classList.remove('active');
    frame.src = '';
    document.body.style.overflow = '';
}

function confirmDelete() {
    if (confirm('Are you sure you want to delete this event? This action cannot be undone.')) {
        alert('Event deleted successfully! (This is a placeholder)');
        window.location.href = 'home.php';
    }
}

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closePreview();
    }
});
</script>
</body>
</html>