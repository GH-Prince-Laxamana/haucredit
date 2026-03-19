<?php
session_start();
require_once "../app/database.php";

// ===== TIMEZONE SETTING =====
// Set the default timezone to Asia/Manila for accurate date/time handling
date_default_timezone_set('Asia/Manila');

// ===== AUTHENTICATION CHECK =====
// Ensure user is logged in before allowing access to requirements page
if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION["user_id"];

// ===== GET FILTER PARAMETER =====
// Retrieve optional event filter from URL query string
$event_filter = $_GET['event_id'] ?? null;

// ===== LOAD REQUIREMENTS FROM DATABASE =====
// Query to fetch requirements joined with events for the current user
// Only include non-archived events
$sql = "
    SELECT r.req_id, r.req_name, r.req_desc, r.file_path, r.doc_status, r.created_at,
           e.event_id, e.event_name, e.start_datetime
    FROM requirements r
    JOIN events e ON r.event_id = e.event_id
    WHERE e.user_id = ?
    AND e.archived_at IS NULL
";

// Add event filter condition if specified
if ($event_filter) {
    $sql .= " AND e.event_id = ?";
}

// Order by event start time and requirement creation time
$sql .= " ORDER BY e.start_datetime ASC, r.created_at ASC";

// Prepare and execute the query
$stmt = $conn->prepare($sql);

// Bind parameters based on whether filter is applied
if ($event_filter) {
    $stmt->bind_param("ii", $user_id, $event_filter);
} else {
    $stmt->bind_param("i", $user_id);
}

$stmt->execute();
$result = $stmt->get_result();

// Initialize requirements array
$requirements = [];

// ===== LOAD EVENTS FOR FILTER DROPDOWN =====
// Fetch all non-archived events for the current user to populate filter options
$events_map = [];

