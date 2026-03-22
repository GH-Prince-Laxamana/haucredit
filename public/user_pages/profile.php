<?php
session_start();
require_once __DIR__ . '/../../app/database.php';

requireLogin();

$user_id = (int) $_SESSION["user_id"];

// ===== FLASH MESSAGES =====
$success = $_SESSION["success"] ?? "";
$error = $_SESSION["error"] ?? "";

// Clear session messages after retrieval to prevent re-display on page refresh
unset($_SESSION["success"], $_SESSION["error"]);

// ============================================================================
// LOAD ORGANIZATION OPTIONS FROM DATABASE
// ============================================================================

// Query all active organization options sorted by display order and name
// Used to populate datalist for org_body input field
$orgRows = fetchAll(
    $conn,
    "
    SELECT org_name
    FROM config_org_options
    WHERE is_active = 1
    ORDER BY sort_order ASC, org_name ASC
    "
);

// Extract org_name values from result rows for easier access in HTML
// Result: array of organization names (strings) in sort order
$org_options = array_map(
    fn($row) => $row['org_name'],
    $orgRows
);

// ============================================================================
// FETCH USER PROFILE DATA FROM DATABASE
// ============================================================================

// Query user profile including all editable fields and password hash
// Fields: personal info, contact, organization, profile picture, and password
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

// Validation: Ensure user record exists (safety check)
// If NOT found, redirect to home and exit - user session may be invalid
if (!$user) {
    $_SESSION["error"] = "User profile not found.";
    header("Location:" . PUBLIC_URL . "index.php");
    exit();
}

// ============================================================================
// PROFILE PICTURE UPLOAD & REPLACEMENT HANDLER
// ============================================================================

// Check if upload_photo form was submitted
if (isset($_POST["upload_photo"])) {
    // ==== STEP 1: VALIDATE FILE WAS UPLOADED ====
    if (isset($_FILES["profile_pic"]) && $_FILES["profile_pic"]["error"] === 0) {
        // Define allowed image file extensions
        $allowed = ["jpg", "jpeg", "png", "webp"];
        
        // Extract file extension from uploaded filename and convert to lowercase
        $ext = strtolower(pathinfo($_FILES["profile_pic"]["name"], PATHINFO_EXTENSION));

        // ==== STEP 2: VALIDATE FILE EXTENSION ====
        // Only allow whitelisted image formats
        if (!in_array($ext, $allowed, true)) {
            $_SESSION["error"] = "Only .JPG, .JPEG, .PNG, and .WEBP files are allowed.";
            header("Location:" . USER_PAGE . "profile.php");
            exit();
        }

        // ==== STEP 3: VALIDATE FILE IS ACTUAL IMAGE ====
        // getimagesize() returns FALSE if file is not a valid image
        $check = getimagesize($_FILES["profile_pic"]["tmp_name"]);
        if ($check === false) {
            $_SESSION["error"] = "Invalid image file.";
            header("Location:" . USER_PAGE . "profile.php");
            exit();
        }

        // ==== STEP 4: PREPARE UPLOAD DIRECTORY ====
        // Define upload directory path
        $uploadDir = PUBLIC_PATH . "assets/profiles/";
        
        // Create directory if it doesn't exist (with full permissions)
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        // ==== STEP 5: GENERATE UNIQUE FILENAME ====
        // Filename format: user_[USER_ID]_[TIMESTAMP].[EXT]
        // Ensures unique filenames and prevents conflicts
        $newName = "user_" . $user_id . "_" . time() . "." . $ext;
        $targetPath = $uploadDir . $newName;

        // ==== STEP 6: MOVE UPLOADED FILE TO FINAL LOCATION ====
        if (move_uploaded_file($_FILES["profile_pic"]["tmp_name"], $targetPath)) {
            // ==== STEP 7: DELETE OLD PROFILE PICTURE (IF EXISTS & NOT DEFAULT) ====
            // Prevent orphaned image files in storage
            if (!empty($user['profile_pic']) && $user['profile_pic'] !== 'default.jpg') {
                $oldPicPath = $uploadDir . $user['profile_pic'];
                if (is_file($oldPicPath)) {
                    @unlink($oldPicPath);
                }
            }

            // ==== STEP 8: UPDATE DATABASE WITH NEW FILENAME ====
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

            // ==== STEP 9: REDIRECT WITH SUCCESS MESSAGE ====
            $_SESSION["success"] = "Profile picture updated.";
            header("Location:" . USER_PAGE . "profile.php");
            exit();
        } else {
            // File move failed (permission issue or disk space)
            $_SESSION["error"] = "Failed to upload profile picture.";
            header("Location:" . USER_PAGE . "profile.php");
            exit();
        }
    } else {
        // No file was selected or upload error occurred
        $_SESSION["error"] = "Please select an image to upload.";
        header("Location:" . USER_PAGE . "profile.php");
        exit();
    }
}

// ============================================================================
// PROFILE PICTURE REMOVAL HANDLER
// ============================================================================

