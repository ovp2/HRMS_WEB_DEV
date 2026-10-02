<?php
/**
 * HRMS - Authentication Helper Functions
 * Handles login, logout, session management, and role-based access
 */

require_once __DIR__ . '/../config/database.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Authenticate user with username and password
 * @param string $username
 * @param string $password
 * @return array|false User data on success, false on failure
 */
function authenticateUser(string $username, string $password): array|false {
    $pdo = getDBConnection();
    
    $stmt = $pdo->prepare("SELECT u.*, e.first_name, e.last_name, e.employee_id AS emp_code, e.id AS emp_id 
                           FROM users u 
                           LEFT JOIN employees e ON u.id = e.user_id 
                           WHERE u.username = ? AND u.status = 'active' 
                           LIMIT 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        // Update last login
        $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $updateStmt->execute([$user['id']]);
        
        return $user;
    }
    
    return false;
}

/**
 * Create user session after successful authentication
 * @param array $user User data from database
 */
function createUserSession(array $user): void {
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['full_name'] = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
    $_SESSION['emp_id'] = $user['emp_id'] ?? null;
    $_SESSION['emp_code'] = $user['emp_code'] ?? null;
    $_SESSION['avatar'] = $user['avatar'] ?? null;
    $_SESSION['logged_in'] = true;
    $_SESSION['login_time'] = time();
    
    // Regenerate session ID to prevent session fixation
    session_regenerate_id(true);
}

/**
 * Destroy user session (logout)
 */
function destroyUserSession(): void {
    $_SESSION = [];
    
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    session_destroy();
}

/**
 * Check if user is logged in
 * @return bool
 */
function isLoggedIn(): bool {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

/**
 * Check if current user is admin
 * @return bool
 */
function isAdmin(): bool {
    return isLoggedIn() && isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/**
 * Check if current user is employee
 * @return bool
 */
function isEmployee(): bool {
    return isLoggedIn() && isset($_SESSION['role']) && $_SESSION['role'] === 'employee';
}

/**
 * Require login - redirect to login page if not authenticated
 */
function requireLogin(): void {
    if (!isLoggedIn()) {
        setFlashMessage('Please login to continue.', 'warning');
        header('Location: ' . APP_URL . '/login.php');
        exit;
    }
    
    // Check session timeout
    if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > SESSION_LIFETIME) {
        destroyUserSession();
        setFlashMessage('Your session has expired. Please login again.', 'warning');
        header('Location: ' . APP_URL . '/login.php');
        exit;
    }
}

/**
 * Require admin role
 */
function requireAdmin(): void {
    requireLogin();
    if (!isAdmin()) {
        setFlashMessage('Access denied. Admin privileges required.', 'danger');
        header('Location: ' . APP_URL . '/employee/dashboard.php');
        exit;
    }
}

/**
 * Require employee role
 */
function requireEmployee(): void {
    requireLogin();
    if (!isEmployee()) {
        header('Location: ' . APP_URL . '/admin/dashboard.php');
        exit;
    }
}

/**
 * Get current user's ID
 * @return int|null
 */
function getCurrentUserId(): ?int {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current employee's ID
 * @return int|null
 */
function getCurrentEmployeeId(): ?int {
    return $_SESSION['emp_id'] ?? null;
}

/**
 * Set flash message in session
 * @param string $message
 * @param string $type (success, danger, warning, info)
 */
function setFlashMessage(string $message, string $type = 'info'): void {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
}

/**
 * Get and clear flash message
 * @return array|null
 */
function getFlashMessage(): ?array {
    if (isset($_SESSION['flash_message'])) {
        $flash = [
            'message' => $_SESSION['flash_message'],
            'type' => $_SESSION['flash_type'] ?? 'info'
        ];
        unset($_SESSION['flash_message'], $_SESSION['flash_type']);
        return $flash;
    }
    return null;
}

/**
 * Generate CSRF token
 * @return string
 */
function generateCSRFToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF token
 * @param string $token
 * @return bool
 */
function validateCSRFToken(string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Get unread notification count for current user
 * @return int
 */
function getUnreadNotificationCount(): int {
    if (!isLoggedIn()) return 0;
    
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([getCurrentUserId()]);
    return (int)$stmt->fetchColumn();
}

/**
 * Sanitize input string
 * @param string $input
 * @return string
 */
function sanitize(string $input): string {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Redirect with flash message
 * @param string $url
 * @param string $message
 * @param string $type
 */
function redirectWith(string $url, string $message = '', string $type = 'info'): void {
    if ($message) {
        setFlashMessage($message, $type);
    }
    header('Location: ' . $url);
    exit;
}

/**
 * Convert numerical currency amount to Indian English words
 * @param float $number
 * @return string
 */
function amountToWordsINR(float $number): string {
    $no = (int)floor($number);
    $point = (int)round(($number - $no) * 100);
    $words = [
        0 => '', 1 => 'One', 2 => 'Two', 3 => 'Three', 4 => 'Four', 5 => 'Five',
        6 => 'Six', 7 => 'Seven', 8 => 'Eight', 9 => 'Nine', 10 => 'Ten',
        11 => 'Eleven', 12 => 'Twelve', 13 => 'Thirteen', 14 => 'Fourteen', 15 => 'Fifteen',
        16 => 'Sixteen', 17 => 'Seventeen', 18 => 'Eighteen', 19 => 'Nineteen',
        20 => 'Twenty', 30 => 'Thirty', 40 => 'Forty', 50 => 'Fifty',
        60 => 'Sixty', 70 => 'Seventy', 80 => 'Eighty', 90 => 'Ninety'
    ];
    
    if ($no <= 0 && $point <= 0) return 'Zero Rupees Only';

    $str = [];
    $divider = 10000000; // Crore
    $crore = (int)floor($no / $divider);
    $no %= $divider;
    if ($crore > 0) {
        $str[] = helperConvertTwoDigits($crore, $words) . ' Crore';
    }

    $divider = 100000; // Lakh
    $lakh = (int)floor($no / $divider);
    $no %= $divider;
    if ($lakh > 0) {
        $str[] = helperConvertTwoDigits($lakh, $words) . ' Lakh';
    }

    $divider = 1000; // Thousand
    $thousand = (int)floor($no / $divider);
    $no %= $divider;
    if ($thousand > 0) {
        $str[] = helperConvertTwoDigits($thousand, $words) . ' Thousand';
    }

    $hundred = (int)floor($no / 100);
    $no %= 100;
    if ($hundred > 0) {
        $str[] = ($words[$hundred] ?? '') . ' Hundred';
    }

    if ($no > 0) {
        $str[] = helperConvertTwoDigits($no, $words);
    }

    $result = trim(implode(' ', array_filter($str))) . ' Rupees';
    if ($point > 0) {
        $result .= ' and ' . helperConvertTwoDigits($point, $words) . ' Paise';
    }
    return $result . ' Only';
}

function helperConvertTwoDigits(int $num, array $words): string {
    if ($num <= 20) return $words[$num] ?? '';
    $ten = (int)floor($num / 10) * 10;
    $unit = $num % 10;
    return trim(($words[$ten] ?? '') . ' ' . ($words[$unit] ?? ''));
}

