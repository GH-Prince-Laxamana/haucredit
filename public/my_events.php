<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit();
}

$username = htmlspecialchars($_SESSION["username"], ENT_QUOTES, "UTF-8");

// PLACEHOLDER DATA (Replace with actual database query)
// $stmt = $pdo->prepare("SELECT * FROM events WHERE user_id = ? ORDER BY created_at DESC");
// $stmt->execute([$_SESSION['user_id']]);
// $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

$events = [
    [
        'event_id' => 1,
        'event_name' => 'SAS Leadership Summit 2026',
        'activity_type' => 'Off-Campus Activity',
        'nature' => 'Seminar-Workshop',
        'organizing_body' => 'HAUSG CSC-SAS',
        'venue_platform' => 'Baguio Country Club, Baguio City',
        'start_date' => '2026-03-15',
        'end_date' => '2026-03-15',
        'expected_participants' => 150,
        'status' => 'Pending Review',
        'docs_uploaded' => 4,
        'docs_total' => 7,
        'created_at' => '2026-02-15 10:30:00',
    ],
    [
        'event_id' => 2,
        'event_name' => 'General Assembly AY 2025-2026',
        'activity_type' => 'On-Campus Activity',
        'nature' => 'Assembly',
        'organizing_body' => 'HAUSG CSC-SAS',
        'venue_platform' => 'HAU Gym, Main Campus',
        'start_date' => '2026-02-20',
        'end_date' => '2026-02-20',
        'expected_participants' => 400,
        'status' => 'Approved',
        'docs_uploaded' => 5,
        'docs_total' => 5,
        'created_at' => '2026-01-28 09:00:00',
    ],
    [
        'event_id' => 3,
        'event_name' => 'Inter-Department Sports Fest',
        'activity_type' => 'On-Campus Activity',
        'nature' => 'Sports Event',
        'organizing_body' => 'HAU SAS Sports Committee',
        'venue_platform' => 'HAU Sports Complex',
        'start_date' => '2026-04-10',
        'end_date' => '2026-04-12',
        'expected_participants' => 300,
        'status' => 'Draft',
        'docs_uploaded' => 1,
        'docs_total' => 6,
        'created_at' => '2026-02-20 14:00:00',
    ],
    [
        'event_id' => 4,
        'event_name' => 'Community Outreach: Brigada Eskwela',
        'activity_type' => 'Off-Campus Activity',
        'nature' => 'Community Service',
        'organizing_body' => 'HAUSG CSC-SAS',
        'venue_platform' => 'Barangay Sta. Lucia, San Fernando, Pampanga',
        'start_date' => '2026-05-05',
        'end_date' => '2026-05-05',
        'expected_participants' => 80,
        'status' => 'Pending Review',
        'docs_uploaded' => 3,
        'docs_total' => 7,
        'created_at' => '2026-03-01 11:15:00',
    ],
    [
        'event_id' => 5,
        'event_name' => 'Freshmen Orientation Program 2026',
        'activity_type' => 'On-Campus Activity',
        'nature' => 'Orientation',
        'organizing_body' => 'HAUSG CSC-SAS',
        'venue_platform' => 'HAU Auditorium',
        'start_date' => '2026-06-15',
        'end_date' => '2026-06-16',
        'expected_participants' => 500,
        'status' => 'Draft',
        'docs_uploaded' => 0,
        'docs_total' => 5,
        'created_at' => '2026-02-28 16:45:00',
    ],
];

// Summary counts
$total_events   = count($events);
$approved       = count(array_filter($events, fn($e) => $e['status'] === 'Approved'));
$pending        = count(array_filter($events, fn($e) => $e['status'] === 'Pending Review'));
$drafts         = count(array_filter($events, fn($e) => $e['status'] === 'Draft'));

// Status helpers
function status_class(string $status): string {
    return match($status) {
        'Approved'       => 'approved',
        'Pending Review' => 'pending',
        'Draft'          => 'draft',
        default          => 'draft',
    };
}

