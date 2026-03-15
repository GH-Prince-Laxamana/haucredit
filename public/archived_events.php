<?php
session_start();
require_once "../app/database.php";


if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit();
}

$username = htmlspecialchars($_SESSION["username"], ENT_QUOTES, "UTF-8");
$org_body = htmlspecialchars($_SESSION["org_body"], ENT_QUOTES, "UTF-8");

$user_id = $_SESSION['user_id'];

$cleanup = $conn->prepare("
    SELECT event_id
    FROM events
    WHERE archived_at IS NOT NULL
    AND archived_at <= NOW() - INTERVAL 30 DAY
    AND user_id = ?
");

$cleanup->bind_param("i", $user_id);
$cleanup->execute();
$res = $cleanup->get_result();

while ($row = $res->fetch_assoc()) {

    $event_id = $row['event_id'];

    $files = $conn->prepare("
        SELECT file_path
        FROM requirements
        WHERE event_id = ?
    ");

    $files->bind_param("i",$event_id);
    $files->execute();
    $fres = $files->get_result();

    while ($file = $fres->fetch_assoc()) {

        if (!empty($file['file_path'])) {

            $path = "../" . $file['file_path'];

            if (file_exists($path)) {
                unlink($path);
            }

        }
    }

    $delReq = $conn->prepare("
        DELETE FROM requirements
        WHERE event_id = ?
    ");

    $delReq->bind_param("i",$event_id);
    $delReq->execute();

    $delEvent = $conn->prepare("
        DELETE FROM events
        WHERE event_id = ?
    ");

    $delEvent->bind_param("i",$event_id);
    $delEvent->execute();
}

$stmt = $conn->prepare("
        SELECT 
            e.event_id,
            e.event_name,
            e.start_datetime,
            e.end_datetime,
            e.venue_platform,

            COUNT(r.req_id) AS total_docs,
            SUM(CASE WHEN r.doc_status = 'uploaded' THEN 1 ELSE 0 END) AS uploaded_docs,

            CASE
                WHEN SUM(CASE WHEN r.doc_status = 'uploaded' THEN 1 ELSE 0 END) < COUNT(r.req_id)
                    THEN 'pending'
                WHEN e.end_datetime >= NOW()
                    THEN 'active'
                ELSE 'completed'
            END AS event_phase

        FROM events e
        LEFT JOIN requirements r ON r.event_id = e.event_id

        WHERE e.user_id = ? 
        AND e.archived_at IS NOT NULL

        GROUP BY e.event_id
        ORDER BY e.start_datetime ASC
");

$stmt->bind_param("i", $user_id);
$stmt->execute();

$events = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
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
                            $total = $event['total_docs'];
                            $uploaded = $event['uploaded_docs'];
                            $progress = $total ? round(($uploaded / $total) * 100) : 0;

                            $progress_color = '#d32f2f';
                            if ($progress >= 75)
                                $progress_color = '#2e7d32';
                            elseif ($progress >= 40)
                                $progress_color = '#f9a825';
                            ?>

                            <a class="event-card-container" href="view_event.php?id=<?= $event['event_id'] ?>">
                                <article class="event-card">
                                    <div class="event-main">
                                        <div class="event-info">
                                            <div class="event-title"><?= htmlspecialchars($event['event_name']) ?></div>
                                            <div class="event-sub">
                                                <time datetime="<?= htmlspecialchars($event['start_datetime']) ?>">
                                                    <?= date("F j, g:i A", strtotime($event['start_datetime'])) ?>
                                                </time>
                                                • <?= htmlspecialchars($event['venue_platform']) ?>
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
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li>No events has been put to archives.</li>
                    <?php endif; ?>
                </ul>
            </section>
        </main>
    </div>
</body>