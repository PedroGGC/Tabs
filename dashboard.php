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

// perf: paginated dashboard query to reduce scanned and rendered rows
$postsPerPage = 10;
$currentPage = filter_input(
    INPUT_GET,
    'page',
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);
$currentPage = $currentPage === false || $currentPage === null ? 1 : $currentPage;

$countStmt = $pdo->prepare('SELECT COUNT(*) FROM posts WHERE user_id = :user_id');
$countStmt->execute(['user_id' => $userId]);
$totalPosts = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalPosts / $postsPerPage));

if ($currentPage > $totalPages) {
    $currentPage = $totalPages;
}

$offset = ($currentPage - 1) * $postsPerPage;

$stmt = $pdo->prepare(
    'SELECT id, title, created_at, updated_at
     FROM posts
     WHERE user_id = :user_id
     ORDER BY created_at DESC
     LIMIT :limit OFFSET :offset'
);
$stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
$stmt->bindValue(':limit', $postsPerPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$posts = $stmt->fetchAll();

$buildPageUrl = static function (int $page): string {
    $params = $_GET;
    $params['page'] = $page;
    return 'dashboard.php?' . http_build_query($params);
};

$hasPreviousPage = $currentPage > 1;
$hasNextPage = $currentPage < $totalPages;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href="public/css/style.css">
</head>
<body<?= $dashboardTransitionClass !== '' ? ' class="' . $dashboardTransitionClass . '"' : ''; ?>>
    <div id="page">
    <header class="site-header">
        <div class="container nav">
            <a class="brand" href="index.php">Blog PHP</a>
            <nav>
                <a href="dashboard.php">Dashboard</a>
                <a href="logout.php" data-transition="back">Sair</a>
            </nav>
        </div>
    </header>

    <main class="container page-shell">
        <section class="page-title">
            <div>
                <h1>Seus posts, <?= e((string) ($_SESSION['username'] ?? '')); ?></h1>
                <p class="meta">Gerencie conteúdos publicados e atualize quando precisar.</p>
            </div>
            <a class="button-inline" href="post-create.php">Criar</a>
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
                                    <a class="action-link" href="post-edit.php?id=<?= (int) $post['id']; ?>" data-transition="up">Editar</a>
                                    <button
                                        type="button"
                                        class="action-link danger-link js-delete-trigger"
                                        data-id="<?= (int) $post['id']; ?>"
                                        data-title="<?= e($post['title']); ?>"
                                    >
                                        Excluir
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($hasPreviousPage || $hasNextPage): ?>
                <nav class="pagination" aria-label="Paginação dos seus posts">
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

    <div id="delete-modal" class="modal-overlay" hidden>
        <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="delete-modal-title">
            <h2 id="delete-modal-title">Confirmar exclusão</h2>
            <p class="modal-message">
                Tem certeza que deseja excluir o post <strong id="delete-post-title"></strong>?
            </p>
            <form id="delete-modal-form" method="post" action="post-delete.php" data-no-transition="true">
                <?= csrfInput(); ?>
                <input type="hidden" name="post_id" id="delete-post-id" value="">
                <input type="hidden" name="confirm" value="yes">
                <div class="actions-row">
                    <button type="button" class="secondary js-modal-cancel">Cancelar</button>
                    <button type="submit" class="danger">Excluir</button>
                </div>
            </form>
        </div>
    </div>
    </div>
    <?php if ($enteredFromLogin): ?>
        <script>
        if (window.history && window.history.replaceState) {
            window.history.replaceState({}, document.title, 'dashboard.php');
        }
        </script>
    <?php endif; ?>
    <script>
    (function () {
        const modal = document.getElementById('delete-modal');
        const form = document.getElementById('delete-modal-form');
        const postIdInput = document.getElementById('delete-post-id');
        const postTitle = document.getElementById('delete-post-title');
        const cancelButton = modal ? modal.querySelector('.js-modal-cancel') : null;
        const triggers = document.querySelectorAll('.js-delete-trigger');

        if (!modal || !form || !postIdInput || !postTitle || !cancelButton) {
            return;
        }

        const closeModal = function () {
            modal.classList.remove('is-open');
            window.setTimeout(function () {
                modal.hidden = true;
            }, 180);
        };

        const openModal = function (id, title) {
            postIdInput.value = id;
            postTitle.textContent = title;
            form.action = 'post-delete.php?id=' + encodeURIComponent(id);
            modal.hidden = false;
            window.requestAnimationFrame(function () {
                modal.classList.add('is-open');
            });
        };

        triggers.forEach(function (button) {
            button.addEventListener('click', function () {
                const id = button.getAttribute('data-id') || '';
                const title = button.getAttribute('data-title') || '';
                if (!id) {
                    return;
                }
                openModal(id, title);
            });
        });

        cancelButton.addEventListener('click', closeModal);

        modal.addEventListener('click', function (event) {
            if (event.target === modal) {
                closeModal();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !modal.hidden) {
                closeModal();
            }
        });
    })();
    </script>
    <script src="public/js/transitions.js"></script>
</body>
</html>
