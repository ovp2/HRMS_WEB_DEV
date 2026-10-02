<?php
/**
 * HRMS - Login Page
 * Handles user authentication with PHP sessions
 */

require_once __DIR__ . '/includes/auth.php';

// Redirect if already logged in
if (isLoggedIn()) {
    if (isAdmin()) {
        header('Location: ' . APP_URL . '/admin/dashboard.php');
    } else {
        header('Location: ' . APP_URL . '/employee/dashboard.php');
    }
    exit;
}

$error = '';
$csrfToken = generateCSRFToken();

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $token = $_POST['csrf_token'] ?? '';
    
    // Validate CSRF token if provided
    if (!empty($token) && !validateCSRFToken($token)) {
        $error = 'Invalid security session token. Please try again.';
    } elseif (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        // Authenticate user
        $user = authenticateUser($username, $password);
        
        if ($user) {
            createUserSession($user);
            
            // Redirect based on role
            if ($user['role'] === 'admin') {
                redirectWith(APP_URL . '/admin/dashboard.php', 'Welcome back, ' . ($user['first_name'] ?? $user['username']) . '!', 'success');
            } else {
                redirectWith(APP_URL . '/employee/dashboard.php', 'Welcome back, ' . ($user['first_name'] ?? $user['username']) . '!', 'success');
            }
        } else {
            $error = 'Invalid username or password. Please try again.';
        }
    }
}

// Get saved theme
$savedTheme = 'light';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= $savedTheme ?>">
<head>
    <script>document.documentElement.setAttribute('data-theme', localStorage.getItem('hrms_theme') || 'light');</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="HRMS - Login to your Human Resource Management System account">
    <title>Login | <?= APP_NAME ?></title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Boxicons -->
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    
    <!-- App CSS -->
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
</head>
<body>
    <div class="login-wrapper">
        <div class="login-container">
            <div class="login-card">
                <!-- Logo -->
                <div class="login-logo">
                    <div class="login-logo-icon">
                        <i class='bx bxs-briefcase-alt-2'></i>
                    </div>
                    <h2><?= APP_NAME ?></h2>
                    <p><?= APP_FULL_NAME ?></p>
                </div>
                
                <!-- Error Message -->
                <?php if ($error): ?>
                <div class="login-error">
                    <i class='bx bx-error-circle'></i>
                    <span><?= sanitize($error) ?></span>
                </div>
                <?php endif; ?>
                
                <?php
                $flash = getFlashMessage();
                if ($flash):
                ?>
                <div class="alert alert-<?= $flash['type'] ?>" style="margin-bottom: 20px;">
                    <div class="alert-content">
                        <i class='bx bx-info-circle'></i>
                        <span><?= sanitize($flash['message']) ?></span>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Login Form -->
                <form class="login-form" method="POST" action="" id="loginForm">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <div class="input-icon-wrapper">
                            <input type="text" id="username" name="username" 
                                   placeholder="Enter your username" 
                                   value="<?= sanitize($_POST['username'] ?? '') ?>"
                                   required autocomplete="username" autofocus>
                            <i class='bx bx-user'></i>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password</label>
                        <div class="input-icon-wrapper">
                            <input type="password" id="password" name="password" 
                                   placeholder="Enter your password" 
                                   required autocomplete="current-password">
                            <i class='bx bx-lock-alt'></i>
                            <button type="button" class="password-toggle" 
                                    onclick="togglePassword('password', this)" 
                                    aria-label="Toggle password visibility">
                                <i class='bx bx-hide'></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="form-options">
                        <label class="remember-me">
                            <input type="checkbox" name="remember">
                            Remember me
                        </label>
                    </div>
                    
                    <button type="submit" class="btn btn-primary login-btn" id="loginBtn">
                        <i class='bx bx-log-in'></i>
                        Sign In
                    </button>
                </form>
                
                <!-- Quick Access -->
                <div class="login-demo">
                    <p>System Sign-In</p>
                    <div class="demo-credentials">
                        <button type="button" class="demo-btn" onclick="fillDemo('admin', 'admin123')">
                            <i class='bx bx-shield-quarter'></i> Admin (admin / admin123)
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="<?= APP_URL ?>/assets/js/app.js"></script>
    <script>
        // Add loading state on form submit
        document.getElementById('loginForm').addEventListener('submit', function() {
            const btn = document.getElementById('loginBtn');
            btn.classList.add('loading');
            btn.disabled = true;
        });
    </script>
</body>
</html>
