<?php
// ===== SESSION INITIALIZATION =====
// Start the session to manage user authentication and data
session_start();

// ===== DATABASE CONNECTION =====
// Include the database connection file to establish a connection for queries
require_once "../app/database.php";

// ===== AUTHENTICATION CHECK =====
// Verify that the user is logged in by checking for the user_id in the session
// If not logged in, redirect to the login page and stop execution
if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit();
}

// ===== USER DATA EXTRACTION =====
// Retrieve and sanitize the username and organization body from the session for display
$username = htmlspecialchars($_SESSION["username"], ENT_QUOTES, "UTF-8");
$org_body = htmlspecialchars($_SESSION["org_body"], ENT_QUOTES, "UTF-8");

// ===== USER ID ASSIGNMENT =====
// Get the user ID from the session for use in database queries
$user_id = $_SESSION['user_id'];

// ===== CLEANUP OF EXPIRED ARCHIVED EVENTS =====
// This section automatically deletes events that have been archived for more than 30 days
// It also removes associated uploaded files and requirements to free up storage

// Prepare a query to find events archived more than 30 days ago for the current user
$cleanup = $conn->prepare("
    SELECT event_id
    FROM events
    WHERE archived_at IS NOT NULL
    AND archived_at <= NOW() - INTERVAL 30 DAY
    AND user_id = ?
");

// Bind the user ID parameter and execute the query
$cleanup->bind_param("i", $user_id);
$cleanup->execute();
$res = $cleanup->get_result();

// Loop through each expired event
while ($row = $res->fetch_assoc()) {

    // Get the event ID for this expired event
    $event_id = $row['event_id'];

    // Prepare a query to find all file paths associated with this event's requirements
    $files = $conn->prepare("
        SELECT file_path
        FROM requirements
        WHERE event_id = ?
    ");

    // Bind the event ID and execute the query
    $files->bind_param("i",$event_id);
    $files->execute();
    $fres = $files->get_result();

    // Loop through each file path and delete the physical file if it exists
    while ($file = $fres->fetch_assoc()) {

        if (!empty($file['file_path'])) {

            // Construct the full path to the file (relative to the current directory)
            $path = "../" . $file['file_path'];

            // Check if the file exists and delete it
            if (file_exists($path)) {
                unlink($path);
            }

        }
    }

    // Prepare and execute a query to delete all requirements for this event
    $delReq = $conn->prepare("
        DELETE FROM requirements
        WHERE event_id = ?
    ");

    $delReq->bind_param("i",$event_id);
    $delReq->execute();

    // Prepare and execute a query to delete the event itself
    $delEvent = $conn->prepare("
        DELETE FROM events
        WHERE event_id = ?
    ");

    $delEvent->bind_param("i",$event_id);
    $delEvent->execute();
}

// ===== FETCH ARCHIVED EVENTS =====
// Prepare a query to fetch all archived events for the current user, including progress information
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

// Bind the user ID parameter and execute the query
$stmt->bind_param("i", $user_id);
$stmt->execute();

// Fetch all results as an associative array
$events = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Character encoding and viewport for responsive design -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Page title -->
    <title>HAUCREDIT - Archived</title>
    <!-- Stylesheets for layout and home page styles -->
    <link rel="stylesheet" href="assets/styles/layout.css" />
    <link rel="stylesheet" href="assets/styles/home_styles.css" />
</head>

<body>
    <div class="app">
        <!-- Overlay for sidebar on mobile devices -->
        <div class="sidebar-overlay" id="sidebarOverlay" hidden></div>

        <!-- Include the general navigation component -->
        <?php include 'assets/includes/general_nav.php' ?>

        <main class="main">
            <header class="topbar">
                <!-- Hamburger menu button for mobile navigation -->
                <button class="hamburger" id="menuBtn" type="button" aria-label="Open menu">☰</button>

                <div class="title-wrap">
                    <!-- Main heading and organization body display -->
                    <h1>Archives</h1>
                    <p><?= $org_body ?></p>
                </div>
            </header>

            <!-- Back button -->
            <div class="action-btns">
                <button type="button" class="btn-secondary" onclick="history.back()">
                    Back
                </button>
            </div>

            <!-- Main content section for archived events -->
            <section class="home-section" style="margin-top: 10px;">

                <header class="home-section-header">
                    <!-- Section title -->
                    <h2 class="home-section-title">Archived Events</h2>
                </header>

                <!-- List of archived events -->
                <ul class="events-table">
                    <?php if (!empty($events)): ?>
                        <?php foreach ($events as $event):
                            // Calculate progress percentage for this event
                            $total = $event['total_docs'];
                            $uploaded = $event['uploaded_docs'];
                            $progress = $total ? round(($uploaded / $total) * 100) : 0;

                            // Determine progress bar color based on completion percentage
                            $progress_color = '#d32f2f';
                            if ($progress >= 75)
                                $progress_color = '#2e7d32';
                            elseif ($progress >= 40)
                                $progress_color = '#f9a825';
                            ?>

                            <!-- Link to view the event details -->
                            <a class="event-card-container" href="view_event.php?id=<?= $event['event_id'] ?>">
                                <article class="event-card">
                                    <div class="event-main">
                                        <div class="event-info">
                                            <!-- Event name -->
                                            <div class="event-title"><?= htmlspecialchars($event['event_name']) ?></div>
                                            <!-- Event date and venue -->
                                            <div class="event-sub">
                                                <time datetime="<?= htmlspecialchars($event['start_datetime']) ?>">
                                                    <?= date("F j, g:i A", strtotime($event['start_datetime'])) ?>
                                                </time>
                                                • <?= htmlspecialchars($event['venue_platform']) ?>
                                            </div>
                                        </div>

                                        <!-- Progress bar and percentage -->
                                        <div class="event-progress">
                                            <div class="home-progress-bar-mini">
                                                <div class="home-progress-fill-mini"
                                                    style="width: <?= $progress ?>%; background: <?= $progress_color ?>">
                                                </div>
                                            </div>
                                            <span class="home-progress-text"><?= $progress ?>%</span>
                                        </div>
                                    </div>

                                    <!-- Event phase badge -->
                                    <span class="home-status-badge <?= htmlspecialchars($event['event_phase']) ?>">
                                        <?= ucfirst($event['event_phase']) ?>
                                    </span>
                                </article>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <!-- Message when no archived events exist -->
                        <li>No events has been put to archives.</li>
                    <?php endif; ?>
                </ul>
            </section>
        </main>
    </div>
</body>
</html>