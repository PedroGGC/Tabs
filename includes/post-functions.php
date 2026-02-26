<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

// CREATE
/**
 * Busca um post que pertence ao usuário informado.
 *
 * @param PDO $pdo Conexão com banco de dados.
 * @param int $postId ID do post.
 * @param int $userId ID do dono esperado.
 *
 * @return array<string, mixed>|null Retorna os dados do post ou null quando não encontrado.
 */
function postFindOwned(PDO $pdo, int $postId, int $userId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT id, title, content, cover_image
         FROM posts
         WHERE id = :id AND user_id = :user_id
         LIMIT 1'
    );
    $stmt->execute([
        'id' => $postId,
        'user_id' => $userId,
    ]);

    $post = $stmt->fetch();
    return $post ?: null;
}

/**
 * Cria um novo post com validação de campos e upload opcional de capa.
 *
 * @param PDO $pdo Conexão com banco de dados.
 * @param int $userId Usuário autor do post.
 * @param array<string, mixed> $input Dados do formulário.
 * @param array<string, mixed> $files Dados de upload ($_FILES).
 *
 * @return array{success: bool, errors: list<string>, old: array{title: string, content: string}, post_id: int|null}
 */
function postCreate(PDO $pdo, int $userId, array $input, array $files): array
{
    $old = [
        'title' => sanitize((string) ($input['title'] ?? '')),
        'content' => sanitize((string) ($input['content'] ?? '')),
    ];

    $errors = postValidatePostFields($old);
    $slug = '';
    $coverImagePath = null;

    if ($errors === []) {
        $slug = generateUniqueSlug($pdo, $old['title']);

        if (postHasCoverUpload($files)) {
            $upload = postUploadCoverImage((array) $files['cover_image'], $slug);
            if ($upload['errors'] !== []) {
                $errors = $upload['errors'];
            } else {
                $coverImagePath = $upload['path'];
            }
        }
    }

    if ($errors !== []) {
        return [
            'success' => false,
            'errors' => $errors,
            'old' => $old,
            'post_id' => null,
        ];
    }

    $stmt = $pdo->prepare(
        'INSERT INTO posts (user_id, title, slug, content, cover_image, created_at, updated_at)
         VALUES (:user_id, :title, :slug, :content, :cover_image, NOW(), NOW())'
    );
    $stmt->execute([
        'user_id' => $userId,
        'title' => $old['title'],
        'slug' => $slug,
        'content' => $old['content'],
        'cover_image' => $coverImagePath,
    ]);

    return [
        'success' => true,
        'errors' => [],
        'old' => $old,
        'post_id' => (int) $pdo->lastInsertId(),
    ];
}

// EDIT
/**
 * Atualiza um post existente com validações, troca/remoção de capa e atualização de slug.
 *
 * @param PDO $pdo Conexão com banco de dados.
 * @param int $postId ID do post.
 * @param int $userId ID do dono do post.
 * @param array<string, mixed> $input Dados do formulário.
 * @param array<string, mixed> $files Dados de upload ($_FILES).
 *
 * @return array{
 *   success: bool,
 *   errors: list<string>,
 *   old: array{title: string, content: string},
 *   post: array<string, mixed>|null
 * }
 */
function postUpdate(PDO $pdo, int $postId, int $userId, array $input, array $files): array
{
    $post = postFindOwned($pdo, $postId, $userId);
    $old = [
        'title' => sanitize((string) ($input['title'] ?? '')),
        'content' => sanitize((string) ($input['content'] ?? '')),
    ];

    if ($post === null) {
        return [
            'success' => false,
            'errors' => ['Post não encontrado ou sem permissão para editar.'],
            'old' => $old,
            'post' => null,
        ];
    }

    $errors = postValidatePostFields($old);
    $removeCover = isset($input['remove_cover']) && (string) $input['remove_cover'] === '1';
    $newCoverPath = (string) ($post['cover_image'] ?? '');

    if ($errors === [] && postHasCoverUpload($files)) {
        $slugForFile = generateSlug($old['title'] === '' ? (string) $post['title'] : $old['title']);
        $upload = postUploadCoverImage((array) $files['cover_image'], $slugForFile);

        if ($upload['errors'] !== []) {
            $errors = array_merge($errors, $upload['errors']);
        } else {
            postDeleteStoredCover((string) ($post['cover_image'] ?? null));
            $newCoverPath = (string) $upload['path'];
        }
    } elseif ($errors === [] && $removeCover) {
        postDeleteStoredCover((string) ($post['cover_image'] ?? null));
        $newCoverPath = '';
    }

    if ($errors !== []) {
        return [
            'success' => false,
            'errors' => $errors,
            'old' => $old,
            'post' => $post,
        ];
    }

    $slug = generateUniqueSlug($pdo, $old['title'], $postId);
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

    return [
        'success' => true,
        'errors' => [],
        'old' => $old,
        'post' => postFindOwned($pdo, $postId, $userId),
    ];
}

