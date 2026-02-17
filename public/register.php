<?php
// register.php
session_start();

require_once("../app/database.php");
require_once("../app/security_headers.php");
send_security_headers();

function e(string $s): string {
  return htmlspecialchars($s, ENT_QUOTES, "UTF-8");
}

$errors = [];
$success = "";

$username = "";
$email = "";
$student_no = "";
$organization = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $username     = trim($_POST["username"] ?? "");
  $email        = trim($_POST["email"] ?? "");
  $student_no   = trim($_POST["student_no"] ?? "");
  $organization = trim($_POST["organization"] ?? "");
  $password     = $_POST["password"] ?? "";
  $confirm      = $_POST["confirm_password"] ?? "";

  if ($username === "") $errors[] = "Username is required.";
  if ($email === "" || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required.";
  if ($student_no === "") $errors[] = "Student number is required.";
  if ($organization === "") $errors[] = "Please select an organization.";
  if (strlen($password) < 8) $errors[] = "Password must be at least 8 characters.";
  if ($password !== $confirm) $errors[] = "Passwords do not match.";

  if (!$errors) {
    $hash = password_hash($password, PASSWORD_DEFAULT);

    try {
      // PDO
      if (isset($pdo) && $pdo instanceof PDO) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR student_no = ? LIMIT 1");
        $stmt->execute([$email, $student_no]);
        if ($stmt->fetch()) {
          $errors[] = "Email or student number already exists.";
        } else {
          $stmt = $pdo->prepare("
            INSERT INTO users (username, email, student_no, organization, password_hash, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
          ");
          $stmt->execute([$username, $email, $student_no, $organization, $hash]);
          $success = "Account created successfully! You may now log in.";
          $username = $email = $student_no = $organization = "";
        }
      }
      // mysqli
      else if (isset($conn) && $conn instanceof mysqli) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? OR student_no = ? LIMIT 1");
        $stmt->bind_param("ss", $email, $student_no);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
          $errors[] = "Email or student number already exists.";
        } else {
          $stmt = $conn->prepare("
            INSERT INTO users (username, email, student_no, organization, password_hash, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
          ");
          $stmt->bind_param("sssss", $username, $email, $student_no, $organization, $hash);
          $stmt->execute();
          $success = "Account created successfully! You may now log in.";
          $username = $email = $student_no = $organization = "";
        }
      } else {
        $errors[] = "Database connection not found. Check ../app/database.php.";
      }
    } catch (Throwable $ex) {
      $errors[] = "Registration failed. Please try again.";
      // dev-only:
      // $errors[] = $ex->getMessage();
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>HAUCREDIT | Register</title>
  <link rel="stylesheet" href="../css/styles.css" />
</head>
<body>

  <!-- NAVBAR: matches burgundy card (no circle) -->
  <div class="navbar">
    <div class="navbar-brand">
      <img class="navbar-mark" src="../img/FavLogo.png" alt="HAUCREDIT mark">
      <div class="navbar-title">HAU<span class="accent">CREDIT</span></div>
    </div>

    <div class="navlinks">
      <a href="index.php">Login</a>
      <a class="active" href="register.php">Register</a>
    </div>
  </div>

  <div class="container">
    <div class="left-panel">
      <div class="brand-title">
        <h1 class="brand-name">HAU<span class="brand-accent">CREDIT</span></h1>
        <p class="brand-tagline">Compliance & Records Engine for Documentation and Institutional Tracking.</p>
      </div>

      <ul>
        <li>Centralized Event Monitoring</li>
        <li>Automated OSA Checklists</li>
        <li>Secure Document Repository</li>
      </ul>
    </div>

    <div class="right-panel">
      <div class="card">

        <h2>Register Account</h2>
        <div class="subtitle">For recognized student organizations only.</div>

        <?php if ($errors): ?>
          <div class="notice error">
            <b>Fix the following:</b>
            <ul style="margin:8px 0 0 18px;">
              <?php foreach ($errors as $err): ?>
                <li><?= e($err) ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php elseif ($success): ?>
          <div class="notice success"><?= e($success) ?></div>
        <?php endif; ?>

        <form method="post" action="register.php" autocomplete="off">

          <div class="form-group">
            <label for="username">Username</label>
            <input id="username" name="username" type="text" placeholder="Enter username"
                   value="<?= e($username) ?>" required />
          </div>

          <div class="form-group">
            <label for="email">Email Address</label>
            <input id="email" name="email" type="email" placeholder="name@student.hau.edu.ph"
                   value="<?= e($email) ?>" required />
          </div>

          <div class="form-group">
            <label for="student_no">Student No.</label>
            <input id="student_no" name="student_no" type="text" placeholder="20XXXXXX"
                   value="<?= e($student_no) ?>" required />
          </div>

          <!-- UPDATED LIST (select) -->
          <div class="form-group select-wrap" id="orgWrap">
            <label for="organization">Organizing Body</label>
            <select id="organization" name="organization" required>
              <option value="">Select Organization</option>

              <!-- HAU OFFICE -->
              <option value="HAU OSA" <?= $organization==="HAU OSA" ? "selected" : "" ?>>HAU Office of Student Affairs</option>

              <!-- UNIVERSITY STUDENT GOVERNMENT -->
              <option value="HAUSG USC" <?= $organization==="HAUSG USC" ? "selected" : "" ?>>HAUSG USC</option>
              <option value="HAUSG HC" <?= $organization==="HAUSG HC" ? "selected" : "" ?>>HAUSG HC</option>
              <option value="HAUSG SEN" <?= $organization==="HAUSG SEN" ? "selected" : "" ?>>HAUSG SEN</option>
              <option value="HAUSG COMELEC" <?= $organization==="HAUSG COMELEC" ? "selected" : "" ?>>HAUSG COMELEC</option>
              <option value="HAUSG CSO" <?= $organization==="HAUSG CSO" ? "selected" : "" ?>>HAUSG CSO</option>
              <option value="HAUSG CFA" <?= $organization==="HAUSG CFA" ? "selected" : "" ?>>HAUSG CFA</option>

              <!-- COLLEGE STUDENT COUNCILS -->
              <option value="HAUSG CSC-CCJEF" <?= $organization==="HAUSG CSC-CCJEF" ? "selected" : "" ?>>HAUSG CSC-CCJEF</option>
              <option value="HAUSG CSC-SAS" <?= $organization==="HAUSG CSC-SAS" ? "selected" : "" ?>>HAUSG CSC-SAS</option>
              <option value="HAUSG CSC-SBA" <?= $organization==="HAUSG CSC-SBA" ? "selected" : "" ?>>HAUSG CSC-SBA</option>
              <option value="HAUSG CSC-SoC" <?= $organization==="HAUSG CSC-SoC" ? "selected" : "" ?>>HAUSG CSC-SoC</option>
              <option value="HAUSG CSC-SEd" <?= $organization==="HAUSG CSC-SEd" ? "selected" : "" ?>>HAUSG CSC-SEd</option>
              <option value="HAUSG CSC-SEA" <?= $organization==="HAUSG CSC-SEA" ? "selected" : "" ?>>HAUSG CSC-SEA</option>
              <option value="HAUSG CSC-SHTM" <?= $organization==="HAUSG CSC-SHTM" ? "selected" : "" ?>>HAUSG CSC-SHTM</option>
              <option value="HAUSG CSC-SNAMS" <?= $organization==="HAUSG CSC-SNAMS" ? "selected" : "" ?>>HAUSG CSC-SNAMS</option>

              <!-- STUDENT PUBLICATIONS -->
              <option value="HPC Angge" <?= $organization==="HPC Angge" ? "selected" : "" ?>>HPC - Angge</option>
              <option value="HPC HQ" <?= $organization==="HPC HQ" ? "selected" : "" ?>>HPC - HQ</option>
              <option value="HPC NX" <?= $organization==="HPC NX" ? "selected" : "" ?>>HPC - NX</option>
              <option value="HPC Enteng" <?= $organization==="HPC Enteng" ? "selected" : "" ?>>HPC - Enteng</option>
              <option value="HPC AP" <?= $organization==="HPC AP" ? "selected" : "" ?>>HPC - AP</option>
              <option value="HPC Reple" <?= $organization==="HPC Reple" ? "selected" : "" ?>>HPC - Reple</option>
              <option value="HPC Soln" <?= $organization==="HPC Soln" ? "selected" : "" ?>>HPC - Soln</option>
              <option value="HPC CC" <?= $organization==="HPC CC" ? "selected" : "" ?>>HPC - CC</option>
              <option value="HPC LL" <?= $organization==="HPC LL" ? "selected" : "" ?>>HPC - LL</option>

              <!-- UNI-WIDE ORGANIZATIONS -->
              <option value="Uniwide DC" <?= $organization==="Uniwide DC" ? "selected" : "" ?>>Uniwide - DC</option>
              <option value="Uniwide JJC" <?= $organization==="Uniwide JJC" ? "selected" : "" ?>>Uniwide - JJC</option>
              <option value="Uniwide JO" <?= $organization==="Uniwide JO" ? "selected" : "" ?>>Uniwide - JO</option>
              <option value="Uniwide GDGoC" <?= $organization==="Uniwide GDGoC" ? "selected" : "" ?>>Uniwide - GDGoC</option>
              <option value="Uniwide ADS" <?= $organization==="Uniwide ADS" ? "selected" : "" ?>>Uniwide - ADS</option>
              <option value="Uniwide RCY" <?= $organization==="Uniwide RCY" ? "selected" : "" ?>>Uniwide - RCY</option>
              <option value="Uniwide RAC" <?= $organization==="Uniwide RAC" ? "selected" : "" ?>>Uniwide - RAC</option>
              <option value="Uniwide APLMS" <?= $organization==="Uniwide APLMS" ? "selected" : "" ?>>Uniwide - APLMS</option>
              <option value="Uniwide SVE" <?= $organization==="Uniwide SVE" ? "selected" : "" ?>>Uniwide - SVE</option>
              <option value="Uniwide 21CC" <?= $organization==="Uniwide 21CC" ? "selected" : "" ?>>Uniwide - 21CC</option>
              <option value="Uniwide HPC" <?= $organization==="Uniwide HPC" ? "selected" : "" ?>>Uniwide - HPC</option>

              <!-- SCHOOL ORGANIZATIONS -->
              <option value="CCJEF COPS" <?= $organization==="CCJEF COPS" ? "selected" : "" ?>>CCJEF - COPS</option>
              <option value="CCJEF SAFE" <?= $organization==="CCJEF SAFE" ? "selected" : "" ?>>CCJEF - SAFE</option>
              <option value="SAS PsychSoc" <?= $organization==="SAS PsychSoc" ? "selected" : "" ?>>SAS - PsychSoc</option>
              <option value="SAS CL" <?= $organization==="SAS CL" ? "selected" : "" ?>>SAS - CL</option>
              <option value="SBA Mansoc" <?= $organization==="SBA Mansoc" ? "selected" : "" ?>>SBA - Mansoc</option>

              <option value="SoC MAFIA" <?= $organization==="SoC MAFIA" ? "selected" : "" ?>>SoC - MAFIA</option>
              <option value="SoC LOOP" <?= $organization==="SoC LOOP" ? "selected" : "" ?>>SoC - LOOP</option>
              <option value="SoC CG" <?= $organization==="SoC CG" ? "selected" : "" ?>>SoC - CG</option>
              <option value="SoC CSIA" <?= $organization==="SoC CSIA" ? "selected" : "" ?>>SoC - CSIA</option>

              <option value="SEd KAS" <?= $organization==="SEd KAS" ? "selected" : "" ?>>SEd - KAS</option>
              <option value="SEd KLDS" <?= $organization==="SEd KLDS" ? "selected" : "" ?>>SEd - KLDS</option>

              <option value="SEA SAEP" <?= $organization==="SEA SAEP" ? "selected" : "" ?>>SEA - SAEP</option>
              <option value="SEA UAPSA" <?= $organization==="SEA UAPSA" ? "selected" : "" ?>>SEA - UAPSA</option>
              <option value="SEA PSME" <?= $organization==="SEA PSME" ? "selected" : "" ?>>SEA - PSME</option>
              <option value="SEA PIIE" <?= $organization==="SEA PIIE" ? "selected" : "" ?>>SEA - PIIE</option>
              <option value="SEA IIEE" <?= $organization==="SEA IIEE" ? "selected" : "" ?>>SEA - IIEE</option>
              <option value="SEA PICE" <?= $organization==="SEA PICE" ? "selected" : "" ?>>SEA - PICE</option>
              <option value="SEA IECEP" <?= $organization==="SEA IECEP" ? "selected" : "" ?>>SEA - IECEP</option>
              <option value="SEA ICpEP" <?= $organization==="SEA ICpEP" ? "selected" : "" ?>>SEA - ICpEP</option>

              <option value="SHTM HMAP" <?= $organization==="SHTM HMAP" ? "selected" : "" ?>>SHTM - HMAP</option>
              <option value="SHTM LTSP" <?= $organization==="SHTM LTSP" ? "selected" : "" ?>>SHTM - LTSP</option>

              <option value="SNAMS ARTS" <?= $organization==="SNAMS ARTS" ? "selected" : "" ?>>SNAMS - ARTS</option>
              <option value="SNAMS PHISMETS" <?= $organization==="SNAMS PHISMETS" ? "selected" : "" ?>>SNAMS - PHISMETS</option>
              <option value="SNAMS SANS" <?= $organization==="SNAMS SANS" ? "selected" : "" ?>>SNAMS - SANS</option>

              <!-- POLITICAL PARTIES -->
              <option value="PP Lualu" <?= $organization==="PP Lualu" ? "selected" : "" ?>>PP - Lualu</option>
              <option value="PP Sulung" <?= $organization==="PP Sulung" ? "selected" : "" ?>>PP - Sulung</option>
              <option value="PP Sulagpo" <?= $organization==="PP Sulagpo" ? "selected" : "" ?>>PP - Sulagpo</option>
              <option value="PP Tindig" <?= $organization==="PP Tindig" ? "selected" : "" ?>>PP - Tindig</option>
            </select>
          </div>

          <div class="form-group">
            <label for="password">Password</label>
            <input id="password" name="password" type="password" placeholder="Minimum 8 characters" required />
          </div>

          <div class="form-group">
            <label for="confirm_password">Confirm Password</label>
            <input id="confirm_password" name="confirm_password" type="password" placeholder="Confirm password" required />
          </div>

          <button type="submit">Create Account</button>

          <div class="link">
            Already a Member? <a href="index.php">Log In</a>
          </div>
        </form>

      </div>
    </div>
  </div>

  <!-- Minimal JS (OPTIONAL) to animate the select arrow.
       Remove this <script> if you don’t want any JS. -->
  <script>
    (function () {
      const wrap = document.getElementById("orgWrap");
      const sel  = document.getElementById("organization");
      if (!wrap || !sel) return;

      sel.addEventListener("mousedown", () => wrap.classList.add("is-open"));
      sel.addEventListener("blur", () => wrap.classList.remove("is-open"));
      sel.addEventListener("change", () => wrap.classList.remove("is-open"));
    })();
  </script>

</body>
</html>
