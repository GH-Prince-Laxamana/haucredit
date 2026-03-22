<?php
// ==================== PAGE INITIALIZATION ====================
// Start session for user authentication and CSRF token tracking
session_start();
// Include database connection and helper functions
require_once __DIR__ . '/../../app/database.php';
// Include security header generation function
require_once APP_PATH . "security_headers.php";
// Send security headers to prevent XSS, clickjacking, and other attacks
send_security_headers();

// ==================== SECURITY CHECK ====================
// Verify current user is logged in; redirect to login if not
requireLogin();

// ==================== EXTRACT SESSION DATA ====================
// Get authenticated user's ID as integer (used for all database queries)
$user_id = (int) $_SESSION["user_id"];

// ==================== CSRF TOKEN SETUP ====================
// Generate CSRF token if not already in session; store for form validation
if (empty($_SESSION["csrf_token"])) {
  $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}
// Get current CSRF token for form embedding
$csrf_token = $_SESSION["csrf_token"];

// ==================== DATE SETUP ====================
// Extract year and month from GET parameters; default to current date
$year = isset($_GET['y']) ? (int) $_GET['y'] : (int) date('Y');
$month = isset($_GET['m']) ? (int) $_GET['m'] : (int) date('n');

// ===== VALIDATE DATE RANGES =====
// Clamp month to 1-12 range (prevent invalid month numbers)
$month = max(1, min(12, $month));
// Clamp year to 1970-2100 range (reasonable calendar bounds)
$year = max(1970, min(2100, $year));

// ===== CALCULATE MONTH PROPERTIES =====
// Get timestamp for first day of requested month (00:00:00)
$firstDayTs = strtotime("$year-$month-01");
// Calculate number of days in this month (28-31)
$daysInMonth = (int) date('t', $firstDayTs);
// Get starting weekday of month (0=Sunday, 6=Saturday)
$startWeekday = (int) date('w', $firstDayTs);
// Get full month name for display (e.g., 'January')
$monthName = date('F', $firstDayTs);

// ===== CALCULATE NAVIGATION TIMESTAMPS =====
// Get timestamp for first day of previous month
$prevTs = strtotime("-1 month", $firstDayTs);
// Get timestamp for first day of next month
$nextTs = strtotime("+1 month", $firstDayTs);

// Extract year/month for previous month links
$prevY = (int) date('Y', $prevTs);
$prevM = (int) date('n', $prevTs);
// Extract year/month for next month links
$nextY = (int) date('Y', $nextTs);
$nextM = (int) date('n', $nextTs);

// ===== TODAY'S DATE FOR HIGHLIGHTING =====
// Get today's year, month, day for visual highlighting in calendar
$todayY = (int) date('Y');
$todayM = (int) date('n');
$todayD = (int) date('j');

// ===== MONTH BOUNDARY TIMESTAMPS =====
// First moment of requested month (for database queries)
$monthStart = sprintf("%04d-%02d-01 00:00:00", $year, $month);
// Last moment of requested month (for database queries)
$monthEnd = date("Y-m-t 23:59:59", strtotime($monthStart));

// ===== MONTH BOUNDARY DATES (FOR CLAMPING) =====
// Date-only version of month start (for date clamping logic)
$monthStartDate = date("Y-m-d", strtotime($monthStart));
// Date-only version of month end (for date clamping logic)
$monthEndDate = date("Y-m-d", strtotime($monthEnd));

// ==================== HELPER FUNCTIONS ====================
/**
 * Combine separate date and time strings into a datetime string
 * Used for form input processing
 *
 * @param string $date Date string (Y-m-d format)
 * @param string $time Time string (H:i format)
 * @return string Combined datetime string (Y-m-d H:i:s format)
 */
function dt_from_date_time(string $date, string $time): string
{
  return date("Y-m-d H:i:s", strtotime("$date $time"));
}

/**
 * Extract date portion from a datetime string
 * Used to isolate dates for calendar day calculations
 *
 * @param string $dt Datetime string
 * @return string Date-only portion (Y-m-d format)
 */
function date_only(string $dt): string
{
  return date("Y-m-d", strtotime($dt));
}

/**
 * Extract time portion from a datetime string
 * Used to display entry times on calendar events
 *
 * @param string $dt Datetime string
 * @return string Time-only portion (H:i format, 24-hour)
 */
