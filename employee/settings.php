<?php
/**
 * HRMS - Employee Account Settings
 */

require_once __DIR__ . '/../includes/auth.php';
requireEmployee();

define('PAGE_TITLE', 'Account Settings');

$pdo = getDBConnection();
$csrf_token = generateCSRFToken();
$userId = getCurrentUserId();
$empId = getCurrentEmployeeId();

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $token = $_POST['csrf_token'] ?? '';

    if (!validateCSRFToken($token)) {
        redirectWith(APP_URL . '/employee/settings.php', 'Invalid security token.', 'danger');
    }

    if ($action === 'change_password') {
        $currentPass = $_POST['current_password'] ?? '';
        $newPass = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';

        if (empty($currentPass) || empty($newPass)) {
            redirectWith(APP_URL . '/employee/settings.php', 'Please fill in all password fields.', 'danger');
        }

        if (strlen($newPass) < 6) {
            redirectWith(APP_URL . '/employee/settings.php', 'New password must be at least 6 characters long.', 'warning');
        }

        if ($newPass !== $confirmPass) {
            redirectWith(APP_URL . '/employee/settings.php', 'New passwords do not match.', 'danger');
        }

        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $userHash = $stmt->fetchColumn();

        if (!password_verify($currentPass, $userHash)) {
            redirectWith(APP_URL . '/employee/settings.php', 'Current password is incorrect.', 'danger');
        }

        $newHash = password_hash($newPass, PASSWORD_DEFAULT);
        $update = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $update->execute([$newHash, $userId]);

        redirectWith(APP_URL . '/employee/settings.php', 'Password updated successfully!', 'success');
    }
}

// Fetch user & employee meta
$user = $pdo->prepare("SELECT u.*, e.employee_id AS emp_code, e.first_name, e.last_name, e.designation 
                       FROM users u 
                       LEFT JOIN employees e ON u.id = e.user_id 
                       WHERE u.id = ?");
$user->execute([$userId]);
$userData = $user->fetch();

include __DIR__ . '/../includes/header.php';
?>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
    <!-- Account Information -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">Account Information</div>
        </div>
        <div class="card-body" style="padding: 0;">
            <table class="data-table">
                <tbody>
                    <tr>
                        <td style="font-weight: 600; width: 40%;">Username</td>
                        <td><code><?= sanitize($userData['username']) ?></code></td>
                    </tr>
                    <tr>
                        <td style="font-weight: 600;">Employee Code</td>
                        <td><span class="badge badge-secondary" style="font-family: monospace;"><?= sanitize($userData['emp_code'] ?? 'EMP-') ?></span></td>
                    </tr>
                    <tr>
                        <td style="font-weight: 600;">Official Email</td>
                        <td><?= sanitize($userData['email']) ?></td>
                    </tr>
                    <tr>
                        <td style="font-weight: 600;">Designation</td>
                        <td><?= sanitize($userData['designation'] ?? 'Staff') ?></td>
                    </tr>
                    <tr>
                        <td style="font-weight: 600;">Role</td>
                        <td><span class="badge badge-primary">Employee</span></td>
                    </tr>
                    <tr>
                        <td style="font-weight: 600;">Last Session Login</td>
                        <td><?= $userData['last_login'] ? date('d M Y, h:i A', strtotime($userData['last_login'])) : 'First login' ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="card-footer" style="display: flex; justify-content: flex-end;">
            <a href="<?= APP_URL ?>/employee/profile.php" class="btn btn-outline">
                <i class='bx bx-user-circle'></i> Edit Personal Profile
            </a>
        </div>
    </div>

    <!-- Security & Password -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">Security & Password</div>
        </div>
        <form method="POST" action="">
            <div class="card-body">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <input type="hidden" name="action" value="change_password">

                <div class="form-group">
                    <label class="form-label">Current Password <span class="required">*</span></label>
                    <input type="password" name="current_password" class="form-control" required placeholder="Enter current password">
                </div>

                <div class="form-group">
                    <label class="form-label">New Password <span class="required">*</span></label>
                    <input type="password" name="new_password" class="form-control" required minlength="6" placeholder="At least 6 characters">
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Confirm New Password <span class="required">*</span></label>
                    <input type="password" name="confirm_password" class="form-control" required minlength="6" placeholder="Repeat new password">
                </div>
            </div>
            <div class="card-footer" style="display: flex; justify-content: flex-end;">
                <button type="submit" class="btn btn-primary"><i class='bx bx-lock-alt'></i> Update Password</button>
            </div>
        </form>
    </div>

    <!-- Theme Preference -->
    <div class="card" style="grid-column: span 2;">
        <div class="card-header">
            <div class="card-title"><i class='bx bx-palette'></i> Interface Display Theme</div>
        </div>
        <div class="card-body">
            <p style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 14px;">
                Choose how HRMS looks to you. You can also toggle this at any time using the moon/sun icon in the top navigation bar.
            </p>
            <div style="display: flex; gap: 14px; max-width: 400px;">
                <button type="button" class="btn btn-outline" style="flex: 1;" onclick="setAppTheme('light')">
                    <i class='bx bx-sun'></i> Light Theme
                </button>
                <button type="button" class="btn btn-outline" style="flex: 1;" onclick="setAppTheme('dark')">
                    <i class='bx bx-moon'></i> Dark Theme
                </button>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
