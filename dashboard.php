<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

requireLogin();

$pdo = getPDO();
$userId = currentUserId();
$flash = getFlash();

$viewerStmt = $pdo->prepare('SELECT id, username, avatar FROM users WHERE id = :id LIMIT 1');
$viewerStmt->execute(['id' => $userId]);
$viewer = $viewerStmt->fetch();

if (!$viewer) {
    setFlash('error', 'Usuário não encontrado.');
    redirect('logout.php');
}

$unreadNotificationsStmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND is_read = 0');
$unreadNotificationsStmt->execute(['user_id' => $userId]);
$unreadNotifications = (int) $unreadNotificationsStmt->fetchColumn();

$notificationsStmt = $pdo->prepare(
    'SELECT
        notifications.id,
        notifications.type,
        notifications.comment_id,
        notifications.post_id,
        notifications.is_read,
        notifications.created_at,
        from_users.username AS from_username,
        from_users.avatar AS from_avatar,
        posts.title AS post_title
     FROM notifications
     INNER JOIN users AS from_users ON from_users.id = notifications.from_user_id
     INNER JOIN posts ON posts.id = notifications.post_id
     WHERE notifications.user_id = :user_id
     ORDER BY notifications.created_at DESC
     LIMIT 10'
);
$notificationsStmt->execute(['user_id' => $userId]);
$notifications = $notificationsStmt->fetchAll();

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

$postsStmt = $pdo->prepare(
    'SELECT id, title, created_at, updated_at
     FROM posts
     WHERE user_id = :user_id
     ORDER BY created_at DESC
     LIMIT :limit OFFSET :offset'
);
$postsStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
$postsStmt->bindValue(':limit', $postsPerPage, PDO::PARAM_INT);
$postsStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$postsStmt->execute();
$posts = $postsStmt->fetchAll();

$buildPageUrl = static function (int $page): string {
    $params = $_GET;
    $params['page'] = $page;
    return 'dashboard.php?' . http_build_query($params);
};

$hasPreviousPage = $currentPage > 1;
$hasNextPage = $currentPage < $totalPages;
$notificationsCsrf = csrfToken();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Threadly | Dashboard</title>
    <link rel="stylesheet" href="public/css/style.css">
