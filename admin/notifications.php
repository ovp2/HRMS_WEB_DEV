<?php
/**
 * HRMS - Admin Notifications & Broadcast System
 */

require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

define('PAGE_TITLE', 'Notifications & Broadcasts');

$pdo = getDBConnection();
$csrf_token = generateCSRFToken();
$userId = getCurrentUserId();

// Handle Actions (Broadcast Announcement, Mark Read, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $token = $_POST['csrf_token'] ?? '';

    if (!validateCSRFToken($token)) {
        redirectWith(APP_URL . '/admin/notifications.php', 'Invalid security token.', 'danger');
    }

    if ($action === 'broadcast') {
        $target = $_POST['target'] ?? 'all';
        $target_dept = (int)($_POST['department_id'] ?? 0);
        $target_emp = (int)($_POST['employee_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $message = trim($_POST['message'] ?? '');
        $type = in_array($_POST['type'] ?? '', ['info', 'success', 'warning', 'danger']) ? $_POST['type'] : 'info';
        $link = trim($_POST['link'] ?? '');

        if (empty($title) || empty($message)) {
            redirectWith(APP_URL . '/admin/notifications.php', 'Title and message cannot be empty.', 'danger');
        }

        // Determine recipient user IDs
        $recipientUserIds = [];
        if ($target === 'all') {
            $stmt = $pdo->query("SELECT user_id FROM employees WHERE status = 'active'");
            $recipientUserIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } elseif ($target === 'department' && $target_dept > 0) {
            $stmt = $pdo->prepare("SELECT user_id FROM employees WHERE department_id = ? AND status = 'active'");
            $stmt->execute([$target_dept]);
            $recipientUserIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } elseif ($target === 'employee' && $target_emp > 0) {
            $stmt = $pdo->prepare("SELECT user_id FROM employees WHERE id = ?");
            $stmt->execute([$target_emp]);
            $recipientUserIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }

        if (empty($recipientUserIds)) {
            redirectWith(APP_URL . '/admin/notifications.php', 'No active recipients found for selected criteria.', 'warning');
        }

        $insertStmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, link, is_read) VALUES (?, ?, ?, ?, ?, 0)");
        foreach ($recipientUserIds as $recipId) {
            $insertStmt->execute([$recipId, $title, $message, $type, $link ?: null]);
        }

        redirectWith(APP_URL . '/admin/notifications.php', "Broadcast dispatched to " . count($recipientUserIds) . " user(s).", 'success');
    }

    if ($action === 'mark_read') {
        $notifId = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        $stmt->execute([$notifId, $userId]);
        redirectWith(APP_URL . '/admin/notifications.php', 'Notification marked as read.', 'info');
    }

    if ($action === 'mark_all_read') {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
        $stmt->execute([$userId]);
        redirectWith(APP_URL . '/admin/notifications.php', 'All notifications marked as read.', 'success');
    }

    if ($action === 'delete') {
        $notifId = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
        $stmt->execute([$notifId, $userId]);
        redirectWith(APP_URL . '/admin/notifications.php', 'Notification removed.', 'info');
    }
}

// Fetch Admin's own notifications
$myNotifs = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
$myNotifs->execute([$userId]);
$notifications = $myNotifs->fetchAll();

