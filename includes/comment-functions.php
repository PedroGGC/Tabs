<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

// CREATE
/**
 * Cria comentário raiz ou resposta, incluindo notificações de reply/mention.
 *
 * @param PDO $pdo Conexão com banco de dados.
 * @param int $currentUserId Usuário autenticado que publica o comentário.
 * @param array<string, mixed> $input Dados do formulário.
 *
 * @return array{
 *   success: bool,
 *   errors: list<string>,
 *   redirect: string,
 *   post_id: int|null,
 *   comment_id: int|null,
 *   anchor: string
 * }
 */
function commentCreate(PDO $pdo, int $currentUserId, array $input): array
{
    $postIdRaw = $input['post_id'] ?? null;
    $postId = filter_var($postIdRaw, FILTER_VALIDATE_INT);
    if ($postId === false || $postId === null) {
        return commentFailureResult('Post inválido.', 'index.php');
    }

    $parentId = null;
    $rawParentId = $input['parent_id'] ?? null;
    if ($rawParentId !== null && $rawParentId !== '') {
        $parentId = filter_var($rawParentId, FILTER_VALIDATE_INT);
        if ($parentId === false) {
            return commentFailureResult('Comentário pai inválido.', commentBuildPostRedirect($postId), $postId, null, '#comments');
        }
    }

    $content = sanitize((string) ($input['content'] ?? ''));
    if ($content === '') {
        return commentFailureResult('O comentário não pode estar vazio.', commentBuildPostRedirect($postId), $postId, null, '#comments');
    }

    if (mb_strlen($content, 'UTF-8') > 1000) {
        return commentFailureResult('O comentário deve ter no máximo 1000 caracteres.', commentBuildPostRedirect($postId), $postId, null, '#comments');
    }

    $postStmt = $pdo->prepare('SELECT id FROM posts WHERE id = :id LIMIT 1');
    $postStmt->execute(['id' => $postId]);
    $post = $postStmt->fetch();
    if (!$post) {
        return commentFailureResult('Post não encontrado.', 'index.php');
    }

    $parentComment = null;
    if ($parentId !== null) {
        $parentStmt = $pdo->prepare(
            'SELECT id, user_id
             FROM comments
             WHERE id = :id AND post_id = :post_id
             LIMIT 1'
        );
        $parentStmt->execute([
            'id' => $parentId,
            'post_id' => $postId,
        ]);
        $parentComment = $parentStmt->fetch();

        if (!$parentComment) {
            return commentFailureResult('Comentário pai inválido.', commentBuildPostRedirect($postId), $postId, null, '#comments');
        }

        if ((int) $parentComment['user_id'] === $currentUserId) {
            return commentFailureResult('Você não pode responder seu próprio comentário.', commentBuildPostRedirect($postId), $postId, $parentId, '#comment-' . $parentId);
        }
    }

    $insertStmt = $pdo->prepare(
        'INSERT INTO comments (post_id, user_id, parent_id, content, created_at)
         VALUES (:post_id, :user_id, :parent_id, :content, NOW())'
    );
    $insertStmt->execute([
        'post_id' => $postId,
        'user_id' => $currentUserId,
        'parent_id' => $parentId,
        'content' => $content,
    ]);

    $commentId = (int) $pdo->lastInsertId();

    $insertNotification = $pdo->prepare(
        'INSERT INTO notifications (user_id, from_user_id, type, comment_id, post_id, is_read, created_at)
         VALUES (:user_id, :from_user_id, :type, :comment_id, :post_id, 0, NOW())'
    );

    if ($parentComment) {
        $parentOwnerId = (int) $parentComment['user_id'];
        if ($parentOwnerId !== $currentUserId) {
            $insertNotification->execute([
                'user_id' => $parentOwnerId,
                'from_user_id' => $currentUserId,
                'type' => 'reply',
                'comment_id' => $commentId,
                'post_id' => $postId,
            ]);
        }
    }

    $mentionMatches = [];
    preg_match_all('/@([A-Za-z0-9_]+)/u', $content, $mentionMatches);

    $mentionUsernames = [];
    if (isset($mentionMatches[1]) && is_array($mentionMatches[1])) {
        foreach ($mentionMatches[1] as $mentionedRaw) {
            $normalized = mb_strtolower((string) $mentionedRaw, 'UTF-8');
            if ($normalized !== '') {
                $mentionUsernames[$normalized] = true;
            }
        }
    }

    if ($mentionUsernames !== []) {
        $userByUsernameStmt = $pdo->prepare(
            'SELECT id
             FROM users
             WHERE LOWER(username) = :username
             LIMIT 1'
        );

        $notifiedMentionUsers = [];
        foreach (array_keys($mentionUsernames) as $mentionUsername) {
            $userByUsernameStmt->execute(['username' => $mentionUsername]);
            $mentionedUser = $userByUsernameStmt->fetch();
            if (!$mentionedUser) {
                continue;
            }

            $mentionedUserId = (int) $mentionedUser['id'];
            if ($mentionedUserId === $currentUserId || isset($notifiedMentionUsers[$mentionedUserId])) {
                continue;
            }

            $insertNotification->execute([
                'user_id' => $mentionedUserId,
                'from_user_id' => $currentUserId,
                'type' => 'mention',
                'comment_id' => $commentId,
                'post_id' => $postId,
            ]);

            $notifiedMentionUsers[$mentionedUserId] = true;
        }
    }

    return commentSuccessResult(commentBuildPostRedirect($postId), $postId, $commentId, '#comments');
}

