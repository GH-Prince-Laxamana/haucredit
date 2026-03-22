<?php
// ============================================================================
// REQUIREMENTS TRACKING - User Compliance Dashboard
// ============================================================================
/**
 * Requirements page displays all compliance requirements across user's events.
 * Shows deadlines organized by status (Overdue, Today, Upcoming, No Deadline)
 * with progress tracking and filtering capabilities.
 */

session_start();
require_once __DIR__ . '/../../app/database.php';

// Set timezone to Asia/Manila for consistent datetime operations
// All timestamps throughout page will use this timezone
date_default_timezone_set('Asia/Manila');

requireLogin();

$user_id = (int) $_SESSION["user_id"];
$event_filter = isset($_GET['event_id']) && $_GET['event_id'] !== '' ? (int) $_GET['event_id'] : null;

// ============================================================================
// HELPER FUNCTIONS - Formatting & Organization
// ============================================================================

/**
 * Determine what date to display for a requirement.
 * Prioritizes requirement deadline; falls back to event start date.
 * 
 * Display logic:
 * 1. If deadline exists -> return deadline (more specific)
 * 2. Else if event start date exists -> return event start (fallback)
 * 3. Else -> return NULL (no date available)
 *
 * @param ?string $deadline Requirement-specific deadline (can be NULL)
 * @param ?string $event_start Event start datetime (can be NULL)
 * @return ?string Datetime string to display, or NULL if neither date available
 */
function getRequirementDisplayDate(?string $deadline, ?string $event_start): ?string
{
    // Priority 1: If deadline explicitly set for this requirement, use it
    if (!empty($deadline)) {
        return $deadline;
    }

    // Priority 2: If no deadline, fall back to event start date
    if (!empty($event_start)) {
        return $event_start;
    }

    // Priority 3: No date information available
    return null;
}

/**
 * Format requirement submission status for user display.
 * Maps internal database status values to human-readable labels.
 *
 * @param string $status Internal status value from database
 * @return string Formatted status label for display
 */
function formatSubmissionStatus(string $status): string
{
    return match ($status) {
        'Uploaded' => 'Uploaded',
        'Pending' => 'Pending',
        default => $status
    };
}

/**
 * Determine deadline grouping/category for a requirement.
 * Organizes requirements into one of 4 groups: Overdue, Today, Upcoming, No Deadline
 *
 * GroupingLogic:
 * - Overdue: deadline < today's start timestamp
 * - Today: deadline >= today's start AND < tomorrow's start
 * - Upcoming: deadline >= tomorrow's start
 * - No Deadline: if deadline field is empty
 *
 * @param array $requirement Requirement data array (must contain 'deadline' key)
 * @param DateTime $todayStart Timestamp representing 00:00:00 of current day (Asia/Manila)
 * @param DateTime $tomorrowStart Timestamp representing 00:00:00 of next day
 * @return string Group name: 'Overdue', 'Today', 'Upcoming', or 'No Deadline'
 */
function getRequirementGroup(array $requirement, DateTime $todayStart, DateTime $tomorrowStart): string
{
    // ===== CHECK 1: DEADLINE EXISTS =====/
    // If no deadline set for this requirement, categorize as 'No Deadline'
    if (empty($requirement['deadline'])) {
        return 'No Deadline';
    }

    // ===== CHECK 2: PARSE DEADLINE TIMESTAMP =====/
    // Convert deadline string to Unix timestamp for comparison
    $deadlineTs = strtotime($requirement['deadline']);
    
    // If parsing fails (malformed datetime), treat as 'No Deadline'
    if ($deadlineTs === false) {
        return 'No Deadline';
    }

    // Extract Unix timestamps from DateTime objects for comparison
    $todayStartTs = $todayStart->getTimestamp();
    $tomorrowStartTs = $tomorrowStart->getTimestamp();

    // ===== CHECK 3: CATEGORIZE BY DEADLINE =====/
    // Deadline is before today's start (00:00) → Overdue
    if ($deadlineTs < $todayStartTs) {
        return 'Overdue';
    }

    // Deadline is between today's start and tomorrow's start → Today
    if ($deadlineTs >= $todayStartTs && $deadlineTs < $tomorrowStartTs) {
        return 'Today';
    }

    // Deadline is tomorrow or later → Upcoming
    return 'Upcoming';
}

