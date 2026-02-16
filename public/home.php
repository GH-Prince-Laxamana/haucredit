<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit();
}

$username = htmlspecialchars($_SESSION["username"], ENT_QUOTES, "UTF-8");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HAUCREDIT - Dashboard</title>
    <style>
        /* =========================
           THEME / VARIABLES
           ========================= */
        :root {
            --burgundy: #4b0014;
            --offwhite: #f9f3ea;
            --gold: #c2a14d;
            --shadow: 0 10px 25px rgba(0,0,0,.10);
            --radius: 14px;
        }

        /* =========================
           RESET / BASE
           ========================= */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: "SF Pro Text", "SF Pro Icons", "Helvetica Neue", Helvetica, Arial, sans-serif;
            background: var(--offwhite);
            color: #1a1a1a;
        }

        /* =========================
           APP LAYOUT
           ========================= */
        .app {
            min-height: 100vh;
            display: flex;
        }

        /* =========================
           SIDEBAR
           ========================= */
        .sidebar {
            width: 240px;
            background: var(--burgundy);
            color: var(--offwhite);
            display: flex;
            flex-direction: column;
            padding: 18px 14px;
        }

        .brand {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 10px 0 18px;
            flex-direction: column;
        }

        .avatar {
            width: 62px;
            height: 62px;
            border-radius: 50%;
            background: rgba(249,243,234,.35);
            border: 2px solid rgba(194,161,77,.55);
            margin-bottom: 10px;
        }

        .brand-name {
            font-size: 18px;
            font-weight: 700;
            letter-spacing: 0.5px;
            color: var(--offwhite);
        }

        .brand-subtitle {
            font-size: 10px;
            opacity: 0.75;
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-top: 2px;
        }

        .nav {
            display: flex;
            flex-direction: column;
            gap: 6px;
            margin-top: 6px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 12px;
            border-radius: 12px;
            color: var(--offwhite);
            text-decoration: none;
            opacity: .92;
            transition: transform .08s ease, background .12s ease, opacity .12s ease;
        }

        .nav-item:hover {
            background: rgba(249,243,234,.12);
            opacity: 1;
        }

        .nav-item:active {
            transform: scale(.99);
        }

        .nav-item .icon {
            width: 22px;
            display: inline-flex;
            justify-content: center;
            opacity: .95;
        }

        .nav-item.active {
            background: rgba(194,161,77,.18);
            border: 1px solid rgba(194,161,77,.45);
        }

        .account {
            margin-top: auto;
            padding-top: 14px;
        }

        .account-btn {
            width: 100%;
            border: 1px solid rgba(249,243,234,.25);
            background: rgba(249,243,234,.10);
            color: var(--offwhite);
            padding: 10px 12px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
        }

        .user-dot {
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: rgba(249,243,234,.45);
            border: 1px solid rgba(194,161,77,.6);
        }

        /* =========================
           MAIN
           ========================= */
        .main {
            flex: 1;
            padding: 22px 26px;
        }

        .topbar {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 18px;
        }

        .title-wrap h1 {
            font-size: 28px;
            line-height: 1.1;
            color: #2a2a2a;
        }

        .title-wrap p {
            margin-top: 6px;
            font-size: 14px;
            opacity: .7;
        }

        .top-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .icon-btn {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            border: 1px solid rgba(194,161,77,.7);
            background: var(--offwhite);
            cursor: pointer;
            box-shadow: 0 6px 16px rgba(0,0,0,.06);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            position: relative;
        }

        .notification-badge {
            position: absolute;
            top: -4px;
            right: -4px;
            background: #dc2626;
            color: white;
            font-size: 10px;
            font-weight: 700;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .primary-btn {
            height: 40px;
            padding: 0 14px;
            border-radius: 12px;
            border: 1px solid rgba(194,161,77,.7);
            background: var(--offwhite);
            color: var(--burgundy);
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 6px 16px rgba(0,0,0,.06);
        }

        .primary-btn:hover {
            background: #fff;
        }

        /* =========================
           STATS CARDS
           ========================= */
        .content {
            padding-top: 10px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 28px;
        }

        .stat-card {
            background: white;
            border-radius: var(--radius);
            padding: 20px;
            box-shadow: var(--shadow);
            border: 1px solid rgba(194,161,77,.15);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 15px 35px rgba(0,0,0,.15);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
        }

        .stat-icon {
            width: 42px;
            height: 42px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .stat-icon.blue { background: linear-gradient(135deg, #3b82f6 0%, #60a5fa 100%); }
        .stat-icon.green { background: linear-gradient(135deg, #059669 0%, #10b981 100%); }
        .stat-icon.amber { background: linear-gradient(135deg, #d97706 0%, #f59e0b 100%); }
        .stat-icon.purple { background: linear-gradient(135deg, #7c3aed 0%, #a78bfa 100%); }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: var(--burgundy);
            margin-bottom: 4px;
        }

        .stat-label {
            font-size: 14px;
            color: #64748b;
            font-weight: 500;
        }

        /* =========================
           SECTIONS
           ========================= */
        .section {
            background: white;
            border-radius: var(--radius);
            padding: 24px;
            box-shadow: var(--shadow);
            border: 1px solid rgba(194,161,77,.15);
            margin-bottom: 24px;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .section-title {
            font-size: 20px;
            font-weight: 700;
            color: var(--burgundy);
        }

        .view-all {
            color: var(--gold);
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: color 0.2s ease;
        }

        .view-all:hover {
            color: var(--burgundy);
        }

        /* =========================
           EVENTS TABLE
           ========================= */
        .event-row {
            border-bottom: 1px solid #e2e8f0;
            padding: 16px 0;
            display: grid;
            grid-template-columns: 2fr 1.2fr 1fr 100px;
            gap: 16px;
            align-items: center;
            transition: background 0.2s ease;
        }

        .event-row:hover {
            background: rgba(194,161,77,.02);
        }

        .event-row:last-child {
            border-bottom: none;
        }

        .event-name {
            font-weight: 600;
            color: var(--burgundy);
            font-size: 15px;
            margin-bottom: 4px;
        }

        .event-date {
            color: #64748b;
            font-size: 13px;
        }

        .event-progress {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .progress-bar-mini {
            flex: 1;
            height: 8px;
            background: #e2e8f0;
            border-radius: 8px;
            overflow: hidden;
        }

        .progress-fill-mini {
            height: 100%;
            background: linear-gradient(90deg, var(--gold) 0%, #ddb05f 100%);
            border-radius: 8px;
            transition: width 0.3s ease;
        }

        .progress-text {
            font-size: 13px;
            font-weight: 600;
            color: #64748b;
            min-width: 42px;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-badge.active {
            background: rgba(5, 150, 105, 0.1);
            color: #059669;
        }

        .status-badge.pending {
            background: rgba(217, 119, 6, 0.1);
            color: #d97706;
        }

        .status-badge.upcoming {
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
        }

        .btn-view {
            padding: 8px 16px;
            background: white;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            color: var(--burgundy);
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .btn-view:hover {
            border-color: var(--gold);
            background: var(--gold);
            color: white;
        }

        /* =========================
           DEADLINES
           ========================= */
        .deadline-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px;
            background: #fafbfc;
            border-radius: 10px;
            margin-bottom: 12px;
            border-left: 4px solid #d97706;
        }

        .deadline-info h4 {
            font-size: 15px;
            font-weight: 600;
            color: var(--burgundy);
            margin-bottom: 4px;
        }

        .deadline-info p {
            font-size: 13px;
            color: #64748b;
        }

        .deadline-date {
            text-align: right;
        }

        .deadline-date strong {
            display: block;
            color: #d97706;
            font-size: 16px;
            margin-bottom: 2px;
        }

        .deadline-date span {
            font-size: 12px;
            color: #64748b;
        }

        /* =========================
           RESPONSIVE
           ========================= */
        @media (max-width: 900px) {
            .sidebar { width: 210px; }
            .event-row { 
                grid-template-columns: 1fr; 
                gap: 10px;
            }
        }

        @media (max-width: 640px) {
            .app { flex-direction: column; }

            .sidebar {
                width: 100%;
                flex-direction: row;
                align-items: center;
                gap: 10px;
            }

            .brand { 
                padding: 0;
                flex-direction: row;
            }
            
            .brand-name {
                display: none;
            }
            
            .avatar { 
                width: 44px; 
                height: 44px;
                margin-bottom: 0;
            }

            .nav {
                flex-direction: row;
                overflow: auto;
                gap: 8px;
                margin: 0;
                padding: 8px 0;
            }

            .nav-item { white-space: nowrap; }
            .account { display: none; }
        }
    </style>
</head>
<body>
    <div class="app">
        <!-- SIDEBAR -->
        <aside class="sidebar">
            <div class="brand">
                <div class="avatar" aria-hidden="true"></div>
                <div class="brand-name">HAUCREDIT</div>
                <div class="brand-subtitle">Compliance Tracker</div>
            </div>

            <nav class="nav">
                <a class="nav-item active" href="dashboard.php">
                    <span class="icon" aria-hidden="true">
                        <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M3 13h1v7c0 1.103.897 2 2 2h12c1.103 0 2-.897 2-2v-7h1a1 1 0 0 0 .707-1.707l-9-9a.999.999 0 0 0-1.414 0l-9 9A1 1 0 0 0 3 13zm7 7v-5h4v5h-4zm2-15.586 6 6V15l.001 5H16v-5c0-1.103-.897-2-2-2h-4c-1.103 0-2 .897-2 2v5H6v-9.586l6-6z"/>
                        </svg>
                    </span>
                    <span>Dashboard</span>
                </a>

                <a class="nav-item" href="createEvent.php">
                    <span class="icon" aria-hidden="true">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                    </span>
                    <span>Create Event</span>
                </a>

                <a class="nav-item" href="calendar.php">
                    <span class="icon" aria-hidden="true">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                    </span>
                    <span>Calendar</span>
                </a>

                <a class="nav-item" href="about.php">
                    <span class="icon" aria-hidden="true">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </span>
                    <span>About Us</span>
                </a>
            </nav>

            <div class="account">
                <button class="account-btn" type="button">
                    <span class="user-dot" aria-hidden="true"></span>
                    <span><?php echo $username; ?></span>
                </button>
            </div>
        </aside>

        <!-- MAIN -->
        <main class="main">
            <header class="topbar">
                <div class="title-wrap">
                    <h1>Dashboard</h1>
                    <p>SAS Student Council • AY 2025-2026</p>
                </div>
                <div class="top-actions">
                    <button class="icon-btn" type="button" aria-label="Notifications">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                        </svg>
                        <span class="notification-badge">3</span>
                    </button>
                    <button class="primary-btn" type="button">
                        <span aria-hidden="true">+</span>
                        Create Event
                    </button>
                </div>
            </header>

            <section class="content">
                <!-- Stats Grid -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-header">
                            <div class="stat-icon blue">
                                <svg width="22" height="22" fill="white" viewBox="0 0 24 24">
                                    <path d="M19 4h-1V2h-2v2H8V2H6v2H5c-1.11 0-1.99.9-1.99 2L3 20c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 16H5V10h14v10zM9 14H7v-2h2v2zm4 0h-2v-2h2v2zm4 0h-2v-2h2v2zm-8 4H7v-2h2v2zm4 0h-2v-2h2v2zm4 0h-2v-2h2v2z"/>
                                </svg>
                            </div>
                        </div>
                        <div class="stat-value">8</div>
                        <div class="stat-label">Active Events</div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-header">
                            <div class="stat-icon amber">
                                <svg width="22" height="22" fill="white" viewBox="0 0 24 24">
                                    <path d="M12 5.99L19.53 19H4.47L12 5.99M12 2L1 21h22L12 2zm1 14h-2v2h2v-2zm0-6h-2v4h2v-4z"/>
                                </svg>
                            </div>
                        </div>
                        <div class="stat-value">3</div>
                        <div class="stat-label">Upcoming Deadlines</div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-header">
                            <div class="stat-icon green">
                                <svg width="22" height="22" fill="white" viewBox="0 0 24 24">
                                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                                </svg>
                            </div>
                        </div>
                        <div class="stat-value">82%</div>
                        <div class="stat-label">Compliance Progress</div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-header">
                            <div class="stat-icon purple">
                                <svg width="22" height="22" fill="white" viewBox="0 0 24 24">
                                    <path d="M20 6h-8l-2-2H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2zm0 12H4V8h16v10z"/>
                                </svg>
                            </div>
                        </div>
                        <div class="stat-value">24</div>
                        <div class="stat-label">Archived Events</div>
                    </div>
                </div>

                <!-- Active Events -->
                <div class="section">
                    <div class="section-header">
                        <h3 class="section-title">Active Events</h3>
                        <a href="#" class="view-all">View All →</a>
                    </div>
                    <div class="events-table">
                        <div class="event-row">
                            <div>
                                <div class="event-name">Event Name</div>
                                <div class="event-date">Event Date • Event Venue</div>
                            </div>
                            <div class="event-progress">
                                <div class="progress-bar-mini">
                                    <div class="progress-fill-mini" style="width: 75%"></div>
                                </div>
                                <span class="progress-text">75%</span>
                            </div>
                            <span class="status-badge active">Active</span>
                            <a href="#" class="btn-view">View</a>
                        </div>

                        <div class="event-row">
                            <div>
                                <div class="event-name">Event Name</div>
                                <div class="event-date">Event Date • Event Venue</div>
                            </div>
                            <div class="event-progress">
                                <div class="progress-bar-mini">
                                    <div class="progress-fill-mini" style="width: 45%"></div>
                                </div>
                                <span class="progress-text">45%</span>
                            </div>
                            <span class="status-badge pending">Pending</span>
                            <a href="#" class="btn-view">View</a>
                        </div>

                        <div class="event-row">
                            <div>
                                <div class="event-name">Event Name</div>
                                <div class="event-date">Event Date • Event Venue</div>
                            </div>
                            <div class="event-progress">
                                <div class="progress-bar-mini">
                                    <div class="progress-fill-mini" style="width: 30%"></div>
                                </div>
                                <span class="progress-text">30%</span>
                            </div>
                            <span class="status-badge upcoming">Upcoming</span>
                            <a href="#" class="btn-view">View</a>
                        </div>

                        <div class="event-row">
                            <div>
                                <div class="event-name">Event Name</div>
                                <div class="event-date">Event Date • Event Venue</div>
                            </div>
                            <div class="event-progress">
                                <div class="progress-bar-mini">
                                    <div class="progress-fill-mini" style="width: 60%"></div>
                                </div>
                                <span class="progress-text">60%</span>
                            </div>
                            <span class="status-badge active">Active</span>
                            <a href="#" class="btn-view">View</a>
                        </div>
                    </div>
                </div>

                <!-- Upcoming Deadlines -->
                <div class="section">
                    <div class="section-header">
                        <h3 class="section-title">Upcoming Deadlines</h3>
                    </div>
                    <div class="deadline-item">
                        <div class="deadline-info">
                            <h4>Event Name - Requirement Name</h4>
                            <p>Form code description or requirement details</p>
                        </div>
                        <div class="deadline-date">
                            <strong>Month Day</strong>
                            <span>X days left</span>
                        </div>
                    </div>

                    <div class="deadline-item">
                        <div class="deadline-info">
                            <h4>Event Name - Requirement Name</h4>
                            <p>Form code description or requirement details</p>
                        </div>
                        <div class="deadline-date">
                            <strong>Month Day</strong>
                            <span>X days left</span>
                        </div>
                    </div>

                    <div class="deadline-item">
                        <div class="deadline-info">
                            <h4>Event Name - Requirement Name</h4>
                            <p>Form code description or requirement details</p>
                        </div>
                        <div class="deadline-date">
                            <strong>Month Day</strong>
                            <span>X days left</span>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>
</body>
</html>