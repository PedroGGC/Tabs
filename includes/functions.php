<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function sanitize(string $value): string
{
    return trim($value);
}

function redirect(string $path): void
{
    header("Location: {$path}");
    exit;
}

function isPostRequest(): bool
{
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

function generateSlug(string $title): string
{
    $slug = mb_strtolower(trim($title), 'UTF-8');
    $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $slug);

    if ($transliterated !== false) {
        $slug = $transliterated;
    }

    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
    $slug = trim($slug, '-');

    return $slug !== '' ? $slug : 'post';
}

function generateUniqueSlug(PDO $pdo, string $title, ?int $ignorePostId = null): string
{
    $baseSlug = generateSlug($title);
    $slug = $baseSlug;
    $counter = 1;

    while (true) {
        if ($ignorePostId === null) {
            $stmt = $pdo->prepare('SELECT id FROM posts WHERE slug = :slug LIMIT 1');
            $stmt->execute(['slug' => $slug]);
        } else {
            $stmt = $pdo->prepare('SELECT id FROM posts WHERE slug = :slug AND id <> :id LIMIT 1');
            $stmt->execute([
                'slug' => $slug,
                'id' => $ignorePostId,
            ]);
        }

        if (!$stmt->fetch()) {
            return $slug;
        }

        $slug = $baseSlug . '-' . $counter;
        $counter++;
    }
}

function setFlash(string $type, string $message): void
{
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message,
    ];
}

function getFlash(): ?array
{
    if (!isset($_SESSION['flash'])) {
        return null;
    }

    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

function requireLogin(): void
{
    if (empty($_SESSION['user_id'])) {
        setFlash('error', 'Você precisa fazer login para acessar esta página.');
        redirect('login.php');
    }
}

function excerpt(string $content, int $length = 180): string
{
    $clean = trim(strip_tags($content));

    if (mb_strlen($clean, 'UTF-8') <= $length) {
        return $clean;
    }

    return rtrim(mb_substr($clean, 0, $length, 'UTF-8')) . '...';
}

function formatDate(string $datetime): string
{
    $timestamp = strtotime($datetime);
    if ($timestamp === false) {
        return $datetime;
    }

    return date('d/m/Y H:i', $timestamp);
}