function time_only(string $dt): string
{
  return date("H:i", strtotime($dt));
}

/**
 * Clamp a date between min and max boundaries
 * Used to handle multi-day entries that extend beyond current month
 *
 * @param string $d The date to clamp
 * @param string $min Minimum date boundary
 * @param string $max Maximum date boundary
 * @return string Clamped date (Y-m-d format)
 */
function clamp_date(string $d, string $min, string $max): string
{
  $t = strtotime($d);
  return date("Y-m-d", max(strtotime($min), min(strtotime($max), $t)));
}

// ==================== CALENDAR STATISTICS CALCULATION ====================
/**
 * Calculate calendar statistics for the displayed month
 * Computes entry counts, day spans, and upcoming events for display
 *
 * @param mysqli $conn Database connection
 * @param int $user_id Authenticated user ID
 * @param string $monthStart Month start timestamp with time
 * @param string $monthEnd Month end timestamp with time
 * @param string $monthStartDate Month start date only (Y-m-d)
 * @param string $monthEndDate Month end date only (Y-m-d)
 * @return array Associative array with keys: 'entries' (count), 'entry_days' (day span), 'upcoming' (future count)
 */
function stats(mysqli $conn, int $user_id, string $monthStart, string $monthEnd, string $monthStartDate, string $monthEndDate): array
{
  // ===== TOTAL ENTRIES COUNT =====
  // Query to count calendar entries that overlap with the displayed month
  // Entries can span multiple days, so we check if entry range overlaps month range
  $countEntriesSql = "
    SELECT COUNT(*) c
    FROM calendar_entries
    WHERE user_id = ?
      AND start_datetime <= ?
      AND COALESCE(end_datetime, start_datetime) >= ?
  ";
  $entriesRow = fetchOne(
    $conn,
    $countEntriesSql,
    "iss",
    [$user_id, $monthEnd, $monthStart]
  );
  $entries = (int) ($entriesRow["c"] ?? 0);

  // ===== TOTAL ENTRY-DAYS SPAN =====
  // Query to sum the total number of days that entries span within the month
  // Example: 3 entries of 2/3/4 days each = 9 total entry-days
  // Complex CASE logic handles date overlaps with month boundaries
  $countEntryDaysSql = "
    SELECT COALESCE(
      SUM(
        CASE
          WHEN LEAST(DATE(COALESCE(end_datetime, start_datetime)), ?) < GREATEST(DATE(start_datetime), ?)
            THEN 0
          ELSE DATEDIFF(
            LEAST(DATE(COALESCE(end_datetime, start_datetime)), ?),
            GREATEST(DATE(start_datetime), ?)
          ) + 1
        END
      ), 0
    ) days
    FROM calendar_entries
    WHERE user_id = ?
      AND start_datetime <= ?
      AND COALESCE(end_datetime, start_datetime) >= ?
  ";
  $entryDaysRow = fetchOne(
    $conn,
    $countEntryDaysSql,
    "ssssiss",
    [$monthEndDate, $monthStartDate, $monthEndDate, $monthStartDate, $user_id, $monthEnd, $monthStart]
  );
  $entry_days = (int) ($entryDaysRow["days"] ?? 0);

  // ===== UPCOMING ENTRIES COUNT =====
  // Get current timestamp for future comparison
  $now = date("Y-m-d H:i:s");
  // Query to count entries starting in the future (within the month)
  $countUpcomingSql = "
    SELECT COUNT(*) c
    FROM calendar_entries
    WHERE user_id = ?
      AND start_datetime BETWEEN ? AND ?
      AND start_datetime >= ?
  ";
  $upcomingRow = fetchOne(
    $conn,
    $countUpcomingSql,
    "isss",
    [$user_id, $monthStart, $monthEnd, $now]
  );
  $upcoming = (int) ($upcomingRow["c"] ?? 0);

  // Return all statistics as associative array
  return compact("entries", "entry_days", "upcoming");
}

