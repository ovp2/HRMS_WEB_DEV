<?php
/**
 * HRMS - Admin Employee Management
 * Phase 3: Comprehensive Employee CRUD, Advanced Search & Filter, Status Toggle, CSV Export, and Modals
 */

require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

define('PAGE_TITLE', 'Employees');

$pdo = getDBConnection();
$csrf_token = generateCSRFToken();

// Helper: Automatically generate next unique employee ID (e.g. EMP001, EMP002, EMP003)
function getNextAvailableEmployeeId(PDO $pdo): string {
    $stmt = $pdo->query("SELECT employee_id FROM employees WHERE employee_id LIKE 'EMP%'");
    $maxNum = 0;
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (preg_match('/^EMP(\d+)$/i', $row['employee_id'], $m)) {
            $val = (int)$m[1];
            if ($val > $maxNum) {
                $maxNum = $val;
            }
        }
    }
    return 'EMP' . str_pad((string)($maxNum + 1), 3, '0', STR_PAD_LEFT);
}

// Helper: Handle safe avatar image upload
function handleAvatarUpload(?array $file, ?string $currentAvatar = null): ?string {
    if (!isset($file) || !isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return $currentAvatar;
    }
    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime, $allowedMimes)) {
        return $currentAvatar;
    }
    if ($file['size'] > 5 * 1024 * 1024) { // Max 5MB
        return $currentAvatar;
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
        $ext = 'jpg';
    }
    $filename = 'emp_' . uniqid() . '.' . $ext;
    $targetDir = __DIR__ . '/../uploads/avatars/';
    if (!is_dir($targetDir)) {
        @mkdir($targetDir, 0777, true);
    }
    $targetPath = $targetDir . $filename;
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        $relPath = 'uploads/avatars/' . $filename;
        // Also sync to XAMPP htdocs if running directly from OneDrive
        $xamppTarget = 'C:/xampp/htdocs/hrms/uploads/avatars/' . $filename;
        if (is_dir(dirname($xamppTarget))) {
            @copy($targetPath, $xamppTarget);
        }
        return $relPath;
    }
    return $currentAvatar;
}

