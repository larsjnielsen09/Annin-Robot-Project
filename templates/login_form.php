<?php
$page_title = "Login";
require_once __DIR__ . '/../php/includes/header.php';
require_once __DIR__ . '/../php/includes/functions.php';
ensure_not_logged_in('index.php'); // Redirect if already logged in
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h2>Login</h2>
            </div>
            <div class="card-body">
                <form action="<?php echo BASE_URL; ?>php/auth/login_handler.php" method="POST">
                    <div class="mb-3">
                        <label for="username_or_email" class="form-label">Username or Email</label>
                        <input type="text" class="form-control" id="username_or_email" name="username_or_email" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Login</button>
                </form>
            </div>
            <div class="card-footer text-center">
                <p>Don't have an account? <a href="<?php echo BASE_URL; ?>register.php">Register here</a></p>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../php/includes/footer.php'; ?>
