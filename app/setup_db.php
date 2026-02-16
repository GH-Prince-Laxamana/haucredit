<?php

$db_server = "localhost";
$db_user = "root";
$db_pass = "";
$db_name = "haucredit_db";

$conn = mysqli_connect($db_server, $db_user, $db_pass);
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

mysqli_query($conn, "CREATE DATABASE IF NOT EXISTS $db_name");

mysqli_select_db($conn, $db_name);

$sql = "
CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    user_name VARCHAR(50) NOT NULL UNIQUE,
    user_password VARCHAR(255) NOT NULL,
    user_email VARCHAR(100) NOT NULL UNIQUE,
    stud_num VARCHAR(50) NOT NULL UNIQUE,
    org_body VARCHAR(200) NOT NULL,
    user_reg_date DATETIME NOT NULL
)";

mysqli_query($conn, $table_sql);

$insert_sql = "
INSERT IGNORE INTO users
(user_name, user_password, user_email, stud_num, org_body, user_reg_date)
VALUES
('admin', '203', 'admin@hau.edu.ph', '203', 'SOC', NOW())
";

if (mysqli_query($conn, $insert_sql)) {
    echo "Setup completed!";
} else {
    echo "Error: " . mysqli_error($conn);
}
?>
