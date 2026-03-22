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
    <img class="avatar" src="<?= PUBLIC_URL ?>assets/profiles/<?= $profile_pic ?>" alt="<?= $username ?>">

    <div class="brand">
        <img class="navbar-mark" src="<?= PUBLIC_URL ?>assets/images/haucredit_logo.png" alt="HAUCREDIT mark">

        <div class="brand-text">
            <div class="brand-name">HAUCREDIT</div>
            <div class="brand-subtitle">Compliance Tracker</div>
        </div>
    </div>

    <nav class="nav">

        <!-- Dashboard -->
        <a class="nav-item <?= ($current_page == 'home.php') ? 'active' : '' ?>" href="<?= USER_PAGE ?>home.php">
            <i class="fa-regular fa-house"></i>
            <span>Dashboard</span>
        </a>

        <!-- Create Event -->
        <a class="nav-item <?= ($current_page == 'create_event.php') ? 'active' : '' ?>" href="<?= USER_PAGE ?>create_event.php">
            <i class="fa-solid fa-plus"></i>
            <span>Create Event</span>
        </a>

        <!-- My Events — active on both my_events.php and view_event.php -->
        <a class="nav-item <?= in_array($current_page, ['my_events.php', 'view_event.php']) ? 'active' : '' ?>"
            href="<?= USER_PAGE ?>my_events.php">
            <i class="fa-regular fa-clipboard"></i>
            <span>My Events</span>
        </a>

        <!-- Calendar -->
        <a class="nav-item <?= ($current_page == 'calendar.php') ? 'active' : '' ?>" href="<?= USER_PAGE ?>calendar.php">
            <i class="fa-regular fa-calendar"></i>
            <span>Calendar</span>
        </a>

        <!-- Requirements -->
        <a class="nav-item <?= ($current_page == 'requirements.php') ? 'active' : '' ?>" href="<?= USER_PAGE ?>requirements.php">
            <i class="fa-solid fa-list-check"></i>
            <span>Requirements</span>
        </a>

        <!-- About -->
        <a class="nav-item <?= ($current_page == 'about.php') ? 'active' : '' ?>" href="<?= PUBLIC_URL ?>about.php">
            <i class="fa-regular fa-circle-question"></i>
            <span>About Us</span>
        </a>

        <!-- Account Section -->
        <div class="account">
            <?php
            if ($current_page === 'about.php' && $username === "") {
                echo '<button class="account-btn" type="button">
                        <i class="fa-regular fa-circle-user"></i>
                        <span style="text-align: center;">Please Sign In to Continue</span>
                      </button>';
            } else {
                echo '<a class="account-btn" href="profile.php">
                        <i class="fa-regular fa-circle-user"></i>
                        <span>My Account</span>
                      </a>';
            }
            ?>

            <form action="<?= PUBLIC_URL ?>logout.php" method="POST">
                <?php
                if ($current_page === 'about.php' && $username === "") {
                    echo '<button type="submit" class="logout-link">
                            <i class="fa-solid fa-right-to-bracket"></i>
                            <span>Sign In</span>
                          </button>';
                } else {
                    echo '<button type="submit" class="logout-link">
                            <i class="fa-solid fa-sign-out-alt"></i>
                            <span>Logout</span>
                          </button>';
                }
                ?>
            </form>
        </div>

    </nav>
</aside>