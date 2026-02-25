<?php
session_start();
require_once "../app/database.php";
require_once "../app/security_headers.php";
send_security_headers();

if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit();
}

$user_id = (int) $_SESSION["user_id"];

/* CSRF */
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION["csrf_token"];

/* Month nav */
$year  = isset($_GET['y']) ? (int) $_GET['y'] : (int) date('Y');
$month = isset($_GET['m']) ? (int) $_GET['m'] : (int) date('n');

$month = max(1, min(12, $month));
$year  = max(1970, min(2100, $year));

$firstDayTs   = strtotime("$year-$month-01");
$daysInMonth  = (int) date('t', $firstDayTs);
$startWeekday = (int) date('w', $firstDayTs);
$monthName    = date('F', $firstDayTs);

$prevTs = strtotime("-1 month", $firstDayTs);
$nextTs = strtotime("+1 month", $firstDayTs);
$prevY = (int) date('Y', $prevTs);
$prevM = (int) date('n', $prevTs);
$nextY = (int) date('Y', $nextTs);
$nextM = (int) date('n', $nextTs);

$todayY = (int) date('Y');
$todayM = (int) date('n');
$todayD = (int) date('j');

$monthStart = sprintf("%04d-%02d-01 00:00:00", $year, $month);
$monthEnd   = date("Y-m-t 23:59:59", strtotime($monthStart));
$monthStartDate = date("Y-m-d", strtotime($monthStart));
$monthEndDate   = date("Y-m-d", strtotime($monthEnd));

/* helpers */
function dt_from_date_time(string $date, string $time): string {
    return date("Y-m-d H:i:s", strtotime("$date $time"));
}
function date_only(string $dt): string {
    return date("Y-m-d", strtotime($dt));
}
function time_only(string $dt): string {
    return date("H:i", strtotime($dt));
}
function clamp_date(string $d, string $min, string $max): string {
    $t = strtotime($d);
    $tmin = strtotime($min);
    $tmax = strtotime($max);
    if ($t < $tmin) return date("Y-m-d", $tmin);
    if ($t > $tmax) return date("Y-m-d", $tmax);
    return date("Y-m-d", $t);
}
function wants_json(): bool {
    return isset($_POST["ajax"]) && $_POST["ajax"] === "1";
}

