<?php
session_start();
require_once __DIR__ . '/../../app/database.php';

requireLogin();

$user_id = (int) $_SESSION["user_id"];

// ===== FLASH MESSAGES =====
$success = $_SESSION["success"] ?? "";
$error = $_SESSION["error"] ?? "";

unset($_SESSION["success"], $_SESSION["error"]);

// ===== LOAD ORGANIZATION OPTIONS FROM DB =====
$orgRows = fetchAll(
    $conn,
    "
    SELECT org_name
    FROM config_org_options
    WHERE is_active = 1
    ORDER BY sort_order ASC, org_name ASC
    "
);

$org_options = array_map(
    fn($row) => $row['org_name'],
    $orgRows
);

// ===== GET USER DATA =====
$fetchUserProfileSql = "
    SELECT user_name, stud_num, user_email, org_body, profile_pic, user_password
    FROM users
    WHERE user_id = ?
";

$user = fetchOne(
    $conn,
    $fetchUserProfileSql,
    "i",
    [$user_id]
);

if (!$user) {
    $_SESSION["error"] = "User profile not found.";
    header("Location:" . PUBLIC_URL . "index.php");
    exit();
}

// ===== PROFILE IMAGE UPLOAD =====
if (isset($_POST["upload_photo"])) {
    if (isset($_FILES["profile_pic"]) && $_FILES["profile_pic"]["error"] === 0) {
        $allowed = ["jpg", "jpeg", "png", "webp"];
        $ext = strtolower(pathinfo($_FILES["profile_pic"]["name"], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed, true)) {
            $_SESSION["error"] = "Only .JPG, .JPEG, .PNG, and .WEBP files are allowed.";
            header("Location:" . USER_PAGE . "profile.php");
            exit();
        }

        $check = getimagesize($_FILES["profile_pic"]["tmp_name"]);
        if ($check === false) {
            $_SESSION["error"] = "Invalid image file.";
            header("Location:" . USER_PAGE . "profile.php");
            exit();
        }

        $uploadDir = PUBLIC_PATH . "assets/profiles/";
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $newName = "user_" . $user_id . "_" . time() . "." . $ext;
        $targetPath = $uploadDir . $newName;

        if (move_uploaded_file($_FILES["profile_pic"]["tmp_name"], $targetPath)) {
            if (!empty($user['profile_pic']) && $user['profile_pic'] !== 'default.jpg') {
                $oldPicPath = $uploadDir . $user['profile_pic'];
                if (is_file($oldPicPath)) {
                    @unlink($oldPicPath);
                }
            }

            $updateProfilePictureSql = "
                UPDATE users
                SET profile_pic = ?
                WHERE user_id = ?
            ";

            execQuery(
                $conn,
                $updateProfilePictureSql,
                "si",
                [$newName, $user_id]
            );

            $_SESSION["success"] = "Profile picture updated.";
            header("Location:" . USER_PAGE . "profile.php");
            exit();
        } else {
            $_SESSION["error"] = "Failed to upload profile picture.";
            header("Location:" . USER_PAGE . "profile.php");
            exit();
        }
    } else {
        $_SESSION["error"] = "Please select an image to upload.";
        header("Location:" . USER_PAGE . "profile.php");
        exit();
    }
}

// ===== REMOVE PROFILE PHOTO =====
if (isset($_POST['remove_photo'])) {
    if (!empty($user['profile_pic']) && $user['profile_pic'] !== 'default.jpg') {
        $oldPic = PUBLIC_PATH . "/assets/profiles/" . $user['profile_pic'];
        if (is_file($oldPic)) {
            @unlink($oldPic);
        }

        $resetProfilePictureSql = "
            UPDATE users
            SET profile_pic = 'default.jpg'
            WHERE user_id = ?
        ";

        execQuery(
            $conn,
            $resetProfilePictureSql,
            "i",
            [$user_id]
        );
    }

    $_SESSION['success'] = "Profile photo removed.";
    header("Location:" . USER_PAGE . "profile.php");
    exit();
}

