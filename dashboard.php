<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

requireLogin();

$pdo = getPDO();
$userId = currentUserId();
$flash = getFlash();

$stmt = $pdo->prepare(
    'SELECT id, title, slug, created_at, updated_at
     FROM posts
     WHERE user_id = :user_id
     ORDER BY created_at DESC'
);
$stmt->execute(['user_id' => $userId]);
$posts = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href="public/css/style.css">
</head>
<body>
    <header class="site-header">
        <div class="container nav">
            <a class="brand" href="index.php">Blog PHP</a>
            <nav>
                <a href="dashboard.php">Dashboard</a>
                <a href="post-create.php">Novo post</a>
                <a href="logout.php">Sair</a>
            </nav>
        </div>
    </header>

    <main class="container">
        <section class="page-title">
            <h1>Seus posts, <?= e((string) ($_SESSION['username'] ?? '')); ?></h1>
            <a class="button-inline" href="post-create.php">Criar novo post</a>
        </section>

        <?php if ($flash): ?>
            <div class="alert <?= $flash['type'] === 'success' ? 'alert-success' : 'alert-error'; ?>">
                <p><?= e($flash['message']); ?></p>
            </div>
        <?php endif; ?>

        <?php if ($posts === []): ?>
            <p class="empty">Você ainda não criou posts.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Título</th>
                            <th>Slug</th>
                            <th>Criado em</th>
                            <th>Atualizado em</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($posts as $post): ?>
                            <tr>
                                <td>
                                    <a href="post.php?id=<?= (int) $post['id']; ?>"><?= e($post['title']); ?></a>
                                </td>
                                <td><?= e($post['slug']); ?></td>
                                <td><?= e(formatDate($post['created_at'])); ?></td>
                                <td><?= e(formatDate($post['updated_at'])); ?></td>
                                <td class="actions">
                                    <a href="post-edit.php?id=<?= (int) $post['id']; ?>">Editar</a>
                                    <a class="danger-link" href="post-delete.php?id=<?= (int) $post['id']; ?>">Excluir</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>
