<?php
session_start(); // <-- MUST be at the top

require_once "../app/database.php";
require_once "../app/security_headers.php";
send_security_headers();

if (!isset($_SESSION["user_id"])) {
  header("Location: index.php");
  exit();
}

$username = htmlspecialchars($_SESSION["username"], ENT_QUOTES, "UTF-8");
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>About Us - HAUCREDIT</title>
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
        
        <div class="about-hero">
          <h2>What is HAUCREDIT?</h2>
          <p>
            <strong>HAUCREDIT</strong> (Holy Angel University Compliance & Records Engine for Documentation and Institutional Tracking) 
            is a comprehensive web-based event compliance tracking system designed specifically for Holy Angel University student organizations.
          </p>
          <p>
            Our platform streamlines the event proposal and compliance process, ensuring that all student activities meet 
            HAU Office of Student Affairs (OSA) requirements while reducing administrative burden on student leaders.
          </p>
        </div>

        <div class="about-section">
          <h3>Key Features</h3>
          <ul class="feature-list">
            <li></span> <strong>Smart Event Classification</strong> - Automatically determines required documents based on activity type</li>
            <li><strong>Dynamic Forms</strong> - Shows only relevant fields for your specific event (on-campus, virtual, off-campus, community service)</li>
            <li><strong>Template Library</strong> - Direct access to official HAU OSA form templates via tinyurl links</li>
            <li><strong>Compliance Checklist</strong> - Auto-generates requirements list based on your event details</li>
            <li><strong>Calendar Integration</strong> - View all scheduled events with progress tracking</li>
            <li><strong>Session Management</strong> - Secure login system for student councils and organizations</li>
          </ul>
        </div>

        <div class="about-section">
          <h3>Our Mission</h3>
          <p>
            To empower Holy Angel University student organizations with efficient tools that ensure full compliance 
            with institutional policies while fostering a culture of accountability, transparency, and excellence in 
            student leadership and event management.
          </p>
        </div>

        <div class="about-section">
          <h3>Meet the Team</h3>
          <div class="about-grid">
            
            <article class="about-card">
              <img src="assets/images/team/prince.jpg" alt="Prince S. Laxamana" class="about-img">
              <div class="about-body">
                <h4>Prince S. Laxamana</h4>
                <p class="role">Backend Developer & Database Engineer</p>
                <p>Responsible for planning the database schema, creating the database and tables, defining relationships, connecting forms to the database, implementing login and logout with session management, and developing CRUD operations and search functionality using PHP and SQL.</p>
              </div>
            </article>

            <article class="about-card">
              <img src="assets/images/team/dannah.jpg" alt="Dannah Mikayla M. Sanchez" class="about-img">
              <div class="about-body">
                <h4>Dannah Mikayla M. Sanchez</h4>
                <p class="role">UI/UX Designer & Layout Architect</p>
                <p>Responsible for creating the wireframes, defining the site structure, layouts, and navigation flow, designing the overall user interface reference, and implementing the responsive layout and navigation menu using HTML and CSS</p>
              </div>
            </article>

            <article class="about-card">
              <img src="assets/images/team/kenaz.jpg" alt="Kenaz Brian M. Yañez" class="about-img">
              <div class="about-body">
                <h4>Kenaz Brian M. Yañez</h4>
                <p class="role">Frontend Developer (Pages)</p>
                <p>Responsible for contributing to the wireframing and layout planning of the website, helping define the overall structure and visual flow of the pages, designing the dashboard and other site pages, applying basic styling and responsiveness, and coding the static pages using HTML and CSS.</p>
              </div>
            </article>

            <article class="about-card">
              <img src="assets/images/team/justine.jpg" alt="Justine Lee N. Larioza" class="about-img">
              <div class="about-body">
                <h4>Justine Lee N. Larioza</h4>
                <p class="role">Database Administrator & Security</p>
                <p>Designing the event input form, search, and login forms by carefully structuring and organizing fields to ensure clarity, accessibility, and ease of use, improving the overall user experience through logical grouping and intuitive layout. </p>
              </div>
            </article>

          </div>
        </div>

        <div class="about-section contact-section">
          <h3>Get in Touch</h3>
          <div class="contact-grid">
            <div class="contact-item">
              <h4>Email Support</h4>
              <p><a href="mailto:haucredit@hau.edu.ph">haucredit@hau.edu.ph</a></p>
            </div>

            <div class="contact-item">
              <h4>HAU OSA Resources</h4>
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

            <div class="contact-item">
              <h4>Office of Student Affairs</h4>
              <p>Email: <a href="mailto:studentactivities@hau.edu.ph">studentactivities@hau.edu.ph</a></p>
              <p>Alt Email: <a href="mailto:studentactivities.hauosa@gmail.com">studentactivities.hauosa@gmail.com</a></p>
            </div>

            
          </div>
        </div>

        <div class="about-footer">
          <p><strong>HAUCREDIT</strong> v1.0 </p>
          <p>© 2026 Holy Angel University. All rights reserved.</p>
        </div>

      </section>
    </main>

  </div>

  <style>
    /* --- General Section Styling --- */
    .about-hero, .about-section {
      background: white;
      padding: 35px 40px;
      border-radius: 14px;
      box-shadow: 0 10px 25px rgba(0,0,0,.10);
      margin-bottom: 30px;
      border: 1px solid #e8dcc8;
    }

    .about-hero h2 {
      color: #4b0014;
      margin-bottom: 20px;
      font-size: 24px;
      font-weight: 600;
    }

    .about-section h3 {
      color: #4b0014;
      margin-bottom: 25px;
      font-size: 22px;
      font-weight: 600;
      border-bottom: 2px solid #c2a14d;
      padding-bottom: 12px;
    }

    /* --- List & Text Styling --- */
    .feature-list {
      list-style: none;
      padding: 0;
      margin-top: 5px;
    }

    .feature-list li {
      padding: 12px 0;
      border-bottom: 1px solid #f0f0f0;
      line-height: 1.6;
    }
    
    .feature-list li:last-child {
      border-bottom: none;
    }

    p {
      line-height: 1.7;
      color: #2a2a2a;
      margin-bottom: 16px;
    }

    /* --- TEAM GRID LAYOUT (The 4 Boxes) --- */
    .about-grid {
      display: grid;
      /* Forces 4 Equal Columns */
      grid-template-columns: repeat(4, 1fr); 
      gap: 20px;
      margin-top: 20px;
    }

    /* --- Individual Card Styling --- */
    .about-card {
      background: rgba(249, 243, 234, .55);
      border: 1px solid rgba(194, 161, 77, .25);
      border-radius: 14px;
      box-shadow: 0 4px 15px rgba(0,0,0,0.05);
      padding: 14px;
      
      /* Keeps boxes same height */
      height: 100%; 
      display: flex;
      flex-direction: column;
    }

    /* --- IMAGE Styling --- */
    img.about-img {
      width: 100%;
      height: 250px; /* Fixed height for uniformity */
      object-fit: cover; /* Crops image cleanly */
      object-position: top; /* Focuses on faces */
      border-radius: 12px;
      border: 1px solid rgba(194, 161, 77, .22);
      margin-bottom: 14px;
      background-color: #f0f0f0;
    }

    /* --- Card Text Content --- */
    .about-body {
      padding: 16px;
      border-radius: 12px;
      background: rgba(255, 255, 255, .85);
      border: 1px solid rgba(194, 161, 77, .18);
      flex: 1; /* Pushes content to fill remaining space */
      display: flex;
      flex-direction: column;
    }

    .about-card h4 {
      color: #4b0014;
      margin-bottom: 10px;
      font-size: 16px; /* Slightly smaller to fit side-by-side */
      font-weight: 600;
    }

    .about-card .role {
      color: #c2a14d;
      font-weight: 600;
      font-size: 13px;
      margin-bottom: 10px;
    }

    .about-card p {
      font-size: 13px;
      line-height: 1.6;
      color: #5a5a5a;
      margin: 0;
    }

    /* --- Contact Grid --- */
    .contact-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 40px 30px;
      margin-top: 30px;
    }
    
    .contact-item h4 {
      color: #4b0014;
      font-size: 16px;
      font-weight: 600;
      margin-bottom: 10px;
    }
    
    .contact-item a {
      color: #c2a14d;
      text-decoration: none;
    }

    .about-footer {
      text-align: center;
      padding: 30px 20px;
      color: #5a5a5a;
      font-size: 14px;
    }

    /* --- Responsive Logic --- */
    /* On tablets (1024px or less), switch to 2 columns */
    @media (max-width: 1024px) {
      .about-grid {
        grid-template-columns: repeat(2, 1fr);
      }
    }

    /* On phones (768px or less), switch to 1 column */
    @media (max-width: 768px) {
      .about-grid {
        grid-template-columns: 1fr;
      }
      .contact-grid {
        grid-template-columns: 1fr;
      }
      .about-hero, .about-section {
        padding: 25px;
      }
    }
  </style>

</body>
</html>