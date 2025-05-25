<?php
require_once 'config.php';

function get_db_connection() {
    static $conn = null; // Static variable to hold the connection

    if ($conn === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            $conn = new PDO($dsn, DB_USERNAME, DB_PASSWORD, $options);
        } catch (PDOException $e) {
            // For a real application, you might want to log this error or display a generic error message
            error_log("Database Connection Error: " . $e->getMessage());
            die("Could not connect to the database. Please check your configuration or try again later.");
        }
    }
    return $conn;
}
?>
