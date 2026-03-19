<?php
/**
 * users_fetch.php
 * AJAX endpoint – returns paginated, filtered, sorted user list + stats as JSON.
 * All inputs are sanitised; queries use prepared statements.
 */

require_once "../app/database.php";

header('Content-Type: application/json');

// ===== INPUT SANITISATION =====
$search   = trim($_GET['search']   ?? '');
$status   = trim($_GET['status']   ?? 'all');
$page     = max(1, (int) ($_GET['page']    ?? 1));
$per_page = max(1, min(100, (int) ($_GET['per_page'] ?? 10)));
$sort_col = $_GET['sort'] ?? 'user_id';
$sort_dir = strtoupper($_GET['dir'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';

// ===== WHITELIST SORT COLUMN =====
$allowed_cols = ['user_id', 'user_name', 'user_email', 'org_body', 'role', 'status', 'last_login'];
if (!in_array($sort_col, $allowed_cols, true)) {
    $sort_col = 'user_id';
}

// ===== BUILD WHERE CLAUSE =====
$conditions = [];
$params     = [];
$types      = '';

// Search filter
if ($search !== '') {
    $conditions[] = "(user_name LIKE ? OR user_email LIKE ? OR org_body LIKE ? OR stud_num LIKE ?)";
    $like = "%{$search}%";
    $params = array_merge($params, [$like, $like, $like, $like]);
    $types .= 'ssss';
}

// Status / tab filter
// "Inactive" = no last_login OR last_login older than 30 days
if ($status === 'Inactive') {
    $conditions[] = "(last_login IS NULL OR last_login < NOW() - INTERVAL 30 DAY)";
} elseif ($status !== 'all') {
    $allowed_statuses = ['Pending', 'Approved', 'Archived'];
    if (in_array($status, $allowed_statuses, true)) {
        $conditions[] = "status = ?";
        $params[]      = $status;
        $types        .= 's';
    }
}

$where_sql = count($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

// ===== COUNT QUERY =====
$count_sql  = "SELECT COUNT(*) AS total FROM users {$where_sql}";
$count_stmt = $conn->prepare($count_sql);
if ($types) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_rows = (int) $count_stmt->get_result()->fetch_assoc()['total'];

// ===== MAIN QUERY =====
$offset  = ($page - 1) * $per_page;
$data_sql = "
    SELECT
        user_id, user_name, user_email, stud_num, org_body, role,
        status, profile_pic, last_login,
        CASE
            WHEN (last_login IS NULL OR last_login < NOW() - INTERVAL 30 DAY)
                AND status = 'Approved'
            THEN 1
            ELSE 0
        END AS is_inactive
    FROM users
    {$where_sql}
    ORDER BY {$sort_col} {$sort_dir}
    LIMIT ? OFFSET ?
";

$data_stmt = $conn->prepare($data_sql);
$bind_types  = $types . 'ii';
$bind_params = array_merge($params, [$per_page, $offset]);
$data_stmt->bind_param($bind_types, ...$bind_params);
$data_stmt->execute();
$result = $data_stmt->get_result();

$users = [];
while ($row = $result->fetch_assoc()) {
    // Never expose password hash
    unset($row['user_password']);
    $users[] = $row;
}

// ===== STATS (always over full table) =====
$stats_row = $conn->query("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN (last_login IS NULL OR last_login < NOW() - INTERVAL 30 DAY) THEN 1 ELSE 0 END) AS inactive,
        SUM(CASE WHEN role = 'Admin'    THEN 1 ELSE 0 END) AS admins,
        SUM(CASE WHEN status = 'Pending'  THEN 1 ELSE 0 END) AS pending_count,
        SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) AS approved_count,
        SUM(CASE WHEN status = 'Archived' THEN 1 ELSE 0 END) AS archived_count
    FROM users
")->fetch_assoc();

echo json_encode([
    'success'   => true,
    'users'     => $users,
    'total'     => $total_rows,
    'page'      => $page,
    'per_page'  => $per_page,
    'stats'     => $stats_row,
]);