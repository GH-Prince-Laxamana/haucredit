<?php
// ============================================================================
// HAUCREDIT DASHBOARD - Home Page
// ============================================================================
/**
 * User dashboard displaying:
 * - Key metrics: current events, upcoming deadlines, compliance progress, archived events
 * - Event cards with progress bars (active events only)
 * - Upcoming deadline list (sorted by deadline date)
 * - Event status distribution and statistics
 *
 * This page loads only non-archived events in active states (Pending Review, Needs Revision, Approved).
 * Archived events are tracked separately and shown only in count form.
 * All data is user-scoped for security (WHERE user_id = ?).
 */


session_start();
require_once __DIR__ . '/../../app/database.php';
require_once APP_PATH . "security_headers.php";
send_security_headers();

requireLogin();

$user_id = (int) $_SESSION['user_id'];

// User's display username
// htmlspecialchars() prevents script injection via user-controlled data
// ENT_QUOTES escapes both double and single quotes
$username = htmlspecialchars($_SESSION["username"], ENT_QUOTES, "UTF-8");

// Organization body this user belongs to
// Used in header to show user's organizational context
$org_body = htmlspecialchars($_SESSION["org_body"], ENT_QUOTES, "UTF-8");

/* ================= HELPERS ================= */
function normalizeStatusClass(string $status): string
{
    return strtolower(str_replace(' ', '-', $status));
}

// ============================================================================
// QUERY 1: FETCH CURRENT ACTIVE EVENTS
// ============================================================================

/**
 * Load all non-archived events for the current user in active status.
 * Joins supporting tables to display event details and requirement progress.
 * 
 * Table relationships:
 * - events: Core event record with status, document counters
 * - event_dates: Scheduled date/time information (nullable)
 * - event_location: Venue and platform details (nullable)
 * - event_metrics: Target metric for event measurement (nullable)
 * 
 * Filtered to show only:
 * - User's own events (user_id = ?)
 * - Non-archived events (archived_at IS NULL)
 * - Active statuses (Needs Revision, Pending Review, Approved)
 * 
 * Sorted by status priority then by start date
 */
$fetchAllEventsSql = "
    SELECT
        e.event_id,
        e.event_name,
        e.docs_total,
        e.docs_uploaded,
        e.event_status,
        ed.start_datetime,
        ed.end_datetime,
        el.venue_platform,
        em.target_metric
    FROM events e
    LEFT JOIN event_dates ed ON e.event_id = ed.event_id
    LEFT JOIN event_location el ON e.event_id = el.event_id
    LEFT JOIN event_metrics em ON e.event_id = em.event_id
    WHERE e.user_id = ?
      AND e.archived_at IS NULL
      AND e.event_status IN ('Pending Review', 'Needs Revision', 'Approved')
    ORDER BY
        CASE e.event_status
            WHEN 'Needs Revision' THEN 1
            WHEN 'Pending Review' THEN 2
            WHEN 'Approved' THEN 3
            WHEN 'Completed' THEN 4
            ELSE 5
        END,
        CASE WHEN ed.start_datetime IS NULL THEN 1 ELSE 0 END,
        ed.start_datetime ASC,
        e.created_at DESC
";

$all_events = fetchAll(
    $conn,
    $fetchAllEventsSql,
    "i",
    [$user_id]
);

// ============================================================================
// CALCULATE OVERALL COMPLIANCE PROGRESS
// ============================================================================

/**
 * Aggregate document submission statistics across all user's active events.
 * Shows user's overall progress toward meeting all requirement submissions.
 * 
 * Progress formula: (Total Uploaded / Total Expected) * 100
 * Each event tracks its own doc counts, we sum all events to get overall.
 */

// Sum requirement document counts across all events
// Each event has docs_total (required documents) and docs_uploaded (submitted documents)
// Filter to integers with null-safety (default 0)
$total_docs_all = array_sum(array_map(fn($e) => (int) ($e['docs_total'] ?? 0), $all_events));
$uploaded_docs_all = array_sum(array_map(fn($e) => (int) ($e['docs_uploaded'] ?? 0), $all_events));

// Calculate percentage complete
// If no requirements exist, show 0% progress (prevent division by zero)
// Otherwise: (uploaded / total) * 100, rounded to whole percent
$overall_progress = $total_docs_all > 0 ? round(($uploaded_docs_all / $total_docs_all) * 100) : 0;

