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
    return '<button type="button" class="action-btn theme-toggle" aria-label="Alternar tema" style="padding: 6px; margin-right: 8px;">
        <svg class="theme-icon-light" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>
        <svg class="theme-icon-dark" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>
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
