<?php
$user_id = $_SESSION["user_id"] ?? null;
$username = htmlspecialchars((string) ($_SESSION["username"] ?? ""), ENT_QUOTES, "UTF-8");
$current_page = basename($_SERVER['PHP_SELF']);

$profile_pic = "default.jpg";

if ($user_id) {
    $fetchUserProfilePictureSql = "
        SELECT profile_pic
        FROM users
        WHERE user_id = ?
        LIMIT 1
    ";

    $user_profile = fetchOne(
        $conn,
        $fetchUserProfilePictureSql,
        "i",
        [$user_id]
    );

    $profile_pic = $user_profile['profile_pic'] ?? "default.jpg";
}

/* ================= ACTIVE NAV HELPERS ================= */
$dashboard_pages = ['admin_dashboard.php'];
$event_pages = ['admin_events.php', 'admin_manage_event.php', 'admin_review_narrative.php'];
$user_pages = ['admin_users.php'];
$config_pages = ['admin_configurations.php'];

function isActiveNav(string $current_page, array $pages): string
{
    return in_array($current_page, $pages, true) ? 'active' : '';
}
?>

<head>
    <script src="https://kit.fontawesome.com/1f718fe609.js" crossorigin="anonymous"></script>
</head>

<aside class="sidebar">
    <img class="avatar" src="<?= PUBLIC_URL ?>assets/profiles/<?= htmlspecialchars($profile_pic) ?>" alt="<?= $username ?>">

    <div class="brand">
        <img class="navbar-mark" src="<?= PUBLIC_URL ?>assets/images/haucredit_logo.png" alt="HAUCREDIT mark">

        <div class="brand-text">
            <div class="brand-name">HAUCREDIT</div>
            <div class="brand-subtitle">Admin Panel</div>
        </div>
    </div>

    <nav class="nav">
        <!-- Dashboard -->
        <a class="nav-item <?= isActiveNav($current_page, $dashboard_pages) ?>" href="<?= ADMIN_PAGE ?>admin_dashboard.php">
            <span><i class="fa-solid fa-chart-line"></i> Dashboard</span>
        </a>

        <!-- Event Management -->
        <a class="nav-item <?= isActiveNav($current_page, $event_pages) ?>" href="<?= ADMIN_PAGE ?>admin_events.php">
            <span><i class="fa-solid fa-calendar-check"></i> Event Management</span>
        </a>

        <!-- Users -->
        <a class="nav-item <?= isActiveNav($current_page, $user_pages) ?>" href="<?= ADMIN_PAGE ?>admin_users.php">
            <span><i class="fa-solid fa-users"></i> Users</span>
        </a>

        <!-- Configurations -->
        <a class="nav-item <?= isActiveNav($current_page, $config_pages) ?>" href="<?= ADMIN_PAGE ?>admin_configurations.php">
            <span><i class="fa-solid fa-sliders"></i> Configurations</span>
        </a>

        <!-- Account -->
        <div class="account">
            <a class="account-btn" href="<?= USER_PAGE ?>profile.php">
                <span><i class="fa-regular fa-user"></i> My Account</span>
            </a>

            <form action="<?= PUBLIC_URL ?>logout.php" method="POST">
                <button type="submit" class="logout-link">Logout</button>
            </form>
        </div>
    </nav>
</aside>