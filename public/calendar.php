<?php
session_start(); // <-- MUST be at the top

require_once "../app/database.php";
require_once "../app/security_headers.php";
send_security_headers();

if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit();
}

$username = htmlspecialchars($_SESSION["username"], ENT_QUOTES, "UTF-8");

// 1) Get month/year from URL or default to current
$year = isset($_GET['y']) ? (int) $_GET['y'] : (int) date('Y');
$month = isset($_GET['m']) ? (int) $_GET['m'] : (int) date('n');

if ($month < 1) {
    $month = 1;
}
if ($month > 12) {
    $month = 12;
}
if ($year < 1970) {
    $year = 1970;
}
if ($year > 2100) {
    $year = 2100;
}

// 2) Build date helpers
$firstDayTs = strtotime("$year-$month-01");
$daysInMonth = (int) date('t', $firstDayTs);
$startWeekday = (int) date('w', $firstDayTs); // 0=Sun..6=Sat
$monthName = date('F', $firstDayTs);

// prev / next month links
$prevTs = strtotime("-1 month", $firstDayTs);
$nextTs = strtotime("+1 month", $firstDayTs);

$prevY = (int) date('Y', $prevTs);
$prevM = (int) date('n', $prevTs);
$nextY = (int) date('Y', $nextTs);
$nextM = (int) date('n', $nextTs);

// Today
$todayY = (int) date('Y');
$todayM = (int) date('n');
$todayD = (int) date('j');

// Fetch events from database
$events = [];
$user_id = $_SESSION["user_id"];

$startDate = sprintf("%04d-%02d-01", $year, $month);
$endDate = sprintf("%04d-%02d-%02d", $year, $month, $daysInMonth);

$stmt = $conn->prepare("
    SELECT event_name, start_date, start_time, end_time
    FROM events 
    WHERE user_id = ? 
    AND start_date BETWEEN ? AND ?
    ORDER BY start_date, start_time
");

$stmt->bind_param("iss", $user_id, $startDate, $endDate);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $dateKey = $row['start_date'];
    $title = $row['event_name'];
    
    // Format time
    if ($row['end_time']) {
        $time = date('H:i', strtotime($row['start_time'])) . ' - ' . date('H:i', strtotime($row['end_time']));
    } else {
        $time = date('H:i', strtotime($row['start_time']));
    }
    
    // Store in same format as original
    $events[$dateKey] = [$title, $time];
}

$stmt->close();

// Calculate progress metrics for tracker
$totalEvents = 0;
$completedEvents = 0;
$upcomingEvents = 0;

$totalStmt = $conn->prepare("SELECT COUNT(*) as total FROM events WHERE user_id = ?");
$totalStmt->bind_param("i", $user_id);
$totalStmt->execute();
$totalResult = $totalStmt->get_result();
$totalEvents = $totalResult->fetch_assoc()['total'] ?? 0;
$totalStmt->close();

$completedStmt = $conn->prepare("SELECT COUNT(*) as completed FROM events WHERE user_id = ? AND start_date < CURDATE()");
$completedStmt->bind_param("i", $user_id);
$completedStmt->execute();
$completedResult = $completedStmt->get_result();
$completedEvents = $completedResult->fetch_assoc()['completed'] ?? 0;
$completedStmt->close();

$upcomingStmt = $conn->prepare("SELECT COUNT(*) as upcoming FROM events WHERE user_id = ? AND start_date >= CURDATE()");
$upcomingStmt->bind_param("i", $user_id);
$upcomingStmt->execute();
$upcomingResult = $upcomingStmt->get_result();
$upcomingEvents = $upcomingResult->fetch_assoc()['upcoming'] ?? 0;
$upcomingStmt->close();

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Calendar</title>
    <link rel="stylesheet" href="assets/styles/layout.css" />
</head>

