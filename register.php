<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

if (isLogged()) {
    redirect('dashboard.php');
}

$pdo = getPDO();
$errors = [];
$old = [
    'username' => '',
    'email' => '',
];

if (isPostRequest()) {
    verifyCsrfOrFail();

    $old['username'] = sanitize($_POST['username'] ?? '');
    $old['email'] = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $result = registerUser($pdo, $old['username'], $old['email'], $password);
    if ($result['success']) {
        setFlash('success', 'Cadastro realizado com sucesso. Faça login para continuar.');
        redirect('login.php');
    }

    $errors = $result['errors'];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro</title>
    <link rel="stylesheet" href="public/css/style.css">
</head>
<body>
    <div id="page">
    <header class="site-header">
        <div class="container nav">
            <a class="brand" href="index.php">Blog PHP</a>
            <nav>
                <a href="login.php">Login</a>
                <a href="register.php">Cadastro</a>
            </nav>
        </div>
    </header>

    <main class="container page-shell">
        <section class="card form-card auth-card">
            <h1>Criar conta</h1>
            <p class="meta form-intro">Cadastre-se para começar a publicar seus conteúdos.</p>

            <?php if ($errors !== []): ?>
                <div class="alert alert-error">
                    <?php foreach ($errors as $error): ?>
                        <p><?= e($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="post" action="register.php">
                <?= csrfInput(); ?>

                <label for="username">Username</label>
                <input type="text" id="username" name="username" value="<?= e($old['username']); ?>" required>

                <label for="email">E-mail</label>
                <input type="email" id="email" name="email" value="<?= e($old['email']); ?>" required>

                <label for="password">Senha (mínimo 8 caracteres)</label>
                <input type="password" id="password" name="password" minlength="8" required>

                <button type="submit">Cadastrar</button>
            </form>

            <p class="meta">Já tem conta? <a href="login.php">Faça login</a>.</p>
        </section>
    </main>
    </div>
    <script src="public/js/transitions.js"></script>
</body>
</html>
