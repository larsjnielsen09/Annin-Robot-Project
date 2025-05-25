<?php
// Database credentials
define('DB_HOST', 'localhost'); // Replace with your database host
define('DB_USERNAME', 'your_db_user');    // Replace with your database username
define('DB_PASSWORD', 'your_db_password'); // Replace with your database password
define('DB_NAME', 'task_manager_db'); // Replace with your database name

// Application settings
define('BASE_URL', 'http://localhost/task_manager/'); // Adjust if your app is in a subdirectory
define('APP_NAME', 'Task & Time Manager');

// Timezone
date_default_timezone_set('UTC'); // Set your preferred timezone

// Error reporting (for development)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>
