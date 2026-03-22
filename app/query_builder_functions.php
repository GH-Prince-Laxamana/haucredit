<?php
/**
 * Database Query Builder and Execution Functions
 * 
 * This file provides a set of helper functions for executing prepared statements
 * with MySQLi to prevent SQL injection and simplify database operations.
 * All functions support parameterized queries with type binding.
 */

/**
 * Prepares and executes a SQL statement with optional parameter binding
 * 
 * This is the base function for all query execution. It handles:
 * - Statement preparation with error checking
 * - Parameter binding for type safety
 * - Query execution with error reporting
 * 
 * @param mysqli $conn The MySQLi database connection object
 * @param string $sql The SQL query with placeholders (?) for parameters
 * @param string $types Optional parameter type string (e.g., "ssi" for string, string, integer)
 *                      Types: 's'=string, 'i'=integer, 'd'=double, 'b'=blob
 * @param array $params Optional array of parameter values to bind to the query
 * @return mysqli_stmt The mysqli statement object after execution
 * @throws Exception If prepare or execute fails, includes detailed error message
 */
function execQuery(mysqli $conn, string $sql, string $types = '', array $params = [])
{
    // Prepare the SQL statement for execution
    // Preparation separates SQL structure from data for security
    $stmt = $conn->prepare($sql);

    // Check if preparation failed (e.g., invalid SQL syntax)
    if (!$stmt) {
        throw new Exception("SQL Prepare failed: " . $conn->error);
    }

    // Bind parameters to the prepared statement if types and parameters are provided
    // This ensures type safety and prevents SQL injection
    if ($types && !empty($params)) {
        // Use variadic syntax to unpack parameter array for bind_param
        $stmt->bind_param($types, ...$params);
    }

    // Execute the prepared statement with bound parameters
    if (!$stmt->execute()) {
        throw new Exception("SQL Execute failed: " . $stmt->error);
    }

    // Return the executed statement for result processing
    return $stmt;
}

/**
 * Executes a query without returning any results
 * 
 * Convenience wrapper for execQuery() when the result is not needed.
 * Useful for INSERT, UPDATE, DELETE, and other non-SELECT operations.
 * 
 * @param mysqli $conn The MySQLi database connection object
 * @param string $sql The SQL query with placeholders (?) for parameters
 * @param string $types Optional parameter type string for bind_param
 * @param array $params Optional array of parameter values to bind
 * @return void - Executes query but returns nothing
 * @throws Exception If the query preparation or execution fails
 */
function executeOnly(mysqli $conn, string $sql, string $types = '', array $params = []): void
{
    // Execute the query using the base execQuery function
    // The result is discarded since this function is for non-SELECT queries
    execQuery($conn, $sql, $types, $params);
}

/**
 * Fetches a single row as an associative array
 * 
 * Executes a query and returns the first result row as an associative array
 * where array keys are column names. Returns null if no rows found.
 * 
 * @param mysqli $conn The MySQLi database connection object
 * @param string $sql The SQL query with placeholders (?) for parameters
 * @param string $types Optional parameter type string for bind_param
 * @param array $params Optional array of parameter values to bind
 * @return array|null Associative array of first row ['column' => 'value'], or null if no rows
 */
function fetchOne(mysqli $conn, string $sql, string $types = '', array $params = [])
{
    // Execute the query and get the statement
    $stmt = execQuery($conn, $sql, $types, $params);
    
    // Get the result set from the statement and fetch first row as associative array
    return $stmt->get_result()->fetch_assoc();
}

/**
 * Fetches a single scalar value from the first column of first row
 * 
 * Executes a query and returns only the value from the first column
 * of the first result row. Returns null if no rows found.
 * Useful for COUNT(), aggregate functions, or single-value selects.
 * 
 * @param mysqli $conn The MySQLi database connection object
 * @param string $sql The SQL query with placeholders (?) for parameters
 * @param string $types Optional parameter type string for bind_param
 * @param array $params Optional array of parameter values to bind
 * @return mixed The value from the first column of first row, or null if no rows
 */
function fetchValue(mysqli $conn, string $sql, string $types = '', array $params = [])
{
    // Fetch the first row as an associative array
    $row = fetchOne($conn, $sql, $types, $params);
    
    // If row exists, extract the first value; otherwise return null
    // array_values() reindexes the array, then [0] gets the first element
    return $row ? array_values($row)[0] : null;
}

/**
 * Fetches all rows as an array of associative arrays
 * 
 * Executes a query and returns all result rows as an array of associative arrays.
 * Each inner array represents one row with column names as keys.
 * Returns empty array if no rows found.
 * 
 * @param mysqli $conn The MySQLi database connection object
 * @param string $sql The SQL query with placeholders (?) for parameters
 * @param string $types Optional parameter type string for bind_param
 * @param array $params Optional array of parameter values to bind
 * @return array Array of associative arrays [['col'=>'val1'], ['col'=>'val2']], or empty array []
 */
function fetchAll(mysqli $conn, string $sql, string $types = '', array $params = [])
{
    // Execute the query and get the statement
    $stmt = execQuery($conn, $sql, $types, $params);
    
    // Get the result set and fetch all rows as array of associative arrays
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
?>