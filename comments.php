<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/comment-functions.php';

requireLogin();

$actionParam = (string) ($_GET['action'] ?? '');
$action = match ($actionParam) {
    'store' => 'store',
    'update' => 'update',
    'delete' => 'delete',
    default => null,
};

if ($action === null || !isPostRequest()) {
    redirect('index.php');
}

verifyCsrfOrFail();

$pdo = getPDO();
$currentUserId = currentUserId();

if ($currentUserId === null) {
    setFlash('error', 'Usuário inválido.');
    redirect('login.php');
}

$payload = $_POST;
$commentIdFromQuery = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (
    ($action === 'update' || $action === 'delete') &&
    $commentIdFromQuery !== false &&
    $commentIdFromQuery !== null
) {
    $payload['comment_id'] = $commentIdFromQuery;
}

$result = match ($action) {
    'store' => commentCreate($pdo, $currentUserId, $payload),
    'update' => commentUpdate($pdo, $currentUserId, $payload),
    'delete' => commentDelete($pdo, $currentUserId, $payload),
};

if ($result['success']) {
    $successMessage = match ($action) {
        'store' => 'Comentário publicado com sucesso.',
        'update' => 'Comentário atualizado com sucesso.',
        'delete' => 'Comentário excluído com sucesso.',
    };
    setFlash('success', $successMessage);
} else {
    setFlash('error', $result['errors'][0] ?? 'Não foi possível concluir a ação no comentário.');
}

redirect($result['redirect'] . $result['anchor']);
