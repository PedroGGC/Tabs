<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

requireLogin();

$pdo = getPDO();
$userId = currentUserId();
$postId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if ($postId === false || $postId === null) {
    setFlash('error', 'Post inválido.');
    redirect('dashboard.php');
}

$stmt = $pdo->prepare('SELECT id, title, content FROM posts WHERE id = :id AND user_id = :user_id LIMIT 1');
$stmt->execute([
    'id' => $postId,
    'user_id' => $userId,
]);
$post = $stmt->fetch();

if (!$post) {
    setFlash('error', 'Post não encontrado ou sem permissão para editar.');
    redirect('dashboard.php');
}

$errors = [];
$old = [
    'title' => (string) $post['title'],
    'content' => (string) $post['content'],
];

if (isPostRequest()) {
    verifyCsrfOrFail();

    $old['title'] = sanitize($_POST['title'] ?? '');
    $old['content'] = sanitize($_POST['content'] ?? '');

    if ($old['title'] === '') {
        $errors[] = 'O título é obrigatório.';
    }

    if ($old['content'] === '') {
        $errors[] = 'O conteúdo é obrigatório.';
    }

    if ($errors === []) {
        $slug = generateUniqueSlug($pdo, $old['title'], (int) $post['id']);

        $updateStmt = $pdo->prepare(
            'UPDATE posts
             SET title = :title, slug = :slug, content = :content, updated_at = NOW()
             WHERE id = :id AND user_id = :user_id'
        );
        $updateStmt->execute([
            'title' => $old['title'],
            'slug' => $slug,
            'content' => $old['content'],
            'id' => $postId,
            'user_id' => $userId,
        ]);

        setFlash('success', 'Post atualizado com sucesso.');
        redirect('dashboard.php');
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar post</title>
    <link rel="stylesheet" href="public/css/style.css">
</head>
<body>
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

            <form method="post" action="post-edit.php?id=<?= (int) $postId; ?>" data-transition="down">
                <?= csrfInput(); ?>

                <label for="title">Título</label>
                <input type="text" id="title" name="title" value="<?= e($old['title']); ?>" required>

                <label for="content">Conteúdo</label>
                <textarea id="content" name="content" rows="10" required><?= e($old['content']); ?></textarea>

                <button type="submit">Salvar alterações</button>
            </form>
        </section>
    </main>
    </div>
    <script src="public/js/transitions.js"></script>
</body>
</html>