$ev_stmt = $conn->prepare("
    SELECT event_id, event_name, start_datetime
    FROM events
    WHERE user_id = ?
    AND archived_at IS NULL
    ORDER BY start_datetime ASC
");

$ev_stmt->bind_param("i", $user_id);
$ev_stmt->execute();
$ev_res = $ev_stmt->get_result();

// Build events map for filter dropdown
while ($row = $ev_res->fetch_assoc()) {
    $events_map[$row['event_id']] = [
        'name' => $row['event_name'],
        'start' => $row['start_datetime']
    ];
}

// Process requirements result into array
while ($row = $result->fetch_assoc()) {
    $requirements[] = [
        'id' => $row['req_id'],
        'name' => $row['req_name'],
        'desc' => $row['req_desc'],
        'file_path' => $row['file_path'],
        'status' => $row['doc_status'],
        'created_at' => $row['created_at'],
        'event_id' => $row['event_id'],
        'event_name' => $row['event_name'],
        'event_start' => $row['start_datetime'],
    ];
}

// ===== DATE GROUPING SETUP =====
// Create DateTime objects for today, yesterday, and tomorrow for grouping
$today = new DateTime('today');
$yesterday = (clone $today)->modify('-1 day');
$tomorrow = (clone $today)->modify('+1 day');

// ===== FUNCTION TO FILTER REQUIREMENTS BY DATE =====
// Helper function to get events and their requirements for a specific date
function eventsForDate($requirements, $date)
{
    // Filter requirements by the specified date (YYYY-MM-DD format)
    $filtered = array_filter($requirements, fn($r) => substr($r['event_start'], 0, 10) === $date);

    $events = [];

    // Group requirements by event
    foreach ($filtered as $r) {
        $eventId = $r['event_id'];

        if (!isset($events[$eventId])) {
            $events[$eventId] = [
                'event_name' => $r['event_name'],
                'event_id' => $eventId,
                'requirements' => []
            ];
        }

        $events[$eventId]['requirements'][] = $r;
    }

    return $events;
}

// ===== CREATE DATE GROUPS =====
// Group requirements into Yesterday, Today, and Tomorrow categories
$groups = [
    [
        'title' => 'Yesterday',
        'date' => $yesterday,
        'items' => eventsForDate($requirements, $yesterday->format('Y-m-d'))
    ],
    [
        'title' => 'Today',
        'date' => $today,
        'items' => eventsForDate($requirements, $today->format('Y-m-d'))
    ],
    [
        'title' => 'Tomorrow',
        'date' => $tomorrow,
        'items' => eventsForDate($requirements, $tomorrow->format('Y-m-d'))
    ]
];

// ===== PROGRESS CALCULATION =====
// Calculate completion progress based on uploaded requirements
$total = count($requirements);
$uploaded = count(array_filter($requirements, fn($r) => $r['status'] === 'uploaded'));
$percent = $total ? round(($uploaded / $total) * 100) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Requirements</title>

    <link rel="stylesheet" href="assets/styles/layout.css">
    <link rel="stylesheet" href="assets/styles/requirements.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>

<body>
    <!-- ===== APPLICATION CONTAINER ===== -->
    <!-- Main app wrapper for layout structure -->
    <div class="app">
        <!-- ===== GENERAL NAVIGATION ===== -->
        <!-- Include the general navigation bar -->
        <?php include "assets/includes/general_nav.php"; ?>

        <main class="main">
            <!-- ===== PAGE HEADER ===== -->
            <!-- Top section with page title and description -->
            <header class="topbar">
                <div class="title-wrap">
                    <h1>Requirements</h1>
                    <p>Keep track your compliance progress.</p>
                </div>
            </header>

            <!-- ===== PROGRESS TRACKER ===== -->
            <!-- Card displaying overall completion progress -->
            <div class="progress-card enhanced">
                <div class="progress-header">
                    <h3><i class="fa-solid fa-chart-pie"></i> Completion Progress</h3>
                    <span class="progress-badge"><?= $percent ?>% Complete</span>
                </div>
                <div class="progress-stats">
                    <div class="progress-stat-item">
                        <span class="stat-label">Uploaded</span>
                        <span class="stat-number"><?= $uploaded ?></span>
                    </div>
                    <div class="progress-stat-item">
                        <span class="stat-label">Total</span>
                        <span class="stat-number"><?= $total ?></span>
                    </div>
                </div>
                <div class="progress-bar-container">
                    <div class="progress-bar">
                        <div class="progress-fill" style="width:<?= $percent ?>%; background: linear-gradient(90deg, #4b0014, #c2a14d);"></div>
                    </div>
                    <span class="progress-percentage"><?= $percent ?>%</span>
                </div>
            </div>

            <!-- ===== EVENT FILTER ===== -->
            <!-- Form for filtering requirements by specific event -->
            <div class="filter-bar" id="req-deadlines">
                <form method="GET">
                    <label>Filter by Event:</label>
                    <select name="event_id" onchange="this.form.submit()">
                        <option value="">-- All Events --</option>
                        <?php foreach ($events_map as $id => $ev): ?>
                            <option value="<?= $id ?>" <?= ($event_filter == $id) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($ev['name']) ?> (<?= date('M j, Y', strtotime($ev['start'])) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>

            <!-- ===== REQUIREMENTS LIST ===== -->
            <!-- Main content section with grouped requirements -->
            <section class="content req-page">
                <?php foreach ($groups as $group): ?>
                    <!-- ===== REQUIREMENT GROUP ===== -->
                    <!-- Each group represents a date category (Yesterday, Today, Tomorrow) -->
                    <div class="req-group">
                        <div class="req-head">
                            <h2><?= $group['title'] ?></h2>
                            <p><?= $group['date']->format('F j, Y') ?></p>
                        </div>

                    <?php if (empty($group['items'])): ?>
                    <div class="empty-timeline">
                        <div class="empty-icon-small">
                            <?php if ($group['title'] === 'Yesterday'): ?>
                                <i class="fa-regular fa-face-smile"></i>
                            <?php elseif ($group['title'] === 'Today'): ?>
                                <i class="fa-regular fa-clock"></i>
                            <?php else: ?>
                                <i class="fa-regular fa-calendar"></i>
                            <?php endif; ?>
                        </div>
                        <div class="empty-content">
                            <h4>No requirements for <?= strtolower($group['title']) ?></h4>
                            <p>
                                <?php if ($group['title'] === 'Yesterday'): ?>
                                    You were all caught up yesterday!
                                <?php elseif ($group['title'] === 'Today'): ?>
                                    No requirements due today. Enjoy your day!
                                <?php else: ?>
                                    No requirements scheduled for tomorrow yet.
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                <?php endif; ?>

                        <!-- ===== EVENT BLOCKS ===== -->
                        <!-- Loop through events within this date group -->
                        <?php foreach ($group['items'] as $event):
                            // Sort requirements within event: uploaded first, then by deadline
                            usort($event['requirements'], function ($a, $b) {
                                $a_uploaded = ($a['status'] === 'uploaded');
                                $b_uploaded = ($b['status'] === 'uploaded');

                                // Prioritize uploaded requirements
                                if ($a_uploaded !== $b_uploaded) {
                                    return $a_uploaded <=> $b_uploaded;
                                }

                                // Then sort by deadline (event start if no specific deadline)
                                $a_deadline = strtotime($a['deadline'] ?? $a['event_start']);
                                $b_deadline = strtotime($b['deadline'] ?? $b['event_start']);

                                return $a_deadline <=> $b_deadline;
                            });
                        ?>
                            <div class="event-block">
                                <!-- ===== INDIVIDUAL REQUIREMENTS ===== -->
                                <!-- Loop through requirements for this event -->
                                <?php foreach ($event['requirements'] as $req):
                                    $status = ($req['status'] === 'uploaded') ? 'Uploaded' : 'Pending';
                                    $deadline = strtotime($req['deadline'] ?? $req['event_start']);
                                ?>
                                    <!-- ===== REQUIREMENT CARD ===== -->
                                    <!-- Clickable card linking to event view page -->
                                    <a class="req-card" href="view_event.php?id=<?= $event['event_id'] ?>">
                                        <div class="req-item">
                                            <div class="req-title">
                                                <?= htmlspecialchars($req['name']) ?>

                                                <!-- Tooltip for requirement description if available -->
                                                <?php if (!empty($req['desc'])): ?>
                                                    <span class="tooltip-icon">
                                                        <i class="fa-regular fa-circle-question"></i>
                                                        <span class="tooltip-text">
                                                            <?= htmlspecialchars($req['desc']) ?>
                                                        </span>
                                                    </span>
                                                <?php endif; ?>
                                            </div>

                                            <!-- Sub-information showing event name and deadline time -->
                                            <div class="req-sub">
                                                From <?= htmlspecialchars($event['event_name']) ?>:
                                                <strong>
                                                    <time datetime="<?= htmlspecialchars($req['deadline'] ?? $req['event_start']) ?>">
                                                        <?= date("g:i A", $deadline) ?>
                                                    </time>
                                                </strong>
                                            </div>
                                        </div>

                                        <!-- Status indicator -->
                                        <span class="status <?= strtolower($status) ?>">
                                            <?= $status ?>
                                        </span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </section>
        </main>
    </div>

    <!-- ===== JAVASCRIPT ===== -->
    <!-- Include requirements-specific JavaScript -->
    <script src="assets/js/requirements.js"></script>
</body>

</html>