// ==================== POST REQUEST HANDLER ====================
// Initialize error message variable for form validation feedback
$error_msg = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  if (!hash_equals($csrf_token, $_POST["csrf_token"] ?? "")) {
    $error_msg = "Invalid request. Please try again.";
  } else {
    $action = $_POST["action"] ?? "";

    // ===== ADD OR EDIT CALENDAR ENTRY =====
    if (in_array($action, ["add_entry", "edit_entry"], true)) {
      $title = trim($_POST["title"] ?? "");
      $start_date = trim($_POST["start_date"] ?? "");
      $start_time = trim($_POST["start_time"] ?? "");
      $end_date = trim($_POST["end_date"] ?? "");
      $end_time = trim($_POST["end_time"] ?? "");
      $notes = trim($_POST["notes"] ?? "");

      // ===== REQUIRED FIELD VALIDATION =====
      // Title, Start Date, and Start Time are required; End Date/Time are optional
      if ($title === "" || $start_date === "" || $start_time === "") {
        $error_msg = "Please fill in Title, Start Date, and Start Time.";
      } else {
        // ===== COMBINE DATE AND TIME =====
        // Merge separate date/time inputs into single datetime string
        $start_dt = dt_from_date_time($start_date, $start_time);
        $end_dt = null;

        // ===== VALIDATE END TIME (IF PROVIDED) =====
        // If end date/time are provided, combine and validate they're after start
        if ($end_date !== "" && $end_time !== "") {
          $tmp = dt_from_date_time($end_date, $end_time);
          // End time must not be before start time
          if (strtotime($tmp) < strtotime($start_dt)) {
            $error_msg = "End date/time must not be earlier than Start date/time.";
          } else {
            $end_dt = $tmp;
          }
        }

        // ===== DATABASE OPERATION =====
        // Only proceed with database insert/update if no validation errors
        if ($error_msg === "") {
          if ($action === "add_entry") {
            // ===== INSERT NEW ENTRY =====
            // Insert calendar entry with user_id, title, times, and optional notes
            // event_id is NULL for user-created entries (not linked to events)
            $insertCalendarEntrySql = "
              INSERT INTO calendar_entries (user_id, event_id, title, start_datetime, end_datetime, notes)
              VALUES (?, NULL, ?, ?, ?, ?)
            ";
            execQuery(
              $conn,
              $insertCalendarEntrySql,
              "issss",
              [$user_id, $title, $start_dt, $end_dt, $notes]
            );
          } else {
            // ===== EDIT EXISTING ENTRY =====
            $entry_id = (int) ($_POST["entry_id"] ?? 0);

            // First, verify entry exists and belongs to this user
            $checkEditableEntrySql = "
              SELECT event_id
              FROM calendar_entries
              WHERE entry_id = ?
                AND user_id = ?
              LIMIT 1
            ";
            $entryRow = fetchOne(
              $conn,
              $checkEditableEntrySql,
              "ii",
              [$entry_id, $user_id]
            );

            // Check if entry was found
            if (!$entryRow) {
              $error_msg = "Calendar entry not found.";
            // Check if entry is linked to an event (event-linked entries can't be edited)
            } elseif (!empty($entryRow["event_id"])) {
              $error_msg = "Event-linked entries cannot be edited here.";
            } else {
              // Update the entry with new values
              $updateCalendarEntrySql = "
                UPDATE calendar_entries
                SET title = ?, start_datetime = ?, end_datetime = ?, notes = ?
                WHERE entry_id = ?
                  AND user_id = ?
                LIMIT 1
              ";
              execQuery(
                $conn,
                $updateCalendarEntrySql,
                "ssssii",
                [$title, $start_dt, $end_dt, $notes, $entry_id, $user_id]
              );
            }
          }

          // ===== REDIRECT ON SUCCESS =====
          // If no errors occurred, redirect to calendar with same month/year view
          if ($error_msg === "") {
            header("Location: calendar.php?y=$year&m=$month");
            exit();
          }
        }
      }
    }

    // ===== DELETE CALENDAR ENTRY =====
    if ($action === "delete_entry") {
      $entry_id = (int) ($_POST["entry_id"] ?? 0);

      if ($entry_id > 0) {
        $checkDeleteEntrySql = "
          SELECT event_id
          FROM calendar_entries
          WHERE entry_id = ?
            AND user_id = ?
          LIMIT 1
        ";
        $row = fetchOne(
          $conn,
          $checkDeleteEntrySql,
          "ii",
          [$entry_id, $user_id]
        );

        if (!$row) {
          $error_msg = "Calendar entry not found.";
        // Check if entry is linked to an event (can't delete event-linked entries)
        } elseif (!empty($row["event_id"])) {
          $error_msg = "Event-linked entries cannot be deleted here.";
        } else {
          // Delete the calendar entry
          $deleteCalendarEntrySql = "
            DELETE FROM calendar_entries
            WHERE entry_id = ?
              AND user_id = ?
            LIMIT 1
          ";
          execQuery(
            $conn,
            $deleteCalendarEntrySql,
            "ii",
            [$entry_id, $user_id]
          );

          header("Location: calendar.php?y=$year&m=$month");
          exit();
        }
      }
    }
  }
}

