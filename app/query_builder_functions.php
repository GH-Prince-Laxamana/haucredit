<?php
function execQuery(mysqli $conn, string $sql, string $types = '', array $params = [])
{
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new Exception("SQL Prepare failed: " . $conn->error);
    }

    if ($types && !empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    if (!$stmt->execute()) {
        throw new Exception("SQL Execute failed: " . $stmt->error);
    }

    return $stmt;
}

function executeOnly(mysqli $conn, string $sql, string $types = '', array $params = []): void
{
    execQuery($conn, $sql, $types, $params);
}

function fetchOne(mysqli $conn, string $sql, string $types = '', array $params = [])
{
    $stmt = execQuery($conn, $sql, $types, $params);
    return $stmt->get_result()->fetch_assoc();
}

function fetchValue(mysqli $conn, string $sql, string $types = '', array $params = [])
{
    $row = fetchOne($conn, $sql, $types, $params);
    return $row ? array_values($row)[0] : null;
}

function fetchAll(mysqli $conn, string $sql, string $types = '', array $params = [])
{
    $stmt = execQuery($conn, $sql, $types, $params);
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

?>