// EDIT
/**
 * Atualiza o conteúdo de um comentário do próprio usuário.
 *
 * @param PDO $pdo Conexão com banco de dados.
 * @param int $currentUserId Usuário autenticado.
 * @param array<string, mixed> $input Dados do formulário.
 *
 * @return array{
 *   success: bool,
 *   errors: list<string>,
 *   redirect: string,
 *   post_id: int|null,
 *   comment_id: int|null,
 *   anchor: string
 * }
 */
function commentUpdate(PDO $pdo, int $currentUserId, array $input): array
{
    $commentIdRaw = $input['comment_id'] ?? null;
    $commentId = filter_var($commentIdRaw, FILTER_VALIDATE_INT);
    if ($commentId === false || $commentId === null) {
        return commentFailureResult('Comentário inválido.', 'index.php');
    }

    $commentStmt = $pdo->prepare(
        'SELECT id, user_id, post_id
         FROM comments
         WHERE id = :id
         LIMIT 1'
    );
    $commentStmt->execute(['id' => $commentId]);
    $comment = $commentStmt->fetch();
    if (!$comment) {
        return commentFailureResult('Comentário não encontrado.', 'index.php');
    }

    $postId = (int) $comment['post_id'];
    $redirect = commentBuildPostRedirect($postId);
    $anchor = '#comment-' . $commentId;

    if ((int) $comment['user_id'] !== $currentUserId) {
        return commentFailureResult('Você não pode editar este comentário.', $redirect, $postId, $commentId, $anchor);
    }

    $content = sanitize((string) ($input['content'] ?? ''));
    if ($content === '') {
        return commentFailureResult('O comentário não pode estar vazio.', $redirect, $postId, $commentId, $anchor);
    }

    if (mb_strlen($content, 'UTF-8') > 1000) {
        return commentFailureResult('O comentário deve ter no máximo 1000 caracteres.', $redirect, $postId, $commentId, $anchor);
    }

    $updateStmt = $pdo->prepare(
        'UPDATE comments
         SET content = :content
         WHERE id = :id AND user_id = :user_id
         LIMIT 1'
    );
    $updateStmt->execute([
        'content' => $content,
        'id' => $commentId,
        'user_id' => $currentUserId,
    ]);

    return commentSuccessResult($redirect, $postId, $commentId, $anchor);
}

