<?php
declare(strict_types=1);

/**
 * Shared HTML fragments used across all pages.
 */

function headTags(string $title, string $description = 'Tabs — Um blog colaborativo para compartilhar ideias e artigos.'): string
{
    $csrf = isLogged() ? e(csrfToken()) : '';
    $assets = '';
    
    // Determine relative path depth
    $depth = substr_count(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/') - substr_count(parse_url($_SERVER['SCRIPT_NAME'], PHP_URL_PATH), '/');
    $base = str_repeat('../', max(0, $depth));
    
    // We can also use an absolute path approach if the project is in the root or a known subfolder
    // For now, let's try a more robust way to find the root URL
    $root = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
    $scriptName = $_SERVER['SCRIPT_NAME'];
    $dir = dirname($scriptName);
    if ($dir === DIRECTORY_SEPARATOR || $dir === '/') {
        $dir = '';
    }
    // If we are in /pages/user.php, dirname is /pages. We want the project root.
    // A simpler way: always use absolute paths from the domain root if possible, 
    // or detect if we are in a subdirectory of the project.
    
    // Let's use a simpler relative approach based on file location
    $isSubdir = str_contains($_SERVER['SCRIPT_NAME'], '/pages/') || str_contains($_SERVER['SCRIPT_NAME'], '/auth/');
    $rel = $isSubdir ? '../' : '';

    $manifestPath = __DIR__ . '/../../public/dist/assets/main.js';
    if (file_exists($manifestPath)) {
        $assets = '<script type="module" src="' . $rel . 'public/dist/assets/main.js"></script>
                   <link rel="stylesheet" href="' . $rel . 'public/dist/assets/main.css">';
    } else {
        $assets = '<script type="module" src="http://localhost:5173/src/main.jsx"></script>';
    }

    return '
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="' . e($description) . '">
    <meta property="og:title" content="' . e($title) . '">
    <meta property="og:description" content="' . e($description) . '">
    <meta property="og:type" content="website">
    ' . ($csrf !== '' ? '<meta name="_csrf" content="' . $csrf . '">' : '') . '
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="' . $rel . 'public/css/style.css">
    ' . $assets . '
    <script src="' . $rel . 'public/js/theme.js"></script>
    <script>
        if (sessionStorage.getItem("incoming")) {
            document.documentElement.style.visibility = "hidden";
        }
    </script>
    <title>' . e($title) . '</title>';
}

function siteHeader(): string
{
    $loggedIn = isLogged();
    
    // Detecta a profundidade baseada no SCRIPT_NAME para ser mais robusto
    $script = $_SERVER['SCRIPT_NAME'];
    $rel = '';
    
    if (str_contains($script, '/pages/') || str_contains($script, '/auth/')) {
        $rel = '../';
    } elseif (str_contains($script, '/src/actions/')) {
        $rel = '../../';
    }
    
    $html = '
    <nav class="side-nav-vertical">
        <div id="header-logo-circular"></div>
        <div id="vertical-nav-buttons" data-logged-in="' . ($loggedIn ? 'true' : 'false') . '" data-base-path="' . $rel . '"></div>
    </nav>';
    
    $html .= '<div id="top-right-audio-slider"></div>';
    
    $html .= '<div style="display:none;">';
    $html .= '<button type="button" class="theme-toggle" id="hidden-theme-toggle"></button>';
    $html .= '</div>';

    if ($loggedIn) {
        $html .= '<div id="notifications-dropdown" class="dropdown-panel" aria-live="polite"></div>';
    }

    return $html;
}

function pageScripts(): string
{
    $isSubdir = str_contains($_SERVER['SCRIPT_NAME'], '/pages/') || str_contains($_SERVER['SCRIPT_NAME'], '/auth/');
    $rel = $isSubdir ? '../' : '';

    return '
    <div id="floating-dock" style="display:none;"></div>
    <script src="' . $rel . 'public/js/scroll-top.js" defer></script>
    <script src="' . $rel . 'public/js/transitions.js"></script>
    <script src="' . $rel . 'public/js/notifications.js" defer></script>';
}

function siteFooter(): string
{
    return '';
}

/**
 * Render numbered pagination.
 */
function renderPagination(int $currentPage, int $totalPages, callable $buildPageUrl, string $ariaLabel = 'Paginação'): string
{
    if ($totalPages <= 1) {
        return '';
    }

    $html = '<nav class="pagination" aria-label="' . e($ariaLabel) . '">';

    if ($currentPage > 1) {
        $html .= '<a class="pagination-link" href="' . e($buildPageUrl($currentPage - 1)) . '" aria-label="Página anterior">←</a>';
    }

    $range = 2;
    for ($i = 1; $i <= $totalPages; $i++) {
        if ($i === 1 || $i === $totalPages || ($i >= $currentPage - $range && $i <= $currentPage + $range)) {
            if ($i === $currentPage) {
                $html .= '<span class="pagination-current" aria-current="page">' . $i . '</span>';
            } else {
                $html .= '<a class="pagination-link" href="' . e($buildPageUrl($i)) . '">' . $i . '</a>';
            }
        } elseif (
            ($i === $currentPage - $range - 1 && $i > 1) ||
            ($i === $currentPage + $range + 1 && $i < $totalPages)
        ) {
            $html .= '<span class="pagination-ellipsis">…</span>';
        }
    }

    if ($currentPage < $totalPages) {
        $html .= '<a class="pagination-link" href="' . e($buildPageUrl($currentPage + 1)) . '" aria-label="Próxima página">→</a>';
    }

    $html .= '</nav>';
    return $html;
}
