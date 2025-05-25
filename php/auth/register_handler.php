<?php
require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . 'register.php');
}

$username = sanitize_string($_POST['username'] ?? '');
$email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
$password = $_POST['password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

// Basic validation
if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
    set_flash_message('All fields are required.', 'danger');
    redirect(BASE_URL . 'register.php');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    set_flash_message('Invalid email format.', 'danger');
    redirect(BASE_URL . 'register.php');
}

if (strlen($password) < 8) { // Example: Minimum password length
    set_flash_message('Password must be at least 8 characters long.', 'danger');
    redirect(BASE_URL . 'register.php');
}

if ($password !== $confirm_password) {
    set_flash_message('Passwords do not match.', 'danger');
    redirect(BASE_URL . 'register.php');
}

$pdo = get_db_connection();

// Check if username or email already exists
try {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :username OR email = :email LIMIT 1");
    $stmt->bindParam(':username', $username);
    $stmt->bindParam(':email', $email);
    $stmt->execute();
    if ($stmt->fetch()) {
        set_flash_message('Username or email already taken.', 'danger');
        redirect(BASE_URL . 'register.php');
    }

    // Hash the password
    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    // Insert new user
    $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash) VALUES (:username, :email, :password_hash)");
    $stmt->bindParam(':username', $username);
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':password_hash', $password_hash);

    if ($stmt->execute()) {
        set_flash_message('Registration successful! You can now login.', 'success');
        redirect(BASE_URL . 'login.php');
    } else {
        set_flash_message('Registration failed. Please try again.', 'danger');
        redirect(BASE_URL . 'register.php');
    }
} catch (PDOException $e) {
    error_log("Registration Error: " . $e->getMessage());
    set_flash_message('An error occurred during registration. Please try again later.', 'danger');
    redirect(BASE_URL . 'register.php');
}
?>
