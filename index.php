<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';

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
$currentUserId = currentUserId();

$stmt = $pdo->prepare(
    'SELECT
        posts.id,
        posts.title,
        LEFT(posts.content, 400) AS content,
        posts.cover_image,
        posts.created_at,
        users.id AS author_id,
        users.username AS author,
        users.avatar AS author_avatar,
        COALESCE(SUM(v.vote), 0) AS score,
        MAX(CASE WHEN v.user_id = :current_user_id THEN v.vote ELSE 0 END) AS userVote
     FROM posts
     INNER JOIN users ON users.id = posts.user_id
     LEFT JOIN votes v ON v.item_type = \'post\' AND v.item_id = posts.id
     GROUP BY posts.id
     ORDER BY posts.created_at DESC
     LIMIT :limit OFFSET :offset'
);
$stmt->bindValue(':limit', $postsPerPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':current_user_id', $currentUserId ?? 0, PDO::PARAM_INT);
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
    <?= headTags('Tabs | Início'); ?>
</head>

<body>
    <div id="page">
        <?= siteHeader(); ?>

        <main class="container page-shell">
            <?php if ($flash): ?>
                <div class="alert <?= $flash['type'] === 'success' ? 'alert-success' : 'alert-error'; ?>">
                    <p><?= e($flash['message']); ?></p>
                </div>
            <?php endif; ?>

            <section class="card hero">
                <div>
                    <h1>Posts recentes</h1>
                    <p class="hero-subtitle">Leituras rápidas, ideias e atualizações publicadas pelos autores da
                        plataforma.</p>
                </div>
            </section> <?php if ($posts === []): ?>
                <p class="empty">Nenhum post publicado ainda.</p>
            <?php else: ?>
                <div class="posts-grid">
                    <?php foreach ($posts as $post): ?>
                        <article class="card post-card post-card-clickable">
                            <a class="post-card-link" href="post.php?id=<?= (int) $post['id']; ?>" aria-label="Abrir post 
                    <?= e((string) $post['title']); ?>">
                            </a>
                            <a class="author-link" href="user.php?id=<?= (int) $post['author_id']; ?>" data-transition="up">
                                <?php if (!empty($post['author_avatar'])): ?>
                                    <img class="avatar avatar-sm" src="<?= e((string) $post['author_avatar']); ?>" alt="Avatar de
                    <?= e($post['author']); ?>">
                                <?php else: ?>
                                    <span
                                        class="avatar avatar-sm avatar-fallback"><?= e(usernameInitial((string) $post['author'])); ?></span>
                                <?php endif; ?>
                                <span class="author-link-name">
                                    <?= e($post['author']); ?>
                                </span>
                            </a>
                            <h2><?= e($post['title']); ?></h2>
                            <?php if (!empty($post['cover_image'])): ?>
                                <div class="post-cover-wrap">
                                    <img class="cover-blur" aria-hidden="true" src="<?= e((string) $post['cover_image']); ?>"
                                        alt="">
                                    <img class="post-cover cover-main" src="<?= e((string) $post['cover_image']); ?>"
                                        alt="Imagem de capa de <?= e($post['title']); ?>">
                                </div>
                            <?php endif; ?>
                            <p><?= e(excerpt((string) $post['content'], 200)); ?></p>

                            <!-- VOTES -->
                            <div class="vote-group" data-item-type="post" data-item-id="<?= (int) $post['id']; ?>"
                                style="margin-top: 1rem;">
                                <button type="button"
                                    class="action-btn vote-btn <?= (int) $post['userVote'] === 1 ? 'active-up' : '' ?>"
                                    data-vote="1" aria-label="Upvote">
                                    <svg width=" 20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                        stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M9 11V19h6v-8h4l-7-7-7 7h4z" />
                                    </svg>
                                </button>
                                <span class="vote-count"
                                    style="font-weight: 700; font-size: 0.95rem; min-width: 2ch; text-align: center; color: var(--color-text); margin: 0 4px;">
                                    <?= (int) $post['score']; ?>
                                </span>
                                <button type="button"
                                    class="action-btn vote-btn <?= (int) $post['userVote'] === -1 ? 'active-down' : '' ?>"
                                    data-vote="-1" aria-label="Downvote">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                        stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M15 13V5H9v8H5l7 7 7-7h-4z" />
                                    </svg>
                                </button>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>

                <?php if ($hasPreviousPage || $hasNextPage): ?>
                    <nav class="pagination" aria-label="Paginação">
                        <?php if ($hasPreviousPage): ?>
                            <a class="pagination-link" href="<?= e($buildPageUrl($currentPage - 1)); ?>">← Anterior</a>
                        <?php endif; ?>
                        <?php if ($hasNextPage): ?>
                            <a class="pagination-link" href="<?= e($buildPageUrl($currentPage + 1)); ?>">Próxima →</a>
                        <?php endif; ?>
                    </nav> <?php endif; ?>
            <?php endif; ?>
        </main>
    </div>
    <?= pageScripts(); ?>
    <script src="public/js/votes.js" defer></script>
</body>

</html>
