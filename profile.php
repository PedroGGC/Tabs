<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

requireLogin();

$pdo = getPDO();
$userId = currentUserId();

if ($userId === null) {
    setFlash('error', 'Usuário inválido.');
    redirect('login.php');
}

$flash = getFlash();
$userStmt = $pdo->prepare('SELECT id, username, email, password, avatar, bio FROM users WHERE id = :id LIMIT 1');
$userStmt->execute(['id' => $userId]);
$user = $userStmt->fetch();

if (!$user) {
    setFlash('error', 'Usuário não encontrado.');
    redirect('login.php');
}

$resolveReturnTo = static function (): string {
    $allowed = ['profile.php', 'dashboard.php'];
    $target = sanitize((string) ($_POST['return_to'] ?? 'profile.php'));
    return in_array($target, $allowed, true) ? $target : 'profile.php';
};

$deleteStoredUpload = static function (?string $relativePath): void {
    if (!is_string($relativePath) || $relativePath === '') {
        return;
    }

    $uploadsBase = realpath(__DIR__ . '/public/uploads');
    $fullPath = realpath(__DIR__ . '/' . ltrim($relativePath, '/\\'));

    if ($uploadsBase === false || $fullPath === false) {
        return;
    }

    if (!str_starts_with($fullPath, $uploadsBase . DIRECTORY_SEPARATOR)) {
        return;
    }

    if (is_file($fullPath)) {
        @unlink($fullPath);
    }
};

