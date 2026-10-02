<?php
/**
 * HRMS - Employee Profile Management
 * View details, update editable contact information, and change security credentials
 */

require_once __DIR__ . '/../includes/auth.php';
requireEmployee();

define('PAGE_TITLE', 'My Profile');

$pdo = getDBConnection();
$csrf_token = generateCSRFToken();
$empId = getCurrentEmployeeId();
$userId = getCurrentUserId();

// Handle Profile Updates & Password Changes
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $token = $_POST['csrf_token'] ?? '';

    if (!validateCSRFToken($token)) {
        redirectWith(APP_URL . '/employee/profile.php', 'Invalid security token.', 'danger');
    }

    if ($action === 'update_contact') {
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $state = trim($_POST['state'] ?? '');
        $zip_code = trim($_POST['zip_code'] ?? '');
        $emergName = trim($_POST['emergency_contact_name'] ?? '');
        $emergPhone = trim($_POST['emergency_contact_phone'] ?? '');

        $stmt = $pdo->prepare("UPDATE employees SET 
            phone = ?, address = ?, city = ?, state = ?, zip_code = ?,
            emergency_contact_name = ?, emergency_contact_phone = ? 
            WHERE id = ?");
        $stmt->execute([$phone, $address, $city, $state, $zip_code, $emergName, $emergPhone, $empId]);

        redirectWith(APP_URL . '/employee/profile.php', 'Contact information updated successfully!', 'success');
    }

    if ($action === 'change_password') {
        $currentPass = $_POST['current_password'] ?? '';
        $newPass = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';

        if (empty($currentPass) || empty($newPass)) {
            redirectWith(APP_URL . '/employee/profile.php', 'Please fill in all password fields.', 'danger');
        }

        if (strlen($newPass) < 6) {
            redirectWith(APP_URL . '/employee/profile.php', 'New password must be at least 6 characters long.', 'warning');
        }

        if ($newPass !== $confirmPass) {
            redirectWith(APP_URL . '/employee/profile.php', 'New passwords do not match.', 'danger');
        }

        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $userHash = $stmt->fetchColumn();

        if (!password_verify($currentPass, $userHash)) {
            redirectWith(APP_URL . '/employee/profile.php', 'Current password is incorrect.', 'danger');
        }

        $newHash = password_hash($newPass, PASSWORD_DEFAULT);
        $update = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $update->execute([$newHash, $userId]);

        redirectWith(APP_URL . '/employee/profile.php', 'Password updated successfully!', 'success');
    }
}