// ============================================================================
// FETCH REQUIREMENTS FROM DATABASE
// ============================================================================

/**
 * Multi-table query to fetch all active requirements with related data.
 * 
 * JOIN STRATEGY:
 * - event_requirements (er): Primary table, contains requirement assignments
 * - requirement_templates (rt): Defines requirement metadata (name, description, template)
 * - events (e): Contains event context and status
 * - event_dates (ed): Optional event schedule dates
 * - requirement_files (rf): Latest uploaded file for this requirement (if exists)
 * 
 * FILTERING:
 * - Only requirements from current user's events
 * - Excludes archived events
 * - Includes only "active" event statuses (Pending Review, Needs Revision, Approved)
 * - Optionally filters by specific event if $event_filter provided
 * 
 * ORDERING:
 * - First: Requirements without deadline/event date (sorted by creation)
 * - Then: Requirements with dates, sorted by deadline (earliest first)
 */
$fetchRequirementsSql = "
    SELECT
        er.event_req_id,
        rt.req_name,
        rt.req_desc,
        rt.template_url,
        er.submission_status,
        er.review_status,
        er.deadline,
        er.created_at,
        er.updated_at,

        rf.file_path,
        rf.original_file_name,
        rf.file_type,
        rf.uploaded_at,

        e.event_id,
        e.event_name,
        e.event_status,
        ed.start_datetime,
        ed.end_datetime
    FROM event_requirements er
    INNER JOIN requirement_templates rt
        ON er.req_template_id = rt.req_template_id
    INNER JOIN events e
        ON er.event_id = e.event_id
    LEFT JOIN event_dates ed
        ON e.event_id = ed.event_id
    LEFT JOIN requirement_files rf
        ON er.event_req_id = rf.event_req_id
       AND rf.is_current = 1
    WHERE e.user_id = ?
      AND e.archived_at IS NULL
      AND e.event_status IN ('Pending Review', 'Needs Revision', 'Approved')
";

// Initialize parameter placeholder and values array
$requirementParams = [$user_id];
$requirementTypes = "i";

// If specific event filter requested, add to WHERE clause
if ($event_filter) {
    $fetchRequirementsSql .= " AND e.event_id = ?";
    $requirementParams[] = $event_filter;
    $requirementTypes .= "i";
}

// Apply ordering: items without deadline last, then by deadline ASC (earliest first)
$fetchRequirementsSql .= "
    ORDER BY
        CASE WHEN er.deadline IS NULL AND ed.start_datetime IS NULL THEN 1 ELSE 0 END,
        COALESCE(er.deadline, ed.start_datetime) ASC,
        er.created_at ASC
";

// Execute parameterized query with dynamic type string and params
$requirementRows = fetchAll(
    $conn,
    $fetchRequirementsSql,
    $requirementTypes,
    $requirementParams
);

// Initialize array to accumulate transformed requirement records
$requirements = [];

// ============================================================================
// FETCH EVENTS FOR FILTER DROPDOWN
// ============================================================================

/**
 * Query to populate "Filter by Event" dropdown on page.
 * Shows all active events with clean formatting.
 * 
 * Ordering: Shows upcoming events first (by start date), then undated events
 */
$fetchFilterEventsSql = "
    SELECT
        e.event_id,
        e.event_name,
        e.event_status,
        ed.start_datetime
    FROM events e
    LEFT JOIN event_dates ed
        ON e.event_id = ed.event_id
    WHERE e.user_id = ?
      AND e.archived_at IS NULL
      AND e.event_status IN ('Pending Review', 'Needs Revision', 'Approved')
    ORDER BY
        CASE WHEN ed.start_datetime IS NULL THEN 1 ELSE 0 END,
        ed.start_datetime ASC,
        e.created_at ASC
";

$eventRows = fetchAll(
    $conn,
    $fetchFilterEventsSql,
    "i",
    [$user_id]
);

// ============================================================================
// TRANSFORM EVENT DATA INTO QUICK-ACCESS MAP
// ============================================================================

