<?php
/**
 * =====================================================
 * EVENT MANAGEMENT ADMIN PAGE
 * =====================================================
 * 
 * Purpose:
 * Displays a comprehensive admin interface for managing all submitted events.
 * Provides filtering by status, organization, and search terms. Supports 
 * pagination and multiple sort/view modes for event list management.
 * 
 * Key Features:
 * 1. Real-time event status filtering (All, Pending Review, Needs Revision, Approved, Completed)
 * 2. Organization-based filtering with dynamic dropdown populated from database
 * 3. Multi-field search (event name, user name, email, org, activity type, venue)
 * 4. Pagination with configurable page size (10 events per page)
 * 5. Summary statistics (total counts, status breakdowns)
 * 6. Table view with event details, submission info, document progress, action links
 * 7. Dashboard deep-links (filter=attention, filter=review_queue, view=recent)
 * 8. Sort options: Default priority order, start_asc, start_desc, updated_desc
 * 
 * Access Control:
 * Requires admin role (enforced by requireAdmin() function from database.php)
 * 
 * Database Connection:
 * Uses MySQLi prepared statements via query helper functions (fetchOne, fetchAll)
 * from query_builder_functions.php for safe SQL execution
 * 
 * URL Parameters:
 * - status: Event status for filtering ('all', 'Pending Review', 'Needs Revision', 'Approved', 'Completed')
 * - org: Organization name for filtering
 * - search: Multi-field search term
 * - filter: Special filters ('attention' for pending/revision, 'review_queue' for pending+dates)
 * - view: View mode ('recent' changes sort and status filter)
 * - sort: Sort method ('start_asc', 'start_desc', 'updated_desc', default is priority+date)
 * - page: Current page number (1-based, default 1)
 * 
 * Template Variables Set:
 * - $page_title: Dynamic header title based on filters/view
 * - $page_subtitle: Contextual description for current view
 * - $total_events, $pending_review_count, $needs_revision_count, etc: Summary counts
 * - $events: Array of event records for current page
 * - $orgOptions: Array of distinct organizations in database
 * - $total_pages, $showing_from, $showing_to: Pagination info
 */

session_start();
require_once __DIR__ . '/../../app/database.php';

requireAdmin();

$admin_name = htmlspecialchars($_SESSION["username"] ?? "Admin", ENT_QUOTES, "UTF-8");

// Extract and validate filter parameters from URL query string
// trim() removes whitespace, ?? provides default empty string, max(1, ...) ensures page >= 1
$status_filter = trim($_GET['status'] ?? 'all');
$org_filter = trim($_GET['org'] ?? '');
$search = trim($_GET['search'] ?? '');
$filter = trim($_GET['filter'] ?? '');
$view = trim($_GET['view'] ?? '');
$sort = trim($_GET['sort'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));

// Pagination configuration
// per_page: Number of events displayed per page for table view
// offset: Calculate starting row position for LIMIT clause (page 1 = offset 0, page 2 = offset 10, etc)
$per_page = 10;
$offset = ($page - 1) * $per_page;

/* ================= HELPER FUNCTIONS ================= */
function normalizeStatusClass(string $status): string
{
    return strtolower(str_replace(' ', '-', trim($status)));
}

/**
 * Validate that status filter is in allowed list
 * 
 * Purpose:
 * Prevent SQL injection or invalid filter values by whitelisting allowed statuses.
 * Used before applying status filter to WHERE clause (security + data integrity).
 * 
 * @param string $status The status value to validate (typically from URL parameter)
 * @return bool True if status is in whitelist, false otherwise
 * 
 * Allowed Values:
 * - 'all': Special value meaning no status filter (show all statuses)
 * - 'Pending Review': Events awaiting admin review
 * - 'Needs Revision': Events returned for user modification
 * - 'Approved': Events approved by admin but not yet completed
 * - 'Completed': Closed events, no further action needed
 * 
 * Reasoning:
 * Uses in_array() with strict type checking (third param = true) to prevent
 * "0" (string zero) matching false, or other type coercion surprises
 */
function isValidStatusFilter(string $status): bool
{
    $allowed = ['all', 'Pending Review', 'Needs Revision', 'Approved', 'Completed'];
    return in_array($status, $allowed, true);
}

/**
 * Build query string for URLs, preserving current filters and adding/overriding specific params
 * 
 * Purpose:
 * Construct URL query strings for pagination links, filter tabs, and sort buttons.
 * Merges current $_GET params with overrides, removes empty values, returns encoded string.
 * 
 * Example Usage:
 * - buildQueryString(['page' => 2]) → Keeps current filters, changes page to 2
 * - buildQueryString(['status' => 'Approved']) → Changes status, keeps search/org, resets page to 1
 * 
 * @param array $overrides Key-value pairs to add/override in query string (default = empty array)
 * @return string Encoded query string ready for URL (e.g., "status=Approved&page=1&search=event")
 * 
 * Implementation:
 * - array_merge($_GET, $overrides): Overlay overrides on top of current GET params
 * - Loop unsets empty/null values to prevent URL clutter ("param=&other=val" → "other=val")
 * - http_build_query(): Encodes special characters (&, =, spaces) for safe URL usage
 * 
 * Note:
 * Relies on $_GET being populated by browser. All user input is ultimately escaped
 * when printed in href="" or value="" attributes using htmlspecialchars()
 */