function stats(mysqli $conn, int $user_id, string $monthStart, string $monthEnd, string $monthStartDate, string $monthEndDate): array {
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS c
        FROM calendar_entries
        WHERE user_id=?
          AND start_datetime <= ?
          AND COALESCE(end_datetime, start_datetime) >= ?
    ");
    $stmt->bind_param("iss", $user_id, $monthEnd, $monthStart);
    $stmt->execute();
    $entries = (int)($stmt->get_result()->fetch_assoc()["c"] ?? 0);

    $stmt2 = $conn->prepare("
      SELECT COALESCE(SUM(
        CASE
          WHEN LEAST(DATE(COALESCE(end_datetime,start_datetime)), ?) < GREATEST(DATE(start_datetime), ?)
            THEN 0
          ELSE DATEDIFF(
            LEAST(DATE(COALESCE(end_datetime,start_datetime)), ?),
            GREATEST(DATE(start_datetime), ?)
          ) + 1
        END
      ),0) AS days
      FROM calendar_entries
      WHERE user_id=?
        AND start_datetime <= ?
        AND COALESCE(end_datetime, start_datetime) >= ?
    ");
    $stmt2->bind_param("ssssiss", $monthEndDate, $monthStartDate, $monthEndDate, $monthStartDate, $user_id, $monthEnd, $monthStart);
    $stmt2->execute();
    $entry_days = (int)($stmt2->get_result()->fetch_assoc()["days"] ?? 0);

    $now = date("Y-m-d H:i:s");
    $stmt3 = $conn->prepare("
      SELECT COUNT(*) AS c
      FROM calendar_entries
      WHERE user_id=?
        AND start_datetime BETWEEN ? AND ?
        AND start_datetime >= ?
    ");
    $stmt3->bind_param("isss", $user_id, $monthStart, $monthEnd, $now);
    $stmt3->execute();
    $upcoming = (int)($stmt3->get_result()->fetch_assoc()["c"] ?? 0);

    return ["entries" => $entries, "entry_days" => $entry_days, "upcoming" => $upcoming];
}

/* POST */
$error_msg = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $posted_token = $_POST["csrf_token"] ?? "";

    if (!hash_equals($csrf_token, $posted_token)) {
        if (wants_json()) {
            header("Content-Type: application/json");
            http_response_code(400);
            echo json_encode(["success" => false, "error" => "Invalid request. Please try again."]);
            exit();
        }
        $error_msg = "Invalid request. Please try again.";
    } else {
        $action = $_POST["action"] ?? "";

        if ($action === "add_entry" || $action === "edit_entry") {
            $title = trim($_POST["title"] ?? "");
            $start_date = trim($_POST["start_date"] ?? "");
            $start_time = trim($_POST["start_time"] ?? "");
            $end_date   = trim($_POST["end_date"] ?? "");
            $end_time   = trim($_POST["end_time"] ?? "");
            $notes      = trim($_POST["notes"] ?? "");

            if ($title === "" || $start_date === "" || $start_time === "") {
                $msg = "Please fill in Title, Start Date, and Start Time.";
                if (wants_json()) {
                    header("Content-Type: application/json");
                    http_response_code(400);
                    echo json_encode(["success" => false, "error" => $msg]);
                    exit();
                }
                $error_msg = $msg;
            } else {
                $start_dt = dt_from_date_time($start_date, $start_time);
                $end_dt = null;

                if ($end_date !== "" && $end_time !== "") {
                    $end_candidate = dt_from_date_time($end_date, $end_time);
                    if (strtotime($end_candidate) < strtotime($start_dt)) {
                        $msg = "End date/time must not be earlier than Start date/time.";
                        if (wants_json()) {
                            header("Content-Type: application/json");
                            http_response_code(400);
                            echo json_encode(["success" => false, "error" => $msg]);
                            exit();
                        }
                        $error_msg = $msg;
                    } else {
                        $end_dt = $end_candidate;
                    }
                }

                if ($error_msg === "") {
                    if ($action === "add_entry") {
                        $stmt = $conn->prepare("
                            INSERT INTO calendar_entries (user_id, title, start_datetime, end_datetime, notes)
                            VALUES (?, ?, ?, ?, ?)
                        ");
                        $stmt->bind_param("issss", $user_id, $title, $start_dt, $end_dt, $notes);
                        $stmt->execute();
                        $entry_id = (int)$conn->insert_id;
                        $mode = "add";
                    } else {
                        $entry_id = (int)($_POST["entry_id"] ?? 0);
                        $stmt = $conn->prepare("
                            UPDATE calendar_entries
                            SET title=?, start_datetime=?, end_datetime=?, notes=?
                            WHERE entry_id=? AND user_id=?
                            LIMIT 1
                        ");
                        $stmt->bind_param("ssssii", $title, $start_dt, $end_dt, $notes, $entry_id, $user_id);
                        $stmt->execute();
                        $mode = "edit";
                    }

                    if (wants_json()) {
                        $startISO = date("Y-m-d\\TH:i", strtotime($start_dt));
                        $endISO   = $end_dt ? date("Y-m-d\\TH:i", strtotime($end_dt)) : "";
                        $startD   = date("Y-m-d", strtotime($start_dt));
                        $endD     = date("Y-m-d", strtotime($end_dt ?: $start_dt));

                        $timeLabel = date("H:i", strtotime($start_dt));
                        if ($end_dt && $startD === $endD) $timeLabel .= "–" . date("H:i", strtotime($end_dt));

                        $s = stats($conn, $user_id, $monthStart, $monthEnd, $monthStartDate, $monthEndDate);

                        header("Content-Type: application/json");
                        echo json_encode([
                            "success" => true,
                            "mode" => $mode,
                            "entry" => [
                                "entry_id" => $entry_id,
                                "title" => $title,
                                "notes" => $notes,
                                "start_iso" => $startISO,
                                "end_iso" => $endISO,
                                "start_date" => $startD,
                                "end_date" => $endD,
                                "time_label" => $timeLabel,
                                "csrf_token" => $csrf_token
                            ],
                            "stats" => $s
                        ]);
                        exit();
                    }

                    header("Location: calendar.php?y=$year&m=$month");
                    exit();
                }
            }
        }

        if ($action === "delete_entry") {
            $entry_id = (int)($_POST["entry_id"] ?? 0);

            if ($entry_id > 0) {
                $stmt = $conn->prepare("DELETE FROM calendar_entries WHERE entry_id=? AND user_id=? LIMIT 1");
                $stmt->bind_param("ii", $entry_id, $user_id);
                $stmt->execute();

                if (wants_json()) {
                    $s = stats($conn, $user_id, $monthStart, $monthEnd, $monthStartDate, $monthEndDate);
                    header("Content-Type: application/json");
                    echo json_encode(["success" => true, "mode" => "delete", "deleted_id" => $entry_id, "stats" => $s]);
                    exit();
                }

                header("Location: calendar.php?y=$year&m=$month");
                exit();
            } else {
                $msg = "Invalid entry to delete.";
                if (wants_json()) {
                    header("Content-Type: application/json");
                    http_response_code(400);
                    echo json_encode(["success" => false, "error" => $msg]);
                    exit();
                }
                $error_msg = $msg;
            }
        }
    }
}

/* FETCH for Month grid render (spans) */
$stmt = $conn->prepare("
    SELECT entry_id, title, start_datetime, end_datetime, notes
    FROM calendar_entries
    WHERE user_id=?
      AND start_datetime <= ?
      AND COALESCE(end_datetime, start_datetime) >= ?
    ORDER BY start_datetime ASC
");
$stmt->bind_param("iss", $user_id, $monthEnd, $monthStart);
$stmt->execute();
$res = $stmt->get_result();

$byDay = [];
$allEntries = [];

while ($row = $res->fetch_assoc()) {
    $id = (int)$row["entry_id"];

    $start_dt = $row["start_datetime"];
    $end_dt_real = $row["end_datetime"];
    $end_dt_for_span = $end_dt_real ?: $start_dt;

    $startDate = date_only($start_dt);
    $endDate   = date_only($end_dt_for_span);

    $allEntries[$id] = [
        "entry_id" => $id,
        "title" => $row["title"],
        "start_datetime" => $start_dt,
        "end_datetime" => $end_dt_real,
        "notes" => $row["notes"] ?? ""
    ];

    $renderStart = clamp_date($startDate, $monthStartDate, $monthEndDate);
    $renderEnd   = clamp_date($endDate,   $monthStartDate, $monthEndDate);

    $cur = strtotime($renderStart);
    $endT = strtotime($renderEnd);

    while ($cur <= $endT) {
        $dayKey = date("Y-m-d", $cur);

        $isStart = ($dayKey === $startDate);
        $isEnd   = ($dayKey === $endDate);
        $isMid   = (!$isStart && !$isEnd);

        $timeLabel = "";
        if ($isStart) {
            $timeLabel = time_only($start_dt);
            if ($end_dt_real && $startDate === $endDate) {
                $timeLabel .= "–" . time_only($end_dt_real);
            }
        }

        $byDay[$dayKey][] = [
            "entry_id" => $id,
            "title" => $row["title"],
            "time" => $timeLabel,
            "isStart" => $isStart,
            "isEnd" => $isEnd,
            "isMid" => $isMid,
            "spans" => ($startDate !== $endDate)
        ];

        $cur = strtotime("+1 day", $cur);
    }
}

$s = stats($conn, $user_id, $monthStart, $monthEnd, $monthStartDate, $monthEndDate);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Calendar</title>

  <link rel="stylesheet" href="assets/styles/layout.css"/>
  <link rel="stylesheet" href="assets/styles/calendar_styles.css"/>
</head>

<body data-month-start="<?php echo htmlspecialchars($monthStartDate); ?>"
      data-month-end="<?php echo htmlspecialchars($monthEndDate); ?>">

<div class="app">
  <div class="sidebar-overlay" id="sidebarOverlay" hidden></div>

  <?php include 'assets/includes/general_nav.php' ?>

  <main class="main">

    <!-- ✅ UPDATED TOPBAR (native-app style on mobile) -->
    <header class="topbar">
      <div class="topbar-left">
        <!-- keep same id used by layout.js -->
        <button class="hamburger" id="menuBtn" type="button" aria-label="Open menu">☰</button>
        <h1 class="mobile-title">Calendar</h1>
      </div>

      <div class="title-wrap">
        <!-- desktop shows both h1 + p, mobile shows only p here -->
        <h1 class="desktop-title">Calendar</h1>
        <p>Plan and track your entries.</p>
      </div>
    </header>

    <section class="content calendar-page">
      <div class="cal-card">

        <?php if ($error_msg !== ""): ?>
          <div class="cal-alert" role="alert"><?php echo htmlspecialchars($error_msg); ?></div>
        <?php endif; ?>

        <div class="cal-top">
          <div class="cal-tabs" id="calTabs">
            <button class="cal-tab active" type="button" data-view="month">Month</button>
            <button class="cal-tab" type="button" data-view="week">Week</button>
            <button class="cal-tab" type="button" data-view="day">Day</button>
          </div>

          <button class="cal-add" id="openAdd" type="button">
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

        <div class="cal-view cal-view--month" id="viewMonth">
          <div class="cal-grid">
            <?php
            $weekdays = ["Sun","Mon","Tue","Wed","Thu","Fri","Sat"];
            foreach ($weekdays as $wd) echo '<div class="cal-cell head">'.htmlspecialchars($wd).'</div>';

            $totalCells = 42;
            $prevMonthTs = strtotime("-1 month", $firstDayTs);
            $daysInPrev = (int) date('t', $prevMonthTs);

            for ($cell=0; $cell<$totalCells; $cell++) {
                $dayNum = $cell - $startWeekday + 1;
                $classes = "cal-cell";
                $label = "";
                $dateKey = "";
                $isToday = false;
                $isValidMonth = false;

                if ($dayNum < 1) {
                    $classes .= " muted";
                    $label = $daysInPrev + $dayNum;
                } else if ($dayNum <= $daysInMonth) {
                    $label = $dayNum;
                    $dateKey = sprintf("%04d-%02d-%02d", $year, $month, $dayNum);
                    $isValidMonth = true;

                    if ($year === $todayY && $month === $todayM && $dayNum === $todayD) {
                        $classes .= " is-today";
                        $isToday = true;
                    }
                } else {
                    $classes .= " muted";
                    $label = $dayNum - $daysInMonth;
                }

                $dataAttr = $isValidMonth ? ' data-date="'.htmlspecialchars($dateKey).'"' : '';
                echo '<div class="'.htmlspecialchars($classes).'"'.$dataAttr.'>';
                echo '<span class="day">'.htmlspecialchars((string)$label).'</span>';

                if ($dateKey && isset($byDay[$dateKey])) {
                    foreach ($byDay[$dateKey] as $p) {
                        $spanClass = "";
                        if ($p["spans"]) {
                            if ($p["isStart"] && !$p["isEnd"]) $spanClass = " span-start";
                            else if ($p["isEnd"] && !$p["isStart"]) $spanClass = " span-end";
                            else if ($p["isMid"]) $spanClass = " span-mid";
                            else $spanClass = " span-one";
                        }

                        $e = $allEntries[$p["entry_id"]];
                        $startISO = date("Y-m-d\\TH:i", strtotime($e["start_datetime"]));
                        $endISO = $e["end_datetime"] ? date("Y-m-d\\TH:i", strtotime($e["end_datetime"])) : "";

                        echo '<div class="pill'.htmlspecialchars($spanClass).'"'
                          .' data-entry-id="'.(int)$p["entry_id"].'"'
                          .' data-title="'.htmlspecialchars($e["title"], ENT_QUOTES, "UTF-8").'"'
                          .' data-start="'.htmlspecialchars($startISO, ENT_QUOTES, "UTF-8").'"'
                          .' data-end="'.htmlspecialchars($endISO, ENT_QUOTES, "UTF-8").'"'
                          .' data-notes="'.htmlspecialchars($e["notes"], ENT_QUOTES, "UTF-8").'">';

                        echo '<div class="pill-title">'.htmlspecialchars($p["title"]).'</div>';
                        if ($p["time"] !== "") echo '<div class="pill-time">'.htmlspecialchars($p["time"]).'</div>';

                        echo '<div class="pill-actions">';
                        echo '<button type="button" class="pill-btn edit" title="Edit">✎</button>';

                        echo '<form method="post" class="pill-del">';
                        echo '<input type="hidden" name="csrf_token" value="'.htmlspecialchars($csrf_token).'">';
                        echo '<input type="hidden" name="action" value="delete_entry">';
                        echo '<input type="hidden" name="entry_id" value="'.(int)$p["entry_id"].'">';
                        echo '<input type="hidden" name="ajax" value="1">';
                        echo '<button type="submit" class="pill-btn del" title="Delete">🗑</button>';
                        echo '</form>';

                        echo '</div></div>';
                    }
                }

                if ($isToday) echo '<div class="today" aria-hidden="true"></div>';
                echo '</div>';
            }
            ?>
          </div>
        </div>

        <div class="cal-view cal-view--week" id="viewWeek" hidden>
          <div class="week-head" id="weekHead"></div>
          <div class="week-grid" id="weekGrid"></div>
        </div>

        <div class="cal-view cal-view--day" id="viewDay" hidden>
          <div class="day-head" id="dayHead"></div>
          <div class="day-list" id="dayList"></div>
        </div>
      </div>

      <aside class="tracker">
        <h2>Progress<br>Tracker</h2>
        <div class="ring" aria-hidden="true"><div class="ring-inner"></div></div>

        <div class="tracker-list">
          <div class="t-row"><span>Entries this month</span><span class="t-score" id="tEntries"><?php echo (int)$s["entries"]; ?></span></div>
          <div class="t-row"><span>Entry-days this month</span><span class="t-score" id="tDays"><?php echo (int)$s["entry_days"]; ?></span></div>
          <div class="t-row"><span>Upcoming this month</span><span class="t-score" id="tUpcoming"><?php echo (int)$s["upcoming"]; ?></span></div>
        </div>
      </aside>
    </section>

    <?php include 'assets/includes/footer.php' ?>
  </main>
</div>

<!-- MODAL -->
<div class="cal-modal" id="calModal" aria-hidden="true">
  <div class="cal-modal__backdrop" id="closeAdd"></div>

  <div class="cal-modal__panel" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
    <div class="cal-modal__top">
      <h3 id="modalTitle">Add Calendar Entry</h3>
      <button class="cal-x" type="button" id="closeAddBtn" aria-label="Close">✕</button>
    </div>

    <form class="cal-form" method="post" action="calendar.php?y=<?php echo $year; ?>&m=<?php echo $month; ?>">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
      <input type="hidden" name="action" id="formAction" value="add_entry">
      <input type="hidden" name="entry_id" id="entryId" value="">
      <input type="hidden" name="ajax" value="1">

      <label class="cal-label">
        Title
        <input class="cal-input" type="text" name="title" id="title" maxlength="255" required />
      </label>

      <div class="cal-row">
        <label class="cal-label">
          Start Date
          <input class="cal-input" type="date" name="start_date" id="start_date" required />
        </label>
        <label class="cal-label">
          Start Time
          <input class="cal-input" type="time" name="start_time" id="start_time" required />
        </label>
      </div>

      <div class="cal-row">
        <label class="cal-label">
          End Date (optional)
          <input class="cal-input" type="date" name="end_date" id="end_date" />
        </label>
        <label class="cal-label">
          End Time (optional)
          <input class="cal-input" type="time" name="end_time" id="end_time" />
        </label>
      </div>

      <label class="cal-label">
        Notes (optional)
        <textarea class="cal-textarea" name="notes" id="notes" rows="3"></textarea>
      </label>

      <div class="cal-actions">
        <button class="cal-btn ghost" type="button" id="cancelAdd">Cancel</button>
        <button class="cal-btn" type="submit">Save</button>
      </div>
    </form>
  </div>
</div>

<script src="assets/script/layout.js?v=1"></script>
<script src="assets/script/calendar.js?v=1"></script>
</body>
</html>