// ============================================================================
// EVENT DISPLAY PAGINATION
// ============================================================================

/**
 * Prepare event data for dashboard display.
 * Shows first 3 events on homepage, with link to "View All" if more exist.
 * This keeps dashboard concise while allowing access to all events.
 */

// Count total number of events user has
// Used to determine if "View All" link should be shown
$total_current_events = count($all_events);

// Flag to show "View All" link only if more events exist beyond the 3 shown
// If 3 or fewer events, don't show view all link
$show_view_all = $total_current_events > 3;

// Extract first 3 events for display
// array_slice preserves array keys and order
$show_limit_events = array_slice($all_events, 0, 3);

// ============================================================================
// QUERY 2: FETCH ALL EVENT STATUS COUNTS
// ============================================================================

/**
 * Count events by their current status across all user's events (including archived).
 * This provides statistics on user's event distribution and workflow state.
 * 
 * Status meaning:
 * - Draft: Event created but not submitted for review
 * - Pending Review: Awaiting admin review of event details
 * - Needs Revision: Admin requested changes
 * - Approved: Event approved, requirements visible to user
 * - Completed: Event concluded (currently unused but tracked)
 */

// Initialize status counters to zero
// These are incremented as we loop through results
$draft_count = 0;
$pending_review_count = 0;
$needs_revision_count = 0;
$approved_count = 0;
$completed_count = 0;

// Fetch all events status (including archived) for this user
// Shows complete status distribution across all user's events
$allUserEventsForCounts = fetchAll(
    $conn,
    "
    SELECT event_status
    FROM events
    WHERE user_id = ?
      AND archived_at IS NULL
    ",
    "i",
    [$user_id]
);

/**
 * Count events by status using switch statement.
 * Iterates through all events and increments appropriate counter.
 */
