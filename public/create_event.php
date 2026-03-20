<?php
session_start();
require_once "../app/database.php";
require_once "../app/security_headers.php";
require_once "../app/query_builder_functions.php";
send_security_headers();

if (!isset($_SESSION["user_id"])) {
  header("Location: index.php");
  exit();
}

$ce_fields = [
  'organizing_body',
  'background_id',
  'activity_type_id',
  'series_option_id',
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

$distance_options = [
  'Within Angeles City',
  'Within Central Luzon',
  'Rest of PH or Overseas'
];

$participant_range_options = ['1-2', '3-15', '15-25', '25 or more'];

$event_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$editing = $event_id > 0;

$formData = [];

function canEditEventStatus(string $status): bool
{
  return in_array($status, ['Draft', 'Pending Review', 'Needs Revision'], true);
}

/* ================= LOAD CONFIG OPTIONS FROM DB ================= */
function fetchConfigOptions(): array
{
  global $conn;

  $org_rows = fetchAll(
    $conn,
    "
        SELECT org_name
        FROM config_org_options
        WHERE is_active = 1
        ORDER BY sort_order ASC, org_name ASC
        "
  );

  $background_rows = fetchAll(
    $conn,
    "
        SELECT background_id, background_name
        FROM config_background_options
        WHERE is_active = 1
        ORDER BY sort_order ASC, background_name ASC
        "
  );

  $activity_rows = fetchAll(
    $conn,
    "
        SELECT activity_type_id, activity_type_name
        FROM config_activity_types
        WHERE is_active = 1
        ORDER BY sort_order ASC, activity_type_name ASC
        "
  );

  $series_rows = fetchAll(
    $conn,
    "
        SELECT series_option_id, series_name
        FROM config_series_options
        WHERE is_active = 1
        ORDER BY sort_order ASC, series_name ASC
        "
  );

  return [$org_rows, $background_rows, $activity_rows, $series_rows];
}

/* ================= FETCH FORM DATA ================= */
function fetchFormData(int $event_id, array $ce_fields, bool $editing): array
{
  global $conn;

  $formData = [];

  if ($editing) {
    $sql = "
            SELECT
                e.organizing_body,
                e.event_status,
                et.background_id,
                et.activity_type_id,
                et.series_option_id,
                cbo.background_name,
                cat.activity_type_name,
                cso.series_name,
                e.nature,
                e.event_name,
                ed.start_datetime,
                ed.end_datetime,
                ep.participants,
                el.venue_platform,
                elg.extraneous,
                elg.collect_payments,
                em.target_metric,
                el.distance,
                ep.participant_range,
                elg.overnight,
                ep.has_visitors
            FROM events e
            LEFT JOIN event_type et
                ON e.event_id = et.event_id
            LEFT JOIN config_background_options cbo
                ON et.background_id = cbo.background_id
            LEFT JOIN config_activity_types cat
                ON et.activity_type_id = cat.activity_type_id
            LEFT JOIN config_series_options cso
                ON et.series_option_id = cso.series_option_id
            LEFT JOIN event_dates ed
                ON e.event_id = ed.event_id
            LEFT JOIN event_participants ep
                ON e.event_id = ep.event_id
            LEFT JOIN event_location el
                ON e.event_id = el.event_id
            LEFT JOIN event_logistics elg
                ON e.event_id = elg.event_id
            LEFT JOIN event_metrics em
                ON e.event_id = em.event_id
            WHERE e.event_id = ?
              AND e.user_id = ?
              AND e.archived_at IS NULL
            LIMIT 1
        ";

    $existing_event = fetchOne(
      $conn,
      $sql,
      "ii",
      [$event_id, $_SESSION["user_id"]]
    );

    if (!$existing_event) {
      popup_error("Event not found or you do not have permission to edit it.");
    }

    if (!canEditEventStatus($existing_event['event_status'] ?? '')) {
      popup_error("This event can no longer be edited.");
    }

    foreach ($ce_fields as $field) {
      $formData[$field] = $existing_event[$field] ?? '';
    }

    $formData['background_name'] = $existing_event['background_name'] ?? '';
    $formData['activity_type_name'] = $existing_event['activity_type_name'] ?? '';
    $formData['series_name'] = $existing_event['series_name'] ?? '';
    $formData['event_status'] = $existing_event['event_status'] ?? 'Draft';
  } else {
    foreach ($ce_fields as $field) {
      $formData[$field] = $_SESSION[$field] ?? '';
    }

    $formData['background_name'] = '';
    $formData['activity_type_name'] = '';
    $formData['series_name'] = '';
    $formData['event_status'] = 'Draft';
  }

  return $formData;
}

/* ================= FETCH TEMPLATE DATA ================= */
function fetchTemplateData(): array
{
  global $conn;

  $rows = fetchAll(
    $conn,
    "
        SELECT req_name, req_desc, template_url
        FROM requirement_templates
        WHERE is_active = 1
        "
  );

  $templates = [];
  $descs = [];

  foreach ($rows as $row) {
    $name = $row['req_name'];
    $templates[$name] = $row['template_url'] ?? '';
    $descs[$name] = $row['req_desc'] ?? '';
  }

  return [$templates, $descs];
}

/* ================= RESOLVE CONFIG LABELS ================= */
function resolveConfigLabels(array &$data): void
{
  global $conn;

  $data['background_name'] = '';
  $data['activity_type_name'] = '';
  $data['series_name'] = '';

  if (!empty($data['background_id'])) {
    $row = fetchOne(
      $conn,
      "
            SELECT background_name
            FROM config_background_options
            WHERE background_id = ? AND is_active = 1
            LIMIT 1
            ",
      "i",
      [(int) $data['background_id']]
    );

    if ($row) {
      $data['background_name'] = $row['background_name'];
    }
  }

  if (!empty($data['activity_type_id'])) {
    $row = fetchOne(
      $conn,
      "
            SELECT activity_type_name
            FROM config_activity_types
            WHERE activity_type_id = ? AND is_active = 1
            LIMIT 1
            ",
      "i",
      [(int) $data['activity_type_id']]
    );

    if ($row) {
      $data['activity_type_name'] = $row['activity_type_name'];
    }
  }

  if (!empty($data['series_option_id'])) {
    $row = fetchOne(
      $conn,
      "
            SELECT series_name
            FROM config_series_options
            WHERE series_option_id = ? AND is_active = 1
            LIMIT 1
            ",
      "i",
      [(int) $data['series_option_id']]
    );

    if ($row) {
      $data['series_name'] = $row['series_name'];
    }
  }
}

/* ================= VALIDATION ================= */
function validateEventSubmission(array $data): void
{
  $requiredFields = [
    'background_id',
    'activity_type_id',
    'nature',
    'event_name',
    'start_datetime',
    'end_datetime',
    'participants',
    'venue_platform',
    'extraneous',
    'collect_payments'
  ];

  foreach ($requiredFields as $field) {
    if (!isset($data[$field]) || trim((string) $data[$field]) === '') {
      popup_error("Please complete all required event fields before submitting.");
    }
  }

  if (empty($data['organizing_body']) || !is_array($data['organizing_body'])) {
    popup_error("Please select at least one organizing body.");
  }

  $target_metric = trim($data['target_metric'] ?? '');
  if ($target_metric !== '' && !preg_match('/^(100|[1-9]?\d)\%\s+.+$/', $target_metric)) {
    popup_error("Target Metric must follow this format: 75% Satisfaction Rating");
  }

  $start_datetime = trim($data['start_datetime'] ?? '');
  $end_datetime = trim($data['end_datetime'] ?? '');

  if ($start_datetime !== '' && $end_datetime !== '') {
    $start_ts = strtotime($start_datetime);
    $end_ts = strtotime($end_datetime);

    if ($start_ts === false || $end_ts === false) {
      popup_error("Invalid start or end date/time.");
    }

    if (($end_ts - $start_ts) < 7200) {
      popup_error("End Date and Time must be at least 2 hours after the Start Date and Time.");
    }
  }

  $background_name = $data['background_name'] ?? '';
  if ($background_name === 'Participation' && empty($data['series_option_id'])) {
    popup_error("Please select a series for participation activities.");
  }
}

/* ================= SAVE EVENT CORE + CHILD TABLES ================= */
function saveEventData(int $event_id, bool $editing, string $event_status): int
{
  global $conn;

  $organizing_body_json = isset($_SESSION['organizing_body'])
    ? json_encode($_SESSION['organizing_body'], JSON_UNESCAPED_UNICODE)
    : '[]';

  $nature = trim($_SESSION['nature'] ?? '');
  $event_name = trim($_SESSION['event_name'] ?? '');

  $background_id = !empty($_SESSION['background_id']) ? (int) $_SESSION['background_id'] : 0;
  $activity_type_id = !empty($_SESSION['activity_type_id']) ? (int) $_SESSION['activity_type_id'] : 0;
  $series_option_id = ($_SESSION['series_option_id'] ?? '') !== '' ? (int) $_SESSION['series_option_id'] : null;

  $start_datetime = $_SESSION['start_datetime'] ?? '';
  $end_datetime = $_SESSION['end_datetime'] ?? '';

  $participants = trim($_SESSION['participants'] ?? '');
  $participant_range = $_SESSION['participant_range'] ?? null;
  $has_visitors = $_SESSION['has_visitors'] ?? null;

  $venue_platform = trim($_SESSION['venue_platform'] ?? '');
  $distance = $_SESSION['distance'] ?? null;

  $extraneous = $_SESSION['extraneous'] ?? 'No';
  $collect_payments = $_SESSION['collect_payments'] ?? 'No';
  $target_metric = trim($_SESSION['target_metric'] ?? '');
  $overnight = (!isset($_SESSION['overnight']) || $_SESSION['overnight'] === '')
    ? null
    : (int) $_SESSION['overnight'];

  if ($editing) {
    execQuery(
      $conn,
      "
            UPDATE events
            SET
                organizing_body = ?,
                nature = ?,
                event_name = ?,
                event_status = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE event_id = ? AND user_id = ?
            ",
      "ssssii",
      [$organizing_body_json, $nature, $event_name, $event_status, $event_id, $_SESSION["user_id"]]
    );

    execQuery(
      $conn,
      "
            UPDATE event_type
            SET background_id = ?, activity_type_id = ?, series_option_id = ?
            WHERE event_id = ?
            ",
      "iiii",
      [$background_id, $activity_type_id, $series_option_id, $event_id]
    );

    execQuery(
      $conn,
      "
            UPDATE event_dates
            SET start_datetime = ?, end_datetime = ?
            WHERE event_id = ?
            ",
      "ssi",
      [$start_datetime, $end_datetime, $event_id]
    );

    execQuery(
      $conn,
      "
            UPDATE event_participants
            SET participants = ?, participant_range = ?, has_visitors = ?
            WHERE event_id = ?
            ",
      "sssi",
      [$participants, $participant_range, $has_visitors, $event_id]
    );

    execQuery(
      $conn,
      "
            UPDATE event_location
            SET venue_platform = ?, distance = ?
            WHERE event_id = ?
            ",
      "ssi",
      [$venue_platform, $distance, $event_id]
    );

    execQuery(
      $conn,
      "
            UPDATE event_logistics
            SET extraneous = ?, collect_payments = ?, overnight = ?
            WHERE event_id = ?
            ",
      "ssii",
      [$extraneous, $collect_payments, $overnight, $event_id]
    );

    $metricExists = fetchOne(
      $conn,
      "
            SELECT event_id
            FROM event_metrics
            WHERE event_id = ?
            LIMIT 1
            ",
      "i",
      [$event_id]
    );

    if ($metricExists) {
      execQuery(
        $conn,
        "
                UPDATE event_metrics
                SET target_metric = ?
                WHERE event_id = ?
                ",
        "si",
        [$target_metric, $event_id]
      );
    } else {
      execQuery(
        $conn,
        "
                INSERT INTO event_metrics (event_id, target_metric, actual_metric)
                VALUES (?, ?, NULL)
                ",
        "is",
        [$event_id, $target_metric]
      );
    }

    $notes = ($event_status === 'Draft')
      ? "Draft event updated via Event Manager"
      : "Event submitted/updated via Event Manager";

    $calendarExists = fetchOne(
      $conn,
      "
            SELECT entry_id
            FROM calendar_entries
            WHERE event_id = ? AND user_id = ?
            LIMIT 1
            ",
      "ii",
      [$event_id, $_SESSION['user_id']]
    );

    if ($calendarExists) {
      execQuery(
        $conn,
        "
                UPDATE calendar_entries
                SET title = ?, start_datetime = ?, end_datetime = ?, notes = ?
                WHERE event_id = ? AND user_id = ?
                ",
        "ssssii",
        [$event_name, $start_datetime, $end_datetime, $notes, $event_id, $_SESSION['user_id']]
      );
    } else {
      execQuery(
        $conn,
        "
                INSERT INTO calendar_entries (user_id, event_id, title, start_datetime, end_datetime, notes)
                VALUES (?, ?, ?, ?, ?, ?)
                ",
        "iissss",
        [$_SESSION["user_id"], $event_id, $event_name, $start_datetime, $end_datetime, $notes]
      );
    }
  } else {
    $docs_total = 0;
    $docs_uploaded = 0;
    $is_system_event = 0;

    $insertEventStmt = execQuery(
      $conn,
      "
            INSERT INTO events (
                user_id, organizing_body, nature, event_name,
                event_status, docs_total, docs_uploaded, is_system_event
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ",
      "issssiii",
      [
        $_SESSION["user_id"],
        $organizing_body_json,
        $nature,
        $event_name,
        $event_status,
        $docs_total,
        $docs_uploaded,
        $is_system_event
      ]
    );

    $event_id = $insertEventStmt->insert_id;

    execQuery(
      $conn,
      "
            INSERT INTO event_type (event_id, background_id, activity_type_id, series_option_id)
            VALUES (?, ?, ?, ?)
            ",
      "iiii",
      [$event_id, $background_id, $activity_type_id, $series_option_id]
    );

    execQuery(
      $conn,
      "
            INSERT INTO event_dates (event_id, start_datetime, end_datetime)
            VALUES (?, ?, ?)
            ",
      "iss",
      [$event_id, $start_datetime, $end_datetime]
    );

    execQuery(
      $conn,
      "
            INSERT INTO event_participants (event_id, participants, participant_range, has_visitors)
            VALUES (?, ?, ?, ?)
            ",
      "isss",
      [$event_id, $participants, $participant_range, $has_visitors]
    );

    execQuery(
      $conn,
      "
            INSERT INTO event_location (event_id, venue_platform, distance)
            VALUES (?, ?, ?)
            ",
      "iss",
      [$event_id, $venue_platform, $distance]
    );

    execQuery(
      $conn,
      "
            INSERT INTO event_logistics (event_id, extraneous, collect_payments, overnight)
            VALUES (?, ?, ?, ?)
            ",
      "issi",
      [$event_id, $extraneous, $collect_payments, $overnight]
    );

    execQuery(
      $conn,
      "
            INSERT INTO event_metrics (event_id, target_metric, actual_metric)
            VALUES (?, ?, NULL)
            ",
      "is",
      [$event_id, $target_metric]
    );

    $notes = ($event_status === 'Draft')
      ? "Draft event created via Event Manager"
      : "Event created via Event Manager";

    execQuery(
      $conn,
      "
            INSERT INTO calendar_entries (user_id, event_id, title, start_datetime, end_datetime, notes)
            VALUES (?, ?, ?, ?, ?, ?)
            ",
      "iissss",
      [$_SESSION["user_id"], $event_id, $event_name, $start_datetime, $end_datetime, $notes]
    );
  }

  return $event_id;
}

/* ================= REQUIREMENT DEADLINE ================= */
function computeRequirementDeadline(string $start_datetime, string $end_datetime, $offset_days, string $basis): ?string
{
  if ($offset_days === null || $basis === 'manual') {
    return null;
  }

  $baseDate = null;

  if (in_array($basis, ['before_start', 'after_start'], true) && $start_datetime !== '') {
    $baseDate = new DateTime($start_datetime);
  } elseif (in_array($basis, ['before_end', 'after_end'], true) && $end_datetime !== '') {
    $baseDate = new DateTime($end_datetime);
  }

  if (!$baseDate) {
    return null;
  }

  if ($basis === 'before_start' || $basis === 'before_end') {
    $baseDate->modify("-{$offset_days} days");
  } elseif ($basis === 'after_start' || $basis === 'after_end') {
    $baseDate->modify("+{$offset_days} days");
  }

  return $baseDate->format('Y-m-d H:i:s');
}

/* ================= FETCH MAPPED REQUIREMENT NAMES ================= */
function fetchMappedRequirementNames(int $background_id, int $activity_type_id): array
{
  global $conn;

  $rows = fetchAll(
    $conn,
    "
        SELECT rt.req_name
        FROM config_requirements_map crm
        INNER JOIN requirement_templates rt
            ON crm.req_template_id = rt.req_template_id
        WHERE crm.background_id = ?
          AND crm.activity_type_id = ?
          AND crm.is_active = 1
          AND rt.is_active = 1
        ORDER BY rt.req_name ASC
        ",
    "ii",
    [$background_id, $activity_type_id]
  );

  return array_map(fn($row) => $row['req_name'], $rows);
}

/* ================= SYNC REQUIREMENTS ================= */
function syncEventRequirements(int $event_id): void
{
  global $conn;

  $background_id = !empty($_SESSION['background_id']) ? (int) $_SESSION['background_id'] : 0;
  $activity_type_id = !empty($_SESSION['activity_type_id']) ? (int) $_SESSION['activity_type_id'] : 0;
  $activity_type_name = $_SESSION['activity_type_name'] ?? '';

  $collect_payments = $_SESSION['collect_payments'] ?? null;
  $extraneous = $_SESSION['extraneous'] ?? null;
  $overnight = $_SESSION['overnight'] ?? null;
  $has_visitors = $_SESSION['has_visitors'] ?? null;
  $start_datetime = $_SESSION['start_datetime'] ?? '';
  $end_datetime = $_SESSION['end_datetime'] ?? '';

  $checklist = fetchMappedRequirementNames($background_id, $activity_type_id);

  if ($collect_payments === 'Yes') {
    $checklist[] = 'Request Letter for Collection/Selling';
  }

  if ($extraneous === 'Yes') {
    $checklist[] = 'Medical Clearance of Participants';
  }

  if ((string) $overnight === '1' && strpos($activity_type_name, 'Off-Campus') !== false) {
    $checklist[] = 'Risk Assessment Plan with Emergency Contacts and Emergency Map';
  }

  if (strpos($activity_type_name, 'On-campus') !== false && $has_visitors === 'Yes') {
    $checklist[] = 'Visitors and Vehicle Lists';
  }

  $checklist[] = 'Narrative Report';
  $checklist = array_values(array_unique($checklist));

  $rows = fetchAll(
    $conn,
    "
        SELECT er.event_req_id, rt.req_name, er.submission_status
        FROM event_requirements er
        INNER JOIN requirement_templates rt
            ON er.req_template_id = rt.req_template_id
        WHERE er.event_id = ?
        ",
    "i",
    [$event_id]
  );

  $current = [];
  foreach ($rows as $row) {
    $current[$row['req_name']] = $row;
  }

  $desiredTemplates = [];

  if (!empty($checklist)) {
    $placeholders = implode(',', array_fill(0, count($checklist), '?'));
    $types = str_repeat('s', count($checklist));

    $rows = fetchAll(
      $conn,
      "
            SELECT req_template_id, req_name, default_due_offset_days, default_due_basis
            FROM requirement_templates
            WHERE is_active = 1
              AND req_name IN ($placeholders)
            ",
      $types,
      $checklist
    );

    foreach ($rows as $row) {
      $desiredTemplates[$row['req_name']] = [
        'req_template_id' => (int) $row['req_template_id'],
        'default_due_offset_days' => $row['default_due_offset_days'],
        'default_due_basis' => $row['default_due_basis']
      ];
    }
  }

  $toAdd = array_diff($checklist, array_keys($current));
  $toRemove = array_diff(array_keys($current), $checklist);
  $toKeep = array_intersect($checklist, array_keys($current));

  foreach ($toKeep as $reqName) {
    if (!isset($desiredTemplates[$reqName])) {
      continue;
    }

    $template = $desiredTemplates[$reqName];
    $templateId = (int) $template['req_template_id'];
    $deadline = computeRequirementDeadline(
      $start_datetime,
      $end_datetime,
      $template['default_due_offset_days'],
      $template['default_due_basis']
    );

    execQuery(
      $conn,
      "
            UPDATE event_requirements
            SET deadline = ?, updated_at = CURRENT_TIMESTAMP
            WHERE event_id = ? AND req_template_id = ?
            ",
      "sii",
      [$deadline, $event_id, $templateId]
    );

    if ($reqName === 'Narrative Report') {
      $eventReqRow = fetchOne(
        $conn,
        "
                SELECT event_req_id
                FROM event_requirements
                WHERE event_id = ? AND req_template_id = ?
                LIMIT 1
                ",
        "ii",
        [$event_id, $templateId]
      );

      if ($eventReqRow) {
        execQuery(
          $conn,
          "
                    INSERT INTO narrative_report_details (event_req_id)
                    VALUES (?)
                    ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP
                    ",
          "i",
          [(int) $eventReqRow['event_req_id']]
        );
      }
    }
  }

  foreach ($toRemove as $reqName) {
    $submissionStatus = $current[$reqName]['submission_status'] ?? 'Pending';

    if ($submissionStatus === 'Uploaded') {
      continue;
    }

    execQuery(
      $conn,
      "
            DELETE er
            FROM event_requirements er
            INNER JOIN requirement_templates rt
                ON er.req_template_id = rt.req_template_id
            WHERE er.event_id = ? AND rt.req_name = ?
            ",
      "is",
      [$event_id, $reqName]
    );
  }

  foreach ($toAdd as $reqName) {
    if (!isset($desiredTemplates[$reqName])) {
      continue;
    }

    $template = $desiredTemplates[$reqName];
    $templateId = (int) $template['req_template_id'];
    $deadline = computeRequirementDeadline(
      $start_datetime,
      $end_datetime,
      $template['default_due_offset_days'],
      $template['default_due_basis']
    );

    execQuery(
      $conn,
      "
            INSERT INTO event_requirements (
                event_id, req_template_id, submission_status, review_status, deadline
            ) VALUES (?, ?, 'Pending', 'Not Reviewed', ?)
            ",
      "iis",
      [$event_id, $templateId, $deadline]
    );

    if ($reqName === 'Narrative Report') {
      $eventReqRow = fetchOne(
        $conn,
        "
                SELECT event_req_id
                FROM event_requirements
                WHERE event_id = ? AND req_template_id = ?
                LIMIT 1
                ",
        "ii",
        [$event_id, $templateId]
      );

      if ($eventReqRow) {
        execQuery(
          $conn,
          "
                    INSERT INTO narrative_report_details (event_req_id)
                    VALUES (?)
                    ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP
                    ",
          "i",
          [(int) $eventReqRow['event_req_id']]
        );
      }
    }
  }

  $countRes = fetchOne(
    $conn,
    "
        SELECT COUNT(*) AS total
        FROM event_requirements
        WHERE event_id = ?
        ",
    "i",
    [$event_id]
  );
  $docs_total = (int) ($countRes['total'] ?? 0);

  $uploadedRes = fetchOne(
    $conn,
    "
        SELECT COUNT(*) AS uploaded_total
        FROM event_requirements
        WHERE event_id = ? AND submission_status = 'Uploaded'
        ",
    "i",
    [$event_id]
  );
  $docs_uploaded = (int) ($uploadedRes['uploaded_total'] ?? 0);

  execQuery(
    $conn,
    "
        UPDATE events
        SET docs_total = ?, docs_uploaded = ?
        WHERE event_id = ?
        ",
    "iii",
    [$docs_total, $docs_uploaded, $event_id]
  );
}

/* ================= LOAD ================= */
list($org_options, $background_options, $activity_types, $series_options) = fetchConfigOptions();
$formData = fetchFormData($event_id, $ce_fields, $editing);
list($requirements_templates, $requirements_descs) = fetchTemplateData();

/* ================= SUBMIT ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $submit_action = $_POST['submit_action'] ?? '';

  foreach ($ce_fields as $field) {
    if ($field === 'organizing_body') {
      $_SESSION[$field] = $_POST[$field] ?? [];
    } else {
      $_SESSION[$field] = $_POST[$field] ?? null;
    }
  }

  resolveConfigLabels($_SESSION);

  $isDraft = ($submit_action === 'save_draft');
  $event_status = $isDraft ? 'Draft' : 'Pending Review';

  if ($editing) {
    $editableEventRow = fetchOne(
      $conn,
      "
            SELECT event_status
            FROM events
            WHERE event_id = ?
              AND user_id = ?
              AND archived_at IS NULL
            LIMIT 1
            ",
      "ii",
      [$event_id, $_SESSION["user_id"]]
    );

    if (!$editableEventRow) {
      popup_error("Event not found or you do not have permission to edit it.");
    }

    if (!canEditEventStatus($editableEventRow['event_status'] ?? '')) {
      popup_error("This event can no longer be edited.");
    }
  }

  try {
    $conn->begin_transaction();

    if (!$isDraft) {
      validateEventSubmission($_SESSION);
    }

    $event_id = saveEventData($event_id, $editing, $event_status);

    if ($isDraft) {
      execQuery(
        $conn,
        "
                UPDATE events
                SET docs_total = 0, docs_uploaded = 0
                WHERE event_id = ?
                ",
        "i",
        [$event_id]
      );
    } else {
      syncEventRequirements($event_id);
    }

    $conn->commit();

    foreach ($ce_fields as $field) {
      unset($_SESSION[$field]);
    }
    unset($_SESSION['background_name'], $_SESSION['activity_type_name'], $_SESSION['series_name']);

    header("Location: view_event.php?id=" . $event_id);
    exit();
  } catch (Exception $e) {
    $conn->rollback();
    popup_error("Failed to save event: " . $e->getMessage());
  }
}
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
        <div class="sidebar-overlay" id="sidebarOverlay" hidden></div>

        <?php include 'assets/includes/general_nav.php' ?>

        <main class="main">
            <header class="topbar ce-topbar">
                <button class="hamburger" id="menuBtn" type="button" aria-label="Open menu">☰</button>

                <div class="title-wrap">
                    <h1><?= $editing ? 'Edit' : 'Create' ?> Event</h1>
                    <p><?= $editing ? 'Update the event details below.' : 'Fill out the form below to create a new event.' ?></p>
                </div>
            </header>

            <form method="POST" class="event-form">
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
                        <fieldset class="field">
                            <label for="organizing_body" class="field-title">Organizing Body</label>
                            <small class="hint">Select one or more organizing bodies.</small>

                            <select id="organizing_body" name="organizing_body[]" multiple hidden>
                                <?php
                                $organizing_body = [];
                                if (!empty($formData['organizing_body'])) {
                                  $organizing_body = json_decode($formData['organizing_body'], true);
                                  if (!is_array($organizing_body)) {
                                    $organizing_body = [];
                                  }
                                }

                                foreach ($org_options as $row) {
                                  $org = $row['org_name'];
                                  $selected = in_array($org, $organizing_body, true) ? 'selected' : '';
                                  echo "<option value=\"" . htmlspecialchars($org) . "\" $selected>" . htmlspecialchars($org) . "</option>";
                                }
                                ?>
                            </select>

                            <div class="selected-tags" id="selectedTags">
                                <?php if (!empty($organizing_body)): ?>
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
                            <label class="field-title">Background</label>
                            <small class="hint">Indicate who initiated this activity.</small>

                            <div class="radio-group two-col">
                                <?php foreach ($background_options as $row): ?>
                                      <label>
                                          <input
                                              type="radio"
                                              name="background_id"
                                              value="<?= (int) $row['background_id'] ?>"
                                              required
                                              <?= ((string) $formData['background_id'] === (string) $row['background_id']) ? 'checked' : '' ?>>
                                          <?= htmlspecialchars($row['background_name']) ?>
                                      </label>
                                <?php endforeach; ?>
                            </div>
                        </fieldset>

                        <fieldset class="field">
                            <label class="field-title">Type of Activity</label>
                            <small class="hint">Choose the activity type. This helps categorize events for reporting.</small>

                            <div class="radio-group two-col">
                                <?php foreach ($activity_types as $row): ?>
                                      <label>
                                          <input
                                              type="radio"
                                              name="activity_type_id"
                                              value="<?= (int) $row['activity_type_id'] ?>"
                                              required
                                              <?= ((string) $formData['activity_type_id'] === (string) $row['activity_type_id']) ? 'checked' : '' ?>>
                                          <?= htmlspecialchars($row['activity_type_name']) ?>
                                      </label>
                                <?php endforeach; ?>
                            </div>
                        </fieldset>

                        <fieldset
                            class="field"
                            id="series-block"
                            style="display:<?= (($formData['background_name'] ?? '') === 'Participation') ? 'flex' : 'none' ?>;">
                            <label class="field-title">Series</label>
                            <small class="hint">Select the series if this is a participation activity.</small>

                            <div class="radio-group two-col">
                                <?php foreach ($series_options as $row): ?>
                                      <label>
                                          <input
                                              type="radio"
                                              name="series_option_id"
                                              value="<?= (int) $row['series_option_id'] ?>" <?= ((string) $formData['series_option_id'] === (string) $row['series_option_id']) ? 'checked' : '' ?>> <?= htmlspecialchars($row['series_name']) ?>
                                      </label>
                                <?php endforeach; ?>
                            </div>
                        </fieldset>
                    </div>
                </details>

                <div class="step-actions step1-actions">
                    <button type="button" class="btn-primary next-btn" disabled>Next</button>
                </div>

                <details class="step-2 acc">
                    <summary class="acc-head">
                        <span class="acc-left">
                            <span class="acc-dot"><i class="fa-solid fa-circle-info"></i></span>
                            <span class="acc-text">
                                <label class="acc-title">Basic Information</label>
                                <span class="acc-sub">Nature & Event Name</span>
                            </span>
                        </span>
                        <span class="acc-chevron"></span>
                    </summary>

                    <div class="acc-body">
                        <div class="field long-field">
                            <label for="nature" class="field-title">Nature of the Event</label>
                            <small class="hint">
                                If you were asked to describe what is your activity in one to three words, what would it be? (Example: Seminar)
                            </small>
                            <textarea name="nature" id="nature" required><?= htmlspecialchars($formData['nature']) ?></textarea>
                        </div>

                        <div class="field long-field">
                            <label for="event_name" class="field-title">Name of the Event</label>
                            <small class="hint">
                                If this is one event in a series of events, place the umbrella event first, then the specific activity.
                                (Example: SOC Days '25: Code Geeks Hackathon)
                            </small>
                            <textarea name="event_name" id="event_name" required><?= htmlspecialchars($formData['event_name']) ?></textarea>
                        </div>

                        <div class="field long-field">
                            <label for="target_metric" class="field-title">Target Metric</label>
                            <small class="hint">
                                <span class="hint-important">Format:</span> percentage followed by a description. Example: 75% Satisfaction Rating
                            </small>
                            <textarea name="target_metric" id="target_metric" rows="2"><?= htmlspecialchars($formData['target_metric'] ?? '') ?></textarea>
                        </div>

                        <fieldset class="field">
                            <label class="field-title">Is this an extraneous activity?</label>

                            <div class="radio-group inline">
                                <label>
                                    <input type="radio" name="extraneous" value="Yes" required <?= ($formData['extraneous'] === 'Yes') ? 'checked' : '' ?>>Yes
                                </label>

                                <label>
                                    <input type="radio" name="extraneous" value="No" required <?= ($formData['extraneous'] === 'No') ? 'checked' : '' ?>>No
                                </label>
                            </div>
                        </fieldset>

                        <fieldset class="field">
                            <label class="field-title">Would you collect payments or sell merchandise for this activity?</label>
                            <div class="radio-group inline">
                                <label>
                                    <input type="radio" name="collect_payments" value="Yes" required <?= ($formData['collect_payments'] === 'Yes') ? 'checked' : '' ?>>
                                    Yes
                                </label>

                                <label>
                                    <input type="radio" name="collect_payments" value="No" required <?= ($formData['collect_payments'] === 'No') ? 'checked' : '' ?>>
                                    No
                                </label>
                            </div>
                        </fieldset>
                    </div>
                </details>

                <details class="step-2 acc">
                    <summary class="acc-head">
                        <span class="acc-left">
                            <span class="acc-dot"><i class="fa-solid fa-calendar-days"></i></span>
                            <span class="acc-text">
                                <label class="acc-title">Logistics</label>
                                <span class="acc-sub">Schedule, Venue, & Participants</span>
                            </span>
                        </span>
                        <span class="acc-chevron"></span>
                    </summary>

                    <div class="acc-body">
                        <div class="form-row">
                            <div class="field">
                                <label for="start_datetime" class="field-title">Start Date and Time</label>
                                <small class="hint">Indicate the start of ingress.</small>
                                <input
                                    type="datetime-local"
                                    name="start_datetime"
                                    id="start_datetime"
                                    value="<?= htmlspecialchars($formData['start_datetime']) ?>"
                                    required>
                            </div>

                            <div class="field">
                                <label for="end_datetime" class="field-title">End Date and Time</label>
                                <small class="hint">Must be <span class="hint-important">at least 2 hours after</span> the Start Date and Time.</small>
                                <input
                                    type="datetime-local"
                                    name="end_datetime"
                                    id="end_datetime"
                                    value="<?= htmlspecialchars($formData['end_datetime']) ?>"
                                    required>
                            </div>
                        </div>

                        <div class="field long-field" style="margin-top: 0;">
                            <label for="participants" class="field-title">Participants</label>
                            <small class="hint">Indicate number and group. (Example: 8 members, 7 officers, 2 guest speakers)</small>
                            <textarea name="participants" id="participants" rows="2" required><?= htmlspecialchars($formData['participants']) ?></textarea>
                        </div>

                        <div class="field long-field">
                            <label for="venue_platform" class="field-title">Venue / Platform</label>
                            <small class="hint">Indicate the room number for caserooms. Provide the invite link if for online sessions and invite either studentactivities@hau.edu.ph or studentactivities.hauosa@gmail.com</small>
                            <textarea name="venue_platform" id="venue_platform" required><?= htmlspecialchars($formData['venue_platform']) ?></textarea>
                        </div>

                        <fieldset
                            class="field"
                            id="visitors-block"
                            style="display:<?= (strpos($formData['activity_type_name'] ?? '', 'On-campus') !== false) ? 'flex' : 'none' ?>;">
                            <label class="field-title">Will there be visitors entering the campus?</label>
                            <small class="hint">External guests or non-HAU members attending the event.</small>

                            <div class="radio-group inline">
                                <label>
                                    <input type="radio" name="has_visitors" value="Yes" <?= (($formData['has_visitors'] ?? '') === 'Yes') ? 'checked' : '' ?>>
                                    Yes
                                </label>

                                <label>
                                    <input type="radio" name="has_visitors" value="No" <?= (($formData['has_visitors'] ?? '') === 'No') ? 'checked' : '' ?>>
                                    No
                                </label>
                            </div>
                        </fieldset>

                        <fieldset
                            class="field"
                            id="offcampus-block"
                            style="display:<?= (strpos($formData['activity_type_name'] ?? '', 'Off-Campus') !== false) ? 'block' : 'none' ?>;">

                            <div class="form-row">
                                <fieldset class="field" style="margin-top: 0;">
                                    <label class="field-title">Range of Total Number of Participants</label>
                                    <div class="radio-group">
                                        <?php foreach ($participant_range_options as $r): ?>
                                              <label>
                                                  <input
                                                      type="radio"
                                                      name="participant_range"
                                                      value="<?= htmlspecialchars($r) ?>"
                                                      <?= ($formData['participant_range'] === $r) ? 'checked' : '' ?>>
                                                  <?= htmlspecialchars($r) ?>
                                              </label>
                                        <?php endforeach; ?>
                                    </div>
                                </fieldset>

                                <fieldset class="field" style="margin-top: 0;">
                                    <label class="field-title">Distance</label>
                                    <small class="hint">How far will participants travel?</small>
                                    <div class="radio-group">
                                        <?php foreach ($distance_options as $distance): ?>
                                              <label>
                                                  <input
                                                      type="radio"
                                                      name="distance"
                                                      value="<?= htmlspecialchars($distance) ?>"
                                                      <?= (($formData['distance'] ?? '') === $distance) ? 'checked' : '' ?>>
                                                  <?= htmlspecialchars($distance) ?>
                                              </label>
                                        <?php endforeach; ?>
                                    </div>
                                </fieldset>
                            </div>

                            <div class="field" style="margin-top: 0;">
                                <label class="field-title">
                                    Will the activity last more than 12 hours from arrival to departure?
                                </label>

                                <small class="hint">Select "Yes" if it includes an overnight stay.</small>

                                <div class="radio-group inline">
                                    <label>
                                        <input type="radio" name="overnight" value="1" <?= ((string) $formData['overnight'] === '1') ? 'checked' : '' ?>>
                                        Yes
                                    </label>

                                    <label>
                                        <input type="radio" name="overnight" value="0" <?= ((string) $formData['overnight'] === '0') ? 'checked' : '' ?>>
                                        No
                                    </label>
                                </div>
                            </div>
                        </fieldset>
                    </div>
                </details>

                <div class="step-actions step-2-actions">
                    <button type="button" class="btn-secondary back-btn">Back</button>

                    <button type="submit" name="submit_action" value="save_draft" class="btn-secondary">
                        Save as Draft
                    </button>

                    <button type="submit" name="submit_action" value="submit_event" class="btn-primary create-btn" disabled>
                        <?= $editing ? 'Submit Event' : 'Create Event' ?>
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