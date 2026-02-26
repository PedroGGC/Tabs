<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

$pdo = getPDO();
$flash = getFlash();
// perf: paginated public listing and reduced selected content payload
$postsPerPage = 6;
$currentPage = filter_input(
    INPUT_GET,
    'page',
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);
$currentPage = $currentPage === false || $currentPage === null ? 1 : $currentPage;

$countStmt = $pdo->query('SELECT COUNT(*) FROM posts');
$totalPosts = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalPosts / $postsPerPage));

if ($currentPage > $totalPages) {
    $currentPage = $totalPages;
}

$offset = ($currentPage - 1) * $postsPerPage;

$stmt = $pdo->prepare(
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
     ORDER BY posts.created_at DESC
     LIMIT :limit OFFSET :offset'
);
$stmt->bindValue(':limit', $postsPerPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$posts = $stmt->fetchAll();

$buildPageUrl = static function (int $page): string {
    $params = $_GET;
    $params['page'] = $page;
    return 'index.php?' . http_build_query($params);
};

$hasPreviousPage = $currentPage > 1;
$hasNextPage = $currentPage < $totalPages;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Threadly | Início</title>
    <link rel="stylesheet" href="public/css/style.css">
</head>
<body>
    <div id="page">
    <header class="site-header">
        <div class="container nav">
            <a class="brand" href="index.php">Threadly</a>
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
        <?php if ($flash): ?>
            <div class="alert <?= $flash['type'] === 'success' ? 'alert-success' : 'alert-error'; ?>">
                <p><?= e($flash['message']); ?></p>
            </div>
        <?php endif; ?>

        <section class="card hero">
            <div>
                <h1>Posts recentes</h1>
                <p class="hero-subtitle">Leituras rápidas, ideias e atualizações publicadas pelos autores da plataforma.</p>
            </div>
            <?php if (isLogged()): ?>
                <a class="button-inline" href="post-create.php">Criar</a>
            <?php endif; ?>
        </section>

        <?php if ($posts === []): ?>
            <p class="empty">Nenhum post publicado ainda.</p>
        <?php else: ?>
            <?php foreach ($posts as $post): ?>
                <article class="card post-card post-card-clickable">
                    <a
                        class="post-card-link"
                        href="post.php?id=<?= (int) $post['id']; ?>"
                        aria-label="Abrir post <?= e((string) $post['title']); ?>"
                    ></a>
                    <a class="author-link" href="user.php?id=<?= (int) $post['author_id']; ?>">
                        <?php if (!empty($post['author_avatar'])): ?>
                            <img class="avatar avatar-sm" src="<?= e((string) $post['author_avatar']); ?>" alt="Avatar de <?= e($post['author']); ?>">
                        <?php else: ?>
                            <span class="avatar avatar-sm avatar-fallback"><?= e(usernameInitial((string) $post['author'])); ?></span>
                        <?php endif; ?>
                        <span class="author-link-name"><?= e($post['author']); ?></span>
                    </a>
                    <h2><?= e($post['title']); ?></h2>
                    <?php if (!empty($post['cover_image'])): ?>
                        <img class="post-cover" src="<?= e((string) $post['cover_image']); ?>" alt="Imagem de capa de <?= e($post['title']); ?>">
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
    </main>
    </div>
    <script src="public/js/transitions.js"></script>
</body>
</html>
