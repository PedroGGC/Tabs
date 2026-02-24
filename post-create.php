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
        $slug = generateUniqueSlug($pdo, $old['title']);
        $coverImagePath = null;

        if (isset($_FILES['cover_image']) && (int) ($_FILES['cover_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
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
                    $coverDir = __DIR__ . '/public/uploads/covers';
                    if (!is_dir($coverDir) && !mkdir($coverDir, 0755, true) && !is_dir($coverDir)) {
                        $errors[] = 'Não foi possível preparar diretório de upload da capa.';
                    } else {
                        $fileName = $slug . '-' . uniqid() . '.' . $extension;
                        $destination = $coverDir . '/' . $fileName;
                        if (!move_uploaded_file($tmpName, $destination)) {
                            $errors[] = 'Não foi possível salvar a imagem de capa.';
                        } else {
                            $coverImagePath = 'public/uploads/covers/' . $fileName;
                        }
                    }
                }
            }
        }
    }

    if ($errors === []) {
        $stmt = $pdo->prepare(
            'INSERT INTO posts (user_id, title, slug, content, cover_image, created_at, updated_at)
             VALUES (:user_id, :title, :slug, :content, :cover_image, NOW(), NOW())'
        );
        $stmt->execute([
            'user_id' => currentUserId(),
            'title' => $old['title'],
            'slug' => $slug,
            'content' => $old['content'],
            'cover_image' => $coverImagePath,
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
    <title>Criar post</title>
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
            <h1>Criar post</h1>
            <p class="meta form-intro">Escreva seu conteúdo e publique para a comunidade.</p>

            <?php if ($errors !== []): ?>
                <div class="alert alert-error">
                    <?php foreach ($errors as $error): ?>
                        <p><?= e($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="post" action="post-create.php" enctype="multipart/form-data">
                <?= csrfInput(); ?>

                <label for="title">Título</label>
                <input type="text" id="title" name="title" value="<?= e($old['title']); ?>" required>

                <label for="content">Conteúdo</label>
                <textarea id="content" name="content" rows="10" required><?= e($old['content']); ?></textarea>

                <label for="cover_image">Imagem de capa (opcional, jpg/jpeg/png/webp até 2MB)</label>
                <input type="file" id="cover_image" name="cover_image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">

                <button type="submit">Publicar</button>
            </form>
        </section>
    </main>
    </div>
    <script src="public/js/transitions.js"></script>
</body>
</html>
