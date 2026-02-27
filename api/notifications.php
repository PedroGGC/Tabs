<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLogged()) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$pdo = getPDO();
$userId = currentUserId();
$action = $_GET['action'] ?? 'fetch';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'read_all') {
    // Basic CSRF verify - Since it's a simple read all endpoint we just ensure it's a POST
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = :user_id");
    $stmt->execute(['user_id' => $userId]);
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'fetch') {
    $stmt = $pdo->prepare("
        SELECT 
            n.id, 
            n.type, 
            n.is_read, 
            n.created_at, 
            n.post_id, 
            u.username as from_user
        FROM notifications n
        INNER JOIN users u ON u.id = n.from_user_id
        WHERE n.user_id = :user_id
        ORDER BY n.created_at DESC
        LIMIT 20
    ");
    $stmt->execute(['user_id' => $userId]);
    $notifications = $stmt->fetchAll();

    $unreadCountStmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND is_read = 0");
    $unreadCountStmt->execute(['user_id' => $userId]);
    $unreadCount = (int) $unreadCountStmt->fetchColumn();

    $formatted = [];
    foreach ($notifications as $n) {
        $message = '';
        if ($n['type'] === 'comment' || $n['type'] === 'reply') {
            $message = htmlspecialchars($n['from_user']) . ' comentou no seu post.';
            if ($n['type'] === 'reply')
                $message = htmlspecialchars($n['from_user']) . ' respondeu ao seu comentário.';
        } elseif ($n['type'] === 'upvote') {
            $message = htmlspecialchars($n['from_user']) . ' curtiu seu post!';
        } elseif ($n['type'] === 'mention') {
            $message = htmlspecialchars($n['from_user']) . ' mencionou você.';
        } else {
            $message = 'Nova interação de ' . htmlspecialchars($n['from_user']);
        }

        $formatted[] = [
            'id' => $n['id'],
            'message' => $message,
            'is_read' => (bool) $n['is_read'],
            'post_id' => $n['post_id'],
            'created_at' => $n['created_at']
        ];
    }

    echo json_encode([
        'unread_count' => $unreadCount,
        'notifications' => $formatted
    ]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Invalid action']);
exit;
