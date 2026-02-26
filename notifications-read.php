<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

requireLogin();

header('Content-Type: application/json; charset=UTF-8');

if (!isPostRequest()) {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Método não permitido.',
    ]);
    exit;
}

$submittedToken = $_POST['_csrf'] ?? '';
$sessionToken = $_SESSION['_csrf_token'] ?? '';

if (
    !is_string($submittedToken) ||
    !is_string($sessionToken) ||
    $sessionToken === '' ||
    !hash_equals($sessionToken, $submittedToken)
) {
    http_response_code(419);
    echo json_encode([
        'success' => false,
        'message' => 'Token CSRF inválido.',
    ]);
    exit;
}

$userId = currentUserId();
if ($userId === null) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Usuário não autenticado.',
    ]);
    exit;
}

$pdo = getPDO();
$stmt = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = :user_id AND is_read = 0');
$stmt->execute(['user_id' => $userId]);

echo json_encode(['success' => true]);
