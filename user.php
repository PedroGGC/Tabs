<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

$pdo = getPDO();
$userId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$profileUser = null;

if ($userId !== false && $userId !== null) {
    $userStmt = $pdo->prepare('SELECT id, username, avatar, bio FROM users WHERE id = :id LIMIT 1');
    $userStmt->execute(['id' => $userId]);
    $profileUser = $userStmt->fetch();
}

if (!$profileUser) {
    http_response_code(404);
}

$posts = [];
$hasPreviousPage = false;
$hasNextPage = false;
$currentPage = 1;
$totalPages = 1;

if ($profileUser) {
    $postsPerPage = 6;
    $currentPage = filter_input(
        INPUT_GET,
        'page',
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );
    $currentPage = $currentPage === false || $currentPage === null ? 1 : $currentPage;

    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM posts WHERE user_id = :user_id');
    $countStmt->execute(['user_id' => $profileUser['id']]);
    $totalPosts = (int) $countStmt->fetchColumn();
    $totalPages = max(1, (int) ceil($totalPosts / $postsPerPage));

    if ($currentPage > $totalPages) {
        $currentPage = $totalPages;
    }

    $offset = ($currentPage - 1) * $postsPerPage;

    $postStmt = $pdo->prepare(
        'SELECT
            posts.id,
            posts.title,
            LEFT(posts.content, 400) AS content,
            posts.cover_image,
            posts.created_at,
            users.id AS author_id,
            users.username AS author,
            users.avatar AS author_avatar
         FROM posts
         INNER JOIN users ON users.id = posts.user_id
         WHERE posts.user_id = :user_id
         ORDER BY posts.created_at DESC
         LIMIT :limit OFFSET :offset'
    );
    $postStmt->bindValue(':user_id', (int) $profileUser['id'], PDO::PARAM_INT);
    $postStmt->bindValue(':limit', $postsPerPage, PDO::PARAM_INT);
    $postStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $postStmt->execute();
    $posts = $postStmt->fetchAll();

    $hasPreviousPage = $currentPage > 1;
    $hasNextPage = $currentPage < $totalPages;
}

$buildPageUrl = static function (int $page): string {
    $params = $_GET;
    $params['page'] = $page;
    return 'user.php?' . http_build_query($params);
};

$isOwnProfile = $profileUser && isLogged() && currentUserId() === (int) $profileUser['id'];
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $profileUser ? e((string) $profileUser['username']) : 'Usuário não encontrado'; ?></title>
    <link rel="stylesheet" href="public/css/style.css">
</head>

<body>
    <div id="page">
        <header class="site-header">
            <div class="container nav">
                <a class="brand" href="index.php">Blog PHP</a>
                <nav>
                    <?php if (isLogged()): ?>
                        <span class="nav-user">Olá, <?= e((string) ($_SESSION['username'] ?? '')); ?></span>
                        <a href="dashboard.php">Dashboard</a>
                        <a href="logout.php" data-transition="back">Sair</a>
                    <?php else: ?>
                        <a href="login.php">Login</a>
                        <a href="register.php">Cadastro</a>
                    <?php endif; ?>
                </nav>
            </div>
        </header>

        <main class="container page-shell">
            <?php if (!$profileUser): ?>
                <article class="card">
                    <h1>Usuário não encontrado</h1>
                    <a class="read-more" href="index.php">Voltar para a página inicial</a>
                </article>
            <?php else: ?>
                <section class="card user-profile-card">
                    <?php if ($isOwnProfile): ?>
                        <a class="user-profile-avatar-link" href="profile.php">
                            <?php if (!empty($profileUser['avatar'])): ?>
                                <img class="avatar avatar-xl" src="<?= e((string) $profileUser['avatar']); ?>"
                                    alt="Avatar de <?= e((string) $profileUser['username']); ?>">
                            <?php else: ?>
                                <span
                                    class="avatar avatar-xl avatar-fallback"><?= e(usernameInitial((string) $profileUser['username'])); ?></span>
                            <?php endif; ?>
                        </a>
                    <?php else: ?>
                        <?php if (!empty($profileUser['avatar'])): ?>
                            <img class="avatar avatar-xl" src="<?= e((string) $profileUser['avatar']); ?>"
                                alt="Avatar de <?= e((string) $profileUser['username']); ?>">
                        <?php else: ?>
                            <span
                                class="avatar avatar-xl avatar-fallback"><?= e(usernameInitial((string) $profileUser['username'])); ?></span>
                        <?php endif; ?>
                    <?php endif; ?>

                    <h1><?= e((string) $profileUser['username']); ?></h1>

                    <?php if (trim((string) ($profileUser['bio'] ?? '')) !== ''): ?>
                        <section class="user-bio">
                            <h2>Sobre mim</h2>
                            <p><?= nl2br(e((string) $profileUser['bio'])); ?></p>
                        </section>
                    <?php endif; ?>
                </section>

                <?php if ($posts === []): ?>
                    <p class="empty">Este usuário ainda não publicou posts.</p>
                <?php else: ?>
                    <?php foreach ($posts as $post): ?>
                        <article class="card post-card post-card-clickable">
                            <a class="post-card-link" href="post.php?id=<?= (int) $post['id']; ?>"
                                aria-label="Abrir post <?= e((string) $post['title']); ?>"></a>
                            <a class="author-link" href="user.php?id=<?= (int) $post['author_id']; ?>">
                                <?php if (!empty($post['author_avatar'])): ?>
                                    <img class="avatar avatar-sm" src="<?= e((string) $post['author_avatar']); ?>"
                                        alt="Avatar de <?= e($post['author']); ?>">
                                <?php else: ?>
                                    <span
                                        class="avatar avatar-sm avatar-fallback"><?= e(usernameInitial((string) $post['author'])); ?></span>
                                <?php endif; ?>
                                <span class="author-link-name"><?= e($post['author']); ?></span>
                            </a>
                            <h2><?= e($post['title']); ?></h2>
                            <?php if (!empty($post['cover_image'])): ?>
                                <img class="post-cover" src="<?= e((string) $post['cover_image']); ?>"
                                    alt="Imagem de capa de <?= e($post['title']); ?>">
                            <?php endif; ?>
                            <p><?= e(excerpt((string) $post['content'], 200)); ?></p>
                        </article>
                    <?php endforeach; ?>

                    <?php if ($hasPreviousPage || $hasNextPage): ?>
                        <nav class="pagination" aria-label="Paginação">
                            <?php if ($hasPreviousPage): ?>
                                <a class="pagination-link" href="<?= e($buildPageUrl($currentPage - 1)); ?>">← Anterior</a>
                            <?php endif; ?>
                            <?php if ($hasNextPage): ?>
                                <a class="pagination-link" href="<?= e($buildPageUrl($currentPage + 1)); ?>">Próxima →</a>
                            <?php endif; ?>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>
        </main>
    </div>
    <script src="public/js/transitions.js"></script>
</body>

</html>