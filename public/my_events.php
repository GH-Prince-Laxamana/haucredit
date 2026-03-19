<?php
session_start();
require_once "../app/database.php";
require_once "../app/security_headers.php";
send_security_headers();

if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit();
}

$user_id = (int) $_SESSION["user_id"];
$username = htmlspecialchars($_SESSION["username"], ENT_QUOTES, "UTF-8");

/* ================= EVENTS QUERY =================
   Uses:
   - events
   - event_type
   - event_dates
   - event_location
   - docs_total / docs_uploaded from events
*/
$stmt = $conn->prepare("
    SELECT
        e.event_id,
        e.event_name,
        e.nature,
        e.organizing_body,
        e.event_status,
        e.created_at,
        e.docs_total,
        e.docs_uploaded,

        et.activity_type,

        ed.start_datetime,
        ed.end_datetime,

        el.venue_platform,

        CASE
            WHEN e.docs_total > 0 AND e.docs_uploaded < e.docs_total THEN 'pending'
            WHEN ed.end_datetime IS NOT NULL AND ed.end_datetime >= NOW() THEN 'active'
            ELSE 'completed'
        END AS event_phase

    FROM events e
    LEFT JOIN event_type et
        ON e.event_id = et.event_id
    LEFT JOIN event_dates ed
        ON e.event_id = ed.event_id
    LEFT JOIN event_location el
        ON e.event_id = el.event_id
    WHERE e.user_id = ?
      AND e.archived_at IS NULL

    ORDER BY
        CASE
            WHEN e.docs_total > 0 AND e.docs_uploaded < e.docs_total THEN 1
            WHEN ed.end_datetime IS NOT NULL AND ed.end_datetime >= NOW() THEN 2
            ELSE 3
        END,
        CASE WHEN ed.start_datetime IS NULL THEN 1 ELSE 0 END,
        ed.start_datetime DESC,
        e.created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$events = [];
while ($row = $result->fetch_assoc()) {
    $events[] = $row;
}

/* ================= SUMMARY COUNTS ================= */
$total_events = count($events);
$active = 0;
$pending = 0;
$completed = 0;

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
        <div class="sidebar-overlay" id="sidebarOverlay" hidden></div>

        <?php include 'assets/includes/general_nav.php' ?>

        <main class="main">
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

                <div class="events-grid" id="eventsGrid">
                    <?php foreach ($events as $event): ?>
                        <?php
                        $docs_total = (int) ($event['docs_total'] ?? 0);
                        $docs_uploaded = (int) ($event['docs_uploaded'] ?? 0);
                        $pct = $docs_total > 0 ? round(($docs_uploaded / $docs_total) * 100) : 0;

                        $org_clean = $event['organizing_body'] ?? '';
                        $decoded_orgs = json_decode($org_clean, true);
                        if (is_array($decoded_orgs)) {
                            $org_clean = implode(', ', $decoded_orgs);
                        } else {
                            $org_clean = str_replace(['[', ']', '"', "'"], '', $org_clean);
                            $org_clean = str_replace(',', ', ', $org_clean);
                        }

                        $search_blob = strtolower(
                            ($event['event_name'] ?? '') . ' ' .
                            ($event['activity_type'] ?? '') . ' ' .
                            ($event['nature'] ?? '') . ' ' .
                            $org_clean . ' ' .
                            ($event['venue_platform'] ?? '') . ' ' .
                            (!empty($event['start_datetime']) ? date('M j Y', strtotime($event['start_datetime'])) : '') . ' ' .
                            (!empty($event['end_datetime']) ? date('M j Y', strtotime($event['end_datetime'])) : '')
                        );

                        $pct_color_ref = max(0, min(100, (int) $pct));
                        $hue = ($pct_color_ref / 100) * 120;
                        $progress_color = "hsl($hue, 70%, 45%)";
                        ?>

                        <article class="event-card" data-status="<?= htmlspecialchars($event['event_phase']) ?>"
                            data-search="<?= htmlspecialchars($search_blob) ?>">

                            <div class="event-card-top">
                                <span class="event-type-tag">
                                    <?= htmlspecialchars($event['activity_type'] ?? 'N/A') ?>
                                </span>

                                <span class="event-status status-<?= htmlspecialchars($event['event_phase']) ?>">
                                    <span class="status-dot"></span>
                                    <span class="status-text">
                                        <?= ucfirst($event['event_phase']) ?>
                                    </span>
                                </span>
                            </div>

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
                                        <span><?= htmlspecialchars($event['venue_platform'] ?? 'No venue set') ?></span>
                                    </div>

                                    <div class="meta-row">
                                        <span class="meta-icon">
                                            <i class="fa-solid fa-calendar"></i>
                                        </span>

                                        <span>
                                            <?php if (!empty($event['start_datetime'])): ?>
                                                <?= date('M j, Y', strtotime($event['start_datetime'])) ?>
                                            <?php else: ?>
                                                No schedule set
                                            <?php endif; ?>

                                            <?php if (!empty($event['start_datetime']) && !empty($event['end_datetime']) && $event['start_datetime'] !== $event['end_datetime']): ?>
                                                – <?= date('M j, Y', strtotime($event['end_datetime'])) ?>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                </div>

                                <div class="doc-progress">
                                    <div class="doc-progress-label">
                                        <span>Documents</span>
                                        <span><?= $docs_uploaded ?>/<?= $docs_total ?> uploaded</span>
                                    </div>

                                    <div class="progress-bar">
                                        <div class="progress-fill"
                                            style="width: <?= $pct ?>%; --progress-color: <?= $progress_color ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <footer class="event-card-footer">
                                <span class="event-created">
                                    Created <?= date('M j, Y', strtotime($event['created_at'])) ?>
                                </span>

                                <div class="card-actions">
                                    <a href="create_event.php?id=<?= (int) $event['event_id'] ?>"
                                        class="btn-secondary btn-edit">
                                        Edit
                                    </a>
                                    <a href="view_event.php?id=<?= (int) $event['event_id'] ?>"
                                        class="btn-primary btn-view">
                                        View
                                    </a>
                                </div>
                            </footer>
                        </article>
                    <?php endforeach; ?>
                </div>

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