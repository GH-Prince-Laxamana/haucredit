<?php
session_start();
require_once "../app/database.php";
require_once "../app/security_headers.php";
send_security_headers();

if (!isset($_SESSION["user_id"])) {
  header("Location: index.php");
  exit();
}

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
$collect_payments = $_SESSION['do_collect_payments'] ?? '';
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
  $_SESSION['collect_payments'] = $_POST['do_collect_payments'] ?? '';
  $_SESSION['target_metric'] = $_POST['target_metric'] ?? '';
  $_SESSION['distance'] = $_POST['distance'] ?? null;
  $_SESSION['participant_range'] = $_POST['participant_range'] ?? null;
  $_SESSION['overnight'] = $_POST['overnight'] ?? null;

  if (isset($_POST['create_event'])) {
    $stmt = $conn->prepare("
            INSERT INTO events (
                user_id, organizing_body, background, activity_type, series,
                nature, event_name, start_datetime, end_datetime,
                participants, venue_platform, is_extraneous, do_collect_payments, target_metric,
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
      $_SESSION['do_collect_payments'],
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
      $_SESSION['do_collect_payments'],
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
          <h1>Create Event</h1>
          <p>Fill out the form below to create a new event.</p>
        </div>
      </header>

      <form method="POST" class="event-form">
        <!-- ================= BASIC INFORMATION ================= -->
        <details class="acc" open>
          <summary class="acc-head">
            <span class="acc-title">Basic Information</span>
            <span class="acc-sub">Background & Activity Type</span>
          </summary>

          <div class="acc-body">

            <!-- Organizing Body -->
            <div class="form-group">
              <label for="organizing_body">Organizing Body</label>
              <small class="hint">Select one or more organizing bodies. You can also type to search.</small>

              <!-- Hidden semantic select -->
              <select id="organizing_body" name="organizing_body[]" multiple hidden>
                <?php
                $org_options = [
                  "University Student Council (USC)",
                  "HAUSG CSC-SOC",
                  "HAUSG CSC-SAS",
                  "HAUSG CSC-SHTM",
                  "HAUSG CSC-SEA",
                  "HAUSG CSC-SNAMS",
                  "HAUSG CSC-CCJEF",
                  "HAUSG CSC-SED",
                  "HAUSG CSC-SBA",
                  "Department Organization",
                  "College Organization",
                  "Special Interest Group"
                ];

                foreach ($org_options as $org) {
                  $selected = (isset($organizing_body) && is_array($organizing_body) && in_array($org, $organizing_body))
                    ? 'selected' : '';
                  echo "<option value=\"$org\" $selected>$org</option>";
                }
                ?>
              </select>

              <!-- Custom input + dropdown -->
              <div class="multi-select" id="orgDropdown">
                <input type="text" placeholder="Type or select organization..." class="multi-input" autocomplete="off">
                <div class="dropdown-list"></div>
              </div>

              <!-- Selected tags -->
              <div class="selected-tags" id="selectedTags">
                <?php if (!empty($organizing_body) && is_array($organizing_body)): ?>
                  <?php foreach ($organizing_body as $org): ?>
                    <div class="tag">
                      <?= htmlspecialchars($org) ?><span>&times;</span>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
            </div>

            <!-- Background -->
            <fieldset class="field">
              <legend>Background</legend>
              <small class="hint">Indicate who initiated this activity.</small>
              <div class="radio-group two-col">
                <label><input type="radio" name="background" value="OSA-Initiated Activity"
                    <?= ($background === 'OSA-Initiated Activity') ? 'checked' : '' ?>> OSA-Initiated Activity</label>

                <label><input type="radio" name="background" value="Student-Initiated Activity"
                    <?= ($background === 'Student-Initiated Activity') ? 'checked' : '' ?>> Student-Initiated
                  Activity</label>

                <label><input type="radio" name="background" value="Participation" <?= ($background === 'Participation') ? 'checked' : '' ?>> Participation</label>
              </div>
            </fieldset>

            <!-- Activity Type -->
            <fieldset class="field">
              <legend>Type of Activity</legend>
              <small class="hint">Choose the activity type. This helps categorize events for reporting.</small>
              <div class="radio-group two-col">
                <?php
                $types = ['On-campus Activity', 'Virtual Activity', 'Off-Campus Activity', 'Community Service - On-campus Activity', 'Community Service - Virtual Activity', 'Community Service - Off-campus Activity'];

                foreach ($types as $type) {
                  $checked = ($activity_type === $type) ? 'checked' : '';
                  echo "<label><input type='radio' name='activity_type' value='$type' $checked required> $type</label>";
                }
                ?>
              </div>
            </fieldset>

            <!-- Series -->
            <fieldset class="field" id="series-block"
              style="display:<?= ($background === 'Participation') ? 'block' : 'none' ?>;">
              <legend>Series</legend>
              <small class="hint">Select the series if this is a participation activity.</small>
              <div class="radio-group two-col">
                <?php
                $series_options = ['College Days', 'University Days', 'Organization Themed-Fairs', 'OSA-Initiated Activities', 'HAU Institutional Activities'];

                foreach ($series_options as $opt) {
                  $checked = ($series === $opt) ? 'checked' : '';
                  echo "<label><input type='radio' name='series' value='$opt' $checked required> $opt</label>";
                }
                ?>
              </div>
            </fieldset>

          </div>
        </details>


        <details class="acc">
          <summary class="acc-head">
            <span class="acc-title">Event Classification</span>
            <span class="acc-sub">Nature & Event Name</span>
          </summary>

          <div class="acc-body">

            <div class="form-row">
              <div class="field long-field">
                <label for="nature">Nature</label>
                <small class="hint">Describe the nature of the activity (e.g., Workshop, Seminar, Outreach).</small>
                <textarea name="nature" id="nature" rows="3" required><?= htmlspecialchars($nature) ?></textarea>
              </div>

              <div class="field long-field">
                <label for="activity_name">Activity Name</label>
                <small class="hint">Provide a concise title or name for the activity.</small>
                <textarea name="activity_name" id="activity_name" rows="3"
                  required><?= htmlspecialchars($activity_name) ?></textarea>
              </div>
            </div>

            <fieldset class="field">
              <legend>Extraneous?</legend>
              <small class="hint">Specify if this event is considered extraneous.</small>
              <div class="radio-group inline">
                <label><input type="radio" name="extraneous" value="Yes" <?= ($extraneous === 'Yes') ? 'checked' : '' ?>
                    required> Yes</label>
                <label><input type="radio" name="extraneous" value="No" <?= ($extraneous === 'No') ? 'checked' : '' ?>
                    required> No</label>
              </div>
            </fieldset>
            
            <fieldset class="field">
              <legend>Collect Payments?</legend>
              <small class="hint">Specify if this event is considered extraneous.</small>
              <div class="radio-group inline">
                <label><input type="radio" name="collect_payments" value="Yes" <?= ($collect_payments === 'Yes') ? 'checked' : '' ?>
                    required> Yes</label>
                <label><input type="radio" name="collect_payments" value="No" <?= ($collect_payments === 'No') ? 'checked' : '' ?>
                    required> No</label>
              </div>
            </fieldset>

            <div class="field long-field">
              <label for="target_metric">Target Metric</label>
              <small class="hint">Optional: Enter measurable targets (e.g., number of attendees).</small>
              <textarea name="target_metric" id="target_metric"
                rows="2"><?= htmlspecialchars($target_metric) ?></textarea>
            </div>

          </div>
        </details>

        <details class="acc">
          <summary class="acc-head">
            <span class="acc-title">Schedule & Logistics</span>
            <span class="acc-sub">Dates, Venue, Participants</span>
          </summary>

          <div class="acc-body">

            <!-- Dates -->
            <div class="form-row">
              <div class="field">
                <label for="start_datetime">Start Date & Time</label>
                <small class="hint">When the event starts.</small>
                <input type="datetime-local" name="start_datetime" id="start_datetime"
                  value="<?= htmlspecialchars($start_datetime) ?>" required>
              </div>

              <div class="field">
                <label for="end_datetime">End Date & Time</label>
                <small class="hint">When the event ends.</small>
                <input type="datetime-local" name="end_datetime" id="end_datetime"
                  value="<?= htmlspecialchars($end_datetime) ?>" required>
              </div>
            </div>

            <div class="form-row">
              <div class="field long-field">
                <label for="participants">Participants</label>
                <small class="hint">Describe participants or groups expected to attend.</small>
                <textarea name="participants" id="participants" rows="2"
                  required><?= htmlspecialchars($participants) ?></textarea>
              </div>
            </div>

            <div class="form-row">
              <div class="field long-field">
                <label for="venue">Venue / Platform</label>
                <small class="hint">Specify where the event will take place (physical or virtual).</small>
                <textarea name="venue" id="venue" rows="2" required><?= htmlspecialchars($venue) ?></textarea>
              </div>
            </div>

            <!-- Off-campus block -->
            <fieldset class="field" id="offcampus-block"
              style="display:<?= (strpos($activity_type, 'Off-Campus') !== false) ? 'block' : 'none' ?>;">
              <legend>Off-Campus Details</legend>
              <small class="hint">Provide off-campus location details if applicable.</small>

              <div class="radio-group two-col">
                <label><input type="radio" name="distance" value="Within Angeles City" <?= ($distance === 'Within Angeles City') ? 'checked' : '' ?> required> Within Angeles City</label>
                <label><input type="radio" name="distance" value="Within Central Luzon" <?= ($distance === 'Within Central Luzon') ? 'checked' : '' ?> required> Within Central Luzon</label>
                <label><input type="radio" name="distance" value="Rest of PH or Overseas" <?= ($distance === 'Rest of PH or Overseas') ? 'checked' : '' ?> required> Rest of PH or Overseas</label>
              </div>

              <legend>Participant Range</legend>
              <small class="hint">Indicate the expected number of participants.</small>
              <div class="radio-group inline">
                <?php
                $ranges = ['1-2', '3-15', '15-25', '25 or more'];
                foreach ($ranges as $r) {
                  $checked = ($participant_range === $r) ? 'checked' : '';
                  echo "<label><input type='radio' name='participant_range' value='$r' $checked required> $r</label>";
                }
                ?>
              </div>

              <legend>More than 12 hours?</legend>
              <small class="hint">Indicate if the event spans more than 12 hours.</small>
              <div class="radio-group inline">
                <label><input type="radio" name="overnight" value="1" <?= ($overnight == 1) ? 'checked' : '' ?> required>
                  Yes</label>
                <label><input type="radio" name="overnight" value="0" <?= ($overnight == 0) ? 'checked' : '' ?> required>
                  No</label>
              </div>

            </fieldset>

          </div>
        </details>

        <button type="submit" name="create_event" class="primary-btn">
          Create Event
        </button>

      </form>

    </main>
  </div>

  <script src="../app/script/create_event.js"></script>

</body>

</html>