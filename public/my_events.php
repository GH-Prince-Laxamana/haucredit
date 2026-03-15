<?php
session_start();
require_once "../app/database.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit();
}

$username = htmlspecialchars($_SESSION["username"], ENT_QUOTES, "UTF-8");

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

$events = [];

while ($row = $result->fetch_assoc()) {
    $events[] = $row;
}

// Summary counts
$total_events = count($events);
$active = $pending = $completed = 0;

foreach ($events as $e) {
    if ($e['event_phase'] === 'active')
        $active++;
    elseif ($e['event_phase'] === 'pending')
        $pending++;
    elseif ($e['event_phase'] === 'completed')
        $completed++;
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
                    <a href="archived_events.php" class="btn-secondary">Archived Events</a>
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

                <!-- Filter & Search Bar -->
                <div class="list-toolbar">
                    <div class="search-wrap">
                        <span class="search-icon"><i class="fa-solid fa-magnifying-glass"></i></span>
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

                <!-- Event Cards Grid -->
                <div class="events-grid" id="eventsGrid">

                    <?php foreach ($events as $event): ?>
                        <?php
                        $pct = $event['docs_total'] > 0
                            ? round(($event['docs_uploaded'] / $event['docs_total']) * 100)
                            : 0;

                        $search_blob = strtolower(
                            $event['event_name'] . ' ' .
                            $event['activity_type'] . ' ' .
                            $event['nature'] . ' ' .
                            $event['organizing_body'] . ' ' .
                            $event['venue_platform'] . ' ' .
                            date('M j Y', strtotime($event['start_datetime'])) . ' ' .
                            date('M j Y', strtotime($event['end_datetime']))
                        );
                        ?>

                        <article class="event-card" data-status="<?= $event['event_phase'] ?>"
                            data-search="<?= htmlspecialchars($search_blob) ?>">
                            <!-- Card Top Bar -->
                            <div class="event-card-top">
                                <span class="event-type-tag"><?= htmlspecialchars($event['activity_type']) ?></span>
                                <span class="event-status status-<?= $event['event_phase'] ?>">
                                    <span class="status-dot"></span>
                                    <span class="status-text"><?= ucfirst($event['event_phase']) ?></span>
                                </span>
                            </div>

                            <!-- Card Body -->
                            <div class="event-card-body">
                                <h3 class="event-title"><?= htmlspecialchars($event['event_name']) ?></h3>

                                <div class="event-meta">
                                    <div class="meta-row">
                                        <span class="meta-icon"><i class="fa-solid fa-building-columns"></i></span>
                                        <span>
                                            <?php
                                            $org = $event['organizing_body'];
                                            $org_clean = str_replace(['[', ']', '"', "'"], '', $org);
                                            $org_clean = str_replace(',', ', ', $org_clean);
                                            echo htmlspecialchars($org_clean);
                                            ?>
                                        </span>
                                    </div>
                                    <div class="meta-row">
                                        <span class="meta-icon"><i class="fa-solid fa-location-dot"></i></span>
                                        <span><?= htmlspecialchars($event['venue_platform']) ?></span>
                                    </div>
                                    <div class="meta-row">
                                        <span class="meta-icon"><i class="fa-solid fa-calendar"></i></span>
                                        <span>
                                            <?= date('M j, Y', strtotime($event['start_datetime'])) ?>
                                            <?php if ($event['start_datetime'] !== $event['end_datetime']): ?>
                                                – <?= date('M j, Y', strtotime($event['end_datetime'])) ?>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                </div>

                                <!-- Document Progress Bar -->
                                <div class="doc-progress">
                                    <div class="doc-progress-label">
                                        <span>Documents</span>
                                        <span><?= $event['docs_uploaded'] ?>/<?= $event['docs_total'] ?> uploaded</span>
                                    </div>
                                    <div class="progress-bar">
                                        <?php
                                        $progress_class = '';
                                        if ($pct == 100) {
                                            $progress_class = 'progress-complete';
                                        } elseif ($pct >= 50) {
                                            $progress_class = 'progress-mid';
                                        }
                                        ;
                                        ?>
                                        <div class="progress-fill <?= $progress_class ?>" style="width: <?= $pct ?>%"></div>
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
                <?php if (empty($events)): ?>
                    <div class="empty-state" id="emptyState">
                        <div class="empty-icon">📭</div>
                        <h3>No events found</h3>
                        <p>Try adjusting your search or filter, or create a new event.</p>
                        <a href="create_event.php" class="btn-primary">+ New Event</a>
                    </div>
                <?php else: ?>
                    <div class="empty-state" id="emptyState" hidden>
                        <div class="empty-icon">📭</div>
                        <h3>No events found</h3>
                        <p>Try adjusting your search or filter, or create a new event.</p>
                        <a href="create_event.php" class="btn-primary">+ New Event</a>
                    </div>
                <?php endif; ?>

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
            const query = document.getElementById('searchInput').value.toLowerCase().trim();
            const cards = document.querySelectorAll('.event-card');
            let visible = 0;

            cards.forEach(card => {
                const status = card.dataset.status;
                const text = card.dataset.search || "";

                const matchFilter = currentFilter === 'all' || status === currentFilter;
                const matchSearch = text.includes(query);

                if (matchFilter && matchSearch) {
                    card.style.display = "block"; // force visible
                    visible++;
                } else {
                    card.style.display = "none"; // hide
                }
            });

            document.getElementById('emptyState').hidden = visible !== 0;
        }
    </script>
</body>

</html>