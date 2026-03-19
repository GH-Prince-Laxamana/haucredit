<?php
// Start session to manage user authentication and temporary form data
session_start();
require_once "../app/database.php";
require_once "../app/security_headers.php";
send_security_headers();

// Check if user is logged in; redirect to login if not
if (!isset($_SESSION["user_id"])) {
  header("Location: index.php");
  exit();
}

// Define the list of form fields used throughout the script for consistency
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
  'overnight',
  'has_visitors'
];

// Get event ID from URL for editing; null if creating new
$event_id = $_GET['id'] ?? null;
$editing = !empty($event_id);

// Initialize form data array
$formData = [];

// Helper function to fetch and populate form data for editing or new events
// Now fetches from multiple tables using JOINs to match the normalized schema
function fetchFormData($event_id, $ce_fields, $editing) {
  global $conn;
  $formData = [];
  if ($editing) {
    // Fetch existing event data from events and child tables via JOIN
    $stmt = $conn->prepare("
      SELECT 
        e.organizing_body, et.background, et.activity_type, et.series,
        e.nature, e.event_name, ed.start_datetime, ed.end_datetime,
        ep.participants, el.venue_platform, elg.extraneous, elg.collect_payments,
        em.target_metric, el.distance, ep.participant_range, elg.overnight, ep.has_visitors
      FROM events e
      LEFT JOIN event_type et ON e.event_id = et.event_id
      LEFT JOIN event_dates ed ON e.event_id = ed.event_id
      LEFT JOIN event_participants ep ON e.event_id = ep.event_id
      LEFT JOIN event_location el ON e.event_id = el.event_id
      LEFT JOIN event_logistics elg ON e.event_id = elg.event_id
      LEFT JOIN event_metrics em ON e.event_id = em.event_id
      WHERE e.event_id = ? AND e.user_id = ? AND e.archived_at IS NULL
      LIMIT 1
    ");
    $stmt->bind_param("ii", $event_id, $_SESSION["user_id"]);
    $stmt->execute();
    $result = $stmt->get_result();
    $existing_event = $result->fetch_assoc();

    if ($existing_event) {
      // Populate formData with fetched values
      foreach ($ce_fields as $field) {
        $formData[$field] = $existing_event[$field] ?? '';
      }
    } else {
      // Error if event not found or no permission
      popup_error("Event not found or you don't have permission to edit it.");
    }
  } else {
    // For new events, use session data if available
    foreach ($ce_fields as $field) {
      $formData[$field] = $_SESSION[$field] ?? '';
    }
  }
  return $formData;
}

// Fetch form data using the helper
$formData = fetchFormData($event_id, $ce_fields, $editing);

// Helper function to fetch requirements templates and descriptions from the DB (system event)
// This replaces hardcoded arrays for better maintainability
function fetchRequirementsFromDB() {
  global $conn;
  $requirements_templates = [];
  $requirements_descs = [];
  try {
    // Query the system event's requirements for defaults
    $stmt = $conn->prepare("
      SELECT r.req_name, rf.template_url, r.req_desc
      FROM requirements r
      LEFT JOIN requirement_files rf ON r.req_id = rf.req_id
      WHERE r.event_id = (SELECT event_id FROM events WHERE is_system_event = 1 LIMIT 1)
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
      $requirements_templates[$row['req_name']] = $row['template_url'] ?? '';
      $requirements_descs[$row['req_name']] = $row['req_desc'] ?? '';
    }
  } catch (Exception $e) {
    // Fallback to empty arrays if DB fetch fails
    $requirements_templates = [];
    $requirements_descs = [];
  }
  return [$requirements_templates, $requirements_descs];
}

// Fetch requirements data from DB
list($requirements_templates, $requirements_descs) = fetchRequirementsFromDB();

// Define the requirements map (unchanged, as it's logic-based)
$requirements_map = [
  'OSA-Initiated Activity' => [
    'On-campus Activity' => ['Approval Letter from Dean', 'Program Flow and/or Itinerary'],
    'Virtual Activity' => ['Approval Letter from Dean', 'Program Flow and/or Itinerary'],
    'Community Service - On-campus Activity' => ['Approval Letter from Dean', 'Program Flow and/or Itinerary', 'Student Organization Intake Form (OCES Annex A Form)'],
    'Community Service - Virtual Activity' => ['Approval Letter from Dean', 'Program Flow and/or Itinerary', 'Student Organization Intake Form (OCES Annex A Form)'],
    'Off-Campus Activity' => ['Approval Letter from Dean', 'Program Flow and/or Itinerary', 'Parental Consent', 'Letter of Undertaking', 'Planned Budget', 'List of Participants', 'CHEd Certificate of Compliance', 'Student Organization Intake Form (OCES Annex A Form)'],
    'Community Service - Off-campus Activity' => ['Approval Letter from Dean', 'Program Flow and/or Itinerary', 'Parental Consent', 'Letter of Undertaking', 'Planned Budget', 'List of Participants', 'CHEd Certificate of Compliance', 'Student Organization Intake Form (OCES Annex A Form)'],
  ],
  'Student-Initiated Activity' => [
    'On-campus Activity' => ['Program Flow and/or Itinerary', 'Planned Budget'],
    'Virtual Activity' => ['Program Flow and/or Itinerary', 'Planned Budget'],
    'Community Service - On-campus Activity' => ['Program Flow and/or Itinerary', 'Planned Budget', 'Student Organization Intake Form (OCES Annex A Form)'],
    'Community Service - Virtual Activity' => ['Program Flow and/or Itinerary', 'Planned Budget', 'Student Organization Intake Form (OCES Annex A Form)'],
    'Off-Campus Activity' => ['Program Flow and/or Itinerary', 'Parental Consent', 'Letter of Undertaking', 'Planned Budget', 'List of Participants', 'CHEd Certificate of Compliance', 'OCES Annex A Form'],
    'Community Service - Off-campus Activity' => ['Program Flow and/or Itinerary', 'Parental Consent', 'Letter of Undertaking', 'Planned Budget', 'List of Participants', 'CHEd Certificate of Compliance', 'OCES Annex A Form'],
  ],
  'Participation' => [
    'On-campus Activity' => [],
    'Virtual Activity' => [],
    'Community Service - On-campus Activity' => ['Student Organization Intake Form (OCES Annex A Form)'],
    'Community Service - Virtual Activity' => ['Student Organization Intake Form (OCES Annex A Form)'],
    'Off-Campus Activity' => ['Parental Consent', 'Letter of Undertaking', 'Planned Budget', 'List of Participants', 'CHEd Certificate of Compliance'],
    'Community Service - Off-campus Activity' => ['Parental Consent', 'Letter of Undertaking', 'Planned Budget', 'List of Participants', 'CHEd Certificate of Compliance', 'OCES Annex A Form'],
  ]
];

// Helper function to insert/update event data across tables
function insertEventData($event_id, $editing) {
  global $conn;

  // Prepare common data
  $organizing_body_json = isset($_SESSION['organizing_body']) ? json_encode($_SESSION['organizing_body']) : null;
  $event_status = "Pending Review";

  if ($editing) {
    // Update events table
    $stmt = $conn->prepare("UPDATE events SET organizing_body = ?, nature = ?, event_name = ? WHERE event_id = ? AND user_id = ?");
    $stmt->bind_param("sssii", $organizing_body_json, $_SESSION['nature'], $_SESSION['event_name'], $event_id, $_SESSION["user_id"]);
    $stmt->execute();

    // Update child tables
    $conn->query("UPDATE event_type SET background = '{$_SESSION['background']}', activity_type = '{$_SESSION['activity_type']}', series = '{$_SESSION['series']}' WHERE event_id = $event_id");
    $conn->query("UPDATE event_dates SET start_datetime = '{$_SESSION['start_datetime']}', end_datetime = '{$_SESSION['end_datetime']}' WHERE event_id = $event_id");
    $conn->query("UPDATE event_participants SET participants = '{$_SESSION['participants']}', participant_range = '{$_SESSION['participant_range']}', has_visitors = '{$_SESSION['has_visitors']}' WHERE event_id = $event_id");
    $conn->query("UPDATE event_location SET venue_platform = '{$_SESSION['venue_platform']}', distance = '{$_SESSION['distance']}' WHERE event_id = $event_id");
    $conn->query("UPDATE event_logistics SET extraneous = '{$_SESSION['extraneous']}', collect_payments = '{$_SESSION['collect_payments']}', overnight = '{$_SESSION['overnight']}' WHERE event_id = $event_id");
    $conn->query("UPDATE event_metrics SET target_metric = '{$_SESSION['target_metric']}' WHERE event_id = $event_id");

    // Update calendar
    $end = $_SESSION['end_datetime'] ?: null;
    $notes = "Event updated via Event Manager";
    $cal_stmt = $conn->prepare("UPDATE calendar_entries SET title=?, start_datetime=?, end_datetime=?, notes=? WHERE event_id=? AND user_id=?");
    $cal_stmt->bind_param("ssssii", $_SESSION['event_name'], $_SESSION['start_datetime'], $end, $notes, $event_id, $_SESSION['user_id']);
    $cal_stmt->execute();

  } else {
    // Insert into events table
    $stmt = $conn->prepare("INSERT INTO events (user_id, organizing_body, nature, event_name, event_status) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issss", $_SESSION["user_id"], $organizing_body_json, $_SESSION['nature'], $_SESSION['event_name'], $event_status);
    $stmt->execute();
    $event_id = $conn->insert_id;

    // Insert into child tables
    $conn->query("INSERT INTO event_type (event_id, background, activity_type, series) VALUES ($event_id, '{$_SESSION['background']}', '{$_SESSION['activity_type']}', '{$_SESSION['series']}')");
    $conn->query("INSERT INTO event_dates (event_id, start_datetime, end_datetime) VALUES ($event_id, '{$_SESSION['start_datetime']}', '{$_SESSION['end_datetime']}')");
    $conn->query("INSERT INTO event_participants (event_id, participants, participant_range, has_visitors) VALUES ($event_id, '{$_SESSION['participants']}', '{$_SESSION['participant_range']}', '{$_SESSION['has_visitors']}')");
    $conn->query("INSERT INTO event_location (event_id, venue_platform, distance) VALUES ($event_id, '{$_SESSION['venue_platform']}', '{$_SESSION['distance']}')");
    $conn->query("INSERT INTO event_logistics (event_id, extraneous, collect_payments, overnight) VALUES ($event_id, '{$_SESSION['extraneous']}', '{$_SESSION['collect_payments']}', '{$_SESSION['overnight']}')");
    $conn->query("INSERT INTO event_metrics (event_id, target_metric) VALUES ($event_id, '{$_SESSION['target_metric']}')");
    $conn->query("INSERT INTO event_documents (event_id, docs_total, docs_uploaded) VALUES ($event_id, 0, 0)");

    // Insert calendar entry
    $end = $_SESSION['end_datetime'] ?: null;
    $notes = "Event created via Event Manager";
    $cal_stmt = $conn->prepare("INSERT INTO calendar_entries (user_id, event_id, title, start_datetime, end_datetime, notes) VALUES (?, ?, ?, ?, ?, ?)");
    $cal_stmt->bind_param("iissss", $_SESSION["user_id"], $event_id, $_SESSION['event_name'], $_SESSION['start_datetime'], $end, $notes);
    $cal_stmt->execute();
  }

  return $event_id;
}

// Handle form submission
if (isset($_POST['create_event'])) {
  // Store form data in session for persistence
  foreach ($ce_fields as $field) {
    $_SESSION[$field] = $_POST[$field] ?? null;
  }

  // Insert/update event data using the helper
  $event_id = insertEventData($event_id, $editing);

  // Section: Sync requirements based on event details
  $background = $_SESSION['background'];
  $activity_type = $_SESSION['activity_type'];
  $collect_payments = $_SESSION['collect_payments'] ?? null;
  $extraneous = $_SESSION['extraneous'] ?? null;
  $overnight = $_SESSION['overnight'] ?? null;

  // Build checklist from map
  $checklist = $requirements_map[$background][$activity_type] ?? [];
  if ($collect_payments === 'Yes') {
    $checklist[] = 'Request Letter for Collection/Selling';
  }
  if ($extraneous === 'Yes') {
    $checklist[] = 'Medical Clearance of Participants';
  }
  if ($overnight == 1 && strpos($activity_type, 'Off-Campus') !== false) {
    $checklist[] = 'Risk Assessment Plan with Emergency Contacts and Emergency Map';
  }
  if (strpos($activity_type, 'On-campus') !== false) {
    if (!empty($_SESSION['has_visitors']) && $_SESSION['has_visitors'] === 'Yes') {
      $checklist[] = 'Visitors and Vehicle Lists';
    }
  }
  $checklist = array_unique($checklist);

  // Get current requirements for the event
  $current_reqs = [];
  $stmt = $conn->prepare("SELECT req_name FROM requirements WHERE event_id=?");
  $stmt->bind_param("i", $event_id);
  $stmt->execute();
  $res = $stmt->get_result();
  while ($row = $res->fetch_assoc()) {
    $current_reqs[] = $row['req_name'];
  }

  // Determine additions and removals
  $to_add = array_diff($checklist, $current_reqs);
  $to_remove = array_diff($current_reqs, $checklist);

  // Remove outdated requirements
  if (!empty($to_remove)) {
    $stmt = $conn->prepare("DELETE FROM requirements WHERE event_id=? AND req_name=?");
    foreach ($to_remove as $req) {
      $stmt->bind_param("is", $event_id, $req);
      $stmt->execute();
    }
  }

  // Add new requirements
  if (!empty($to_add)) {
    $stmt = $conn->prepare("INSERT INTO requirements (event_id, req_name, req_desc) VALUES (?, ?, ?)");
    foreach ($to_add as $req) {
      $desc = $requirements_descs[$req] ?? '';
      $stmt->bind_param("iss", $event_id, $req, $desc);
      $stmt->execute();
      $req_id = $conn->insert_id;
      // Insert file entry with template
      $file_stmt = $conn->prepare("INSERT INTO requirement_files (req_id, template_url) VALUES (?, ?)");
      $template = $requirements_templates[$req] ?? '';
      $file_stmt->bind_param("is", $req_id, $template);
      $file_stmt->execute();
      // Insert status
      $status_stmt = $conn->prepare("INSERT INTO requirement_status (req_id, doc_status) VALUES (?, ?)");
      $status_stmt->bind_param("is", $req_id, "pending");
      $status_stmt->execute();
    }
  }

  // Update document count in event_documents
  $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM requirements WHERE event_id=?");
  $stmt->bind_param("i", $event_id);
  $stmt->execute();
  $res = $stmt->get_result();
  $docs_total = $res->fetch_assoc()['total'] ?? 0;
  $stmt = $conn->prepare("UPDATE event_documents SET docs_total=? WHERE event_id=?");
  $stmt->bind_param("ii", $docs_total, $event_id);
  $stmt->execute();

  // Clear session data
  foreach ($ce_fields as $field) {
    unset($_SESSION[$field]);
  }

  // Redirect based on action
  if ($editing) {
    header("Location: view_event.php?id=$event_id");
  } else {
    header("Location: home.php");
  }
  exit();
}

// HTML output starts here (unchanged structure)
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= $editing ? 'Edit' : 'Create' ?> Event</title>
  <link rel="stylesheet" href="assets/styles/layout.css" />
  <link rel="stylesheet" href="assets/styles/ce_styles.css" />
</head>

<body>
  <div class="app">
    <!-- Overlay for sidebar on mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay" hidden></div>

    <?php include 'assets/includes/general_nav.php' ?>

    <main class="main">
      <!-- Page header -->
      <header class="topbar ce-topbar">
        <button class="hamburger" id="menuBtn" type="button" aria-label="Open menu">☰</button>

        <div class="title-wrap">
          <h1><?= $editing ? 'Edit Event' : 'Create Event' ?></h1>
          <p><?= $editing ? 'Update the event details below.' : 'Fill out the form below to create a new event.' ?></p>
        </div>
      </header>

      <!-- ===== EVENT FORM ===== -->
      <form method="POST" class="event-form">
        <!-- Step 1: Classification -->
        <details class="step-1 acc" open>
          <summary class="acc-head">
            <span class="acc-left">
              <span class="acc-dot"><i class="fa-solid fa-magnifying-glass-chart"></i></span>
              <span class="acc-text">
                <label for="organizing_body" class="acc-title">Classification</label>
                <span class="acc-sub">Background & Activity Type</span>
              </span>
            </span>
            <span class="acc-chevron"></span>
          </summary>
          <div class="acc-body">
            <!-- Organizing Body Selection -->
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

                // Decode organizing_body from JSON if available
                $organizing_body = [];
                if (!empty($formData['organizing_body'])) {
                  $organizing_body = json_decode($formData['organizing_body'], true);
                  if (!is_array($organizing_body)) {
                    $organizing_body = [];
                  }
                }

                // Render option for each organization
                foreach ($org_options as $org) {
                  $selected = (is_array($organizing_body) && in_array($org, $organizing_body)) ? 'selected' : '';
                  echo "<option value=\"$org\" $selected>" . htmlspecialchars($org) . "</option>";
                }
                ?>
              </select>

              <!-- Display selected organizations as tags -->
              <div class="selected-tags" id="selectedTags">
                <?php if (!empty($organizing_body) && is_array($organizing_body)): ?>
                  <?php foreach ($organizing_body as $org): ?>
                    <div class="tag">
                      <?= htmlspecialchars($org) ?><span>&times;</span>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>

              <!-- Custom multi-select dropdown -->
              <div class="multi-select" id="orgDropdown">
                <input type="text" placeholder="Search or select organization" class="multi-input" autocomplete="off">
                <div class="dropdown-list"></div>
              </div>
            </fieldset>

            <!-- Background Selection -->
            <fieldset class="field">
              <label for="background" class="field-title">Background</label>
              <small class="hint">Indicate who initiated this activity.</small>

              <div class="radio-group two-col">
                <label>
                  <input type="radio" name="background" value="OSA-Initiated Activity" required
                    <?= ($formData['background'] === 'OSA-Initiated Activity') ? 'checked' : '' ?>>
                  OSA-Initiated Activity
                </label>

                <label>
                  <input type="radio" name="background" value="Student-Initiated Activity" required
                    <?= ($formData['background'] === 'Student-Initiated Activity') ? 'checked' : '' ?>>
                  Student-Initiated Activity
                </label>

                <label>
                  <input type="radio" name="background" value="Participation" required
                    <?= ($formData['background'] === 'Participation') ? 'checked' : '' ?>>
                  Participation
                </label>
              </div>
            </fieldset>

            <!-- Activity Type Selection -->
            <fieldset class="field">
              <label for="activity_type" class="field-title">Type Activity</label>
              <small class="hint">Choose the activity type. This helps categorize events for reporting.</small>

              <div class="radio-group two-col">
                <?php
                $types = [
                  'On-campus Activity',
                  'Virtual Activity',
                  'Off-Campus Activity',
                  'Community Service - On-campus Activity',
                  'Community Service - Virtual Activity',
                  'Community Service - Off-campus Activity'
                ];

                // Render radio button for each activity type
                foreach ($types as $type) {
                  $checked = ($formData['activity_type'] === $type) ? 'checked' : '';
                  echo "<label><input type='radio' name='activity_type' value='" . htmlspecialchars($type) . "' $checked required> " . htmlspecialchars($type) . "</label>";
                }
                ?>
              </div>
            </fieldset>

            <!-- Series Selection (Participation activities only) -->
            <fieldset class="field" id="series-block"
              style="display:<?= ($formData['background'] === 'Participation') ? 'flex' : 'none' ?>;">
              <label for="series" class="field-title">Series</label>
              <small class="hint">Select the series if this is a participation activity.</small>

              <div class="radio-group two-col">
                <?php
                $series_options = [
                  'College Days',
                  'University Days',
                  'Organization Themed-Fairs',
                  'OSA-Initiated Activities',
                  'HAU Institutional Activities'
                ];

                // Render radio button for each series option
                foreach ($series_options as $opt) {
                  $checked = ($formData['series'] === $opt) ? 'checked' : '';
                  echo "<label><input type='radio' name='series' value='" . htmlspecialchars($opt) . "' $checked required> " . htmlspecialchars($opt) . "</label>";
                }
                ?>
              </div>
            </fieldset>
          </div>
        </details>

        <!-- Step 1 Action Buttons -->
        <div class="step-actions step1-actions">
          <button type="button" class="btn-primary next-btn" disabled>Next</button>
        </div>

        <!-- ===== STEP 2A: BASIC INFORMATION ===== -->
        <!-- Collect event description and nature -->
        <details class="step-2 acc">
          <summary class="acc-head">

            <span class="acc-left">
              <span class="acc-dot"><i class="fa-solid fa-circle-info"></i></span>

              <span class="acc-text">
                <label for="organizing_body" class="acc-title">Basic Information</label>
                <span class="acc-sub">Nature & Event Name</span>
              </span>
            </span>

            <span class="acc-chevron"></span>
          </summary>

          <div class="acc-body">

            <!-- Nature of Event -->
            <div class="field long-field">
              <label for="nature" class="field-title">Nature of the Event</label>
              <small class="hint">
                If you were asked to describe what is your activity in one to three words, what would it be?
                (ex. Singing Contest, Quiz Bee, Tutorial Session, Bulletin Board Campaign, Online Poster Campaign, Amazing Race, Forum, Seminar, Workshop, Focus Group Discussion)
              </small>

              <textarea name="nature" id="nature" required><?= htmlspecialchars($formData['nature']) ?></textarea>
            </div>

            <!-- Event Name -->
            <div class="field long-field">
              <label for="event_name" class="field-title">Name of the Event</label>
              <small class="hint">
                If this is one event in a series of events (e.g. College Days, UDays, festivals with mini-events),
                place the umbrella event first, then put a colon with the name and a hyphen for the nature description.
                (ex. SAS Days 2025: Kundiman - Concert for a cause)
              </small>

              <textarea name="event_name" id="event_name" required><?= htmlspecialchars($formData['event_name']) ?></textarea>
            </div>

            <!-- Target Metric -->
            <div class="field long-field">
              <label for="target_metric" class="field-title">Target Metric</label>
              <small class="hint">
                Indicate the target metric and the standard value you wish to achieve.
                (ex. 50% Turnout of Voters, 75% Satisfaction Rating)
              </small>

              <textarea name="target_metric" id="target_metric" rows="2"><?= htmlspecialchars($formData['target_metric']) ?></textarea>
            </div>

            <!-- Extraneous Activity Flag -->
            <fieldset class="field">
              <label for="extraneous" class="field-title">Is this an extraneous activity?</label>
              <small class="hint">Mark if this activity is classified as extraneous (requires medical clearance)</small>

              <div class="radio-group inline">
                <label>
                  <input type="radio" name="extraneous" value="Yes" required
                    <?= ($formData['extraneous'] === 'Yes') ? 'checked' : '' ?>>
                  Yes
                </label>

                <label>
                  <input type="radio" name="extraneous" value="No" required
                    <?= ($formData['extraneous'] === 'No') ? 'checked' : '' ?>>
                  No
                </label>
              </div>
            </fieldset>

            <!-- Payment Collection Flag -->
            <fieldset class="field">
              <label for="collect-payments" class="field-title">
                Would you collect payments or sell merchandise for this activity?
              </label>

              <div class="radio-group inline">
                <label>
                  <input type="radio" name="collect_payments" value="Yes" required
                    <?= ($formData['collect_payments'] === 'Yes') ? 'checked' : '' ?>>
                  Yes
                </label>

                <label>
                  <input type="radio" name="collect_payments" value="No" required
                    <?= ($formData['collect_payments'] === 'No') ? 'checked' : '' ?>>
                  No
                </label>
              </div>
            </fieldset>
          </div>
        </details>

        <!-- ===== STEP 2B: LOGISTICS ===== -->
        <!-- Collect schedule, venue, and participants information -->
        <details class="step-2 acc">
          <summary class="acc-head">

            <span class="acc-left">
              <span class="acc-dot"><i class="fa-solid fa-calendar-days"></i></span>

              <span class="acc-text">
                <label for="organizing_body" class="acc-title">Logistics</label>
                <span class="acc-sub">Schedule, Venue, & Participants</span>
              </span>
            </span>

            <span class="acc-chevron"></span>
          </summary>

          <div class="acc-body">

            <!-- Date & Time Fields -->
            <div class="form-row">

              <!-- Start Date/Time -->
              <div class="field">
                <label for="start_datetime" class="field-title">Start Date and Time</label>
                <small class="hint">Indicate the start of ingress (arrival time)</small>

                <input type="datetime-local" name="start_datetime" id="start_datetime"
                  value="<?= htmlspecialchars($formData['start_datetime']) ?>" required>
              </div>

              <!-- End Date/Time -->
              <div class="field">
                <label for="end_datetime" class="field-title">End Date and Time</label>
                <small class="hint">Indicate the start of egress (departure time)</small>

                <input type="datetime-local" name="end_datetime" id="end_datetime"
                  value="<?= htmlspecialchars($formData['end_datetime']) ?>" required>
              </div>
            </div>

            <!-- Participants Description -->
            <div class="field long-field" style="margin-top: 0;">
              <label for="participants" class="field-title">Participants</label>
              <small class="hint">
                Indicate number and group (ex. 8 members, 7 officers, 2 guest speakers, 40 beneficiaries from XYZ Foundation)
              </small>

              <textarea name="participants" id="participants" rows="2" required><?= htmlspecialchars($formData['participants']) ?></textarea>
            </div>

            <!-- Venue / Platform -->
            <div class="field long-field">
              <label for="venue_platform" class="field-title">Venue / Platform</label>
              <small class="hint">
                Indicate the room number for classrooms. Provide the invite link if for online sessions
                and invite either studentactivities@hau.edu.ph or studentactivities.hauosa@gmail.com
              </small>

              <textarea name="venue_platform" id="venue_platform" required><?= htmlspecialchars($formData['venue_platform']) ?></textarea>
            </div>

            <!-- Visitors on Campus (On-campus activities only) -->
            <fieldset class="field" id="visitors-block"
              style="display:<?= (strpos($formData['activity_type'] ?? '', 'On-campus') !== false) ? 'flex' : 'none' ?>;">

              <label class="field-title">Will there be visitors entering the campus?</label>
              <small class="hint">External guests or non-HAU members attending the event</small>

              <div class="radio-group inline">
                <label>
                  <input type="radio" name="has_visitors" value="Yes" required
                    <?= ($formData['has_visitors'] ?? '') === 'Yes' ? 'checked' : '' ?>>
                  Yes
                </label>

                <label>
                  <input type="radio" name="has_visitors" value="No" required
                    <?= ($formData['has_visitors'] ?? '') === 'No' ? 'checked' : '' ?>>
                  No
                </label>
              </div>

            </fieldset>

            <!-- Off-Campus Specific Fields -->
            <fieldset class="field" id="offcampus-block"
              style="display:<?= (strpos($formData['activity_type'], 'Off-Campus') !== false) ? 'block' : 'none' ?>;">

              <div class="form-row">

                <!-- Participant Range -->
                <fieldset class="field" style="margin-top: 0;">
                  <label for="participant_range" class="field-title">Range of Total Number of Participants</label>

                  <div class="radio-group">
                    <?php
                    $ranges = ['1-2', '3-15', '15-25', '25 or more'];

                    foreach ($ranges as $r) {
                      $checked = ($formData['participant_range'] === $r) ? 'checked' : '';
                      echo "<label><input type='radio' name='participant_range' value='" . htmlspecialchars($r) . "' $checked required> " . htmlspecialchars($r) . "</label>";
                    }
                    ?>
                  </div>
                </fieldset>

                <!-- Distance Traveled -->
                <fieldset class="field" style="margin-top: 0;">
                  <label for="distance" class="field-title">Distance</label>
                  <small class="hint">How far will participants travel?</small>

                  <div class="radio-group">
                    <?php
                    $distances = [
                      'Within Angeles City',
                      'Within Central Luzon',
                      'Rest of PH or Overseas'
                    ];

                    foreach ($distances as $option) {
                      $checked = (($formData['distance'] ?? '') === $option) ? 'checked' : '';
                      echo "<label>
                              <input type='radio' name='distance' value='" . htmlspecialchars($option) . "' $checked required>
                              " . htmlspecialchars($option) . "
                            </label>";
                    }
                    ?>
                  </div>
                </fieldset>
              </div>

              <!-- Overnight Stay -->
              <div class="field" style="margin-top: 0;">
                <label for="overnight" class="field-title">
                  Will the activity last more than 12 hours from arrival to departure?
                </label>
                <small class="hint">Select "Yes" if it includes an overnight stay</small>

                <div class="radio-group inline">
                  <label>
                    <input type="radio" name="overnight" value="1" required
                      <?= ($formData['overnight'] == 1) ? 'checked' : '' ?>>
                    Yes
                  </label>

                  <label>
                    <input type="radio" name="overnight" value="0" required
                      <?= ($formData['overnight'] == 0) ? 'checked' : '' ?>>
                    No
                  </label>
                </div>
              </div>
            </fieldset>
          </div>
        </details>

        <!-- Final Action Buttons -->
        <div class="step-actions step-2-actions">
          <button type="button" class="btn-secondary back-btn">Back</button>
          <button type="submit" name="create_event" class="btn-primary create-btn" disabled>
            <?= $editing ? 'Update Event' : 'Create Event' ?>
          </button>
        </div>
      </form>

      <?php include 'assets/includes/footer.php' ?>
    </main>
  </div>

  <script src="../app/script/layout.js?v=1"></script>
  <script src="../app/script/create_event.js"></script>
</body>

</html>