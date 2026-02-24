<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

$pdo = getPDO();
$flash = getFlash();
$postId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$post = null;
$comments = [];

if ($postId !== false && $postId !== null) {
    $stmt = $pdo->prepare(
        'SELECT posts.id, posts.title, posts.content, posts.cover_image, posts.created_at, posts.updated_at, users.id AS author_id, users.username AS author, users.avatar AS author_avatar
         FROM posts
         INNER JOIN users ON users.id = posts.user_id
         WHERE posts.id = :id
         LIMIT 1'
    );
    $stmt->execute(['id' => $postId]);
    $post = $stmt->fetch();
}

if (isPostRequest()) {
    if ($postId === false || $postId === null || !$post) {
        setFlash('error', 'Post inválido para comentário.');
        redirect('index.php');
    }

    if (!isLogged()) {
        setFlash('error', 'Faça login para comentar.');
        redirect('login.php');
    }

    verifyCsrfOrFail();
    $commentContent = sanitize((string) ($_POST['comment'] ?? ''));

    if ($commentContent === '') {
        setFlash('error', 'O comentário não pode estar vazio.');
        redirect('post.php?id=' . (int) $postId);
    }

    if (mb_strlen($commentContent, 'UTF-8') > 1000) {
        setFlash('error', 'O comentário deve ter no máximo 1000 caracteres.');
        redirect('post.php?id=' . (int) $postId);
    }

    $insertCommentStmt = $pdo->prepare(
        'INSERT INTO comments (post_id, user_id, content, created_at)
         VALUES (:post_id, :user_id, :content, NOW())'
    );
    $insertCommentStmt->execute([
        'post_id' => $postId,
        'user_id' => currentUserId(),
        'content' => $commentContent,
    ]);

    setFlash('success', 'Comentário publicado com sucesso.');
    redirect('post.php?id=' . (int) $postId);
}

if (!$post) {
    http_response_code(404);
} else {
    $commentsStmt = $pdo->prepare(
        'SELECT comments.id, comments.content, comments.created_at, users.id AS comment_user_id, users.username AS comment_author, users.avatar AS comment_author_avatar
         FROM comments
         INNER JOIN users ON users.id = comments.user_id
         WHERE comments.post_id = :post_id
         ORDER BY comments.created_at ASC'
    );
    $commentsStmt->execute(['post_id' => $postId]);
    $comments = $commentsStmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $post ? e($post['title']) : 'Post não encontrado'; ?></title>
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
                <h1><?= e($post['title']); ?></h1>
                <div class="meta author-row">
                    <a class="author-link" href="user.php?id=<?= (int) $post['author_id']; ?>">
                        <?php if (!empty($post['author_avatar'])): ?>
                            <img class="avatar avatar-sm" src="<?= e((string) $post['author_avatar']); ?>" alt="Avatar de <?= e($post['author']); ?>">
                        <?php else: ?>
                            <span class="avatar avatar-sm avatar-fallback"><?= e(usernameInitial((string) $post['author'])); ?></span>
                        <?php endif; ?>
                        <span class="author-link-name"><?= e($post['author']); ?></span>
                    </a>
                    <p class="meta">em <?= e(formatDate($post['created_at'])); ?></p>
                </div>
                <?php if (!empty($post['cover_image'])): ?>
                    <img class="post-cover post-cover-full" src="<?= e((string) $post['cover_image']); ?>" alt="Imagem de capa de <?= e($post['title']); ?>">
                <?php endif; ?>
                <div class="post-content"><?= nl2br(e($post['content'])); ?></div>
                <p class="meta">Última atualização: <?= e(formatDate($post['updated_at'])); ?></p>
                <a class="read-more" href="index.php">Voltar para a listagem</a>
            </article>

            <section class="card post-full">
                <h2>Comentários</h2>

                <?php if (isLogged()): ?>
                    <form method="post" action="post.php?id=<?= (int) $postId; ?>" class="comment-form">
                        <?= csrfInput(); ?>
                        <label for="comment">Escreva um comentário</label>
                        <textarea id="comment" name="comment" rows="4" maxlength="1000" required></textarea>
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
                            <article class="comment-item">
                                <div class="author-row">
                                    <a class="author-link" href="user.php?id=<?= (int) $comment['comment_user_id']; ?>">
                                        <?php if (!empty($comment['comment_author_avatar'])): ?>
                                            <img class="avatar avatar-sm" src="<?= e((string) $comment['comment_author_avatar']); ?>" alt="Avatar de <?= e((string) $comment['comment_author']); ?>">
                                        <?php else: ?>
                                            <span class="avatar avatar-sm avatar-fallback"><?= e(usernameInitial((string) $comment['comment_author'])); ?></span>
                                        <?php endif; ?>
                                        <span class="author-link-name"><?= e((string) $comment['comment_author']); ?></span>
                                    </a>
                                    <p class="meta">em <?= e(formatDate((string) $comment['created_at'])); ?></p>
                                </div>
                                <p class="comment-content"><?= nl2br(e((string) $comment['content'])); ?></p>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </main>
    </div>
    <script src="public/js/transitions.js"></script>
</body>
</html>
