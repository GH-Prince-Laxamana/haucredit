<?php
session_start();
require_once "../app/database.php";

date_default_timezone_set('Asia/Manila');

$user_id = $_SESSION['user_id'] ?? 1;

/* ================= GET FILTER ================= */
$event_filter = $_GET['event_id'] ?? null;

/* ================= LOAD REQUIREMENTS ================= */
$sql = "
SELECT r.req_id, r.req_name, r.req_desc, r.file_path, r.doc_status, r.created_at,
       e.event_id, e.event_name, e.start_datetime
FROM requirements r
JOIN events e ON r.event_id = e.event_id
WHERE e.user_id = ?
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
$events_map = []; // to store event info for links

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

    $events_map[$row['event_id']] = [
        'name' => $row['event_name'],
        'start' => $row['start_datetime']
    ];
}

/* ================= DATE GROUPING ================= */
$today = new DateTime('today');
$yesterday = (clone $today)->modify('-1 day');
$tomorrow = (clone $today)->modify('+1 day');

function eventsForDate($requirements, $date)
{
    return array_values(array_filter($requirements, fn($r) => substr($r['event_start'], 0, 10) === $date));
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
$completed = count(array_filter($requirements, fn($r) => $r['status'] === 'approved'));
$percent = $total ? round(($completed / $total) * 100) : 0;
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

            <!-- ================= PROGRESS TRACKER ================= -->
            <div class="progress-card">
                <h3>Completion Progress</h3>
                <p><?= $completed ?> / <?= $total ?> Completed</p>
                <div class="progress-bar">
                    <div class="progress-fill" style="width:<?= $percent ?>%"></div>
                </div>
                <p><?= $percent ?>%</p>
            </div>

            <!-- ================= EVENT FILTER ================= -->
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

            <!-- ================= REQUIREMENTS LIST ================= -->
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

                        <?php foreach ($group['items'] as $req): ?>
                            <div class="req-card <?= $req['status'] === 'approved' ? 'completed' : '' ?>">
                                <div class="req-text">
                                    <div class="req-title">
                                        <?= htmlspecialchars($req['name']) ?>
                                    </div>
                                    <div class="req-sub"><?= htmlspecialchars($req['desc']) ?></div>
                                    <div class="req-meta">
                                        <span class="status <?= $req['status'] ?>"><?= ucfirst($req['status']) ?></span>
                                        <span class="event-link">
                                            <a href="view_event.php?event_id=<?= $req['event_id'] ?>">View Event</a>
                                        </span>
                                    </div>
                                    <?php if ($req['file_path']): ?>
                                        <div class="req-file">
                                            <a href="<?= htmlspecialchars($req['file_path']) ?>" target="_blank">View File</a>
                                        </div>
                                    <?php endif; ?>
                                </div>
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