function buildQueryString(array $overrides = []): string
{
    // Start with current query parameters and apply overrides on top
    $params = array_merge($_GET, $overrides);

    // Remove any empty or null values to keep URLs clean
    // Empty query params add noise but don't filter anything (e.g., search=&status=all)
    foreach ($params as $key => $value) {
        if ($value === '' || $value === null) {
            unset($params[$key]);
        }
    }

    // Encode special characters and return as URL-ready query string
    return http_build_query($params);
}

// Validate status filter against whitelist, reset to 'all' if invalid
// Prevents SQLi via malformed status values like "'; DROP TABLE users; --"
if (!isValidStatusFilter($status_filter)) {
    $status_filter = 'all';
}

/* ================= DYNAMIC PAGE TITLE/SUBTITLE ================= */

// Set dynamic page title and subtitle based on applied filters and view modes
// Provides contextual information about what data is being displayed
// Helps admins understand the current filtering context at a glance

// Default title: All non-draft events that aren't system events
$page_title = "Event Management";
$page_subtitle = "Review and manage all submitted events.";

// When filter=attention, show only pending/revision items requiring admin action
if ($filter === 'attention') {
    $page_title = "Events Requiring Attention";
    $page_subtitle = "Pending review and revision items that need admin action.";
}
// When view=recent, show newest submissions across all non-draft statuses (except archived)
elseif ($view === 'recent') {
    $page_title = "Recent Event Submissions";
    $page_subtitle = "Newest submitted events from organizations.";
}
// When looking at pending review sorted by date ascending - shows upcoming review deadlines
elseif ($status_filter === 'Pending Review' && $sort === 'start_asc') {
    $page_title = "Upcoming Review Deadlines";
    $page_subtitle = "Pending review events sorted by nearest schedule.";
}

/* ================= SUMMARY STATISTICS QUERY ================= */

/**
 * Database Query: Event Status Counts
 * 
 * Purpose:
 * Retrieve aggregated statistics about all events in the system:
 * - Total event count
 * - Count broken down by each status type (Pending Review, Needs Revision, Approved, Completed)
 * Results displayed in status cards at top of page for admin dashboard awareness.
 * 
 * Key Filters:
 * - archived_at IS NULL: Exclude archived/deleted events from statistics
 * - is_system_event = 0: Exclude internal system events, show only user-submitted events
 * - event_status <> 'Draft': Exclude draft events (in-progress, not submitted)
 * Reasoning: Admin view should show only "real" submitted events, not drafts or system artifacts
 * 
 * Aggregation Method:
 * SUM(CASE WHEN condition THEN 1 ELSE 0 END): Count-by-status pattern
 * - CASE evaluates condition for each row
 * - IF true: counts as 1, IF false: counts as 0
 * - SUM aggregates 1s and 0s into count; much more flexible than multiple GROUP BY
 * Example: "pending_review_count" = count of all rows where event_status = 'Pending Review'
 * 
 * Result Format:
 * Single row object with keys: total_events, pending_review_count, needs_revision_count,
 * approved_count, completed_count (all integer values or NULL if no matching events)
 */
$statusCountsSql = "
    SELECT
        COUNT(*) AS total_events,
        SUM(CASE WHEN e.event_status = 'Pending Review' THEN 1 ELSE 0 END) AS pending_review_count,
        SUM(CASE WHEN e.event_status = 'Needs Revision' THEN 1 ELSE 0 END) AS needs_revision_count,
        SUM(CASE WHEN e.event_status = 'Approved' THEN 1 ELSE 0 END) AS approved_count,
        SUM(CASE WHEN e.event_status = 'Completed' THEN 1 ELSE 0 END) AS completed_count
    FROM events e
    WHERE e.archived_at IS NULL
      AND e.is_system_event = 0
      AND e.event_status <> 'Draft'
";

// Execute status counts query and fetch single row of aggregated results
// Handles NULL return gracefully (will use ?? operator later to set defaults)
$statusCounts = fetchOne($conn, $statusCountsSql);

/* ================= ORGANIZATION FILTER OPTIONS QUERY ================= */

/**
 * Database Query: Distinct Organization Names
 * 
 * Purpose:
 * Populate the organization filter dropdown with all organizations that have
 * submitted events. Helps admin quickly filter by organizational affiliation.
 * 
 * Key Logic:
 * - DISTINCT u.org_body: Get unique organization names (prevents duplicates in dropdown)
 * - INNER JOIN to events: Only show orgs that have submitted events (not unused orgs)
 * - WHERE checks: Same filters as status counts (archived_at, is_system_event, Draft status)
 * - u.role = 'user': Only user accounts (not admin-created system orgs)
 * - org_body IS NOT NULL AND org_body != '': Exclude empty/null org names
 * - ORDER BY org_body ASC: Alphabetical for easier scanning in dropdown
 * 
 * Result Format:
 * Array of associative arrays, each with key 'org_body' containing organization name string.
 * Example: [['org_body' => 'Engineering Club'], ['org_body' => 'Student Government'], ...]
 */
