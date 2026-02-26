<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';

$pdo = getPDO();
$flash = getFlash();
$postId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$post = null;
$comments = [];

if ($postId !== false && $postId !== null) {
    $stmt = $pdo->prepare(
        'SELECT posts.id, posts.title, posts.content, posts.cover_image,
                posts.created_at, posts.updated_at,
                users.id AS author_id, users.username AS author, users.avatar AS author_avatar
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
} else {
    $commentsStmt = $pdo->prepare(
        'SELECT
            comments.id,
            comments.content,
            comments.created_at,
            comments.parent_id,
            users.id   AS comment_user_id,
            users.username AS comment_author,
            users.avatar   AS comment_author_avatar
         FROM comments
         INNER JOIN users ON users.id = comments.user_id
         WHERE comments.post_id = :post_id
         ORDER BY comments.created_at ASC'
    );
    $commentsStmt->execute(['post_id' => $postId]);
    $comments = $commentsStmt->fetchAll();
}

$currentUserId = currentUserId();
$pageTitle = $post ? e((string) $post['title']) . ' | Threadly' : 'Post não encontrado | Threadly';
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <?= headTags($post ? (string) $post['title'] . ' | Threadly' : 'Post não encontrado | Threadly'); ?>
</head>

<body>
    <div id="page">
        <header class="site-header">
            <div class="container nav">
                <a class="brand" href="index.php">Threadly</a>
                <nav>
                    <?= themeToggle(); ?>
                    <?php if (isLogged()): ?>
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

            <?php if (!$post): ?>
                <article class="card">
                    <h1>Post não encontrado</h1>
                    <p>O post solicitado não existe ou foi removido.</p>
                    <a class="read-more" href="index.php">Voltar para a listagem</a>
                </article>
            <?php else: ?>
                <article class="card post-full">
                    <h1><?= e((string) $post['title']); ?></h1>
                    <div class="meta author-row">
                        <a class="author-link" href="user.php?id=<?= (int) $post['author_id']; ?>">
                            <?php if (!empty($post['author_avatar'])): ?>
                                <img class="avatar avatar-sm" src="<?= e((string) $post['author_avatar']); ?>"
                                    alt="Avatar de <?= e((string) $post['author']); ?>">
                            <?php else: ?>
                                <span
                                    class="avatar avatar-sm avatar-fallback"><?= e(usernameInitial((string) $post['author'])); ?></span>
                            <?php endif; ?>
                            <span class="author-link-name"><?= e((string) $post['author']); ?></span>
                        </a>
                        <p class="meta">em <?= e(formatDate((string) $post['created_at'])); ?></p>
                    </div>
                    <?php if (!empty($post['cover_image'])): ?>
                        <img class="post-cover post-cover-full" src="<?= e((string) $post['cover_image']); ?>"
                            alt="Imagem de capa de <?= e((string) $post['title']); ?>">
                    <?php endif; ?>
                    <div class="post-content"><?= nl2br(e((string) $post['content'])); ?></div>
                    <p class="meta">Última atualização: <?= e(formatDate((string) $post['updated_at'])); ?></p>
                    <?php if ($currentUserId !== null && (int) $post['author_id'] === $currentUserId): ?>
                        <div class="actions-row" style="margin-top:1rem;">
                            <a class="action-link" href="posts.php?action=edit&id=<?= (int) $post['id']; ?>"
                                data-transition="up">Editar post</a>
                        </div>
                    <?php endif; ?>
                    <a class="read-more" href="index.php">Voltar para a listagem</a>
                </article>

                <section class="card post-full" id="comments">
                    <h2>Comentários</h2>

                    <?php if (isLogged()): ?>
                        <form method="post" action="comments.php?action=store" class="comment-form">
                            <?= csrfInput(); ?>
                            <input type="hidden" name="post_id" value="<?= (int) $postId; ?>">
                            <label for="comment-content">Escreva um comentário</label>
                            <textarea id="comment-content" name="content" rows="4" maxlength="1000" required></textarea>
                            <button type="submit">Comentar</button>
                        </form>
                    <?php else: ?>
                        <p class="meta">Faça <a href="login.php">login</a> para comentar.</p>
                    <?php endif; ?>

                    <?php if ($comments === []): ?>
                        <p class="meta">Nenhum comentário ainda.</p>
                    <?php else: ?>
                        <div class="comment-list">
                            <?php foreach ($comments as $comment): ?>
                                <?php
                                $isOwn = $currentUserId !== null && (int) $comment['comment_user_id'] === $currentUserId;
                                $commentAnchor = 'comment-' . (int) $comment['id'];
                                ?>
                                <article class="comment-item" id="<?= $commentAnchor; ?>">
                                    <div class="author-row">
                                        <a class="author-link" href="user.php?id=<?= (int) $comment['comment_user_id']; ?>">
                                            <?php if (!empty($comment['comment_author_avatar'])): ?>
                                                <img class="avatar avatar-sm"
                                                    src="<?= e((string) $comment['comment_author_avatar']); ?>"
                                                    alt="Avatar de <?= e((string) $comment['comment_author']); ?>">
                                            <?php else: ?>
                                                <span
                                                    class="avatar avatar-sm avatar-fallback"><?= e(usernameInitial((string) $comment['comment_author'])); ?></span>
                                            <?php endif; ?>
                                            <span class="author-link-name"><?= e((string) $comment['comment_author']); ?></span>
                                        </a>
                                        <p class="meta">em <?= e(formatDate((string) $comment['created_at'])); ?></p>
                                    </div>

                                    <?php if ($isOwn && isset($_GET['edit_comment']) && (int) $_GET['edit_comment'] === (int) $comment['id']): ?>
                                        <form method="post" action="comments.php?action=update&id=<?= (int) $comment['id']; ?>"
                                            class="comment-form">
                                            <?= csrfInput(); ?>
                                            <textarea name="content" rows="3" maxlength="1000"
                                                required><?= e((string) $comment['content']); ?></textarea>
                                            <div class="actions-row">
                                                <button type="submit">Salvar</button>
                                                <a class="secondary"
                                                    href="post.php?id=<?= (int) $postId; ?>#<?= $commentAnchor; ?>">Cancelar</a>
                                            </div>
                                        </form>
                                    <?php else: ?>
                                        <p class="comment-content"><?= nl2br(e((string) $comment['content'])); ?></p>
                                        <?php if ($isOwn): ?>
                                            <div class="comment-actions">
                                                <a class="action-link"
                                                    href="post.php?id=<?= (int) $postId; ?>&edit_comment=<?= (int) $comment['id']; ?>#<?= $commentAnchor; ?>">Editar</a>
                                                <form method="post" action="comments.php?action=delete&id=<?= (int) $comment['id']; ?>"
                                                    style="display:inline;">
                                                    <?= csrfInput(); ?>
                                                    <button type="submit" class="action-link danger-link"
                                                        onclick="return confirm('Excluir este comentário?')">Excluir</button>
                                                </form>
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>

                                    <?php if (isLogged() && !$isOwn): ?>
                                        <form method="post" action="comments.php?action=store" class="comment-form reply-form">
                                            <?= csrfInput(); ?>
                                            <input type="hidden" name="post_id" value="<?= (int) $postId; ?>">
                                            <input type="hidden" name="parent_id" value="<?= (int) $comment['id']; ?>">
                                            <label for="reply-<?= (int) $comment['id']; ?>" class="meta">Responder
                                                <?= e((string) $comment['comment_author']); ?></label>
                                            <textarea id="reply-<?= (int) $comment['id']; ?>" name="content" rows="2" maxlength="1000"
                                                required></textarea>
                                            <button type="submit">Responder</button>
                                        </form>
                                    <?php endif; ?>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
        </main>
        <?= siteFooter(); ?>
    </div>
    <?= pageScripts(); ?>
</body>

</html>