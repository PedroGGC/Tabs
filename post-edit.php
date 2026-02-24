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

$stmt = $pdo->prepare('SELECT id, title, content, cover_image FROM posts WHERE id = :id AND user_id = :user_id LIMIT 1');
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
$deleteStoredCover = static function (?string $relativePath): void {
    if (!is_string($relativePath) || $relativePath === '') {
        return;
    }

    $coversBase = realpath(__DIR__ . '/public/uploads/covers');
    $fullPath = realpath(__DIR__ . '/' . ltrim($relativePath, '/\\'));
    if ($coversBase === false || $fullPath === false) {
        return;
    }

    if (!str_starts_with($fullPath, $coversBase . DIRECTORY_SEPARATOR)) {
        return;
    }

    if (is_file($fullPath)) {
        @unlink($fullPath);
    }
};

if (isPostRequest()) {
    verifyCsrfOrFail();

    $old['title'] = sanitize($_POST['title'] ?? '');
    $old['content'] = sanitize($_POST['content'] ?? '');
    $removeCover = isset($_POST['remove_cover']) && $_POST['remove_cover'] === '1';
    $newCoverPath = (string) ($post['cover_image'] ?? '');
    $hasCoverUpload = isset($_FILES['cover_image']) && (int) ($_FILES['cover_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

    if ($old['title'] === '') {
        $errors[] = 'O título é obrigatório.';
    }

    if ($old['content'] === '') {
        $errors[] = 'O conteúdo é obrigatório.';
    }

    if ($hasCoverUpload) {
        $file = $_FILES['cover_image'];
        $errorCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($errorCode !== UPLOAD_ERR_OK) {
            $errors[] = 'Falha no upload da imagem de capa.';
        } else {
            $maxSize = 2 * 1024 * 1024;
            $size = (int) ($file['size'] ?? 0);
            if ($size <= 0 || $size > $maxSize) {
                $errors[] = 'A imagem de capa deve ter no máximo 2MB.';
            }

            $originalName = (string) ($file['name'] ?? '');
            $extension = mb_strtolower(pathinfo($originalName, PATHINFO_EXTENSION), 'UTF-8');
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
            if (!in_array($extension, $allowedExtensions, true)) {
                $errors[] = 'Formato inválido para capa. Use jpg, jpeg, png ou webp.';
            }

            $tmpName = (string) ($file['tmp_name'] ?? '');
            if ($tmpName === '' || !is_uploaded_file($tmpName)) {
                $errors[] = 'Arquivo de capa inválido.';
            } else {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = $finfo ? finfo_file($finfo, $tmpName) : false;
                if ($finfo) {
                    finfo_close($finfo);
                }

                $allowedMimeMap = [
                    'image/jpeg' => ['jpg', 'jpeg'],
                    'image/png' => ['png'],
                    'image/webp' => ['webp'],
                ];

                if (!is_string($mimeType) || !isset($allowedMimeMap[$mimeType])) {
                    $errors[] = 'Tipo MIME inválido para imagem de capa.';
                } elseif (!in_array($extension, $allowedMimeMap[$mimeType], true)) {
                    $errors[] = 'A extensão da capa não corresponde ao tipo da imagem.';
                }
            }

            if ($errors === []) {
                $slugForFile = generateSlug($old['title']);
                $coverDir = __DIR__ . '/public/uploads/covers';
                if (!is_dir($coverDir) && !mkdir($coverDir, 0755, true) && !is_dir($coverDir)) {
                    $errors[] = 'Não foi possível preparar diretório de upload da capa.';
                } else {
                    $fileName = $slugForFile . '-' . uniqid() . '.' . $extension;
                    $destination = $coverDir . '/' . $fileName;
                    if (!move_uploaded_file($tmpName, $destination)) {
                        $errors[] = 'Não foi possível salvar a imagem de capa.';
                    } else {
                        $deleteStoredCover((string) ($post['cover_image'] ?? null));
                        $newCoverPath = 'public/uploads/covers/' . $fileName;
                    }
                }
            }
        }
    } elseif ($removeCover) {
        $deleteStoredCover((string) ($post['cover_image'] ?? null));
        $newCoverPath = '';
    }

    if ($errors === []) {
        $slug = generateUniqueSlug($pdo, $old['title'], (int) $post['id']);

        $updateStmt = $pdo->prepare(
            'UPDATE posts
             SET title = :title, slug = :slug, content = :content, cover_image = :cover_image, updated_at = NOW()
             WHERE id = :id AND user_id = :user_id'
        );
        $updateStmt->execute([
            'title' => $old['title'],
            'slug' => $slug,
            'content' => $old['content'],
            'cover_image' => $newCoverPath !== '' ? $newCoverPath : null,
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

            <form method="post" action="post-edit.php?id=<?= (int) $postId; ?>" data-transition="down" enctype="multipart/form-data">
                <?= csrfInput(); ?>

                <label for="title">Título</label>
                <input type="text" id="title" name="title" value="<?= e($old['title']); ?>" required>

                <label for="content">Conteúdo</label>
                <textarea id="content" name="content" rows="10" required><?= e($old['content']); ?></textarea>

                <?php if (!empty($post['cover_image'])): ?>
                    <p class="meta">Imagem de capa atual</p>
                    <img src="<?= e((string) $post['cover_image']); ?>" alt="Imagem de capa atual" class="cover-preview">
                    <label class="inline-check" for="remove_cover">
                        <input type="checkbox" id="remove_cover" name="remove_cover" value="1">
                        Remover imagem atual
                    </label>
                <?php endif; ?>

                <label for="cover_image">Nova imagem de capa (opcional, jpg/jpeg/png/webp até 2MB)</label>
                <input type="file" id="cover_image" name="cover_image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">

                <button type="submit">Salvar alterações</button>
            </form>
        </section>
    </main>
    </div>
    <script src="public/js/transitions.js"></script>
</body>
</html>
