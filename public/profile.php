<?php
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Profile Settings</title>
  <link rel="stylesheet" href="assets/styles/layout.css" />
  <link rel="stylesheet" href="assets/styles/profile.css" />
</head>

<body>

  <div class="sidebar-overlay"></div>

  <div class="app">

    <aside class="sidebar">
      <div class="brand">
        <div class="avatar" aria-hidden="true"></div>
      </div>

      <nav class="nav">
        <a href="home.php" class="nav-item">Dashboard</a>
        <a href="calendar.php" class="nav-item">Calendar</a>
        <a href="create_event.php" class="nav-item">Create Event</a>
        <a href="about.php" class="nav-item">About</a>
      </nav>

      <div class="account">
        <button class="account-btn" type="button">
          <span class="user-dot" aria-hidden="true"></span>
          <span>Account Name</span>
        </button>
      </div>
    </aside>

    <!-- MAIN -->
    <main class="main">

      <header class="topbar">
        <div class="title-wrap">
          <h1>Profile Settings</h1>
        </div>
      </header>

      <section class="content profile-page">

        <aside class="profile-card">
          <div class="profile-avatar-wrap">
            <div class="profile-avatar" aria-hidden="true"></div>

            <button class="camera-btn" type="button" aria-label="Change profile photo">
              <img src="assets/images/camera.png" alt="" class="camera-icon">
            </button>
          </div>

          <div class="profile-meta">
            <div class="profile-name">ACCOUNT NAME</div>
            <div class="profile-org">Organization</div>
          </div>
        </aside>

        <!-- RIGHT SETTINGS PANEL -->
        <section class="profile-panel">

          <!-- TABS -->
          <div class="tabs" role="tablist" aria-label="Profile tabs">
            <button class="tab active" type="button" role="tab" aria-selected="true" aria-controls="editTab"
              id="editBtn">
              Edit Profile
            </button>

            <button class="tab" type="button" role="tab" aria-selected="false" aria-controls="passTab" id="passBtn">
              Change Password
            </button>
          </div>

          <!-- EDIT PROFILE CONTENT (SEPARATE) -->
          <div id="editTab" class="tab-content active" role="tabpanel" aria-labelledby="editBtn">
            <form class="profile-form" action="#" method="post">

              <div class="grid-2">
                <div class="form-field">
                  <label for="first">FIRST NAME</label>
                  <input id="first" type="text" placeholder="Enter Firstname">
                </div>

                <div class="form-field">
                  <label for="last">LASTNAME</label>
                  <input id="last" type="text" placeholder="Enter Lastname">
                </div>

                <div class="form-field">
                  <label for="middle">MIDDLE NAME</label>
                  <input id="middle" type="text" placeholder="Enter Middle Name (leave blank if none)">
                </div>

                <div class="form-field">
                  <label for="username">USERNAME</label>
                  <input id="username" type="text" placeholder="Enter Username">
                </div>
              </div>

              <div class="form-field full">
                <label for="email">EMAIL ADDRESS</label>
                <input id="email" type="email" placeholder="student@email.com">
              </div>

              <div class="form-field full">
                <label for="org">ORGANIZING BODY</label>
                <input id="org" type="text" placeholder="Enter Organizing Body">
              </div>

              <div class="form-field full">
                <label for="phone">PHONE NUMBER</label>
                <input id="phone" type="tel" placeholder="09XXXXXXXXX">
              </div>

            </form>
          </div>

          <!-- CHANGE PASSWORD CONTENT (SEPARATE) -->
          <div id="passTab" class="tab-content" role="tabpanel" aria-labelledby="passBtn">
            <p class="pw-subtext">Update password for enhanced account security.</p>

            <form class="pw-form" action="#" method="post">

              <div class="pw-field">
                <label for="curpw">CURRENT PASSWORD *</label>
                <div class="pw-input">
                  <input id="curpw" type="password" placeholder="Enter Current Password">
                  <button class="eye-btn" type="button" data-toggle="curpw" aria-label="Show password">👁</button>
                </div>
              </div>

              <div class="pw-field">
                <label for="newpw">NEW PASSWORD *</label>
                <div class="pw-input">
                  <input id="newpw" type="password" placeholder="Enter New Password">
                  <button class="eye-btn" type="button" data-toggle="newpw" aria-label="Show password">👁</button>
                </div>
              </div>

              <div class="pw-field">
                <label for="confpw">CONFIRM NEW PASSWORD *</label>
                <div class="pw-input">
                  <input id="confpw" type="password" placeholder="Enter New Password">
                  <button class="eye-btn" type="button" data-toggle="confpw" aria-label="Show password">👁</button>
                </div>
              </div>

              <div class="pw-req">
                <div class="pw-req-title">Weak password. Must contain;</div>
                <ul class="pw-req-list">
                  <li class="ok">✓ At least 1 uppercase</li>
                  <li class="bad">✕ At least 1 number</li>
                  <li class="bad">✕ At least 8 characters</li>
                </ul>
              </div>

              <div class="pw-actions">
                <button class="pw-btn ghost" type="reset">Discard Changes</button>
                <button class="pw-btn primary" type="submit">Apply Changes</button>
              </div>

            </form>
          </div>

        </section>
      </section>
    </main>
  </div>

  <script>
    // ===== Tabs Toggle =====
    const editBtn = document.getElementById("editBtn");
    const passBtn = document.getElementById("passBtn");
    const editTab = document.getElementById("editTab");
    const passTab = document.getElementById("passTab");

    function showTab(which) {
      const isEdit = which === "edit";

      editBtn.classList.toggle("active", isEdit);
      passBtn.classList.toggle("active", !isEdit);

      editBtn.setAttribute("aria-selected", isEdit ? "true" : "false");
      passBtn.setAttribute("aria-selected", !isEdit ? "true" : "false");

      editTab.classList.toggle("active", isEdit);
      passTab.classList.toggle("active", !isEdit);
    }

    editBtn.addEventListener("click", () => showTab("edit"));
    passBtn.addEventListener("click", () => showTab("pass"));

    // ===== Show/Hide password inputs =====
    document.querySelectorAll(".eye-btn").forEach(btn => {
      btn.addEventListener("click", () => {
        const id = btn.getAttribute("data-toggle");
        const input = document.getElementById(id);
        if (!input) return;
        input.type = (input.type === "password") ? "text" : "password";
      });
    });

    // ===== Mobile sidebar toggle (optional if you use body.sidebar-open) =====
    const hamburger = document.querySelector(".hamburger");
    const overlay = document.querySelector(".sidebar-overlay");

    if (hamburger && overlay) {
      hamburger.addEventListener("click", () => document.body.classList.toggle("sidebar-open"));
      overlay.addEventListener("click", () => document.body.classList.remove("sidebar-open"));
    }
  </script>

</body>

</html>