<?php
session_start();
require_once "../app/database.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['event_id'])) {
    popup_error("Invalid request.");
}

$event_id = (int) $_POST['event_id'];
$user_id = (int) $_SESSION['user_id'];

if ($event_id <= 0) {
    popup_error("Invalid event.");
}

/* ================= OWNERSHIP + ARCHIVE CHECK ================= */
$checkArchivedEventSql = "
    SELECT event_id
    FROM events
    WHERE event_id = ?
      AND user_id = ?
      AND archived_at IS NOT NULL
    LIMIT 1
";

$eventRow = fetchOne(
    $conn,
    $checkArchivedEventSql,
    "ii",
    [$event_id, $user_id]
);

if (!$eventRow) {
    popup_error("Unauthorized action or event is not archived.");
}

/* ================= COLLECT FILE PATHS =================
   Current DB path:
   events -> event_requirements -> requirement_files
*/
$fetchEventFilePathsSql = "
    SELECT DISTINCT rf.file_path
    FROM requirement_files rf
    INNER JOIN event_requirements er
        ON rf.event_req_id = er.event_req_id
    INNER JOIN events e
        ON er.event_id = e.event_id
    WHERE e.event_id = ?
      AND e.user_id = ?
      AND rf.file_path IS NOT NULL
      AND rf.file_path != ''
";

$fileRows = fetchAll(
    $conn,
    $fetchEventFilePathsSql,
    "ii",
    [$event_id, $user_id]
);

$filePaths = [];
foreach ($fileRows as $row) {
    $filePaths[] = $row['file_path'];
}

/* ================= DELETE EVENT =================
   Cascades will remove:
   - event_type
   - event_dates
   - event_participants
   - event_location
   - event_logistics
   - event_metrics
   - event_requirements
   - requirement_files

   calendar_entries.event_id becomes NULL because of ON DELETE SET NULL
*/
try {
    $conn->begin_transaction();

    $deleteArchivedEventSql = "
        DELETE FROM events
        WHERE event_id = ?
          AND user_id = ?
          AND archived_at IS NOT NULL
        LIMIT 1
    ";

    $deleteStmt = execQuery(
        $conn,
        $deleteArchivedEventSql,
        "ii",
        [$event_id, $user_id]
    );

    if ($deleteStmt->affected_rows === 0) {
        throw new Exception("Event could not be deleted.");
    }

    $conn->commit();

    /* ================= DELETE PHYSICAL FILES AFTER COMMIT ================= */
    foreach ($filePaths as $relativePath) {
        $fullPath = __DIR__ . "/../" . ltrim($relativePath, "/");

        if (is_file($fullPath)) {
            @unlink($fullPath);
        }
    }

} catch (Exception $e) {
    $conn->rollback();
    popup_error("Deletion failed: " . $e->getMessage());
}

header("Location: archived_events.php");
exit();
?>