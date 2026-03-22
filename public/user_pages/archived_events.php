<?php
session_start();
require_once __DIR__ . '/../../app/database.php';

requireLogin();

$username = htmlspecialchars($_SESSION["username"], ENT_QUOTES, "UTF-8");
$org_body = htmlspecialchars($_SESSION["org_body"], ENT_QUOTES, "UTF-8");
$user_id = (int) $_SESSION['user_id'];

/* ================= HELPERS ================= */
function normalizeEventStatusClass(string $status): string
{
    return strtolower(str_replace(' ', '-', $status));
}

// ==================== CLEANUP OF EXPIRED ARCHIVED EVENTS ====================
// Automatically delete archived events older than 30 days for this user
// This prevents the archive from accumulating too much data over time
// Process:
//   1. Query for events archived >30 days ago
//   2. For each event, find all uploaded requirement files
//   3. Delete event from database (transaction-protected)
//   4. Delete uploaded files from file system

// First, fetch IDs of archived events older than 30 days
$fetchExpiredArchivedEventsSql = "
    SELECT event_id
    FROM events
    WHERE archived_at IS NOT NULL
      AND archived_at <= NOW() - INTERVAL 30 DAY
      AND user_id = ?
";

// Execute query to get all expired archived events for this user
$expiredArchivedEvents = fetchAll(
    $conn,
    $fetchExpiredArchivedEventsSql,
    "i",
    [$user_id]
);

// For each expired event, delete its files and database record
foreach ($expiredArchivedEvents as $row) {
    // Extract event ID as integer from query result
    $event_id = (int) $row['event_id'];

    // Query to find all uploaded requirement files for this event
    // This uses INNER JOINs to only find files actually associated with this event
    $fetchRequirementFilePathsSql = "
        SELECT rf.file_path
        FROM requirement_files rf
        INNER JOIN event_requirements er
            ON rf.event_req_id = er.event_req_id
        WHERE er.event_id = ?
          AND rf.file_path IS NOT NULL
          AND rf.file_path != ''
    ";

    // Execute file query to get paths of all files for this event
    $fileRows = fetchAll(
        $conn,
        $fetchRequirementFilePathsSql,
        "i",
        [$event_id]
    );

    // Extract file paths from query results into simple array
    $filePaths = [];
    foreach ($fileRows as $file) {
        $filePaths[] = $file['file_path'];
    }

    try {
        $conn->begin_transaction();

        // SQL to delete the event from database
        // Multiple WHERE conditions for safety:
        //   - Identifies specific event
        //   - Ensures it's this user's event (security)
        //   - Confirms event is actually archived (safety check)
        $deleteExpiredArchivedEventSql = "
            DELETE FROM events
            WHERE event_id = ?
              AND user_id = ?
              AND archived_at IS NOT NULL
        ";

        execQuery(
            $conn,
            $deleteExpiredArchivedEventSql,
            "ii",
            [$event_id, $user_id]
        );

        $conn->commit();

        // After successful database deletion, delete uploaded files from file system
        // This runs after commit so if files can't be deleted, at least DB is cleaned
        foreach ($filePaths as $relativePath) {
            // Convert relative path (e.g., 'uploads/requirements/file.pdf')
            // to absolute file system path
            $path = __DIR__ . "/../" . ltrim($relativePath, "/");
            // Check if file exists before attempting deletion
            if (is_file($path)) {
                // @unlink suppresses PHP warnings if file doesn't exist or can't be deleted
                @unlink($path);
            }
        }
    } catch (Exception $e) {
        $conn->rollback();
    }
}

// ==================== FETCH ACTIVE ARCHIVED EVENTS ====================
// Query to retrieve all non-deleted archived events for display to the user
$fetchArchivedEventsSql = "
    SELECT
        e.event_id,
        e.event_name,
        e.docs_total,
        e.docs_uploaded,
        e.event_status,
        e.archived_at,
        ed.start_datetime,
        ed.end_datetime,
        el.venue_platform
    FROM events e
    LEFT JOIN event_dates ed
        ON e.event_id = ed.event_id
    LEFT JOIN event_location el
        ON e.event_id = el.event_id
    WHERE e.user_id = ?
      AND e.archived_at IS NOT NULL
    ORDER BY
        e.archived_at DESC,
        CASE WHEN ed.start_datetime IS NULL THEN 1 ELSE 0 END,
        ed.start_datetime ASC
