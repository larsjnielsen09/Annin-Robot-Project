<?php
// Ensure config is loaded if not already (e.g. for standalone script use)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../core/config.php';

/**
 * Sanitizes string input.
 * @param string $data The input data.
 * @return string Sanitized data.
 */
function sanitize_string(string $data): string {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Redirects to a given URL.
 * @param string $url The URL to redirect to.
 */
function redirect(string $url): void {
    header("Location: " . $url);
    exit;
}

/**
 * Sets a session flash message.
 * @param string $message The message content.
 * @param string $type The message type (e.g., 'success', 'danger', 'info').
 */
function set_flash_message(string $message, string $type = 'info'): void {
    $_SESSION['message'] = $message;
    $_SESSION['message_type'] = $type;
}

/**
 * Checks if a user is logged in. If not, redirects to login page.
 * Optionally, specify a redirect URL if already logged in (e.g., for login/register pages).
 * @param string|null $redirect_if_logged_in URL to redirect to if user IS logged in.
 */
function ensure_logged_in(string $redirect_if_logged_in = null): void {
    if (isset($_SESSION['user_id'])) {
        if ($redirect_if_logged_in) {
            redirect($redirect_if_logged_in);
        }
        // User is logged in, and no redirect for logged-in users is specified, so do nothing.
        return;
    } else {
        // User is not logged in.
        // If $redirect_if_logged_in is null (meaning this is a protected page),
        // or if we are on a page that does not require redirecting if logged in,
        // then redirect to login.
        if (!$redirect_if_logged_in) {
             set_flash_message('You must be logged in to view this page.', 'warning');
             redirect(BASE_URL . 'login.php');
        }
    }
}

/**
 * Checks if a user is NOT logged in. If they are, redirect them.
 * Useful for login/register pages where logged-in users shouldn't be.
 * @param string $redirect_to URL to redirect logged-in users to (e.g., dashboard).
 */
function ensure_not_logged_in(string $redirect_to = 'index.php'): void {
    if (isset($_SESSION['user_id'])) {
        redirect(BASE_URL . $redirect_to);
    }
}

// Add more utility functions as needed, e.g., for date formatting, CSRF token generation/validation, etc.
?>
