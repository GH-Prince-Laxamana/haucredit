<?php
session_start();
include "../app/database.php";

if (!isset($_SESSION["user_id"])) {
  header("Location: index.php");
  exit();
}

require_once("../app/security_headers.php");
send_security_headers();
// Persist previous inputs
$organizing_body = $_SESSION['organizing_body'] ?? '';
$background = $_SESSION['background'] ?? '';
$activity_type = $_SESSION['activity_type'] ?? '';
$series = $_SESSION['series'] ?? '';
$nature = $_SESSION['nature'] ?? '';
$activity_name = $_SESSION['event_name'] ?? '';
$start_datetime = $_SESSION['start_datetime'] ?? '';
$end_datetime = $_SESSION['end_datetime'] ?? '';
$participants = $_SESSION['participants'] ?? '';
$venue = $_SESSION['venue_platform'] ?? '';
$extraneous = $_SESSION['is_extraneous'] ?? '';
$target_metric = $_SESSION['target_metric'] ?? '';
$distance = $_SESSION['distance'] ?? '';
$participant_range = $_SESSION['participant_range'] ?? '';
$overnight = $_SESSION['overnight'] ?? '';

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $_SESSION['organizing_body'] = $_POST['organizing_body'] ?? null;
  $_SESSION['background'] = $_POST['background'] ?? null;
  $_SESSION['activity_type'] = $_POST['activity_type'] ?? null;
  $_SESSION['series'] = $_POST['series'] ?? null;
  $_SESSION['nature'] = $_POST['nature'] ?? '';
  $_SESSION['event_name'] = $_POST['activity_name'] ?? '';
  $_SESSION['start_datetime'] = $_POST['start_datetime'] ?? '';
  $_SESSION['end_datetime'] = $_POST['end_datetime'] ?? '';
  $_SESSION['participants'] = $_POST['participants'] ?? '';
  $_SESSION['venue_platform'] = $_POST['venue'] ?? '';
  $_SESSION['is_extraneous'] = $_POST['extraneous'] ?? '';
  $_SESSION['target_metric'] = $_POST['target_metric'] ?? '';
  $_SESSION['distance'] = $_POST['distance'] ?? null;
  $_SESSION['participant_range'] = $_POST['participant_range'] ?? null;
  $_SESSION['overnight'] = $_POST['overnight'] ?? null;

  if (isset($_POST['create_event'])) {
    $stmt = $conn->prepare("
            INSERT INTO events (
                user_id, organizing_body, background, activity_type, series,
                nature, event_name, start_datetime, end_datetime,
                participants, venue_platform, is_extraneous, target_metric,
                distance, participant_range, overnight
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

    $user_id = $_SESSION["user_id"];

    $stmt->bind_param(
      "issssssssissssss",
      $user_id,
      $_SESSION['organizing_body'],
      $_SESSION['background'],
      $_SESSION['activity_type'],
      $_SESSION['series'],
      $_SESSION['nature'],
      $_SESSION['event_name'],
      $_SESSION['start_datetime'],
      $_SESSION['end_datetime'],
      $_SESSION['participants'],
      $_SESSION['venue_platform'],
      $_SESSION['is_extraneous'],
      $_SESSION['target_metric'],
      $_SESSION['distance'],
      $_SESSION['participant_range'],
      $_SESSION['overnight']
    );

    $stmt->execute();

    unset(
      $_SESSION['organizing_body'],
      $_SESSION['background'],
      $_SESSION['activity_type'],
      $_SESSION['series'],
      $_SESSION['nature'],
      $_SESSION['event_name'],
      $_SESSION['start_datetime'],
      $_SESSION['end_datetime'],
      $_SESSION['participants'],
      $_SESSION['venue_platform'],
      $_SESSION['is_extraneous'],
      $_SESSION['target_metric'],
      $_SESSION['distance'],
      $_SESSION['participant_range'],
      $_SESSION['overnight']
    );

    header("Location: home.php");
    exit();
  }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Create Event</title>
  <link rel="stylesheet" href="../app/css/layout.css" />
</head>

<body>
  <div class="app">

    <!-- SIDEBAR -->
    <aside class="sidebar">
      <div class="brand">
        <div class="avatar" aria-hidden="true"></div>
      </div>

      <nav class="nav">
        <a class="nav-item" href="home.php">
          <span class="icon" aria-hidden="true"></span>
          <span>Dashboard</span>
        </a>

        <a class="nav-item active" href="create_event.php">
          <span class="icon" aria-hidden="true"></span>
          <span>Create Event</span>
        </a>

        <a class="nav-item" href="calendar.php">
          <span class="icon" aria-hidden="true"></span>
          <span>Calendar</span>
        </a>

        <a class="nav-item" href="about.php">
          <span class="icon" aria-hidden="true"></span>
          <span>About Us</span>
        </a>
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
          <h1>Create Event</h1>
          <p>Fill out the form below to create a new event.</p>
        </div>
      </header>

      <section class="content create-event">
        <form method="POST">
          <!-- Accordion: Basic Information -->
          <details class="acc" open>
            <summary class="acc-head">
              <span class="acc-left">
                <span class="acc-dot" aria-hidden="true"></span>
                <span class="acc-text">
                  <span class="acc-title">Basic Information</span>
                  <span class="acc-sub">Background & Activity Type</span>
                </span>
              </span>
              <span class="acc-chevron" aria-hidden="true"></span>
            </summary>

            <div class="acc-body">
              <div class="field">
                <label for="organizing_body">Organizing Body:</label><br>
                <input list="org_list" id="organizing_body" name="organizing_body"
                  value="<?= htmlspecialchars($organizing_body) ?>" required>
                <datalist id="org_list">
                  <!-- temp selections -->
                  <option value="SOC">
                  <option value="SAS">
                  <option value="SEA">
                  <option value="CCJEF">
                  <option value="SHTM">
                </datalist>
              </div>

              <div class="field">
                <label>Background:</label><br>
                <label><input type="radio" name="background" value="OSA-Initiated Activity"
                    <?= ($background === 'OSA-Initiated Activity') ? 'checked' : '' ?>> OSA-Initiated Activity</label><br>
                <label><input type="radio" name="background" value="Student-Initiated Activity"
                    <?= ($background === 'Student-Initiated Activity') ? 'checked' : '' ?>> Student-Initiated
                  Activity</label><br>
                <label><input type="radio" name="background" value="Participation" <?= ($background === 'Participation') ? 'checked' : '' ?>> Participation</label>
              </div>

              <div class="field">
                <label>Type of Activity:</label><br>
                <?php
                $types = [
                  "On-campus Activity",
                  "Virtual Activity",
                  "Off-Campus Activity",
                  "Community Service - On-campus Activity",
                  "Community Service - Virtual Activity",
                  "Off-Campus Community Service"
                ];
                foreach ($types as $type) {
                  $checked = ($activity_type === $type) ? 'checked' : '';
                  echo "<label><input type='radio' name='activity_type' value='$type' $checked required> $type</label><br>";
                }
                ?>
              </div>

              <div class="field" id="series-block"
                style="display:<?= ($background === 'Participation') ? 'block' : 'none' ?>;">
                <label>Series:</label><br>
                <?php
                $series_options = ["College Days", "University Days", "Organization Themed-Fairs", "OSA-Initiated Activities", "HAU Institutional Activities"];
                foreach ($series_options as $opt) {
                  $checked = ($series === $opt) ? 'checked' : '';
                  echo "<label><input type='radio' name='series' value='$opt' $checked required> $opt</label><br>";
                }
                ?>
              </div>
            </div>
          </details>

          <!-- Accordion: Event Classification -->
          <details class="acc">
            <summary class="acc-head">
              <span class="acc-left">
                <span class="acc-dot" aria-hidden="true"></span>
                <span class="acc-text">
                  <span class="acc-title">Event Classification</span>
                  <span class="acc-sub">Nature & Event Name</span>
                </span>
              </span>
              <span class="acc-chevron" aria-hidden="true"></span>
            </summary>

            <div class="acc-body">
              <div class="field">
                <label for="nature">Nature:</label>
                <input type="text" name="nature" id="nature" value="<?= htmlspecialchars($nature) ?>" required>
              </div>

              <div class="field">
                <label for="activity_name">Activity Name:</label>
                <input type="text" name="activity_name" id="activity_name"
                  value="<?= htmlspecialchars($activity_name) ?>" required>
              </div>

              <div class="field">
                <label for="extraneous">Extraneous?</label><br>
                <label><input type="radio" name="extraneous" value="Yes" <?= ($extraneous === 'Yes') ? 'checked' : '' ?>
                    required> Yes</label>
                <label><input type="radio" name="extraneous" value="No" <?= ($extraneous === 'No') ? 'checked' : '' ?>
                    required> No</label>
              </div>

              <div class="field">
                <label for="target_metric">Target Metric:</label>
                <input type="text" name="target_metric" id="target_metric"
                  value="<?= htmlspecialchars($target_metric) ?>">
              </div>
            </div>
          </details>

          <!-- Accordion: Schedule & Logistics -->
          <details class="acc">
            <summary class="acc-head">
              <span class="acc-left">
                <span class="acc-dot" aria-hidden="true"></span>
                <span class="acc-text">
                  <span class="acc-title">Schedule & Logistics</span>
                  <span class="acc-sub">Dates, Venue, Participants, Distance</span>
                </span>
              </span>
              <span class="acc-chevron" aria-hidden="true"></span>
            </summary>

            <div class="acc-body">
              <div class="field">
                <label for="start_datetime">Start Date & Time:</label>
                <input type="datetime-local" name="start_datetime" id="start_datetime"
                  value="<?= htmlspecialchars($start_datetime) ?>" required>
              </div>

              <div class="field">
                <label for="end_datetime">End Date & Time:</label>
                <input type="datetime-local" name="end_datetime" id="end_datetime"
                  value="<?= htmlspecialchars($end_datetime) ?>" required>
              </div>

              <div class="field">
                <label for="participants">Participants:</label>
                <input type="text" name="participants" id="participants" value="<?= htmlspecialchars($participants) ?>"
                  required>
              </div>

              <div class="field">
                <label for="venue">Venue/Platform:</label>
                <input type="text" name="venue" id="venue" value="<?= htmlspecialchars($venue) ?>" required>
              </div>

              <div class="field" id="offcampus-block"
                style="display:<?= (strpos($activity_type, 'Off-Campus') !== false) ? 'block' : 'none' ?>;">
                <label>Distance:</label><br>
                <label><input type="radio" name="distance" value="Within Angeles City" <?= ($distance === 'Within Angeles City') ? 'checked' : '' ?> required> Within Angeles City</label><br>
                <label><input type="radio" name="distance" value="Within Central Luzon" <?= ($distance === 'Within Central Luzon') ? 'checked' : '' ?> required> Within Central Luzon</label><br>
                <label><input type="radio" name="distance" value="Rest of PH or Overseas" <?= ($distance === 'Rest of PH or Overseas') ? 'checked' : '' ?> required> Rest of PH or Overseas</label><br>

                <label>Participant Range:</label><br>
                <?php
                $ranges = ["1-2", "3-15", "15-25", "25+"];
                foreach ($ranges as $r) {
                  $checked = ($participant_range === $r) ? 'checked' : '';
                  echo "<label><input type='radio' name='participant_range' value='$r' $checked required> $r</label><br>";
                }
                ?>

                <label>More than 12 hours?</label><br>
                <label>
                  <input type="radio" name="overnight" value="1" <?= ($overnight == 1) ? 'checked' : '' ?> required>
                  Yes
                </label>

                <label>
                  <input type="radio" name="overnight" value="0" <?= ($overnight == 0) ? 'checked' : '' ?> required>
                  No
                </label>

              </div>
            </div>
          </details>

          <button type="submit" name="create_event" class="primary-btn">Create Event</button>
        </form>
      </section>
    </main>
  </div>

  <script src="../app/script/create_event.js"></script>

</body>

</html>