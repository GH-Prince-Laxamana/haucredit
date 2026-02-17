<?php
session_start();

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

// Fetch events from database for current month
$events = [];
$user_id = $_SESSION["user_id"];

$startDate = sprintf("%04d-%02d-01", $year, $month);
$endDate = sprintf("%04d-%02d-%02d", $year, $month, $daysInMonth);

$stmt = $conn->prepare("
    SELECT event_name, nature, start_date, start_time, end_time, activity_type, is_extraneous
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
    $nature = $row['nature'];
    
    // Format time display
    if ($row['end_time']) {
        $time = date('H:i', strtotime($row['start_time'])) . ' - ' . date('H:i', strtotime($row['end_time']));
    } else {
        $time = date('H:i', strtotime($row['start_time']));
    }
    
    // Store event (can have multiple events per day)
    if (!isset($events[$dateKey])) {
        $events[$dateKey] = [];
    }
    
    $events[$dateKey][] = [
        'title' => $title,
        'nature' => $nature,
        'time' => $time,
        'type' => $row['activity_type']
    ];
}

$stmt->close();

// Calculate progress metrics
$totalEvents = 0;
$completedEvents = 0;
$upcomingEvents = 0;

// Total events
$metricsStmt = $conn->prepare("SELECT COUNT(*) as total FROM events WHERE user_id = ?");
$metricsStmt->bind_param("i", $user_id);
$metricsStmt->execute();
$metricsResult = $metricsStmt->get_result();
$totalEvents = $metricsResult->fetch_assoc()['total'] ?? 0;
$metricsStmt->close();

// Completed events (past events)
$completedStmt = $conn->prepare("SELECT COUNT(*) as completed FROM events WHERE user_id = ? AND start_date < CURDATE()");
$completedStmt->bind_param("i", $user_id);
$completedStmt->execute();
$completedResult = $completedStmt->get_result();
$completedEvents = $completedResult->fetch_assoc()['completed'] ?? 0;
$completedStmt->close();

// Upcoming events
$upcomingStmt = $conn->prepare("SELECT COUNT(*) as upcoming FROM events WHERE user_id = ? AND start_date >= CURDATE()");
$upcomingStmt->bind_param("i", $user_id);
$upcomingStmt->execute();
$upcomingResult = $upcomingStmt->get_result();
$upcomingEvents = $upcomingResult->fetch_assoc()['upcoming'] ?? 0;
$upcomingStmt->close();

// Calculate compliance percentage
$complianceRate = $totalEvents > 0 ? round(($completedEvents / $totalEvents) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>HAUCREDIT - Calendar</title>
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

                            // Event pills (only for current month days)
                            if ($dateKey && isset($events[$dateKey])) {
                                foreach ($events[$dateKey] as $evt) {
                                    echo '<div class="pill">';
                                    echo '<div class="pill-title">' . htmlspecialchars($evt['title']) . '</div>';
                                    echo '<div class="pill-time">' . htmlspecialchars($evt['time']) . '</div>';
                                    echo '</div>';
                                }
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
                        <svg viewBox="0 0 100 100" style="width: 100%; height: 100%;">
                            <circle cx="50" cy="50" r="40" fill="none" stroke="#e8dcc8" stroke-width="8"/>
                            <circle cx="50" cy="50" r="40" fill="none" stroke="#c2a14d" stroke-width="8"
                                stroke-dasharray="<?php echo $complianceRate * 2.51; ?> 251"
                                transform="rotate(-90 50 50)"
                                style="transition: stroke-dasharray 0.3s ease;"/>
                            <text x="50" y="50" text-anchor="middle" dy="7" font-size="20" font-weight="bold" fill="#4b0014">
                                <?php echo $complianceRate; ?>%
                            </text>
                        </svg>
                    </div>

                    <div class="tracker-list">
                        <div class="t-row">
                            <span>Total Events</span>
                            <span class="t-score"><?php echo $totalEvents; ?></span>
                        </div>
                        <div class="t-row">
                            <span>Completed</span>
                            <span class="t-score"><?php echo $completedEvents; ?>/<?php echo $totalEvents; ?></span>
                        </div>
                        <div class="t-row">
                            <span>Upcoming</span>
                            <span class="t-score"><?php echo $upcomingEvents; ?></span>
                        </div>
                    </div>
                </aside>

            </section>
        </main>
    </div>
</body>

</html>