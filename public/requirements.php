<?php
session_start();
require_once "../app/database.php";

date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION["user_id"];

/* ================= GET FILTER ================= */
$event_filter = $_GET['event_id'] ?? null;

/* ================= LOAD REQUIREMENTS ================= */
$sql = "
SELECT r.req_id, r.req_name, r.req_desc, r.file_path, r.doc_status, r.created_at,
       e.event_id, e.event_name, e.start_datetime
FROM requirements r
JOIN events e ON r.event_id = e.event_id
WHERE e.user_id = ?
AND e.archived_at IS NULL
";

if ($event_filter) {
    $sql .= " AND e.event_id = ?";
}

$sql .= " ORDER BY e.start_datetime ASC, r.created_at ASC";

$stmt = $conn->prepare($sql);

if ($event_filter) {
    $stmt->bind_param("ii", $user_id, $event_filter);
} else {
    $stmt->bind_param("i", $user_id);
}

$stmt->execute();
$result = $stmt->get_result();

$requirements = [];
/* ================= LOAD EVENTS FOR FILTER ================= */

$events_map = [];

$ev_stmt = $conn->prepare("
    SELECT event_id, event_name, start_datetime
    FROM events
    WHERE user_id = ?
    AND archived_at IS NULL
    ORDER BY start_datetime ASC
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
        'id' => $row['req_id'],
        'name' => $row['req_name'],
        'desc' => $row['req_desc'],
        'file_path' => $row['file_path'],
        'status' => $row['doc_status'],
        'created_at' => $row['created_at'],
        'event_id' => $row['event_id'],
        'event_name' => $row['event_name'],
        'event_start' => $row['start_datetime'],
    ];
}

/* ================= DATE GROUPING ================= */

$today = new DateTime('today');
$yesterday = (clone $today)->modify('-1 day');
$tomorrow = (clone $today)->modify('+1 day');

function eventsForDate($requirements, $date)
{
    $filtered = array_filter($requirements, fn($r) => substr($r['event_start'], 0, 10) === $date);

    $events = [];

    foreach ($filtered as $r) {
        $eventId = $r['event_id'];

        if (!isset($events[$eventId])) {
            $events[$eventId] = [
                'event_name' => $r['event_name'],
                'event_id' => $eventId,
                'requirements' => []
            ];
        }

        $events[$eventId]['requirements'][] = $r;
    }

    return $events;
}

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
                    <p>Track compliance progress</p>
                </div>
            </header>

            <!-- PROGRESS TRACKER -->

            <div class="progress-card">

                <h3>Completion Progress</h3>

                <p><?= $uploaded ?> / <?= $total ?> Uploaded</p>

                <div class="progress-bar">
                    <div class="progress-fill" style="width:<?= $percent ?>%"></div>
                    <p><?= $percent ?>%</p>
                </div>



            </div>


            <!-- EVENT FILTER -->

            <div class="filter-bar">

                <form method="GET">

                    <label>Filter by Event:</label>

                    <select name="event_id" onchange="this.form.submit()">

                        <option value="">-- All Events --</option>

                        <?php foreach ($events_map as $id => $ev): ?>

                            <option value="<?= $id ?>" <?= ($event_filter == $id) ? 'selected' : '' ?>>

                                <?= htmlspecialchars($ev['name']) ?> (<?= date('M j, Y', strtotime($ev['start'])) ?>)

                            </option>

                        <?php endforeach; ?>

                    </select>

                </form>

            </div>


            <!-- REQUIREMENTS LIST -->

            <section class="content req-page">

                <?php foreach ($groups as $group): ?>

                    <div class="req-group">

                        <div class="req-head">
                            <h2><?= $group['title'] ?></h2>
                            <p><?= $group['date']->format('F j, Y') ?></p>
                        </div>

                        <?php if (empty($group['items'])): ?>
                            <p class="empty-msg">No requirements for this day.</p>
                        <?php endif; ?>

                        <?php foreach ($group['items'] as $event):
                            usort($event['requirements'], function ($a, $b) {
                                return ($a['status'] === 'uploaded') <=> ($b['status'] === 'uploaded');
                            }); ?>


                            <div class="event-block">
                                <?php
                                $deadlines = array_map(fn($r) => strtotime($r['deadline'] ?? $r['event_start']), $event['requirements']);
                                $earliest = !empty($deadlines) ? min($deadlines) : strtotime($event['event_start']);

                                $diff = $earliest - time();
                                $days = floor($diff / 86400);
                                $hours = floor(($diff % 86400) / 3600);

                                $time_left = [];
                                if ($days > 0)
                                    $time_left[] = "$days " . ($days == 1 ? "day" : "days");
                                if ($hours > 0)
                                    $time_left[] = "$hours " . ($hours == 1 ? "hour" : "hours");

                                $time_left_str = !empty($time_left) ? implode(", ", $time_left) . " left" : "Due soon";
                                ?>

                                <div class="event-header">

                                    <h3>
                                        <?= htmlspecialchars($event['event_name']) ?>:
                                        <span class="deadline-text">
                                            <time datetime="<?= date('c', $earliest) ?>">
                                                 <?= date("g:i A", $earliest) ?>
                                            </time>
                                        </span>
                                    </h3>

                                    <a class="btn-secondary" href="view_event.php?id=<?= $event['event_id'] ?>">
                                        View Event
                                    </a>
                                </div>



                                <?php foreach ($event['requirements'] as $req):

                                    $status = ($req['status'] === 'uploaded') ? 'Uploaded' : 'Pending';
                                    $deadline = strtotime($req['deadline'] ?? $req['event_start']); // fallback to event_start if no specific deadline
                                    $diff = $deadline - time();

                                    $days = floor($diff / 86400);
                                    $hours = floor(($diff % 86400) / 3600);

                                    $time_left = [];
                                    if ($days > 0)
                                        $time_left[] = "$days " . ($days == 1 ? "day" : "days");
                                    if ($hours > 0)
                                        $time_left[] = "$hours " . ($hours == 1 ? "hour" : "hours");

                                    $time_left_str = !empty($time_left) ? implode(", ", $time_left) . " left" : "Due soon";

                                    ?>

                                    <a class="req-card" href="view_event.php?id=<?= $event['event_id'] ?>">

                                        <div>
                                            <div class="req-title">
                                                <?= htmlspecialchars($req['name']) ?>
                                            </div>

                                            <div class="req-sub">
                                                <?= htmlspecialchars($req['desc']) ?>
                                            </div>
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

    <script src="assets/js/requirements.js"></script>

</body>

</html>