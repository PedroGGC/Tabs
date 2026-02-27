<?php
declare(strict_types=1);

/**
 * Shared HTML fragments used across all pages.
 */

function headTags(string $title, string $description = 'Tabs — Um blog colaborativo para compartilhar ideias e artigos.'): string
{
    $csrf = isLogged() ? e(csrfToken()) : '';
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
    <!-- Theme styles and toggle script -->
    <link rel="stylesheet" href="public/css/style.css">
    <script src="public/js/theme.js"></script>
    <script>
        // Pre-hide body if a page transition is incoming to prevent unstyled layout flash
        if (sessionStorage.getItem("incoming")) {
            document.documentElement.style.visibility = "hidden";
        }
    </script>
    <title>' . e($title) . '</title>';
}

function siteHeader(): string
{
    $loggedIn = isLogged();
    $userId = currentUserId();
    $userAvatarHtml = '';

    if ($loggedIn) {
        // Fetch tiny user avatar just for the header since it needs an image or initials. Small inline query ok here for component safety.
        $pdo = getPDO();
        $st = $pdo->prepare('SELECT username, avatar FROM users WHERE id = :id');
        $st->execute(['id' => $userId]);
        $u = $st->fetch();

        if ($u) {
            if (!empty($u['avatar'])) {
                $userAvatarHtml = '<img class="avatar avatar-sm" src="' . e((string) $u['avatar']) . '" alt="Account" style="width:32px;height:32px;object-fit:cover;">';
            } else {
                $userAvatarHtml = '<span class="avatar avatar-sm avatar-fallback" style="width:32px;height:32px;line-height:32px;font-size:14px;">' . e(usernameInitial((string) $u['username'])) . '</span>';
            }
        }
    }

    $themeToggle = '<button type="button" class="action-btn theme-toggle" aria-label="Alternar tema" style="padding: 6px;">
        <svg class="theme-icon-light" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>
        <svg class="theme-icon-dark" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>
    </button>';

    $html = '<header class="site-header">
        <div class="container nav">
            <a class="brand" href="index.php">Tabs</a>
            <nav style="display: flex; align-items: center; gap: 0.5rem;">';

    $html .= $themeToggle;

    if ($loggedIn && $userAvatarHtml !== '') {
        $html .= '
            <div class="notifications-wrapper" style="position: relative;">
                <button type="button" class="action-btn" id="bell-toggle" aria-label="Notificações" style="padding: 6px; position:relative;">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
                    <span id="bell-badge" class="notification-count" style="display:none;">0</span>
                </button>
                <div id="notifications-dropdown" class="dropdown-panel">
                    <div class="dropdown-empty">Carregando...</div>
                </div>
            </div>

            <a class="button-inline" href="posts.php?action=create" data-transition="up" style="margin: 0 0.5rem;">Criar</a>
            
            <div class="profile-dropdown-wrapper" style="position: relative;">
                <button type="button" class="avatar-dropdown-toggle" id="avatar-toggle" style="background:none; border:none; padding:0; cursor:pointer; display:flex; align-items:center;">
                    ' . $userAvatarHtml . '
                </button>
                <div id="profile-dropdown" class="dropdown-panel profile-panel">
                    <a href="user.php?id=' . $userId . '" class="dropdown-item">Ver perfil</a>
                    <a href="account.php" class="dropdown-item">Minha conta</a>
                    <hr class="dropdown-divider">
                    <a href="logout.php" data-transition="back" class="dropdown-item text-danger">Sair</a>
                </div>
            </div>
        ';
    } else {
        $html .= '
            <a href="login.php">Login</a>
            <a href="register.php">Cadastro</a>
        ';
    }

    $html .= '
            </nav>
        </div>
    </header>';

    return $html;
}

function pageScripts(): string
{
    return '
    <button type="button" class="scroll-top-btn" aria-label="Voltar ao topo">↑</button>
    <script src="public/js/scroll-top.js" defer></script>
    <script src="public/js/transitions.js"></script>
    <script src="public/js/notifications.js" defer></script>';
}

function siteFooter(): string
{
    return '';
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