$departments = $pdo->query("SELECT id, name FROM departments WHERE status = 'active' ORDER BY name ASC")->fetchAll();
$employees = $pdo->query("SELECT id, employee_id, first_name, last_name FROM employees WHERE status = 'active' ORDER BY first_name ASC")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
    <!-- Send Broadcast Announcement -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">Dispatch Broadcast / Announcement</div>
        </div>
        <form method="POST" action="">
            <div class="card-body">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <input type="hidden" name="action" value="broadcast">

                <div class="form-group">
                    <label class="form-label">Audience Target <span class="required">*</span></label>
                    <select name="target" id="targetSelect" class="form-control" onchange="handleTargetChange(this.value)">
                        <option value="all">Broadcast to All Active Employees</option>
                        <option value="department">Specific Department</option>
                        <option value="employee">Individual Employee</option>
                    </select>
                </div>

                <div class="form-group" id="deptTargetGroup" style="display: none;">
                    <label class="form-label">Select Department</label>
                    <select name="department_id" class="form-control">
                        <?php foreach ($departments as $d): ?>
                        <option value="<?= $d['id'] ?>"><?= sanitize($d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" id="empTargetGroup" style="display: none;">
                    <label class="form-label">Select Employee</label>
                    <select name="employee_id" class="form-control">
                        <?php foreach ($employees as $e): ?>
                        <option value="<?= $e['id'] ?>"><?= sanitize($e['first_name'] . ' ' . $e['last_name']) ?> (<?= $e['employee_id'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Announcement Title <span class="required">*</span></label>
                    <input type="text" name="title" class="form-control" required placeholder="e.g. Office Holiday Notice / All-Hands Meeting">
                </div>

                <div class="form-group">
                    <label class="form-label">Alert Severity / Type</label>
                    <select name="type" class="form-control">
                        <option value="info">General Information (Blue)</option>
                        <option value="success">Success / Congratulations (Green)</option>
                        <option value="warning">Important Warning / Due Date (Orange)</option>
                        <option value="danger">High Priority / Urgent (Red)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Message Content <span class="required">*</span></label>
                    <textarea name="message" class="form-control" rows="4" required placeholder="Write your announcement details here..."></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">Target Page Link (Optional)</label>
                    <input type="text" name="link" class="form-control" placeholder="e.g. /employee/leaves.php">
                </div>
            </div>
            <div class="card-footer" style="display: flex; justify-content: flex-end;">
                <button type="submit" class="btn btn-primary">
                    <i class='bx bx-send'></i> Send Broadcast
                </button>
            </div>
        </form>
    </div>

    <!-- Personal Admin Notifications -->
    <div class="card">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <div class="card-title">My Notifications (<?= count($notifications) ?>)</div>
            <?php if (!empty($notifications)): ?>
            <form method="POST" action="" style="display: inline;">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <input type="hidden" name="action" value="mark_all_read">
                <button type="submit" class="btn btn-outline btn-sm">Mark All Read</button>
            </form>
            <?php endif; ?>
        </div>
        <div class="card-body" style="padding: 0; max-height: 520px; overflow-y: auto;">
            <?php if (empty($notifications)): ?>
            <div style="padding: 40px; text-align: center; color: var(--text-muted);">
                <i class='bx bx-bell-off' style="font-size: 3rem; margin-bottom: 8px; display: block;"></i>
                No notifications right now.
            </div>
            <?php else: ?>
                <div style="display: flex; flex-direction: column;">
                    <?php foreach ($notifications as $n): ?>
                    <div style="display: flex; align-items: flex-start; gap: 14px; padding: 16px 20px; border-bottom: 1px solid var(--border-color); background: <?= $n['is_read'] ? 'transparent' : 'rgba(59,130,246,0.04)' ?>;">
                        <div class="notif-icon notif-<?= $n['type'] ?>" style="width: 36px; height: 36px; border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; background: var(--primary-50); color: var(--primary-600);">
                            <i class='bx bx-bell' style="font-size: 1.2rem;"></i>
                        </div>
                        <div style="flex: 1;">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <strong style="font-size: 0.95rem; color: var(--text-primary);"><?= sanitize($n['title']) ?></strong>
                                <small style="color: var(--text-muted); font-size: 0.75rem;"><?= date('M j, h:i A', strtotime($n['created_at'])) ?></small>
                            </div>
                            <p style="margin: 4px 0 8px 0; font-size: 0.85rem; color: var(--text-secondary); line-height: 1.4;">
                                <?= nl2br(sanitize($n['message'])) ?>
                            </p>
                            <div style="display: flex; gap: 12px; align-items: center;">
                                <?php if (!empty($n['link'])): ?>
                                <a href="<?= sanitize($n['link']) ?>" class="btn btn-outline btn-sm" style="padding: 2px 8px; font-size: 0.75rem;">
                                    View <i class='bx bx-right-arrow-alt'></i>
                                </a>
                                <?php endif; ?>

                                <?php if (!$n['is_read']): ?>
                                <form method="POST" action="" style="display: inline;">
                                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                    <input type="hidden" name="action" value="mark_read">
                                    <input type="hidden" name="id" value="<?= $n['id'] ?>">
                                    <button type="submit" class="btn btn-outline btn-sm" style="padding: 2px 8px; font-size: 0.75rem;">Mark Read</button>
                                </form>
                                <?php endif; ?>

                                <form method="POST" action="" style="display: inline;">
                                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $n['id'] ?>">
                                    <button type="submit" class="btn btn-outline btn-sm" style="padding: 2px 8px; font-size: 0.75rem; color: var(--danger); border-color: var(--danger);">
                                        <i class='bx bx-trash'></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function handleTargetChange(val) {
    document.getElementById('deptTargetGroup').style.display = (val === 'department') ? 'block' : 'none';
    document.getElementById('empTargetGroup').style.display = (val === 'employee') ? 'block' : 'none';
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
