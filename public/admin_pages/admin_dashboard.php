<?php
/**
 * Admin Dashboard Page
 * 
 * The main admin landing page providing:
 * - At-a-glance statistics of system activity
 * - Recent event submissions (last 5)
 * - Events requiring attention (Needs Revision status)
 * - Upcoming review deadlines for pending events
 * - User accounts preview (last 5 registered)
 * - Visual dashboard with stat cards and summary alerts
 * 
 * Key Features:
 * - Real-time event status counts (Pending Review, Needs Revision, Approved, Completed)
 * - Document upload progress tracking
 * - Alert section highlighting events needing revision
 * - Two-column layout with recent submissions and attention queue
 * - User registration preview with event counts
 * - Quick links to detailed pages (View All, Open Queue)
 * 
 * Access Control:
 * - Admin-only page (requireAdmin() prevents non-admin access)
 * - Requires valid session
 * 
 * Data Sources:
 * - 6 database queries loading statistics and recent data
 * - Grouped into logical sections for dashboard display
 */

session_start();
require_once __DIR__ . '/../../app/database.php';

requireAdmin();

// ========== ADMIN NAME FOR GREETING ==========
// Get the logged-in admin's username for personalized greeting
// Falls back to "Admin" if username not in session
// htmlspecialchars() prevents XSS if username contains HTML characters
$admin_name = htmlspecialchars($_SESSION["username"] ?? "Admin", ENT_QUOTES, "UTF-8");

/* ========================================
   HELPER FUNCTIONS SECTION
   ======================================== */

/**
 * Normalizes event status string to CSS class name
 * 
 * Converts database status values to valid CSS classes for styling
 * 
 * @param string $status - The event status from database
 *   Examples: "Pending Review", "Needs Revision", "Approved", "Completed"
 * 
 * @returns string - CSS class name (lowercase with hyphens)
 *   Examples: "pending-review", "needs-revision", "approved", "completed"
 * 
 * Implementation:
 * - str_replace(' ', '-'): Converts spaces to hyphens
 * - strtolower(): Converts to lowercase
 * - trim(): Removes leading/trailing whitespace
 * 
 * Usage:
 * <span class="status-<?= normalizeStatusClass($status) ?>">
 * 
 * Rationale:
 * - Status names from database contain spaces and mixed case
 * - CSS classes need to be lowercase with hyphens (convention)
 * - Allows CSS to style different statuses with corresponding classes
 */
function normalizeStatusClass(string $status): string
{
    return strtolower(str_replace(' ', '-', trim($status)));
}

/**
 * Formats datetime for display on dashboard
 * 
 * Converts ISO datetime (from database) to readable format
 * 
 * @param ?string $datetime - ISO datetime string or null/empty
 *   Format from database: "2024-03-22 14:30:00"
 * 
 * @returns string - Formatted datetime for display
 *   Examples: "Mar 22, 2024 2:30 PM"
 *   Empty returns: "No schedule set"
 * 
 * Implementation:
 * - Check if datetime is empty/null
 * - Use PHP date() function with format string
 * - strtotime() converts ISO to Unix timestamp
 * - Format: "M j, Y g:i A" (Month Day, Year Hour:Minutes AM/PM)
 * 
 * Format Characters:
 * - M: Short month name (Jan, Feb, Mar, ...)
 * - j: Day without leading zero (1-31)
 * - Y: 4-digit year
 * - g: Hour in 12-hour format without leading zero (1-12)
 * - i: Minutes with leading zero (00-59)
 * - A: AM/PM in uppercase
 * 
 * Usage:
 * <small><?= formatEventDate($event['start_datetime']) ?></small>
 * 
 * Rationale:
 * - ISO format (8:30) not user-friendly for US audience
 * - AM/PM (12-hour) more intuitive than 24-hour format
 * - Consistent date formatting across dashboard
 */
function formatEventDate(?string $datetime): string
{
    // Return default message if datetime is empty/null
    if (empty($datetime)) {
        return "No schedule set";
    }

    return date("M j, Y g:i A", strtotime($datetime));
}