function status_icon(string $status): string {
    return match($status) {
        'Approved'       => '✓',
        'Pending Review' => '⏳',
        'Draft'          => '✏',
        default          => '•',
    };
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
    <div class="sidebar-overlay" id="sidebarOverlay" hidden></div>

    <?php include 'assets/includes/general_nav.php' ?>

    <main class="main">
        <header class="topbar">
            <button class="hamburger" id="menuBtn" type="button" aria-label="Open menu">☰</button>

            <div class="title-wrap">
                <h1>My Events</h1>
                <p>Manage and track your submitted activities</p>
            </div>

            <div class="action-btns">
                <a href="create_event.php" class="btn-primary">+ New Event</a>
            </div>
        </header>

        <section class="content my-events-page">

            <!-- Summary Cards -->
            <div class="summary-strip">
                <div class="summary-card">
                    <span class="summary-num"><?= $total_events ?></span>
                    <span class="summary-label">Total Events</span>
                </div>
                <div class="summary-card summary-approved">
                    <span class="summary-num"><?= $approved ?></span>
                    <span class="summary-label">Approved</span>
                </div>
                <div class="summary-card summary-pending">
                    <span class="summary-num"><?= $pending ?></span>
                    <span class="summary-label">Pending Review</span>
                </div>
                <div class="summary-card summary-draft">
                    <span class="summary-num"><?= $drafts ?></span>
                    <span class="summary-label">Drafts</span>
                </div>
            </div>

            <!-- Filter & Search Bar -->
            <div class="list-toolbar">
                <div class="search-wrap">
                    <span class="search-icon">🔍</span>
                    <input
                        type="text"
                        id="searchInput"
                        class="search-input"
                        placeholder="Search events..."
                        oninput="filterEvents()"
                    />
                </div>
                <div class="filter-tabs" id="filterTabs">
                    <button class="filter-tab active" data-filter="all"    onclick="setFilter(this, 'all')">All</button>
                    <button class="filter-tab"        data-filter="approved"       onclick="setFilter(this, 'approved')">Approved</button>
                    <button class="filter-tab"        data-filter="pending"        onclick="setFilter(this, 'pending')">Pending</button>
                    <button class="filter-tab"        data-filter="draft"          onclick="setFilter(this, 'draft')">Draft</button>
                </div>
            </div>

            <!-- Event Cards Grid -->
            <div class="events-grid" id="eventsGrid">

                <?php foreach ($events as $event): ?>
                <?php
                    $pct      = $event['docs_total'] > 0
                                ? round(($event['docs_uploaded'] / $event['docs_total']) * 100)
                                : 0;
                    $sc       = status_class($event['status']);
                    $si       = status_icon($event['status']);
                    $days_left = (strtotime($event['start_date']) - time()) / 86400;
                ?>
                <article
                    class="event-card"
                    data-status="<?= $sc ?>"
                    data-name="<?= strtolower(htmlspecialchars($event['event_name'])) ?>"
                >
                    <!-- Card Top Bar -->
                    <div class="event-card-top">
                        <span class="event-type-tag"><?= htmlspecialchars($event['activity_type']) ?></span>
                        <span class="event-status status-<?= $sc ?>">
                            <span class="status-dot"></span>
                            <?= htmlspecialchars($event['status']) ?>
                        </span>
                    </div>

                    <!-- Card Body -->
                    <div class="event-card-body">
                        <h3 class="event-title"><?= htmlspecialchars($event['event_name']) ?></h3>

                        <div class="event-meta">
                            <div class="meta-row">
                                <span class="meta-icon">🏛️</span>
                                <span><?= htmlspecialchars($event['organizing_body']) ?></span>
                            </div>
                            <div class="meta-row">
                                <span class="meta-icon">📍</span>
                                <span><?= htmlspecialchars($event['venue_platform']) ?></span>
                            </div>
                            <div class="meta-row">
                                <span class="meta-icon">📅</span>
                                <span>
                                    <?= date('M j, Y', strtotime($event['start_date'])) ?>
                                    <?php if ($event['start_date'] !== $event['end_date']): ?>
                                        – <?= date('M j, Y', strtotime($event['end_date'])) ?>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <div class="meta-row">
                                <span class="meta-icon">👥</span>
                                <span><?= $event['expected_participants'] ?> expected participants</span>
                            </div>
                        </div>

                        <!-- Document Progress Bar -->
                        <div class="doc-progress">
                            <div class="doc-progress-label">
                                <span>Documents</span>
                                <span><?= $event['docs_uploaded'] ?>/<?= $event['docs_total'] ?> uploaded</span>
                            </div>
                            <div class="progress-bar">
                                <div
                                    class="progress-fill progress-<?= $sc ?>"
                                    style="width: <?= $pct ?>%"
                                ></div>
                            </div>
                        </div>
                    </div>

                    <!-- Card Footer -->
                    <div class="event-card-footer">
                        <span class="event-created">
                            Submitted <?= date('M j, Y', strtotime($event['created_at'])) ?>
                        </span>
                        <div class="card-actions">
                            <a href="view_event.php?id=<?= $event['event_id'] ?>" class="btn-view">View</a>
                            <a href="create_event.php?id=<?= $event['event_id'] ?>" class="btn-edit">Edit</a>
                        </div>
                    </div>
                </article>
                <?php endforeach; ?>

            </div>

            <!-- Empty State (shown via JS when no results) -->
            <div class="empty-state" id="emptyState" hidden>
                <div class="empty-icon">📭</div>
                <h3>No events found</h3>
                <p>Try adjusting your search or filter, or create a new event.</p>
                <a href="create_event.php" class="btn-primary">+ New Event</a>
            </div>

        </section>

        <?php include 'assets/includes/footer.php' ?>
    </main>
</div>

<script src="assets/script/layout.js?v=1"></script>
<script>
let currentFilter = 'all';

function setFilter(btn, filter) {
    document.querySelectorAll('.filter-tab').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    currentFilter = filter;
    filterEvents();
}

function filterEvents() {
    const query  = document.getElementById('searchInput').value.toLowerCase().trim();
    const cards  = document.querySelectorAll('.event-card');
    let   visible = 0;

    cards.forEach(card => {
        const matchFilter = currentFilter === 'all' || card.dataset.status === currentFilter;
        const matchSearch = card.dataset.name.includes(query);

        if (matchFilter && matchSearch) {
            card.style.display = '';
            visible++;
        } else {
            card.style.display = 'none';
        }
    });

    document.getElementById('emptyState').hidden = visible > 0;
}
</script>
</body>
</html>