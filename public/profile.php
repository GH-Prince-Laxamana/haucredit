<?php
session_start();
require_once "../app/database.php";

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
SELECT user_name, stud_num, user_email, org_body, profile_pic, user_password
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
            $_SESSION["error"] = "Only .JPG, .PNG, .WEBP allowed.";
            header("Location: profile.php");
            exit();
        }

        $check = getimagesize($_FILES["profile_pic"]["tmp_name"]);
        if ($check === false) {
            $_SESSION["error"] = "Invalid image file.";
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

/* UPDATE PROFILE INFO */

if (isset($_POST["update_profile"])) {

    $username = trim($_POST["username"] ?? "");
    $studnum = trim($_POST["stud_num"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $org = trim($_POST["org_body"] ?? "");

    /* REQUIRED FIELDS */

    if ($username === "" || $studnum === "" || $email === "" || $org === "") {
        $_SESSION["error"] = "All fields are required.";
        header("Location: profile.php");
        exit();
    }

    /* EMAIL FORMAT */

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION["error"] = "Invalid email format.";
        header("Location: profile.php");
        exit();
    }

    /* HAU STUDENT EMAIL ONLY */

    if (!preg_match('/@(student\.hau\.edu\.ph)$/i', $email)) {
        $_SESSION["error"] = "Email must be your HAU student email.";
        header("Location: profile.php");
        exit();
    }

    /* STUDENT NUMBER VALIDATION */

    if (strlen($studnum) < 8) {
        $_SESSION["error"] = "Invalid student number.";
        header("Location: profile.php");
        exit();
    }

    /* USERNAME LENGTH */

    if (strlen($username) < 4) {
        $_SESSION["error"] = "Username must be at least 4 characters.";
        header("Location: profile.php");
        exit();
    }

    /* CHECK UNIQUE CONSTRAINTS */

    $stmt = $conn->prepare("
        SELECT user_name, user_email, stud_num
        FROM users
        WHERE (user_name=? OR user_email=? OR stud_num=?)
        AND user_id != ?
        LIMIT 1
    ");

    $stmt->bind_param("sssi", $username, $email, $studnum, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {

        if ($row["user_name"] === $username) {
            $_SESSION["error"] = "Username already exists.";
        } elseif ($row["user_email"] === $email) {
            $_SESSION["error"] = "Email already exists.";
        } elseif ($row["stud_num"] === $studnum) {
            $_SESSION["error"] = "Student number already exists.";
        }

        header("Location: profile.php");
        exit();
    }

    /* UPDATE PROFILE */

    $stmt = $conn->prepare("
        UPDATE users
        SET user_name=?, stud_num=?, user_email=?, org_body=?
        WHERE user_id=?
    ");

    $stmt->bind_param("ssssi", $username, $studnum, $email, $org, $user_id);

    if ($stmt->execute()) {
        $_SESSION["success"] = "Profile updated successfully.";
    } else {
        $_SESSION["error"] = "Unable to update profile.";
    }

    header("Location: profile.php");
    exit();
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

    if (
        strlen($new) < 8 ||
        !preg_match('/[A-Z]/', $new) ||
        !preg_match('/[a-z]/', $new) ||
        !preg_match('/[0-9]/', $new)
    ) {
        $_SESSION["error"] = "Password must be at least 8 characters including uppercase, lowercase, and numbers.";
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
                            src="assets/profiles/<?php echo htmlspecialchars($user['profile_pic'] ?? 'default.jpg'); ?>"
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
                        <div class="alert success"><?php echo $success ?></div>
                    <?php endif; ?>

                    <?php if ($error): ?>
                        <div class="alert error"><?php echo $error ?></div>
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
                                    <!-- HAU OFFICE -->
                                    <option value="HAU OSA">

                                        <!-- UNIVERSITY STUDENT GOVERNMENT -->
                                    <option value="HAUSG USC">
                                    <option value="HAUSG HC">
                                    <option value="HAUSG SEN">
                                    <option value="HAUSG COMELEC">
                                    <option value="HAUSG CSO">
                                    <option value="HAUSG CFA">

                                        <!-- COLLEGE STUDENT COUNCILS -->
                                    <option value="HAUSG CSC-CCJEF">
                                    <option value="HAUSG CSC-SAS">
                                    <option value="HAUSG CSC-SBA">
                                    <option value="HAUSG CSC-SoC">
                                    <option value="HAUSG CSC-SEd">
                                    <option value="HAUSG CSC-SEA">
                                    <option value="HAUSG CSC-SHTM">
                                    <option value="HAUSG CSC-SNAMS">

                                        <!-- STUDENT PUBLICATIONS -->
                                    <option value="HPC Angge">
                                    <option value="HPC HQ">
                                    <option value="HPC NX">
                                    <option value="HPC Enteng">
                                    <option value="HPC AP">
                                    <option value="HPC Reple">
                                    <option value="HPC Soln">
                                    <option value="HPC CC">
                                    <option value="HPC LL">

                                        <!-- UNI-WIDE ORGANIZATIONS -->
                                    <option value="Uniwide DC">
                                    <option value="Uniwide JJC">
                                    <option value="Uniwide JO">
                                    <option value="Uniwide GDGoC">
                                    <option value="Uniwide ADS">
                                    <option value="Uniwide RCY">
                                    <option value="Uniwide RAC">
                                    <option value="Uniwide APLMS">
                                    <option value="Uniwide SVE">
                                    <option value="Uniwide 21CC">
                                    <option value="Uniwide HPC">

                                        <!-- SCHOOL ORGANIZATIONS -->
                                    <option value="CCJEF COPS">
                                    <option value="CCJEF SAFE">
                                    <option value="SAS PsychSoc">
                                    <option value="SAS CL">
                                    <option value="SBA Mansoc">
                                    <option value="SoC MAFIA">
                                    <option value="SoC LOOP">
                                    <option value="SoC CG">
                                    <option value="SoC CSIA">
                                    <option value="SEd KAS">
                                    <option value="SEd KLDS">
                                    <option value="SEA SAEP">
                                    <option value="SEA UAPSA">
                                    <option value="SEA PSME">
                                    <option value="SEA PIIE">
                                    <option value="SEA IIEE">
                                    <option value="SEA PICE">
                                    <option value="SEA IECEP">
                                    <option value="SEA ICpEP">
                                    <option value="SHTM HMAP">
                                    <option value="SHTM LTSP">
                                    <option value="SNAMS ARTS">
                                    <option value="SNAMS PHISMETS">
                                    <option value="SNAMS SANS">

                                        <!-- POLITICAL PARTIES -->
                                    <option value="PP Lualu">
                                    <option value="PP Sulung">
                                    <option value="PP Sulagpo">
                                    <option value="PP Tindig">
                                </datalist>
                            </div>

                            <div class="pw-actions">

                                <button class="pw-btn ghost" type="reset">
                                    Discard Changes
                                </button>

                                <button class="pw-btn primary" type="submit" name="update_profile">
                                    Apply Changes
                                </button>

                            </div>



                        </form>



                    </div>


                    <!-- CHANGE PASSWORD -->

                    <div id="passTab" class="tab-content">

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