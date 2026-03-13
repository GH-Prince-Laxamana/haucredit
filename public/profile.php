<?php
session_start();
require_once "../app/database.php";
require_once "../app/security_headers.php";
send_security_headers();

if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION["user_id"];

/* FLASH MESSAGES */

$success = $_SESSION["success"] ?? "";
$error = $_SESSION["error"] ?? "";

unset($_SESSION["success"], $_SESSION["error"]);

/* GET USER */

$stmt = $conn->prepare("
SELECT user_name, user_email, org_body, profile_pic, user_password
FROM users
WHERE user_id=?
");

$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

/* PROFILE IMAGE */

if (isset($_POST["upload_photo"])) {

    if (isset($_FILES["profile_pic"]) && $_FILES["profile_pic"]["error"] === 0) {

        $allowed = ["jpg", "jpeg", "png", "webp"];
        $ext = strtolower(pathinfo($_FILES["profile_pic"]["name"], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed)) {
            $_SESSION["error"] = "Only JPG PNG WEBP allowed.";
            header("Location: profile.php");
            exit();
        }

        $newName = "user_" . $user_id . "_" . time() . "." . $ext;
        $target = "assets/profiles/" . $newName;

        if (move_uploaded_file($_FILES["profile_pic"]["tmp_name"], $target)) {

            $stmt = $conn->prepare("
                                    UPDATE users
                                    SET profile_pic=?
                                    WHERE user_id=?
                                    ");

            $stmt->bind_param("si", $newName, $user_id);
            $stmt->execute();

            $_SESSION["success"] = "Profile picture updated.";
            header("Location: profile.php");
            exit();

        }

    }
}

/* PASSWORD CHANGE */

if (isset($_POST["change_password"])) {

    $current = $_POST["current_password"] ?? "";
    $new = $_POST["new_password"] ?? "";
    $confirm = $_POST["confirm_password"] ?? "";

    if (!password_verify($current, $user["user_password"])) {
        $_SESSION["error"] = "Current password incorrect.";
        header("Location: profile.php");
        exit();
    }

    if ($new !== $confirm) {
        $_SESSION["error"] = "Passwords do not match.";
        header("Location: profile.php");
        exit();
    }

    if (strlen($new) < 8) {
        $_SESSION["error"] = "Password must be at least 8 characters.";
        header("Location: profile.php");
        exit();
    }

    $hash = password_hash($new, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("
                            UPDATE users
                            SET user_password=?
                            WHERE user_id=?
                            ");

    $stmt->bind_param("si", $hash, $user_id);
    $stmt->execute();

    $_SESSION["success"] = "Password changed successfully.";
    header("Location: profile.php");
    exit();

}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Profile Settings</title>

    <link rel="stylesheet" href="assets/styles/layout.css">
    <link rel="stylesheet" href="assets/styles/profile.css">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.css">

</head>

<body>

    <div class="sidebar-overlay"></div>

    <div class="app">

        <?php include 'assets/includes/general_nav.php' ?>

        <main class="main">

            <header class="topbar">
                <div class="title-wrap">
                    <h1>Profile Settings</h1>
                </div>
            </header>

            <section class="content profile-page">

                <!-- PROFILE CARD -->

                <aside class="profile-card">

                    <div class="profile-avatar-wrap">

                        <img class="profile-avatar"
                            src="assets/profiles/<?php echo htmlspecialchars($user['profile_pic'] ?? 'default.png'); ?>"
                            alt="Profile Picture">

                        <button class="camera-btn" type="button">
                            <img src="assets/images/camera.png" class="camera-icon">
                        </button>

                        <form method="post" enctype="multipart/form-data" id="photoForm">

                            <input type="file" name="profile_pic" id="photoInput" accept="image/*" hidden>

                            <button type="submit" name="upload_photo" hidden id="photoSubmit"></button>

                        </form>

                    </div>

                    <div class="profile-meta">

                        <div class="profile-name">
                            <?php echo htmlspecialchars($user['user_name']); ?>
                        </div>

                        <div class="profile-org">
                            <?php echo htmlspecialchars($user['org_body']); ?>
                        </div>

                    </div>

                </aside>


                <!-- IMAGE CROP MODAL -->

                <div id="cropModal" class="crop-modal">

                    <div class="crop-container">

                        <div class="crop-header">
                            <span>Edit Profile Photo</span>
                            <button id="cropClose">✕</button>
                        </div>

                        <div class="crop-body">
                            <img id="cropImage">
                        </div>

                        <div class="crop-actions">
                            <button class="crop-btn cancel" id="cropCancel">Cancel</button>
                            <button class="crop-btn save" id="cropSave">Apply</button>
                        </div>

                    </div>

                </div>


                <!-- RIGHT PANEL -->

                <section class="profile-panel">

                    <?php if ($success): ?>
                        <div class="alert success">✔ <?php echo $success ?></div>
                    <?php endif; ?>

                    <?php if ($error): ?>
                        <div class="alert error">⚠ <?php echo $error ?></div>
                    <?php endif; ?>


                    <div class="tabs">

                        <button class="tab active" id="editBtn">
                            Edit Profile
                        </button>

                        <button class="tab" id="passBtn">
                            Change Password
                        </button>

                    </div>


                    <!-- EDIT PROFILE -->

                    <div id="editTab" class="tab-content active">

                        <form class="profile-form">

                            <div class="grid-2">

                                <div class="form-field">
                                    <label>FIRST NAME</label>
                                    <input type="text" name="first_name">
                                </div>

                                <div class="form-field">
                                    <label>LAST NAME</label>
                                    <input type="text" name="last_name">
                                </div>

                                <div class="form-field">
                                    <label>MIDDLE NAME</label>
                                    <input type="text" name="middle_name">
                                </div>

                                <div class="form-field">
                                    <label>USERNAME</label>
                                    <input type="text" name="username"
                                        value="<?php echo htmlspecialchars($user['user_name']); ?>">
                                </div>

                            </div>

                            <div class="form-field full">
                                <label>EMAIL ADDRESS</label>
                                <input type="email" name="email"
                                    value="<?php echo htmlspecialchars($user['user_email']); ?>">
                            </div>

                            <div class="form-field full">
                                <label>ORGANIZING BODY</label>
                                <input type="text" name="org_body"
                                    value="<?php echo htmlspecialchars($user['org_body']); ?>">
                            </div>

                            <div class="form-field full">
                                <label>PHONE NUMBER</label>
                                <input type="tel" name="phone" placeholder="09XXXXXXXXX">
                            </div>

                        </form>

                    </div>


                    <!-- CHANGE PASSWORD -->

                    <div id="passTab" class="tab-content">

                        <p class="pw-subtext">
                            Update password for enhanced account security.
                        </p>

                        <form class="pw-form" method="post">

                            <div class="pw-field">

                                <label>CURRENT PASSWORD *</label>

                                <div class="pw-input">

                                    <input id="curpw" name="current_password" type="password" required>

                                    <button class="eye-btn" type="button" data-toggle="curpw">
                                        👁
                                    </button>

                                </div>

                            </div>


                            <div class="pw-field">

                                <label>NEW PASSWORD *</label>

                                <div class="pw-input">

                                    <input id="newpw" name="new_password" type="password" required>

                                    <button class="eye-btn" type="button" data-toggle="newpw">
                                        👁
                                    </button>

                                </div>

                            </div>


                            <div class="pw-field">

                                <label>CONFIRM NEW PASSWORD *</label>

                                <div class="pw-input">

                                    <input id="confpw" name="confirm_password" type="password" required>

                                    <button class="eye-btn" type="button" data-toggle="confpw">
                                        👁
                                    </button>

                                </div>

                            </div>


                            <div class="pw-actions">

                                <button class="pw-btn ghost" type="reset">
                                    Discard Changes
                                </button>

                                <button class="pw-btn primary" type="submit" name="change_password">
                                    Apply Changes
                                </button>

                            </div>

                        </form>

                    </div>

                </section>

            </section>

        </main>

    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.js"></script>
    <script src="assets/script/profile.js" defer></script>

</body>

</html>