<body>
    <div class="app">
        <!-- SIDEBAR -->
        <?php include 'assets/includes/general_nav.php' ?>

        <!-- MAIN -->
        <main class="main">
            <header class="topbar">
                <div class="title-wrap">
                    <h1>Calendar</h1>
                    <p>View and manage your scheduled events</p>
                </div>
            </header>

            <section class="content calendar-page">

                <!-- CALENDAR CARD -->
                <div class="cal-card">
                    <div class="cal-top">
                        <div class="cal-tabs">
                            <button class="cal-tab active" type="button">Month</button>
                            <button class="cal-tab" type="button" disabled>Week</button>
                            <button class="cal-tab" type="button" disabled>Day</button>
                        </div>

                        <a href="create_event.php" class="cal-add">
                            <span class="plus" aria-hidden="true">+</span> Add
                        </a>
                    </div>

                    <div class="cal-monthrow">
                        <div class="cal-month">
                            <strong><?php echo htmlspecialchars($monthName . " " . $year); ?></strong>

                            <a class="cal-navbtn" href="?y=<?php echo $prevY; ?>&m=<?php echo $prevM; ?>"
                                aria-label="Previous month">◀</a>
                            <a class="cal-navbtn" href="?y=<?php echo $nextY; ?>&m=<?php echo $nextM; ?>"
                                aria-label="Next month">▶</a>
                        </div>
                    </div>

                    <div class="cal-grid">
                        <!-- Header -->
                        <?php
                        $weekdays = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];
                        foreach ($weekdays as $wd) {
                            echo '<div class="cal-cell head">' . $wd . '</div>';
                        }
                        ?>

                        <?php
                        $totalCells = 42;

                        // Determine previous month days to fill leading blanks
                        $prevMonthTs = strtotime("-1 month", $firstDayTs);
                        $daysInPrev = (int) date('t', $prevMonthTs);

                        for ($cell = 0; $cell < $totalCells; $cell++) {
                            $dayNum = $cell - $startWeekday + 1;

                            $classes = "cal-cell";
                            $label = "";
                            $dateKey = "";
                            $isToday = false;

                            // Leading days (previous month)
                            if ($dayNum < 1) {
                                $prevDay = $daysInPrev + $dayNum;
                                $classes .= " muted";
                                $label = $prevDay;
                            }
                            // Current month
                            else if ($dayNum >= 1 && $dayNum <= $daysInMonth) {
                                $label = $dayNum;
                                $dateKey = sprintf("%04d-%02d-%02d", $year, $month, $dayNum);

                                // today indicator
                                if ($year === $todayY && $month === $todayM && $dayNum === $todayD) {
                                    $classes .= " is-today";
                                    $isToday = true;
                                }
                            }
                            // Trailing days (next month)
                            else {
                                $nextDay = $dayNum - $daysInMonth;
                                $classes .= " muted";
                                $label = $nextDay;
                            }

                            echo '<div class="' . htmlspecialchars($classes) . '">';
                            echo '<span class="day">' . htmlspecialchars((string) $label) . '</span>';

                            // Event pill (only for current month days)
                            if ($dateKey && isset($events[$dateKey])) {
                                $evtTitle = $events[$dateKey][0];
                                $evtTime = $events[$dateKey][1];
                                echo '<div class="pill">';
                                echo '<div class="pill-title">' . htmlspecialchars($evtTitle) . '</div>';
                                echo '<div class="pill-time">' . htmlspecialchars($evtTime) . '</div>';
                                echo '</div>';
                            }

                            // Today circle
                            if ($isToday) {
                                echo '<div class="today" aria-hidden="true"></div>';
                            }

                            echo '</div>';
                        }
                        ?>
                    </div>
                </div>

                <!-- PROGRESS TRACKER -->
                <aside class="tracker">
                    <h2>Progress<br>Tracker</h2>

                    <div class="ring" aria-hidden="true">
                        <div class="ring-inner"></div>
                    </div>

                    <div class="tracker-list">
                        <div class="t-row"><span>Total Events</span><span class="t-score"><?php echo $totalEvents; ?></span></div>
                        <div class="t-row"><span>Completed</span><span class="t-score"><?php echo $completedEvents; ?></span></div>
                        <div class="t-row"><span>Upcoming</span><span class="t-score"><?php echo $upcomingEvents; ?></span></div>
                    </div>
                </aside>

            </section>
        </main>
    </div>
</body>

</html>