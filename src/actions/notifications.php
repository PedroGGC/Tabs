<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../auth/auth.php';

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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'clear_read') {
    $stmt = $pdo->prepare("DELETE FROM notifications WHERE user_id = :user_id AND is_read = 1");
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
            n.comment_id,
            n.from_user_id,
            u.username as from_user
        FROM notifications n
        INNER JOIN users u ON u.id = n.from_user_id
        WHERE n.user_id = :user_id
        ORDER BY n.created_at DESC
        LIMIT 40
    ");
    $stmt->execute(['user_id' => $userId]);
    $notifications = $stmt->fetchAll();

    $unreadCountStmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND is_read = 0");
    $unreadCountStmt->execute(['user_id' => $userId]);
    $unreadCount = (int) $unreadCountStmt->fetchColumn();

    $groups = [];
    foreach ($notifications as $n) {
        $type = (string) $n['type'];
        $postId = (int) $n['post_id'];
        $commentId = isset($n['comment_id']) ? (int) $n['comment_id'] : null;
        $fromUserId = (int) $n['from_user_id'];
        $fromUser = (string) $n['from_user'];
        $createdAt = (string) $n['created_at'];
        $isUnread = !(bool) $n['is_read'];

        if ($type === 'comment') {
            $key = 'comment:' . $postId;
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'type' => 'comment',
                    'post_id' => $postId,
                    'created_at' => $createdAt,
                    'has_unread' => false,
                    'names' => [],
                    'order' => [],
                ];
            }
            if (!isset($groups[$key]['names'][$fromUserId])) {
                $groups[$key]['names'][$fromUserId] = $fromUser;
                $groups[$key]['order'][] = $fromUserId;
            }
            if ($isUnread) {
                $groups[$key]['has_unread'] = true;
            }
            if ($createdAt > $groups[$key]['created_at']) {
                $groups[$key]['created_at'] = $createdAt;
            }
            continue;
        }

        if ($type === 'upvote') {
            $target = $commentId ? 'comment' : 'post';
            $key = 'upvote:' . $target . ':' . ($commentId ?: $postId);
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'type' => 'upvote',
                    'post_id' => $postId,
                    'comment_id' => $commentId,
                    'target' => $target,
                    'created_at' => $createdAt,
                    'has_unread' => false,
                    'voters' => [],
                ];
            }
            $groups[$key]['voters'][$fromUserId] = true;
            if ($isUnread) {
                $groups[$key]['has_unread'] = true;
            }
            if ($createdAt > $groups[$key]['created_at']) {
                $groups[$key]['created_at'] = $createdAt;
            }
            continue;
        }

        $groups['single:' . $n['id']] = [
            'type' => $type,
            'post_id' => $postId,
            'created_at' => $createdAt,
            'has_unread' => $isUnread,
            'from_user' => $fromUser,
        ];
    }

    $formatted = [];
    foreach ($groups as $key => $group) {
        $message = '';

        if ($group['type'] === 'comment') {
            $names = [];
            foreach ($group['order'] as $userId) {
                $names[] = $group['names'][$userId];
            }
            $total = count($names);
            $display = array_slice($names, 0, 3);

            if ($total <= 0) {
                $message = 'Novo comentário no seu post.';
            } elseif ($total === 1) {
                $message = $display[0] . ' comentou no seu post.';
            } elseif ($total === 2) {
                $message = $display[0] . ' e ' . $display[1] . ' comentaram no seu post.';
            } elseif ($total === 3) {
                $message = $display[0] . ', ' . $display[1] . ' e ' . $display[2] . ' comentaram no seu post.';
            } else {
                $message = $display[0] . ', ' . $display[1] . ', ' . $display[2] . ' e mais ' . ($total - 3) . ' pessoas comentaram no seu post.';
            }
        } elseif ($group['type'] === 'upvote') {
            $count = count($group['voters']);
            $suffix = ($group['target'] ?? 'post') === 'comment' ? 'comentário' : 'post';
            if ($count === 1) {
                $message = '1 pessoa gostou do seu ' . $suffix . '.';
            } else {
                $message = $count . ' pessoas gostaram do seu ' . $suffix . '.';
            }
        } elseif ($group['type'] === 'reply') {
            $message = $group['from_user'] . ' respondeu ao seu comentário.';
        } elseif ($group['type'] === 'mention') {
            $message = $group['from_user'] . ' mencionou você.';
        } else {
            $message = 'Nova interação de ' . $group['from_user'];
        }

        $formatted[] = [
            'id' => $key,
            'message' => $message,
            'is_read' => !$group['has_unread'],
            'post_id' => $group['post_id'],
            'created_at' => $group['created_at'],
        ];
    }

    usort($formatted, static function (array $a, array $b): int {
        return strcmp($b['created_at'], $a['created_at']);
    });

    $unread = [];
    $read = [];
    foreach ($formatted as $item) {
        if (!empty($item['is_read'])) {
            $read[] = $item;
        } else {
            $unread[] = $item;
        }
    }

    echo json_encode([
        'unread_count' => $unreadCount,
        'notifications' => $formatted,
        'unread' => $unread,
        'read' => $read,
    ]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Invalid action']);
exit;
