<?php
require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . 'login.php');
}

$username_or_email = sanitize_string($_POST['username_or_email'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($username_or_email) || empty($password)) {
    set_flash_message('Username/Email and password are required.', 'danger');
    redirect(BASE_URL . 'login.php');
}

$pdo = get_db_connection();

try {
    // Use two distinct named placeholders: :uname for username, :uemail for email
    $stmt = $pdo->prepare("SELECT id, username, password_hash FROM users WHERE username = :uname OR email = :uemail LIMIT 1");

    // Execute by passing an associative array with keys matching the new placeholders.
    // The same input value ($username_or_email) is used for both.
    $stmt->execute([':uname' => $username_or_email, ':uemail' => $username_or_email]);

    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        // Login success logic (session start, regenerate ID, set session variables, flash message, redirect)
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        session_regenerate_id(true);

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];

        set_flash_message('Login successful! Welcome back, ' . htmlspecialchars($user['username']) . '.', 'success');
        redirect(BASE_URL . 'index.php');
    } else {
        // Login failure logic (flash message, redirect)
        set_flash_message('Invalid username/email or password.', 'danger');
        redirect(BASE_URL . 'login.php');
    }
} catch (PDOException $e) {
    // Error handling logic (log error, flash message, redirect)
    error_log("Login Error: " . $e->getMessage());
    set_flash_message('An error occurred during login. Please try again later.', 'danger');
    redirect(BASE_URL . 'login.php');
}
?>
