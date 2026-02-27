<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';

$pdo = getPDO();
$flash = getFlash();
$postId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$currentUserId = currentUserId();
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
            users.id           AS comment_user_id,
            users.username     AS comment_author,
            users.avatar       AS comment_author_avatar,
            COALESCE(SUM(v.vote), 0)                    AS score,
            MAX(CASE WHEN v.user_id = :viewer_id THEN v.vote ELSE NULL END) AS userVote
         FROM comments
         INNER JOIN users ON users.id = comments.user_id
         LEFT JOIN votes v ON v.item_type = \'comment\' AND v.item_id = comments.id
         WHERE comments.post_id = :post_id
         GROUP BY comments.id, comments.content, comments.created_at, comments.parent_id,
                  users.id, users.username, users.avatar
         ORDER BY comments.created_at ASC'
    );
    $commentsStmt->execute([
        'post_id'   => $postId,
        'viewer_id' => $currentUserId ?? 0,
    ]);
    $comments = $commentsStmt->fetchAll();
}


$pageTitle = $post ? e((string) $post['title']) . ' | Tabs' : 'Post não encontrado | Tabs';
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <?= headTags($post ? (string) $post['title'] . ' | Tabs' : 'Post não encontrado | Tabs'); ?>
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
                        <a class="author-link" href="user.php?id=<?= (int) $post['author_id']; ?>" data-transition="up">
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
                        <div class="post-cover-wrap">
                            <img class="cover-blur" aria-hidden="true" src="<?= e((string) $post['cover_image']); ?>" alt="">
                            <img class="post-cover post-cover-full cover-main" src="<?= e((string) $post['cover_image']); ?>"
                                alt="Imagem de capa de <?= e((string) $post['title']); ?>">
                        </div>
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
                        <?php if ($currentUserId !== null && (int) $post['author_id'] === $currentUserId): ?>
                            <p class="meta">Como autor, você pode apenas responder a outros comentários.</p>
                        <?php else: ?>
                            <div class="join-conversation-fake" id="fake-comment-input">Join the conversation...</div>
                            <form method="post" action="comments.php?action=store" class="comment-form" id="real-comment-form"
                                style="display: none;">
                                <?= csrfInput(); ?>
                                <input type="hidden" name="post_id" value="<?= (int) $postId; ?>">
                                <textarea id="comment-content" name="content" rows="4" maxlength="1000" required
                                    placeholder="Escreva seu comentário..."></textarea>
                                <div class="actions-row">
                                    <button type="button" class="button-outline" id="cancel-comment-btn">Cancelar</button>
                                    <button type="submit">Comentar</button>
                                </div>
                            </form>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="meta">Faça <a href="login.php" data-transition="up">login</a> para comentar.</p>
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
                                    <a class="author-avatar-link" href="user.php?id=<?= (int) $comment['comment_user_id']; ?>"
                                        data-transition="up">
                                        <?php if (!empty($comment['comment_author_avatar'])): ?>
                                            <img class="avatar avatar-sm" src="<?= e((string) $comment['comment_author_avatar']); ?>"
                                                alt="Avatar de <?= e((string) $comment['comment_author']); ?>">
                                        <?php else: ?>
                                            <span
                                                class="avatar avatar-sm avatar-fallback"><?= e(usernameInitial((string) $comment['comment_author'])); ?></span>
                                        <?php endif; ?>
                                    </a>

                                    <div class="comment-body">
                                        <div class="author-row">
                                            <a class="author-link" href="user.php?id=<?= (int) $comment['comment_user_id']; ?>"
                                                data-transition="up">
                                                <span class="author-link-name"><?= e((string) $comment['comment_author']); ?></span>
                                            </a>
                                            <?php if ((int) $comment['comment_user_id'] === (int) $post['author_id']): ?>
                                                <span class="badge badge-op">Autor</span>
                                            <?php endif; ?>
                                            <p class="meta" style="margin-left:auto;">em
                                                <?= e(formatDate((string) $comment['created_at'])); ?>
                                            </p>
                                        </div>

                                        <?php if ($isOwn && isset($_GET['edit_comment']) && (int) $_GET['edit_comment'] === (int) $comment['id']): ?>
                                            <form method="post" action="comments.php?action=update&id=<?= (int) $comment['id']; ?>"
                                                class="comment-form">
                                                <?= csrfInput(); ?>
                                                <textarea name="content" rows="3" maxlength="1000"
                                                    required><?= e((string) $comment['content']); ?></textarea>
                                                <div class="actions-row">
                                                    <button type="submit">Salvar</button>
                                                    <a class="secondary button-outline"
                                                        href="post.php?id=<?= (int) $postId; ?>#<?= $commentAnchor; ?>">Cancelar</a>
                                                </div>
                                            </form>
                                        <?php else: ?>