// Check if remove_photo form was submitted
if (isset($_POST['remove_photo'])) {
    // ==== STEP 1: CHECK IF USER HAS CUSTOM PROFILE PICTURE ====
    // Only proceed if current picture is not default
    if (!empty($user['profile_pic']) && $user['profile_pic'] !== 'default.jpg') {
        // ==== STEP 2: DELETE PHYSICAL FILE FROM STORAGE ====
        $oldPic = PUBLIC_PATH . "/assets/profiles/" . $user['profile_pic'];
        // Check file exists before attempting deletion
        if (is_file($oldPic)) {
            @unlink($oldPic);
        }

        // ==== STEP 3: RESET DATABASE TO DEFAULT PICTURE ====
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

    // ==== STEP 4: REDIRECT WITH SUCCESS MESSAGE ====
    // Always show success (even if user had no custom pic, it's now default)
    $_SESSION['success'] = "Profile photo removed.";
    header("Location:" . USER_PAGE . "profile.php");
    exit();
}

// ============================================================================
// UPDATE PROFILE INFORMATION HANDLER
// ============================================================================

// Check if update_profile form was submitted
if (isset($_POST["update_profile"])) {
    // ==== STEP 1: EXTRACT & TRIM FORM INPUT ====
    // Remove leading/trailing whitespace from all fields
    $username = trim($_POST["username"] ?? "");
    $studnum = trim($_POST["stud_num"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $org = trim($_POST["org_body"] ?? "");

    // ==== STEP 2: VALIDATE REQUIRED FIELDS ====
    // All fields must be non-empty
    if ($username === "" || $studnum === "" || $email === "" || $org === "") {
        $_SESSION["error"] = "All fields are required.";
        header("Location:" . USER_PAGE . "profile.php");
        exit();
    }

    // ==== STEP 3: VALIDATE EMAIL FORMAT ====
    // Check email follows standard email format (RFC 5322 basic)
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION["error"] = "Invalid email format.";
        header("Location:" . USER_PAGE . "profile.php");
        exit();
    }

    // ==== STEP 4: VALIDATE EMAIL DOMAIN ====
    // Enforce HAU (Holy Angel University) student email domain
    if (!preg_match('/@(student\.hau\.edu\.ph)$/i', $email)) {
        $_SESSION["error"] = "Email must be your HAU student email.";
        header("Location:" . USER_PAGE . "profile.php");
        exit();
    }

    // ==== STEP 5: VALIDATE STUDENT NUMBER LENGTH ====
    // Student number must be at least 8 characters (HAU format requirement)
    if (strlen($studnum) < 8) {
        $_SESSION["error"] = "Invalid student number.";
        header("Location:" . USER_PAGE . "profile.php");
        exit();
    }

    // ==== STEP 6: VALIDATE USERNAME LENGTH ====
    // Username must be at least 4 characters for usability
    if (strlen($username) < 4) {
        $_SESSION["error"] = "Username must be at least 4 characters.";
        header("Location:" . USER_PAGE . "profile.php");
        exit();
    }

    // ==== STEP 7: VALIDATE ORGANIZATION OPTION ====
    // Ensure selected organization is from the allowed list
    if (!in_array($org, $org_options, true)) {
        $_SESSION["error"] = "Please select a valid organizing body.";
        header("Location:" . USER_PAGE . "profile.php");
        exit();
    }

    // ==== STEP 8: VALIDATE UNIQUE CONSTRAINTS ====
    // Check if username, email, or student number already exist in database
    // Exclude current user's record (they can keep their own values)
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

    // If duplicate found, identify which field caused the conflict
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

    // ==== STEP 9: UPDATE DATABASE WITH NEW PROFILE DATA ====
    // All validations passed, update user record
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

    // ==== STEP 10: UPDATE SESSION DATA ====
    // Keep session in sync with updated profile
    $_SESSION["username"] = $username;
    $_SESSION["org_body"] = $org;

    // ==== STEP 11: REDIRECT WITH SUCCESS MESSAGE ====
    $_SESSION["success"] = "Profile updated successfully.";
    header("Location:" . USER_PAGE . "profile.php");
    exit();
}

// ============================================================================
// PASSWORD CHANGE HANDLER
// ============================================================================

// Check if change_password form was submitted
if (isset($_POST["change_password"])) {
    // ==== STEP 1: EXTRACT PASSWORD FORM INPUTS ====
    $current = $_POST["current_password"] ?? "";
    $new = $_POST["new_password"] ?? "";
    $confirm = $_POST["confirm_password"] ?? "";

    // ==== STEP 2: VERIFY CURRENT PASSWORD ====
    // Use password_verify() for secure hash comparison (prevents timing attacks)
    if (!password_verify($current, $user["user_password"])) {
        $_SESSION["error"] = "Current password incorrect.";
        header("Location:" . USER_PAGE . "profile.php");
        exit();
    }

    // ==== STEP 3: VALIDATE NEW PASSWORDS MATCH ====
    // Ensure user typed new password correctly twice
    if ($new !== $confirm) {
        $_SESSION["error"] = "Passwords do not match.";
        header("Location:" . USER_PAGE . "profile.php");
        exit();
    }

    // ==== STEP 4: VALIDATE PASSWORD STRENGTH ====
    // Enforce minimum 8 characters with uppercase, lowercase, and numbers
    // Requirements:
    //   - At least 8 characters total
    //   - At least one uppercase letter (A-Z)
    //   - At least one lowercase letter (a-z)
    //   - At least one digit (0-9)
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

    // ==== STEP 5: HASH NEW PASSWORD ====
    // Use PASSWORD_DEFAULT algorithm (currently bcrypt, upgradeable in future)
    // Hash is computed here, NOT transmitted to database raw
    $hash = password_hash($new, PASSWORD_DEFAULT);

    // ==== STEP 6: UPDATE DATABASE WITH NEW PASSWORD HASH ====
    // Store only the hash, never store plain-text passwords
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

    // ==== STEP 7: REDIRECT WITH SUCCESS MESSAGE ====
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