<?php
/**
 * HRMS - Notifications AJAX Handler
 * Returns notifications as JSON for the navbar dropdown
 */

require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? '';
$userId = getCurrentUserId();
$pdo = getDBConnection();

switch ($action) {
    case 'recent':
        $stmt = $pdo->prepare("SELECT id, title, message, type, is_read, link, created_at,
                               CASE 
                                   WHEN TIMESTAMPDIFF(MINUTE, created_at, NOW()) < 1 THEN 'Just now'
                                   WHEN TIMESTAMPDIFF(MINUTE, created_at, NOW()) < 60 THEN CONCAT(TIMESTAMPDIFF(MINUTE, created_at, NOW()), 'm ago')
                                   WHEN TIMESTAMPDIFF(HOUR, created_at, NOW()) < 24 THEN CONCAT(TIMESTAMPDIFF(HOUR, created_at, NOW()), 'h ago')
                                   WHEN TIMESTAMPDIFF(DAY, created_at, NOW()) < 7 THEN CONCAT(TIMESTAMPDIFF(DAY, created_at, NOW()), 'd ago')
                                   ELSE DATE_FORMAT(created_at, '%d %b %Y')
                               END AS time_ago
                               FROM notifications 
                               WHERE user_id = ? 
                               ORDER BY created_at DESC 
                               LIMIT 5");
        $stmt->execute([$userId]);
        $notifications = $stmt->fetchAll();
        
        echo json_encode(['notifications' => $notifications]);
        break;
        
    case 'mark_read':
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $userId]);
        }
        echo json_encode(['success' => true]);
        break;
        
    case 'mark_all_read':
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
        $stmt->execute([$userId]);
        echo json_encode(['success' => true]);
        break;
        
    default:
        echo json_encode(['error' => 'Invalid action']);
}