if (isPostRequest()) {
    verifyCsrfOrFail();
    $action = sanitize((string) ($_GET['action'] ?? ''));

    if ($action === 'username') {
        $newUsername = sanitize((string) ($_POST['username'] ?? ''));

        if (mb_strlen($newUsername, 'UTF-8') < 3) {
            setFlash('error', 'Username deve ter no mínimo 3 caracteres.');
            redirect('profile.php');
        }

        if (!preg_match('/^[A-Za-z0-9_]+$/', $newUsername)) {
            setFlash('error', 'Username deve conter apenas letras, números e underscore.');
            redirect('profile.php');
        }

        $checkStmt = $pdo->prepare('SELECT id FROM users WHERE username = :username AND id <> :id LIMIT 1');
        $checkStmt->execute([
            'username' => $newUsername,
            'id' => $userId,
        ]);

        if ($checkStmt->fetch()) {
            setFlash('error', 'Username já está em uso.');
            redirect('profile.php');
        }

        $updateStmt = $pdo->prepare('UPDATE users SET username = :username WHERE id = :id');
        $updateStmt->execute([
            'username' => $newUsername,
            'id' => $userId,
        ]);

        $_SESSION['username'] = $newUsername;
        setFlash('success', 'Username atualizado com sucesso.');
        redirect('profile.php');
    }

    if ($action === 'bio') {
        $bio = trim((string) ($_POST['bio'] ?? ''));

        if (mb_strlen($bio, 'UTF-8') > 300) {
            setFlash('error', 'A bio deve ter no máximo 300 caracteres.');
            redirect('profile.php');
        }

        $updateStmt = $pdo->prepare('UPDATE users SET bio = :bio WHERE id = :id');
        $updateStmt->execute([
            'bio' => $bio === '' ? null : $bio,
            'id' => $userId,
        ]);

        setFlash('success', 'Seção "Sobre mim" atualizada com sucesso.');
        redirect('profile.php');
    }

    if ($action === 'password') {
        $currentPassword = (string) ($_POST['current_password'] ?? '');
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

        if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
            setFlash('error', 'Preencha todos os campos de senha.');
            redirect('profile.php');
        }

        if (!password_verify($currentPassword, (string) $user['password'])) {
            setFlash('error', 'Senha atual inválida.');
            redirect('profile.php');
        }

        if (mb_strlen($newPassword, 'UTF-8') < 8) {
            setFlash('error', 'A nova senha deve ter no mínimo 8 caracteres.');
            redirect('profile.php');
        }

        if ($newPassword !== $confirmPassword) {
            setFlash('error', 'A confirmação da nova senha não confere.');
            redirect('profile.php');
        }

        $hash = password_hash($newPassword, PASSWORD_BCRYPT);
        $updateStmt = $pdo->prepare('UPDATE users SET password = :password WHERE id = :id');
        $updateStmt->execute([
            'password' => $hash,
            'id' => $userId,
        ]);

        setFlash('success', 'Senha atualizada com sucesso.');
        redirect('profile.php');
    }

    if ($action === 'avatar') {
        $returnTo = $resolveReturnTo();
        $removeAvatar = isset($_POST['remove_avatar']) && $_POST['remove_avatar'] === '1';
        $hasUpload = isset($_FILES['avatar']) && (int) ($_FILES['avatar']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

        if (!$removeAvatar && !$hasUpload) {
            setFlash('error', 'Selecione uma imagem ou marque remover foto atual.');
            redirect($returnTo);
        }

        if ($hasUpload) {
            $file = $_FILES['avatar'];
            $errorCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

            if ($errorCode !== UPLOAD_ERR_OK) {
                setFlash('error', 'Falha no upload da imagem.');
                redirect($returnTo);
            }

            $maxSize = 1024 * 1024;
            $size = (int) ($file['size'] ?? 0);
            if ($size <= 0 || $size > $maxSize) {
                setFlash('error', 'A imagem de perfil deve ter no máximo 1MB.');
                redirect($returnTo);
            }

            $originalName = (string) ($file['name'] ?? '');
            $extension = mb_strtolower(pathinfo($originalName, PATHINFO_EXTENSION), 'UTF-8');
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];

            if (!in_array($extension, $allowedExtensions, true)) {
                setFlash('error', 'Formato inválido. Envie jpg, jpeg, png ou webp.');
                redirect($returnTo);
            }

            $tmpName = (string) ($file['tmp_name'] ?? '');
            if ($tmpName === '' || !is_uploaded_file($tmpName)) {
                setFlash('error', 'Arquivo de upload inválido.');
                redirect($returnTo);
            }

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
                setFlash('error', 'Tipo MIME inválido para imagem de perfil.');
                redirect($returnTo);
            }

            if (!in_array($extension, $allowedMimeMap[$mimeType], true)) {
                setFlash('error', 'A extensão do arquivo não corresponde ao tipo da imagem.');
                redirect($returnTo);
            }

            $avatarDir = __DIR__ . '/public/uploads/avatars';
            if (!is_dir($avatarDir) && !mkdir($avatarDir, 0755, true) && !is_dir($avatarDir)) {
                setFlash('error', 'Não foi possível preparar diretório de upload.');
                redirect($returnTo);
            }

            $fileName = 'avatar-' . $userId . '-' . uniqid() . '.' . $extension;
            $destination = $avatarDir . '/' . $fileName;
            $relativePath = 'public/uploads/avatars/' . $fileName;

            if (!move_uploaded_file($tmpName, $destination)) {
                setFlash('error', 'Não foi possível salvar a imagem enviada.');
                redirect($returnTo);
            }

            $deleteStoredUpload((string) ($user['avatar'] ?? null));

            $updateStmt = $pdo->prepare('UPDATE users SET avatar = :avatar WHERE id = :id');
            $updateStmt->execute([
                'avatar' => $relativePath,
                'id' => $userId,
            ]);

            setFlash('success', 'Foto de perfil atualizada com sucesso.');
            redirect($returnTo);
        }

        if ($removeAvatar) {
            $deleteStoredUpload((string) ($user['avatar'] ?? null));
            $updateStmt = $pdo->prepare('UPDATE users SET avatar = NULL WHERE id = :id');
            $updateStmt->execute(['id' => $userId]);

            setFlash('success', 'Foto de perfil removida com sucesso.');
            redirect($returnTo);
        }
    }

    setFlash('error', 'Ação inválida.');
    redirect('profile.php');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meu Perfil</title>
    <link rel="stylesheet" href="public/css/style.css">