// ===== UPDATE PROFILE INFO =====
if (isset($_POST["update_profile"])) {
    $username = trim($_POST["username"] ?? "");
    $studnum = trim($_POST["stud_num"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $org = trim($_POST["org_body"] ?? "");

    if ($username === "" || $studnum === "" || $email === "" || $org === "") {
        $_SESSION["error"] = "All fields are required.";
        header("Location:" . USER_PAGE . "profile.php");
        exit();
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION["error"] = "Invalid email format.";
        header("Location:" . USER_PAGE . "profile.php");
        exit();
    }

    if (!preg_match('/@(student\.hau\.edu\.ph)$/i', $email)) {
        $_SESSION["error"] = "Email must be your HAU student email.";
        header("Location:" . USER_PAGE . "profile.php");
        exit();
    }

    if (strlen($studnum) < 8) {
        $_SESSION["error"] = "Invalid student number.";
        header("Location:" . USER_PAGE . "profile.php");
        exit();
    }

    if (strlen($username) < 4) {
        $_SESSION["error"] = "Username must be at least 4 characters.";
        header("Location:" . USER_PAGE . "profile.php");
        exit();
    }

    if (!in_array($org, $org_options, true)) {
        $_SESSION["error"] = "Please select a valid organizing body.";
        header("Location:" . USER_PAGE . "profile.php");
        exit();
    }

    // ===== VALIDATION: UNIQUE CONSTRAINTS =====
    $checkUniqueProfileFieldsSql = "
        SELECT user_name, user_email, stud_num
        FROM users
        WHERE (user_name = ? OR user_email = ? OR stud_num = ?)
          AND user_id != ?
        LIMIT 1
    ";

    $row = fetchOne(
        $conn,
        $checkUniqueProfileFieldsSql,
        "sssi",
        [$username, $email, $studnum, $user_id]
    );

    if ($row) {
        if (($row["user_name"] ?? '') === $username) {
            $_SESSION["error"] = "Username already exists.";
        } elseif (($row["user_email"] ?? '') === $email) {
            $_SESSION["error"] = "Email already exists.";
        } elseif (($row["stud_num"] ?? '') === $studnum) {
            $_SESSION["error"] = "Student number already exists.";
        }

        header("Location:" . USER_PAGE . "profile.php");
        exit();
    }

    // ===== UPDATE PROFILE =====
    $updateUserProfileSql = "
        UPDATE users
        SET user_name = ?, stud_num = ?, user_email = ?, org_body = ?
        WHERE user_id = ?
    ";

    execQuery(
        $conn,
        $updateUserProfileSql,
        "ssssi",
        [$username, $studnum, $email, $org, $user_id]
    );

    $_SESSION["username"] = $username;
    $_SESSION["org_body"] = $org;

    $_SESSION["success"] = "Profile updated successfully.";
    header("Location:" . USER_PAGE . "profile.php");
    exit();
}

// ===== PASSWORD CHANGE =====
if (isset($_POST["change_password"])) {
    $current = $_POST["current_password"] ?? "";
    $new = $_POST["new_password"] ?? "";
    $confirm = $_POST["confirm_password"] ?? "";

    if (!password_verify($current, $user["user_password"])) {
        $_SESSION["error"] = "Current password incorrect.";
        header("Location:" . USER_PAGE . "profile.php");
        exit();
    }

    if ($new !== $confirm) {
        $_SESSION["error"] = "Passwords do not match.";
        header("Location:" . USER_PAGE . "profile.php");
        exit();
    }

    if (
        strlen($new) < 8 ||
        !preg_match('/[A-Z]/', $new) ||
        !preg_match('/[a-z]/', $new) ||
        !preg_match('/[0-9]/', $new)
    ) {
        $_SESSION["error"] = "Password must be at least 8 characters including uppercase, lowercase, and numbers.";
        header("Location:" . USER_PAGE . "profile.php");
        exit();
    }

    $hash = password_hash($new, PASSWORD_DEFAULT);

    $updateUserPasswordSql = "
        UPDATE users
        SET user_password = ?
        WHERE user_id = ?
    ";

    execQuery(
        $conn,
        $updateUserPasswordSql,
        "si",
        [$hash, $user_id]
    );

    $_SESSION["success"] = "Password changed successfully.";
    header("Location:" . USER_PAGE . "profile.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Settings</title>
    <link rel="stylesheet" href="<?= PUBLIC_URL ?>assets/styles/layout.css" />
    <link rel="stylesheet" href="<?= PUBLIC_URL ?>assets/styles/profile.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.css">
</head>

<body>
    <div class="sidebar-overlay"></div>

    <div class="app">
        <?php
        $nav_file = (($_SESSION["role"] ?? "") === "admin")
            ? PUBLIC_PATH . 'assets/includes/admin_nav.php'
            : PUBLIC_PATH . 'assets/includes/general_nav.php';

        include $nav_file;
        ?>

        <main class="main">
            <header class="topbar">
                <div class="title-wrap">
                    <h1>Profile Settings</h1>
                    <p>Update personal information or password.</p>
                </div>
            </header>

            <section class="content profile-page">
                <aside class="profile-card">
                    <div class="profile-avatar-wrap">
                        <img class="profile-avatar"
                            src="<?= PUBLIC_URL ?>assets/profiles/<?php echo htmlspecialchars($user['profile_pic'] ?? 'default.jpg'); ?>"
                            alt="Profile Picture">

                        <button class="pencil-btn" type="button" id="editPhotoBtn">
                            <img class="pencil-icon" src="<?= PUBLIC_URL ?>assets/images/pencil.png" alt="Pencil">
                        </button>

                        <form method="post" enctype="multipart/form-data" id="photoForm">
                            <input type="file" name="profile_pic" id="photoInput" accept="image/*" hidden>
                            <button type="submit" name="upload_photo" hidden id="photoSubmit"></button>
                        </form>

                        <div class="photo-menu" id="photoMenu" style="display:none;">
                            <button class="btn-secondary btn-smaller" type="button" id="uploadChoice">Upload
                                Photo</button>

                            <?php if (!empty($user['profile_pic']) && $user['profile_pic'] !== 'default.jpg'): ?>
                                <button class="btn-secondary btn-smaller" type="button" id="removeChoice">Remove
                                    Photo</button>
                            <?php endif; ?>
                        </div>
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
                            <button class="btn-secondary btn-smaller cancel" id="cropCancel">Cancel</button>
                            <button class="btn-primary btn-smaller save" id="cropSave">Apply</button>
                        </div>
                    </div>
                </div>

                <section class="profile-panel">
                    <?php if ($success): ?>
                        <div class="alert success"><?php echo $success ?></div>
                    <?php endif; ?>

                    <?php if ($error): ?>
                        <div class="alert error"><?php echo $error ?></div>
                    <?php endif; ?>

                    <div class="tabs">
                        <button class="tab active" id="editBtn">Edit Profile</button>
                        <button class="tab" id="passBtn">Change Password</button>
                    </div>

                    <div id="editTab" class="tab-content active">
                        <form class="profile-form" method="post">
                            <div class="grid-2">
                                <div class="form-field">
                                    <label>USERNAME</label>
                                    <input type="text" name="username"
                                        value="<?php echo htmlspecialchars($user['user_name']); ?>">
                                </div>

                                <div class="form-field">
                                    <label>STUDENT NUMBER</label>
                                    <input type="text" name="stud_num"
                                        value="<?php echo htmlspecialchars($user['stud_num']); ?>">
                                </div>
                            </div>

                            <div class="form-field full">
                                <label>EMAIL ADDRESS</label>
                                <input type="email" name="email"
                                    value="<?php echo htmlspecialchars($user['user_email']); ?>">
                            </div>

                            <div class="form-field full">
                                <label>ORGANIZING BODY</label>
                                <input list="org_list" name="org_body"
                                    value="<?php echo htmlspecialchars($user['org_body']); ?>"
                                    placeholder="Search or select organization" required>
                                <datalist id="org_list">
                                    <?php foreach ($org_options as $org): ?>
                                        <option value="<?= htmlspecialchars($org, ENT_QUOTES, 'UTF-8') ?>">
                                        <?php endforeach; ?>
                                </datalist>
                            </div>

                            <div class="pw-actions">
                                <button class="btn-secondary btn-smaller ghost" type="reset">Discard Changes</button>
                                <button class="btn-primary btn-smaller" type="submit" name="update_profile">Apply
                                    Changes</button>
                            </div>
                        </form>
                    </div>

                    <div id="passTab" class="tab-content">
                        <form class="pw-form" method="post">
                            <div class="pw-field">
                                <label>CURRENT PASSWORD *</label>
                                <div class="pw-input">
                                    <input id="curpw" name="current_password" type="password" required>
                                    <button class="eye-btn" type="button" data-toggle="curpw">
                                        <i class="fa-regular fa-eye"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="pw-field">
                                <label>NEW PASSWORD *</label>
                                <div class="pw-input">
                                    <input id="newpw" name="new_password" type="password" required>
                                    <button class="eye-btn" type="button" data-toggle="newpw">
                                        <i class="fa-regular fa-eye"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="pw-field">
                                <label>CONFIRM NEW PASSWORD *</label>
                                <div class="pw-input">
                                    <input id="confpw" name="confirm_password" type="password" required>
                                    <button class="eye-btn" type="button" data-toggle="confpw">
                                        <i class="fa-regular fa-eye"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="pw-actions">
                                <button class="btn-secondary btn-smaller ghost" type="reset">Discard Changes</button>
                                <button class="btn-primary btn-smaller primary" type="submit"
                                    name="change_password">Apply Changes</button>
                            </div>
                        </form>
                    </div>
                </section>
            </section>
        </main>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.js"></script>
    <script src="<?= APP_URL ?>script/profile.js" defer></script>
</body>

</html>