// ==================== FETCH CALENDAR ENTRIES ====================
// Query to retrieve all calendar entries for the displayed month
$fetchCalendarEntriesSql = "
  SELECT
    ce.entry_id,
    ce.title,
    ce.start_datetime,
    ce.end_datetime,
    ce.notes,
    ce.event_id,
    e.event_name
  FROM calendar_entries ce
  LEFT JOIN events e
    ON ce.event_id = e.event_id
  WHERE ce.user_id = ?
    AND ce.start_datetime <= ?
    AND COALESCE(ce.end_datetime, ce.start_datetime) >= ?
  ORDER BY ce.start_datetime ASC
";

$calendarEntries = fetchAll(
  $conn,
  $fetchCalendarEntriesSql,
  "iss",
  [$user_id, $monthEnd, $monthStart]
);

// ==================== PROCESS ENTRIES BY DAY ====================
// Build two data structures:
//   1. $byDay: Multi-day entry display (organized by date)
//   2. $allEntries: Complete entry data lookup (indexed by entry_id)
$byDay = [];
$allEntries = [];

foreach ($calendarEntries as $row) {
  // Extract entry ID as integer
  $id = (int) $row["entry_id"];
  // Get entry start datetime
  $start_dt = $row["start_datetime"];
  // Get entry end datetime (may be NULL for single-day entries)
  $end_dt_real = $row["end_datetime"];
  // Use end datetime if exists; otherwise use start date (for single-day span calculation)
  $end_dt_for_span = $end_dt_real ?: $start_dt;

  // ===== EXTRACT DATES =====
  // Get date portion of start datetime (without time)
  $startDate = date_only($start_dt);
  // Get date portion of end datetime (without time)
  $endDate = date_only($end_dt_for_span);

  // Store complete entry data for later access (by entry_id)
  $allEntries[$id] = $row;

  // ===== CLAMP ENTRY SPAN TO MONTH BOUNDARIES =====
  // Handle multi-day entries that extend beyond the current month
  // Example: Entry from Jan 25 - Feb 5 in Feb view should start at Feb 1
  $renderStart = clamp_date($startDate, $monthStartDate, $monthEndDate);
  $renderEnd = clamp_date($endDate, $monthStartDate, $monthEndDate);

  // ===== POPULATE BY-DAY STRUCTURE =====
  // Loop through each day the entry spans (within month boundaries)
  for ($cur = strtotime($renderStart); $cur <= strtotime($renderEnd); $cur = strtotime("+1 day", $cur)) {
    // Format current date as YYYY-MM-DD key
    $dayKey = date("Y-m-d", $cur);

    // Flags for styling: is this the first day of the entry? The last?
    $isStart = $dayKey === $startDate;
    $isEnd = $dayKey === $endDate;

    // ===== DETERMINE TIME LABEL =====
    // Show time only on the first day of multi-day entry
    $timeLabel = $isStart ? time_only($start_dt) : "";

    // If entry is single-day (start and end on same date), show time range
    if ($isStart && $end_dt_real && $startDate === $endDate) {
      $timeLabel .= "–" . time_only($end_dt_real);
    }

    // ===== ADD TO BY-DAY ARRAY =====
    // Store entry metadata organized by day for rendering
    $byDay[$dayKey][] = [
      "entry_id" => $id,
      "title" => $row["title"],
      "time" => $timeLabel,
      "isStart" => $isStart,
      "isEnd" => $isEnd,
      "isMid" => (!$isStart && !$isEnd),
      "spans" => ($startDate !== $endDate)
    ];
  }
}

// ===== CALCULATE MONTH STATISTICS =====
// Get statistics for display cards (entry count, total days, upcoming count)
$s = stats($conn, $user_id, $monthStart, $monthEnd, $monthStartDate, $monthEndDate);
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Calendar</title>

  <link rel="stylesheet" href="<?= PUBLIC_URL ?>assets/styles/layout.css" />
  <link rel="stylesheet" href="<?= PUBLIC_URL ?>assets/styles/calendar_styles.css" />
