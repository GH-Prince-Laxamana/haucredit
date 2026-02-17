<?php

// 1) Get month/year from URL or default to current
$year  = isset($_GET['y']) ? (int)$_GET['y'] : (int)date('Y');
$month = isset($_GET['m']) ? (int)$_GET['m'] : (int)date('n');

if ($month < 1)  { $month = 1; }
if ($month > 12) { $month = 12; }
if ($year < 1970){ $year = 1970; }
if ($year > 2100){ $year = 2100; }

// 2) Build date helpers
$firstDayTs   = strtotime("$year-$month-01");
$daysInMonth  = (int)date('t', $firstDayTs);
$startWeekday = (int)date('w', $firstDayTs); // 0=Sun..6=Sat
$monthName    = date('F', $firstDayTs);

// prev / next month links
$prevTs = strtotime("-1 month", $firstDayTs);
$nextTs = strtotime("+1 month", $firstDayTs);

$prevY = (int)date('Y', $prevTs);
$prevM = (int)date('n', $prevTs);
$nextY = (int)date('Y', $nextTs);
$nextM = (int)date('n', $nextTs);

// Today
$todayY = (int)date('Y');
$todayM = (int)date('n');
$todayD = (int)date('j');

// Optional: sample events (replace with DB later)
// key format: YYYY-MM-DD
$events = [
  sprintf("%04d-%02d-03", $year, $month) => ["Prep (RNN)", "08:00"],
  sprintf("%04d-%02d-05", $year, $month) => ["Classroom", "10:00"],
  sprintf("%04d-%02d-07", $year, $month) => ["Exam", "10:00 - 11:00"],
  sprintf("%04d-%02d-11", $year, $month) => ["Presentation", "08:00"],
  sprintf("%04d-%02d-20", $year, $month) => ["Meeting", "09:00 - 10:00"],
];

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
        <aside class="sidebar">
            <div class="brand">
            <div class="avatar" aria-hidden="true"></div>
        </div>

        <nav class="nav">
            <a class="nav-item" href="index.php"><span class="icon" aria-hidden="true"></span><span>Dashboard</span></a>
            <a class="nav-item" href="create_event.php"><span class="icon" aria-hidden="true"></span><span>Create Event</span></a>
            <a class="nav-item active" href="calendar.php"><span class="icon" aria-hidden="true"></span><span>Calendar</span></a>
            <a class="nav-item" href="about.php"><span class="icon" aria-hidden="true"></span><span>About Us</span></a>
        </nav>

        <div class="account">
            <button class="account-btn" type="button">
            <span class="user-dot" aria-hidden="true"></span>
            <span>Account Name</span>
            </button>
        </div>
        </aside>

        <!-- MAIN -->
        <main class="main">
        <header class="topbar">
            <div class="title-wrap">
            <h1>Calendar</h1>
            <p>Text Here</p>
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

                <button class="cal-add" type="button">
                <span class="plus" aria-hidden="true">+</span> Add
                </button>
            </div>

            <div class="cal-monthrow">
                <div class="cal-month">
                <strong><?php echo htmlspecialchars($monthName . " " . $year); ?></strong>

                <a class="cal-navbtn" href="?y=<?php echo $prevY; ?>&m=<?php echo $prevM; ?>" aria-label="Previous month">◀</a>
                <a class="cal-navbtn" href="?y=<?php echo $nextY; ?>&m=<?php echo $nextM; ?>" aria-label="Next month">▶</a>
                </div>
            </div>

            <div class="cal-grid">
                <!-- Header -->
                <?php
                $weekdays = ["Sun","Mon","Tue","Wed","Thu","Fri","Sat"];
                foreach($weekdays as $wd){
                    echo '<div class="cal-cell head">'.$wd.'</div>';
                }
                ?>

                <?php
                $totalCells = 42;

                // Determine previous month days to fill leading blanks
                $prevMonthTs = strtotime("-1 month", $firstDayTs);
                $daysInPrev  = (int)date('t', $prevMonthTs);

                for ($cell = 0; $cell < $totalCells; $cell++){
                    $dayNum = $cell - $startWeekday + 1;

                    $classes = "cal-cell";
                    $label   = "";
                    $dateKey = "";
                    $isToday = false;

                    // Leading days (previous month)
                    if ($dayNum < 1){
                    $prevDay = $daysInPrev + $dayNum;
                    $classes .= " muted";
                    $label = $prevDay;
                    }
                    // Current month
                    else if ($dayNum >= 1 && $dayNum <= $daysInMonth){
                    $label = $dayNum;
                    $dateKey = sprintf("%04d-%02d-%02d", $year, $month, $dayNum);

                    // today indicator
                    if ($year === $todayY && $month === $todayM && $dayNum === $todayD){
                        $classes .= " is-today";
                        $isToday = true;
                    }
                    }
                    // Trailing days (next month)
                    else{
                    $nextDay = $dayNum - $daysInMonth;
                    $classes .= " muted";
                    $label = $nextDay;
                    }

                    echo '<div class="'.htmlspecialchars($classes).'">';
                    echo '<span class="day">'.htmlspecialchars((string)$label).'</span>';

                    // Event pill (only for current month days)
                    if ($dateKey && isset($events[$dateKey])){
                    $evtTitle = $events[$dateKey][0];
                    $evtTime  = $events[$dateKey][1];
                    echo '<div class="pill">';
                    echo '<div class="pill-title">'.htmlspecialchars($evtTitle).'</div>';
                    echo '<div class="pill-time">'.htmlspecialchars($evtTime).'</div>';
                    echo '</div>';
                    }

                    // Today circle
                    if ($isToday){
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
                <div class="t-row"><span>Text here</span><span class="t-score">0/0</span></div>
                <div class="t-row"><span>Text here</span><span class="t-score">0/0</span></div>
                <div class="t-row"><span>Text here</span><span class="t-score">0/0</span></div>
            </div>
            </aside>

        </section>
        </main>
    </div>
    </body>
</html>
