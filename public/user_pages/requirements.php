<?php
session_start();
require_once __DIR__ . '/../../app/database.php';

date_default_timezone_set('Asia/Manila');

requireLogin();

$user_id = (int) $_SESSION["user_id"];
$event_filter = isset($_GET['event_id']) && $_GET['event_id'] !== '' ? (int) $_GET['event_id'] : null;

/* ================= HELPERS ================= */
function getRequirementDisplayDate(?string $deadline, ?string $event_start): ?string
{
    if (!empty($deadline)) {
        return $deadline;
    }

    if (!empty($event_start)) {
        return $event_start;
    }

    return null;
}

function formatSubmissionStatus(string $status): string
{
    return match ($status) {
        'Uploaded' => 'Uploaded',
        'Pending' => 'Pending',
        default => $status
    };
}

function getRequirementGroup(array $requirement, DateTime $todayStart, DateTime $tomorrowStart): string
{
    if (empty($requirement['deadline'])) {
        return 'No Deadline';
    }

    $deadlineTs = strtotime($requirement['deadline']);
    if ($deadlineTs === false) {
        return 'No Deadline';
    }

    $todayStartTs = $todayStart->getTimestamp();
    $tomorrowStartTs = $tomorrowStart->getTimestamp();

    if ($deadlineTs < $todayStartTs) {
        return 'Overdue';
    }

    if ($deadlineTs >= $todayStartTs && $deadlineTs < $tomorrowStartTs) {
        return 'Today';
    }

    return 'Upcoming';
}

/* ================= LOAD REQUIREMENTS FROM DATABASE ================= */
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

$requirementParams = [$user_id];
$requirementTypes = "i";

if ($event_filter) {
    $fetchRequirementsSql .= " AND e.event_id = ?";
    $requirementParams[] = $event_filter;
    $requirementTypes .= "i";
}

$fetchRequirementsSql .= "
    ORDER BY
        CASE WHEN er.deadline IS NULL AND ed.start_datetime IS NULL THEN 1 ELSE 0 END,
        COALESCE(er.deadline, ed.start_datetime) ASC,
        er.created_at ASC
";

$requirementRows = fetchAll(
    $conn,
    $fetchRequirementsSql,
    $requirementTypes,
    $requirementParams
);

$requirements = [];

/* ================= LOAD EVENTS FOR FILTER DROPDOWN ================= */
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

$events_map = [];
foreach ($eventRows as $row) {
    $events_map[$row['event_id']] = [
        'name' => $row['event_name'],
        'status' => $row['event_status'],
        'start' => $row['start_datetime']
    ];
}

foreach ($requirementRows as $row) {
    $requirements[] = [
        'id' => (int) $row['event_req_id'],
        'name' => $row['req_name'],
        'desc' => $row['req_desc'],
        'template_url' => $row['template_url'],
        'file_path' => $row['file_path'],
        'original_file_name' => $row['original_file_name'],
        'file_type' => $row['file_type'],
        'uploaded_at' => $row['uploaded_at'],
        'status' => $row['submission_status'],
        'review_status' => $row['review_status'],
        'deadline' => $row['deadline'],
        'created_at' => $row['created_at'],
        'updated_at' => $row['updated_at'],
        'event_id' => (int) $row['event_id'],
        'event_name' => $row['event_name'],
        'event_status' => $row['event_status'],
        'event_start' => $row['start_datetime'],
        'event_end' => $row['end_datetime'],
        'display_datetime' => getRequirementDisplayDate($row['deadline'] ?? null, $row['start_datetime'] ?? null)
    ];
}

/* ================= DATE GROUPING SETUP ================= */
$todayStart = new DateTime('today');
$tomorrowStart = (clone $todayStart)->modify('+1 day');

/* ================= GROUP REQUIREMENTS ================= */
$grouped = [
    'Overdue' => [],
    'Today' => [],
    'Upcoming' => [],
    'No Deadline' => []
];

foreach ($requirements as $r) {
    $groupName = getRequirementGroup($r, $todayStart, $tomorrowStart);
    $eventId = $r['event_id'];

    if (!isset($grouped[$groupName][$eventId])) {
        $grouped[$groupName][$eventId] = [
            'event_name' => $r['event_name'],
            'event_id' => $eventId,
            'event_status' => $r['event_status'],
            'event_start' => $r['event_start'],
            'requirements' => []
        ];
    }

    $grouped[$groupName][$eventId]['requirements'][] = $r;
}

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

/* ================= PROGRESS CALCULATION ================= */
$total = count($requirements);
$uploaded = count(array_filter($requirements, fn($r) => ($r['status'] ?? '') === 'Uploaded'));
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