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
    ";

    $user_profile = fetchOne(
        $conn,
        $fetchUserProfilePictureSql,
        "i",
        [$user_id]
    );

    $profile_pic = $user_profile['profile_pic'] ?? "default.jpg";
}
?>

<head>
    <script src="https://kit.fontawesome.com/1f718fe609.js" crossorigin="anonymous"></script>
</head>

<aside class="sidebar">
    <img class="avatar" src="assets/profiles/<?= $profile_pic ?>" alt="<?= $username ?>">

    <div class="brand">
        <img class="navbar-mark" src="assets/images/FavLogo.png" alt="HAUCREDIT mark">

        <div class="brand-text">
            <div class="brand-name">HAUCREDIT</div>
            <div class="brand-subtitle">Compliance Tracker</div>
        </div>
    </div>

    <nav class="nav">

        <!-- Dashboard -->
        <a class="nav-item <?= ($current_page == 'home.php') ? 'active' : '' ?>" href="home.php">
            <span><i class="fa-regular fa-house"></i> Dashboard</span>
        </a>

        <!-- Create Event -->
        <a class="nav-item <?= ($current_page == 'create_event.php') ? 'active' : '' ?>" href="create_event.php">
            <span><i class="fa-solid fa-plus"></i> Create Event</span>
        </a>

        <!-- My Events — active on both my_events.php and view_event.php -->
        <a class="nav-item <?= in_array($current_page, ['my_events.php', 'view_event.php']) ? 'active' : '' ?>"
            href="my_events.php">
            <span><i class="fa-regular fa-clipboard"></i> My Events</span>
        </a>

        <!-- Calendar -->
        <a class="nav-item <?= ($current_page == 'calendar.php') ? 'active' : '' ?>" href="calendar.php">
            <span><i class="fa-regular fa-calendar"></i> Calendar</span>
        </a>

        <!-- Requirements -->
        <a class="nav-item <?= ($current_page == 'requirements.php') ? 'active' : '' ?>" href="requirements.php">
            <span><i class="fa-solid fa-list-check"></i> Requirements</span>
        </a>

        <!-- About -->
        <a class="nav-item <?= ($current_page == 'about.php') ? 'active' : '' ?>" href="about.php">
            <span><i class="fa-regular fa-circle-question"></i> About Us</span>
        </a>

        <!-- Account -->
        <div class="account">

            <?php
            if ($current_page === 'about.php' && $username === "") {
                echo '<button class="account-btn" type="button">
                            <span style="text-align: center; margin: auto;"> Please Sign In to Continue </span>
                        </button>';
            } else {
                echo '<a class="account-btn" href="profile.php">
                            <span>My Account</span>
                        </a>';
            }
            ?>

            <form action="logout.php" method="POST">
                <?php
                if ($current_page === 'about.php' && $username === "") {
                    echo '<button type="submit" class="logout-link">Sign In</button>';
                } else {
                    echo '<button type="submit" class="logout-link">Logout</button>';
                }
                ?>
            </form>

        </div>

    </nav>
</aside>