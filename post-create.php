<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

requireLogin();

$pdo = getPDO();
$errors = [];
$old = [
    'title' => '',
    'content' => '',
];

if (isPostRequest()) {
    $old['title'] = sanitize($_POST['title'] ?? '');
    $old['content'] = sanitize($_POST['content'] ?? '');

    if ($old['title'] === '') {
        $errors[] = 'O título é obrigatório.';
    }

    if ($old['content'] === '') {
        $errors[] = 'O conteúdo é obrigatório.';
    }

    if ($errors === []) {
        $slug = generateUniqueSlug($pdo, $old['title']);

        $stmt = $pdo->prepare(
            'INSERT INTO posts (user_id, title, slug, content, created_at, updated_at)
             VALUES (:user_id, :title, :slug, :content, NOW(), NOW())'
        );
        $stmt->execute([
            'user_id' => currentUserId(),
            'title' => $old['title'],
            'slug' => $slug,
            'content' => $old['content'],
        ]);

        setFlash('success', 'Post criado com sucesso.');
        redirect('dashboard.php');
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Novo post</title>
    <link rel="stylesheet" href="public/css/style.css">
</head>
<body>
    <header class="site-header">
        <div class="container nav">
            <a class="brand" href="index.php">Blog PHP</a>
            <nav>
                <a href="dashboard.php">Dashboard</a>
                <a href="post-create.php">Novo post</a>
                <a href="logout.php">Sair</a>
            </nav>
        </div>
    </header>

    <main class="container">
        <section class="card form-card">
            <h1>Criar post</h1>

            <?php if ($errors !== []): ?>
                <div class="alert alert-error">
                    <?php foreach ($errors as $error): ?>
                        <p><?= e($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="post" action="post-create.php">
                <label for="title">Título</label>
                <input type="text" id="title" name="title" value="<?= e($old['title']); ?>" required>

                <label for="content">Conteúdo</label>
                <textarea id="content" name="content" rows="10" required><?= e($old['content']); ?></textarea>

                <button type="submit">Publicar</button>
            </form>
        </section>
    </main>
</body>
</html>
