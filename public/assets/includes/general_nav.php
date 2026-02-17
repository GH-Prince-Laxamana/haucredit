<?php
$username = htmlspecialchars($_SESSION["username"], ENT_QUOTES, "UTF-8");

$current_page = basename($_SERVER['PHP_SELF']);
?>

<aside class="sidebar">
    <div class="brand">
        <div class="avatar" aria-hidden="true"></div>
        <div class="brand-name">HAUCREDIT</div>
        <div class="brand-subtitle">Compliance Tracker</div>
    </div>

    <nav class="nav">
        <a class="nav-item <?= ($current_page == 'home.php') ? 'active' : '' ?>" href="home.php">
            <span class="icon" aria-hidden="true">
                <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24">
                    <path
                        d="M3 13h1v7c0 1.103.897 2 2 2h12c1.103 0 2-.897 2-2v-7h1a1 1 0 0 0 .707-1.707l-9-9a.999.999 0 0 0-1.414 0l-9 9A1 1 0 0 0 3 13zm7 7v-5h4v5h-4zm2-15.586 6 6V15l.001 5H16v-5c0-1.103-.897-2-2-2h-4c-1.103 0-2 .897-2 2v5H6v-9.586l6-6z" />
                </svg>
            </span>
            <span>Dashboard</span>
        </a>

        <a class="nav-item <?= ($current_page == 'create_event.php') ? 'active' : '' ?>" href="create_event.php">
            <span class="icon" aria-hidden="true">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
            </span>
            <span>Create Event</span>
        </a>

        <a class="nav-item <?= ($current_page == 'calendar.php') ? 'active' : '' ?>" href="calendar.php">
            <span class="icon" aria-hidden="true">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
            </span>
            <span>Calendar</span>
        </a>

        <a class="nav-item <?= ($current_page == 'about.php') ? 'active' : '' ?>" href="about.php">
            <span class="icon" aria-hidden="true">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </span>
            <span>About Us</span>
        </a>

        <div class="account">
            <button class="account-btn" type="button">
                <span class="user-dot" aria-hidden="true"></span>
                <span>
                    <?= $username ?>
                </span>
            </button>
            <a href="logout.php" class="logout-link">Logout</a>
        </div>
    </nav>
</aside>