/**
 * Gets first initial from user name for avatar
 * 
 * Used in user profile cards to display as avatar fallback
 * 
 * @param string $name - Full user name (e.g., "John Smith")
 * @returns string - Single uppercase letter
 *   Examples: "J" from "John Smith", "S" from "Smith", "U" if empty
 * 
 * Implementation:
 * - Trim whitespace from name
 * - Check if name is non-empty
 * - Get first character
 * - Convert to uppercase
 * - Fallback to "U" (User) if name is empty
 * 
 * Usage:
 * <div class="avatar-initial"><?= getUserInitial($user['user_name']) ?></div>
 * 
 * Rationale:
 * - User avatars often show first initial in circular badge
 * - Consistent visual identifier for users
 * - Fallback prevents showing empty avatar
 * - Always uppercase for consistent styling
 */
function getUserInitial(string $name): string
{
    $name = trim($name);
    
    // Return first character (uppercase) if name is not empty
    // Otherwise return 'U' as fallback for "User"
    return $name !== '' ? strtoupper(substr($name, 0, 1)) : 'U';
}

/* ========================================
   STATISTICS QUERIES SECTION
   ======================================== */

/**
 * Load event status counts
 * 
 * Counts events by status to display in stat cards
 * Only counts non-archived, non-system events
 * 
 * Query Logic:
 * - Uses SUM with CASE to count by status
 * - Returns 0 for each status if no events exist
 * - WHERE filters to only user-created events (is_system_event = 0)
 * - AND filters to non-archived events (archived_at IS NULL)
 * 
 * Statuses Counted:
 * - pending_review_count: Awaiting initial admin review
 * - needs_revision_count: Sent back to user for changes
 * - approved_count: Approved by admin
 * - completed_count: Event happened and all requirements done
 * 
 * Result: Single row with 4 columns
 */
$countStatusesSql = "
    SELECT
        SUM(CASE WHEN event_status = 'Pending Review' THEN 1 ELSE 0 END) AS pending_review_count,
        SUM(CASE WHEN event_status = 'Needs Revision' THEN 1 ELSE 0 END) AS needs_revision_count,
        SUM(CASE WHEN event_status = 'Approved' THEN 1 ELSE 0 END) AS approved_count,
        SUM(CASE WHEN event_status = 'Completed' THEN 1 ELSE 0 END) AS completed_count
    FROM events
    WHERE archived_at IS NULL
      AND is_system_event = 0
";
$statusCounts = fetchOne($conn, $countStatusesSql);

/**
 * Count total regular users
 * 
 * Gets number of non-admin user accounts in system
 * 
 * Query Logic:
 * - SELECT COUNT(*): Counts all rows matching criteria
 * - WHERE role = 'user': Only regular users (excludes admins)
 * 
 * Result: Single row with total_users column
 */
$countUsersSql = "
    SELECT COUNT(*) AS total_users
    FROM users
    WHERE role = 'user'
";
$userCountRow = fetchOne($conn, $countUsersSql);

/**
 * Count upcoming events pending review
 * 
 * Counts pending/revision events that haven't happened yet
 * Used for "Review Queue" stat card
 * 
 * Query Logic:
 * - LEFT JOIN event_dates: Some events may not have dates yet
 * - WHERE archived_at IS NULL: Only non-archived
 * - AND is_system_event = 0: Only user-created events
 * - AND event_status IN (...): Only pending or revision status
 * - AND ed.start_datetime IS NOT NULL: Only events with scheduled dates
 * - AND ed.start_datetime >= NOW(): Only upcoming events (today and later)
 * 
 * Result: Single row with total column
 */
$countUpcomingReviewSql = "
    SELECT COUNT(*) AS total
    FROM events e
    LEFT JOIN event_dates ed ON e.event_id = ed.event_id
    WHERE e.archived_at IS NULL
      AND e.is_system_event = 0
      AND e.event_status IN ('Pending Review', 'Needs Revision')
      AND ed.start_datetime IS NOT NULL
      AND ed.start_datetime >= NOW()
";
$reviewDeadlineRow = fetchOne($conn, $countUpcomingReviewSql);

/* ========================================
   RECENT EVENTS QUERY SECTION
   ======================================== */

