<?php
declare(strict_types=1);

/**
 * Shared HTML fragments used across all pages.
 */

function headTags(string $title, string $description = 'Tabs — Um blog colaborativo para compartilhar ideias e artigos.'): string
{
    return '
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="' . e($description) . '">
    <meta property="og:title" content="' . e($title) . '">
    <meta property="og:description" content="' . e($description) . '">
    <meta property="og:type" content="website">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <!-- Theme styles and toggle script -->
    <link rel="stylesheet" href="public/css/style.css">
    <script src="public/js/theme.js"></script>
    <title>' . e($title) . '</title>';
}

function themeToggle(): string
{
    return '<button type="button" class="theme-toggle" aria-label="Alternar tema">
        <span class="theme-icon-light" aria-hidden="true">🌙</span>
        <span class="theme-icon-dark" aria-hidden="true">☀️</span>
    </button>';
}

function pageScripts(): string
{
    return '
    <button type="button" class="scroll-top-btn" aria-label="Voltar ao topo">↑</button>
    <script src="public/js/scroll-top.js" defer></script>
    <script src="public/js/transitions.js"></script>';
}

function siteFooter(): string
{
    $year = date('Y');
    return '
    <footer class="site-footer">
        <div class="container">
            <div class="footer-inner">
                <span>&copy; ' . $year . ' Tabs. Todos os direitos reservados.</span>
                <div class="footer-links">
                    <a href="index.php">Explorar</a>
                    <a href="login.php">Login</a>
                    <a href="register.php">Cadastro</a>
                </div>
            </div>
        </div>
    </footer>';
}

/**
 * Render numbered pagination.
 *
 * @param int    $currentPage
 * @param int    $totalPages
 * @param callable $buildPageUrl  fn(int $page): string
 * @param string $ariaLabel
 */
function renderPagination(int $currentPage, int $totalPages, callable $buildPageUrl, string $ariaLabel = 'Paginação'): string
{
    if ($totalPages <= 1) {
        return '';
    }

    $html = '<nav class="pagination" aria-label="' . e($ariaLabel) . '">';

    // Previous
    if ($currentPage > 1) {
        $html .= '<a class="pagination-link" href="' . e($buildPageUrl($currentPage - 1)) . '" aria-label="Página anterior">←</a>';
    }

    // Page numbers with ellipsis
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

    // Next
    if ($currentPage < $totalPages) {
        $html .= '<a class="pagination-link" href="' . e($buildPageUrl($currentPage + 1)) . '" aria-label="Próxima página">→</a>';
    }

    $html .= '</nav>';
    return $html;
}
