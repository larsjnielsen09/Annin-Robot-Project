<?php
// This script is to be included at the top of pages that require authentication.
// It uses the function from functions.php
require_once __DIR__ . '/../includes/functions.php';

// ensure_logged_in() will handle redirecting to login if not authenticated.
// It expects BASE_URL to be defined, which functions.php (via config.php) ensures.
ensure_logged_in();
?>