// Convert event rows into associative array indexed by event_id
// Used for easy lookup when displaying requirement event info
// Structure: events_map[event_id] = ['name' => string, 'status' => string, 'start' => string]
$events_map = [];
foreach ($eventRows as $row) {
    $events_map[$row['event_id']] = [
        'name' => $row['event_name'],
        'status' => $row['event_status'],
        'start' => $row['start_datetime']
    ];
}

// ============================================================================
// TRANSFORM REQUIREMENT ROWS INTO NORMALIZED ARRAY
// ============================================================================

/**
 * Convert database rows into normalized requirement objects.
 * 
 * Data transformation steps:
 * 1. Cast numeric IDs to integers (required for HTML attributes)
 * 2. Extract all fields from requirement/event/file rows
 * 3. Compute display_datetime using helper function
 * 4. Build flat array for easier iteration later
 */
foreach ($requirementRows as $row) {
    $requirements[] = [
        // Requirement identifiers
        'id' => (int) $row['event_req_id'],
        
        // Requirement template info (what is being required)
        'name' => $row['req_name'],
        'desc' => $row['req_desc'],
        'template_url' => $row['template_url'],
        
        // Uploaded file info (if any)
        'file_path' => $row['file_path'],
        'original_file_name' => $row['original_file_name'],
        'file_type' => $row['file_type'],
        'uploaded_at' => $row['uploaded_at'],
        
        // Requirement status and review stage
        'status' => $row['submission_status'],
        'review_status' => $row['review_status'],
        
        // Dates
        'deadline' => $row['deadline'],              // Requirement-specific deadline
        'created_at' => $row['created_at'],
        'updated_at' => $row['updated_at'],
        
        // Event context
        'event_id' => (int) $row['event_id'],
        'event_name' => $row['event_name'],
        'event_status' => $row['event_status'],
        'event_start' => $row['start_datetime'],
        'event_end' => $row['end_datetime'],
        
        // Computed display datetime (priority: deadline > event_start > null)
        'display_datetime' => getRequirementDisplayDate($row['deadline'] ?? null, $row['start_datetime'] ?? null)
    ];
}

// ============================================================================
// DATE GROUPING SETUP
// ============================================================================

/**
 * Create DateTime objects for today's start and tomorrow's start.
 * Used to categorize requirements into deadline groups (Overdue/Today/Upcoming).
 * 
 * Both set to 00:00:00 (midnight) for consistent day boundary comparison.
 * Timezone: Asia/Manila (set via date_default_timezone_set)
 */
$todayStart = new DateTime('today');      // Today at 00:00:00
$tomorrowStart = (clone $todayStart)->modify('+1 day');  // Tomorrow at 00:00:00

// ============================================================================
// GROUP REQUIREMENTS BY DEADLINE STATUS
// ============================================================================

/**
 * Organize flat requirement array into nested structure by deadline group.
 * 
 * Grouping structure:
 * grouped[group_name][event_id] = [
 *     'event_name' => string,
 *     'event_id' => int,
 *     'event_status' => string,
 *     'event_start' => string,
 *     'requirements' => [requirement_array, ...]
 * ]
 * 
 * Where group_name is one of: 'Overdue', 'Today', 'Upcoming', 'No Deadline'
 */
$grouped = [
    'Overdue' => [],      // Deadline is past (before today 00:00)
    'Today' => [],        // Deadline is today (today 00:00 - tomorrow 00:00)
    'Upcoming' => [],     // Deadline is future (tomorrow 00:00 or later)
    'No Deadline' => []   // No deadline computed for this requirement
];

// Iterate requirements and assign to groups by event
foreach ($requirements as $r) {
    // Determine which deadline group this requirement belongs to
    $groupName = getRequirementGroup($r, $todayStart, $tomorrowStart);
    $eventId = $r['event_id'];

    // Create event block if not already exists
    if (!isset($grouped[$groupName][$eventId])) {
        $grouped[$groupName][$eventId] = [
            'event_name' => $r['event_name'],
            'event_id' => $eventId,
            'event_status' => $r['event_status'],
            'event_start' => $r['event_start'],
            'requirements' => []
        ];
    }

    // Add requirement to its event block
    $grouped[$groupName][$eventId]['requirements'][] = $r;
}

// ============================================================================
// FORMAT GROUPS FOR DISPLAY
// ============================================================================

