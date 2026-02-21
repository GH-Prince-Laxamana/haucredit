<?php
/* UNDER CONSTRUCTION (Continue after PHP functions have been finalzied)*/

session_start();
require_once "../app/database.php";
require_once "../app/security_headers.php";
send_security_headers();

if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit();
}

$username = htmlspecialchars($_SESSION["username"], ENT_QUOTES, "UTF-8");

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

$firstDayTs = strtotime("$year-$month-01");
$daysInMonth = (int) date('t', $firstDayTs);
$startWeekday = (int) date('w', $firstDayTs);
$monthName = date('F', $firstDayTs);

$prevTs = strtotime("-1 month", $firstDayTs);
$nextTs = strtotime("+1 month", $firstDayTs);

$prevY = (int) date('Y', $prevTs);
$prevM = (int) date('n', $prevTs);
$nextY = (int) date('Y', $nextTs);
$nextM = (int) date('n', $nextTs);

$todayY = (int) date('Y');
$todayM = (int) date('n');
$todayD = (int) date('j');

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

        <?php include 'assets/includes/general_nav.php' ?>

        <main class="main">
            <header class="topbar">
                <div class="title-wrap">
                    <h1>Calendar</h1>
                    <p>Text Here</p>
                </div>
            </header>

            <section class="content calendar-page">
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

                            <a class="cal-navbtn" href="?y=<?php echo $prevY; ?>&m=<?php echo $prevM; ?>"
                                aria-label="Previous month">◀</a>
                            <a class="cal-navbtn" href="?y=<?php echo $nextY; ?>&m=<?php echo $nextM; ?>"
                                aria-label="Next month">▶</a>
                        </div>
                    </div>

                    <div class="cal-grid">
                        <?php
                        $weekdays = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];
                        foreach ($weekdays as $wd) {
                            echo '<div class="cal-cell head">' . $wd . '</div>';
                        }
                        ?>

                        <?php
                        $totalCells = 42;

                        $prevMonthTs = strtotime("-1 month", $firstDayTs);
                        $daysInPrev = (int) date('t', $prevMonthTs);

                        for ($cell = 0; $cell < $totalCells; $cell++) {
                            $dayNum = $cell - $startWeekday + 1;

                            $classes = "cal-cell";
                            $label = "";
                            $dateKey = "";
                            $isToday = false;

                            if ($dayNum < 1) {
                                $prevDay = $daysInPrev + $dayNum;
                                $classes .= " muted";
                                $label = $prevDay;
                            } else if ($dayNum >= 1 && $dayNum <= $daysInMonth) {
                                $label = $dayNum;
                                $dateKey = sprintf("%04d-%02d-%02d", $year, $month, $dayNum);

                                if ($year === $todayY && $month === $todayM && $dayNum === $todayD) {
                                    $classes .= " is-today";
                                    $isToday = true;
                                }
                            } else {
                                $nextDay = $dayNum - $daysInMonth;
                                $classes .= " muted";
                                $label = $nextDay;
                            }

                            echo '<div class="' . htmlspecialchars($classes) . '">';
                            echo '<span class="day">' . htmlspecialchars((string) $label) . '</span>';

                            if ($dateKey && isset($events[$dateKey])) {
                                $evtTitle = $events[$dateKey][0];
                                $evtTime = $events[$dateKey][1];
                                echo '<div class="pill">';
                                echo '<div class="pill-title">' . htmlspecialchars($evtTitle) . '</div>';
                                echo '<div class="pill-time">' . htmlspecialchars($evtTime) . '</div>';
                                echo '</div>';
                            }

                            if ($isToday) {
                                echo '<div class="today" aria-hidden="true"></div>';
                            }

                            echo '</div>';
                        }
                        ?>
                    </div>
                </div>

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

        <?php include 'assets/includes/footer.php' ?>

        </main>
    </div>
</body>

</html>