// Fetch complete employee profile
$stmt = $pdo->prepare("SELECT e.*, d.name AS department_name, u.username, u.email as account_email, u.last_login 
                       FROM employees e 
                       JOIN users u ON e.user_id = u.id 
                       LEFT JOIN departments d ON e.department_id = d.id 
                       WHERE e.id = ?");
$stmt->execute([$empId]);
$emp = $stmt->fetch();

$initials = strtoupper(substr($emp['first_name'] ?? 'E', 0, 1) . substr($emp['last_name'] ?? 'M', 0, 1));

include __DIR__ . '/../includes/header.php';
?>

<!-- Profile Hero Banner -->
<div class="card" style="margin-bottom: 24px; background: linear-gradient(135deg, var(--primary-700), var(--primary-900)); color: #fff; border: none;">
    <div class="card-body" style="padding: 32px 28px;">
        <div style="display: flex; flex-wrap: wrap; gap: 24px; align-items: center;">
            <div style="width: 80px; height: 80px; border-radius: 50%; background: rgba(255,255,255,0.2); backdrop-filter: blur(10px); display: flex; align-items: center; justify-content: center; font-size: 2rem; font-weight: 800; border: 3px solid rgba(255,255,255,0.4); flex-shrink: 0;">
                <?= $initials ?>
            </div>
            <div>
                <h2 style="margin: 0; font-size: 1.6rem; color: #fff;">
                    <?= sanitize($emp['first_name'] . ' ' . $emp['last_name']) ?>
                </h2>
                <div style="display: flex; flex-wrap: wrap; gap: 10px; align-items: center; margin-top: 8px; font-size: 0.95rem; opacity: 0.9;">
                    <span class="badge" style="background: rgba(255,255,255,0.25); color: #fff; font-family: monospace; font-size: 0.85rem;">
                        <?= sanitize($emp['employee_id']) ?>
                    </span>
                    <span>•</span>
                    <span><?= sanitize($emp['designation'] ?? 'Staff') ?></span>
                    <span>•</span>
                    <span><?= sanitize($emp['department_name'] ?? 'General') ?></span>
                    <span>•</span>
                    <span class="badge" style="background: rgba(16, 185, 129, 0.4); color: #fff; text-transform: capitalize;">
                        <?= sanitize($emp['employment_type']) ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px;">
    <!-- Main Profile Details & Edit Contact -->
    <div style="display: flex; flex-direction: column; gap: 24px;">
        <!-- Official Employment Info -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">Employment & Job Information</div>
            </div>
            <div class="card-body" style="padding: 0;">
                <table class="data-table">
                    <tbody>
                        <tr>
                            <td style="font-weight: 600; width: 35%;">Employee Code</td>
                            <td><span class="badge badge-secondary" style="font-family: monospace;"><?= sanitize($emp['employee_id']) ?></span></td>
                        </tr>
                        <tr>
                            <td style="font-weight: 600;">Department</td>
                            <td><?= sanitize($emp['department_name'] ?? 'Not assigned') ?></td>
                        </tr>
                        <tr>
                            <td style="font-weight: 600;">Designation</td>
                            <td><?= sanitize($emp['designation'] ?? 'Staff') ?></td>
                        </tr>
                        <tr>
                            <td style="font-weight: 600;">Date of Joining</td>
                            <td><?= date('d M Y', strtotime($emp['date_of_joining'])) ?></td>
                        </tr>
                        <tr>
                            <td style="font-weight: 600;">Employment Type</td>
                            <td style="text-transform: capitalize;"><?= sanitize($emp['employment_type']) ?></td>
                        </tr>
                        <tr>
                            <td style="font-weight: 600;">Official Email</td>
                            <td><?= sanitize($emp['email']) ?></td>
                        </tr>
                        <tr>
                            <td style="font-weight: 600;">Account Status</td>
                            <td><span class="badge badge-success"><?= ucfirst($emp['status']) ?></span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Editable Contact Information -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">Personal & Contact Details</div>
            </div>
            <form method="POST" action="">
                <div class="card-body">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <input type="hidden" name="action" value="update_contact">

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">Phone Number</label>
                            <input type="text" name="phone" class="form-control" value="<?= sanitize($emp['phone'] ?? '') ?>" placeholder="+91 98765 43210">
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">Date of Birth</label>
                            <input type="text" class="form-control" value="<?= $emp['date_of_birth'] ? date('d M Y', strtotime($emp['date_of_birth'])) : '-' ?>" disabled>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Residential Address</label>
                        <input type="text" name="address" class="form-control" value="<?= sanitize($emp['address'] ?? '') ?>" placeholder="Address line">
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">City</label>
                            <input type="text" name="city" class="form-control" value="<?= sanitize($emp['city'] ?? '') ?>">
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">State</label>
                            <input type="text" name="state" class="form-control" value="<?= sanitize($emp['state'] ?? '') ?>">
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">Postal / Zip Code</label>
                            <input type="text" name="zip_code" class="form-control" value="<?= sanitize($emp['zip_code'] ?? '') ?>">
                        </div>
                    </div>

                    <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 20px 0;">

                    <h4 style="font-size: 0.95rem; font-weight: 700; color: var(--primary-600); margin-bottom: 14px;">
                        Emergency Contact
                    </h4>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">Contact Person Name</label>
                            <input type="text" name="emergency_contact_name" class="form-control" value="<?= sanitize($emp['emergency_contact_name'] ?? '') ?>" placeholder="e.g. Spouse / Parent">
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">Emergency Phone</label>
                            <input type="text" name="emergency_contact_phone" class="form-control" value="<?= sanitize($emp['emergency_contact_phone'] ?? '') ?>" placeholder="Phone number">
                        </div>
                    </div>
                </div>
                <div class="card-footer" style="display: flex; justify-content: flex-end;">
                    <button type="submit" class="btn btn-primary"><i class='bx bx-save'></i> Save Contact Info</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Right Column: Banking & Security -->
    <div style="display: flex; flex-direction: column; gap: 24px;">
        <!-- Banking & Statutory -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">Banking & Statutory</div>
            </div>
            <div class="card-body" style="padding: 0;">
                <table class="data-table">
                    <tbody>
                        <tr>
                            <td style="font-weight: 600; width: 45%;">Bank Name</td>
                            <td><?= sanitize($emp['bank_name'] ?? 'Not added') ?></td>
                        </tr>
                        <tr>
                            <td style="font-weight: 600;">Account No.</td>
                            <td style="font-family: monospace;"><?= sanitize($emp['bank_account'] ?? '-') ?></td>
                        </tr>
                        <tr>
                            <td style="font-weight: 600;">IFSC Code</td>
                            <td style="font-family: monospace;"><?= sanitize($emp['ifsc_code'] ?? '-') ?></td>
                        </tr>
                        <tr>
                            <td style="font-weight: 600;">PAN Number</td>
                            <td style="font-family: monospace;"><?= sanitize($emp['pan_number'] ?? '-') ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Security / Change Password -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">Change Password</div>
            </div>
            <form method="POST" action="">
                <div class="card-body">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <input type="hidden" name="action" value="change_password">

                    <div class="form-group">
                        <label class="form-label">Current Password <span class="required">*</span></label>
                        <input type="password" name="current_password" class="form-control" required placeholder="Current password">
                    </div>

                    <div class="form-group">
                        <label class="form-label">New Password <span class="required">*</span></label>
                        <input type="password" name="new_password" class="form-control" required minlength="6" placeholder="Min. 6 characters">
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Confirm New Password <span class="required">*</span></label>
                        <input type="password" name="confirm_password" class="form-control" required minlength="6" placeholder="Repeat password">
                    </div>
                </div>
                <div class="card-footer" style="display: flex; justify-content: flex-end;">
                    <button type="submit" class="btn btn-primary"><i class='bx bx-lock-alt'></i> Update Password</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