/**
 * Convert grouped array into display format with metadata.
 * Creates ordered list of group objects with title, subtitle, and items.
 * 
 * Each group contains:
 * - title: Group name (Overdue, Today, Upcoming, No Deadline)
 * - subtitle: Descriptive text for users
 * - items: Grouped and organized requirements
 */
$groups = [
    [
        'title' => 'Overdue',
        'subtitle' => 'Requirements with deadlines before today',
        'items' => $grouped['Overdue']
    ],
    [
        'title' => 'Today',
        'subtitle' => 'Requirements due today',
        'items' => $grouped['Today']
    ],
    [
        'title' => 'Upcoming',
        'subtitle' => 'Requirements due after today',
        'items' => $grouped['Upcoming']
    ],
    [
        'title' => 'No Deadline',
        'subtitle' => 'Requirements without a computed deadline',
        'items' => $grouped['No Deadline']
    ]
];

// ============================================================================
// CALCULATE OVERALL PROGRESS METRICS
// ============================================================================

/**
 * Compute completion statistics for progress bar display.
 * 
 * Metrics:
 * - total: Total number of requirements across all events
 * - uploaded: Count of requirements with status 'Uploaded'
 * - percent: Percentage (0-100) of requirements uploaded
 */
$total = count($requirements);                                          // Total requirement count

// Count only requirements with status 'Uploaded'
$uploaded = count(array_filter($requirements, fn($r) => ($r['status'] ?? '') === 'Uploaded'));

