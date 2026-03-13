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
        event_id,
        event_name,
        activity_type,
        nature,
        organizing_body,
        venue_platform,
        start_datetime,
        end_datetime,
        participants,
        event_status,
        docs_total,
        docs_uploaded,
        created_at
    FROM events
    WHERE user_id = ? AND archived_at IS NULL
    ORDER BY created_at DESC
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
$approved = $pending = $drafts = 0;

foreach ($events as $e) {
    $event_status = $e['event_status'] ?? '';

    if ($event_status === 'Approved')
        $approved++;
    elseif ($event_status === 'Pending Review')
        $pending++;
    elseif ($event_status === 'Draft')
        $drafts++;
}

// Status helpers
function status_class(string $event_status): string
{
    return match ($event_status) {
        'Approved' => 'approved',
        'Pending Review' => 'pending',
        'Draft' => 'draft',
        default => 'draft',
    };
}

function status_icon(string $event_status): string
{
    return match ($event_status) {
        'Approved' => '✓',
        'Pending Review' => '⏳',
        'Draft' => '✏',
        default => '•',
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
                        <input type="text" id="searchInput" class="search-input" placeholder="Search events..."
                            oninput="filterEvents()" />
                    </div>
                    <div class="filter-tabs" id="filterTabs">
                        <button class="filter-tab active" data-filter="all"
                            onclick="setFilter(this, 'all')">All</button>
                        <button class="filter-tab" data-filter="approved"
                            onclick="setFilter(this, 'approved')">Approved</button>
                        <button class="filter-tab" data-filter="pending"
                            onclick="setFilter(this, 'pending')">Pending</button>
                        <button class="filter-tab" data-filter="draft" onclick="setFilter(this, 'draft')">Draft</button>
                    </div>
                </div>

                <!-- Event Cards Grid -->
                <div class="events-grid" id="eventsGrid">

                    <?php foreach ($events as $event): ?>
                        <?php
                        $pct = $event['docs_total'] > 0
                            ? round(($event['docs_uploaded'] / $event['docs_total']) * 100)
                            : 0;
                        $sc = status_class($event['event_status']);
                        $si = status_icon($event['event_status']);
                        $days_left = (strtotime($event['start_datetime']) - time()) / 86400;
                        ?>
                        <article class="event-card" data-status="<?= $sc ?>"
                            data-name="<?= strtolower(htmlspecialchars($event['event_name'])) ?>">
                            <!-- Card Top Bar -->
                            <div class="event-card-top">
                                <span class="event-type-tag"><?= htmlspecialchars($event['activity_type']) ?></span>
                                <span class="event-status status-<?= $sc ?>">
                                    <span class="status-dot"></span>
                                    <?= htmlspecialchars($event['event_status']) ?>
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
                                        <div class="progress-fill progress-<?= $sc ?>" style="width: <?= $pct ?>%"></div>
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