</head>

<body data-month-start="<?= htmlspecialchars($monthStartDate); ?>"
  data-month-end="<?= htmlspecialchars($monthEndDate); ?>">

  <div class="app">
    <div class="sidebar-overlay" id="sidebarOverlay" hidden></div>

    <?php include PUBLIC_PATH . 'assets/includes/general_nav.php' ?>

    <!-- ==================== MAIN CONTENT AREA ==================== -->
    <main class="main">

      <header class="topbar">
        <div class="topbar-left">
          <button class="hamburger" id="menuBtn" type="button" aria-label="Open menu">☰</button>
          <div class="title-wrap">
            <h1 class="desktop-title">Calendar</h1>
            <p>Organize your schedule and track progress.</p>
          </div>
        </div>
      </header>

      <!-- =============== CALENDAR SECTION =============== -->
      <section class="content calendar-page">
        <div class="cal-card">

          <?php if ($error_msg !== ""): ?>
            <div class="cal-alert" role="alert"><?= htmlspecialchars($error_msg); ?></div>
          <?php endif; ?>

          <div class="cal-top">
            <div class="cal-tabs" id="calTabs">
              <button class="cal-tab active" type="button" data-view="month">Month</button>
              <button class="cal-tab" type="button" data-view="week">Week</button>
              <button class="cal-tab" type="button" data-view="day">Day</button>
            </div>

            <button class="btn-primary ghost" type="button" id="openAdd"><i class="fa-solid fa-plus"></i> Add</button>
          </div>

          <div class="cal-monthrow">
            <div class="cal-month">
              <strong><?= htmlspecialchars($monthName . " " . $year); ?></strong>
              <a class="cal-navbtn" href="?y=<?= $prevY; ?>&m=<?= $prevM; ?>" aria-label="Previous month"><i
                  class="fa-solid fa-caret-left"></i></a>
              <a class="cal-navbtn" href="?y=<?= $nextY; ?>&m=<?= $nextM; ?>" aria-label="Next month"><i
                  class="fa-solid fa-caret-right"></i></a>
            </div>
          </div>

          <!-- ========== MONTH VIEW (CALENDAR GRID) ========== -->
          <div class="cal-view cal-view--month" id="viewMonth">
            <div class="cal-grid">
              <?php
              $weekdays = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];
              foreach ($weekdays as $wd) {
                echo '<div class="cal-cell head">' . htmlspecialchars($wd) . '</div>';
              }

              $totalCells = 42;
              $prevMonthTs = strtotime("-1 month", $firstDayTs);
              $daysInPrev = (int) date('t', $prevMonthTs);

              for ($cell = 0; $cell < $totalCells; $cell++) {
                $dayNum = $cell - $startWeekday + 1;
                $classes = "cal-cell";
                $label = "";
                $dateKey = "";
                $isToday = false;
                $isValidMonth = false;

                if ($dayNum < 1) {
                  $classes .= " muted";
                  $label = $daysInPrev + $dayNum;
                } elseif ($dayNum <= $daysInMonth) {
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

                $dataAttr = $isValidMonth ? ' data-date="' . htmlspecialchars($dateKey) . '"' : '';
                echo '<div class="' . htmlspecialchars($classes) . '"' . $dataAttr . '>';
                echo '<span class="day">' . htmlspecialchars((string) $label) . '</span>';

                if ($dateKey && isset($byDay[$dateKey])) {
                  foreach ($byDay[$dateKey] as $p) {
                    $spanClass = "";
                    if ($p["spans"]) {
                      if ($p["isStart"] && !$p["isEnd"])
                        $spanClass = " span-start";
                      elseif ($p["isEnd"] && !$p["isStart"])
                        $spanClass = " span-end";
                      elseif ($p["isMid"])
                        $spanClass = " span-mid";
                      else
                        $spanClass = " span-one";
                    }

                    $e = $allEntries[$p["entry_id"]];
                    $startISO = date("Y-m-d\\TH:i", strtotime($e["start_datetime"]));
                    $endISO = $e["end_datetime"] ? date("Y-m-d\\TH:i", strtotime($e["end_datetime"])) : "";
                    $isLinkedEvent = !empty($e["event_id"]);

                    echo '<div class="pill pill-link' . htmlspecialchars($spanClass) . ($isLinkedEvent ? ' pill-event' : '') . '"'
                      . ' data-entry-id="' . (int) $p["entry_id"] . '"'
                      . ' data-title="' . htmlspecialchars($e["title"], ENT_QUOTES, "UTF-8") . '"'
                      . ' data-start="' . htmlspecialchars($startISO, ENT_QUOTES, "UTF-8") . '"'
                      . ' data-end="' . htmlspecialchars($endISO, ENT_QUOTES, "UTF-8") . '"'
                      . ' data-notes="' . htmlspecialchars($e["notes"] ?? "", ENT_QUOTES, "UTF-8") . '"'
                      . ' data-event-id="' . (int) ($e["event_id"] ?? 0) . '"'
                      . ' data-event-name="' . htmlspecialchars($e["event_name"] ?? "", ENT_QUOTES, "UTF-8") . '">';

                    echo '<div class="pill-title">' . htmlspecialchars($p["title"]) . '</div>';
                    if ($p["time"] !== "") {
                      echo '<div class="pill-time">' . htmlspecialchars($p["time"]) . '</div>';
                    }

                    echo '<div class="pill-actions">';

                    if ($isLinkedEvent) {
                      echo '<a href="view_event.php?id=' . (int) $e["event_id"] . '" class="pill-btn edit" title="View Event"><i class="fa-solid fa-arrow-up-right-from-square"></i></a>';
                    } else {
                      echo '<button type="button" class="pill-btn edit" title="Edit"><i class="fa-solid fa-pen"></i></button>';

                      echo '<form method="post" class="pill-del" onsubmit="return confirm(\'Delete this entry?\')">';
                      echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($csrf_token) . '">';
                      echo '<input type="hidden" name="action" value="delete_entry">';
                      echo '<input type="hidden" name="entry_id" value="' . (int) $p["entry_id"] . '">';
                      echo '<button type="submit" class="pill-btn del" title="Delete"><i class="fa-solid fa-trash-can"></i></button>';
                      echo '</form>';
                    }

                    echo '</div></div>';
                  }
                }

                // ===== TODAY INDICATOR =====
                // Show visual indicator on today's date
                if ($isToday) {
                  echo '<div class="today" aria-hidden="true"></div>';
                }
                echo '</div>';
              }
              ?>
            </div>
          </div>

          <!-- ========== WEEK VIEW ========== -->
          <!-- Populated dynamically by JavaScript (calendar.js) -->
          <!-- Hidden by default; shown when Week tab is clicked -->
          <div class="cal-view cal-view--week" id="viewWeek" hidden>
            <div class="week-head" id="weekHead"></div>
            <div class="week-grid" id="weekGrid"></div>
          </div>

          <!-- ========== DAY VIEW ========== -->
          <!-- Populated dynamically by JavaScript (calendar.js) -->
          <!-- Hidden by default; shown when Day tab is clicked -->
          <div class="cal-view cal-view--day" id="viewDay" hidden>
            <div class="day-head" id="dayHead"></div>
            <div class="day-list" id="dayList"></div>
          </div>
        </div>
      </section>

      <?php include PUBLIC_PATH . 'assets/includes/footer.php' ?>
    </main>
  </div>

  <!-- ==================== CALENDAR ENTRY MODAL ==================== -->
  <!-- Dialog for creating and editing calendar entries -->
  <div class="cal-modal" id="calModal" aria-hidden="true">
    <div class="cal-modal__backdrop" id="closeAdd"></div>

    <div class="cal-modal__panel" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
      <div class="cal-modal__top">
        <h3 id="modalTitle">Add Calendar Entry</h3>
      </div>

      <form class="cal-form" method="post" action="calendar.php?y=<?= $year; ?>&m=<?= $month; ?>">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
        <input type="hidden" name="action" id="formAction" value="add_entry">
        <input type="hidden" name="entry_id" id="entryId" value="">

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
          <button class="btn-secondary btn-smaller ghost" type="button" id="cancelAdd">Cancel</button>
          <button class="btn-primary btn-smaller" type="submit">Save</button>
        </div>
      </form>
    </div>
  </div>

  <script src="<?= APP_URL ?>script/layout.js?v=1"></script>
  <script src="<?= APP_URL ?>script/calendar.js?v=1"></script>
</body>

</html>