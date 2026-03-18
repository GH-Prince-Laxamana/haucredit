<?php
session_start();
require_once "../app/database.php";

// ===== AUTHENTICATION CHECK =====
// Ensure user is logged in before displaying events
if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit();
}

// ===== USER DATA EXTRACTION =====
// Retrieve and sanitize username from session
$username = htmlspecialchars($_SESSION["username"], ENT_QUOTES, "UTF-8");

/* ================= EVENTS QUERY ================= */
// Fetch all active events for the current user with document progress and phase calculation
// Phase logic: pending (incomplete docs), active (complete docs + future end), completed (past end)
$stmt = $conn->prepare("
    SELECT 
        e.event_id,
        e.event_name,
        e.activity_type,
        e.nature,
        e.organizing_body,
        e.venue_platform,
        e.start_datetime,
        e.end_datetime,
        e.participants,
        e.event_status,
        e.created_at,

        COUNT(r.req_id) AS docs_total,
        SUM(CASE WHEN r.doc_status = 'uploaded' THEN 1 ELSE 0 END) AS docs_uploaded,

        CASE
            WHEN SUM(CASE WHEN r.doc_status = 'uploaded' THEN 1 ELSE 0 END) < COUNT(r.req_id)
                THEN 'pending'
            WHEN e.end_datetime >= NOW()
                THEN 'active'
            ELSE 'completed'
        END AS event_phase

    FROM events e
    LEFT JOIN requirements r ON e.event_id = r.event_id
    WHERE e.user_id = ? AND e.archived_at IS NULL
    GROUP BY e.event_id

    ORDER BY 
    CASE
        WHEN SUM(CASE WHEN r.doc_status = 'uploaded' THEN 1 ELSE 0 END) < COUNT(r.req_id)
            THEN 1
        WHEN e.end_datetime >= NOW()
            THEN 2
        ELSE 3
    END,
    e.start_datetime DESC
");

$stmt->bind_param("i", $_SESSION["user_id"]);
$stmt->execute();
$result = $stmt->get_result();

// ===== STORE EVENTS IN ARRAY =====
// Collect all event data into an array for processing
$events = [];
while ($row = $result->fetch_assoc()) {
    $events[] = $row;
}

/* ================= SUMMARY COUNTS ================= */
// Calculate total events and count by phase for summary display
$total_events = count($events);
$active = $pending = $completed = 0;

foreach ($events as $e) {
    switch ($e['event_phase']) {
        case 'active':
            $active++;
            break;
        case 'pending':
            $pending++;
            break;
        case 'completed':
            $completed++;
            break;
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>My Events - HAUCREDIT</title>

    <link rel="stylesheet" href="assets/styles/layout.css" />
    <link rel="stylesheet" href="assets/styles/my_events.css" />
</head>

<body>
    <div class="app">
        <!-- Sidebar overlay for mobile navigation -->
        <div class="sidebar-overlay" id="sidebarOverlay" hidden></div>

        <?php include 'assets/includes/general_nav.php' ?>

        <main class="main">
            <!-- ===== PAGE HEADER ===== -->
            <header class="topbar">
                <button class="hamburger" id="menuBtn" type="button" aria-label="Open menu">☰</button>

                <div class="title-wrap">
                    <h1>My Events</h1>
                    <p>Manage and track your events.</p>
                </div>

                <div class="action-btns">
                    <a href="archived_events.php" class="btn-secondary">Archived Events</a>
                    <a href="create_event.php" class="btn-primary">Create Event</a>
                </div>
            </header>

            <section class="content my-events-page">
                <!-- ===== SUMMARY CARDS ===== -->
                <!-- Display overview statistics for events -->
                <div class="summary-strip">
                    <div class="summary-card">
                        <span class="summary-num"><?= $total_events ?></span>
                        <span class="summary-label">Total Events</span>
                    </div>

                    <div class="summary-card">
                        <span class="summary-num"><?= $active ?></span>
                        <span class="summary-label">Active</span>
                    </div>

                    <div class="summary-card">
                        <span class="summary-num"><?= $pending ?></span>
                        <span class="summary-label">Pending Review</span>
                    </div>

                    <div class="summary-card">
                        <span class="summary-num"><?= $completed ?></span>
                        <span class="summary-label">Completed</span>
                    </div>
                </div>

                <!-- ===== SEARCH AND FILTER CONTROLS ===== -->
                <!-- Provide search and filter functionality for events -->
                <div class="list-toolbar">
                    <div class="search-wrap">
                        <span class="search-icon">
                            <i class="fa-solid fa-magnifying-glass"></i>
                        </span>

                        <input type="text" id="searchInput" class="search-input" placeholder="Search events..."
                            oninput="filterEvents()" />
                    </div>

                    <div class="filter-tabs" id="filterTabs">
                        <button class="filter-tab active" onclick="setFilter(this,'all')">All Events</button>
                        <button class="filter-tab" onclick="setFilter(this,'active')">Active</button>
                        <button class="filter-tab" onclick="setFilter(this,'pending')">Pending</button>
                        <button class="filter-tab" onclick="setFilter(this,'completed')">Completed</button>
                    </div>
                </div>

                <!-- ===== EVENT CARDS GRID ===== -->
                <!-- Display individual event cards with details and actions -->
                <div class="events-grid" id="eventsGrid">
                    <?php foreach ($events as $event): ?>
                        <?php
                        // Calculate document upload progress percentage
                        $pct = $event['docs_total'] > 0
                            ? round(($event['docs_uploaded'] / $event['docs_total']) * 100)
                            : 0;

                        // Create searchable text blob for filtering
                        $search_blob = strtolower(
                            $event['event_name'] . ' ' .
                            $event['activity_type'] . ' ' .
                            $event['nature'] . ' ' .
                            $event['organizing_body'] . ' ' .
                            $event['venue_platform'] . ' ' .
                            date('M j Y', strtotime($event['start_datetime'])) . ' ' .
                            date('M j Y', strtotime($event['end_datetime']))
                        );

                        // Clean up organizing body display (remove JSON artifacts)
                        $org_clean = str_replace(
                            ['[', ']', '"', "'"],
                            '',
                            $event['organizing_body']
                        );

                        $org_clean = str_replace(',', ', ', $org_clean);

                        $pct_color_ref = max(0, min(100, (int) $pct)); // clamp 0–100
                    
                        $hue = ($pct_color_ref / 100) * 120; // 0 = red, 120 = green
                    
                        $progress_color = "hsl($hue, 70%, 45%)";
                        ?>

                        <article class="event-card" data-status="<?= $event['event_phase'] ?>"
                            data-search="<?= htmlspecialchars($search_blob) ?>">

                            <!-- Card Top Section -->
                            <div class="event-card-top">
                                <span class="event-type-tag">
                                    <?= htmlspecialchars($event['activity_type']) ?>
                                </span>

                                <span class="event-status status-<?= $event['event_phase'] ?>">
                                    <span class="status-dot"></span>
                                    <span class="status-text">
                                        <?= ucfirst($event['event_phase']) ?>
                                    </span>
                                </span>
                            </div>

                            <!-- Card Body Section -->
                            <div class="event-card-body">
                                <h3 class="event-title">
                                    <?= htmlspecialchars($event['event_name']) ?>
                                </h3>

                                <div class="event-meta">
                                    <div class="meta-row">
                                        <span class="meta-icon">
                                            <i class="fa-solid fa-building-columns"></i>
                                        </span>
                                        <span><?= htmlspecialchars($org_clean) ?></span>
                                    </div>

                                    <div class="meta-row">
                                        <span class="meta-icon">
                                            <i class="fa-solid fa-location-dot"></i>
                                        </span>
                                        <span><?= htmlspecialchars($event['venue_platform']) ?></span>
                                    </div>

                                    <div class="meta-row">
                                        <span class="meta-icon">
                                            <i class="fa-solid fa-calendar"></i>
                                        </span>

                                        <span>
                                            <?= date('M j, Y', strtotime($event['start_datetime'])) ?>

                                            <?php if ($event['start_datetime'] !== $event['end_datetime']): ?>
                                                – <?= date('M j, Y', strtotime($event['end_datetime'])) ?>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                </div>

                                <!-- Documents Progress Section -->
                                <div class="doc-progress">
                                    <div class="doc-progress-label">
                                        <span>Documents</span>
                                        <span>
                                            <?= $event['docs_uploaded'] ?>/<?= $event['docs_total'] ?> uploaded
                                        </span>
                                    </div>

                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?= $pct ?>%; --progress-color: <?= $progress_color ?>"></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Card Footer Section -->
                            <footer class="event-card-footer">
                                <span class="event-created">
                                    Created <?= date('M j, Y', strtotime($event['created_at'])) ?>
                                </span>

                                <div class="card-actions">
                                    <a href="create_event.php?id=<?= $event['event_id'] ?>"
                                        class="btn-secondary btn-edit">Edit</a>
                                    <a href="view_event.php?id=<?= $event['event_id'] ?>"
                                        class="btn-primary btn-view">View</a>
                                </div>
                            </footer>
                        </article>
                    <?php endforeach; ?>
                </div>

                <!-- ===== EMPTY STATE ===== -->
                <!-- Display when no events match the current filters -->
                <div class="empty-state" id="emptyState" <?= empty($events) ? '' : 'hidden' ?>>
                    <div class="empty-icon">
                        <i class="fa-solid fa-file-circle-xmark"></i>
                    </div>

                    <h3>No events found</h3>

                    <p>
                        Try adjusting your search or filter, or create a new event.
                    </p>

                    <a href="create_event.php" class="btn-primary">
                        Create Event
                    </a>
                </div>
            </section>

            <?php include 'assets/includes/footer.php' ?>
        </main>
    </div>

    <script src="../app/script/layout.js?v=1"></script>
    <script src="../app/script/my_events.js?v=1"></script>
</body>

</html>