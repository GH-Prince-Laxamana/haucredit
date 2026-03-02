<?php
date_default_timezone_set('Asia/Manila');

$today = new DateTime('today');
$yesterday = (clone $today)->modify('-1 day');
$tomorrow = (clone $today)->modify('+1 day');

$events = [
  ['id' => 1, 'name' => 'Event Name', 'desc' => 'Text Here', 'date' => $yesterday->format('Y-m-d'), 'done' => false],
  ['id' => 2, 'name' => 'Event Name', 'desc' => 'Text Here', 'date' => $today->format('Y-m-d'), 'done' => false],
  ['id' => 3, 'name' => 'Event Name', 'desc' => 'Text Here', 'date' => $tomorrow->format('Y-m-d'), 'done' => false],
];

function eventsForDate($events, $date)
{
  return array_values(array_filter($events, fn($e) => $e['date'] === $date));
}

$groups = [
  ['title' => 'Yesterday', 'date' => $yesterday, 'items' => eventsForDate($events, $yesterday->format('Y-m-d'))],
  ['title' => 'Today', 'date' => $today, 'items' => eventsForDate($events, $today->format('Y-m-d'))],
  ['title' => 'Tomorrow', 'date' => $tomorrow, 'items' => eventsForDate($events, $tomorrow->format('Y-m-d'))],
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Requirements</title>
  <link rel="stylesheet" href="assets/styles/layout.css" />
  <link rel="stylesheet" href="assets/styles/requirements.css" />
</head>
<body>

  <div class="app">

    <aside class="sidebar">
      <div class="brand">
        <div class="avatar"></div>
      </div>

      <nav class="nav">
        <a class="nav-item" href="index.html">Dashboard</a>
        <a class="nav-item" href="create-event.html">Create Event</a>
        <a class="nav-item" href="calendar.php">Calendar</a>
        <a class="nav-item active" href="requirements.php">Requirements</a>
      </nav>

      <div class="account">
        <button class="account-btn">
          <span class="user-dot"></span>
          <span>Account Name</span>
        </button>
      </div>
    </aside>

    <main class="main">
      <header class="topbar">
        <div class="title-wrap">
          <h1>Requirements</h1>
        </div>
      </header>

      <section class="content req-page">

        <?php foreach ($groups as $group): ?>
          <div class="req-group">
            <div class="req-head">
              <h2><?= $group['title'] ?></h2>
              <p><?= $group['date']->format('F j, Y') ?></p>
            </div>

            <?php foreach ($group['items'] as $event): ?>
              <label class="req-card">
                <input type="checkbox" class="req-checkbox" <?= $event['done'] ? 'checked' : '' ?>>

                <span class="checkmark"></span>

                <div class="req-text">
                  <div class="req-title"><?= htmlspecialchars($event['name']) ?></div>
                  <div class="req-sub"><?= htmlspecialchars($event['desc']) ?></div>
                </div>
              </label>
            <?php endforeach; ?>

          </div>
        <?php endforeach; ?>

      </section>
    </main>

  </div>
</body>

</html>