// Handle Form Submissions (Create, Edit, Toggle Status, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $token = $_POST['csrf_token'] ?? '';

    if (!validateCSRFToken($token)) {
        redirectWith(APP_URL . '/admin/employees.php', 'Invalid security token.', 'danger');
    }

    if ($action === 'create') {
        // Personal Information
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $gender = in_array($_POST['gender'] ?? '', ['male', 'female', 'other']) ? $_POST['gender'] : 'male';
        $dob = !empty($_POST['date_of_birth']) ? $_POST['date_of_birth'] : null;
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $state = trim($_POST['state'] ?? '');
        $zip_code = trim($_POST['zip_code'] ?? '');

        // Employment Information
        $emp_id = trim($_POST['employee_id'] ?? '');
        if (empty($emp_id)) {
            $emp_id = getNextAvailableEmployeeId($pdo);
        }
        $department_id = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
        $designation = trim($_POST['designation'] ?? '');
        $doj = !empty($_POST['date_of_joining']) ? $_POST['date_of_joining'] : date('Y-m-d');
        $emp_type = in_array($_POST['employment_type'] ?? '', ['full-time', 'part-time', 'contract', 'intern']) ? $_POST['employment_type'] : 'full-time';
        $salary = (float)($_POST['salary'] ?? 0);
        $status = in_array($_POST['status'] ?? '', ['active', 'inactive', 'on-leave', 'terminated']) ? $_POST['status'] : 'active';

        // System Login Credentials (optional - auto-generated if left blank)
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if (empty($username)) {
            $cleanFirst = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $first_name));
            $cleanLast = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $last_name));
            $baseUser = ($cleanFirst && $cleanLast) ? "$cleanFirst.$cleanLast" : ($cleanFirst ?: $cleanLast);
            if (empty($baseUser)) {
                $baseUser = strtolower(explode('@', $email)[0] ?? 'emp');
            }
            $username = $baseUser;
            $counter = 1;
            while (true) {
                $uCheck = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                $uCheck->execute([$username]);
                if (!$uCheck->fetch()) break;
                $username = $baseUser . $counter;
                $counter++;
            }
        }

        if (empty($password)) {
            $password = 'Employee@123';
        }

        // Optional Banking & Emergency Details
        $bank_name = trim($_POST['bank_name'] ?? '');
        $bank_account = trim($_POST['bank_account'] ?? '');
        $ifsc_code = trim($_POST['ifsc_code'] ?? '');
        $pan_number = trim($_POST['pan_number'] ?? '');
        $emerg_name = trim($_POST['emergency_contact_name'] ?? '');
        $emerg_phone = trim($_POST['emergency_contact_phone'] ?? '');

        // Validation
        if (empty($first_name) || empty($last_name)) {
            redirectWith(APP_URL . '/admin/employees.php', 'Please enter first name and last name.', 'danger');
        }
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            redirectWith(APP_URL . '/admin/employees.php', 'Please provide a valid official email address.', 'warning');
        }
        if (empty($designation)) {
            redirectWith(APP_URL . '/admin/employees.php', 'Please specify the employee designation.', 'danger');
        }

        // Validate department if specified
        if ($department_id) {
            $dStmt = $pdo->prepare("SELECT id FROM departments WHERE id = ?");
            $dStmt->execute([$department_id]);
            if (!$dStmt->fetch()) {
                $department_id = null;
            }
        }

        // Check if username or email already exists
        $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $check->execute([$email]);
        if ($check->fetch()) {
            redirectWith(APP_URL . '/admin/employees.php', 'A user with email "' . $email . '" already exists.', 'warning');
        }

        $checkUser = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $checkUser->execute([$username]);
        if ($checkUser->fetch()) {
            redirectWith(APP_URL . '/admin/employees.php', 'A user with username "' . $username . '" already exists.', 'warning');
        }

        // Check or auto-advance Employee ID if collision
        $checkEmp = $pdo->prepare("SELECT id FROM employees WHERE employee_id = ?");
        $checkEmp->execute([$emp_id]);
        if ($checkEmp->fetch()) {
            $emp_id = getNextAvailableEmployeeId($pdo);
        }

        // Process avatar upload
        $avatarPath = handleAvatarUpload($_FILES['avatar'] ?? null);

        try {
            $pdo->beginTransaction();

            // 1. Create user account
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $userStatus = in_array($status, ['active', 'on-leave']) ? 'active' : 'inactive';
            $stmtUser = $pdo->prepare("INSERT INTO users (username, password, email, role, status, avatar) VALUES (?, ?, ?, 'employee', ?, ?)");
            $stmtUser->execute([$username, $hashedPassword, $email, $userStatus, $avatarPath]);
            $newUserId = (int)$pdo->lastInsertId();

            // 2. Create employee record
            $stmtEmp = $pdo->prepare("INSERT INTO employees (
                user_id, employee_id, first_name, last_name, email, phone,
                date_of_birth, gender, address, city, state, zip_code,
                department_id, designation, date_of_joining, employment_type, salary,
                avatar, bank_name, bank_account, ifsc_code, pan_number,
                emergency_contact_name, emergency_contact_phone, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

            $stmtEmp->execute([
                $newUserId, $emp_id, $first_name, $last_name, $email, $phone,
                $dob, $gender, $address, $city, $state, $zip_code,
                $department_id, $designation, $doj, $emp_type, $salary,
                $avatarPath, $bank_name, $bank_account, $ifsc_code, $pan_number,
                $emerg_name, $emerg_phone, $status
            ]);

            // 3. Send welcome notification to user
            $notif = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, link) VALUES (?, ?, ?, 'info', ?)");
            $notif->execute([
                $newUserId,
                'Welcome to ' . APP_NAME . '!',
                'Your employee account (' . $emp_id . ') has been registered. You can manage your profile, view attendance, leaves, and payroll here.',
                APP_URL . '/employee/dashboard.php'
            ]);

            $pdo->commit();
            redirectWith(APP_URL . '/admin/employees.php', 'Employee "' . $first_name . ' ' . $last_name . '" (ID: ' . $emp_id . ') registered successfully!', 'success');
        } catch (Exception $e) {
            $pdo->rollBack();
            redirectWith(APP_URL . '/admin/employees.php', 'Failed to register employee: ' . $e->getMessage(), 'danger');
        }
    }

    if ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $user_id = (int)($_POST['user_id'] ?? 0);
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $gender = in_array($_POST['gender'] ?? '', ['male', 'female', 'other']) ? $_POST['gender'] : 'male';
        $dob = !empty($_POST['date_of_birth']) ? $_POST['date_of_birth'] : null;
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $state = trim($_POST['state'] ?? '');
        $zip_code = trim($_POST['zip_code'] ?? '');

        $department_id = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
        $designation = trim($_POST['designation'] ?? '');
        $doj = !empty($_POST['date_of_joining']) ? $_POST['date_of_joining'] : null;
        $emp_type = in_array($_POST['employment_type'] ?? '', ['full-time', 'part-time', 'contract', 'intern']) ? $_POST['employment_type'] : 'full-time';
        $salary = (float)($_POST['salary'] ?? 0);
        $status = in_array($_POST['status'] ?? '', ['active', 'inactive', 'on-leave', 'terminated']) ? $_POST['status'] : 'active';
        $bank_name = trim($_POST['bank_name'] ?? '');
        $bank_account = trim($_POST['bank_account'] ?? '');
        $ifsc_code = trim($_POST['ifsc_code'] ?? '');
        $pan_number = trim($_POST['pan_number'] ?? '');
        $emerg_name = trim($_POST['emergency_contact_name'] ?? '');
        $emerg_phone = trim($_POST['emergency_contact_phone'] ?? '');
        $new_password = trim($_POST['new_password'] ?? '');

        if ($id <= 0 || empty($first_name) || empty($last_name) || empty($email) || empty($designation)) {
            redirectWith(APP_URL . '/admin/employees.php', 'Please fill in all required fields.', 'danger');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            redirectWith(APP_URL . '/admin/employees.php', 'Please enter a valid email address.', 'warning');
        }

        if ($user_id <= 0) {
            $uStmt = $pdo->prepare("SELECT user_id FROM employees WHERE id = ?");
            $uStmt->execute([$id]);
            $user_id = (int)$uStmt->fetchColumn();
        }

        // Check email uniqueness excluding self
        $checkEmail = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $checkEmail->execute([$email, $user_id]);
        if ($checkEmail->fetch()) {
            redirectWith(APP_URL . '/admin/employees.php', 'Email is already used by another user.', 'warning');
        }

        // Existing avatar check and optional replacement
        $curStmt = $pdo->prepare("SELECT avatar FROM employees WHERE id = ?");
        $curStmt->execute([$id]);
        $curAvatar = $curStmt->fetchColumn() ?: null;
        $avatarPath = handleAvatarUpload($_FILES['avatar'] ?? null, $curAvatar);

        try {
            $pdo->beginTransaction();

            $userStatus = in_array($status, ['active', 'on-leave']) ? 'active' : 'inactive';

            // Update user table
            if (!empty($new_password)) {
                $hash = password_hash($new_password, PASSWORD_DEFAULT);
                $uStmt = $pdo->prepare("UPDATE users SET email = ?, password = ?, status = ?, avatar = ? WHERE id = ?");
                $uStmt->execute([$email, $hash, $userStatus, $avatarPath, $user_id]);
            } else {
                $uStmt = $pdo->prepare("UPDATE users SET email = ?, status = ?, avatar = ? WHERE id = ?");
                $uStmt->execute([$email, $userStatus, $avatarPath, $user_id]);
            }

            // Update employees table
            $eStmt = $pdo->prepare("UPDATE employees SET 
                first_name = ?, last_name = ?, email = ?, phone = ?, date_of_birth = ?,
                gender = ?, address = ?, city = ?, state = ?, zip_code = ?,
                department_id = ?, designation = ?, date_of_joining = COALESCE(?, date_of_joining),
                employment_type = ?, salary = ?, avatar = ?, bank_name = ?, bank_account = ?,
                ifsc_code = ?, pan_number = ?, emergency_contact_name = ?,
                emergency_contact_phone = ?, status = ?
                WHERE id = ?");
            
            $eStmt->execute([
                $first_name, $last_name, $email, $phone, $dob,
                $gender, $address, $city, $state, $zip_code,
                $department_id, $designation, $doj, $emp_type, $salary,
                $avatarPath, $bank_name, $bank_account, $ifsc_code, $pan_number,
                $emerg_name, $emerg_phone, $status, $id
            ]);

            $pdo->commit();
            redirectWith(APP_URL . '/admin/employees.php', 'Employee profile updated successfully!', 'success');
        } catch (Exception $e) {
            $pdo->rollBack();
            redirectWith(APP_URL . '/admin/employees.php', 'Update failed: ' . $e->getMessage(), 'danger');
        }
    }

    if ($action === 'toggle_status') {
        $id = (int)($_POST['id'] ?? 0);
        $user_id = (int)($_POST['user_id'] ?? 0);
        $new_status = in_array($_POST['status'] ?? '', ['active', 'inactive', 'on-leave']) ? $_POST['status'] : 'active';

        if ($id > 0 && $user_id > 0) {
            $pdo->beginTransaction();
            $stmt1 = $pdo->prepare("UPDATE employees SET status = ? WHERE id = ?");
            $stmt1->execute([$new_status, $id]);
            $userStatus = in_array($new_status, ['active', 'on-leave']) ? 'active' : 'inactive';
            $stmt2 = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
            $stmt2->execute([$userStatus, $user_id]);
            $pdo->commit();

            redirectWith(APP_URL . '/admin/employees.php', "Employee account set to " . ucfirst($new_status) . ".", 'success');
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $user_id = (int)($_POST['user_id'] ?? 0);

        if ($id <= 0) {
            redirectWith(APP_URL . '/admin/employees.php', 'Invalid employee ID specified.', 'danger');
        }

        // Verify employee exists in database
        $empStmt = $pdo->prepare("SELECT id, user_id, first_name, last_name, avatar FROM employees WHERE id = ?");
        $empStmt->execute([$id]);
        $emp = $empStmt->fetch();

        if (!$emp) {
            redirectWith(APP_URL . '/admin/employees.php', 'Employee record not found or already deleted.', 'warning');
        }

        $userId = !empty($emp['user_id']) ? (int)$emp['user_id'] : $user_id;

        try {
            $pdo->beginTransaction();

            // 1. Unlink department head if this employee was head of any department
            $clearHead = $pdo->prepare("UPDATE departments SET head_id = NULL WHERE head_id = ?");
            $clearHead->execute([$id]);

            // 2. Safely delete related attendance records
            $delAtt = $pdo->prepare("DELETE FROM attendance WHERE employee_id = ?");
            $delAtt->execute([$id]);

            // 3. Safely delete related leave requests
            $delLeaves = $pdo->prepare("DELETE FROM leaves WHERE employee_id = ?");
            $delLeaves->execute([$id]);

            // 4. Safely delete related payroll records
            $delPayroll = $pdo->prepare("DELETE FROM payroll WHERE employee_id = ?");
            $delPayroll->execute([$id]);

            // 5. Safely delete related user notifications
            if ($userId > 0) {
                $delNotif = $pdo->prepare("DELETE FROM notifications WHERE user_id = ?");
                $delNotif->execute([$userId]);
            }

            // 6. Delete employee record
            $delEmp = $pdo->prepare("DELETE FROM employees WHERE id = ?");
            $delEmp->execute([$id]);

            // 7. Delete linked user login account
            if ($userId > 0) {
                $delUser = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $delUser->execute([$userId]);
            }

            $pdo->commit();

            // 8. Remove avatar image file if exists
            if (!empty($emp['avatar'])) {
                $avatarPath = __DIR__ . '/../' . $emp['avatar'];
                if (file_exists($avatarPath)) {
                    @unlink($avatarPath);
                }
                $xamppAvatar = 'C:/xampp/htdocs/hrms/' . $emp['avatar'];
                if (file_exists($xamppAvatar)) {
                    @unlink($xamppAvatar);
                }
            }

            redirectWith(APP_URL . '/admin/employees.php', 'Employee deleted successfully.', 'success');
        } catch (Exception $e) {
            $pdo->rollBack();
            redirectWith(APP_URL . '/admin/employees.php', 'Failed to delete employee: ' . $e->getMessage(), 'danger');
        }
    }
}