";

$events = fetchAll(
    $conn,
    $fetchArchivedEventsSql,
    "i",
    [$user_id]
);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HAUCREDIT - Archived</title>
    <link rel="stylesheet" href="<?= PUBLIC_URL ?>assets/styles/layout.css" />
    <link rel="stylesheet" href="<?= PUBLIC_URL ?>assets/styles/home_styles.css" />
</head>

<body>
    <div class="app">
        <div class="sidebar-overlay" id="sidebarOverlay" hidden></div>

        <?php include PUBLIC_PATH . 'assets/includes/general_nav.php' ?>

        <!-- ==================== MAIN CONTENT AREA ==================== -->
        <main class="main">
            <header class="topbar">
                <button class="hamburger" id="menuBtn" type="button" aria-label="Open menu">☰</button>

                <div class="title-wrap">
                    <h1>Archives</h1>
                    <p><?= $org_body ?></p>
                </div>
            </header>

            <div class="action-btns">
                <button type="button" class="btn-secondary" onclick="history.back()">
                    Back
                </button>
            </div>

            <section class="home-section" style="margin-top: 10px;">
                <header class="home-section-header">
                    <h2 class="home-section-title">Archived Events</h2>
                </header>

                <!-- ========== EVENT LIST ========== -->
                <ul class="events-table">
                    <?php if (!empty($events)): ?>
                        <?php foreach ($events as $event): ?>
                            <?php
                            $total = (int) ($event['docs_total'] ?? 0);
                            $uploaded = (int) ($event['docs_uploaded'] ?? 0);
                            $progress = $total > 0 ? round(($uploaded / $total) * 100) : 0;

                            $progress_color = '#992525';
                            if ($progress >= 75) {
                                $progress_color = '#1e5221';
                            } elseif ($progress >= 40) {
                                $progress_color = '#9c6a18';
                            }

                            $status_class = normalizeEventStatusClass($event['event_status'] ?? 'Draft');
                            ?>
                            <li>
                                <a class="event-card-container" href="view_event.php?id=<?= (int) $event['event_id'] ?>">
                                    <article class="event-card">
                                        <div class="event-main">
                                            <div class="event-info">
                                                <div class="event-title"><?= htmlspecialchars($event['event_name']) ?></div>

                                                <div class="event-sub">
                                                    <?php if (!empty($event['start_datetime'])): ?>
                                                        <time datetime="<?= htmlspecialchars($event['start_datetime']) ?>">
                                                            <?= date("F j, g:i A", strtotime($event['start_datetime'])) ?>
                                                        </time>
                                                    <?php else: ?>
                                                        <span>No schedule set</span>
                                                    <?php endif; ?>

                                                    • <?= htmlspecialchars($event['venue_platform'] ?? 'No venue set') ?>
                                                </div>

                                                <div class="event-sub">
                                                    Archived on
                                                    <strong>
                                                        <?= !empty($event['archived_at']) ? date("F j, Y g:i A", strtotime($event['archived_at'])) : 'N/A' ?>
                                                    </strong>
                                                </div>
                                            </div>

                                            <div class="event-progress">
                                                <div class="home-progress-bar-mini">
                                                    <div class="home-progress-fill-mini"
                                                        style="width: <?= $progress ?>%; background: <?= $progress_color ?>">
                                                    </div>
                                                </div>
                                                <span class="home-progress-text"><?= $progress ?>%</span>
                                            </div>
                                        </div>

                                        <span class="home-status-badge <?= htmlspecialchars($status_class) ?>">
                                            <?= htmlspecialchars($event['event_status'] ?? 'Unknown') ?>
                                        </span>
                                    </article>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-icon">
                                <i class="fa-regular fa-folder-open"></i>
                            </div>
                            <h3>No archived events</h3>
                            <p>Your archived events will appear here when you archive them.</p>
                        </div>
                    <?php endif; ?>
                </ul>
            </section>
        </main>
    </div>

    <script src="<?= APP_URL ?>script/layout.js?v=1"></script>
</body>

</html>