<?php

define('BASE_PATH', dirname(__DIR__));      // your full file path/haucredit/app (ex. C:\xampp\htdocs\haucredit\app)
define('ROOT_PATH', dirname(BASE_PATH));   // your full file path/haucredit (ex. C:\xampp\htdocs\haucredit)

$projectRoot = str_replace('\\', '/', realpath(ROOT_PATH));
$documentRoot = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));

// to define where you had put /haucredit (ex. my_folder\)
$baseUrl = '';
if ($documentRoot && str_starts_with($projectRoot, $documentRoot)) {
    $baseUrl = str_replace($documentRoot, '', $projectRoot);
}

// URL CONSTANTS
define('BASE_URL', $baseUrl); // $baseUrl/haucredit (ex. my_folder\haucredit)
define('PUBLIC_URL', BASE_URL . '/public/'); // $baseUrl/haucredit/public/ 
define('APP_URL', BASE_URL . '/app/'); // $baseUrl/haucredit/app/ 
define('UPLOADS_URL', BASE_URL . '/uploads/'); // $baseUrl/haucredit/uploads/

define('USER_PAGE', PUBLIC_URL . 'user_pages/'); // $baseUrl/haucredit/public/user_pages/ 
define('ADMIN_PAGE', PUBLIC_URL . 'admin_pages/'); // $baseUrl/haucredit/public/user_pages/ 

// FILESYSTEM CONSTANTS
define('APP_PATH', ROOT_PATH . '/app/'); // ROOT_PATH/app (ex. C:\xampp\htdocs\haucredit\app\)
define('PUBLIC_PATH', ROOT_PATH . '/public/'); // ROOT_PATH/public/
define('UPLOADS_PATH', ROOT_PATH . '/uploads/'); // ROOT_PATH/uploads/
?>