// Filters & Search
$search = trim($_GET['search'] ?? '');
$deptFilter = !empty($_GET['department_id']) ? (int)$_GET['department_id'] : 0;
$statusFilter = trim($_GET['status'] ?? '');
$typeFilter = trim($_GET['type'] ?? '');

$query = "SELECT e.*, d.name AS department_name, u.username, u.last_login,
                 (SELECT COUNT(*) FROM attendance a WHERE a.employee_id = e.id AND a.status IN ('present', 'late')) AS present_count,
                 (SELECT COALESCE(SUM(total_days), 0) FROM leaves l WHERE l.employee_id = e.id AND l.status = 'approved') AS leave_days
          FROM employees e 
          JOIN users u ON e.user_id = u.id 
          LEFT JOIN departments d ON e.department_id = d.id 
          WHERE 1=1";
$params = [];

if ($search !== '') {
    $query .= " AND (e.first_name LIKE ? OR e.last_name LIKE ? OR e.employee_id LIKE ? OR e.email LIKE ? OR e.designation LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($deptFilter > 0) {
    $query .= " AND e.department_id = ?";
    $params[] = $deptFilter;
}

if ($statusFilter !== '' && in_array($statusFilter, ['active', 'inactive', 'terminated'])) {
    $query .= " AND e.status = ?";
    $params[] = $statusFilter;
}

