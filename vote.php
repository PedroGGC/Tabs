<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

header('Content-Type: application/json');

// --- Auth guard (HTTP 401 if not logged in) ---
if (!isLogged()) {
    http_response_code(401);
    echo json_encode(['error' => 'Você precisa estar logado para votar.']);
    exit;
}

// --- Only POST accepted ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido.']);
    exit;
}

// --- CSRF ---
$submittedToken = $_POST['_csrf'] ?? '';
$sessionToken = $_SESSION['_csrf_token'] ?? '';
if (
    !is_string($submittedToken) ||
    !is_string($sessionToken) ||
    $sessionToken === '' ||
    !hash_equals($sessionToken, $submittedToken)
) {
    http_response_code(403);
    echo json_encode(['error' => 'Token CSRF inválido.']);
    exit;
}

// --- Input validation ---
// --- Input validation ---
$itemType = filter_input(INPUT_POST, 'item_type', FILTER_SANITIZE_SPECIAL_CHARS);
$itemId = filter_input(INPUT_POST, 'item_id', FILTER_VALIDATE_INT);
$voteRaw = filter_input(INPUT_POST, 'vote', FILTER_VALIDATE_INT);

if (!in_array($itemType, ['post', 'comment'], true) || !$itemId || $itemId <= 0 || !in_array($voteRaw, [1, -1], true)) {
    http_response_code(422);
    echo json_encode(['error' => 'Dados inválidos.']);
    exit;
}

$vote = (int) $voteRaw;
$userId = currentUserId();
$pdo = getPDO();

// Helper to notify owner
function notifyOwner($pdo, $itemType, $itemId, $userId)
{
    if ($itemType === 'post') {
        $stmt = $pdo->prepare('SELECT user_id FROM posts WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $itemId]);
        $owner = $stmt->fetchColumn();

        if ($owner && (int) $owner !== $userId) {
            $pdo->prepare('INSERT INTO notifications (user_id, from_user_id, type, post_id, is_read, created_at) VALUES (:u, :from, "upvote", :pid, 0, NOW())')
                ->execute(['u' => $owner, 'from' => $userId, 'pid' => $itemId]);
        }
    } else {
        $stmt = $pdo->prepare('SELECT user_id, post_id FROM comments WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $itemId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row && (int) $row['user_id'] !== $userId) {
            $pdo->prepare('INSERT INTO notifications (user_id, from_user_id, type, post_id, comment_id, is_read, created_at) VALUES (:u, :from, "upvote", :pid, :cid, 0, NOW())')
                ->execute(['u' => $row['user_id'], 'from' => $userId, 'pid' => $row['post_id'], 'cid' => $itemId]);
        }
    }
}

// --- Load current vote for this user on this item ---
$currentStmt = $pdo->prepare(
    'SELECT vote FROM votes WHERE item_type = :type AND item_id = :iid AND user_id = :uid LIMIT 1'
);
$currentStmt->execute(['type' => $itemType, 'iid' => $itemId, 'uid' => $userId]);
$existing = $currentStmt->fetchColumn();

if ($existing === false) {
    // No vote yet → insert
    $pdo->prepare('INSERT INTO votes (item_type, item_id, user_id, vote) VALUES (:type, :iid, :uid, :vote)')
        ->execute(['type' => $itemType, 'iid' => $itemId, 'uid' => $userId, 'vote' => $vote]);
    $userVote = $vote;

    if ($vote === 1) {
        notifyOwner($pdo, $itemType, $itemId, $userId);
    }
} elseif ((int) $existing === $vote) {
    // Same vote → toggle off
    $pdo->prepare('DELETE FROM votes WHERE item_type = :type AND item_id = :iid AND user_id = :uid')
        ->execute(['type' => $itemType, 'iid' => $itemId, 'uid' => $userId]);
    $userVote = 0;
} else {
    // Different vote → update
    $pdo->prepare('UPDATE votes SET vote = :vote WHERE item_type = :type AND item_id = :iid AND user_id = :uid')
        ->execute(['vote' => $vote, 'type' => $itemType, 'iid' => $itemId, 'uid' => $userId]);
    $userVote = $vote;

    if ($vote === 1) {
        notifyOwner($pdo, $itemType, $itemId, $userId);
    }
}

// --- Return updated score ---
$scoreStmt = $pdo->prepare(
    'SELECT COALESCE(SUM(vote), 0) AS score FROM votes WHERE item_type = :type AND item_id = :iid'
);
$scoreStmt->execute(['type' => $itemType, 'iid' => $itemId]);
$score = (int) $scoreStmt->fetchColumn();

echo json_encode(['score' => $score, 'userVote' => $userVote]);
