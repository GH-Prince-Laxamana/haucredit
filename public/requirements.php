<?php
session_start();
require_once "../app/database.php";

date_default_timezone_set('Asia/Manila');

$user_id = $_SESSION['user_id'] ?? 1;


/* ================= LOAD REQUIREMENTS ================= */

$stmt = $conn->prepare("
SELECT req_id, req_name, req_desc, req_date, req_done
FROM requirements
WHERE user_id = ?
ORDER BY req_date ASC
");

$stmt->bind_param("i",$user_id);
$stmt->execute();
$result = $stmt->get_result();

$events = [];

while($row = $result->fetch_assoc()){

$events[] = [
'id'=>$row['req_id'],
'name'=>$row['req_name'],
'desc'=>$row['req_desc'],
'date'=>$row['req_date'],
'done'=>$row['req_done']
];

}


/* ================= DATE GROUPING ================= */

$today = new DateTime('today');
$yesterday = (clone $today)->modify('-1 day');
$tomorrow = (clone $today)->modify('+1 day');

function eventsForDate($events,$date){

return array_values(
array_filter($events, fn($e)=>$e['date'] === $date)
);

}

$groups = [

[
'title'=>'Yesterday',
'date'=>$yesterday,
'items'=>eventsForDate($events,$yesterday->format('Y-m-d'))
],

[
'title'=>'Today',
'date'=>$today,
'items'=>eventsForDate($events,$today->format('Y-m-d'))
],

[
'title'=>'Tomorrow',
'date'=>$tomorrow,
'items'=>eventsForDate($events,$tomorrow->format('Y-m-d'))
]

];


/* ================= PROGRESS CALCULATION ================= */

$total = count($events);
$completed = count(array_filter($events, fn($e)=>$e['done']));
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

<!-- SIDEBAR -->
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


<!-- ================= REQUIREMENTS LIST ================= -->

<section class="content req-page">

<?php foreach ($groups as $group): ?>

<div class="req-group">

<div class="req-head">
<h2><?= $group['title'] ?></h2>
<p><?= $group['date']->format('F j, Y') ?></p>
</div>


<?php if(empty($group['items'])): ?>

<p style="opacity:.6;font-size:13px;margin-top:8px;">
No requirements for this day.
</p>

<?php endif; ?>


<?php foreach ($group['items'] as $event): ?>

<label class="req-card">

<input
type="checkbox"
class="req-checkbox"
data-id="<?= $event['id'] ?>"
<?= $event['done'] ? 'checked' : '' ?>
>

<span class="checkmark"></span>

<div class="req-text">

<div class="req-title">
<?= htmlspecialchars($event['name']) ?>
</div>

<div class="req-sub">
<?= htmlspecialchars($event['desc']) ?>
</div>

</div>

</label>

<?php endforeach; ?>

</div>

<?php endforeach; ?>

</section>

</main>

</div>


<!-- REQUIREMENTS JS -->
<script src="assets/js/requirements.js"></script>

</body>
</html>