if ($typeFilter !== '' && in_array($typeFilter, ['full-time', 'part-time', 'contract', 'intern'])) {
    $query .= " AND e.employment_type = ?";
    $params[] = $typeFilter;
}

$query .= " ORDER BY e.id DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$employees = $stmt->fetchAll();

// CSV Export Stream Handler
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=hrms_employees_' . date('Ymd_His') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Employee Code', 'First Name', 'Last Name', 'Email', 'Phone', 'Department', 'Designation', 'Joining Date', 'Type', 'Salary (INR)', 'Status', 'Bank Name', 'Account No', 'IFSC Code', 'PAN']);
    foreach ($employees as $row) {
        fputcsv($output, [
            $row['id'],
            $row['employee_id'],
            $row['first_name'],
            $row['last_name'],
            $row['email'],
            $row['phone'],
            $row['department_name'] ?? 'Unassigned',
            $row['designation'],
            $row['date_of_joining'],
            $row['employment_type'],
            $row['salary'],
            $row['status'],
            $row['bank_name'],
            $row['bank_account'],
            $row['ifsc_code'],
            $row['pan_number']
        ]);
    }
    fclose($output);
    exit;
}

// Global stats
$totalEmps = $pdo->query("SELECT COUNT(*) FROM employees")->fetchColumn();
$activeEmps = $pdo->query("SELECT COUNT(*) FROM employees WHERE status = 'active'")->fetchColumn();
$fullTimeEmps = $pdo->query("SELECT COUNT(*) FROM employees WHERE employment_type = 'full-time' AND status = 'active'")->fetchColumn();
$departments = $pdo->query("SELECT id, name FROM departments WHERE status = 'active' ORDER BY name ASC")->fetchAll();

// Next suggested Employee ID
$suggestedId = getNextAvailableEmployeeId($pdo);


include __DIR__ . '/../includes/header.php';
?>

<!-- Statistics Overview -->
<div class="stats-grid" style="margin-bottom: 24px;">
    <div class="stat-card blue">
        <div class="stat-info">
            <h4>Total Staff</h4>
            <div class="stat-number"><?= $totalEmps ?></div>
            <div class="stat-change up"><i class='bx bx-group'></i> Enrolled</div>
        </div>
        <div class="stat-icon blue"><i class='bx bxs-user-detail'></i></div>
    </div>
    <div class="stat-card green">
        <div class="stat-info">
            <h4>Active Employees</h4>
            <div class="stat-number"><?= $activeEmps ?></div>
            <div class="stat-change up"><i class='bx bx-check-circle'></i> In Service</div>
        </div>
        <div class="stat-icon green"><i class='bx bxs-user-check'></i></div>
    </div>
    <div class="stat-card orange">
        <div class="stat-info">
            <h4>Full-Time Staff</h4>
            <div class="stat-number"><?= $fullTimeEmps ?></div>
            <div class="stat-change up"><i class='bx bx-briefcase'></i> Permanent</div>
        </div>
        <div class="stat-icon orange"><i class='bx bxs-briefcase-alt-2'></i></div>
    </div>
</div>