$orgOptionsSql = "
    SELECT DISTINCT u.org_body
    FROM users u
    INNER JOIN events e
        ON u.user_id = e.user_id
    WHERE e.archived_at IS NULL
      AND e.is_system_event = 0
      AND e.event_status <> 'Draft'
      AND u.role = 'user'
      AND u.org_body IS NOT NULL
      AND u.org_body != ''
    ORDER BY u.org_body ASC
";

// Execute organization options query and fetch all matching rows
$orgOptions = fetchAll($conn, $orgOptionsSql);

/* ================= BASE SQL TEMPLATE ================= */

/**
 * Base SQL Query Template
 * 
 * Purpose:
 * Defines the FROM clause and core JOINs for all event queries on this page.
 * Provides left outer query selection with optional related data (types, locations, dates).
 * Acts as foundation for count query, list query, with WHERE/ORDER clauses added separately.
 * 
 * JOIN Strategy:
 * 
 * events e → users u (INNER JOIN on e.user_id = u.user_id)
 *   Mandatory join to get user details (name, org, email) shown in table
 *   INNER = exclude any corrupted events without user (data integrity check)
 * 
 * events e → event_type et (LEFT JOIN on e.event_id = et.event_id)
 *   Optional join for activity type/background (some events may not have type set)
 *   LEFT = include events even if type not defined (still shows as N/A in table)
 * 
 * event_type et → config_activity_types cat (LEFT JOIN on et.activity_type_id = ...)
 *   Denormalization: Reduces query to one JOIN instead of two separate queries
 *   LEFT = handle NULL activity_type_id gracefully (shows as N/A)
 * 
 * event_type et → config_background_options cbo (LEFT JOIN on et.background_id = ...)
 *   Provides human-readable background name (Indoor/Outdoor/Virtual/etc)
 *   LEFT = handle missing background (still shows event without background context)
 * 
 * events e → event_dates ed (LEFT JOIN on e.event_id = ed.event_id)
 *   Optional dates for filtering/sorting by event start date
 *   LEFT = include events with no scheduled dates (NULL start_datetime)
 *   Note: event_dates might have multiple rows per event; no GROUP By, duplicates possible
 * 
 * events e → event_location el (LEFT JOIN on e.event_id = el.event_id)
 *   Optional venue information shown in table (reduces queries needed)
 *   LEFT = include events with no location/venue set
 *   Note: Similar to event_dates, may duplicate rows if multiple locations per event
 * 
 * WHERE Clause:
 * - e.archived_at IS NULL: Exclude deleted/archived events (soft delete pattern)
 * - e.is_system_event = 0: Exclude internal test/system events from user view
 * - e.event_status <> 'Draft': Exclude in-progress draft forms (not yet submitted)
 * These consistent WHERE filters are applied to every query on this page
 */
$baseSql = "
    FROM events e
    INNER JOIN users u
        ON e.user_id = u.user_id
    LEFT JOIN event_type et
        ON e.event_id = et.event_id
    LEFT JOIN config_activity_types cat
        ON et.activity_type_id = cat.activity_type_id
    LEFT JOIN config_background_options cbo
        ON et.background_id = cbo.background_id
    LEFT JOIN event_dates ed
        ON e.event_id = ed.event_id
    LEFT JOIN event_location el
        ON e.event_id = el.event_id
    WHERE e.archived_at IS NULL
      AND e.is_system_event = 0
      AND e.event_status <> 'Draft'
";

// Initialize prepared statement parameters array and type string
// These build up as we add filters to the WHERE clause
// params: array of values bound to ? placeholders
// types: string of type indicators ("s" = string, "i" = int, "d" = double)
// MySQLi prepared statements use typed binding for SQL injection prevention
$params = [];
$types = "";

/* ================= FILTER: DASHBOARD DEEP-LINKS ================= */

/**
 * Special Filter: filter=attention
 * 
 * Behavior:
 * When filter=attention in URL, restrict results to events requiring admin action
 * Shows only events in 'Pending Review' or 'Needs Revision' status
 * Used by dashboard.php attention queue widget to link to this page
 * 
 * Reasoning:
 * Admin dashboard shows attention queue (5 newest events needing action).
 * Click "View All" → admin_events.php?filter=attention → shows all {Pending Review, Needs Revision}
 */
if ($filter === 'attention') {
    $baseSql .= " AND e.event_status IN ('Pending Review', 'Needs Revision')";
}
// Special Filter: filter=review_queue
// Restriction: Only pending/revision events that have scheduled dates NOT NULL
// Used by dashboard deep-link to review queue (events that are scheduled for deadline checking)
elseif ($filter === 'review_queue') {
    $baseSql .= " AND e.event_status IN ('Pending Review', 'Needs Revision')";
    $baseSql .= " AND ed.start_datetime IS NOT NULL";
}

/* ================= FILTER: STATUS ================= */

