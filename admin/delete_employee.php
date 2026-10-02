<?php
/**
 * HRMS - Dedicated Employee Delete Handler
 * Deletes employee record, user account, and safely removes/cascades all related records.
 */

require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$pdo = getDBConnection();

// Allow POST (preferred) or GET with validation
$id = 0;
$token = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $token = $_POST['csrf_token'] ?? '';
    if (!empty($token) && !validateCSRFToken($token)) {
        redirectWith(APP_URL . '/admin/employees.php', 'Invalid security token.', 'danger');
    }
} else {
    $id = (int)($_GET['id'] ?? 0);
    $token = $_GET['csrf_token'] ?? '';
    if (!empty($token) && !validateCSRFToken($token)) {
        redirectWith(APP_URL . '/admin/employees.php', 'Invalid security token.', 'danger');
    }
}

if ($id <= 0) {
    redirectWith(APP_URL . '/admin/employees.php', 'Invalid employee ID specified.', 'danger');
}

// Check employee exists
$empStmt = $pdo->prepare("SELECT id, user_id, first_name, last_name, avatar FROM employees WHERE id = ?");
$empStmt->execute([$id]);
$emp = $empStmt->fetch();

if (!$emp) {
    redirectWith(APP_URL . '/admin/employees.php', 'Employee record not found or already deleted.', 'warning');
}

$userId = !empty($emp['user_id']) ? (int)$emp['user_id'] : 0;

try {
    $pdo->beginTransaction();

    // 1. Unlink department head reference if this employee was head of any department
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

    // 8. Clean up uploaded profile photo file if exists
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