// DELETE
/**
 * Exclui um post do usuário dono.
 *
 * @param PDO $pdo Conexão com banco de dados.
 * @param int $postId ID do post.
 * @param int $userId ID do dono.
 *
 * @return bool True quando algum registro foi removido.
 */
function postDelete(PDO $pdo, int $postId, int $userId): bool
{
    $deleteStmt = $pdo->prepare('DELETE FROM posts WHERE id = :id AND user_id = :user_id');
    $deleteStmt->execute([
        'id' => $postId,
        'user_id' => $userId,
    ]);

    return $deleteStmt->rowCount() > 0;
}

// HELPERS
/**
 * Valida os campos obrigatórios de post.
 *
 * @param array{title: string, content: string} $old Valores sanitizados do formulário.
 *
 * @return list<string> Lista de mensagens de erro.
 */
function postValidatePostFields(array $old): array
{
    $errors = [];

    if ($old['title'] === '') {
        $errors[] = 'O título é obrigatório.';
    }

    if ($old['content'] === '') {
        $errors[] = 'O conteúdo é obrigatório.';
    }

    return $errors;
}

/**
 * Informa se existe upload de capa enviado.
 *
 * @param array<string, mixed> $files Dados de upload ($_FILES).
 *
 * @return bool True quando o campo cover_image contém arquivo enviado.
 */
function postHasCoverUpload(array $files): bool
{
    if (!isset($files['cover_image']) || !is_array($files['cover_image'])) {
        return false;
    }

    return (int) ($files['cover_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
}

/**
 * Valida e move o upload de imagem de capa para o diretório público.
 *
 * @param array<string, mixed> $file Dados do arquivo em $_FILES['cover_image'].
 * @param string $slugForFile Base do nome de arquivo.
 *
 * @return array{path: string|null, errors: list<string>}
 */
function postUploadCoverImage(array $file, string $slugForFile): array
{
    $errors = [];
    $errorCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

    if ($errorCode !== UPLOAD_ERR_OK) {
        $errors[] = 'Falha no upload da imagem de capa.';
        return [
            'path' => null,
            'errors' => $errors,
        ];
    }

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

    if ($errors !== []) {
        return [
            'path' => null,
            'errors' => $errors,
        ];
    }

    $coverDir = __DIR__ . '/../public/uploads/covers';
    if (!is_dir($coverDir) && !mkdir($coverDir, 0755, true) && !is_dir($coverDir)) {
        return [
            'path' => null,
            'errors' => ['Não foi possível preparar diretório de upload da capa.'],
        ];
    }

    $safeSlug = $slugForFile !== '' ? $slugForFile : 'post';
    $fileName = $safeSlug . '-' . uniqid() . '.' . $extension;
    $destination = $coverDir . '/' . $fileName;

    if (!move_uploaded_file($tmpName, $destination)) {
        return [
            'path' => null,
            'errors' => ['Não foi possível salvar a imagem de capa.'],
        ];
    }

    return [
        'path' => 'public/uploads/covers/' . $fileName,
        'errors' => [],
    ];
}

/**
 * Remove arquivo de capa previamente salvo, restringindo a remoção ao diretório de capas.
 *
 * @param string|null $relativePath Caminho relativo salvo em banco.
 *
 * @return void
 */
function postDeleteStoredCover(?string $relativePath): void
{
    if (!is_string($relativePath) || $relativePath === '') {
        return;
    }

    $coversBase = realpath(__DIR__ . '/../public/uploads/covers');
    $fullPath = realpath(__DIR__ . '/../' . ltrim($relativePath, '/\\'));
    if ($coversBase === false || $fullPath === false) {
        return;
    }

    if (!str_starts_with($fullPath, $coversBase . DIRECTORY_SEPARATOR)) {
        return;
    }

    if (is_file($fullPath)) {
        @unlink($fullPath);
    }
}
