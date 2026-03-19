<?php
session_start();
require_once "../app/database.php";

date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit();
}

$user_id = (int) $_SESSION["user_id"];
$event_filter = isset($_GET['event_id']) && $_GET['event_id'] !== '' ? (int) $_GET['event_id'] : null;

/* ================= LOAD REQUIREMENTS FROM DATABASE =================
   Uses:
   - event_requirements
   - requirement_templates
   - requirement_files (current file only)
   - events
   - event_dates
*/
$sql = "
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
";

if ($event_filter) {
    $sql .= " AND e.event_id = ?";
}

$sql .= "
    ORDER BY
        CASE WHEN ed.start_datetime IS NULL THEN 1 ELSE 0 END,
        ed.start_datetime ASC,
        er.created_at ASC
";

$stmt = $conn->prepare($sql);

if ($event_filter) {
    $stmt->bind_param("ii", $user_id, $event_filter);
} else {
    $stmt->bind_param("i", $user_id);
}

$stmt->execute();
$result = $stmt->get_result();

$requirements = [];

/* ================= LOAD EVENTS FOR FILTER DROPDOWN =================
   Uses:
   - events
   - event_dates
*/
$events_map = [];

$ev_stmt = $conn->prepare("
    SELECT
        e.event_id,
        e.event_name,
        ed.start_datetime
    FROM events e
    LEFT JOIN event_dates ed
        ON e.event_id = ed.event_id
    WHERE e.user_id = ?
      AND e.archived_at IS NULL
    ORDER BY
        CASE WHEN ed.start_datetime IS NULL THEN 1 ELSE 0 END,
        ed.start_datetime ASC,
        e.created_at ASC
");
$ev_stmt->bind_param("i", $user_id);
$ev_stmt->execute();
$ev_res = $ev_stmt->get_result();

while ($row = $ev_res->fetch_assoc()) {
    $events_map[$row['event_id']] = [
        'name' => $row['event_name'],
        'start' => $row['start_datetime']
    ];
}

while ($row = $result->fetch_assoc()) {
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
        'event_start' => $row['start_datetime'],
        'event_end' => $row['end_datetime'],
    ];
}

/* ================= DATE GROUPING SETUP ================= */
$today = new DateTime('today');
$yesterday = (clone $today)->modify('-1 day');
$tomorrow = (clone $today)->modify('+1 day');

/* ================= FUNCTION TO GROUP REQUIREMENTS BY EVENT FOR A DATE ================= */
function eventsForDate(array $requirements, string $date): array
{
    $filtered = array_filter($requirements, function ($r) use ($date) {
        if (empty($r['event_start'])) {
            return false;
        }
        return substr($r['event_start'], 0, 10) === $date;
    });

    $events = [];

    foreach ($filtered as $r) {
        $eventId = $r['event_id'];

        if (!isset($events[$eventId])) {
            $events[$eventId] = [
                'event_name' => $r['event_name'],
                'event_id' => $eventId,
                'event_start' => $r['event_start'],
                'requirements' => []
            ];
        }

        $events[$eventId]['requirements'][] = $r;
    }

    return $events;
}

/* ================= CREATE DATE GROUPS ================= */
$groups = [
    [
        'title' => 'Yesterday',
        'date' => $yesterday,
        'items' => eventsForDate($requirements, $yesterday->format('Y-m-d'))
    ],
    [
        'title' => 'Today',
        'date' => $today,
        'items' => eventsForDate($requirements, $today->format('Y-m-d'))
    ],
    [
        'title' => 'Tomorrow',
        'date' => $tomorrow,
        'items' => eventsForDate($requirements, $tomorrow->format('Y-m-d'))
    ]
];

/* ================= PROGRESS CALCULATION ================= */
$total = count($requirements);
$uploaded = count(array_filter($requirements, fn($r) => $r['status'] === 'uploaded'));
$percent = $total ? round(($uploaded / $total) * 100) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Requirements</title>

    <link rel="stylesheet" href="assets/styles/layout.css">
    <link rel="stylesheet" href="assets/styles/requirements.css">
</head>

<body>
    <div class="app">
        <?php include "assets/includes/general_nav.php"; ?>

        <main class="main">
            <header class="topbar">
                <div class="title-wrap">
                    <h1>Requirements</h1>
                    <p>Keep track of your compliance progress.</p>
                </div>
            </header>

            <div class="progress-card">
                <h3>Completion Progress</h3>
                <p><?= $uploaded ?> / <?= $total ?> Uploaded</p>
                <div class="progress-bar">
                    <div class="progress-fill" style="width:<?= $percent ?>%"></div>
                    <p><?= $percent ?>%</p>
                </div>
            </div>

            <div class="filter-bar" id="req-deadlines">
                <form method="GET">
                    <label>Filter by Event:</label>
                    <select name="event_id" onchange="this.form.submit()">
                        <option value="">-- All Events --</option>
                        <?php foreach ($events_map as $id => $ev): ?>
                            <option value="<?= (int) $id ?>" <?= ($event_filter == $id) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($ev['name']) ?>
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
                            <h2><?= htmlspecialchars($group['title']) ?></h2>
                            <p><?= $group['date']->format('F j, Y') ?></p>
                        </div>

                        <?php if (empty($group['items'])): ?>
                            <p class="empty-msg">No requirements for this day.</p>
                        <?php endif; ?>

                        <?php foreach ($group['items'] as $event):
                            usort($event['requirements'], function ($a, $b) {
                                $a_uploaded = ($a['status'] === 'uploaded');
                                $b_uploaded = ($b['status'] === 'uploaded');

                                if ($a_uploaded !== $b_uploaded) {
                                    return $a_uploaded <=> $b_uploaded;
                                }

                                $a_deadline = strtotime($a['deadline'] ?? $a['event_start'] ?? '9999-12-31 23:59:59');
                                $b_deadline = strtotime($b['deadline'] ?? $b['event_start'] ?? '9999-12-31 23:59:59');

                                return $a_deadline <=> $b_deadline;
                            });
                        ?>
                            <div class="event-block">
                                <?php foreach ($event['requirements'] as $req):
                                    $status = ($req['status'] === 'uploaded') ? 'Uploaded' : 'Pending';
                                    $deadline_source = $req['deadline'] ?: $req['event_start'];
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
                                                From <?= htmlspecialchars($event['event_name']) ?>:
                                                <?php if ($deadline_ts): ?>
                                                    <strong>
                                                        <time datetime="<?= htmlspecialchars($deadline_source) ?>">
                                                            <?= date("g:i A", $deadline_ts) ?>
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
                                            <?= $status ?>
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