</head>
<body>
    <div id="page">
    <header class="site-header">
        <div class="container nav">
            <a class="brand" href="index.php">Blog PHP</a>
            <nav>
                <a href="dashboard.php">Dashboard</a>
                <a href="profile.php">Meu Perfil</a>
                <a href="logout.php" data-transition="back">Sair</a>
            </nav>
        </div>
    </header>

    <main class="container page-shell">
        <?php if ($flash): ?>
            <div class="alert <?= $flash['type'] === 'success' ? 'alert-success' : 'alert-error'; ?>">
                <p><?= e($flash['message']); ?></p>
            </div>
        <?php endif; ?>

        <section class="card form-card profile-card">
            <h1>Foto de perfil</h1>
            <p class="meta form-intro">Clique na foto para editar.</p>

            <form id="avatar-upload-form" method="post" action="profile.php?action=avatar" enctype="multipart/form-data">
                <?= csrfInput(); ?>
                <input type="hidden" name="return_to" value="profile.php">
                <input
                    type="file"
                    id="avatar-input"
                    name="avatar"
                    accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                    class="hidden-file-input"
                >
            </form>

            <button type="button" id="avatar-trigger" class="avatar-editor" aria-label="Editar foto de perfil">
                <?php if (!empty($user['avatar'])): ?>
                    <img src="<?= e((string) $user['avatar']); ?>" alt="Foto de perfil de <?= e((string) $user['username']); ?>" class="avatar avatar-lg">
                <?php else: ?>
                    <span class="avatar avatar-lg avatar-fallback"><?= e(usernameInitial((string) $user['username'])); ?></span>
                <?php endif; ?>
                <span class="avatar-editor-overlay">Editar foto</span>
            </button>

            <?php if (!empty($user['avatar'])): ?>
                <form id="avatar-remove-form" method="post" action="profile.php?action=avatar">
                    <?= csrfInput(); ?>
                    <input type="hidden" name="return_to" value="profile.php">
                    <label class="inline-check" for="remove_avatar">
                        <input type="checkbox" id="remove_avatar" name="remove_avatar" value="1">
                        Remover foto atual
                    </label>
                </form>
            <?php endif; ?>
        </section>

        <section class="card form-card">
            <h2>Alterar username</h2>
            <form method="post" action="profile.php?action=username">
                <?= csrfInput(); ?>
                <label for="username">Username</label>
                <input
                    type="text"
                    id="username"
                    name="username"
                    value="<?= e((string) $user['username']); ?>"
                    minlength="3"
                    pattern="[A-Za-z0-9_]+"
                    required
                >
                <button type="submit">Salvar username</button>
            </form>
        </section>

        <section class="card form-card">
            <h2>Sobre mim</h2>
            <form method="post" action="profile.php?action=bio">
                <?= csrfInput(); ?>
                <label for="bio">Biografia (opcional)</label>
                <textarea id="bio" name="bio" maxlength="300" rows="5"><?= e((string) ($user['bio'] ?? '')); ?></textarea>
                <p class="meta" id="bio-counter">0/300 caracteres</p>
                <button type="submit">Salvar bio</button>
            </form>
        </section>

        <section class="card form-card">
            <h2>Alterar senha</h2>
            <form method="post" action="profile.php?action=password">
                <?= csrfInput(); ?>
                <label for="current_password">Senha atual</label>
                <input type="password" id="current_password" name="current_password" required>

                <label for="new_password">Nova senha (mínimo 8 caracteres)</label>
                <input type="password" id="new_password" name="new_password" minlength="8" required>

                <label for="confirm_password">Confirmar nova senha</label>
                <input type="password" id="confirm_password" name="confirm_password" minlength="8" required>

                <button type="submit">Salvar senha</button>
            </form>
        </section>
    </main>
    </div>
    <script>
    (function () {
        const avatarTrigger = document.getElementById('avatar-trigger');
        const avatarInput = document.getElementById('avatar-input');
        const avatarUploadForm = document.getElementById('avatar-upload-form');
        const removeCheckbox = document.getElementById('remove_avatar');
        const removeForm = document.getElementById('avatar-remove-form');
        const bioField = document.getElementById('bio');
        const bioCounter = document.getElementById('bio-counter');

        if (avatarTrigger && avatarInput && avatarUploadForm) {
            avatarTrigger.addEventListener('click', function () {
                avatarInput.click();
            });

            avatarInput.addEventListener('change', function () {
                if (avatarInput.files && avatarInput.files.length > 0) {
                    avatarUploadForm.submit();
                }
            });
        }

        if (removeCheckbox && removeForm) {
            removeCheckbox.addEventListener('change', function () {
                if (removeCheckbox.checked) {
                    removeForm.submit();
                }
            });
        }

        if (bioField && bioCounter) {
            const updateCounter = function () {
                bioCounter.textContent = bioField.value.length + '/300 caracteres';
            };

            bioField.addEventListener('input', updateCounter);
            updateCounter();
        }
    })();
    </script>
    <script src="public/js/transitions.js"></script>
</body>
</html>