/**
 * Load recent event submissions
 * 
 * Gets last 5 events in active workflow (not archived, recently created)
 * Displays on left side of dashboard for quick review
 * 
 * Join Strategy:
 * - INNER JOIN users: Get submitter info (required)
 * - LEFT JOIN event_dates: Optional (many events have dates)
 * - LEFT JOIN event_location: Optional (many have venues)
 * 
 * Filtering:
 * - archived_at IS NULL: Active events only
 * - is_system_event = 0: User submissions, not system events
 * - event_status IN (...): Active workflow (not completed/archived)
 * 
 * Sorting:
 * - created_at DESC: Most recent first
 * - LIMIT 5: Show last 5 submissions
 * 
 * Data Points per Event:
 * - event_name, event_status, docs_total, docs_uploaded: Progress tracking
 * - user_name, org_body: Submitter details
 * - start_datetime: When event is scheduled
 * - venue_platform: Where event is held
 * 
 * Used for: Document upload progress bars, status badges
 */
$fetchRecentEventsSql = "
    SELECT
        e.event_id,
        e.event_name,
        e.event_status,
        e.docs_total,
        e.docs_uploaded,
        e.created_at,
        e.updated_at,
        e.organizing_body,
        u.user_name,
        u.org_body,
        ed.start_datetime,
        el.venue_platform
    FROM events e
    INNER JOIN users u
        ON e.user_id = u.user_id
    LEFT JOIN event_dates ed
        ON e.event_id = ed.event_id
    LEFT JOIN event_location el
        ON e.event_id = el.event_id
    WHERE e.archived_at IS NULL
      AND e.is_system_event = 0
      AND e.event_status IN ('Pending Review', 'Needs Revision', 'Approved')
    ORDER BY e.created_at DESC
    LIMIT 5
";
$recent_events = fetchAll($conn, $fetchRecentEventsSql);

/* ========================================
   EVENTS REQUIRING ATTENTION QUERY SECTION
   ======================================== */

/**
 * Load events marked as "Needs Revision"
 * 
 * These events have been reviewed and require user action before approval
 * Highlights problems/missing requirements for admin to track
 * Shows up to 6 events in attention queue
 * 
 * Filtering:
 * - event_status IN ('Needs Revision'): Only problem events
 * - archived_at IS NULL: Active events only
 * - is_system_event = 0: User submissions
 * 
 * Sorting Strategy:
 * - CASE event_status: Priority by urgency (revisions first)
 * - CASE WHEN ed.start_datetime IS NULL: Events without dates are urgent
 * - ed.start_datetime ASC: Earlier events first (deadline approaching)
 * - e.updated_at ASC: Older unanswered updates highest priority
 * 
 * Result:
 * - Up to 6 events sorted by urgency
 * - Shows events that have been pending longest
 * - Highlights events approaching start dates
 * 
 * Used for: Attention cards section (right side), urgent alert
 */
$fetchAttentionEventsSql = "
    SELECT
        e.event_id,
        e.event_name,
        e.event_status,
        e.docs_total,
        e.docs_uploaded,
        e.updated_at,
        u.user_name,
        u.org_body,
        ed.start_datetime
    FROM events e
    INNER JOIN users u
        ON e.user_id = u.user_id
    LEFT JOIN event_dates ed
        ON e.event_id = ed.event_id
    WHERE e.archived_at IS NULL
      AND e.is_system_event = 0
      AND e.event_status IN ('Needs Revision')
    ORDER BY
        CASE e.event_status
            WHEN 'Needs Revision' THEN 1
            WHEN 'Pending Review' THEN 2
            ELSE 3
        END,
        CASE WHEN ed.start_datetime IS NULL THEN 1 ELSE 0 END,
        ed.start_datetime ASC,
        e.updated_at ASC
    LIMIT 6
";
$attention_events = fetchAll($conn, $fetchAttentionEventsSql);

/* ========================================
   USERS PREVIEW QUERY SECTION
   ======================================== */

/**
 * Load user registration preview
 * 
 * Shows last 5 registered users with event count
 * Helps admin track new user signups
 * 
 * Join Strategy:
 * - LEFT JOIN events: Count user's events
 * - Join filters: Only count non-archived, non-system events
 * 
 * Grouping:
 * - GROUP BY user fields: Aggregate events per user
 * - COUNT(e.event_id) AS total_events: How many events this user created
 * 
 * Filtering:
 * - u.role = 'user': Only regular users, not admins
 * 
 * Sorting:
 * - user_reg_date DESC: Most recently registered first
 * - LIMIT 5: Show last 5 registrations
 * 
 * Data Points per User:
 * - user_name, user_email, org_body: User details
 * - total_events: How many events they've created
 * - user_reg_date: When they registered (for display)
 * 
 * Used for: User registration tracking, user cards
 */
