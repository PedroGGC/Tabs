<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

$pdo = getPDO();
$stmt = $pdo->query(
    'SELECT posts.id, posts.title, posts.content, posts.created_at, users.username AS author
     FROM posts
     INNER JOIN users ON users.id = posts.user_id
     ORDER BY posts.created_at DESC'
);
$posts = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blog PHP</title>
    <link rel="stylesheet" href="public/css/style.css">
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

    <main class="container">
        <h1>Posts recentes</h1>

        <?php if ($posts === []): ?>
            <p class="empty">Nenhum post publicado ainda.</p>
        <?php else: ?>
            <?php foreach ($posts as $post): ?>
                <article class="card">
                    <h2>
                        <a href="post.php?id=<?= (int) $post['id']; ?>"><?= e($post['title']); ?></a>
                    </h2>
                    <p class="meta">
                        Por <strong><?= e($post['author']); ?></strong> em <?= e(formatDate($post['created_at'])); ?>
                    </p>
                    <p><?= e(excerpt($post['content'])); ?></p>
                    <a class="read-more" href="post.php?id=<?= (int) $post['id']; ?>">Ler post completo</a>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </main>
</body>
</html>
