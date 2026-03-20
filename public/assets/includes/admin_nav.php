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
        <a class="nav-item <?= ($current_page == 'admin_dashboard.php') ? 'active' : '' ?>" href="admin_dashboard.php">
            <span><i class="fa-regular fa-house"></i> Admin Dashboard</span>
        </a>

        <a class="nav-item <?= ($current_page == 'admin_events.php') ? 'active' : '' ?>" href="admin_events.php">
            <span><i class="fa-regular fa-house"></i> Admin Events</span>
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