<p class="comment-content" id="comment-text-<?= (int) $comment['id']; ?>"><?= nl2br(trim(e((string) $comment['content']))); ?></p>
                                          <!-- Inline edit form (hidden by default) -->
                                            <form method="post" action="comments.php?action=update&id=<?= (int) $comment['id']; ?>"
                                                class="inline-edit-form" id="edit-form-<?= (int) $comment['id']; ?>"
                                                style="display:none;">
                                                <?= csrfInput(); ?>
                                                <textarea name="content" rows="3" maxlength="1000"
                                                    required><?= e((string) $comment['content']); ?></textarea>
                                                <div class="actions-row">
                                                    <button type="submit">Salvar</button>
                                                    <button type="button" class="button-outline edit-cancel-btn"
                                                        data-comment-id="<?= (int) $comment['id']; ?>">Cancelar</button>
                                                </div>
                                            </form>

                                            <div class="comment-actions">
                                                <div class="vote-group" data-item-type="comment" data-item-id="<?= (int) $comment['id']; ?>" style="display:flex; align-items:center;">
                                                    <button type="button" class="action-btn<?= ($comment['userVote'] ?? 0) === 1 ? ' active-up' : '' ?>" data-vote="1" aria-label="Upvote">
                                                        <svg width="20" height="20" viewBox="0 0 24 24" stroke="currentColor"
                                                            stroke-width="1.5" fill="none" stroke-linecap="round"
                                                            stroke-linejoin="round">
                                                            <path d="M9 11V19h6v-8h4l-7-7-7 7h4z" />
                                                        </svg>
                                                    </button>
                                                    <span class="vote-count"
                                                        style="font-weight:700; font-size:0.85rem; color:var(--color-text-muted); margin: 0 2px;"><?= (int) ($comment['score'] ?? 0); ?></span>
                                                    <button type="button" class="action-btn<?= ($comment['userVote'] ?? 0) === -1 ? ' active-down' : '' ?>" data-vote="-1" aria-label="Downvote">
                                                        <svg width="20" height="20" viewBox="0 0 24 24" stroke="currentColor"
                                                            stroke-width="1.5" fill="none" stroke-linecap="round"
                                                            stroke-linejoin="round">
                                                            <path d="M15 13V5H9v8H5l7 7 7-7h-4z" />
                                                        </svg>
                                                    </button>
                                                </div>

                                                <?php if (isLogged()): ?>
                                                    <button type="button" class="action-btn reply-toggle-btn"
                                                        data-target="reply-form-<?= (int) $comment['id'] ?>">
                                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                            stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
                                                        </svg>
                                                        Reply
                                                    </button>
                                                <?php endif; ?>

                                                <button type="button" class="action-btn award-btn">
                                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                        stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                                        <circle cx="12" cy="8" r="7" />
                                                        <polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88" />
                                                    </svg>
                                                    Award
                                                </button>

                                                <button type="button" class="action-btn share-btn"
                                                    data-link="<?= (int) $comment['id'] ?>">
                                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                        stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                                        <polyline points="15 14 20 9 15 4" />
                                                        <path d="M4 20v-7a4 4 0 0 1 4-4h12" />
                                                    </svg>
                                                    Share
                                                </button>

                                                <?php if ($isOwn): ?>
                                                    <button type="button" class="action-btn edit-comment-btn"
                                                        data-comment-id="<?= (int) $comment['id']; ?>"
                                                        style="margin-left:auto;">Editar</button>
                                                    <button type="button" class="action-btn danger-label" style="color:var(--danger);"
                                                        onclick="openCommentDeleteModal(<?= (int) $comment['id']; ?>)">Excluir</button>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (isLogged()): ?>
                                            <form method="post" action="comments.php?action=store" class="comment-form reply-form"
                                                id="reply-form-<?= (int) $comment['id'] ?>" style="display:none; margin-top: 10px;">
                                                <?= csrfInput(); ?>
                                                <input type="hidden" name="post_id" value="<?= (int) $postId; ?>">
                                                <input type="hidden" name="parent_id" value="<?= (int) $comment['id']; ?>">
                                                <label for="reply-input-<?= (int) $comment['id']; ?>" class="meta">Responder a
                                                    <?= e((string) $comment['comment_author']); ?></label>
                                                <textarea id="reply-input-<?= (int) $comment['id']; ?>" name="content" rows="2"
                                                    maxlength="1000" required></textarea>
                                                <div class="actions-row">
                                                    <button type="button" class="button-outline reply-cancel-btn"
                                                        data-target="reply-form-<?= (int) $comment['id'] ?>">Cancelar</button>
                                                    <button type="submit">Responder</button>
                                                </div>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
        </main>
        <!-- Comment Delete Modal -->
        <div id="commentDeleteModal" class="modal" aria-hidden="true" role="dialog">
            <div class="modal-content card">
                <h2>Confirmar Exclusão</h2>
                <p>Tem certeza que deseja excluir este comentário? Esta ação não pode ser desfeita.</p>
                <form method="post" id="commentDeleteForm">
                    <?= csrfInput(); ?>
                    <div class="actions-row" style="margin-top: 1.5rem; justify-content: flex-end;">
                        <button type="button" class="button-outline"
                            onclick="closeCommentDeleteModal()">Cancelar</button>
                        <button type="submit" class="button-danger">Excluir Comentário</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Share Popover element -->
        <div id="share-popover" class="share-popover">
            <input type="text" id="share-link-input" readonly>
            <button type="button" id="copy-share-btn" class="button-inline">Copiar</button>
        </div>

        <?= siteFooter(); ?>
    </div>
    <?= pageScripts(); ?>
    <script>
        (function () {
            // Fake input toggle
            const fakeInput = document.getElementById('fake-comment-input');
            const realForm = document.getElementById('real-comment-form');
            const cancelBtn = document.getElementById('cancel-comment-btn');

            if (fakeInput && realForm && cancelBtn) {
                fakeInput.addEventListener('click', () => {
                    fakeInput.style.display = 'none';
                    realForm.style.display = 'block';
                    realForm.querySelector('textarea').focus();
                });
                cancelBtn.addEventListener('click', () => {
                    realForm.style.display = 'none';
                    realForm.querySelector('textarea').value = '';
                    fakeInput.style.display = 'block';
                });
            }

            // Reply toggle
            document.querySelectorAll('.reply-toggle-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const targetId = e.currentTarget.getAttribute('data-target');
                    const form = document.getElementById(targetId);
                    if (form) {
                        form.style.display = form.style.display === 'none' ? 'block' : 'none';
                        if (form.style.display === 'block') {
                            form.querySelector('textarea').focus();
                        }
                    }
                });
            });

            // Reply cancel toggle
            document.querySelectorAll('.reply-cancel-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const targetId = e.currentTarget.getAttribute('data-target');
                    const form = document.getElementById(targetId);
                    if (form) {
                        form.style.display = 'none';
                        form.querySelector('textarea').value = '';
                    }
                });
            });


            const sharePopover = document.getElementById('share-popover');
            const shareInput = document.getElementById('share-link-input');
            const copyBtn = document.getElementById('copy-share-btn');

            document.querySelectorAll('.share-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const commentId = e.currentTarget.getAttribute('data-link');
                    const url = window.location.origin + window.location.pathname + '?id=<?= (int) $postId ?>#comment-' + commentId;

                    shareInput.value = url;

                    const rect = e.currentTarget.getBoundingClientRect();
                    sharePopover.style.left = rect.left + 'px';
                    sharePopover.style.top = (rect.bottom + window.scrollY) + 'px';
                    sharePopover.classList.add('visible');
                });
            });

            copyBtn.addEventListener('click', () => {
                shareInput.select();
                document.execCommand('copy');
                copyBtn.textContent = 'Copiado!';
                setTimeout(() => {
                    copyBtn.textContent = 'Copiar';
                    sharePopover.classList.remove('visible');
                }, 2000);
            });

            document.addEventListener('click', (e) => {
                if (!sharePopover.contains(e.target) && !e.target.classList.contains('share-btn')) {
                    sharePopover.classList.remove('visible');
                }
            });

            // Inline edit toggle
            document.querySelectorAll('.edit-comment-btn').forEach(btn => {
                btn.addEventListener('click', function () {
                    const cid = this.getAttribute('data-comment-id');
                    const text = document.getElementById('comment-text-' + cid);
                    const form = document.getElementById('edit-form-' + cid);
                    if (text && form) {
                        text.style.display = 'none';
                        this.closest('.comment-actions').style.display = 'none';
                        form.style.display = 'block';
                    }
                });
            });

            document.querySelectorAll('.edit-cancel-btn').forEach(btn => {
                btn.addEventListener('click', function () {
                    const cid = this.getAttribute('data-comment-id');
                    const text = document.getElementById('comment-text-' + cid);
                    const form = document.getElementById('edit-form-' + cid);
                    const article = this.closest('article');
                    if (text && form) {
                        form.style.display = 'none';
                        text.style.display = '';
                        const actions = article.querySelector('.comment-actions');
                        if (actions) actions.style.display = '';
                    }
                });
            });
        })();

        // Comment delete modal
        function openCommentDeleteModal(commentId) {
            const modal = document.getElementById('commentDeleteModal');
            const form = document.getElementById('commentDeleteForm');
            form.action = 'comments.php?action=delete&id=' + commentId;
            modal.classList.add('modal-visible');
            modal.setAttribute('aria-hidden', 'false');
        }

        function closeCommentDeleteModal() {
            const modal = document.getElementById('commentDeleteModal');
            modal.classList.remove('modal-visible');
            modal.setAttribute('aria-hidden', 'true');
        }

        document.getElementById('commentDeleteModal').addEventListener('click', function (e) {
            if (e.target === this) closeCommentDeleteModal();
        });
    </script>
</body>

</html>