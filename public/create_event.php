<?php
session_start();
require_once "../app/database.php";
require_once "../app/security_headers.php";
send_security_headers();

if (!isset($_SESSION["user_id"])) {
  header("Location: index.php");
  exit();
}

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
  $_SESSION['do_collect_payments'] = $_POST['collect_payments'] ?? '';
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
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

    $user_id = $_SESSION["user_id"];
    $organizing_body_json = isset($_SESSION['organizing_body'])
      ? json_encode($_SESSION['organizing_body'])
      : null;

    $stmt->bind_param(
      "issssssssisssssss",
      $user_id,
      $organizing_body_json,
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

    <?php include 'assets/includes/general_nav.php' ?>

    <main class="main">
      <header class="topbar ce-topbar">
        <div class="title-wrap">
          <h1>Create Event</h1>
          <p>Fill out the form below to create a new event.</p>
        </div>
      </header>

      <form method="POST" class="event-form">
        <details class="step-1 acc" open>
          <summary class="acc-head">

            <span class="acc-left">
              <span class="acc-dot"></span>

              <span class="acc-text">
                <label for="organizing_body" class="acc-title">Classification</label>
                <span class="acc-sub">Background & Activity Type</span>
              </span>
            </span>

            <span class="acc-chevron"></span>
          </summary>

          <div class="acc-body">
            <fieldset class="field">
              <label for="organizing_body" class="field-title">Organizing Body</label>
              <small class="hint">Select one or more organizing bodies.</small>

              <select id="organizing_body" name="organizing_body[]" multiple hidden>
                <?php
                $org_options = [
                  // HAU OFFICE
                  "HAU OSA",

                  // UNIVERSITY STUDENT GOVERNMENT
                  "HAUSG USC",
                  "HAUSG HC",
                  "HAUSG SEN",
                  "HAUSG COMELEC",
                  "HAUSG CSO",
                  "HAUSG CFA",

                  // COLLEGE STUDENT COUNCILS
                  "HAUSG CSC-CCJEF",
                  "HAUSG CSC-SAS",
                  "HAUSG CSC-SBA",
                  "HAUSG CSC-SoC",
                  "HAUSG CSC-SEd",
                  "HAUSG CSC-SEA",
                  "HAUSG CSC-SHTM",
                  "HAUSG CSC-SNAMS",

                  // STUDENT PUBLICATIONS
                  "HPC Angge",
                  "HPC HQ",
                  "HPC NX",
                  "HPC Enteng",
                  "HPC AP",
                  "HPC Reple",
                  "HPC Soln",
                  "HPC CC",
                  "HPC LL",

                  // UNI-WIDE ORGANIZATIONS
                  "Uniwide DC",
                  "Uniwide JJC",
                  "Uniwide JO",
                  "Uniwide GDGoC",
                  "Uniwide ADS",
                  "Uniwide RCY",
                  "Uniwide RAC",
                  "Uniwide APLMS",
                  "Uniwide SVE",
                  "Uniwide 21CC",
                  "Uniwide HPC",

                  // SCHOOL ORGANIZATIONS
                  "CCJEF COPS",
                  "CCJEF SAFE",
                  "SAS PsychSoc",
                  "SAS CL",
                  "SBA Mansoc",
                  "SoC MAFIA",
                  "SoC LOOP",
                  "SoC CG",
                  "SoC CSIA",
                  "SEd KAS",
                  "SEd KLDS",
                  "SEA SAEP",
                  "SEA UAPSA",
                  "SEA PSME",
                  "SEA PIIE",
                  "SEA IIEE",
                  "SEA PICE",
                  "SEA IECEP",
                  "SEA ICpEP",
                  "SHTM HMAP",
                  "SHTM LTSP",
                  "SNAMS ARTS",
                  "SNAMS PHISMETS",
                  "SNAMS SANS",

                  // POLITICAL PARTIES
                  "PP Lualu",
                  "PP Sulung",
                  "PP Sulagpo",
                  "PP Tindig",
                ];

                foreach ($org_options as $org) {
                  $selected = (isset($organizing_body) && is_array($organizing_body) && in_array($org, $organizing_body)) ? 'selected' : '';

                  echo "<option value=\"$org\" $selected>$org</option>";
                }
                ?>
              </select>

              <div class="selected-tags" id="selectedTags">
                <?php if (!empty($organizing_body) && is_array($organizing_body)): ?>

                  <?php foreach ($organizing_body as $org): ?>
                    <div class="tag">
                      <?= htmlspecialchars($org) ?><span>&times;</span>
                    </div>

                  <?php endforeach; ?>
                <?php endif; ?>
              </div>

              <div class="multi-select" id="orgDropdown">
                <input type="text" placeholder="Search or select organization" class="multi-input" autocomplete="off">

                <div class="dropdown-list"></div>
              </div>
            </fieldset>

            <fieldset class="field">
              <label for="background" class="field-title">Background</label>
              <small class="hint">Indicate who initiated this activity.</small>

              <div class="radio-group two-col">
                <label>
                  <input type="radio" name="background" value="OSA-Initiated Activity" required
                    <?= ($background === 'OSA-Initiated Activity') ? 'checked' : '' ?>> OSA-Initiated Activity
                </label>

                <label>
                  <input type="radio" name="background" value="Student-Initiated Activity"
                    <?= ($background === 'Student-Initiated Activity') ? 'checked' : '' ?> required> Student-Initiated
                  Activity
                </label>

                <label>
                  <input type="radio" name="background" value="Participation" <?= ($background === 'Participation') ? 'checked' : '' ?> required> Participation
                </label>
              </div>
            </fieldset>

            <fieldset class="field">
              <label for="activity_type" class="field-title">Type Activity</label>
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

            <fieldset class="field" id="series-block"
              style="display:<?= ($background === 'Participation') ? 'flex' : 'none' ?>;">
              <label for="series" class="field-title">Series</label>
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

        <div class="step-actions step1-actions">
          <button type="button" class="primary-btn next-btn" disabled>Next</button>
        </div>

        <details class="step-2 acc">
          <summary class="acc-head">

            <span class="acc-left">
              <span class="acc-dot"></span>

              <span class="acc-text">
                <label for="organizing_body" class="acc-title">Basic Information</label>
                <span class="acc-sub">Nature & Event Name</span>
              </span>
            </span>

            <span class="acc-chevron"></span>
          </summary>

          <div class="acc-body">
            <div class="field long-field">
              <label for="nature" class="field-title">Nature of the Event</label>
              <small class="hint">
                If you were asked to describe what is your activity in one to three words, what would
                it be? (ex. Singing Context, Quiz Bee, Tutorial Session, Bulletin Board Campaign, Online Poster
                Campaign, Amazing Race, Forum, Seminar, Workshop, Focus Group Discussion)
              </small>

              <textarea name="nature" id="nature" required><?= htmlspecialchars($nature) ?></textarea>
            </div>

            <div class="field long-field">
              <label for="activity_name" class="field-title">Name of the Event</label>
              <small class="hint">
                If this is one event in a series of events (e.g. College Days, UDays, festivals with
                mini-events), place the umbrella event first, then put a colon with the name and a hyphen for the nature
                description. (ex. SAS Days 2025: Kundiman - Concert for a cause)
              </small>

              <textarea name="activity_name" id="activity_name"
                required><?= htmlspecialchars($activity_name) ?></textarea>
            </div>

            <div class="field long-field">
              <label for="target_metric" class="field-title">Target Metric</label>
              <small class="hint">
                Indicate the target metric and the standard value you wish to achieve. (ex. 50%
                Turnout of Voters, 75% Satisfaction Rating)
              </small>

              <textarea name="target_metric" id="target_metric"
                rows="2"><?= htmlspecialchars($target_metric) ?></textarea>
            </div>

            <fieldset class="field">
              <label for="extraneous" class="field-title">Is this an extraneous activity?</label>

              <div class="radio-group inline">
                <label>
                  <input type="radio" name="extraneous" value="Yes" <?= ($extraneous === 'Yes') ? 'checked' : '' ?>
                    required> Yes
                </label>

                <label>
                  <input type="radio" name="extraneous" value="No" <?= ($extraneous === 'No') ? 'checked' : '' ?> required>
                  No
                </label>
              </div>
            </fieldset>

            <fieldset class="field">
              <label for="collect-payments" class="field-title">
                Would you collect payments or sell merchandise for this activity?
              </label>
              <small class="hint"></small>

              <div class="radio-group inline">
                <label>
                  <input type="radio" name="collect_payments" value="Yes" <?= ($collect_payments === 'Yes') ? 'checked' : '' ?> required>
                  Yes
                </label>

                <label>
                  <input type="radio" name="collect_payments" value="No" <?= ($collect_payments === 'No') ? 'checked' : '' ?> required> No
                </label>
              </div>
            </fieldset>
          </div>
        </details>

        <details class="step-2 acc">
          <summary class="acc-head">

            <span class="acc-left">
              <span class="acc-dot"></span>

              <span class="acc-text">
                <label for="organizing_body" class="acc-title">Logistics</label>
                <span class="acc-sub">Schedule, Venue, & Participants</span>
              </span>
            </span>

            <span class="acc-chevron"></span>
          </summary>

          <div class="acc-body">
            <div class="form-row">

              <div class="field">
                <label for="start_datetime" class="field-title">Start Date and Time</label>
                <small class="hint">Indicate the start of ingress</small>

                <input type="datetime-local" name="start_datetime" id="start_datetime"
                  value="<?= htmlspecialchars($start_datetime) ?>" required>
              </div>

              <div class="field">
                <label for="end_datetime" class="field-title">End Date and Time</label>
                <small class="hint">Indicate the start of egress</small>

                <input type="datetime-local" name="end_datetime" id="end_datetime"
                  value="<?= htmlspecialchars($end_datetime) ?>" required>
              </div>
            </div>

            <div class="field long-field">
              <label for="participants" class="field-title">Participants</label>
              <small class="hint">
                Indicate number and group (ex. 8 members, 7 officers, 2 guest speakers, 40
                beneficiaries from XYZ Foundation)
              </small>

              <textarea name="participants" id="participants" rows="2"
                required><?= htmlspecialchars($participants) ?></textarea>
            </div>

            <div class="field long-field">
              <label for="venue" class="field-title">Venue / Platform</label>
              <small class="hint">
                Indicate the room number for caserooms. Provide the invite link if for online sessions
                and invite either studentactivities@hau.edu.ph or studentactivities.hauosa@gmail.com
              </small>

              <textarea name="venue" id="venue" required><?= htmlspecialchars($venue) ?></textarea>
            </div>

            <fieldset class="field" id="offcampus-block"
              style="display:<?= (strpos($activity_type, 'Off-Campus') !== false) ? 'block' : 'none' ?>;">

              <div class="form-row">
                <fieldset class="field">
                  <label for="participant_range" class="field-title">Range of Total Number of Participants</label>

                  <div class="radio-group">
                    <?php
                    $ranges = ['1-2', '3-15', '15-25', '25 or more'];

                    foreach ($ranges as $r) {
                      $checked = ($participant_range === $r) ? 'checked' : '';

                      echo "<label><input type='radio' name='participant_range' value='$r' $checked required> $r</label>";
                    }
                    ?>
                  </div>
                </fieldset>

                <fieldset class="field">
                  <label for="distance" class="field-title">Distance</label>

                  <div class="radio-group">
                    <?php
                    $distances = ['Within Angeles City', 'Within Central Luzon', 'Rest of PH or Overseas'];

                    foreach ($distances as $distance) {
                      $checked = ($distances === $distance) ? 'checked' : '';

                      echo "<label><input type='radio' name='distance' value='$distance' $checked required> $distance</label>";
                    }
                    ?>
                  </div>
                </fieldset>
              </div>

              <div class="field">
                <label for="participant_range" class="field-title">
                  Will the activity last more than 12 hours from arrival to departure?
                </label>
                <small>Select "Yes" if it includes an overnight stay</small>

                <div class="radio-group inline">
                  <label>
                    <input type="radio" name="overnight" value="1" <?= ($overnight == 1) ? 'checked' : '' ?> required> Yes
                  </label>

                  <label>
                    <input type="radio" name="overnight" value="0" <?= ($overnight == 0) ? 'checked' : '' ?> required> No
                  </label>
                </div>
              </div>
            </fieldset>
          </div>
        </details>

        <div class="step-actions step-2-actions">
          <button type="button" class="secondary-btn back-btn">Back</button>
          <button type="submit" name="create_event" class="primary-btn create-btn" disabled>Create Event</button>
        </div>
      </form>

      <?php include 'assets/includes/footer.php' ?>
    </main>
  </div>

  <script src="../app/script/create_event.js"></script>
</body>

</html>