foreach ($allUserEventsForCounts as $e) {
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

// ============================================================================
// QUERY 3: FETCH UPCOMING DEADLINES
// ============================================================================

/**
 * Retrieve requirement deadlines for active events.
 * Only shows requirements still pending submission (not yet uploaded).
 * Filters to events that haven't ended yet.
 * 
 * Table relationships:
 * - event_requirements: Links events to required document types
 * - events: Event details including status and dates
 * - requirement_templates: Human-readable requirement names/descriptions
 * - event_dates: Event schedule to filter by end date
 * 
 * Display logic:
 * - Must be pending submission (submission_status = 'Pending')
 * - Must have deadline set (NOT NULL)
 * - Event must still be active (end_datetime >= NOW or null)
 * - Event must be in active status (not Draft, not Archived)
 * - Sorted by earliest deadline first (ascending)
 */
$fetchDeadlinesSql = "
    SELECT
        e.event_id,
        e.event_status,
        rt.req_name,
        rt.req_desc,
        e.event_name,
        er.deadline
    FROM event_requirements er
    INNER JOIN events e
        ON er.event_id = e.event_id
    INNER JOIN requirement_templates rt
        ON er.req_template_id = rt.req_template_id
    LEFT JOIN event_dates ed
        ON e.event_id = ed.event_id
    WHERE e.user_id = ?
      AND e.archived_at IS NULL
      AND e.event_status IN ('Pending Review', 'Needs Revision', 'Approved')
      AND er.submission_status = 'Pending'
      AND er.deadline IS NOT NULL
      AND (
            ed.end_datetime IS NULL
            OR ed.end_datetime >= NOW()
          )
    ORDER BY er.deadline ASC
";

$deadlines = fetchAll(
    $conn,
    $fetchDeadlinesSql,
    "i",
    [$user_id]
);

/**
 * Prepare deadline list for dashboard display.
 * Shows first 3 upcoming deadlines, with "View All" link if more exist.
 */
$show_view_all_deadlines = count($deadlines) > 3;
$show_limit_deadlines = array_slice($deadlines, 0, 3);

// ============================================================================
// QUERY 4: COUNT ARCHIVED EVENTS
// ============================================================================

/**
 * Retrieve count of all archived events for the current user.
 * Archived events are completed events moved to archive storage.
 * Shown as a stat card but not displayed in detail on home.
 * 
 * Events are archived by setting archived_at timestamp.
 * This separates completed work from active events for cleaner dashboard.
 */
$countArchivedEventsSql = "
    SELECT COUNT(*) AS total
    FROM events
    WHERE user_id = ? AND archived_at IS NOT NULL
";

$archivedRow = fetchOne(
    $conn,
    $countArchivedEventsSql,
    "i",
    [$user_id]
);

// Extract count from result with null-safe default
// Cast to int to ensure numeric type
$archived_events = (int) ($archivedRow['total'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HAUCREDIT - Dashboard</title>
    <link rel="stylesheet" href="<?= PUBLIC_URL ?>assets/styles/layout.css" />
    <link rel="stylesheet" href="<?= PUBLIC_URL ?>assets/styles/home_styles.css" />
</head>

<body>
    <div class="app">
        <div class="sidebar-overlay" id="sidebarOverlay" hidden></div>

        <?php include PUBLIC_PATH . 'assets/includes/general_nav.php' ?>

        <!-- Main content area -->
        <main class="main">
            <header class="topbar">
                <button class="hamburger" id="menuBtn" type="button" aria-label="Open menu">☰</button>
                <div class="title-wrap">
                    <h1>Dashboard</h1>
                    <p><?= $username ?></p>
                    <p><?= $org_body ?></p>
                </div>
                <div class="home-top-actions">
                    <a class="btn-primary" href="create_event.php">
                        <i class="fa-solid fa-plus"></i> Create Event
                    </a>
                </div>
            </header>

            <section class="home-content">

                <section class="home-stats-grid">
                    <?php
                    $stats = [
                        [
                            'count' => $total_current_events,
                            'label' => 'Current Events',
                            'icon' => 'fa-regular fa-calendar-days',
                            'link' => 'my_events.php'
                        ],
                        [
                            'count' => count($deadlines),
                            'label' => 'Upcoming Deadlines',
                            'icon' => 'fa-solid fa-list-check',
                            'link' => 'requirements.php#req-deadlines'
                        ],
                        [
                            'count' => $overall_progress . '%',
                            'label' => 'Compliance Progress',
                            'icon' => 'fa-solid fa-circle-check',
                            'link' => 'requirements.php'
                        ],
                        [
                            'count' => $archived_events,
                            'label' => 'Archived Events',
                            'icon' => 'fa-regular fa-folder-open',
                            'link' => 'archived_events.php'
                        ]
                    ];

                    foreach ($stats as $s): ?>
                        <a href="<?= htmlspecialchars($s['link']) ?>" class="home-stat-link">
                            <article class="home-stat-card">
                                <div class="home-stat-header">
                                    <div class="home-stat-icon"><i class="<?= htmlspecialchars($s['icon']) ?>"></i></div>
                                </div>
                                <p class="home-stat-value"><?= htmlspecialchars((string) $s['count']) ?></p>
                                <p class="home-stat-label"><?= htmlspecialchars($s['label']) ?></p>
                            </article>
                        </a>
                    <?php endforeach; ?>
                </section>


                <!-- ===== CURRENT EVENTS SECTION ===== -->
                <!-- Lists first 3 active events with progress indicators -->
                <!-- Link to "View All" appears if more than 3 events exist -->
                <section class="home-section">
                    <header class="home-section-header">
                        <h2 class="home-section-title">Current Events</h2>
                        <?php if ($show_view_all): ?>
                            <a href="my_events.php" class="btn-secondary btn-smaller">View All</a>
                        <?php endif; ?>
                    </header>

                    <?php if ($show_limit_events): ?>
                        <ul class="events-table">
                            <?php foreach ($show_limit_events as $event):
                                $total_docs = (int) ($event['docs_total'] ?? 0);
                                $uploaded_docs = (int) ($event['docs_uploaded'] ?? 0);
                                $progress = $total_docs > 0 ? round(($uploaded_docs / $total_docs) * 100) : 0;
                                $progress_color = $progress >= 75 ? '#2e7d32' : ($progress >= 40 ? '#f9a825' : '#d32f2f');
                                $status_class = normalizeStatusClass($event['event_status'] ?? 'Pending Review');
                                ?>
                                <li>
                                    <a class="event-card-container" href="view_event.php?id=<?= (int) $event['event_id'] ?>">
                                        <article class="event-card">
                                            <div class="event-main">
                                                <div class="event-info">
                                                    <div class="event-title"><?= htmlspecialchars($event['event_name'] ?? '') ?>
                                                    </div>
                                                    <div class="event-sub">
                                                        <?= htmlspecialchars($event['venue_platform'] ?? 'No venue set') ?> •
                                                        <?php if (!empty($event['start_datetime'])): ?>
                                                            <time datetime="<?= htmlspecialchars($event['start_datetime']) ?>">
                                                                <?= date("F j, g:i A", strtotime($event['start_datetime'])) ?>
                                                            </time>
                                                        <?php else: ?>
                                                            <span>No schedule set</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>

                                                <div class="event-progress">
                                                    <div class="home-progress-bar-mini">
                                                        <div class="home-progress-fill-mini"
                                                            style="width: <?= $progress ?>%; background: <?= $progress_color ?>;">
                                                        </div>
                                                    </div>
                                                    <span class="home-progress-text"><?= $progress ?>%</span>
                                                </div>
                                            </div>

                                            <span class="home-status-badge <?= htmlspecialchars($status_class) ?>">
                                                <?= htmlspecialchars($event['event_status']) ?>
                                            </span>
                                        </article>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <!-- ===== EMPTY STATE - NO ACTIVE EVENTS ===== -->
                        <!-- Shown when user has no current events -->
                        <!-- Provides helpful message and encouragement to create first event -->
                        <div class="empty-state">
                            <div class="empty-icon">
                                <i class="fa-solid fa-file-circle-xmark"></i>
                            </div>
                            <h3>No events found</h3>
                            <p>Try adjusting your search or filter, or create a new event.</p>
                            <a href="create_event.php" class="btn-primary">
                                Create Event
                            </a>
                        </div>
                    <?php endif; ?>
                </section>


                <!-- ===== UPCOMING DEADLINES SECTION ===== -->
                <!-- Lists first 3 requirement deadlines sorted by urgency -->
                <!-- Shows pending requirements that haven't been uploaded yet -->
                <section class="home-section">
                    <header class="home-section-header">
                        <h2 class="home-section-title">Upcoming Deadlines</h2>
                        <?php if ($show_view_all_deadlines): ?>
                            <a href="requirements.php#req-deadlines" class="btn-secondary btn-smaller">View All</a>
                        <?php endif; ?>
                    </header>

                    <?php if ($show_limit_deadlines): ?>
                        <ul>
                            <?php foreach ($show_limit_deadlines as $d): ?>
                                <li>
                                    <a class="req-card" href="view_event.php?id=<?= (int) $d['event_id'] ?>">
                                        <div class="req-item">
                                            <div class="req-title">
                                                <?= htmlspecialchars($d['req_name'] ?? '') ?>
                                                <?php if (!empty($d['req_desc'])): ?>
                                                    <span class="tooltip-icon">
                                                        <i class="fa-regular fa-circle-question"></i>
                                                        <span class="tooltip-text"><?= htmlspecialchars($d['req_desc']) ?></span>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="req-sub">
                                                From <?= htmlspecialchars($d['event_name'] ?? '') ?>
                                                (<?= htmlspecialchars($d['event_status'] ?? '') ?>) •
                                                <strong>
                                                    <time datetime="<?= htmlspecialchars($d['deadline']) ?>">
                                                        <?= date("F j, g:i A", strtotime($d['deadline'])) ?>
                                                    </time>
                                                </strong>
                                            </div>
                                        </div>
                                        <span class="status pending">Pending</span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <!-- ===== EMPTY STATE FOR DEADLINES ===== -->
                        <div class="empty-state">
                            <div class="empty-icon">
                                <i class="fa-regular fa-calendar-check"></i>
                            </div>
                            <h3>No upcoming deadlines</h3>
                            <p>You're all caught up! No pending requirements at the moment.</p>
                        </div>
                    <?php endif; ?>
                </section>
            </section>

            <?php include PUBLIC_PATH . 'assets/includes/footer.php' ?>
        </main>
    </div>

    <script src="<?= APP_URL ?>script/layout.js?v=1"></script>
</body>

</html>