<!-- Search & Filters Toolbar -->
<div class="card" style="margin-bottom: 24px;">
    <div class="card-body" style="padding: 16px 20px;">
        <div style="display: flex; flex-wrap: wrap; gap: 12px; justify-content: space-between; align-items: center;">
            <form method="GET" action="" style="display: flex; flex-wrap: wrap; gap: 10px; flex: 1; align-items: center;">
                <input type="text" name="search" class="form-control" style="max-width: 240px;" placeholder="Search name, ID, title..." value="<?= sanitize($search) ?>">
                
                <select name="department_id" class="form-control" style="width: 170px;">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $d): ?>
                    <option value="<?= $d['id'] ?>" <?= $deptFilter === (int)$d['id'] ? 'selected' : '' ?>>
                        <?= sanitize($d['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>

                <select name="status" class="form-control" style="width: 130px;">
                    <option value="">All Status</option>
                    <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    <option value="terminated" <?= $statusFilter === 'terminated' ? 'selected' : '' ?>>Terminated</option>
                </select>

                <select name="type" class="form-control" style="width: 140px;">
                    <option value="">All Types</option>
                    <option value="full-time" <?= $typeFilter === 'full-time' ? 'selected' : '' ?>>Full-Time</option>
                    <option value="part-time" <?= $typeFilter === 'part-time' ? 'selected' : '' ?>>Part-Time</option>
                    <option value="contract" <?= $typeFilter === 'contract' ? 'selected' : '' ?>>Contract</option>
                    <option value="intern" <?= $typeFilter === 'intern' ? 'selected' : '' ?>>Intern</option>
                </select>

                <button type="submit" class="btn btn-primary" style="padding: 0 16px;">
                    <i class='bx bx-search'></i> Filter
                </button>
                <?php if ($search !== '' || $deptFilter > 0 || $statusFilter !== '' || $typeFilter !== ''): ?>
                <a href="<?= APP_URL ?>/admin/employees.php" class="btn btn-outline" title="Reset Filters">
                    <i class='bx bx-reset'></i> Reset
                </a>
                <?php endif; ?>
            </form>

            <div style="display: flex; gap: 10px; align-items: center;">
                <a href="?export=csv<?= $search ? '&search=' . urlencode($search) : '' ?><?= $deptFilter ? '&department_id=' . $deptFilter : '' ?><?= $statusFilter ? '&status=' . urlencode($statusFilter) : '' ?>" class="btn btn-outline" title="Export current list to CSV">
                    <i class='bx bx-download'></i> Export CSV
                </a>
                <button type="button" class="btn btn-primary" onclick="openModal('addEmpModal')">
                    <i class='bx bx-user-plus'></i> Add New Employee
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Employees Table -->
<div class="card">
    <div class="card-header">
        <div class="card-title">Employee Directory</div>
        <span class="badge badge-secondary"><?= count($employees) ?> Employees Found</span>
    </div>
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>ID</th>
                    <th>Department</th>
                    <th>Designation</th>
                    <th>Type</th>
                    <th>Monthly Salary</th>
                    <th>Status</th>
                    <th style="text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($employees)): ?>
                <tr>
                    <td colspan="8" style="text-align: center; padding: 48px; color: var(--text-muted);">
                        <i class='bx bx-user-x' style="font-size: 3rem; margin-bottom: 8px; display: block;"></i>
                        No employees found matching the specified criteria.
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($employees as $emp): 
                        $fullName = $emp['first_name'] . ' ' . $emp['last_name'];
                        $initials = strtoupper(substr($emp['first_name'], 0, 1) . substr($emp['last_name'], 0, 1));
                    ?>
                    <tr>
                        <td>
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <?php if (!empty($emp['avatar']) && file_exists(__DIR__ . '/../' . $emp['avatar'])): ?>
                                <img src="<?= APP_URL . '/' . htmlspecialchars($emp['avatar']) ?>" alt="<?= sanitize($fullName) ?>" style="width: 36px; height: 36px; border-radius: 50%; object-fit: cover;">
                                <?php else: ?>
                                <div class="user-avatar-sm" style="width: 36px; height: 36px; font-size: 0.85rem; font-weight: 700;">
                                    <?= $initials ?>
                                </div>
                                <?php endif; ?>
                                <div>
                                    <div style="font-weight: 600; color: var(--text-primary);">
                                        <?= sanitize($fullName) ?>
                                    </div>
                                    <div style="color: var(--text-muted); font-size: 0.8rem;">
                                        <?= sanitize($emp['email']) ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="badge badge-secondary" style="font-family: monospace; font-size: 0.85rem;">
                                <?= sanitize($emp['employee_id']) ?>
                            </span>
                        </td>
                        <td>
                            <?= !empty($emp['department_name']) ? sanitize($emp['department_name']) : '<span style="color:var(--text-muted);">Unassigned</span>' ?>
                        </td>
                        <td>
                            <div style="font-weight: 500; font-size: 0.9rem;"><?= sanitize($emp['designation'] ?: 'Staff') ?></div>
                        </td>
                        <td>
                            <span class="badge badge-info" style="text-transform: capitalize;">
                                <?= sanitize($emp['employment_type']) ?>
                            </span>
                        </td>
                        <td>
                            <strong style="color: var(--text-primary);">₹<?= number_format($emp['salary'], 2) ?></strong>
                        </td>
                        <td>
                            <?php if ($emp['status'] === 'active'): ?>
                            <span class="badge badge-success">Active</span>
                            <?php elseif ($emp['status'] === 'inactive'): ?>
                            <span class="badge badge-warning">Inactive</span>
                            <?php elseif ($emp['status'] === 'on-leave'): ?>
                            <span class="badge badge-secondary">On Leave</span>
                            <?php else: ?>
                            <span class="badge badge-danger">Terminated</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: right; white-space: nowrap;">
                            <!-- View -->
                            <button type="button" class="btn btn-outline btn-sm" onclick='viewEmployee(<?= json_encode($emp) ?>)' title="View Full Details">
                                <i class='bx bx-show'></i>
                            </button>

                            <!-- Edit -->
                            <button type="button" class="btn btn-outline btn-sm" onclick='editEmployee(<?= json_encode($emp) ?>)' title="Edit Profile">
                                <i class='bx bx-edit-alt'></i>
                            </button>

                            <!-- Quick Toggle Status -->
                            <form method="POST" action="" style="display: inline;" onsubmit="return confirm('Change status of <?= addslashes(sanitize($fullName)) ?> to <?= $emp['status'] === 'active' ? 'Inactive' : 'Active' ?>?')">
                                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                <input type="hidden" name="action" value="toggle_status">
                                <input type="hidden" name="id" value="<?= $emp['id'] ?>">
                                <input type="hidden" name="user_id" value="<?= $emp['user_id'] ?>">
                                <input type="hidden" name="status" value="<?= $emp['status'] === 'active' ? 'inactive' : 'active' ?>">
                                <button type="submit" class="btn btn-outline btn-sm" title="<?= $emp['status'] === 'active' ? 'Deactivate Employee' : 'Activate Employee' ?>" style="color: <?= $emp['status'] === 'active' ? 'var(--warning)' : 'var(--success)' ?>;">
                                    <i class='bx <?= $emp['status'] === 'active' ? 'bx-pause-circle' : 'bx-play-circle' ?>'></i>
                                </button>
                            </form>

                            <!-- Delete -->
                            <button type="button" class="btn btn-danger btn-sm" onclick="confirmDeleteEmployee(<?= (int)$emp['id'] ?>, <?= (int)$emp['user_id'] ?>, '<?= addslashes(sanitize($fullName)) ?>')" title="Delete Employee">
                                <i class='bx bx-trash'></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- View Employee Details Modal -->
<div class="modal-overlay" id="viewEmpModal">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h3>Employee Profile Overview</h3>
            <button class="modal-close" onclick="closeModal('viewEmpModal')"><i class='bx bx-x'></i></button>
        </div>
        <div class="modal-body" id="viewEmpBody">
            <!-- Dynamically populated via JS -->
        </div>
        <div class="modal-footer" id="viewEmpFooter">
            <button type="button" class="btn btn-secondary" onclick="closeModal('viewEmpModal')">Close</button>
        </div>
    </div>
</div>

<!-- Add Employee Modal -->
<div class="modal-overlay" id="addEmpModal">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h3>Register New Employee</h3>
            <button class="modal-close" onclick="closeModal('addEmpModal')"><i class='bx bx-x'></i></button>
        </div>
        <form method="POST" action="" id="addEmpForm" enctype="multipart/form-data">
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <input type="hidden" name="action" value="create">

                <!-- Personal Information -->
                <h4 style="font-size: 0.95rem; font-weight: 700; color: var(--primary-600); margin-bottom: 12px; display: flex; align-items: center; gap: 6px;">
                    <i class='bx bx-user'></i> Personal Information
                </h4>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 20px;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">First Name <span class="required">*</span></label>
                        <input type="text" name="first_name" id="addFirstName" class="form-control" required placeholder="First name">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Last Name <span class="required">*</span></label>
                        <input type="text" name="last_name" id="addLastName" class="form-control" required placeholder="Last name">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Gender</label>
                        <select name="gender" class="form-control">
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Date of Birth</label>
                        <input type="date" name="date_of_birth" class="form-control">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Phone Number</label>
                        <input type="text" name="phone" class="form-control" placeholder="+91 98765 43210">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Official Email <span class="required">*</span></label>
                        <input type="email" name="email" id="addEmail" class="form-control" required placeholder="name@company.com">
                    </div>
                    <div class="form-group" style="grid-column: 1 / -1; margin-bottom: 0;">
                        <label class="form-label">Address</label>
                        <input type="text" name="address" class="form-control" placeholder="Street address / locality">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">City</label>
                        <input type="text" name="city" class="form-control" placeholder="City">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">State</label>
                        <input type="text" name="state" class="form-control" placeholder="State">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Pincode</label>
                        <input type="text" name="zip_code" class="form-control" placeholder="e.g. 380015">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Profile Photo</label>
                        <input type="file" name="avatar" class="form-control" accept="image/png, image/jpeg, image/webp">
                    </div>
                </div>

                <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 20px 0;">

                <!-- Employment Information -->
                <h4 style="font-size: 0.95rem; font-weight: 700; color: var(--primary-600); margin-bottom: 12px; display: flex; align-items: center; gap: 6px;">
                    <i class='bx bx-briefcase'></i> Employment Information
                </h4>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 20px;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Employee ID <span class="required">*</span></label>
                        <input type="text" name="employee_id" class="form-control" required value="<?= $suggestedId ?>" placeholder="e.g. EMP003">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Department</label>
                        <select name="department_id" class="form-control">
                            <option value="">-- Select Department --</option>
                            <?php foreach ($departments as $d): ?>
                            <option value="<?= $d['id'] ?>"><?= sanitize($d['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Designation <span class="required">*</span></label>
                        <input type="text" name="designation" class="form-control" required placeholder="e.g. Software Engineer">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Joining Date <span class="required">*</span></label>
                        <input type="date" name="date_of_joining" class="form-control" required value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Employment Type</label>
                        <select name="employment_type" class="form-control">
                            <option value="full-time">Full-Time</option>
                            <option value="part-time">Part-Time</option>
                            <option value="contract">Contract</option>
                            <option value="intern">Intern</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Monthly Gross Salary (₹) <span class="required">*</span></label>
                        <input type="number" step="0.01" name="salary" class="form-control" required placeholder="e.g. 50000">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-control">
                            <option value="active" selected>Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="on-leave">On Leave</option>
                        </select>
                    </div>
                </div>

                <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 20px 0;">

                <!-- System Login Credentials (Optional) -->
                <h4 style="font-size: 0.95rem; font-weight: 700; color: var(--primary-600); margin-bottom: 12px; display: flex; align-items: center; gap: 6px;">
                    <i class='bx bx-lock-alt'></i> System Login Account <span style="font-size: 0.75rem; font-weight: 400; color: var(--text-muted);">(Optional - auto-generated if left blank)</span>
                </h4>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 20px;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Login Username</label>
                        <input type="text" name="username" class="form-control" placeholder="Default: firstname.lastname">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Login Password</label>
                        <input type="password" name="password" class="form-control" placeholder="Default: Employee@123">
                    </div>
                </div>

                <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 20px 0;">

                <!-- Optional Banking & Emergency Details -->
                <h4 style="font-size: 0.95rem; font-weight: 700; color: var(--primary-600); margin-bottom: 12px; display: flex; align-items: center; gap: 6px;">
                    <i class='bx bx-credit-card'></i> Banking & Emergency Contact <span style="font-size: 0.75rem; font-weight: 400; color: var(--text-muted);">(Optional)</span>
                </h4>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Bank Name</label>
                        <input type="text" name="bank_name" class="form-control" placeholder="e.g. HDFC Bank">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Account Number</label>
                        <input type="text" name="bank_account" class="form-control" placeholder="Account No">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">IFSC Code</label>
                        <input type="text" name="ifsc_code" class="form-control" placeholder="HDFC0001234">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">PAN Number</label>
                        <input type="text" name="pan_number" class="form-control" placeholder="ABCDE1234F">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Emergency Contact Name</label>
                        <input type="text" name="emergency_contact_name" class="form-control" placeholder="Contact person">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Emergency Contact Phone</label>
                        <input type="text" name="emergency_contact_phone" class="form-control" placeholder="Phone number">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('addEmpModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class='bx bx-check'></i> Create Employee Record</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Employee Modal -->
<div class="modal-overlay" id="editEmpModal">
    <div class="modal modal-lg">
        <div class="modal-header">
            <div style="display: flex; align-items: center; gap: 10px;">
                <h3 style="margin: 0;">Edit Employee Profile</h3>
                <span class="badge badge-secondary" id="editEmployeeIdBadge" style="font-family: monospace; font-size: 0.9rem;"></span>
            </div>
            <button class="modal-close" onclick="closeModal('editEmpModal')"><i class='bx bx-x'></i></button>
        </div>
        <form method="POST" action="" id="editEmpForm" enctype="multipart/form-data">
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="editId">
                <input type="hidden" name="user_id" id="editUserId">

                <!-- Personal Information -->
                <h4 style="font-size: 0.95rem; font-weight: 700; color: var(--primary-600); margin-bottom: 12px; display: flex; align-items: center; gap: 6px;">
                    <i class='bx bx-user'></i> Personal Information
                </h4>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 20px;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">First Name <span class="required">*</span></label>
                        <input type="text" name="first_name" id="editFirstName" class="form-control" required>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Last Name <span class="required">*</span></label>
                        <input type="text" name="last_name" id="editLastName" class="form-control" required>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Gender</label>
                        <select name="gender" id="editGender" class="form-control">
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Date of Birth</label>
                        <input type="date" name="date_of_birth" id="editDob" class="form-control">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" id="editPhone" class="form-control">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Official Email <span class="required">*</span></label>
                        <input type="email" name="email" id="editEmail" class="form-control" required>
                    </div>
                    <div class="form-group" style="grid-column: 1 / -1; margin-bottom: 0;">
                        <label class="form-label">Address</label>
                        <input type="text" name="address" id="editAddress" class="form-control">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">City</label>
                        <input type="text" name="city" id="editCity" class="form-control">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">State</label>
                        <input type="text" name="state" id="editState" class="form-control">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Pincode</label>
                        <input type="text" name="zip_code" id="editZipCode" class="form-control" placeholder="Pincode">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Update Photo (Optional)</label>
                        <input type="file" name="avatar" class="form-control" accept="image/png, image/jpeg, image/webp">
                    </div>
                </div>

                <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 20px 0;">

                <!-- Employment Information -->
                <h4 style="font-size: 0.95rem; font-weight: 700; color: var(--primary-600); margin-bottom: 12px; display: flex; align-items: center; gap: 6px;">
                    <i class='bx bx-briefcase'></i> Employment Information
                </h4>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 20px;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Department</label>
                        <select name="department_id" id="editDepartmentId" class="form-control">
                            <option value="">-- Select Department --</option>
                            <?php foreach ($departments as $d): ?>
                            <option value="<?= $d['id'] ?>"><?= sanitize($d['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Designation <span class="required">*</span></label>
                        <input type="text" name="designation" id="editDesignation" class="form-control" required>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Joining Date</label>
                        <input type="date" name="date_of_joining" id="editDoj" class="form-control">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Employment Type</label>
                        <select name="employment_type" id="editEmploymentType" class="form-control">
                            <option value="full-time">Full-Time</option>
                            <option value="part-time">Part-Time</option>
                            <option value="contract">Contract</option>
                            <option value="intern">Intern</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Monthly Gross Salary (₹) <span class="required">*</span></label>
                        <input type="number" step="0.01" name="salary" id="editSalary" class="form-control" required>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Status</label>
                        <select name="status" id="editStatus" class="form-control">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="on-leave">On Leave</option>
                            <option value="terminated">Terminated</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Reset Password (Optional)</label>
                        <input type="password" name="new_password" class="form-control" placeholder="Leave blank to keep current">
                    </div>
                </div>

                <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 20px 0;">

                <!-- Banking & Emergency Details -->
                <h4 style="font-size: 0.95rem; font-weight: 700; color: var(--primary-600); margin-bottom: 12px; display: flex; align-items: center; gap: 6px;">
                    <i class='bx bx-credit-card'></i> Banking & Emergency Contact <span style="font-size: 0.75rem; font-weight: 400; color: var(--text-muted);">(Optional)</span>
                </h4>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Bank Name</label>
                        <input type="text" name="bank_name" id="editBankName" class="form-control">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Account No</label>
                        <input type="text" name="bank_account" id="editBankAccount" class="form-control">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">IFSC Code</label>
                        <input type="text" name="ifsc_code" id="editIfscCode" class="form-control">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">PAN Number</label>
                        <input type="text" name="pan_number" id="editPanNumber" class="form-control">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Emergency Contact Name</label>
                        <input type="text" name="emergency_contact_name" id="editEmergName" class="form-control">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Emergency Contact Phone</label>
                        <input type="text" name="emergency_contact_phone" id="editEmergPhone" class="form-control">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('editEmpModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class='bx bx-save'></i> Save Updates</button>
            </div>
        </form>
    </div>
</div>

<!-- Hidden Delete Form -->
<form id="deleteEmpForm" method="POST" action="<?= APP_URL ?>/admin/employees.php" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="deleteEmpId">
    <input type="hidden" name="user_id" id="deleteEmpUserId">
</form>

<script>
function viewEmployee(emp) {
    const fullName = emp.first_name + ' ' .concat(emp.last_name);
    const body = document.getElementById('viewEmpBody');
    const avatarHtml = emp.avatar 
        ? `<img src="<?= APP_URL ?>/${escapeHtml(emp.avatar)}" style="width: 64px; height: 64px; border-radius: 50%; object-fit: cover; border: 2px solid var(--primary-600);" alt="${escapeHtml(fullName)}">`
        : `<div class="user-avatar-sm" style="width: 64px; height: 64px; font-size: 1.5rem; font-weight: 700;">${emp.first_name[0]}${emp.last_name[0]}</div>`;

    body.innerHTML = `
        <div style="display: flex; gap: 20px; align-items: center; padding-bottom: 20px; border-bottom: 1px solid var(--border-color); margin-bottom: 20px;">
            ${avatarHtml}
            <div>
                <h3 style="margin: 0; font-size: 1.25rem;">${escapeHtml(fullName)}</h3>
                <div style="color: var(--text-secondary); margin-top: 4px;">
                    <span class="badge badge-secondary" style="font-family: monospace;">${escapeHtml(emp.employee_id)}</span>
                    <span style="margin: 0 6px;">•</span>
                    <strong>${escapeHtml(emp.designation || 'Staff')}</strong>
                    <span style="margin: 0 6px;">•</span>
                    ${escapeHtml(emp.department_name || 'No Department')}
                </div>
            </div>
        </div>

        <!-- Quick HR Stats Ribbon -->
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 24px;">
            <div style="background: var(--bg-hover); padding: 12px; border-radius: var(--border-radius); text-align: center;">
                <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Present Days</div>
                <div style="font-size: 1.3rem; font-weight: 800; color: var(--success); margin-top: 2px;">${emp.present_count || 0}</div>
            </div>
            <div style="background: var(--bg-hover); padding: 12px; border-radius: var(--border-radius); text-align: center;">
                <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Approved Leaves</div>
                <div style="font-size: 1.3rem; font-weight: 800; color: var(--warning); margin-top: 2px;">${emp.leave_days || 0} d</div>
            </div>
            <div style="background: var(--bg-hover); padding: 12px; border-radius: var(--border-radius); text-align: center;">
                <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Monthly Salary</div>
                <div style="font-size: 1.3rem; font-weight: 800; color: var(--primary-600); margin-top: 2px;">₹${parseFloat(emp.salary).toLocaleString('en-IN', {minimumFractionDigits: 0})}</div>
            </div>
        </div>

        <!-- Details Grid -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px;">
            <div>
                <label style="color: var(--text-muted); font-size: 0.8rem; font-weight: 600; text-transform: uppercase;">Official Email</label>
                <div style="font-weight: 500;">${escapeHtml(emp.email || '-')}</div>
            </div>
            <div>
                <label style="color: var(--text-muted); font-size: 0.8rem; font-weight: 600; text-transform: uppercase;">Phone Number</label>
                <div style="font-weight: 500;">${escapeHtml(emp.phone || '-')}</div>
            </div>
            <div>
                <label style="color: var(--text-muted); font-size: 0.8rem; font-weight: 600; text-transform: uppercase;">Employment Type</label>
                <div style="text-transform: capitalize; font-weight: 500;">${escapeHtml(emp.employment_type || '-')}</div>
            </div>
            <div>
                <label style="color: var(--text-muted); font-size: 0.8rem; font-weight: 600; text-transform: uppercase;">Date of Joining</label>
                <div style="font-weight: 500;">${escapeHtml(emp.date_of_joining || '-')}</div>
            </div>
            <div>
                <label style="color: var(--text-muted); font-size: 0.8rem; font-weight: 600; text-transform: uppercase;">Date of Birth</label>
                <div style="font-weight: 500;">${escapeHtml(emp.date_of_birth || '-')}</div>
            </div>
            <div>
                <label style="color: var(--text-muted); font-size: 0.8rem; font-weight: 600; text-transform: uppercase;">Status</label>
                <div><span class="badge ${emp.status === 'active' ? 'badge-success' : (emp.status === 'on-leave' ? 'badge-secondary' : 'badge-danger')}">${escapeHtml(emp.status)}</span></div>
            </div>
            <div>
                <label style="color: var(--text-muted); font-size: 0.8rem; font-weight: 600; text-transform: uppercase;">Bank & Account</label>
                <div style="font-weight: 500;">${escapeHtml(emp.bank_name || '-')} ${emp.bank_account ? '(' + escapeHtml(emp.bank_account) + ')' : ''}</div>
            </div>
            <div>
                <label style="color: var(--text-muted); font-size: 0.8rem; font-weight: 600; text-transform: uppercase;">PAN / IFSC</label>
                <div style="font-weight: 500;">PAN: ${escapeHtml(emp.pan_number || '-')} | IFSC: ${escapeHtml(emp.ifsc_code || '-')}</div>
            </div>
            <div>
                <label style="color: var(--text-muted); font-size: 0.8rem; font-weight: 600; text-transform: uppercase;">Emergency Contact</label>
                <div style="font-weight: 500;">${escapeHtml(emp.emergency_contact_name || '-')} (${escapeHtml(emp.emergency_contact_phone || '-')})</div>
            </div>
            <div>
                <label style="color: var(--text-muted); font-size: 0.8rem; font-weight: 600; text-transform: uppercase;">Residential Address</label>
                <div style="font-weight: 500;">${escapeHtml(emp.address || '')}${emp.city ? ', ' + escapeHtml(emp.city) : ''}${emp.state ? ', ' + escapeHtml(emp.state) : ''}${emp.zip_code ? ' - ' + escapeHtml(emp.zip_code) : ''}</div>
            </div>
        </div>
    `;
    openModal('viewEmpModal');
}

function editEmployee(emp) {
    document.getElementById('editId').value = emp.id;
    document.getElementById('editUserId').value = emp.user_id;
    const badge = document.getElementById('editEmployeeIdBadge');
    if (badge) badge.innerText = emp.employee_id || '';
    document.getElementById('editFirstName').value = emp.first_name || '';
    document.getElementById('editLastName').value = emp.last_name || '';
    document.getElementById('editEmail').value = emp.email || '';
    document.getElementById('editPhone').value = emp.phone || '';
    document.getElementById('editDob').value = emp.date_of_birth || '';
    document.getElementById('editGender').value = emp.gender || 'male';
    document.getElementById('editDepartmentId').value = emp.department_id || '';
    document.getElementById('editDesignation').value = emp.designation || '';
    const dojElem = document.getElementById('editDoj');
    if (dojElem) dojElem.value = emp.date_of_joining || '';
    document.getElementById('editEmploymentType').value = emp.employment_type || 'full-time';
    document.getElementById('editSalary').value = emp.salary || 0;
    document.getElementById('editStatus').value = emp.status || 'active';
    document.getElementById('editAddress').value = emp.address || '';
    document.getElementById('editCity').value = emp.city || '';
    document.getElementById('editState').value = emp.state || '';
    document.getElementById('editZipCode').value = emp.zip_code || '';
    document.getElementById('editBankName').value = emp.bank_name || '';
    document.getElementById('editBankAccount').value = emp.bank_account || '';
    document.getElementById('editIfscCode').value = emp.ifsc_code || '';
    document.getElementById('editPanNumber').value = emp.pan_number || '';
    document.getElementById('editEmergName').value = emp.emergency_contact_name || '';
    document.getElementById('editEmergPhone').value = emp.emergency_contact_phone || '';
    openModal('editEmpModal');
}

function confirmDeleteEmployee(id, userId, name) {
    if (confirm('Are you sure you want to delete employee "' + name + '"?\n\nThis will permanently delete the employee record and safely clean up all connected attendance, leave, and payroll records.')) {
        const form = document.getElementById('deleteEmpForm');
        document.getElementById('deleteEmpId').value = id;
        document.getElementById('deleteEmpUserId').value = userId;
        form.submit();
    }
}
window.confirmDelete = confirmDeleteEmployee;
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
