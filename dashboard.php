<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

requireLogin();

$pdo = getPDO();
$userId = currentUserId();
$flash = getFlash();
$dashboardTransitionClass = '';
$enteredFromLogin = isset($_GET['entered']) && $_GET['entered'] === '1';

if (
    $enteredFromLogin ||
    (isset($_SESSION['dashboard_transition']) && $_SESSION['dashboard_transition'] === 'slide_in_right')
) {
    $dashboardTransitionClass = 'dashboard-slide-in';
    unset($_SESSION['dashboard_transition']);
}

$stmt = $pdo->prepare(
    'SELECT id, title, created_at, updated_at
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
    <script defer src="public/js/transitions.js"></script>
</head>
<body<?= $dashboardTransitionClass !== '' ? ' class="' . $dashboardTransitionClass . '"' : ''; ?>>
    <header class="site-header">
        <div class="container nav">
            <a class="brand" href="index.php">Blog PHP</a>
            <nav>
                <a href="dashboard.php">Dashboard</a>
                <a href="logout.php">Sair</a>
            </nav>
        </div>
    </header>

    <main class="container page-shell">
        <section class="page-title">
            <div>
                <h1>Seus posts, <?= e((string) ($_SESSION['username'] ?? '')); ?></h1>
                <p class="meta">Gerencie conteúdos publicados e atualize quando precisar.</p>
            </div>
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
                                <td><?= e(formatDate($post['created_at'])); ?></td>
                                <td><?= e(formatDate($post['updated_at'])); ?></td>
                                <td class="actions">
                                    <a class="action-link" href="post-edit.php?id=<?= (int) $post['id']; ?>">Editar</a>
                                    <a class="action-link danger-link" href="post-delete.php?id=<?= (int) $post['id']; ?>">Excluir</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </main>
    <?php if ($enteredFromLogin): ?>
        <script>
        if (window.history && window.history.replaceState) {
            window.history.replaceState({}, document.title, 'dashboard.php');
        }
        </script>
    <?php endif; ?>
</body>
</html>