</head>
<body>
    <div id="page">
    <header class="site-header">
        <div class="container nav">
            <a class="brand" href="index.php">Threadly</a>
            <nav class="dashboard-nav">
                <a href="index.php">Explorar</a>
                <a href="dashboard.php">Dashboard</a>

                <div class="dropdown-wrap" id="notifications-wrap">
                    <button
                        type="button"
                        class="icon-button"
                        id="notifications-toggle"
                        aria-haspopup="true"
                        aria-expanded="false"
                        data-csrf="<?= e($notificationsCsrf); ?>"
                    >
                        <span class="icon-bell" aria-hidden="true">🔔</span>
                        <?php if ($unreadNotifications > 0): ?>
                            <span class="notification-badge" id="notifications-badge"><?= (int) $unreadNotifications; ?></span>
                        <?php endif; ?>
                    </button>
                    <div class="dropdown-menu dropdown-menu-wide" id="notifications-menu" hidden>
                        <p class="dropdown-title">Notificações</p>
                        <?php if ($notifications === []): ?>
                            <p class="meta">Sem notificações recentes.</p>
                        <?php else: ?>
                            <ul class="notification-list">
                                <?php foreach ($notifications as $notification): ?>
                                    <?php
                                    $notificationType = (string) $notification['type'];
                                    $isUnread = (int) $notification['is_read'] === 0;
                                    $notificationText = $notificationType === 'reply'
                                        ? e((string) $notification['from_username']) . ' respondeu seu comentário em "' . e((string) $notification['post_title']) . '"'
                                        : e((string) $notification['from_username']) . ' mencionou você em "' . e((string) $notification['post_title']) . '"';
                                    ?>
                                    <li class="notification-item<?= $isUnread ? ' notification-unread' : ''; ?>">
                                        <a href="post.php?id=<?= (int) $notification['post_id']; ?>#comment-<?= (int) $notification['comment_id']; ?>">
                                            <span class="author-row">
                                                <?php if (!empty($notification['from_avatar'])): ?>
                                                    <img class="avatar avatar-sm" src="<?= e((string) $notification['from_avatar']); ?>" alt="Avatar de <?= e((string) $notification['from_username']); ?>">
                                                <?php else: ?>
                                                    <span class="avatar avatar-sm avatar-fallback"><?= e(usernameInitial((string) $notification['from_username'])); ?></span>
                                                <?php endif; ?>
                                                <span><?= $notificationText; ?></span>
                                            </span>
                                            <span class="meta"><?= e(formatDate((string) $notification['created_at'])); ?></span>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="dropdown-wrap" id="user-menu-wrap">
                    <button type="button" class="user-menu-trigger" id="user-menu-toggle" aria-haspopup="true" aria-expanded="false">
                        <?php if (!empty($viewer['avatar'])): ?>
                            <img class="avatar avatar-sm" src="<?= e((string) $viewer['avatar']); ?>" alt="Avatar de <?= e((string) $viewer['username']); ?>">
                        <?php else: ?>
                            <span class="avatar avatar-sm avatar-fallback"><?= e(usernameInitial((string) $viewer['username'])); ?></span>
                        <?php endif; ?>
                    </button>
                    <div class="dropdown-menu" id="user-menu" hidden>
                        <a href="user.php?id=<?= (int) $viewer['id']; ?>">Ver Perfil</a>
                        <a href="profile.php">Settings</a>
                        <a href="logout.php" data-transition="back">Sair</a>
                    </div>
                </div>
            </nav>
        </div>
    </header>

    <main class="container page-shell">
        <section class="page-title">
            <div>
                <div class="author-row author-row-strong">
                    <?php if (!empty($viewer['avatar'])): ?>
                        <img class="avatar avatar-sm" src="<?= e((string) $viewer['avatar']); ?>" alt="Avatar de <?= e((string) $viewer['username']); ?>">
                    <?php else: ?>
                        <span class="avatar avatar-sm avatar-fallback"><?= e(usernameInitial((string) $viewer['username'])); ?></span>
                    <?php endif; ?>
                    <p class="meta">Olá, <strong><?= e((string) $viewer['username']); ?></strong></p>
                </div>
                <h1>Meus Posts</h1>
                <p class="meta">Gerencie conteúdos publicados e atualize quando precisar.</p>
            </div>
            <a class="button-inline" href="post-create.php">Novo Post</a>
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
                                    <a href="post.php?id=<?= (int) $post['id']; ?>"><?= e((string) $post['title']); ?></a>
                                </td>
                                <td><?= e(formatDate((string) $post['created_at'])); ?></td>
                                <td><?= e(formatDate((string) $post['updated_at'])); ?></td>
                                <td class="actions">
                                    <a class="action-link" href="post-edit.php?id=<?= (int) $post['id']; ?>" data-transition="up">Editar</a>
                                    <button
                                        type="button"
                                        class="action-link danger-link js-delete-trigger"
                                        data-id="<?= (int) $post['id']; ?>"
                                        data-title="<?= e((string) $post['title']); ?>"
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
    <script>
    (function () {
        const modal = document.getElementById('delete-modal');
        const form = document.getElementById('delete-modal-form');
        const postIdInput = document.getElementById('delete-post-id');
        const postTitle = document.getElementById('delete-post-title');
        const cancelButton = modal ? modal.querySelector('.js-modal-cancel') : null;
        const deleteTriggers = document.querySelectorAll('.js-delete-trigger');

        const notificationsWrap = document.getElementById('notifications-wrap');
        const notificationsToggle = document.getElementById('notifications-toggle');
        const notificationsMenu = document.getElementById('notifications-menu');
        const notificationsBadge = document.getElementById('notifications-badge');

        const userMenuWrap = document.getElementById('user-menu-wrap');
        const userMenuToggle = document.getElementById('user-menu-toggle');
        const userMenu = document.getElementById('user-menu');

        let notificationsMarkedAsRead = false;

        const closeDeleteModal = function () {
            if (!modal) {
                return;
            }
            modal.classList.remove('is-open');
            window.setTimeout(function () {
                modal.hidden = true;
            }, 180);
        };

        if (modal && form && postIdInput && postTitle && cancelButton) {
            const openDeleteModal = function (id, title) {
                postIdInput.value = id;
                postTitle.textContent = title;
                form.action = 'post-delete.php?id=' + encodeURIComponent(id);
                modal.hidden = false;
                window.requestAnimationFrame(function () {
                    modal.classList.add('is-open');
                });
            };

            deleteTriggers.forEach(function (button) {
                button.addEventListener('click', function () {
                    const id = button.getAttribute('data-id') || '';
                    const title = button.getAttribute('data-title') || '';
                    if (!id) {
                        return;
                    }
                    openDeleteModal(id, title);
                });
            });

            cancelButton.addEventListener('click', closeDeleteModal);
            modal.addEventListener('click', function (event) {
                if (event.target === modal) {
                    closeDeleteModal();
                }
            });
        }

        const openDropdown = function (trigger, menu) {
            if (!trigger || !menu) {
                return;
            }
            menu.hidden = false;
            menu.classList.add('is-open');
            trigger.setAttribute('aria-expanded', 'true');
        };

        const closeDropdown = function (trigger, menu) {
            if (!trigger || !menu) {
                return;
            }
            menu.classList.remove('is-open');
            trigger.setAttribute('aria-expanded', 'false');
            window.setTimeout(function () {
                if (trigger.getAttribute('aria-expanded') === 'false') {
                    menu.hidden = true;
                }
            }, 180);
        };

        const toggleDropdown = function (trigger, menu) {
            if (!trigger || !menu) {
                return;
            }
            const isOpen = trigger.getAttribute('aria-expanded') === 'true';
            if (isOpen) {
                closeDropdown(trigger, menu);
            } else {
                openDropdown(trigger, menu);
            }
        };

        const markNotificationsAsRead = function () {
            if (!notificationsToggle || notificationsMarkedAsRead) {
                return;
            }

            const csrf = notificationsToggle.getAttribute('data-csrf') || '';
            if (!csrf) {
                return;
            }

            const payload = '_csrf=' + encodeURIComponent(csrf);

            fetch('notifications-read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                },
                body: payload,
                credentials: 'same-origin'
            })
            .then(function (response) {
                return response.json();
            })
            .then(function (data) {
                if (!data || data.success !== true) {
                    return;
                }

                notificationsMarkedAsRead = true;
                if (notificationsBadge) {
                    notificationsBadge.remove();
                }

                const unreadItems = document.querySelectorAll('.notification-unread');
                unreadItems.forEach(function (item) {
                    item.classList.remove('notification-unread');
                });
            })
            .catch(function () {
                /* silent fail */
            });
        };

        if (notificationsToggle && notificationsMenu) {
            notificationsToggle.addEventListener('click', function () {
                const willOpen = notificationsToggle.getAttribute('aria-expanded') !== 'true';
                toggleDropdown(notificationsToggle, notificationsMenu);
                if (willOpen) {
                    markNotificationsAsRead();
                }
            });
        }

        if (userMenuToggle && userMenu) {
            userMenuToggle.addEventListener('click', function () {
                toggleDropdown(userMenuToggle, userMenu);
            });
        }

        document.addEventListener('click', function (event) {
            if (
                notificationsWrap &&
                notificationsToggle &&
                notificationsMenu &&
                !notificationsWrap.contains(event.target)
            ) {
                closeDropdown(notificationsToggle, notificationsMenu);
            }

            if (
                userMenuWrap &&
                userMenuToggle &&
                userMenu &&
                !userMenuWrap.contains(event.target)
            ) {
                closeDropdown(userMenuToggle, userMenu);
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeDeleteModal();
                if (notificationsToggle && notificationsMenu) {
                    closeDropdown(notificationsToggle, notificationsMenu);
                }
                if (userMenuToggle && userMenu) {
                    closeDropdown(userMenuToggle, userMenu);
                }
            }
        });
    })();
    </script>
    <script src="public/js/transitions.js"></script>
</body>
</html>
