<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';

if (isLogged()) {
    redirect('index.php');
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
    <?= headTags('Cadastro | Tabs'); ?>
</head>

<body>
    <div id="page">
        <header class="site-header">
            <div class="container nav">
                <a class="brand" href="index.php">Tabs</a>
                <nav>
                    <?= themeToggle(); ?>
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
                    <div class="password-wrapper">
                        <input type="password" id="password" name="password" minlength="8" required>
                        <button type="button" class="password-toggle" aria-label="Mostrar senha">
                            <svg class="eye-on" width="20" height="20" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                <circle cx="12" cy="12" r="3" />
                            </svg>
                            <svg class="eye-off" width="20" height="20" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                <path
                                    d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24" />
                                <line x1="1" y1="1" x2="23" y2="23" />
                            </svg>
                        </button>
                    </div>

                    <button type="submit">Cadastrar</button>
                </form>

                <p class="meta">Já tem conta? <a href="login.php">Faça login</a>.</p>
            </section>
        </main>
    </div>
    <script src="public/js/transitions.js"></script>
    <script>
        document.querySelectorAll('.password-toggle').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var input = this.closest('.password-wrapper').querySelector('input');
                var isPassword = input.type === 'password';
                input.type = isPassword ? 'text' : 'password';
                this.classList.toggle('is-visible', isPassword);
                this.setAttribute('aria-label', isPassword ? 'Ocultar senha' : 'Mostrar senha');
            });
        });
    </script>
</body>

</html>