<?php
// ===== SESSION INITIALIZATION =====
// Start the session to manage user authentication state
session_start();

// ===== DATABASE CONNECTION =====
// Include the database connection file to establish a connection for potential future use
require_once "../app/database.php";

// ===== SECURITY HEADERS =====
// Include and send security headers to protect against common web vulnerabilities
require_once "../app/security_headers.php";
send_security_headers();

// ===== PROFILE PICTURE INITIALIZATION =====
// If the user is not logged in, set a default profile picture
if (!isset($_SESSION["user_id"])) {
  $profile_pic = "default.jpg";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Character encoding and viewport for responsive design -->
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <!-- Page title -->
    <title>About Us</title>
    <!-- Stylesheets for layout and about page specific styles -->
    <link rel="stylesheet" href="assets/styles/layout.css" />
    <link rel="stylesheet" href="assets/styles/about_styles.css" />
</head>

<body>
    <div class="app">
        <!-- Overlay for sidebar on mobile devices -->
        <div class="sidebar-overlay" id="sidebarOverlay" hidden></div>

        <!-- Include the general navigation component -->
        <?php include 'assets/includes/general_nav.php' ?>

        <main class="main">
            <header class="topbar">
                <!-- Hamburger menu button for mobile navigation -->
                <button class="hamburger" id="menuBtn" type="button" aria-label="Open menu">☰</button>

                <div class="title-wrap">
                    <!-- Main heading and subtitle for the page -->
                    <h1>About Us</h1>
                    <p>Learn How HAUCREDIT Simplifies Student Event Management</p>
                </div>
            </header>

            <section class="content about-page">
                <!-- Hero section introducing HAUCREDIT -->
                <header class="about-hero">
                    <div class="hero-content">
                        <h1>What is HAUCREDIT?</h1>
                        <div class="hero-description">
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
                        </div>
                    </div>
                </header>

                <!-- Key Features section -->
                <section class="about-section">
                    <div class="section-header">
                        <h2>Key Features</h2>
                        <p class="section-subtitle">Everything you need to manage events efficiently</p>
                    </div>
                    
                    <div class="features-grid">
                        <div class="feature-card">
                            <div class="feature-icon">
                                <i class="fa-solid fa-chart-line"></i>
                            </div>
                            <h3>Centralized Event Dashboard</h3>
                            <p>View all your inputted events in one place with real-time updates on compliance status, requirements, and progress. Your event dashboard makes planning and monitoring effortless.</p>
                        </div>

                        <div class="feature-card">
                            <div class="feature-icon">
                                <i class="fa-solid fa-lightbulb"></i>
                            </div>
                            <h3>Intelligent Event Guidance</h3>
                            <p>HAUCREDIT automatically classifies your event type and highlights required documents, helping student leaders stay on track with institutional policies.</p>
                        </div>

                        <div class="feature-card">
                            <div class="feature-icon">
                                <i class="fa-solid fa-book-open"></i>
                            </div>
                            <h3>Template Library</h3>
                            <p>Direct access to official HAU OSA form templates via tinyurl links for quick and easy document preparation.</p>
                        </div>

                        <div class="feature-card">
                            <div class="feature-icon">
                                <i class="fa-solid fa-clipboard-list"></i>
                            </div>
                            <h3>Automated Compliance Checklist</h3>
                            <p>Generate a personalized checklist for each event to ensure nothing is overlooked, minimizing delays and administrative headaches.</p>
                        </div>

                        <div class="feature-card">
                            <div class="feature-icon">
                                <i class="fa-solid fa-file-alt"></i>
                            </div>
                            <h3>Built-in Templates for Requirements</h3>
                            <p>Whenever specific forms are needed, HAUCREDIT provides direct access to official HAU OSA templates alongside your event requirements.</p>
                        </div>

                        <div class="feature-card">
                            <div class="feature-icon">
                                <i class="fa-solid fa-shield-alt"></i>
                            </div>
                            <h3>Secure Login & Session Management</h3>
                            <p>Access HAUCREDIT safely with your personal account, ensuring your event data remains private and protected.</p>
                        </div>
                    </div>
                </section>

                <!-- Mission section -->
                <section class="about-section">
                    <h2>Our Mission</h2>
                    <p>
                        To empower Holy Angel University student organizations with efficient tools that ensure full compliance
                        with institutional policies while fostering a culture of accountability, transparency, and excellence in
                        student leadership and event management.
                    </p>
                </section>

                <!-- Meet the Team section -->
                <section class="about-section">
                    <h2>Meet the Team</h2>
                    <div class="about-grid">
                        <!-- Team member card for Prince -->
                        <article class="about-card">
                            <figure><img src="assets/images/team/prince.jpg" alt="Prince S. Laxamana" class="about-img"></figure>
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

                        <!-- Team member card for Dannah -->
                        <article class="about-card">
                            <figure><img src="assets/images/team/dannah.jpg" alt="Dannah Mikayla M. Sanchez" class="about-img"></figure>
                            <div class="about-body">
                                <h3>Dannah Mikayla M. Sanchez</h3>
                                <p class="role">Frontend Developer (Layout Architect & UI/UX Designer)</p>
                                <p>
                                    Responsible for creating the wireframes, defining the site structure, layouts, and navigation flow,
                                    designing the overall user interface reference, and implementing the responsive layout and navigation
                                    menu using HTML and CSS.
                                </p>
                            </div>
                        </article>

                        <!-- Team member card for Kenaz -->
                        <article class="about-card">
                            <figure><img src="assets/images/team/kenaz.jpg" alt="Kenaz Brian M. Yañez" class="about-img"></figure>
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

                        <!-- Team member card for Justine -->
                        <article class="about-card">
                            <figure><img src="assets/images/team/justine.jpg" alt="Justine Lee N. Larioza" class="about-img"></figure>
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

                <!-- Contact section -->
                <section class="about-section contact-section">
                    <h2>Get in Touch</h2>
                    <div class="contact-grid">
                        <!-- Email support contact -->
                        <address class="contact-item">
                            <h3>Email Support</h3>
                            <p><a href="mailto:haucredit@hau.edu.ph">haucredit@hau.edu.ph</a></p>
                        </address>

                        <!-- HAU OSA Resources -->
                        <div class="contact-item">
                            <h3>HAU OSA Resources</h3>
                            <p><a href="https://tinyurl.com/HAUColStuHandbook2025Ed" target="_blank">College Student Handbook</a></p>
                            <p><a href="https://tinyurl.com/HAUStuActManual2025Edition" target="_blank">Student Activity Manual</a></p>
                            <p><a href="https://tinyurl.com/allLinksHAUOSAStuAct" target="_blank">All OSA Forms & Templates</a></p>
                        </div>

                        <!-- Office of Student Affairs contact -->
                        <address class="contact-item">
                            <h3>Office of Student Affairs</h3>
                            <p>Email: <a href="mailto:studentactivities@hau.edu.ph">studentactivities@hau.edu.ph</a></p>
                            <p>Alt Email: <a href="mailto:studentactivities.hauosa@gmail.com">studentactivities.hauosa@gmail.com</a></p>
                        </address>
                    </div>
                </section>
            </section>

            <!-- Include the footer component -->
            <?php include 'assets/includes/footer.php' ?>
        </main>
    </div>

    <!-- Include the layout JavaScript for navigation functionality -->
    <script src="../app/script/layout.js?v=1"></script>
</body>
</html>