<?php
/**
 * HRMS - Admin System Settings
 */

require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

define('PAGE_TITLE', 'System Settings');

$pdo = getDBConnection();
$csrf_token = generateCSRFToken();
$userId = getCurrentUserId();

// Handle Profile & Password Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $token = $_POST['csrf_token'] ?? '';

    if (!validateCSRFToken($token)) {
        redirectWith(APP_URL . '/admin/settings.php', 'Invalid security token.', 'danger');
    }

    if ($action === 'update_profile') {
        $email = trim($_POST['email'] ?? '');

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            redirectWith(APP_URL . '/admin/settings.php', 'Please provide a valid email address.', 'danger');
        }

        // Check uniqueness
        $check = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $check->execute([$email, $userId]);
        if ($check->fetch()) {
            redirectWith(APP_URL . '/admin/settings.php', 'That email address is already in use.', 'warning');
        }

        $stmt = $pdo->prepare("UPDATE users SET email = ? WHERE id = ?");
        $stmt->execute([$email, $userId]);
        $_SESSION['email'] = $email;

        redirectWith(APP_URL . '/admin/settings.php', 'Profile email updated successfully!', 'success');
    }

    if ($action === 'change_password') {
        $currentPass = $_POST['current_password'] ?? '';
        $newPass = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';

        if (empty($currentPass) || empty($newPass)) {
            redirectWith(APP_URL . '/admin/settings.php', 'Please fill in all password fields.', 'danger');
        }

        if (strlen($newPass) < 6) {
            redirectWith(APP_URL . '/admin/settings.php', 'New password must be at least 6 characters long.', 'warning');
        }

        if ($newPass !== $confirmPass) {
            redirectWith(APP_URL . '/admin/settings.php', 'New passwords do not match.', 'danger');
        }

        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $userHash = $stmt->fetchColumn();

        if (!password_verify($currentPass, $userHash)) {
            redirectWith(APP_URL . '/admin/settings.php', 'Current password is incorrect.', 'danger');
        }

        $newHash = password_hash($newPass, PASSWORD_DEFAULT);
        $update = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $update->execute([$newHash, $userId]);

        redirectWith(APP_URL . '/admin/settings.php', 'Password changed successfully!', 'success');
    }
}

$userStmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$userStmt->execute([$userId]);
$user = $userStmt->fetch();

include __DIR__ . '/../includes/header.php';
?>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
    <!-- Profile & Security Column -->
    <div style="display: flex; flex-direction: column; gap: 24px;">
        <!-- Admin Profile -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">Administrator Account Details</div>
            </div>
            <form method="POST" action="">
                <div class="card-body">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <input type="hidden" name="action" value="update_profile">

                    <div class="form-group">
                        <label class="form-label">Username</label>
                        <input type="text" class="form-control" value="<?= sanitize($user['username']) ?>" disabled>
                        <small style="color: var(--text-muted);">Username cannot be changed.</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Email Address <span class="required">*</span></label>
                        <input type="email" name="email" class="form-control" required value="<?= sanitize($user['email']) ?>">
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Account Role</label>
                        <span class="badge badge-primary" style="font-size: 0.85rem; text-transform: uppercase;">
                            <?= sanitize($user['role']) ?>
                        </span>
                    </div>
                </div>
                <div class="card-footer" style="display: flex; justify-content: flex-end;">
                    <button type="submit" class="btn btn-primary"><i class='bx bx-save'></i> Save Profile</button>
                </div>
            </form>
        </div>

        <!-- Change Password -->
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
    </div>

    <!-- System Configuration & Diagnostics Column -->
    <div style="display: flex; flex-direction: column; gap: 24px;">
        <div class="card">
            <div class="card-header">
                <div class="card-title">System Environment Information</div>
            </div>
            <div class="card-body" style="padding: 0;">
                <table class="data-table">
                    <tbody>
                        <tr>
                            <td style="font-weight: 600; width: 40%;">Application Name</td>
                            <td><?= APP_NAME ?></td>
                        </tr>
                        <tr>
                            <td style="font-weight: 600;">System Version</td>
                            <td><span class="badge badge-info"><?= APP_VERSION ?></span></td>
                        </tr>
                        <tr>
                            <td style="font-weight: 600;">PHP Version</td>
                            <td><?= phpversion() ?></td>
                        </tr>
                        <tr>
                            <td style="font-weight: 600;">Database Engine</td>
                            <td>MySQL <?= $pdo->query("SELECT VERSION()")->fetchColumn() ?></td>
                        </tr>
                        <tr>
                            <td style="font-weight: 600;">Server Software</td>
                            <td><?= $_SERVER['SERVER_SOFTWARE'] ?? 'Apache / PHP' ?></td>
                        </tr>
                        <tr>
                            <td style="font-weight: 600;">Active Timezone</td>
                            <td><?= date_default_timezone_get() ?> (<?= date('P') ?>)</td>
                        </tr>
                        <tr>
                            <td style="font-weight: 600;">Current Server Time</td>
                            <td><?= date('Y-m-d H:i:s') ?></td>
                        </tr>
                        <tr>
                            <td style="font-weight: 600;">Session Timeout</td>
                            <td><?= SESSION_LIFETIME / 60 ?> minutes</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class='bx bx-palette'></i> Interface Theme Preference</div>
            </div>
            <div class="card-body">
                <p style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 14px;">
                    Select your visual display theme preference:
                </p>
                <div style="display: flex; gap: 12px;">
                    <button type="button" class="btn btn-outline" style="flex: 1;" onclick="setAppTheme('light')">
                        <i class='bx bx-sun'></i> Light Theme
                    </button>
                    <button type="button" class="btn btn-outline" style="flex: 1;" onclick="setAppTheme('dark')">
                        <i class='bx bx-moon'></i> Dark Theme
                    </button>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div class="card-title">Default Credentials Reference</div>
            </div>
            <div class="card-body" style="font-size: 0.9rem; color: var(--text-secondary);">
                <p style="margin-bottom: 12px;">This HRMS installation is configured with default project credentials for examination & testing:</p>
                <div style="background: var(--bg-hover); padding: 12px; border-radius: var(--border-radius); font-family: monospace; line-height: 1.6;">
                    <strong>Admin Role:</strong><br>
                    Username: <code>admin</code><br>
                    Password: <code>admin123</code><br><br>
                    <strong>Employee Role:</strong><br>
                    Username: <code>employee</code> (Priya Sharma)<br>
                    Password: <code>employee123</code>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
