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
$event_scope = $_SESSION['event_scope'] ?? '';
$expected_participants = $_SESSION['expected_participants'] ?? '';
$event_name = $_SESSION['event_name'] ?? '';
$event_description = $_SESSION['event_description'] ?? '';
$contact_person = $_SESSION['contact_person'] ?? '';
$contact_email = $_SESSION['contact_email'] ?? '';
$nature = $_SESSION['nature'] ?? '';
$activity_name = $_SESSION['activity_name'] ?? '';
$start_date = $_SESSION['start_date'] ?? '';
$start_time = $_SESSION['start_time'] ?? '';
$end_date = $_SESSION['end_date'] ?? '';
$end_time = $_SESSION['end_time'] ?? '';
$collect_payments = $_SESSION['collect_payments'] ?? '';
$num_participants = $_SESSION['num_participants'] ?? '';
$venue_platform = $_SESSION['venue_platform'] ?? '';
$distance = $_SESSION['distance'] ?? '';
$participant_range = $_SESSION['participant_range'] ?? '';
$duration = $_SESSION['duration'] ?? '';
$target_metric = $_SESSION['target_metric'] ?? '';
$extraneous = $_SESSION['extraneous'] ?? '';

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $_SESSION['organizing_body'] = $_POST['organizing_body'] ?? null;
  $_SESSION['background'] = $_POST['background'] ?? null;
  $_SESSION['activity_type'] = $_POST['activity_type'] ?? null;
  $_SESSION['series'] = $_POST['series'] ?? null;
  $_SESSION['event_scope'] = $_POST['event_scope'] ?? null;
  $_SESSION['expected_participants'] = $_POST['expected_participants'] ?? null;
  $_SESSION['event_name'] = $_POST['event_name'] ?? '';
  $_SESSION['event_description'] = $_POST['event_description'] ?? '';
  $_SESSION['contact_person'] = $_POST['contact_person'] ?? '';
  $_SESSION['contact_email'] = $_POST['contact_email'] ?? '';
  $_SESSION['nature'] = $_POST['nature_activity'] ?? '';
  $_SESSION['activity_name'] = $_POST['activity_name'] ?? '';
  $_SESSION['start_date'] = $_POST['start_date'] ?? '';
  $_SESSION['start_time'] = $_POST['start_time'] ?? '';
  $_SESSION['end_date'] = $_POST['end_date'] ?? '';
  $_SESSION['end_time'] = $_POST['end_time'] ?? '';
  $_SESSION['collect_payments'] = $_POST['collect_payments'] ?? '';
  $_SESSION['num_participants'] = $_POST['num_participants'] ?? '';
  $_SESSION['venue_platform'] = $_POST['venue_platform'] ?? '';
  $_SESSION['distance'] = $_POST['distance'] ?? null;
  $_SESSION['participant_range'] = $_POST['participant_range'] ?? null;
  $_SESSION['duration'] = $_POST['duration'] ?? null;
  $_SESSION['target_metric'] = $_POST['target_metric'] ?? '';
  $_SESSION['extraneous'] = $_POST['extraneous'] ?? '';

  if (isset($_POST['create_event'])) {
    // Handle file uploads here
    
    $stmt = $conn->prepare("
      INSERT INTO events (
        user_id, organizing_body, background, activity_type, series, event_scope, expected_participants,
        event_name, event_description, contact_person, contact_email,
        nature, activity_name, start_date, start_time, end_date, end_time,
        collect_payments, num_participants, venue_platform, distance, 
        participant_range, duration, target_metric, is_extraneous
      ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $user_id = $_SESSION["user_id"];

    $stmt->bind_param(
      "issssissssssssssssssssss",
      $user_id,
      $_SESSION['organizing_body'],
      $_SESSION['background'],
      $_SESSION['activity_type'],
      $_SESSION['series'],
      $_SESSION['event_scope'],
      $_SESSION['expected_participants'],
      $_SESSION['event_name'],
      $_SESSION['event_description'],
      $_SESSION['contact_person'],
      $_SESSION['contact_email'],
      $_SESSION['nature'],
      $_SESSION['activity_name'],
      $_SESSION['start_date'],
      $_SESSION['start_time'],
      $_SESSION['end_date'],
      $_SESSION['end_time'],
      $_SESSION['collect_payments'],
      $_SESSION['num_participants'],
      $_SESSION['venue_platform'],
      $_SESSION['distance'],
      $_SESSION['participant_range'],
      $_SESSION['duration'],
      $_SESSION['target_metric'],
      $_SESSION['extraneous']
    );

    $stmt->execute();

    // Clear session variables
    unset(
      $_SESSION['organizing_body'],
      $_SESSION['background'],
      $_SESSION['activity_type'],
      $_SESSION['series'],
      $_SESSION['event_scope'],
      $_SESSION['expected_participants'],
      $_SESSION['event_name'],
      $_SESSION['event_description'],
      $_SESSION['contact_person'],
      $_SESSION['contact_email'],
      $_SESSION['nature'],
      $_SESSION['activity_name'],
      $_SESSION['start_date'],
      $_SESSION['start_time'],
      $_SESSION['end_date'],
      $_SESSION['end_time'],
      $_SESSION['collect_payments'],
      $_SESSION['num_participants'],
      $_SESSION['venue_platform'],
      $_SESSION['distance'],
      $_SESSION['participant_range'],
      $_SESSION['duration'],
      $_SESSION['target_metric'],
      $_SESSION['extraneous']
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
  <title>HAUCREDIT - Create Event</title>
  <link rel="stylesheet" href="../app/css/styles.css" />
  <style>
    .template-link {
      color: #c2a14d;
      text-decoration: none;
      font-size: 13px;
      margin-bottom: 8px;
      display: inline-block;
    }
    .template-link:hover {
      text-decoration: underline;
    }
  </style>
  <script>
    function toggleSeriesField() {
      const background = document.querySelector('input[name="background"]:checked');
      const seriesBlock = document.getElementById('series-block');
      if (background && background.value === 'Participation to an OSA-Initiated or a Student-Initiated Activity') {
        seriesBlock.style.display = 'block';
      } else {
        seriesBlock.style.display = 'none';
      }
    }

    function goToStep2() {
      const organization = document.getElementById('organization').value;
      const background = document.querySelector('input[name="background"]:checked');
      const activityType = document.getElementById('activity-type').value;
      const eventScope = document.getElementById('event-scope').value;
      const expectedParticipants = document.getElementById('expected-participants').value;

      if (!organization || !background || !activityType || !eventScope || !expectedParticipants) {
        alert('Please fill in all required fields before proceeding.');
        return;
      }

      // Check if series is required
      if (background.value === 'Participation to an OSA-Initiated or a Student-Initiated Activity') {
        const series = document.querySelector('input[name="series"]:checked');
        if (!series) {
          alert('Please select a series.');
          return;
        }
      }

      document.getElementById('step-1').style.display = 'none';
      document.getElementById('step-2').style.display = 'block';
      window.scrollTo(0, 0);
    }

    function goToStep1() {
      document.getElementById('step-2').style.display = 'none';
      document.getElementById('step-1').style.display = 'block';
      window.scrollTo(0, 0);
    }

    function updateAllFields() {
      const activityType = document.getElementById('activity-type').value;
      const offCampusFields = document.getElementById('off-campus-fields');
      const offCampusDocs = document.getElementById('off-campus-docs');
      const communityFields = document.getElementById('community-service-fields');
      
      if (offCampusFields) offCampusFields.style.display = 'none';
      if (offCampusDocs) offCampusDocs.style.display = 'none';
      if (communityFields) communityFields.style.display = 'none';
      
      if (activityType.includes('Off-Campus')) {
        if (offCampusFields) offCampusFields.style.display = 'block';
        if (offCampusDocs) offCampusDocs.style.display = 'block';
      }
      
      if (activityType.includes('Community Service')) {
        if (communityFields) communityFields.style.display = 'block';
      }
    }

    function updatePaymentFields() {
      const collectPayments = document.getElementById('collect-payments');
      const paymentField = document.getElementById('payment-field');
      
      if (collectPayments && paymentField) {
        if (collectPayments.value === 'yes') {
          paymentField.style.display = 'block';
        } else {
          paymentField.style.display = 'none';
        }
      }
    }

    document.addEventListener('DOMContentLoaded', function() {
      const activityTypeEl = document.getElementById('activity-type');
      const collectPaymentsEl = document.getElementById('collect-payments');
      
      if (activityTypeEl) {
        activityTypeEl.addEventListener('change', updateAllFields);
      }
      if (collectPaymentsEl) {
        collectPaymentsEl.addEventListener('change', updatePaymentFields);
      }
      
      // Initialize series field visibility
      toggleSeriesField();
    });
  </script>
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
          <p>Fill in the event details to generate your compliance checklist</p>
        </div>
      </header>

      <section class="content create-event">
        <form method="POST" enctype="multipart/form-data">
          
          <!-- STEP 1: Event Classification & Organizing Body -->
          <div id="step-1">
            <details class="acc" open>
              <summary class="acc-head">
                <span class="acc-left">
                  <span class="acc-dot" aria-hidden="true"></span>
                  <span class="acc-text">
                    <span class="acc-title">Event Classification</span>
                    <span class="acc-sub">Select activity type and organizing body</span>
                  </span>
                </span>
                <span class="acc-chevron" aria-hidden="true"></span>
              </summary>

              <div class="acc-body">
                <div class="form-grid">
                  <div class="field">
                    <label for="organization">Organizing Body *</label>
                    <input list="org_list" id="organization" name="organizing_body" value="<?= htmlspecialchars($organizing_body) ?>" required>
                    <datalist id="org_list">
                      <option value="HAUSG CSC-SOC">
                      <option value="HAUSG CSC-SAS">
                      <option value="HAUSG CSC-SHTM">
                      <option value="HAUSG CSC-SEA">
                      <option value="HAUSG CSC-SNAMS">
                      <option value="HAUSG CSC-CCJEF">
                      <option value="HAUSG CSC-SED">
                      <option value="HAUSG CSC-SBA">
                      <option value="Department Organization">
                      <option value="College Organization">
                      <option value="Special Interest Group">
                    </datalist>
                  </div>

                  <div class="field">
                    <label>Background *</label>
                    <label style="display: block; margin: 8px 0;">
                      <input type="radio" name="background" value="OSA-Initiated Activity" <?= ($background === 'OSA-Initiated Activity') ? 'checked' : '' ?> required onchange="toggleSeriesField()">
                      OSA-Initiated Activity
                    </label>
                    <label style="display: block; margin: 8px 0;">
                      <input type="radio" name="background" value="Student-Initiated Activity" <?= ($background === 'Student-Initiated Activity') ? 'checked' : '' ?> required onchange="toggleSeriesField()">
                      Student-Initiated Activity
                    </label>
                    <label style="display: block; margin: 8px 0;">
                      <input type="radio" name="background" value="Participation to an OSA-Initiated or a Student-Initiated Activity" <?= ($background === 'Participation to an OSA-Initiated or a Student-Initiated Activity') ? 'checked' : '' ?> required onchange="toggleSeriesField()">
                      Participation to an OSA-Initiated or a Student-Initiated Activity
                    </label>
                  </div>

                  <div class="field">
                    <label for="activity-type">Type of Activity *</label>
                    <select id="activity-type" name="activity_type" required onchange="updateAllFields()">
                      <option value="">Select activity type</option>
                      <option value="On-campus Activity" <?= ($activity_type === 'On-campus Activity') ? 'selected' : '' ?>>On-campus Activity</option>
                      <option value="Virtual Activity" <?= ($activity_type === 'Virtual Activity') ? 'selected' : '' ?>>Virtual Activity</option>
                      <option value="Off-Campus Activity" <?= ($activity_type === 'Off-Campus Activity') ? 'selected' : '' ?>>Off-Campus Activity</option>
                      <option value="Community Service - On-campus Activity" <?= ($activity_type === 'Community Service - On-campus Activity') ? 'selected' : '' ?>>Community Service - On-campus Activity</option>
                      <option value="Community Service - Virtual Activity" <?= ($activity_type === 'Community Service - Virtual Activity') ? 'selected' : '' ?>>Community Service - Virtual Activity</option>
                      <option value="Off-Campus Community Service" <?= ($activity_type === 'Off-Campus Community Service') ? 'selected' : '' ?>>Off-Campus Community Service</option>
                    </select>
                    <span class="field-hint">This determines the compliance requirements for your event</span>
                  </div>

                  <div class="field" id="series-block" style="display: <?= ($background === 'Participation to an OSA-Initiated or a Student-Initiated Activity') ? 'block' : 'none' ?>;">
                    <label>Series *</label>
                    <?php
                    $series_options = ["College Days", "University Days", "Organization Themed-Fairs", "OSA-Initiated Activities", "HAU Institutional Activities"];
                    foreach ($series_options as $opt) {
                      $checked = ($series === $opt) ? 'checked' : '';
                      echo "<label style='display: block; margin: 8px 0;'><input type='radio' name='series' value='$opt' $checked> $opt</label>";
                    }
                    ?>
                  </div>

                  <div class="field">
                    <label for="event-scope">Event Scope *</label>
                    <select id="event-scope" name="event_scope" required>
                      <option value="">Select event scope</option>
                      <option value="internal" <?= ($event_scope === 'internal') ? 'selected' : '' ?>>Internal (Department/College only)</option>
                      <option value="university" <?= ($event_scope === 'university') ? 'selected' : '' ?>>University-wide (All HAU students)</option>
                      <option value="external" <?= ($event_scope === 'external') ? 'selected' : '' ?>>External (Open to public/other schools)</option>
                    </select>
                  </div>

                  <div class="field">
                    <label for="expected-participants">Expected Number of Participants *</label>
                    <input type="number" id="expected-participants" name="expected_participants" required min="1" placeholder="e.g., 150" value="<?= htmlspecialchars($expected_participants) ?>">
                  </div>
                </div>
              </div>
            </details>

            <div class="submit-section">
              <div class="submit-info">Complete this section to proceed</div>
              <div class="submit-actions">
                <button type="button" class="btn-primary" onclick="goToStep2()">Next</button>
              </div>
            </div>
          </div>

          <!-- STEP 2: All Other Details -->
          <div id="step-2" style="display: none;">
            <div style="margin-bottom: 20px;">
              <button type="button" class="btn-secondary" onclick="goToStep1()" style="display: inline-flex; align-items: center; gap: 8px;">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Back
              </button>
            </div>

            <!-- Accordion: Basic Information -->
            <details class="acc" open>
              <summary class="acc-head">
                <span class="acc-left">
                  <span class="acc-dot" aria-hidden="true"></span>
                  <span class="acc-text">
                    <span class="acc-title">Basic Information</span>
                    <span class="acc-sub">Event name, description, and basic details</span>
                  </span>
                </span>
                <span class="acc-chevron" aria-hidden="true"></span>
              </summary>

              <div class="acc-body">
                <div class="form-grid">
                  <div class="field">
                    <label for="event-name">Event Name *</label>
                    <input type="text" id="event-name" name="event_name" required placeholder="e.g., Leadership Summit 2026" value="<?= htmlspecialchars($event_name) ?>">
                  </div>

                  <div class="field">
                    <label for="event-description">Event Description *</label>
                    <textarea id="event-description" name="event_description" required placeholder="Briefly describe the purpose and objectives of your event"><?= htmlspecialchars($event_description) ?></textarea>
                  </div>

                  <div class="field-row">
                    <div class="field">
                      <label for="contact-person">Contact Person *</label>
                      <input type="text" id="contact-person" name="contact_person" required placeholder="Full name" value="<?= htmlspecialchars($contact_person) ?>">
                    </div>

                    <div class="field">
                      <label for="contact-email">Contact Email *</label>
                      <input type="email" id="contact-email" name="contact_email" required placeholder="email@hau.edu.ph" value="<?= htmlspecialchars($contact_email) ?>">
                    </div>
                  </div>
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
                    <span class="acc-sub">Date, time, venue, and logistical details</span>
                  </span>
                </span>
                <span class="acc-chevron" aria-hidden="true"></span>
              </summary>

              <div class="acc-body">
                <div class="form-grid">
                  <div class="field">
                    <label for="nature-activity">Nature of the Activity *</label>
                    <input type="text" id="nature-activity" name="nature_activity" required placeholder="e.g., Seminar, Workshop, Quiz Bee" value="<?= htmlspecialchars($nature) ?>">
                    <span class="field-hint">Describe your activity in one to three words</span>
                  </div>

                  <div class="field">
                    <label for="activity-name">Name of the Activity *</label>
                    <input type="text" id="activity-name" name="activity_name" required placeholder="e.g., SAS Days 2025: Kundiman - Concert for a cause" value="<?= htmlspecialchars($activity_name) ?>">
                    <span class="field-hint">If part of a series, use format: Umbrella Event: Specific Event - Nature</span>
                  </div>

                  <div class="field-row">
                    <div class="field">
                      <label for="start-date">Start Date *</label>
                      <input type="date" id="start-date" name="start_date" required value="<?= htmlspecialchars($start_date) ?>">
                      <span class="field-hint">Indicate the start of ingress</span>
                    </div>

                    <div class="field">
                      <label for="start-time">Start Time *</label>
                      <input type="time" id="start-time" name="start_time" required value="<?= htmlspecialchars($start_time) ?>">
                    </div>
                  </div>

                  <div class="field-row">
                    <div class="field">
                      <label for="end-date">End Date *</label>
                      <input type="date" id="end-date" name="end_date" required value="<?= htmlspecialchars($end_date) ?>">
                      <span class="field-hint">Indicate the end of egress</span>
                    </div>

                    <div class="field">
                      <label for="end-time">End Time *</label>
                      <input type="time" id="end-time" name="end_time" required value="<?= htmlspecialchars($end_time) ?>">
                    </div>
                  </div>

                  <div class="field">
                    <label for="collect-payments">Would you collect payments or sell merchandise for this activity? *</label>
                    <select id="collect-payments" name="collect_payments" required>
                      <option value="">Select option</option>
                      <option value="yes" <?= ($collect_payments === 'yes') ? 'selected' : '' ?>>Yes</option>
                      <option value="no" <?= ($collect_payments === 'no') ? 'selected' : '' ?>>No</option>
                    </select>
                  </div>

                  <div class="field">
                    <label for="num-participants">Number of Participants *</label>
                    <textarea id="num-participants" name="num_participants" required placeholder="e.g., 8 members, 7 officers, 2 guest speakers, 40 beneficiaries from XYZ Foundation" rows="3"><?= htmlspecialchars($num_participants) ?></textarea>
                    <span class="field-hint">Indicate number and group</span>
                  </div>

                  <div class="field">
                    <label for="venue-platform">Venue/Platform *</label>
                    <textarea id="venue-platform" name="venue_platform" required placeholder="Indicate room number for caserooms, or invite link for online sessions" rows="2"><?= htmlspecialchars($venue_platform) ?></textarea>
                    <span class="field-hint">For online sessions, invite studentactivities@hau.edu.ph or studentactivities.hauosa@gmail.com</span>
                  </div>

                  <!-- Off-Campus Specific Fields -->
                  <div id="off-campus-fields" style="display: <?= (strpos($activity_type, 'Off-Campus') !== false) ? 'block' : 'none' ?>;">
                    <div class="field">
                      <label>Distance *</label>
                      <label style="display: block; margin: 8px 0;">
                        <input type="radio" name="distance" value="Within Angeles City" <?= ($distance === 'Within Angeles City') ? 'checked' : '' ?> required>
                        Within Angeles City
                      </label>
                      <label style="display: block; margin: 8px 0;">
                        <input type="radio" name="distance" value="Within Central Luzon" <?= ($distance === 'Within Central Luzon') ? 'checked' : '' ?> required>
                        Within Central Luzon
                      </label>
                      <label style="display: block; margin: 8px 0;">
                        <input type="radio" name="distance" value="Rest of PH or Overseas" <?= ($distance === 'Rest of PH or Overseas') ? 'checked' : '' ?> required>
                        Rest of PH or Overseas
                      </label>
                    </div>

                    <div class="field">
                      <label>Participant Range *</label>
                      <?php
                      $ranges = ["1-2", "3-15", "15-25", "25+"];
                      foreach ($ranges as $r) {
                        $checked = ($participant_range === $r) ? 'checked' : '';
                        echo "<label style='display: block; margin: 8px 0;'><input type='radio' name='participant_range' value='$r' $checked required> $r</label>";
                      }
                      ?>
                    </div>

                    <div class="field">
                      <label>More than 12 hours? *</label>
                      <label style="display: block; margin: 8px 0;">
                        <input type="radio" name="duration" value="1" <?= ($duration == 1) ? 'checked' : '' ?> required>
                        Yes
                      </label>
                      <label style="display: block; margin: 8px 0;">
                        <input type="radio" name="duration" value="0" <?= ($duration == 0) ? 'checked' : '' ?> required>
                        No
                      </label>
                    </div>
                  </div>

                  <div class="field">
                    <label for="target-metric">Target Metric *</label>
                    <input type="text" id="target-metric" name="target_metric" required placeholder="e.g., 50% Turnout of Voters, 75% Satisfaction Rating" value="<?= htmlspecialchars($target_metric) ?>">
                    <span class="field-hint">Indicate the target metric and standard value you wish to achieve</span>
                  </div>

                  <div class="field">
                    <label for="extraneous">Is this an extraneous activity? *</label>
                    <select id="extraneous" name="extraneous" required>
                      <option value="">Select option</option>
                      <option value="yes" <?= ($extraneous === 'yes') ? 'selected' : '' ?>>It is an extraneous activity</option>
                      <option value="no" <?= ($extraneous === 'no') ? 'selected' : '' ?>>It is NOT an extraneous activity</option>
                    </select>
                  </div>
                </div>
              </div>
            </details>

            <!-- Accordion: Required Documents -->
            <details class="acc">
              <summary class="acc-head">
                <span class="acc-left">
                  <span class="acc-dot" aria-hidden="true"></span>
                  <span class="acc-text">
                    <span class="acc-title">Required Documents</span>
                    <span class="acc-sub">Upload necessary attachments and forms</span>
                  </span>
                </span>
                <span class="acc-chevron" aria-hidden="true"></span>
              </summary>

              <div class="acc-body">
                <div class="form-grid">
                  <div class="field">
                    <p style="margin-bottom: 12px; font-size: 13px; color: #64748b;">
                      Check the format of attachments at 
                      <a href="https://tinyurl.com/allLinksHAUOSAStuAct" target="_blank" style="color: #c2a14d; text-decoration: underline;">https://tinyurl.com/allLinksHAUOSAStuAct</a>
                    </p>
                  </div>

                  <div class="field">
                    <label for="approval-letter">Approval Letter from Dean *</label>
                    <a href="https://tinyurl.com/HAUStuActApprovalLetter" target="_blank" class="template-link">
                      📄 Download Template: FM-SSA-SAO-8004.1
                    </a>
                    <input type="file" id="approval-letter" name="approval_letter" accept=".pdf,.doc,.docx" required>
                    <span class="field-hint">Submit with adviser's noting signature and Dean's approval. For Uniwide: address to Ms. Iris Ann Castro through Mr. Paul Ernest D. Carreon</span>
                  </div>

                  <div class="field">
                    <label for="program-flow">Program Flow and/or Itinerary *</label>
                    <a href="https://tinyurl.com/HAUStudentActivityForm" target="_blank" class="template-link">
                      📄 Download Template: FM-SSA-SAO-8004
                    </a>
                    <input type="file" id="program-flow" name="program_flow" accept=".pdf,.doc,.docx" required>
                    <span class="field-hint">If spontaneous, discuss guidelines. For off-campus: include travel itinerary with stopovers</span>
                  </div>

                  <!-- Community Service Fields -->
                  <div id="community-service-fields" style="display: <?= (strpos($activity_type, 'community') !== false) ? 'block' : 'none' ?>;">
                    <div class="field">
                      <label for="oces-annex">OCES Annex A Form *</label>
                      <a href="#" target="_blank" class="template-link">
                        📄 Download Template: OCES Annex A
                      </a>
                      <input type="file" id="oces-annex" name="oces_annex" accept=".pdf,.doc,.docx">
                    </div>
                  </div>

                  <!-- Payment Collection Field -->
                  <div id="payment-field" style="display: <?= ($collect_payments === 'yes') ? 'block' : 'none' ?>;">
                    <div class="field">
                      <label for="payment-request">Request Letter to Collect Payments or Sell Merchandise *</label>
                      <a href="https://tinyurl.com/HAUStuActPermitCollectOrSell" target="_blank" class="template-link">
                        📄 Download Template: FM-SSA-SAO-8004.5
                      </a>
                      <input type="file" id="payment-request" name="payment_request" accept=".pdf,.doc,.docx">
                      <span class="field-hint">Letter approved by Dean. For Uniwide: address to Ms. Iris Ann Castro through Mr. Paul Ernest D. Carreon</span>
                    </div>
                  </div>

                  <!-- Off-Campus Required Documents -->
                  <div id="off-campus-docs" style="display: <?= (strpos($activity_type, 'off-campus') !== false) ? 'block' : 'none' ?>;">
                    <div class="field">
                      <label for="parental-consent">Parental Consents *</label>
                      <a href="https://tinyurl.com/HAUParentalConsentFormat" target="_blank" class="template-link">
                        📄 Download Template: FM-SSA-SAO-8004.8
                      </a>
                      <input type="file" id="parental-consent" name="parental_consent" accept=".pdf">
                      <span class="field-hint">Upload as one PDF file. Shall be individually notarized</span>
                    </div>

                    <div class="field">
                      <label for="undertaking-letter">Letter of Undertaking *</label>
                      <a href="https://tinyurl.com/formatUndertakingLetter" target="_blank" class="template-link">
                        📄 Download Template: FM-SSA-SAO-8004.10
                      </a>
                      <input type="file" id="undertaking-letter" name="undertaking_letter" accept=".pdf,.doc,.docx">
                      <span class="field-hint">Signed by adviser. Person-in-Charge must be a university employee</span>
                    </div>

                    <div class="field">
                      <label for="planned-budget">Planned Budget *</label>
                      <a href="#" target="_blank" class="template-link">
                        📄 Download Template: Budget Template
                      </a>
                      <input type="file" id="planned-budget" name="planned_budget" accept=".pdf,.xlsx,.xls,.doc,.docx">
                      <span class="field-hint">Discuss source of budget and projected spending for all resources</span>
                    </div>

                    <div class="field">
                      <label for="participant-list">List of Participants *</label>
                      <a href="https://tinyurl.com/HAUStuActVisitorsList" target="_blank" class="template-link">
                        📄 Download Template: FM-SSA-SAO-8004.6
                      </a>
                      <input type="file" id="participant-list" name="participant_list" accept=".pdf,.xlsx,.xls,.doc,.docx">
                      <span class="field-hint">List and sort all students, employees, and guests with their roles</span>
                    </div>

                    <div class="field">
                      <label for="ched-certificate">CHEd Certificate of Compliance *</label>
                      <a href="https://tinyurl.com/CHEdComplianceCertFormat" target="_blank" class="template-link">
                        📄 Download Template: FM-SSA-SAO-8004.9
                      </a>
                      <input type="file" id="ched-certificate" name="ched_certificate" accept=".pdf">
                    </div>
                  </div>
                </div>
              </div>
            </details>

            <!-- Submit Section -->
            <div class="submit-section">
              <div class="submit-info">All fields marked with * are required</div>
              <div class="submit-actions">
                <button type="button" class="btn-secondary">Save as Draft</button>
                <button type="submit" name="create_event" class="btn-primary">Generate Checklist</button>
              </div>
            </div>
          </div>

        </form>
      </section>
    </main>
  </div>
</body>
</html>