<?php
/**
 * Event Creation and Management Module
 * 
 * This module handles the creation, editing, and requirement synchronization of events.
 * It manages form data, database operations, and requirement templates.
 */

session_start();
require_once __DIR__ . '/../../app/database.php';
require_once APP_PATH . "security_headers.php";
send_security_headers();

requireLogin();

// ============================================================================
// CONFIGURATION & CONSTANTS
// ============================================================================

/** List of all form fields used in event creation and editing */
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

// Initialize form data that will be populated from database or session
$formData = [];

// ============================================================================
// UTILITY & VALIDATION FUNCTIONS
// ============================================================================

/**
 * Determines if an event with the given status can be edited.
 * Only Draft, Pending Review, and Needs Revision statuses are editable.
 *
 * @param string $status The current event status
 * @return bool True if the event can be edited, false otherwise
 */
function canEditEventStatus(string $status): bool
{
  return in_array($status, ['Draft', 'Pending Review', 'Needs Revision'], true);
}

// ============================================================================
// DATABASE CONFIGURATION LOADING
// ============================================================================

/**
 * Fetches all configuration options required for event form rendering.
 * Retrieves four sets of configuration data: organizing bodies, backgrounds,
 * activity types, and series options. All are sorted by order and name.
 *
 * @return array Array containing [org_rows, background_rows, activity_rows, series_rows]
 */