$fetchUsersPreviewSql = "
    SELECT
        u.user_id,
        u.user_name,
        u.user_email,
        u.org_body,
        u.user_reg_date,
        COUNT(e.event_id) AS total_events
    FROM users u
    LEFT JOIN events e
        ON u.user_id = e.user_id
       AND e.archived_at IS NULL
       AND e.is_system_event = 0
    WHERE u.role = 'user'
    GROUP BY u.user_id, u.user_name, u.user_email, u.org_body, u.user_reg_date
    ORDER BY u.user_reg_date DESC
    LIMIT 5
";
$users_preview = fetchAll($conn, $fetchUsersPreviewSql);

/* ========================================
   REVIEW DEADLINES QUERY SECTION
   ======================================== */

/**
 * Load upcoming review deadlines
 * 
 * Events pending review sorted by event start date (deadline to review before event)
 * Helps admin prioritize review workload by urgency
 * 
 * Filtering:
 * - event_status IN ('Pending Review'): Only events awaiting initial review
 * - ed.start_datetime IS NOT NULL: Only events with scheduled dates
 * - archived_at IS NULL: Active events only
 * - is_system_event = 0: User submissions
 * 
 * Sorting:
 * - ed.start_datetime ASC: Closest events first (most urgent)
 * - LIMIT 5: Show 5 most urgent reviews
 * 
 * Data Points:
 * - event_name, user_name, org_body: Context
 * - start_datetime: Event start date (review deadline)
 * 
 * Used for: Review deadlines section displaying timeline
 */
$fetchReviewDeadlinesSql = "
    SELECT
        e.event_id,
        e.event_name,
        e.event_status,
        u.user_name,
        u.org_body,
        ed.start_datetime
    FROM events e
    INNER JOIN users u
        ON e.user_id = u.user_id
    LEFT JOIN event_dates ed
        ON e.event_id = ed.event_id
    WHERE e.archived_at IS NULL
      AND e.is_system_event = 0
      AND e.event_status IN ('Pending Review')
      AND ed.start_datetime IS NOT NULL
    ORDER BY ed.start_datetime ASC
    LIMIT 5
";
$review_deadlines = fetchAll($conn, $fetchReviewDeadlinesSql);

/* ========================================
   EXTRACT STATISTICS FOR DISPLAY
   ======================================== */

/**
 * Convert database counts to PHP variables for template use
 * 
 * Safely cast to int to handle null/empty results
 * Provides default 0 if result is missing
 * 
 * fetchOne() returns associative array or null
 * Array access with ?? operator provides fallback
 * (int) cast ensures numeric value for arithmetic/display
 */
$pending_review_count = (int) ($statusCounts['pending_review_count'] ?? 0);
$needs_revision_count = (int) ($statusCounts['needs_revision_count'] ?? 0);
$approved_count = (int) ($statusCounts['approved_count'] ?? 0);
$completed_count = (int) ($statusCounts['completed_count'] ?? 0);
$total_users = (int) ($userCountRow['total_users'] ?? 0);
$review_queue_count = (int) ($reviewDeadlineRow['total'] ?? 0);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Admin Dashboard - HAUCREDIT</title>
    <link rel="stylesheet" href="<?= PUBLIC_URL ?>assets/styles/layout.css" />
    <link rel="stylesheet" href="<?= PUBLIC_URL ?>assets/styles/home_styles.css" />
    <link rel="stylesheet" href="<?= PUBLIC_URL ?>assets/styles/admin_dashboard.css" />
</head>