// Calculate percentage (show 0% if no requirements, else round to nearest integer)
$percent = $total ? round(($uploaded / $total) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Requirements</title>

    <link rel="stylesheet" href="<?= PUBLIC_URL ?>assets/styles/layout.css" />
    <link rel="stylesheet" href="<?= PUBLIC_URL ?>assets/styles/requirements.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>

<body>
    <div class="app">
        <?php include PUBLIC_PATH . 'assets/includes/general_nav.php' ?>

        <main class="main">
            <header class="topbar">
                <div class="title-wrap">
                    <h1>Requirements</h1>
                    <p>Keep track of your compliance progress.</p>
                </div>
            </header>

            <!-- ===== PROGRESS TRACKER ===== -->
            <!-- Card displaying overall completion progress -->
            <div class="progress-card enhanced">
                <div class="progress-header">
                    <h3><i class="fa-solid fa-chart-pie"></i> Completion Progress</h3>
                    <span class="progress-badge"><?= $percent ?>% Complete</span>
                </div>
                <div class="progress-stats">
                    <div class="progress-stat-item">
                        <span class="stat-label">Uploaded</span>
                        <span class="stat-number"><?= $uploaded ?></span>
                    </div>
                    <div class="progress-stat-item">
                        <span class="stat-label">Total</span>
                        <span class="stat-number"><?= $total ?></span>
                    </div>
                </div>
                <div class="progress-bar-container">
                    <div class="progress-bar">
                        <div class="progress-fill" style="width:<?= $percent ?>%; background: linear-gradient(90deg, #4b0014, #c2a14d);"></div>
                    </div>
                    <span class="progress-percentage"><?= $percent ?>%</span>
                </div>
            </div>

            <div class="filter-bar" id="req-deadlines">
                <form method="GET">
                    <label>Filter by Event:</label>
                    <select name="event_id" onchange="this.form.submit()">
                        <option value="">-- All Active Compliance Events --</option>
                        <?php foreach ($events_map as $id => $ev): ?>
                            <option value="<?= (int) $id ?>" <?= ($event_filter == $id) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($ev['name']) ?> - <?= htmlspecialchars($ev['status']) ?>
                                <?php if (!empty($ev['start'])): ?>
                                    (<?= date('M j, Y', strtotime($ev['start'])) ?>)
                                <?php else: ?>
                                    (No schedule)
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>

            <section class="content req-page">
                <?php foreach ($groups as $group): ?>
                    <div class="req-group">
                        <div class="req-head">
                            <div>
                                <h2><?= htmlspecialchars($group['title']) ?></h2>
                                <p><?= htmlspecialchars($group['subtitle']) ?></p>
                            </div>
                        </div>

                    <?php if (empty($group['items'])): ?>
                    <div class="empty-timeline">
                        <div class="empty-icon-small">
                            <?php if ($group['title'] === 'Yesterday'): ?>
                                <i class="fa-regular fa-face-smile"></i>
                            <?php elseif ($group['title'] === 'Today'): ?>
                                <i class="fa-regular fa-clock"></i>
                            <?php else: ?>
                                <i class="fa-regular fa-calendar"></i>
                            <?php endif; ?>
                        </div>
                        <div class="empty-content">
                            <h4>No requirements for <?= strtolower($group['title']) ?></h4>
                            <p>
                                <?php if ($group['title'] === 'Yesterday'): ?>
                                    You were all caught up yesterday!
                                <?php elseif ($group['title'] === 'Today'): ?>
                                    No requirements due today. Enjoy your day!
                                <?php else: ?>
                                    No requirements scheduled for tomorrow yet.
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                <?php endif; ?>

                        <?php foreach ($group['items'] as $event): ?>
                            <?php
                            usort($event['requirements'], function ($a, $b) {
                                $a_uploaded = (($a['status'] ?? '') === 'Uploaded');
                                $b_uploaded = (($b['status'] ?? '') === 'Uploaded');

                                if ($a_uploaded !== $b_uploaded) {
                                    return $a_uploaded <=> $b_uploaded;
                                }

                                $a_deadline = strtotime($a['display_datetime'] ?? '9999-12-31 23:59:59');
                                $b_deadline = strtotime($b['display_datetime'] ?? '9999-12-31 23:59:59');

                                return $a_deadline <=> $b_deadline;
                            });
                            ?>

                            <div class="event-block">
                                <?php foreach ($event['requirements'] as $req): ?>
                                    <?php
                                    $status = formatSubmissionStatus($req['status'] ?? 'Pending');
                                    $deadline_source = $req['display_datetime'];
                                    $deadline_ts = $deadline_source ? strtotime($deadline_source) : null;
                                    ?>

                                    <a class="req-card" href="view_event.php?id=<?= (int) $event['event_id'] ?>">
                                        <div class="req-item">
                                            <div class="req-title">
                                                <?= htmlspecialchars($req['name']) ?>

                                                <?php if (!empty($req['desc'])): ?>
                                                    <span class="tooltip-icon">
                                                        <i class="fa-regular fa-circle-question"></i>
                                                        <span class="tooltip-text">
                                                            <?= htmlspecialchars($req['desc']) ?>
                                                        </span>
                                                    </span>
                                                <?php endif; ?>
                                            </div>

                                            <div class="req-sub">
                                                From <?= htmlspecialchars($event['event_name']) ?>
                                                (<?= htmlspecialchars($event['event_status']) ?>)
                                            </div>

                                            <div class="req-sub">
                                                <?php if (!empty($req['deadline']) && $deadline_ts): ?>
                                                    Deadline:
                                                    <strong>
                                                        <time datetime="<?= htmlspecialchars($req['deadline']) ?>">
                                                            <?= date("M j, Y g:i A", $deadline_ts) ?>
                                                        </time>
                                                    </strong>
                                                <?php elseif ($deadline_ts): ?>
                                                    Event Date:
                                                    <strong>
                                                        <time datetime="<?= htmlspecialchars($deadline_source) ?>">
                                                            <?= date("M j, Y g:i A", $deadline_ts) ?>
                                                        </time>
                                                    </strong>
                                                <?php else: ?>
                                                    <strong>No schedule</strong>
                                                <?php endif; ?>
                                            </div>

                                            <?php if (!empty($req['template_url']) || !empty($req['file_path'])): ?>
                                                <div class="req-sub">
                                                    <?php if (!empty($req['template_url'])): ?>
                                                        <span>Template available</span>
                                                    <?php endif; ?>

                                                    <?php if (!empty($req['template_url']) && !empty($req['file_path'])): ?>
                                                        <span> • </span>
                                                    <?php endif; ?>

                                                    <?php if (!empty($req['file_path'])): ?>
                                                        <span>File uploaded</span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <span class="status <?= strtolower($status) ?>">
                                            <?= htmlspecialchars($status) ?>
                                        </span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </section>
        </main>
    </div>

    <script src="../app/script/requirements.js"></script>
</body>

</html>