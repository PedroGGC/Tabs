<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

function isLogged(): bool
{
    return isset($_SESSION['user_id']) && is_int($_SESSION['user_id']);
}

function currentUserId(): ?int
{
    if (!isLogged()) {
        return null;
    }

    return $_SESSION['user_id'];
}

function registerUser(PDO $pdo, string $username, string $email, string $password): array
{
    $errors = [];
    $username = sanitize($username);
    $email = mb_strtolower(sanitize($email), 'UTF-8');

    if ($username === '') {
        $errors[] = 'Username é obrigatório.';
    }

    if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $errors[] = 'Informe um e-mail válido.';
    }

    if (mb_strlen($password, 'UTF-8') < 8) {
        $errors[] = 'A senha deve ter no mínimo 8 caracteres.';
    }

    if ($errors !== []) {
        return [
            'success' => false,
            'errors' => $errors,
        ];
    }

    $stmt = $pdo->prepare('SELECT id FROM users WHERE username = :username LIMIT 1');
    $stmt->execute(['username' => $username]);
    if ($stmt->fetch()) {
        $errors[] = 'Username já está em uso.';
    }

    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => $email]);
    if ($stmt->fetch()) {
        $errors[] = 'E-mail já está em uso.';
    }

    if ($errors !== []) {
        return [
            'success' => false,
            'errors' => $errors,
        ];
    }

    $passwordHash = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $pdo->prepare(
        'INSERT INTO users (username, email, password, created_at) VALUES (:username, :email, :password, NOW())'
    );
    $stmt->execute([
        'username' => $username,
        'email' => $email,
        'password' => $passwordHash,
    ]);

    return [
        'success' => true,
        'errors' => [],
    ];
}

function loginUser(PDO $pdo, string $emailOrUsername, string $password): array
{
    $errors = [];
    $identifier = sanitize($emailOrUsername);

    if ($identifier === '' || $password === '') {
        return [
            'success' => false,
            'errors' => ['Preencha usuário/e-mail e senha.'],
        ];
    }

    $stmt = $pdo->prepare(
        'SELECT id, username, email, password
         FROM users
         WHERE email = :email_identifier OR username = :username_identifier
         LIMIT 1'
    );
    $stmt->execute([
        'email_identifier' => $identifier,
        'username_identifier' => $identifier,
    ]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        $errors[] = 'Credenciais inválidas.';
        return [
            'success' => false,
            'errors' => $errors,
        ];
    }

    if (password_needs_rehash($user['password'], PASSWORD_BCRYPT)) {
        $newHash = password_hash($password, PASSWORD_BCRYPT);
        $updateStmt = $pdo->prepare('UPDATE users SET password = :password WHERE id = :id');
        $updateStmt->execute([
            'password' => $newHash,
            'id' => (int) $user['id'],
        ]);
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['username'] = $user['username'];

    return [
        'success' => true,
        'errors' => [],
    ];
}

function logoutUser(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}