<body>
    <div class="app">
        <div class="sidebar-overlay" id="sidebarOverlay" hidden></div>

        <?php include PUBLIC_PATH . 'assets/includes/admin_nav.php'; ?>

        <main class="main">
            <header class="topbar">
                <button class="hamburger" id="menuBtn" type="button" aria-label="Open menu">☰</button>

                <div class="title-wrap">
                    <h1>Admin Dashboard</h1>
                    <p>Welcome back, <?= $admin_name ?></p>
                </div>
            </header>

            <section class="home-content">
                <!-- Stats -->
                <section class="dashboard-stats">
                    <article class="stat-card">
                        <div class="stat-icon"><i class="fa-solid fa-hourglass-half"></i></div>
                        <div>
                            <div class="stat-number"><?= $pending_review_count ?></div>
                            <div class="stat-label">Pending Review</div>
                        </div>
                    </article>

                    <article class="stat-card">
                        <div class="stat-icon"><i class="fa-solid fa-rotate-right"></i></div>
                        <div>
                            <div class="stat-number"><?= $needs_revision_count ?></div>
                            <div class="stat-label">Needs Revision</div>
                        </div>
                    </article>

                    <article class="stat-card">
                        <div class="stat-icon"><i class="fa-solid fa-circle-check"></i></div>
                        <div>
                            <div class="stat-number"><?= $approved_count ?></div>
                            <div class="stat-label">Approved</div>
                        </div>
                    </article>

                    <article class="stat-card">
                        <div class="stat-icon"><i class="fa-solid fa-flag-checkered"></i></div>
                        <div>
                            <div class="stat-number"><?= $completed_count ?></div>
                            <div class="stat-label">Completed</div>
                        </div>
                    </article>

                    <article class="stat-card">
                        <div class="stat-icon"><i class="fa-solid fa-users"></i></div>
                        <div>
                            <div class="stat-number"><?= $total_users ?></div>
                            <div class="stat-label">Users</div>
                        </div>
                    </article>

                    <article class="stat-card">
                        <div class="stat-icon"><i class="fa-solid fa-calendar-exclamation"></i></div>
                        <div>
                            <div class="stat-number"><?= $review_queue_count ?></div>
                            <div class="stat-label">Review Queue</div>
                        </div>
                    </article>
                </section>

                <?php if ($needs_revision_count > 0): ?>
                    <section class="overdue-alert">
                        <div>
                            <strong><?= $needs_revision_count ?> event<?= $needs_revision_count !== 1 ? 's' : '' ?> need
                                attention</strong>
                            <span class="overdue-alert-subtext">Events needing revision are waiting for admin
                                action.</span>
                        </div>
                        <a href="admin_events.php?status=Needs+Revision" class="btn-outline">Open Queue</a>
                    </section>
                <?php endif; ?>

                <section class="dashboard-grid">
                    <!-- Left column -->
                    <div>
                        <section class="dashboard-card">
                            <header class="card-header">
                                <h2>Recent Event Submissions</h2>
                                <a href="admin_events.php">View All →</a>
                            </header>

                            <div class="recent-events-list">
                                <?php if (!empty($recent_events)): ?>
                                    <?php foreach ($recent_events as $event): ?>
                                        <?php
                                        $total_docs = (int) ($event['docs_total'] ?? 0);
                                        $uploaded_docs = (int) ($event['docs_uploaded'] ?? 0);
                                        $progress = $total_docs > 0 ? round(($uploaded_docs / $total_docs) * 100) : 0;
                                        $status_class = normalizeStatusClass($event['event_status'] ?? 'Pending Review');
                                        ?>
                                        <a class="recent-event-item"
                                            href="admin_manage_event.php?id=<?= (int) $event['event_id'] ?>">
                                            <div class="recent-event-info">
                                                <div class="recent-event-title"><?= htmlspecialchars($event['event_name']) ?>
                                                </div>
                                                <div class="recent-event-meta">
                                                    <?= htmlspecialchars($event['user_name']) ?> •
                                                    <?= htmlspecialchars($event['org_body']) ?> •
                                                    <?= htmlspecialchars($event['venue_platform'] ?? 'No venue set') ?>
                                                </div>

                                                <div class="progress-mini">
                                                    <div class="progress-bar-mini">
                                                        <div class="progress-fill-mini" style="width: <?= $progress ?>%;"></div>
                                                    </div>
                                                    <span><?= $uploaded_docs ?>/<?= $total_docs ?></span>
                                                </div>
                                            </div>

                                            <div class="recent-event-side">
                                                <span class="status-badge-small status-<?= htmlspecialchars($status_class) ?>">
                                                    <?= htmlspecialchars($event['event_status']) ?>
                                                </span>
                                                <small><?= formatEventDate($event['start_datetime'] ?? null) ?></small>
                                            </div>
                                        </a>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="empty-state-small">
                                        <p>No recent event submissions.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </section>

                        <section class="dashboard-card attention-section">
                            <header class="card-header">
                                <h2>Events Requiring Attention</h2>
                                <a href="admin_events.php?status=Needs+Revision">Open Queue →</a>
                            </header>

                            <div class="attention-events">
                                <?php if (!empty($attention_events)): ?>
                                    <?php foreach ($attention_events as $event): ?>
                                        <?php
                                        $status = $event['event_status'] ?? 'Pending Review';
                                        $status_class = normalizeStatusClass($status);
                                        $card_class = $status === 'Needs Revision' ? 'urgent' : 'warning';
                                        ?>
                                        <a class="attention-card <?= $card_class ?>"
                                            href="admin_manage_event.php?id=<?= (int) $event['event_id'] ?>">
                                            <div class="attention-icon">
                                                <i
                                                    class="fa-solid <?= $status === 'Needs Revision' ? 'fa-triangle-exclamation' : 'fa-hourglass-half' ?>"></i>
                                            </div>

                                            <div class="attention-content">
                                                <h4><?= htmlspecialchars($event['event_name']) ?></h4>
                                                <p>
                                                    <?= htmlspecialchars($event['user_name']) ?> •
                                                    <?= htmlspecialchars($event['org_body']) ?>
                                                </p>
                                                <small>Starts: <?= formatEventDate($event['start_datetime'] ?? null) ?></small>
                                            </div>

                                            <div class="attention-meta">
                                                <span class="status-badge-small status-<?= htmlspecialchars($status_class) ?>">
                                                    <?= htmlspecialchars($status) ?>
                                                </span>
                                            </div>
                                        </a>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="empty-state-small">
                                        <p>No events currently require attention.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </section>
                    </div>

                    <!-- Right column -->
                    <div>
                        <section class="dashboard-card" style="margin-bottom: 24px;">
                            <header class="card-header">
                                <h2>Upcoming Review Deadlines</h2>
                                <a href="admin_events.php?status=Pending+Review">View All →</a>
                            </header>

                            <div>
                                <?php if (!empty($review_deadlines)): ?>
                                    <?php foreach ($review_deadlines as $item): ?>
                                        <?php $status_class = normalizeStatusClass($item['event_status'] ?? 'Pending Review'); ?>
                                        <a class="deadline-item"
                                            href="admin_manage_event.php?id=<?= (int) $item['event_id'] ?>">
                                            <div class="deadline-info">
                                                <div class="deadline-title"><?= htmlspecialchars($item['event_name']) ?></div>
                                                <div class="deadline-meta">
                                                    <?= htmlspecialchars($item['user_name']) ?> •
                                                    <?= htmlspecialchars($item['org_body']) ?>
                                                </div>
                                            </div>
                                            <div class="deadline-side">
                                                <span class="status-badge-small status-<?= htmlspecialchars($status_class) ?>">
                                                    <?= htmlspecialchars($item['event_status']) ?>
                                                </span>
                                                <div class="deadline-date">
                                                    <?= formatEventDate($item['start_datetime'] ?? null) ?>
                                                </div>
                                            </div>
                                        </a>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="empty-state-small">
                                        <p>No upcoming review deadlines.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </section>

                        <section class="dashboard-card">
                            <header class="card-header">
                                <h2>Users Preview</h2>
                                <a href="admin_users.php">View All →</a>
                            </header>

                            <div class="users-grid">
                                <?php if (!empty($users_preview)): ?>
                                    <?php foreach ($users_preview as $user): ?>
                                        <a class="user-card" href="admin_users.php">
                                            <div class="user-avatar-initial">
                                                <?= htmlspecialchars(getUserInitial($user['user_name'] ?? 'U')) ?>
                                            </div>

                                            <div class="user-info">
                                                <div class="user-name"><?= htmlspecialchars($user['user_name']) ?></div>
                                                <div class="user-email"><?= htmlspecialchars($user['user_email']) ?></div>
                                                <div class="user-org"><?= htmlspecialchars($user['org_body']) ?></div>
                                                <small>
                                                    <?= (int) $user['total_events'] ?>
                                                    event<?= ((int) $user['total_events'] !== 1 ? 's' : '') ?> •
                                                    Registered <?= date('M j, Y', strtotime($user['user_reg_date'])) ?>
                                                </small>
                                            </div>
                                        </a>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="empty-state-small">
                                        <p>No users found.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </section>
                    </div>
                </section>

                <?php include PUBLIC_PATH . 'assets/includes/footer.php'; ?>
            </section>
        </main>
    </div>

    <script src="<?= APP_URL ?>script/layout.js?v=1"></script>
</body>

</html>