/**
 * Status Filter Application
 * 
 * Logic:
 * If user selected specific status from dropdown (not 'all'), add WHERE condition
 * Matches e.event_status = ? where ? is the selected status value
 * 
 * Valid Status Values (re-checked in isValidStatusFilter()):
 * - 'all': Special no-op value, skips this filter block
 * - 'Pending Review': Events awaiting admin review (initial submission state)
 * - 'Needs Revision': Events returned to user for fixes/changes
 * - 'Approved': Events passed review, awaiting completion
 * - 'Completed': Events finished, closed from admin perspective
 * 
 * Type Binding:
 * Adds "s" (string type) to $types for prepared statement type string
 * Appends actual status value to $params for value binding
 */
if ($status_filter !== 'all') {
    $baseSql .= " AND e.event_status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

/* ================= FILTER: ORGANIZATION ================= */

/**
 * Organization Filter Application
 * 
 * Logic:
 * If user selected specific org from dropdown (not empty), filter to that organization
 * Matches u.org_body = ? where ? is the selected organizational affiliation
 * 
 * Use Cases:
 * - Admin views only "Engineering Club" events
 * - Admin views events from specific department or student body
 * - Useful for large institutions with many competing organizations
 * 
 * Note:
 * Relies on org_body being properly set in users table (see user registration/onboarding)
 * Empty org_body handled in organization options query (excluded from dropdown)
 */
if ($org_filter !== '') {
    $baseSql .= " AND u.org_body = ?";
    $params[] = $org_filter;
    $types .= "s";
}

/* ================= FILTER: MULTI-FIELD SEARCH ================= */

/**
 * Full-Text Search Across Multiple Event Fields
 * 
 * Logic:
 * If user entered search term, apply LIKE pattern match across 7 different fields
 * Enables admins to find events by partial name, user, org, type, or venue
 * Case-insensitive (LIKE in MySQL default case-insensitive for non-utf8 collations)
 * 
 * Searched Fields:
 * 1. e.event_name: Event title entered by user
 * 2. u.user_name: Full name of submitting user
 * 3. u.user_email: Email of submitting user
 * 4. u.org_body: Organization name
 * 5. cat.activity_type_name: Activity type (e.g., "Service", "Leadership")
 * 6. cbo.background_name: Background setting (e.g., "Indoor", "Outdoor")
 * 7. el.venue_platform: Venue/location (e.g., "Zoom", "Memorial Hall")
 * 
 * Implementation:
 * - "%" + search + "%" pattern for substring matching
 * - ( ... OR ... OR ... ) group ensures any field match returns event
 * - Each field gets same $like value (repeats in $params)
 * - Type string prepended with 7x "s" (string types for LIKE%)
 * 
 * Performance Note:
 * No indexes on these text fields may cause full table scan.
 * Acceptable for moderate dataset sizes; production might need fulltext index.
 */
if ($search !== '') {
    $baseSql .= "
        AND (
            e.event_name LIKE ?
            OR u.user_name LIKE ?
            OR u.user_email LIKE ?
            OR u.org_body LIKE ?
            OR cat.activity_type_name LIKE ?
            OR cbo.background_name LIKE ?
            OR el.venue_platform LIKE ?
        )
    ";
    // Add search term surrounded by % wildcards for substring matching
    $like = "%" . $search . "%";
    // Push same $like value 7 times (once per OR condition)
    array_push($params, $like, $like, $like, $like, $like, $like, $like);
    // Add 7 string type indicators ("s") to type string
    $types .= "sssssss";
}

/* ================= FILTER: VIEW MODE OVERRIDE ================= */

/**
 * View Mode Filter: view=recent
 * 
 * Behavior:
 * When view=recent parameter passed, restrict events to 3 statuses:
 * - Pending Review: Just submitted, awaiting review
 * - Needs Revision: Returned to user, awaiting resubmission
 * - Approved: Passed review, completion pending
 * 
 * Excluded Status: Completed events not shown (already handled, not recent news)
 * Excluded Status: Draft events already filtered in base WHERE
 * 
 * Purpose:
 * Provides "Recent Submissions" view for quick scanning of new work
 * Paired with 'created_at DESC' sort (see ORDER BY section)
 * Commonly linked from dashboard.php recent_events widget
 */
if ($view === 'recent') {
    $baseSql .= " AND e.event_status IN ('Pending Review', 'Needs Revision', 'Approved')";
}

/* ================= ORDER BY SORTING ================= */

/**
 * Default Sort Order (Priority-Based with Urgency Hints)
 * 
 * Sort Hierarchy:
 * 1. Event Status Priority (CASE statement):
 *    - "Needs Revision" (priority 1) shown first (most urgent action needed)
 *    - "Pending Review" (priority 2) follows (awaiting admin action)
 *    - "Approved" (priority 3) comes next (less urgent, completion pending)
 *    - "Completed" (priority 4) last (already handled)
 *    - Everything else (priority 5) (shouldn't happen with our WHERE filters)
 * 
 * 2. Date Urgency Indicator:
 *    - CASE WHEN ed.start_datetime IS NULL THEN 1 ELSE 0 END
 *    - Events WITHOUT scheduled dates (priority 1) shown before events WITH dates
 *    - Reasoning: Unscheduled events might be blocking other reviews; date-certain events are planned
 * 
 * 3. Event Date Ascending:
 *    - ed.start_datetime ASC: Among same status/date-null, sort by earliest scheduled date first
 *    - Shows soonest-occurring events at top (urgency by proximity)
 * 
 * 4. Creation Order Descending:
 *    - e.created_at DESC: Tiebreaker for same status/date. Newest submissions shown first
 *    - Helps catch recent resubmissions or new entries
 * 
 * Overall Result:
 * Returns urgent, needs-revision items first, then pending reviews, grouped by date.
 * Same behavior as admin_dashboard.php "attention events" sort.
 */
$orderBy = "
    ORDER BY
        CASE e.event_status
            WHEN 'Needs Revision' THEN 1
            WHEN 'Pending Review' THEN 2
            WHEN 'Approved' THEN 3
            WHEN 'Completed' THEN 4
            ELSE 5
        END,
        CASE WHEN ed.start_datetime IS NULL THEN 1 ELSE 0 END,
        ed.start_datetime ASC,
        e.created_at DESC
";

// Override sorting based on user-selected sort parameter
// Allows quick switching between different sort criteria via URL parameter
if ($view === 'recent') {
    // View=recent shows newest submissions first (recent additions)
    $orderBy = " ORDER BY e.created_at DESC ";
}
elseif ($sort === 'start_asc') {
    // Sort by event start date ascending (earliest first)
    // Useful for "Upcoming Review Deadlines" view where nearest dates shown first
    $orderBy = "
        ORDER BY
            CASE WHEN ed.start_datetime IS NULL THEN 1 ELSE 0 END,
            ed.start_datetime ASC,
            e.created_at DESC
    ";
}
elseif ($sort === 'start_desc') {
    // Sort by event start date descending (latest first)
    // Useful for viewing furthest-dated events first
    $orderBy = "
        ORDER BY
            CASE WHEN ed.start_datetime IS NULL THEN 1 ELSE 0 END,
            ed.start_datetime DESC,
            e.created_at DESC
    ";
}
elseif ($sort === 'updated_desc') {
    // Sort by last modification date descending (recently changed first)
    // Catches recent admin revisions or user resubmissions
    $orderBy = " ORDER BY e.updated_at DESC, e.created_at DESC ";
}

/* ================= COUNT QUERY: TOTAL EVENTS MATCHING FILTERS ================= */

/**
 * Database Query: Count Total Events
 * 
 * Purpose:
 * Determine total number of rows matching all active filters (status, org, search, etc).
 * Used to calculate pagination (total_pages = ceil(total_rows / per_page)).
 * Result displayed as "Showing X to Y of Z events" in pagination info.
 * 
 * Query Structure:
 * SELECT COUNT(*) AS total: Count all rows from FROM+WHERE (no need for SELECT *)
 * Reuses $baseSql (lines with JOINs and WHERE filters already built)
 * Does NOT include ORDER BY or LIMIT (unnecessary for count)
 * 
 * Type Binding:
 * Uses $types (built-up string of "s", "i", etc) and $params (values array)
 * Same types and params used across count query, ensuring filter consistency
 * 
 * Return:
 * Single row with key 'total' containing integer count of matching events
 */
$countSql = "SELECT COUNT(*) AS total " . $baseSql;
$countRow = fetchOne($conn, $countSql, $types, $params);
$total_rows = (int) ($countRow['total'] ?? 0);  // Cast to int, default 0 if null
$total_pages = max(1, (int) ceil($total_rows / $per_page));  // Never less than 1

/* ================= FETCH QUERY: EVENT LIST FOR CURRENT PAGE ================= */

/**
 * Database Query: Event List with Full Details
 * 
 * Purpose:
 * Retrieve complete event records for current page, with related user/type/date/location info.
 * All data needed for table display fetched in single query (prevents N+1 select problem).
 * Paginated via LIMIT and OFFSET; sorted per user selection and filtering.
 * 
 * SELECT Columns:
 * Event columns (e.*): event_id, event_name, event_status, docs_total, docs_uploaded,
 *                      created_at, updated_at, organizing_body, admin_remarks
 * User columns (u.*): user_name, user_email, org_body (organization affiliation)
 * Type columns (et, cat, cbo): activity_type_name, background_name
 * Date columns (ed.*): start_datetime, end_datetime (nullable if no event_date record)
 * Location columns (el.*): venue_platform (nullable if no event_location record)
 * 
 * Query Structure:
 * Uses $baseSql ($FROM $JOIN $WHERE) built earlier
 * Appends $orderBy (priority sort or user-requested sort)
 * Appends LIMIT ? OFFSET ? for pagination
 * 
 * Type Binding:
 * Extends $types with "ii" (two integers for LIMIT and OFFSET)
 * Pushes $per_page and $offset to end of $params array
 * Total params: [all filter values..., per_page (10), offset (0/10/20/...)]
 * 
 * Return:
 * Array of associative arrays, each representing one event row with all joined data
 * Number of rows: min($per_page, remaining events) up to $total_rows
 */
$fetchEventsSql = "
    SELECT
        e.event_id,
        e.event_name,
        e.event_status,
        e.docs_total,
        e.docs_uploaded,
        e.created_at,
        e.updated_at,
        e.organizing_body,
        e.admin_remarks,

        u.user_name,
        u.user_email,
        u.org_body,

        et.activity_type_id,
        et.background_id,
        cat.activity_type_name AS activity_type,
        cbo.background_name AS background,

        ed.start_datetime,
        ed.end_datetime,

        el.venue_platform
    " . $baseSql . "
    " . $orderBy . "
    LIMIT ? OFFSET ?
";

// Prepare parameters for the fetch query
// Reuse $params (filter values), add pagination integers
$listParams = $params;  // Copy filter params (status, search, org, etc)
$listTypes = $types . "ii";  // Add "ii" for LIMIT and OFFSET integers
$listParams[] = $per_page;  // Add limit (10 events per page)
$listParams[] = $offset;  // Add offset (0, 10, 20, ...)

// Execute fetch query and get array of event records for current page
$events = fetchAll($conn, $fetchEventsSql, $listTypes, $listParams);

/* ================= EXTRACT AND CAST SUMMARY VALUES ================= */

/**
 * Type Casting Summary Statistics
 * 
 * Purpose:
 * Convert database results to PHP integers for use in template calculations
 * Provides fallback values (0) if query returned NULL (edge case: no events)
 * 
 * Variables Populated:
 * - $total_events: Total all non-draft events (from statusCounts query)
 * - $pending_review_count: Events awaiting admin initial review
 * - $needs_revision_count: Events returned to users for changes
 * - $approved_count: Events approved by admin, awaiting completion
 * - $completed_count: Finished, closed events
 * - $pending_total: Sum of pending_review + needs_revision (shown in stat card as "Pending Events")
 * 
 * Pagination Info:
 * - $showing_from: First event number on current page (1-based, not 0-based)
 *   Formula: If total_rows = 0, shows_from = 0 (empty result). Else shows_from = offset + 1
 * - $showing_to: Last event number on current page (capped at total_rows)
 *   Formula: min(offset + per_page, total_rows)
 * Example: Page 1 with 10 results → "Showing 1 to 10 of 47 events"
 */
$total_events = (int) ($statusCounts['total_events'] ?? 0);
$pending_review_count = (int) ($statusCounts['pending_review_count'] ?? 0);
$needs_revision_count = (int) ($statusCounts['needs_revision_count'] ?? 0);
$approved_count = (int) ($statusCounts['approved_count'] ?? 0);
$completed_count = (int) ($statusCounts['completed_count'] ?? 0);
$pending_total = $pending_review_count + $needs_revision_count;

$showing_from = $total_rows > 0 ? $offset + 1 : 0;
$showing_to = min($offset + $per_page, $total_rows);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Admin Events - HAUCREDIT</title>
    <link rel="stylesheet" href="<?= PUBLIC_URL ?>assets/styles/layout.css" />
    <link rel="stylesheet" href="<?= PUBLIC_URL ?>assets/styles/home_styles.css" />
    <link rel="stylesheet" href="<?= PUBLIC_URL ?>assets/styles/admin_events.css" />
</head>

<body>
    <div class="app">
        <div class="sidebar-overlay" id="sidebarOverlay" hidden></div>

        <?php include PUBLIC_PATH . 'assets/includes/admin_nav.php'; ?>

        <main class="main">
            <header class="topbar">
                <button class="hamburger" id="menuBtn" type="button" aria-label="Open menu">☰</button>

                <div class="title-wrap">
                    <h1><?= htmlspecialchars($page_title) ?></h1>
                    <p><?= htmlspecialchars($page_subtitle) ?></p>
                </div>
            </header>

            <section class="content admin-events-page">
                <!-- Summary -->
                <section class="events-stats">
                    <article class="stat-card">
                        <div class="stat-icon"><i class="fa-solid fa-list"></i></div>
                        <div>
                            <div class="stat-number"><?= $total_events ?></div>
                            <div class="stat-label">Total Events</div>
                        </div>
                    </article>

                    <article class="stat-card">
                        <div class="stat-icon"><i class="fa-solid fa-hourglass-half"></i></div>
                        <div>
                            <div class="stat-number"><?= $pending_total ?></div>
                            <div class="stat-label">Pending Events</div>
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
                </section>

                <!-- Toolbar -->
                <section class="events-toolbar">
                    <div class="toolbar-top">
                        <?php
                        $tabs = [
                            ['value' => 'all', 'label' => 'All', 'count' => $total_events, 'icon' => 'fa-solid fa-list'],
                            ['value' => 'Pending Review', 'label' => 'Pending', 'count' => $pending_review_count, 'icon' => 'fa-solid fa-hourglass-half'],
                            ['value' => 'Needs Revision', 'label' => 'Needs Revision', 'count' => $needs_revision_count, 'icon' => 'fa-solid fa-rotate-left'],
                            ['value' => 'Approved', 'label' => 'Approved', 'count' => $approved_count, 'icon' => 'fa-solid fa-circle-check'],
                            ['value' => 'Completed', 'label' => 'Completed', 'count' => $completed_count, 'icon' => 'fa-solid fa-flag-checkered']
                        ];

                        foreach ($tabs as $tab):
                            $active = $status_filter === $tab['value'] ? 'active' : '';
                            $tabUrl = 'admin_events.php?' . buildQueryString([
                                'status' => $tab['value'],
                                'page' => 1
                            ]);
                            ?>
                            <a href="<?= htmlspecialchars($tabUrl) ?>" class="tab-btn <?= $active ?>">
                                <i class="<?= htmlspecialchars($tab['icon']) ?>"></i>
                                <?= htmlspecialchars($tab['label']) ?>
                                <span class="tab-count"><?= (int) $tab['count'] ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>

                    <div class="toolbar-search">
                        <form method="GET" class="toolbar-form" id="eventsFilterForm">
                            <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
                            <input type="hidden" name="view" value="<?= htmlspecialchars($view) ?>">
                            <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">
                            <input type="hidden" name="page" value="1">

                            <div class="search-wrap">
                                <i class="fa-solid fa-magnifying-glass"></i>
                                <input type="text" name="search" id="searchInput" class="search-input"
                                    placeholder="Search events, organizations, users, type, venue..."
                                    value="<?= htmlspecialchars($search) ?>">
                            </div>

                            <select name="status" class="toolbar-select auto-submit-filter">
                                <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Statuses</option>
                                <option value="Pending Review" <?= $status_filter === 'Pending Review' ? 'selected' : '' ?>>Pending Review</option>
                                <option value="Needs Revision" <?= $status_filter === 'Needs Revision' ? 'selected' : '' ?>>Needs Revision</option>
                                <option value="Approved" <?= $status_filter === 'Approved' ? 'selected' : '' ?>>Approved
                                </option>
                                <option value="Completed" <?= $status_filter === 'Completed' ? 'selected' : '' ?>>Completed
                                </option>
                            </select>

                            <select name="org" class="toolbar-select auto-submit-filter">
                                <option value="">All Organizations</option>
                                <?php foreach ($orgOptions as $org): ?>
                                    <?php $org_value = trim($org['org_body'] ?? ''); ?>
                                    <option value="<?= htmlspecialchars($org_value) ?>" <?= $org_filter === $org_value ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($org_value) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <?php if ($status_filter !== 'all' || $org_filter !== '' || $search !== '' || $filter !== '' || $view !== '' || $sort !== ''): ?>
                                <a href="admin_events.php" class="btn-filter">
                                    <i class="fa-solid fa-xmark"></i> Reset
                                </a>
                            <?php endif; ?>
                        </form>
                    </div>
                </section>

                <!-- Table -->
                <section class="events-table-container">
                    <table class="events-table">
                        <thead>
                            <tr>
                                <th>Event</th>
                                <th>Organization</th>
                                <th>Submitted By</th>
                                <th>Start Date</th>
                                <th>Status</th>
                                <th>Documents</th>
                                <th>Type</th>
                                <th>Actions</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php if (!empty($events)): ?>
                                <?php foreach ($events as $event): ?>
                                    <?php
                                    $docs_total = (int) ($event['docs_total'] ?? 0);
                                    $docs_uploaded = (int) ($event['docs_uploaded'] ?? 0);
                                    $status = $event['event_status'];
                                    $status_class = normalizeStatusClass($status);

                                    $badge_class = match ($status) {
                                        'Pending Review' => 'badge-pending-review',
                                        'Needs Revision' => 'badge-needs-revision',
                                        'Approved' => 'badge-approved',
                                        'Completed' => 'badge-completed'
                                    };

                                    $doc_text = $docs_total > 0
                                        ? "{$docs_uploaded}/{$docs_total}"
                                        : "0/0";
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="event-name-cell">
                                                <span
                                                    class="event-name-text"><?= htmlspecialchars($event['event_name']) ?></span>
                                                <?php if (!empty($event['admin_remarks']) && $status === 'Needs Revision'): ?>
                                                    <div class="event-inline-note">Has admin remarks</div>
                                                <?php endif; ?>
                                            </div>
                                        </td>

                                        <td>
                                            <span class="org-badge">
                                                <i class="fa-solid fa-building-columns"></i>
                                                <?= htmlspecialchars($event['org_body'] ?? 'No organization') ?>
                                            </span>
                                        </td>

                                        <td>
                                            <div class="submitted-by">
                                                <strong><?= htmlspecialchars($event['user_name'] ?? 'Unknown User') ?></strong>
                                                <small><?= htmlspecialchars($event['user_email'] ?? '') ?></small>
                                            </div>
                                        </td>

                                        <td>
                                            <?php if (!empty($event['start_datetime'])): ?>
                                                <span class="date-chip">
                                                    <i class="fa-regular fa-calendar"></i>
                                                    <?= date('M j, Y', strtotime($event['start_datetime'])) ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="muted-dash">—</span>
                                            <?php endif; ?>
                                        </td>

                                        <td>
                                            <span class="status-badge <?= htmlspecialchars($badge_class) ?>">
                                                <?= htmlspecialchars($status) ?>
                                            </span>
                                        </td>

                                        <td>
                                            <span
                                                class="req-badge <?= $docs_uploaded >= $docs_total && $docs_total > 0 ? 'req-approved' : 'req-pending' ?>">
                                                <i
                                                    class="fa-solid <?= $docs_uploaded >= $docs_total && $docs_total > 0 ? 'fa-circle-check' : 'fa-clock' ?>"></i>
                                                <?= htmlspecialchars($doc_text) ?>
                                            </span>
                                        </td>

                                        <td><?= htmlspecialchars($event['activity_type'] ?? 'N/A') ?></td>

                                        <td>
                                            <a href="admin_manage_event.php?id=<?= (int) $event['event_id'] ?>"
                                                class="btn-view">
                                                <i class="fa-solid fa-eye"></i> Manage
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8">
                                        <div class="empty-state">
                                            <i class="fa-regular fa-calendar-xmark"></i>
                                            <p>No events found. Try adjusting your filters.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </section>

                <!-- Pagination -->
                <?php if ($total_rows > 0): ?>
                    <section class="pagination-wrap">
                        <div class="pagination-info">
                            Showing <strong><?= $showing_from ?></strong> to <strong><?= $showing_to ?></strong> of
                            <strong><?= $total_rows ?></strong> events
                        </div>

                        <div class="pagination">
                            <?php
                            $prevPage = max(1, $page - 1);
                            $nextPage = min($total_pages, $page + 1);
                            $startPage = max(1, $page - 2);
                            $endPage = min($total_pages, $page + 2);
                            ?>

                            <a href="admin_events.php?<?= htmlspecialchars(buildQueryString(['page' => 1])) ?>"
                                class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>">
                                <i class="fa-solid fa-angles-left"></i>
                            </a>

                            <a href="admin_events.php?<?= htmlspecialchars(buildQueryString(['page' => $prevPage])) ?>"
                                class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>">
                                <i class="fa-solid fa-chevron-left"></i>
                            </a>

                            <?php for ($pg = $startPage; $pg <= $endPage; $pg++): ?>
                                <a href="admin_events.php?<?= htmlspecialchars(buildQueryString(['page' => $pg])) ?>"
                                    class="page-btn <?= $pg === $page ? 'active' : '' ?>">
                                    <?= $pg ?>
                                </a>
                            <?php endfor; ?>

                            <a href="admin_events.php?<?= htmlspecialchars(buildQueryString(['page' => $nextPage])) ?>"
                                class="page-btn <?= $page >= $total_pages ? 'disabled' : '' ?>">
                                <i class="fa-solid fa-chevron-right"></i>
                            </a>

                            <a href="admin_events.php?<?= htmlspecialchars(buildQueryString(['page' => $total_pages])) ?>"
                                class="page-btn <?= $page >= $total_pages ? 'disabled' : '' ?>">
                                <i class="fa-solid fa-angles-right"></i>
                            </a>
                        </div>
                    </section>
                <?php endif; ?>

                <?php include PUBLIC_PATH . 'assets/includes/footer.php'; ?>
            </section>
        </main>
    </div>

    <script src="<?= APP_URL ?>script/layout.js?v=1"></script>
    
    <!-- JavaScript: Filter Form Auto-Submit Behavior -->
    <script>
        /**
         * Form Auto-Submit Logic
         * 
         * Purpose:
         * Enable instant filter responses when user changes status or org dropdown.
         * Also enables Enter key to submit search without clicking a button.
         * Provides smooth, responsive filtering experience (no "Apply" button needed).
         * 
         * Implementation:
         * - Selects all inputs with class auto-submit-filter
         * - Attaches change event listeners to these inputs
         * - When changed, automatically submits the eventsFilterForm
         * - Also enables search input to submit on Enter key press
         * 
         * Affected Elements:
         * - Status dropdown (<select name="status" class="auto-submit-filter">)
         * - Organization dropdown (<select name="org" class="auto-submit-filter">)
         * - Search input (triggers on Enter key)
         * 
         * User Flow:
         * 1. User selects "Approved" from status dropdown
         * 2. change event triggered on <select>
         * 3. Form auto-submitted to admin_events.php?status=Approved&page=1&...
         * 4. Page reloads showing only Approved events
         * 5. Same for org dropdown or search Enter key
         */
        (function () {
            // Get the filter form element by ID
            const form = document.getElementById('eventsFilterForm');
            if (!form) return;

            // Find all elements with auto-submit-filter class (status and org dropdowns)
            const autoSubmitFields = form.querySelectorAll('.auto-submit-filter');
            // Find search input element
            const searchInput = document.getElementById('searchInput');

            // Attach change event listener to each auto-submit field
            autoSubmitFields.forEach(field => {
                field.addEventListener('change', function () {
                    // When field value changes, submit the entire form
                    // Form submission triggers page reload with new filter parameter
                    form.submit();
                });
            });

            // Attach Enter key listener to search input
            if (searchInput) {
                searchInput.addEventListener('keydown', function (e) {
                    // Check if pressed key is Enter (key code 13)
                    if (e.key === 'Enter') {
                        // Prevent form default behavior (textbox.value reset)
                        e.preventDefault();
                        // Submit the form with search term
                        form.submit();
                    }
                });
            }
        })();
    </script>
</body>

</html>