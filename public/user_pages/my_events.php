<?php
session_start();
require_once __DIR__ . '/../../app/database.php';

requireLogin();

$user_id = (int) $_SESSION["user_id"];
$username = htmlspecialchars($_SESSION["username"], ENT_QUOTES, "UTF-8");

/* ================= EVENTS QUERY ================= */
$fetchUserEventsSql = "
    SELECT
        e.event_id,
        e.event_name,
        e.nature,
        e.organizing_body,
        e.event_status,
        e.created_at,
        e.docs_total,
        e.docs_uploaded,

        et.activity_type_id,
        cat.activity_type_name AS activity_type,

        ed.start_datetime,
        ed.end_datetime,

        el.venue_platform

    FROM events e
    LEFT JOIN event_type et
        ON e.event_id = et.event_id
    LEFT JOIN config_activity_types cat
        ON et.activity_type_id = cat.activity_type_id
    LEFT JOIN event_dates ed
        ON e.event_id = ed.event_id
    LEFT JOIN event_location el
        ON e.event_id = el.event_id
    WHERE e.user_id = ?
      AND e.archived_at IS NULL
    ORDER BY
        CASE e.event_status
            WHEN 'Draft' THEN 1
            WHEN 'Needs Revision' THEN 2
            WHEN 'Pending Review' THEN 3
            WHEN 'Approved' THEN 4
            WHEN 'Completed' THEN 5
            ELSE 6
        END,
        CASE WHEN ed.start_datetime IS NULL THEN 1 ELSE 0 END,
        ed.start_datetime DESC,
        e.created_at DESC
";

$events = fetchAll(
    $conn,
    $fetchUserEventsSql,
    "i",
    [$user_id]
);

/* ================= SUMMARY COUNTS ================= */
$total_events = count($events);
$draft_count = 0;
$pending_review_count = 0;
$needs_revision_count = 0;
$approved_count = 0;
$completed_count = 0;

foreach ($events as $e) {
    switch ($e['event_status']) {
        case 'Draft':
            $draft_count++;
            break;
        case 'Pending Review':
            $pending_review_count++;
            break;
        case 'Needs Revision':
            $needs_revision_count++;
            break;
        case 'Approved':
            $approved_count++;
            break;
        case 'Completed':
            $completed_count++;
            break;
    }
}

/* ================= HELPERS ================= */
function normalizeEventStatusClass(string $status): string
{
    return strtolower(str_replace(' ', '-', $status));
}

function canEditEvent(string $status): bool
{
    return in_array($status, ['Draft', 'Pending Review', 'Needs Revision'], true);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>My Events - HAUCREDIT</title>

    <link rel="stylesheet" href="<?= PUBLIC_URL ?>assets/styles/layout.css" />
    <link rel="stylesheet" href="<?= PUBLIC_URL ?>assets/styles/my_events.css" />
</head>

<body>
    <div class="app">
        <div class="sidebar-overlay" id="sidebarOverlay" hidden></div>

        <?php include PUBLIC_PATH . 'assets/includes/general_nav.php' ?>

        <main class="main">
            <header class="topbar">
                <button class="hamburger" id="menuBtn" type="button" aria-label="Open menu">☰</button>

                <div class="title-wrap">
                    <h1>My Events</h1>
                    <p>Manage and track your events.</p>
                </div>

                <div class="action-btns">
                    <a href="archived_events.php" class="btn-secondary">Archived Events</a>
                    <a href="create_event.php" class="btn-primary"><i class="fa-solid fa-plus"></i> Create Event</a>
                </div>
            </header>

            <section class="content my-events-page">
                <div class="summary-strip">
                    <div class="summary-card">
                        <span class="summary-num"><?= $total_events ?></span>
                        <span class="summary-label">Total Events</span>
                    </div>

                    <div class="summary-card">
                        <span class="summary-num"><?= $draft_count ?></span>
                        <span class="summary-label">Drafts</span>
                    </div>

                    <div class="summary-card">
                        <span class="summary-num"><?= $pending_review_count ?></span>
                        <span class="summary-label">Pending Review</span>
                    </div>

                    <div class="summary-card">
                        <span class="summary-num"><?= $needs_revision_count ?></span>
                        <span class="summary-label">Needs Revision</span>
                    </div>

                    <div class="summary-card">
                        <span class="summary-num"><?= $approved_count ?></span>
                        <span class="summary-label">Approved</span>
                    </div>

                    <div class="summary-card">
                        <span class="summary-num"><?= $completed_count ?></span>
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
                        <button class="filter-tab" onclick="setFilter(this,'Draft')">Drafts</button>
                        <button class="filter-tab" onclick="setFilter(this,'Pending Review')">Pending Review</button>
                        <button class="filter-tab" onclick="setFilter(this,'Needs Revision')">Needs Revision</button>
                        <button class="filter-tab" onclick="setFilter(this,'Approved')">Approved</button>
                        <button class="filter-tab" onclick="setFilter(this,'Completed')">Completed</button>
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
                            ($event['event_status'] ?? '') . ' ' .
                            (!empty($event['start_datetime']) ? date('M j Y', strtotime($event['start_datetime'])) : '') . ' ' .
                            (!empty($event['end_datetime']) ? date('M j Y', strtotime($event['end_datetime'])) : '')
                        );

                        $pct_color_ref = max(0, min(100, (int) $pct));
                        $hue = ($pct_color_ref / 100) * 120;
                        $progress_color = "hsl($hue, 70%, 45%)";

                        $status_class = normalizeEventStatusClass($event['event_status'] ?? 'Draft');
                        $can_edit = canEditEvent($event['event_status'] ?? 'Draft');
                        ?>

                        <article class="event-card" data-status="<?= htmlspecialchars($event['event_status']) ?>"
                            data-search="<?= htmlspecialchars($search_blob) ?>">

                            <div class="event-card-top">
                                <span class="event-type-tag">
                                    <?= htmlspecialchars($event['activity_type'] ?? 'N/A') ?>
                                </span>

                                <span class="event-status status-<?= htmlspecialchars($status_class) ?>">
                                    <span class="status-dot"></span>
                                    <span class="status-text">
                                        <?= htmlspecialchars($event['event_status']) ?>
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
                                    <?php if ($can_edit): ?>
                                        <a href="create_event.php?id=<?= (int) $event['event_id'] ?>"
                                            class="btn-secondary btn-edit">
                                            Edit
                                        </a>
                                    <?php endif; ?>

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

            <?php include PUBLIC_PATH . 'assets/includes/footer.php' ?>
        </main>
    </div>

    <script src="<?= APP_URL ?>script/layout.js?v=1"></script>
    <script src="<?= APP_URL ?>script/my_events.js?v=1"></script>
</body>

</html>