<?php
/**
 * HRMS - Employee Notifications Center
 */

require_once __DIR__ . '/../includes/auth.php';
requireEmployee();

define('PAGE_TITLE', 'Notifications');

$pdo = getDBConnection();
$csrf_token = generateCSRFToken();
$userId = getCurrentUserId();
$filter = trim($_GET['filter'] ?? 'all');

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $token = $_POST['csrf_token'] ?? '';

    if (!validateCSRFToken($token)) {
        redirectWith(APP_URL . '/employee/notifications.php', 'Invalid security token.', 'danger');
    }

    if ($action === 'mark_read') {
        $notifId = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        $stmt->execute([$notifId, $userId]);
        redirectWith(APP_URL . "/employee/notifications.php?filter=$filter", 'Notification marked as read.', 'info');
    }

    if ($action === 'mark_all_read') {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
        $stmt->execute([$userId]);
        redirectWith(APP_URL . "/employee/notifications.php?filter=$filter", 'All notifications marked as read.', 'success');
    }

    if ($action === 'delete') {
        $notifId = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
        $stmt->execute([$notifId, $userId]);
        redirectWith(APP_URL . "/employee/notifications.php?filter=$filter", 'Notification removed.', 'info');
    }

    if ($action === 'clear_read') {
        $stmt = $pdo->prepare("DELETE FROM notifications WHERE user_id = ? AND is_read = 1");
        $stmt->execute([$userId]);
        redirectWith(APP_URL . "/employee/notifications.php?filter=$filter", 'Cleared all read notifications.', 'success');
    }
}

// Fetch notification stats
$unreadCountStmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
$unreadCountStmt->execute([$userId]);
$unreadCount = (int)$unreadCountStmt->fetchColumn();

// Query notifications based on filter
$query = "SELECT * FROM notifications WHERE user_id = ?";
$params = [$userId];

if ($filter === 'unread') {
    $query .= " AND is_read = 0";
}

$query .= " ORDER BY created_at DESC";
$notifStmt = $pdo->prepare($query);
$notifStmt->execute($params);
$notifications = $notifStmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="card">
    <div class="card-header" style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 12px;">
        <div style="display: flex; align-items: center; gap: 12px;">
            <div class="card-title" style="margin: 0;">My Notifications Feed</div>
            <div style="display: flex; gap: 6px;">
                <a href="?filter=all" class="btn btn-sm <?= $filter === 'all' ? 'btn-primary' : 'btn-outline' ?>">
                    All
                </a>
                <a href="?filter=unread" class="btn btn-sm <?= $filter === 'unread' ? 'btn-primary' : 'btn-outline' ?>">
                    Unread <?= $unreadCount > 0 ? "($unreadCount)" : '' ?>
                </a>
            </div>
        </div>

        <div style="display: flex; gap: 8px; align-items: center;">
            <?php if ($unreadCount > 0): ?>
            <form method="POST" action="" style="display: inline;">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <input type="hidden" name="action" value="mark_all_read">
                <button type="submit" class="btn btn-outline btn-sm">
                    <i class='bx bx-check-double'></i> Mark All Read
                </button>
            </form>
            <?php endif; ?>

            <?php if (!empty($notifications)): ?>
            <form method="POST" action="" style="display: inline;" onsubmit="return confirm('Clear all read notifications?')">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <input type="hidden" name="action" value="clear_read">
                <button type="submit" class="btn btn-outline btn-sm" title="Delete all read notifications">
                    <i class='bx bx-trash'></i> Clear Read
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="card-body" style="padding: 0;">
        <?php if (empty($notifications)): ?>
        <div style="padding: 48px; text-align: center; color: var(--text-muted);">
            <i class='bx bx-bell-off' style="font-size: 3.5rem; margin-bottom: 12px; display: block; color: var(--text-muted);"></i>
            <h3>No <?= $filter === 'unread' ? 'Unread ' : '' ?>Notifications</h3>
            <p style="font-size: 0.9rem;">You're completely up to date.</p>
        </div>
        <?php else: ?>
            <div style="display: flex; flex-direction: column;">
                <?php foreach ($notifications as $n): ?>
                <div style="display: flex; align-items: flex-start; gap: 16px; padding: 18px 24px; border-bottom: 1px solid var(--border-color); background: <?= $n['is_read'] ? 'transparent' : 'rgba(59,130,246,0.04)' ?>; transition: background 0.15s ease;">
                    <div style="width: 42px; height: 42px; border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; background: <?= $n['type'] === 'success' ? 'var(--success-light)' : ($n['type'] === 'warning' ? 'var(--warning-light)' : 'var(--primary-50)') ?>; color: <?= $n['type'] === 'success' ? 'var(--success)' : ($n['type'] === 'warning' ? 'var(--warning)' : 'var(--primary)') ?>;">
                        <i class='bx <?php
                            echo match($n['type']) {
                                'success' => 'bx-check-circle',
                                'warning' => 'bx-error-circle',
                                'danger' => 'bx-x-circle',
                                default => 'bx-bell'
                            };
                        ?>' style="font-size: 1.4rem;"></i>
                    </div>

                    <div style="flex: 1;">
                        <div style="display: flex; justify-content: space-between; align-items: center; gap: 8px;">
                            <strong style="font-size: 0.98rem; color: var(--text-primary); display: flex; align-items: center; gap: 8px;">
                                <?= sanitize($n['title']) ?>
                                <?php if (!$n['is_read']): ?>
                                <span class="badge badge-primary" style="font-size: 0.7rem; padding: 2px 6px;">New</span>
                                <?php endif; ?>
                            </strong>
                            <small style="color: var(--text-muted); font-size: 0.8rem;"><?= date('M j, Y, h:i A', strtotime($n['created_at'])) ?></small>
                        </div>
                        <p style="margin: 6px 0 10px 0; font-size: 0.9rem; color: var(--text-secondary); line-height: 1.5;">
                            <?= nl2br(sanitize($n['message'])) ?>
                        </p>
                        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                            <?php if (!empty($n['link'])): ?>
                            <a href="<?= sanitize($n['link']) ?>" class="btn btn-outline btn-sm" style="padding: 4px 12px; font-size: 0.8rem;">
                                View Details <i class='bx bx-right-arrow-alt'></i>
                            </a>
                            <?php endif; ?>

                            <?php if (!$n['is_read']): ?>
                            <form method="POST" action="" style="display: inline;">
                                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                <input type="hidden" name="action" value="mark_read">
                                <input type="hidden" name="id" value="<?= $n['id'] ?>">
                                <button type="submit" class="btn btn-outline btn-sm" style="padding: 4px 12px; font-size: 0.8rem;">
                                    <i class='bx bx-check'></i> Mark Read
                                </button>
                            </form>
                            <?php endif; ?>

                            <form method="POST" action="" style="display: inline;">
                                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $n['id'] ?>">
                                <button type="submit" class="btn btn-outline btn-sm" style="padding: 4px 10px; font-size: 0.8rem; color: var(--danger); border-color: var(--border-color);" title="Delete Notification">
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

<?php include __DIR__ . '/../includes/footer.php'; ?>
