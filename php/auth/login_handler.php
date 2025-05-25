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
    $stmt = $pdo->prepare("SELECT id, username, password_hash FROM users WHERE username = :username_or_email OR email = :username_or_email LIMIT 1");
    $stmt->bindParam(':username_or_email', $username_or_email);
    $stmt->execute();
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        // Password is correct, start session
        if (session_status() == PHP_SESSION_NONE) {
            session_start(); // Ensure session is started before regenerating ID
        }
        session_regenerate_id(true); // Regenerate session ID for security

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];

        set_flash_message('Login successful! Welcome back, ' . htmlspecialchars($user['username']) . '.', 'success');
        redirect(BASE_URL . 'index.php'); // Redirect to dashboard or main page
    } else {
        set_flash_message('Invalid username/email or password.', 'danger');
        redirect(BASE_URL . 'login.php');
    }
} catch (PDOException $e) {
    error_log("Login Error: " . $e->getMessage());
    set_flash_message('An error occurred during login. Please try again later.', 'danger');
    redirect(BASE_URL . 'login.php');
}
?>
