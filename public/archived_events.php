<?php
session_start();
require_once "../app/database.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit();
}

$username = htmlspecialchars($_SESSION["username"], ENT_QUOTES, "UTF-8");
$org_body = htmlspecialchars($_SESSION["org_body"], ENT_QUOTES, "UTF-8");
$user_id = (int) $_SESSION['user_id'];

/* ================= CLEANUP OF EXPIRED ARCHIVED EVENTS =================
   Delete archived events older than 30 days for this user.
   Pull uploaded files from requirement_files through event_requirements.
*/
$fetchExpiredArchivedEventsSql = "
    SELECT event_id
    FROM events
    WHERE archived_at IS NOT NULL
      AND archived_at <= NOW() - INTERVAL 30 DAY
      AND user_id = ?
";

$expiredArchivedEvents = fetchAll(
    $conn,
    $fetchExpiredArchivedEventsSql,
    "i",
    [$user_id]
);

foreach ($expiredArchivedEvents as $row) {
    $event_id = (int) $row['event_id'];

    $fetchRequirementFilePathsSql = "
        SELECT rf.file_path
        FROM requirement_files rf
        INNER JOIN event_requirements er
            ON rf.event_req_id = er.event_req_id
        WHERE er.event_id = ?
          AND rf.file_path IS NOT NULL
          AND rf.file_path != ''
    ";

    $fileRows = fetchAll(
        $conn,
        $fetchRequirementFilePathsSql,
        "i",
        [$event_id]
    );

    $filePaths = [];
    foreach ($fileRows as $file) {
        $filePaths[] = $file['file_path'];
    }

    try {
        $conn->begin_transaction();

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

        foreach ($filePaths as $relativePath) {
            $path = __DIR__ . "/../" . ltrim($relativePath, "/");
            if (is_file($path)) {
                @unlink($path);
            }
        }
    } catch (Exception $e) {
        $conn->rollback();
    }
}

/* ================= FETCH ARCHIVED EVENTS =================
   Uses:
   - events
   - event_dates
   - event_location
   - events.docs_total / docs_uploaded
*/
$fetchArchivedEventsSql = "
    SELECT
        e.event_id,
        e.event_name,
        e.docs_total,
        e.docs_uploaded,
        e.archived_at,
        ed.start_datetime,
        ed.end_datetime,
        el.venue_platform,
        CASE
            WHEN e.docs_total > 0 AND e.docs_uploaded < e.docs_total THEN 'pending'
            WHEN ed.end_datetime IS NOT NULL AND ed.end_datetime >= NOW() THEN 'active'
            ELSE 'completed'
        END AS event_phase
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
    <link rel="stylesheet" href="assets/styles/layout.css" />
    <link rel="stylesheet" href="assets/styles/home_styles.css" />
</head>

<body>
    <div class="app">
        <div class="sidebar-overlay" id="sidebarOverlay" hidden></div>

        <?php include 'assets/includes/general_nav.php' ?>

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

                <ul class="events-table">
                    <?php if (!empty($events)): ?>
                        <?php foreach ($events as $event):
                            $total = (int) ($event['docs_total'] ?? 0);
                            $uploaded = (int) ($event['docs_uploaded'] ?? 0);
                            $progress = $total > 0 ? round(($uploaded / $total) * 100) : 0;

                            $progress_color = '#d32f2f';
                            if ($progress >= 75) {
                                $progress_color = '#2e7d32';
                            } elseif ($progress >= 40) {
                                $progress_color = '#f9a825';
                            }
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

                                        <span class="home-status-badge <?= htmlspecialchars($event['event_phase']) ?>">
                                            <?= ucfirst($event['event_phase']) ?>
                                        </span>
                                    </article>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li>No events have been put into archives.</li>
                    <?php endif; ?>
                </ul>
            </section>
        </main>
    </div>

    <script src="../app/script/layout.js?v=1"></script>
</body>

</html>