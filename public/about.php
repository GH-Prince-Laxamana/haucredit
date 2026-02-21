<?php
session_start();
require_once "../app/database.php";
require_once "../app/security_headers.php";
send_security_headers();
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>About Us</title>
  <link rel="stylesheet" href="assets/styles/layout.css" />
</head>

<body>
  <div class="app">

    <?php include 'assets/includes/general_nav.php' ?>

    <main class="main">

      <header class="topbar">
        <div class="title-wrap">
          <h1>About Us</h1>

          <p>Learn more about HAUCREDIT and our team</p>
        </div>
      </header>

      <section class="content about-page">

        <!-- HERO -->
        <header class="about-hero">
          <h1>What is HAUCREDIT?</h1>

          <p>
            <strong>HAUCREDIT</strong> (Holy Angel University Compliance & Records Engine for Documentation and
            Institutional Tracking) is a comprehensive web-based event compliance tracking system designed specifically
            for Holy Angel University student organizations.
          </p>

          <p>
            Our platform streamlines the event proposal and compliance process, ensuring that all student activities
            meet HAU Office of Student Affairs (OSA) requirements while reducing administrative burden on student
            leaders.
          </p>
        </header>

        <!-- FEATURES -->
        <section class="about-section">
          <h2>Key Features</h2>

          <ul class="feature-list">
            <li>
              <strong>Smart Event Classification</strong> - Automatically determines required documents based on
              activity type
            </li>
            <li>
              <strong>Dynamic Forms</strong> - Shows only relevant fields for your specific event (on-campus, virtual,
              off-campus, community service)
            </li>
            <li>
              <strong>Template Library</strong> - Direct access to official HAU OSA form templates via tinyurl links
            </li>
            <li>
              <strong>Compliance Checklist</strong> - Auto-generates requirements list based on your event details
            </li>
            <li>
              <strong>Calendar Integration</strong> - View all scheduled events with progress tracking
            </li>
            <li>
              <strong>Session Management</strong> - Secure login system for student councils and organizations
            </li>
          </ul>
        </section>

        <!-- MISSION -->
        <section class="about-section">
          <h2>Our Mission</h2>

          <p>
            To empower Holy Angel University student organizations with efficient tools that ensure full compliance
            with institutional policies while fostering a culture of accountability, transparency, and excellence in
            student leadership and event management.
          </p>
        </section>

        <!-- TEAM -->
        <section class="about-section">
          <h2>Meet the Team</h2>

          <div class="about-grid">

            <article class="about-card">
              <figure>
                <img src="assets/images/team/prince.jpg" alt="Prince S. Laxamana" class="about-img">
              </figure>

              <div class="about-body">
                <h3>Prince S. Laxamana</h3>
                <p class="role">Backend Developer & Database Engineer</p>
                <p>
                  Responsible for planning the database schema, creating the database and tables, defining
                  relationships, connecting forms to the database, implementing login and logout with session
                  management, and developing CRUD operations and search functionality using PHP and SQL.
                </p>
              </div>
            </article>

            <article class="about-card">
              <figure>
                <img src="assets/images/team/dannah.jpg" alt="Dannah Mikayla M. Sanchez" class="about-img">
              </figure>

              <div class="about-body">
                <h3>Dannah Mikayla M. Sanchez</h3>
                <p class="role">Frontend Developer (Layout Architect)</p>
                <p>
                  Responsible for creating the wireframes, defining the site structure, layouts, and navigation flow,
                  designing the overall user interface reference, and implementing the responsive layout and navigation
                  menu using HTML and CSS.
                </p>
              </div>
            </article>

            <article class="about-card">
              <figure>
                <img src="assets/images/team/kenaz.jpg" alt="Kenaz Brian M. Yañez" class="about-img">
              </figure>

              <div class="about-body">
                <h3>Kenaz Brian M. Yañez</h3>
                <p class="role">Frontend Developer (UI/UX Designer)</p>
                <p>
                  Responsible for contributing to the wireframing and layout planning of the website, helping define
                  the overall structure and visual flow of the pages, designing the dashboard and other site pages,
                  applying basic styling and responsiveness, and coding the static pages using HTML and CSS.
                </p>
              </div>
            </article>

            <article class="about-card">
              <figure>
                <img src="assets/images/team/justine.jpg" alt="Justine Lee N. Larioza" class="about-img">
              </figure>

              <div class="about-body">
                <h3>Justine Lee N. Larioza</h3>
                <p class="role">Database Administrator & Security</p>
                <p>
                  Designing the event input form, search, and login forms by carefully structuring and organizing
                  fields to ensure clarity, accessibility, and ease of use, improving the overall user experience
                  through logical grouping and intuitive layout.
                </p>
              </div>
            </article>

          </div>
        </section>

        <!-- CONTACT -->
        <section class="about-section contact-section">
          <h2>Get in Touch</h2>

          <div class="contact-grid">

            <address class="contact-item">
              <h3>Email Support</h3>
              <p>
                <a href="mailto:haucredit@hau.edu.ph">haucredit@hau.edu.ph</a>
              </p>
            </address>

            <div class="contact-item">
              <h3>HAU OSA Resources</h3>
              <p>
                <a href="https://tinyurl.com/HAUColStuHandbook2025Ed" target="_blank">College Student Handbook</a>
              </p>
              <p>
                <a href="https://tinyurl.com/HAUStuActManual2025Edition" target="_blank">Student Activity Manual</a>
              </p>
              <p>
                <a href="https://tinyurl.com/allLinksHAUOSAStuAct" target="_blank">All OSA Forms & Templates</a>
              </p>
            </div>

            <address class="contact-item">
              <h3>Office of Student Affairs</h3>
              <p>Email:
                <a href="mailto:studentactivities@hau.edu.ph">studentactivities@hau.edu.ph</a>
              </p>
              <p>Alt Email:
                <a href="mailto:studentactivities.hauosa@gmail.com">studentactivities.hauosa@gmail.com</a>
              </p>
            </address>

          </div>
        </section>
      </section>

      <?php include 'assets/includes/footer.php' ?>
    </main>
  </div>
</body>

</html>