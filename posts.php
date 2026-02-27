<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/post-functions.php';
require_once __DIR__ . '/includes/layout.php';

requireLogin();

$pdo = getPDO();
$userId = currentUserId();

if ($userId === null) {
    setFlash('error', 'Usuário inválido.');
    redirect('login.php');
}

$actionParam = (string) ($_GET['action'] ?? '');
$action = match ($actionParam) {
    'create' => 'create',
    'edit' => 'edit',
    'delete' => 'delete',
    default => null,
};

if ($action === null) {
    redirect('user.php?id=' . $userId);
}

$errors = [];
$old = [
    'title' => '',
    'content' => '',
];
$post = null;
$postId = null;

if ($action === 'create') {
    if (isPostRequest()) {
        verifyCsrfOrFail();
        $result = postCreate($pdo, $userId, $_POST, $_FILES);
        $errors = $result['errors'];
        $old = $result['old'];

        if ($result['success']) {
            setFlash('success', 'Post criado com sucesso.');
            redirect('user.php?id=' . $userId);
        }
    }
} elseif ($action === 'edit') {
    $postId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if ($postId === false || $postId === null) {
        setFlash('error', 'Post inválido.');
        redirect('user.php?id=' . $userId);
    }

    $post = postFindOwned($pdo, $postId, $userId);
    if ($post === null) {
        setFlash('error', 'Post não encontrado ou sem permissão para editar.');
        redirect('user.php?id=' . $userId);
    }

    $old = [
        'title' => (string) $post['title'],
        'content' => (string) $post['content'],
    ];

    if (isPostRequest()) {
        verifyCsrfOrFail();
        $result = postUpdate($pdo, $postId, $userId, $_POST, $_FILES);
        $errors = $result['errors'];
        $old = $result['old'];
        $post = $result['post'];

        if ($result['success']) {
            setFlash('success', 'Post atualizado com sucesso.');
            redirect('user.php?id=' . $userId);
        }

        if ($post === null) {
            setFlash('error', 'Post não encontrado ou sem permissão para editar.');
            redirect('user.php?id=' . $userId);
        }
    }
} else {
    $postId = filter_input(INPUT_POST, 'post_id', FILTER_VALIDATE_INT);
    if ($postId === false || $postId === null) {
        $postId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    }

    if ($postId === false || $postId === null) {
        setFlash('error', 'Post inválido.');
        redirect('user.php?id=' . $userId);
    }

    $post = postFindOwned($pdo, $postId, $userId);
    if ($post === null) {
        setFlash('error', 'Post não encontrado ou sem permissão para excluir.');
        redirect('user.php?id=' . $userId);
    }

    if (isPostRequest()) {
        verifyCsrfOrFail();
        $confirm = (string) ($_POST['confirm'] ?? 'no');

        if ($confirm === 'yes') {
            if (postDelete($pdo, $postId, $userId)) {
                setFlash('success', 'Post excluído com sucesso.');
            } else {
                setFlash('error', 'Post não encontrado ou sem permissão para excluir.');
            }
        } else {
            setFlash('error', 'Exclusão cancelada.');
        }

        redirect('user.php?id=' . $userId);
    }
}

$title = match ($action) {
    'create' => 'Tabs | Criar post',
    'edit' => 'Tabs | Editar post',
    'delete' => 'Tabs | Excluir post',
};
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <?= headTags($title); ?>
</head>

<body>
    <div id="page">
        <header class="site-header">
            <div class="container nav">
                <a class="brand" href="index.php">Tabs</a>
                <nav>
                    <?= themeToggle(); ?>
                    <a href="user.php?id=<?= $userId; ?>" data-transition="up">Meu Perfil</a>
                    <?php if ($action === 'delete'): ?>
                        <a href="posts.php?action=create">Criar</a>
                    <?php endif; ?>
                    <a href="logout.php" data-transition="back">Sair</a>
                </nav>
            </div>
        </header>

        <main class="container page-shell">
            <?php if ($action === 'create'): ?>
                <section class="card form-card">
                    <h1>Criar post</h1>
                    <p class="meta form-intro">Escreva seu conteúdo e publique para a comunidade.</p>

                    <?php if ($errors !== []): ?>
                        <div class="alert alert-error">
                            <?php foreach ($errors as $error): ?>
                                <p><?= e($error); ?></p>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <form method="post" action="posts.php?action=create" enctype="multipart/form-data">
                        <?= csrfInput(); ?>

                        <label for="title">Título</label>
                        <input type="text" id="title" name="title" value="<?= e($old['title']); ?>" required>

                        <label for="content">Conteúdo</label>
                        <textarea id="content" name="content" rows="10" required><?= e($old['content']); ?></textarea>

                        <label for="cover_image">Imagem de capa (opcional, jpg/jpeg/png/webp até 2MB)</label>
                        <input type="file" id="cover_image" name="cover_image"
                            accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">

                        <button type="submit">Publicar</button>
                    </form>
                </section>
            <?php elseif ($action === 'edit'): ?>
                <section class="card form-card">
                    <h1>Editar post</h1>
                    <p class="meta form-intro">Ajuste seu conteúdo e salve as alterações.</p>

                    <?php if ($errors !== []): ?>
                        <div class="alert alert-error">
                            <?php foreach ($errors as $error): ?>
                                <p><?= e($error); ?></p>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <form method="post" action="posts.php?action=edit&id=<?= (int) $postId; ?>" data-transition="down"
                        enctype="multipart/form-data">
                        <?= csrfInput(); ?>

                        <label for="title">Título</label>
                        <input type="text" id="title" name="title" value="<?= e($old['title']); ?>" required>

                        <label for="content">Conteúdo</label>
                        <textarea id="content" name="content" rows="10" required><?= e($old['content']); ?></textarea>

                        <?php if ($post !== null && !empty($post['cover_image'])): ?>
                            <p class="meta">Imagem de capa atual</p>
                            <img src="<?= e((string) $post['cover_image']); ?>" alt="Imagem de capa atual" class="cover-preview"
                                loading="lazy">
                            <label class="inline-check" for="remove_cover">
                                <input type="checkbox" id="remove_cover" name="remove_cover" value="1">
                                Remover imagem atual
                            </label>
                        <?php endif; ?>

                        <label for="cover_image">Nova imagem de capa (opcional, jpg/jpeg/png/webp até 2MB)</label>
                        <input type="file" id="cover_image" name="cover_image"
                            accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">

                        <button type="submit">Salvar alterações</button>
                    </form>
                </section>
            <?php else: ?>
                <section class="card form-card">
                    <h1>Confirmar exclusão</h1>
                    <p>Tem certeza que deseja excluir o post <strong><?= e((string) $post['title']); ?></strong>?</p>

                    <form method="post" action="posts.php?action=delete&id=<?= (int) $postId; ?>" class="actions-row">
                        <?= csrfInput(); ?>
                        <input type="hidden" name="post_id" value="<?= (int) $postId; ?>">
                        <button type="submit" name="confirm" value="yes" class="danger">Sim, excluir</button>
                        <button type="submit" name="confirm" value="no" class="secondary">Cancelar</button>
                    </form>
                </section>
            <?php endif; ?>
        </main>
        <?= siteFooter(); ?>
    </div>
    <?= pageScripts(); ?>
</body>

</html>