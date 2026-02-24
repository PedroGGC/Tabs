<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

requireLogin();

$pdo = getPDO();
$userId = currentUserId();
$postId = filter_input(INPUT_POST, 'post_id', FILTER_VALIDATE_INT);

if ($postId === false || $postId === null) {
    $postId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
}

if ($postId === false || $postId === null) {
    setFlash('error', 'Post inválido.');
    redirect('dashboard.php');
}

$stmt = $pdo->prepare('SELECT id, title FROM posts WHERE id = :id AND user_id = :user_id LIMIT 1');
$stmt->execute([
    'id' => $postId,
    'user_id' => $userId,
]);
$post = $stmt->fetch();

if (!$post) {
    setFlash('error', 'Post não encontrado ou sem permissão para excluir.');
    redirect('dashboard.php');
}

if (isPostRequest()) {
    verifyCsrfOrFail();
    $confirm = $_POST['confirm'] ?? 'no';

    if ($confirm === 'yes') {
        $deleteStmt = $pdo->prepare('DELETE FROM posts WHERE id = :id AND user_id = :user_id');
        $deleteStmt->execute([
            'id' => $postId,
            'user_id' => $userId,
        ]);
        setFlash('success', 'Post excluído com sucesso.');
    } else {
        setFlash('error', 'Exclusão cancelada.');
    }

    redirect('dashboard.php');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Excluir post</title>
    <link rel="stylesheet" href="public/css/style.css">
</head>
<body>
    <div id="page">
    <header class="site-header">
        <div class="container nav">
            <a class="brand" href="index.php">Blog PHP</a>
            <nav>
                <a href="dashboard.php">Dashboard</a>
                <a href="post-create.php">Criar</a>
                <a href="logout.php" data-transition="back">Sair</a>
            </nav>
        </div>
    </header>

    <main class="container page-shell">
        <section class="card form-card">
            <h1>Confirmar exclusão</h1>
            <p>Tem certeza que deseja excluir o post <strong><?= e($post['title']); ?></strong>?</p>

            <form method="post" action="post-delete.php?id=<?= (int) $postId; ?>" class="actions-row">
                <?= csrfInput(); ?>
                <input type="hidden" name="post_id" value="<?= (int) $postId; ?>">
                <button type="submit" name="confirm" value="yes" class="danger">Sim, excluir</button>
                <button type="submit" name="confirm" value="no" class="secondary">Cancelar</button>
            </form>
        </section>
    </main>
    </div>
    <script src="public/js/transitions.js"></script>
</body>
</html>
