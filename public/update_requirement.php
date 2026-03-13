<?php

/* DATABASE CONNECTION */
require_once dirname(__DIR__, 2) . "/app/script/database.php";

/* CHECK REQUEST METHOD */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $id = $_POST['id'] ?? null;
    $event_status = $_POST['event_status'] ?? null;

    if ($id !== null && $event_status !== null) {

        $stmt = $conn->prepare("
        UPDATE requirements
        SET req_done = ?
        WHERE req_id = ?
        ");

        $stmt->bind_param("ii", $event_status, $id);
        $stmt->execute();

        echo "success";

    } else {

        echo "invalid data";

    }

} else {

    echo "invalid request";

}
?>