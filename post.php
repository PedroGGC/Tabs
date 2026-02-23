<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

$pdo = getPDO();
$postId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$post = null;

if ($postId !== false && $postId !== null) {
    $stmt = $pdo->prepare(
        'SELECT posts.id, posts.title, posts.content, posts.created_at, posts.updated_at, users.username AS author
         FROM posts
         INNER JOIN users ON users.id = posts.user_id
         WHERE posts.id = :id
         LIMIT 1'
    );
    $stmt->execute(['id' => $postId]);
    $post = $stmt->fetch();
}

if (!$post) {
    http_response_code(404);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $post ? e($post['title']) : 'Post não encontrado'; ?></title>
    <link rel="stylesheet" href="public/css/style.css">
    <script defer src="public/js/transitions.js"></script>
</head>
<body>
    <header class="site-header">
        <div class="container nav">
            <a class="brand" href="index.php">Blog PHP</a>
            <nav>
                <?php if (isLogged()): ?>
                    <a href="dashboard.php">Dashboard</a>
                    <a href="logout.php">Sair</a>
                <?php else: ?>
                    <a href="login.php">Login</a>
                    <a href="register.php">Cadastro</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <main class="container page-shell">
        <?php if (!$post): ?>
            <article class="card">
                <h1>Post não encontrado</h1>
                <p>O post solicitado não existe ou foi removido.</p>
                <a class="read-more" href="index.php">Voltar para a listagem</a>
            </article>
        <?php else: ?>
            <article class="card post-full">
                <h1><?= e($post['title']); ?></h1>
                <p class="meta">
                    Por <strong><?= e($post['author']); ?></strong> em <?= e(formatDate($post['created_at'])); ?>
                </p>
                <div class="post-content"><?= nl2br(e($post['content'])); ?></div>
                <p class="meta">Última atualização: <?= e(formatDate($post['updated_at'])); ?></p>
                <a class="read-more" href="index.php">Voltar para a listagem</a>
            </article>
        <?php endif; ?>
    </main>
</body>
</html>
