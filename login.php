<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

if (isLogged()) {
    redirect('index.php');
}

$pdo = getPDO();
$errors = [];
$oldIdentifier = '';
$flash = getFlash();

if (isPostRequest()) {
    verifyCsrfOrFail();

    $oldIdentifier = sanitize($_POST['identifier'] ?? '');
    $password = $_POST['password'] ?? '';

    $result = loginUser($pdo, $oldIdentifier, $password);
    if ($result['success']) {
        setFlash('success', 'Login realizado com sucesso.');
        redirect('index.php');
    }

    $errors = $result['errors'];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Threadly | Login</title>
    <link rel="stylesheet" href="public/css/style.css">
</head>
<body>
    <div id="page">
    <header class="site-header">
        <div class="container nav">
            <a class="brand" href="index.php">Threadly</a>
            <nav>
                <a href="login.php">Login</a>
                <a href="register.php">Cadastro</a>
            </nav>
        </div>
    </header>

    <main class="container page-shell">
        <section class="card form-card auth-card">
            <h1>Entrar</h1>
            <p class="meta form-intro">Acesse sua conta para gerenciar seus posts.</p>

            <?php if ($flash): ?>
                <div class="alert <?= $flash['type'] === 'success' ? 'alert-success' : 'alert-error'; ?>">
                    <p><?= e($flash['message']); ?></p>
                </div>
            <?php endif; ?>

            <?php if ($errors !== []): ?>
                <div class="alert alert-error">
                    <?php foreach ($errors as $error): ?>
                        <p><?= e($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="post" action="login.php">
                <?= csrfInput(); ?>

                <label for="identifier">E-mail ou Username</label>
                <input type="text" id="identifier" name="identifier" value="<?= e($oldIdentifier); ?>" required>

                <label for="password">Senha</label>
                <input type="password" id="password" name="password" required>

                <button type="submit">Login</button>
            </form>

            <p class="meta">Ainda não tem conta? <a href="register.php">Cadastre-se</a>.</p>
        </section>
    </main>
    </div>
    <script src="public/js/transitions.js"></script>
</body>
</html>
