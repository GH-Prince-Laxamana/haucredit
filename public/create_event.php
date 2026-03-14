<?php
session_start();
require_once "../app/database.php";
require_once "../app/security_headers.php";
send_security_headers();

if (!isset($_SESSION["user_id"])) {
  header("Location: index.php");
  exit();
}

$ce_fields = [
  'organizing_body',
  'background',
  'activity_type',
  'series',
  'nature',
  'event_name',
  'start_datetime',
  'end_datetime',
  'participants',
  'venue_platform',
  'extraneous',
  'collect_payments',
  'target_metric',
  'distance',
  'participant_range',
  'overnight'
];

$event_id = $_GET['id'] ?? null;
$editing = !empty($event_id);

$formData = [];

if ($event_id) {
  $stmt = $conn->prepare("SELECT * 
                          FROM events 
                          WHERE event_id = ? 
                          AND user_id = ? 
                          AND archived_at IS NULL
                          LIMIT 1");
  $stmt->bind_param("ii", $event_id, $_SESSION["user_id"]);
  $stmt->execute();
  $result = $stmt->get_result();
  $existing_event = $result->fetch_assoc();

  if ($existing_event) {
    foreach ($ce_fields as $field) {
      $formData[$field] = $existing_event[$field] ?? '';
    }
  } else {
    die("Event not found or you don't have permission to edit it.");
  }
} else {
  foreach ($ce_fields as $field) {
    $formData[$field] = $_SESSION[$field] ?? '';
  }
}

$requirements_map = [
  'OSA-Initiated Activity' => [
    'On-campus Activity' => ['Approval Letter from Dean', 'Program Flow and/or Itinerary'],
    'Virtual Activity' => ['Approval Letter from Dean', 'Program Flow and/or Itinerary'],
    'Community Service - On-campus Activity' => ['Approval Letter from Dean', 'Program Flow and/or Itinerary', 'OCES Annex A Form'],
    'Community Service - Virtual Activity' => ['Approval Letter from Dean', 'Program Flow and/or Itinerary', 'OCES Annex A Form'],
    'Off-Campus Activity' => ['Approval Letter from Dean', 'Program Flow and/or Itinerary', 'Parental Consent', 'Letter of Undertaking', 'Planned Budget', 'List of Participants', 'CHEd Certificate of Compliance', 'OCES Annex A Form'],
    'Community Service - Off-campus Activity' => ['Approval Letter from Dean', 'Program Flow and/or Itinerary', 'Parental Consent', 'Letter of Undertaking', 'Planned Budget', 'List of Participants', 'CHEd Certificate of Compliance', 'OCES Annex A Form'],
  ],
  'Student-Initiated Activity' => [
    'On-campus Activity' => ['Program Flow and/or Itinerary', 'Planned Budget'],
    'Virtual Activity' => ['Program Flow and/or Itinerary', 'Planned Budget'],
    'Community Service - On-campus Activity' => ['Program Flow and/or Itinerary', 'Planned Budget', 'OCES Annex A Form'],
    'Community Service - Virtual Activity' => ['Program Flow and/or Itinerary', 'Planned Budget', 'OCES Annex A Form'],
    'Off-Campus Activity' => ['Program Flow and/or Itinerary', 'Parental Consent', 'Letter of Undertaking', 'Planned Budget', 'List of Participants', 'CHEd Certificate of Compliance', 'OCES Annex A Form'],
    'Community Service - Off-campus Activity' => ['Program Flow and/or Itinerary', 'Parental Consent', 'Letter of Undertaking', 'Planned Budget', 'List of Participants', 'CHEd Certificate of Compliance', 'OCES Annex A Form'],
  ],
  'Participation' => [
    'On-campus Activity' => [],
    'Virtual Activity' => [],
    'Community Service - On-campus Activity' => ['OCES Annex A Form'],
    'Community Service - Virtual Activity' => ['OCES Annex A Form'],
    'Off-Campus Activity' => ['Parental Consent', 'Letter of Undertaking', 'Planned Budget', 'List of Participants', 'CHEd Certificate of Compliance'],
    'Community Service - Off-campus Activity' => ['Parental Consent', 'Letter of Undertaking', 'Planned Budget', 'List of Participants', 'CHEd Certificate of Compliance', 'OCES Annex A Form'],
  ]
];

if (isset($_POST['create_event'])) {

  foreach ($ce_fields as $field) {
    $_SESSION[$field] = $_POST[$field] ?? null;
  }

  $organizing_body_json = isset($_SESSION['organizing_body'])
    ? json_encode($_SESSION['organizing_body'])
    : null;

  $event_status = "Pending Review";

  /* =========================
     UPDATE EVENT
  ========================= */

  if ($editing) {

    $stmt = $conn->prepare("
      UPDATE events SET
        organizing_body = ?, background = ?, activity_type = ?, series = ?, nature = ?,
        event_name = ?, start_datetime = ?, end_datetime = ?, participants = ?,
        venue_platform = ?, extraneous = ?, collect_payments = ?, target_metric = ?,
        distance = ?, participant_range = ?, overnight = ?
      WHERE event_id = ? AND user_id = ?
    ");

    $stmt->bind_param(
      "sssssssssssssssiis",
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
      $_SESSION['extraneous'],
      $_SESSION['collect_payments'],
      $_SESSION['target_metric'],
      $_SESSION['distance'],
      $_SESSION['participant_range'],
      $_SESSION['overnight'],
      $event_id,
      $_SESSION["user_id"]
    );

    $stmt->execute();

    /* UPDATE CALENDAR */

    $end = $_SESSION['end_datetime'] ?: null;
    $notes = "Event updated via Event Manager";

    $cal_stmt = $conn->prepare("
      UPDATE calendar_entries
      SET title=?, start_datetime=?, end_datetime=?, notes=?
      WHERE event_id=? AND user_id=?
    ");

    $cal_stmt->bind_param(
      "ssssii",
      $_SESSION['event_name'],
      $_SESSION['start_datetime'],
      $end,
      $notes,
      $event_id,
      $_SESSION['user_id']
    );

    $cal_stmt->execute();

  }

  /* =========================
     CREATE EVENT
  ========================= */ else {

    $stmt = $conn->prepare("
      INSERT INTO events (
        user_id, organizing_body, background, activity_type, series,
        nature, event_name, start_datetime, end_datetime,
        participants, venue_platform, extraneous, collect_payments, target_metric,
        distance, participant_range, overnight, event_status
      ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->bind_param(
      "isssssssssssssssis",
      $_SESSION["user_id"],
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
      $_SESSION['extraneous'],
      $_SESSION['collect_payments'],
      $_SESSION['target_metric'],
      $_SESSION['distance'],
      $_SESSION['participant_range'],
      $_SESSION['overnight'],
      $event_status
    );

    $stmt->execute();
    $event_id = $stmt->insert_id;

    /* INSERT CALENDAR ENTRY */

    $end = $_SESSION['end_datetime'] ?: null;
    $notes = "Event created via Event Manager";

    $cal_stmt = $conn->prepare("
      INSERT INTO calendar_entries
      (user_id,event_id,title,start_datetime,end_datetime,notes)
      VALUES (?,?,?,?,?,?)
    ");

    $cal_stmt->bind_param(
      "iissss",
      $_SESSION["user_id"],
      $event_id,
      $_SESSION['event_name'],
      $_SESSION['start_datetime'],
      $end,
      $notes
    );

    $cal_stmt->execute();
  }

  /* =========================
     REQUIREMENTS SYNC
  ========================= */

  $background = $_SESSION['background'];
  $activity_type = $_SESSION['activity_type'];

  $requirements_templates = [
    'Approval Letter from Dean' => 'https://docs.google.com/document/d/1cfTUM6YD0Lpf6DCZl0LjNTTeeBXtAmgUgM2eQBj7QOI/edit',
    'Program Flow and/or Itinerary' => 'https://docs.google.com/document/d/1cfTUM6YD0Lpf6DCZl0LjNTTeeBXtAmgUgM2eQBj7QOI/edit',
    'Parental Consent' => 'https://docs.google.com/document/d/1rCQbqIH1YFUxekaTCkIYTx3wxUnEKoHUxofG5c7K_1s/edit',
    'Letter of Undertaking' => 'https://docs.google.com/document/d/1vNsUTnyTeYo9sF_p6nvJCOOouWt7O7Du8m9OxbC4LZc/edit',
    'Planned Budget' => '',
    'List of Participants' => '',
    'CHEd Certificate of Compliance' => 'https://docs.google.com/document/d/1gdHMH0iFZpS3OFwoG8w1r8DZoMh_oeXB4nN22kQt21o/edit',
    'OCES Annex A Form' => ''
  ];

  $requirements_descs = [
    'Approval Letter from Dean' => "Submit this program flow with the adviser's noting signature and approval of your College/School Dean. For Uniwide Institutions, address it to Ms. Iris Ann Castro (OSA Director) through Mr. Paul Ernest D. Carreon (Student Activities Coordinator), and submit it without their signature. There is no need to place the names of Mr. Carreon and Ms. Castro on the approval.",

    'Program Flow and/or Itinerary' => 'If the program is spontaneous (meaning that it does not have a program flow), discuss in outline the guidelines of the event. For Off-Campus Activities, include the Travel Itinerary with the stopovers and indicate the places where you assemble, stop, and arrive (this is different from the event program flow/guidelines)',

    'Parental Consent' => 'Upload as one PDF File. Shall be individually notarized.',

    'Letter of Undertaking' => 'Shall be signed by the adviser. The Person-in-Charge shall always be an employee of the university.',

    'Planned Budget' => 'Discuss the source of budget, projected spending for all resources needed for the activity.',

    'List of Participants' => 'List and sort all students, employees, and guests to attend with their roles to the activity.',

    'CHEd Certificate of Compliance' => 'Shall be notarized. Please view template provided.',

    'OCES Annex A Form' => 'Form required by the Office of Community Extension Services.'
  ];

  $checklist = $requirements_map[$background][$activity_type] ?? [];

  $current_reqs = [];

  $stmt = $conn->prepare("SELECT req_name FROM requirements WHERE event_id=?");
  $stmt->bind_param("i", $event_id);
  $stmt->execute();
  $res = $stmt->get_result();

  while ($row = $res->fetch_assoc()) {
    $current_reqs[] = $row['req_name'];
  }

  $to_add = array_diff($checklist, $current_reqs);
  $to_remove = array_diff($current_reqs, $checklist);

  /* REMOVE OLD */

  if (!empty($to_remove)) {

    $stmt = $conn->prepare(
      "DELETE FROM requirements WHERE event_id=? AND req_name=?"
    );

    foreach ($to_remove as $req) {
      $stmt->bind_param("is", $event_id, $req);
      $stmt->execute();
    }
  }

  /* ADD NEW */

  if (!empty($to_add)) {

    $stmt = $conn->prepare(
      "INSERT INTO requirements (event_id, req_name, template_url, req_desc)
       VALUES (?, ?, ?, ?)"
    );

    foreach ($to_add as $req) {

      $template = $requirements_templates[$req] ?? null;
      $desc = $requirements_descs[$req] ?? '';
      $stmt->bind_param("isss", $event_id, $req, $template, $desc);
      $stmt->execute();
    }
  }

  /* UPDATE DOC COUNT */

  $stmt = $conn->prepare(
    "SELECT COUNT(*) AS total FROM requirements WHERE event_id=?"
  );
  $stmt->bind_param("i", $event_id);
  $stmt->execute();

  $res = $stmt->get_result();
  $docs_total = $res->fetch_assoc()['total'] ?? 0;

  $stmt->close();

  $stmt = $conn->prepare(
    "UPDATE events SET docs_total=? WHERE event_id=?"
  );
  $stmt->bind_param("ii", $docs_total, $event_id);
  $stmt->execute();

  /* CLEAR SESSION */

  foreach ($ce_fields as $field) {
    unset($_SESSION[$field]);
  }

  /* REDIRECT */

  if ($editing) {
    header("Location: view_event.php?id=$event_id");
  } else {
    header("Location: home.php");
  }

  exit();
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Create Event</title>
  <link rel="stylesheet" href="assets/styles/layout.css" />
  <link rel="stylesheet" href="assets/styles/ce_styles.css" />
</head>

<body>
  <div class="app">
    <div class="sidebar-overlay" id="sidebarOverlay" hidden></div>
    <?php include 'assets/includes/general_nav.php' ?>

    <main class="main">
      <header class="topbar ce-topbar">
        <button class="hamburger" id="menuBtn" type="button">☰</button>

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
                  $organizing_body = [];
                  if (!empty($formData['organizing_body'])) {
                    $organizing_body = json_decode($formData['organizing_body'], true);
                    if (!is_array($organizing_body))
                      $organizing_body = [];
                  }

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
                    <?= ($formData['background'] === 'OSA-Initiated Activity') ? 'checked' : '' ?>> OSA-Initiated Activity
                </label>

                <label>
                  <input type="radio" name="background" value="Student-Initiated Activity"
                    <?= ($formData['background'] === 'Student-Initiated Activity') ? 'checked' : '' ?> required>
                  Student-Initiated
                  Activity
                </label>

                <label>
                  <input type="radio" name="background" value="Participation"
                    <?= ($formData['background'] === 'Participation') ? 'checked' : '' ?> required> Participation
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
                  $checked = ($formData['activity_type'] === $type) ? 'checked' : '';

                  echo "<label><input type='radio' name='activity_type' value='$type' $checked required> $type</label>";
                }
                ?>
              </div>
            </fieldset>

            <fieldset class="field" id="series-block"
              style="display:<?= ($formData['background'] === 'Participation') ? 'flex' : 'none' ?>;">
              <label for="series" class="field-title">Series</label>
              <small class="hint">Select the series if this is a participation activity.</small>

              <div class="radio-group two-col">
                <?php
                $series_options = ['College Days', 'University Days', 'Organization Themed-Fairs', 'OSA-Initiated Activities', 'HAU Institutional Activities'];

                foreach ($series_options as $opt) {
                  $checked = ($formData['series'] === $opt) ? 'checked' : '';

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

              <textarea name="nature" id="nature" required><?= htmlspecialchars($formData['nature']) ?></textarea>
            </div>

            <div class="field long-field">
              <label for="event_name" class="field-title">Name of the Event</label>
              <small class="hint">
                If this is one event in a series of events (e.g. College Days, UDays, festivals with
                mini-events), place the umbrella event first, then put a colon with the name and a hyphen for the nature
                description. (ex. SAS Days 2025: Kundiman - Concert for a cause)
              </small>

              <textarea name="event_name" id="event_name"
                required><?= htmlspecialchars($formData['event_name']) ?></textarea>
            </div>

            <div class="field long-field">
              <label for="target_metric" class="field-title">Target Metric</label>
              <small class="hint">
                Indicate the target metric and the standard value you wish to achieve. (ex. 50%
                Turnout of Voters, 75% Satisfaction Rating)
              </small>

              <textarea name="target_metric" id="target_metric"
                rows="2"><?= htmlspecialchars($formData['target_metric']) ?></textarea>
            </div>

            <fieldset class="field">
              <label for="extraneous" class="field-title">Is this an extraneous activity?</label>

              <div class="radio-group inline">
                <label>
                  <input type="radio" name="extraneous" value="Yes" <?= ($formData['extraneous'] === 'Yes') ? 'checked' : '' ?> required> Yes
                </label>

                <label>
                  <input type="radio" name="extraneous" value="No" <?= ($formData['extraneous'] === 'No') ? 'checked' : '' ?> required>
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
                  <input type="radio" name="collect_payments" value="Yes" <?= ($formData['collect_payments'] === 'Yes') ? 'checked' : '' ?> required>
                  Yes
                </label>

                <label>
                  <input type="radio" name="collect_payments" value="No" <?= ($formData['collect_payments'] === 'No') ? 'checked' : '' ?> required> No
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
                  value="<?= htmlspecialchars($formData['start_datetime']) ?>" required>
              </div>

              <div class="field">
                <label for="end_datetime" class="field-title">End Date and Time</label>
                <small class="hint">Indicate the start of egress</small>

                <input type="datetime-local" name="end_datetime" id="end_datetime"
                  value="<?= htmlspecialchars($formData['end_datetime']) ?>" required>
              </div>
            </div>

            <div class="field long-field">
              <label for="participants" class="field-title">Participants</label>
              <small class="hint">
                Indicate number and group (ex. 8 members, 7 officers, 2 guest speakers, 40
                beneficiaries from XYZ Foundation)
              </small>

              <textarea name="participants" id="participants" rows="2"
                required><?= htmlspecialchars($formData['participants']) ?></textarea>
            </div>

            <div class="field long-field">
              <label for="venue_platform" class="field-title">Venue / Platform</label>
              <small class="hint">
                Indicate the room number for caserooms. Provide the invite link if for online sessions
                and invite either studentactivities@hau.edu.ph or studentactivities.hauosa@gmail.com
              </small>

              <textarea name="venue_platform" id="venue_platform"
                required><?= htmlspecialchars($formData['venue_platform']) ?></textarea>
            </div>

            <fieldset class="field" id="offcampus-block"
              style="display:<?= (strpos($formData['activity_type'], 'Off-Campus') !== false) ? 'block' : 'none' ?>;">

              <div class="form-row">
                <fieldset class="field">
                  <label for="participant_range" class="field-title">Range of Total Number of Participants</label>

                  <div class="radio-group">
                    <?php
                    $ranges = ['1-2', '3-15', '15-25', '25 or more'];

                    foreach ($ranges as $r) {
                      $checked = ($formData['participant_range'] === $r) ? 'checked' : '';

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

                    foreach ($distances as $option) {
                      $checked = (($formData['distance'] ?? '') === $option) ? 'checked' : '';

                      echo "<label>
                              <input type='radio' name='distance' value='" . htmlspecialchars($option) . "' $checked required>
                              $option
                            </label>";
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
                    <input type="radio" name="overnight" value="1" <?= ($formData['overnight'] == 1) ? 'checked' : '' ?>
                      required> Yes
                  </label>

                  <label>
                    <input type="radio" name="overnight" value="0" <?= ($formData['overnight'] == 0) ? 'checked' : '' ?>
                      required> No
                  </label>
                </div>
              </div>
            </fieldset>
          </div>
        </details>

        <div class="step-actions step-2-actions">
          <button type="button" class="secondary-btn back-btn">Back</button>
          <button type="submit" name="create_event" class="primary-btn create-btn" disabled>
            <?= $editing ? 'Update Event' : 'Create Event' ?>
          </button>
        </div>
      </form>

      <?php include 'assets/includes/footer.php' ?>
    </main>
  </div>
  <script src="assets/script/layout.js?v=1"></script>
  <script src="../app/script/create_event.js"></script>
</body>

</html>