function fetchConfigOptions(): array
{
  global $conn;

  // Fetch all active organizing body options, ordered for presentation
  $org_rows = fetchAll(
    $conn,
    "
        SELECT org_name
        FROM config_org_options
        WHERE is_active = 1
        ORDER BY sort_order ASC, org_name ASC
        "
  );

  // Fetch all active background options with their IDs for form controls
  $background_rows = fetchAll(
    $conn,
    "
        SELECT background_id, background_name
        FROM config_background_options
        WHERE is_active = 1
        ORDER BY sort_order ASC, background_name ASC
        "
  );

  // Fetch all active activity type options with their IDs
  $activity_rows = fetchAll(
    $conn,
    "
        SELECT activity_type_id, activity_type_name
        FROM config_activity_types
        WHERE is_active = 1
        ORDER BY sort_order ASC, activity_type_name ASC
        "
  );

  // Fetch all active series options with their IDs
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

// ============================================================================
// FORM DATA MANAGEMENT
// ============================================================================

/**
 * Fetches form data for display in the event form.
 * When editing, loads data from the database. When creating new, loads from session.
 * Includes validation that the user has permission to edit the event.
 *
 * @param int $event_id The event ID (0 if creating new)
 * @param array $ce_fields List of form field names to load
 * @param bool $editing Whether we're editing an existing event
 * @return array Associative array of form field values
 */
function fetchFormData(int $event_id, array $ce_fields, bool $editing): array
{
  global $conn;

  $formData = [];

  if ($editing) {
    // Complex query that joins all related event tables to gather all necessary data
    // Uses LEFT JOINs to handle optional tables (some events may not have all child records)
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

    // Fetch the existing event data with user permission check
    $existing_event = fetchOne(
      $conn,
      $sql,
      "ii",
      [$event_id, $_SESSION["user_id"]]
    );

    // Validate that the event exists and user has permission
    if (!$existing_event) {
      popup_error("Event not found or you do not have permission to edit it.");
    }

    // Validate that the event status allows editing
    if (!canEditEventStatus($existing_event['event_status'] ?? '')) {
      popup_error("This event can no longer be edited.");
    }

    // Populate form data from the existing event record
    foreach ($ce_fields as $field) {
      $formData[$field] = $existing_event[$field] ?? '';
    }

    // Add the human-readable config label names
    $formData['background_name'] = $existing_event['background_name'] ?? '';
    $formData['activity_type_name'] = $existing_event['activity_type_name'] ?? '';
    $formData['series_name'] = $existing_event['series_name'] ?? '';
    $formData['event_status'] = $existing_event['event_status'] ?? 'Draft';
  } else {
    // For new events, load initial data from session (user may have started filling the form)
    foreach ($ce_fields as $field) {
      $formData[$field] = $_SESSION[$field] ?? '';
    }

    // Initialize config label names as empty for new events
    $formData['background_name'] = '';
    $formData['activity_type_name'] = '';
    $formData['series_name'] = '';
    $formData['event_status'] = 'Draft';
  }

  return $formData;
}

/**
 * Fetches requirement templates and descriptions from database.
 * Organizes templates by requirement name for easy lookup during form rendering.
 *
 * @return array Array containing [templates_array, descriptions_array]
 */
function fetchTemplateData(): array
{
  global $conn;

  // Fetch all active requirement templates with their metadata
  $rows = fetchAll(
    $conn,
    "
        SELECT req_name, req_desc, template_url
        FROM requirement_templates
        WHERE is_active = 1
        "
  );

  // Initialize associative arrays keyed by requirement name for fast lookup
  $templates = [];
  $descs = [];

  // Organize template data by requirement name
  foreach ($rows as $row) {
    $name = $row['req_name'];
    $templates[$name] = $row['template_url'] ?? '';
    $descs[$name] = $row['req_desc'] ?? '';
  }

  return [$templates, $descs];
}

/**
 * Resolves and looks up human-readable names for configuration IDs.
 * This function updates the data array in-place, replacing IDs with their display names.
 * Used after form submission to get readable names for the selected options.
 *
 * @param array &$data Reference to data array containing config IDs
 * @return void Updates $data array in-place with resolved names
 */
function resolveConfigLabels(array &$data): void
{
  global $conn;

  // Initialize all label fields to empty strings
  $data['background_name'] = '';
  $data['activity_type_name'] = '';
  $data['series_name'] = '';

  // Resolve background ID to its display name
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

  // Resolve activity type ID to its display name
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

  // Resolve series option ID to its display name
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

// ============================================================================
// EVENT DATA VALIDATION
// ============================================================================

/**
 * Validates the complete event submission data before saving to database.
 * Checks for required fields, proper format, and business logic rules.
 * Terminates execution with popup_error on validation failure.
 *
 * @param array $data The event data to validate
 * @return void Exits with error message if validation fails
 */
function validateEventSubmission(array $data): void
{
  // List of fields that must be present and non-empty
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

  // Verify all required fields are filled
  foreach ($requiredFields as $field) {
    if (!isset($data[$field]) || trim((string) $data[$field]) === '') {
      popup_error("Please complete all required event fields before submitting.");
    }
  }

  // Verify at least one organizing body is selected
  if (empty($data['organizing_body']) || !is_array($data['organizing_body'])) {
    popup_error("Please select at least one organizing body.");
  }

  // Validate target metric format if provided (percentage with description)
  $target_metric = trim($data['target_metric'] ?? '');
  if ($target_metric !== '' && !preg_match('/^(100|[1-9]?\d)\%\s+.+$/', $target_metric)) {
    popup_error("Target Metric must follow this format: 75% Satisfaction Rating");
  }

  // Validate start and end datetime
  $start_datetime = trim($data['start_datetime'] ?? '');
  $end_datetime = trim($data['end_datetime'] ?? '');

  if ($start_datetime !== '' && $end_datetime !== '') {
    // Convert to timestamps for comparison
    $start_ts = strtotime($start_datetime);
    $end_ts = strtotime($end_datetime);

    // Validate that both dates parsed successfully
    if ($start_ts === false || $end_ts === false) {
      popup_error("Invalid start or end date/time.");
    }

    // Validate that end time is at least 2 hours (7200 seconds) after start time
    if (($end_ts - $start_ts) < 7200) {
      popup_error("End Date and Time must be at least 2 hours after the Start Date and Time.");
    }
  }

  // Validate series requirement for participation activities
  $background_name = $data['background_name'] ?? '';
  if ($background_name === 'Participation' && empty($data['series_option_id'])) {
    popup_error("Please select a series for participation activities.");
  }
}

// ============================================================================
// EVENT DATA PERSISTENCE
// ============================================================================

/**
 * Saves event data to the database, handling both creation and updates.
 * For new events: Creates core event record and all child records.
 * For existing events: Updates all related tables and syncs calendar entries.
 * 
 * Function processes session data and distributes it across multiple tables:
 * - events: Core event information
 * - event_type: Background and activity classification
 * - event_dates: Start and end datetime information
 * - event_participants: Participant count and range information
 * - event_location: Venue/platform and distance information
 * - event_logistics: Extraneous flag, payment collection, overnight status
 * - event_metrics: Target metrics and measurements
 * - calendar_entries: User calendar synchronization
 *
 * @param int $event_id The event ID (0 for new events)
 * @param bool $editing Whether this is an update operation
 * @param string $event_status The status to set (Draft, Pending Review)
 * @return int The event ID (newly generated if creating, same if updating)
 */
function saveEventData(int $event_id, bool $editing, string $event_status): int
{
  global $conn;

  // ======================= EXTRACT & PREPARE DATA =======================
  
  // Process organizing body array into JSON format for storage
  $organizing_body_json = isset($_SESSION['organizing_body'])
    ? json_encode($_SESSION['organizing_body'], JSON_UNESCAPED_UNICODE)
    : '[]';

  // Extract and trim string fields
  $nature = trim($_SESSION['nature'] ?? '');
  $event_name = trim($_SESSION['event_name'] ?? '');

  // Extract configuration IDs, with defaults for failed conversions
  $background_id = !empty($_SESSION['background_id']) ? (int) $_SESSION['background_id'] : 0;
  $activity_type_id = !empty($_SESSION['activity_type_id']) ? (int) $_SESSION['activity_type_id'] : 0;
  $series_option_id = ($_SESSION['series_option_id'] ?? '') !== '' ? (int) $_SESSION['series_option_id'] : null;

  // Extract datetime fields
  $start_datetime = $_SESSION['start_datetime'] ?? '';
  $end_datetime = $_SESSION['end_datetime'] ?? '';

  // Extract participant information
  $participants = trim($_SESSION['participants'] ?? '');
  $participant_range = $_SESSION['participant_range'] ?? null;
  $has_visitors = $_SESSION['has_visitors'] ?? null;

  // Extract location information
  $venue_platform = trim($_SESSION['venue_platform'] ?? '');
  $distance = $_SESSION['distance'] ?? null;

  // Extract logistics flags with default values
  $extraneous = $_SESSION['extraneous'] ?? 'No';
  $collect_payments = $_SESSION['collect_payments'] ?? 'No';
  $target_metric = trim($_SESSION['target_metric'] ?? '');
  // overnight is stored as 1/0, null if not set
  $overnight = (!isset($_SESSION['overnight']) || $_SESSION['overnight'] === '')
    ? null
    : (int) $_SESSION['overnight'];

  if ($editing) {
    // ======================= UPDATING EXISTING EVENT =======================
    
    // Update core event information
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

    // Update event classification (background and activity type)
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

    // Update event schedule
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

    // Update participant information
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

    // Update location and venue information
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

    // Update event logistics information
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

    // Update or create event metrics (check existence first to use appropriate query)
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

    // Generate appropriate calendar note based on event status
    $notes = ($event_status === 'Draft')
      ? "Draft event updated via Event Manager"
      : "Event submitted/updated via Event Manager";

    // Update or create corresponding calendar entry
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
    // ======================= CREATING NEW EVENT =======================
    
    // Initialize metrics counters for new events
    $docs_total = 0;
    $docs_uploaded = 0;
    $is_system_event = 0;

    // Insert core event record and retrieve generated ID
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

    // Insert event classification record
    execQuery(
      $conn,
      "
            INSERT INTO event_type (event_id, background_id, activity_type_id, series_option_id)
            VALUES (?, ?, ?, ?)
            ",
      "iiii",
      [$event_id, $background_id, $activity_type_id, $series_option_id]
    );

    // Insert event schedule record
    execQuery(
      $conn,
      "
            INSERT INTO event_dates (event_id, start_datetime, end_datetime)
            VALUES (?, ?, ?)
            ",
      "iss",
      [$event_id, $start_datetime, $end_datetime]
    );

    // Insert participant information record
    execQuery(
      $conn,
      "
            INSERT INTO event_participants (event_id, participants, participant_range, has_visitors)
            VALUES (?, ?, ?, ?)
            ",
      "isss",
      [$event_id, $participants, $participant_range, $has_visitors]
    );

    // Insert location information record
    execQuery(
      $conn,
      "
            INSERT INTO event_location (event_id, venue_platform, distance)
            VALUES (?, ?, ?)
            ",
      "iss",
      [$event_id, $venue_platform, $distance]
    );

    // Insert logistics information record
    execQuery(
      $conn,
      "
            INSERT INTO event_logistics (event_id, extraneous, collect_payments, overnight)
            VALUES (?, ?, ?, ?)
            ",
      "issi",
      [$event_id, $extraneous, $collect_payments, $overnight]
    );

    // Insert event metrics record
    execQuery(
      $conn,
      "
            INSERT INTO event_metrics (event_id, target_metric, actual_metric)
            VALUES (?, ?, NULL)
            ",
      "is",
      [$event_id, $target_metric]
    );

    // Generate appropriate calendar note based on event status
    $notes = ($event_status === 'Draft')
      ? "Draft event created via Event Manager"
      : "Event created via Event Manager";

    // Create corresponding calendar entry
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

  // Return the event ID (newly generated for new events, unchanged for updates)
  return $event_id;
}

// ============================================================================
// REQUIREMENT & DEADLINE MANAGEMENT
// ============================================================================

/**
 * Computes the deadline for a requirement based on configured offset and basis.
 * The deadline can be relative to the event's start or end date, or set before/after.
 * Returns null if the requirement has no automatic deadline calculation.
 *
 * @param string $start_datetime Event start datetime in 'Y-m-d H:i:s' format
 * @param string $end_datetime Event end datetime in 'Y-m-d H:i:s' format
 * @param mixed $offset_days Number of days to offset from the basis date (nullable)
 * @param string $basis Basis for deadline calculation:
 *                      - 'before_start': offset days before event start
 *                      - 'after_start': offset days after event start
 *                      - 'before_end': offset days before event end
 *                      - 'after_end': offset days after event end
 *                      - 'manual': no automatic deadline
 * @return string|null Computed deadline in 'Y-m-d H:i:s' format, or null if no deadline
 */
function computeRequirementDeadline(string $start_datetime, string $end_datetime, $offset_days, string $basis): ?string
{
  // Return null for null offset or manual basis (no automatic calculation)
  if ($offset_days === null || $basis === 'manual') {
    return null;
  }

  // Determine the base date based on the basis parameter
  $baseDate = null;

  if (in_array($basis, ['before_start', 'after_start'], true) && $start_datetime !== '') {
    $baseDate = new DateTime($start_datetime);
  } elseif (in_array($basis, ['before_end', 'after_end'], true) && $end_datetime !== '') {
    $baseDate = new DateTime($end_datetime);
  }

  // If no valid base date could be determined, return null
  if (!$baseDate) {
    return null;
  }

  // Apply the offset based on the basis direction
  if ($basis === 'before_start' || $basis === 'before_end') {
    // Subtract days for "before" deadlines
    $baseDate->modify("-{$offset_days} days");
  } elseif ($basis === 'after_start' || $basis === 'after_end') {
    // Add days for "after" deadlines
    $baseDate->modify("+{$offset_days} days");
  }

  return $baseDate->format('Y-m-d H:i:s');
}

/**
 * Fetches the list of requirement names mapped to a specific background/activity combination.
 * These are the base requirements that should exist for this type of event.
 *
 * @param int $background_id The event background ID
 * @param int $activity_type_id The event activity type ID
 * @return array List of requirement names (strings) mapped to this combo
 */
function fetchMappedRequirementNames(int $background_id, int $activity_type_id): array
{
  global $conn;

  // Query the requirements map to find all requirements for this background/activity combination
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

  // Extract just the requirement names from the result set
  return array_map(fn($row) => $row['req_name'], $rows);
}

/**
 * Synchronizes event requirements with configuration rules and event attributes.
 * This function:
 * 1. Determines what requirements SHOULD exist based on event config and attributes
 * 2. Fetches what requirements CURRENTLY exist for the event
 * 3. Adds missing requirements, removes unneeded ones, and updates existing ones with correct deadlines
 * 
 * The requirement checklist is built from:
 * - Base requirements mapped to the background/activity combination
 * - Conditional requirements based on event flags (payments, extraneous, overnight, visitors)
 * - Narrative Report (always required)
 *
 * @param int $event_id The event ID to sync requirements for
 * @return void Updates database but returns nothing
 */
function syncEventRequirements(int $event_id): void
{
  global $conn;

  // ==================== EXTRACT EVENT CONFIGURATION ====================
  
  // Extract event classification and attributes from session
  $background_id = !empty($_SESSION['background_id']) ? (int) $_SESSION['background_id'] : 0;
  $activity_type_id = !empty($_SESSION['activity_type_id']) ? (int) $_SESSION['activity_type_id'] : 0;
  $activity_type_name = $_SESSION['activity_type_name'] ?? '';

  // Extract conditional requirement flags
  $collect_payments = $_SESSION['collect_payments'] ?? null;
  $extraneous = $_SESSION['extraneous'] ?? null;
  $overnight = $_SESSION['overnight'] ?? null;
  $has_visitors = $_SESSION['has_visitors'] ?? null;
  $start_datetime = $_SESSION['start_datetime'] ?? '';
  $end_datetime = $_SESSION['end_datetime'] ?? '';

  // ==================== BUILD DESIRED REQUIREMENT CHECKLIST ====================
  
  // Start with base requirements mapped to this background/activity combination
  $checklist = fetchMappedRequirementNames($background_id, $activity_type_id);

  // Add conditional requirements based on event attributes
  if ($collect_payments === 'Yes') {
    $checklist[] = 'Request Letter for Collection/Selling';
  }

  if ($extraneous === 'Yes') {
    $checklist[] = 'Medical Clearance of Participants';
  }

  // Off-campus overnight activities require risk assessment
  if ((string) $overnight === '1' && strpos($activity_type_name, 'Off-Campus') !== false) {
    $checklist[] = 'Risk Assessment Plan with Emergency Contacts and Emergency Map';
  }

  // On-campus activities with visitors require visitor/vehicle lists
  if (strpos($activity_type_name, 'On-campus') !== false && $has_visitors === 'Yes') {
    $checklist[] = 'Visitors and Vehicle Lists';
  }

  // Narrative report is always required
  $checklist[] = 'Narrative Report';
  
  // Remove duplicates and maintain numeric indexing
  $checklist = array_values(array_unique($checklist));

  // ==================== FETCH CURRENT REQUIREMENTS ====================
  
  // Query all requirements currently assigned to this event
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

  // Index current requirements by name for easy lookup
  $current = [];
  foreach ($rows as $row) {
    $current[$row['req_name']] = $row;
  }

  // ==================== FETCH REQUIREMENT TEMPLATE DETAILS ====================
  
  // Pre-fetch template details for all desired requirements
  $desiredTemplates = [];

  if (!empty($checklist)) {
    // Build placeholder string for IN clause
    $placeholders = implode(',', array_fill(0, count($checklist), '?'));
    $types = str_repeat('s', count($checklist));

    // Query template details for all requirements in checklist
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

    // Index templates by name with their metadata
    foreach ($rows as $row) {
      $desiredTemplates[$row['req_name']] = [
        'req_template_id' => (int) $row['req_template_id'],
        'default_due_offset_days' => $row['default_due_offset_days'],
        'default_due_basis' => $row['default_due_basis']
      ];
    }
  }

  // ==================== DETERMINE CHANGES NEEDED ====================
  
  // Calculate set differences to determine what to add, remove, and keep
  $toAdd = array_diff($checklist, array_keys($current));
  $toRemove = array_diff(array_keys($current), $checklist);
  $toKeep = array_intersect($checklist, array_keys($current));

  // ==================== UPDATE EXISTING REQUIREMENTS ====================
  
  // For requirements that should remain, update their deadlines
  foreach ($toKeep as $reqName) {
    if (!isset($desiredTemplates[$reqName])) {
      continue;
    }

    $template = $desiredTemplates[$reqName];
    $templateId = (int) $template['req_template_id'];
    
    // Compute the deadline based on template configuration and event dates
    $deadline = computeRequirementDeadline(
      $start_datetime,
      $end_datetime,
      $template['default_due_offset_days'],
      $template['default_due_basis']
    );

    // Update requirement with new deadline
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

    // Special handling for Narrative Report: ensure narrative_report_details record exists
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
        // Create or update narrative report details record
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

  // ==================== REMOVE UNNEEDED REQUIREMENTS ====================
  
  // Remove requirements that should no longer exist (unless already uploaded)
  foreach ($toRemove as $reqName) {
    $submissionStatus = $current[$reqName]['submission_status'] ?? 'Pending';

    // Don't remove requirements that have been uploaded (preserve submitted work)
    if ($submissionStatus === 'Uploaded') {
      continue;
    }

    // Delete the requirement record
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

  // ==================== ADD NEW REQUIREMENTS ====================
  
  // Add requirements that are now needed but don't currently exist
  foreach ($toAdd as $reqName) {
    if (!isset($desiredTemplates[$reqName])) {
      continue;
    }

    $template = $desiredTemplates[$reqName];
    $templateId = (int) $template['req_template_id'];
    
    // Compute deadline for new requirement
    $deadline = computeRequirementDeadline(
      $start_datetime,
      $end_datetime,
      $template['default_due_offset_days'],
      $template['default_due_basis']
    );

    // Create new event requirement record
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

    // Special handling for Narrative Report: create narrative_report_details record
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
        // Create narrative report details record
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

  // ==================== UPDATE EVENT DOCUMENT COUNTERS ====================
  
  // Count total requirements for this event
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

  // Count uploaded requirements for this event
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

  // Update event record with new counters
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

// ============================================================================
// INITIALIZATION & DATA LOADING
// ============================================================================

// Load all configuration options needed for form rendering
list($org_options, $background_options, $activity_types, $series_options) = fetchConfigOptions();

// Load form data - either from existing event (edit mode) or session (new event mode)
$formData = fetchFormData($event_id, $ce_fields, $editing);

// Load all requirement templates and descriptions for form reference
list($requirements_templates, $requirements_descs) = fetchTemplateData();

// ============================================================================
// FORM SUBMISSION HANDLING
// ============================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Extract the submit action to determine save behavior (draft vs submit)
  $submit_action = $_POST['submit_action'] ?? '';

  // ======================= PERSIST FORM DATA TO SESSION =======================
  
  // Copy all form field values from POST to SESSION for persistence across redirects
  foreach ($ce_fields as $field) {
    if ($field === 'organizing_body') {
      // Special handling for multi-select array
      $_SESSION[$field] = $_POST[$field] ?? [];
    } else {
      // Copy scalar values
      $_SESSION[$field] = $_POST[$field] ?? null;
    }
  }

  // Resolve config IDs to their human-readable names for use in requirement logic
  resolveConfigLabels($_SESSION);

  // ======================= DETERMINE EVENT STATUS =======================
  
  // Determine the target event status based on form action
  // Draft: saved but not submitted | Pending Review: submitted for approval
  $isDraft = ($submit_action === 'save_draft');
  $event_status = $isDraft ? 'Draft' : 'Pending Review';

  // ======================= VALIDATE EDIT PERMISSIONS =======================
  
  if ($editing) {
    // Fetch current event to verify user ownership and editability
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

    // Verify event exists and belongs to current user
    if (!$editableEventRow) {
      popup_error("Event not found or you do not have permission to edit it.");
    }

    // Verify event status allows editing
    if (!canEditEventStatus($editableEventRow['event_status'] ?? '')) {
      popup_error("This event can no longer be edited.");
    }
  }

  // ======================= SAVE EVENT DATA =======================
  
  try {
    // Start database transaction to ensure all changes succeed together
    $conn->begin_transaction();

    // Validate submission data only if not saving as draft
    // Draft mode allows incomplete data
    if (!$isDraft) {
      validateEventSubmission($_SESSION);
    }

    // Save event data to database (creates new or updates existing)
    $event_id = saveEventData($event_id, $editing, $event_status);

    // ======================= POST-SAVE ACTIONS =======================
    
    if ($isDraft) {
      // For draft saves, reset document counters (no requirements tracking)
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
      // For full submissions, synchronize event requirements
      // This creates/updates/removes requirements based on event configuration
      syncEventRequirements($event_id);
    }

    // Commit all changes to database
    $conn->commit();

    // ======================= CLEANUP & REDIRECT =======================
    
    // Clear session data to prevent accidental reuse
    foreach ($ce_fields as $field) {
      unset($_SESSION[$field]);
    }
    unset($_SESSION['background_name'], $_SESSION['activity_type_name'], $_SESSION['series_name']);

    // Redirect to event view page with success
    header("Location: view_event.php?id=" . $event_id);
    exit();
  } catch (Exception $e) {
    // Rollback all database changes on error
    $conn->rollback();
    // Display error message to user
    popup_error("Failed to save event: " . $e->getMessage());
  }
}
?>

<!DOCTYPE html>
<!--
Event Creation and Management Page

This page provides a multi-step form for creating new events or editing existing ones.
The form is organized in collapsible sections (step-1 and step-2 accordions) for better UX.

Step 1: Classification - Background, Activity Type, Organization, Series
Step 2: Basic Info - Nature, Name, Target Metrics, Extraneous/Payment flags
Step 3: Logistics - Dates, Participants, Venue, Location details

Database Persistence: Uses transactional saves to ensure data consistency
Requirement Sync: Automatically creates/removes event requirements based on config/attributes
-->
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?= $editing ? 'Edit' : 'Create' ?> Event</title>
    <link rel="stylesheet" href="<?= PUBLIC_URL ?>assets/styles/layout.css" />
    <link rel="stylesheet" href="<?= PUBLIC_URL ?>assets/styles/ce_styles.css" />
</head>

<body>
    <div class="app">
        <div class="sidebar-overlay" id="sidebarOverlay" hidden></div>

        <?php include PUBLIC_PATH . 'assets/includes/general_nav.php' ?>

        <main class="main">
            <header class="topbar ce-topbar">
                <button class="hamburger" id="menuBtn" type="button" aria-label="Open menu">☰</button>

                <div class="title-wrap">
                    <h1><?= $editing ? 'Edit' : 'Create' ?> Event</h1>
                    <p><?= $editing ? 'Update the event details below.' : 'Fill out the form below to create a new event.' ?></p>
                </div>
            </header>

            <!--
            Main event form
            Multi-step accordion form with three main sections
            Uses HTML5 details/summary elements for collapsible sections
            -->
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
                                            data-background-name="<?= htmlspecialchars($row['background_name']) ?>"
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
                                            data-activity-type-name="<?= htmlspecialchars($row['activity_type_name']) ?>"
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

            <?php include PUBLIC_PATH . 'assets/includes/footer.php' ?>
        </main>
    </div>

    <!--
    JavaScript Dependencies
    layout.js: Handles global layout, responsive design, and menu interactions
    create_event.js: Handles form interaction, step navigation, multi-select, conditional fields
    -->
    <script src="<?= APP_URL ?>script/layout.js?v=1"></script>
    <script src="<?= APP_URL ?>script/create_event.js"></script>
</body>
</html>