// DELETE
/**
 * Exclui um comentário do próprio usuário.
 *
 * @param PDO $pdo Conexão com banco de dados.
 * @param int $currentUserId Usuário autenticado.
 * @param array<string, mixed> $input Dados do formulário.
 *
 * @return array{
 *   success: bool,
 *   errors: list<string>,
 *   redirect: string,
 *   post_id: int|null,
 *   comment_id: int|null,
 *   anchor: string
 * }
 */
function commentDelete(PDO $pdo, int $currentUserId, array $input): array
{
    $commentIdRaw = $input['comment_id'] ?? null;
    $commentId = filter_var($commentIdRaw, FILTER_VALIDATE_INT);
    if ($commentId === false || $commentId === null) {
        return commentFailureResult('Comentário inválido.', 'index.php');
    }

    $commentStmt = $pdo->prepare(
        'SELECT id, user_id, post_id
         FROM comments
         WHERE id = :id
         LIMIT 1'
    );
    $commentStmt->execute(['id' => $commentId]);
    $comment = $commentStmt->fetch();
    if (!$comment) {
        return commentFailureResult('Comentário não encontrado.', 'index.php');
    }

    $postId = (int) $comment['post_id'];
    $redirect = commentBuildPostRedirect($postId);

    if ((int) $comment['user_id'] !== $currentUserId) {
        return commentFailureResult('Você não pode excluir este comentário.', $redirect, $postId, $commentId, '#comment-' . $commentId);
    }

    $deleteStmt = $pdo->prepare(
        'DELETE FROM comments
         WHERE id = :id AND user_id = :user_id
         LIMIT 1'
    );
    $deleteStmt->execute([
        'id' => $commentId,
        'user_id' => $currentUserId,
    ]);

    return commentSuccessResult($redirect, $postId, $commentId, '#comments');
}

// HELPERS
/**
 * Monta URL base da página de post para redirecionamentos de comentários.
 *
 * @param int $postId ID do post.
 *
 * @return string URL de destino sem âncora.
 */
function commentBuildPostRedirect(int $postId): string
{
    return 'post.php?id=' . $postId;
}

/**
 * Cria payload padrão para respostas de erro em ações de comentário.
 *
 * @param string $message Mensagem de erro principal.
 * @param string $redirect URL de redirecionamento.
 * @param int|null $postId ID do post, quando conhecido.
 * @param int|null $commentId ID do comentário, quando conhecido.
 * @param string $anchor Âncora de redirecionamento.
 *
 * @return array{
 *   success: bool,
 *   errors: list<string>,
 *   redirect: string,
 *   post_id: int|null,
 *   comment_id: int|null,
 *   anchor: string
 * }
 */
function commentFailureResult(
    string $message,
    string $redirect,
    ?int $postId = null,
    ?int $commentId = null,
    string $anchor = ''
): array {
    return [
        'success' => false,
        'errors' => [$message],
        'redirect' => $redirect,
        'post_id' => $postId,
        'comment_id' => $commentId,
        'anchor' => $anchor,
    ];
}

/**
 * Cria payload padrão para respostas de sucesso em ações de comentário.
 *
 * @param string $redirect URL de redirecionamento.
 * @param int|null $postId ID do post relacionado.
 * @param int|null $commentId ID do comentário relacionado.
 * @param string $anchor Âncora de redirecionamento.
 *
 * @return array{
 *   success: bool,
 *   errors: list<string>,
 *   redirect: string,
 *   post_id: int|null,
 *   comment_id: int|null,
 *   anchor: string
 * }
 */
function commentSuccessResult(
    string $redirect,
    ?int $postId = null,
    ?int $commentId = null,
    string $anchor = ''
): array {
    return [
        'success' => true,
        'errors' => [],
        'redirect' => $redirect,
        'post_id' => $postId,
        'comment_id' => $commentId,
        'anchor' => $anchor,
    ];
}
