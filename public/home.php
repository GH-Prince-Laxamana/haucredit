<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit();
}

require_once("../app/security_headers.php");
send_security_headers();

$username = htmlspecialchars($_SESSION["username"], ENT_QUOTES, "UTF-8");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>HAUCredit - Dashboard</title>
    <link rel="stylesheet" href="assets/styles/layout.css" />
    <style>
        /* =========================
           THEME / VARIABLES
           ========================= */
        :root {
            --burgundy: #4b0014;
            --offwhite: #f9f3ea;
            --gold: #c2a14d;
            --shadow: 0 10px 25px rgba(0, 0, 0, .10);
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
            background: rgba(249, 243, 234, .35);
            border: 2px solid rgba(194, 161, 77, .55);
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
            background: rgba(249, 243, 234, .12);
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
            background: rgba(194, 161, 77, .18);
            border: 1px solid rgba(194, 161, 77, .45);
        }

        .account {
            margin-top: auto;
            padding-top: 14px;
        }

        .account-btn {
            width: 100%;
            border: 1px solid rgba(249, 243, 234, .25);
            background: rgba(249, 243, 234, .10);
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
            background: rgba(249, 243, 234, .45);
            border: 1px solid rgba(194, 161, 77, .6);
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
            border: 1px solid rgba(194, 161, 77, .7);
            background: var(--offwhite);
            cursor: pointer;
            box-shadow: 0 6px 16px rgba(0, 0, 0, .06);
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
            border: 1px solid rgba(194, 161, 77, .7);
            background: var(--offwhite);
            color: var(--burgundy);
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 6px 16px rgba(0, 0, 0, .06);
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
            border: 1px solid rgba(194, 161, 77, .15);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, .15);
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

        .stat-icon.blue {
            background: linear-gradient(135deg, #3b82f6 0%, #60a5fa 100%);
        }

        .stat-icon.green {
            background: linear-gradient(135deg, #059669 0%, #10b981 100%);
        }

        .stat-icon.amber {
            background: linear-gradient(135deg, #d97706 0%, #f59e0b 100%);
        }

        .stat-icon.purple {
            background: linear-gradient(135deg, #7c3aed 0%, #a78bfa 100%);
        }

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
            border: 1px solid rgba(194, 161, 77, .15);
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
            background: rgba(194, 161, 77, .02);
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
            .sidebar {
                width: 210px;
            }

            .event-row {
                grid-template-columns: 1fr;
                gap: 10px;
            }
        }

        @media (max-width: 640px) {
            .app {
                flex-direction: column;
            }

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

            .nav-item {
                white-space: nowrap;
            }

            .account {
                display: none;
            }
        }
    </style>
</head>
<body>

<div class="navbar">
    <div class="logo"></div>

    <div class="navlinks">
        <a href="home.php">Dashboard</a>
        <a href="create_event.php">Create Event</a>
        <a href="calendar.php">Calendar</a>
        <a href="about.php">About Us</a>
    </div>

    <div class="search">
        <input type="text" placeholder="Search">
    </div>
</div>

<div class="home-wrap">
    <h1>Welcome, <?= $username ?>!</h1>

    <form action="logout.php" method="post">
        <button class="logout-btn" type="submit">Logout</button>
    </form>
</div>

</body>
</html>
