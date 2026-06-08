<?php

function bh_current_route(): string
{
    return isset($_GET['r']) ? trim((string) $_GET['r'], '/') : '';
}

function bh_navigation_items(): array
{
    return [
        [
            'label' => 'Dashboard',
            'route' => 'dashboard/index',
            'href' => 'index.php?r=dashboard/index',
            'icon' => 'bi-house-door',
        ],
        [
            'label' => 'Simulador',
            'route' => 'simulador/index',
            'href' => 'index.php?r=simulador/index',
            'icon' => 'bi-sliders2',
        ],
        [
            'label' => 'Blog',
            'route' => 'blog/index',
            'href' => 'index.php?r=blog/index',
            'icon' => 'bi-journal-text',
        ],
        [
            'label' => 'Cuenta',
            'route' => 'cuenta/index',
            'href' => 'index.php?r=cuenta/index',
            'icon' => 'bi-person',
        ],
    ];
}

function bh_navigation_link(array $item): string
{
    $currentRoute = bh_current_route();
    $isActive = $currentRoute === $item['route'] || (
        $item['route'] === 'blog/index' && str_starts_with($currentRoute, 'blog/')
    );
    $class = 'nav-link' . ($isActive ? ' is-active' : '');
    $ariaCurrent = $isActive ? ' aria-current="page"' : '';

    return sprintf(
        '<a class="%s"%s href="%s"><i class="bi %s" aria-hidden="true"></i><span>%s</span></a>',
        $class,
        $ariaCurrent,
        htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8'),
        htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8'),
        htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8')
    );
}

function bh_navigation_list(bool $includeLogout = false): void
{
    echo '<ul class="nav flex-column">';

    foreach (bh_navigation_items() as $item) {
        echo '<li class="nav-item">' . bh_navigation_link($item) . '</li>';
    }

    if ($includeLogout) {
        echo '<li class="nav-item mt-3">';
        echo '<a class="nav-link bh-nav-logout" href="?r=auth/logout"><i class="bi bi-box-arrow-right" aria-hidden="true"></i><span>Cerrar sesión</span></a>';
        echo '</li>';
    }

    echo '</ul>';
}

function bh_mobile_nav(): void
{
    ?>
    <!-- Boton menu movil -->
    <div class="bh-mobile-nav d-md-none">
        <button class="bh-btn bh-btn-primary bh-btn-icon"
            type="button"
            data-bs-toggle="offcanvas"
            data-bs-target="#mobileMenu"
            aria-controls="mobileMenu"
            aria-label="Abrir menu">
            <i class="bi bi-list" aria-hidden="true"></i>
        </button>
    </div>
    <?php
}

function bh_sidebar(): void
{
    ?>
    <!-- Panel Lateral izquierdo -->
    <aside class="d-none d-md-flex bh-sidebar">
        <!-- Logo Benehom-->
        <div class="logo-container text-center mb-4">
            <a href="index.php?r=dashboard/index">
                <img src="<?= BASE_URL ?>img/logo-benehom.png" alt="Logo Benehom" class="logo-benehom">
            </a>
        </div>

        <nav aria-label="Navegacion principal">
            <?php bh_navigation_list(); ?>
        </nav>

        <!--Enlace para cerrar sesion-->
        <div class="bh-sidebar-footer">
            <a class="nav-link bh-nav-logout" href="?r=auth/logout"><i class="bi bi-box-arrow-right" aria-hidden="true"></i><span>Cerrar sesión</span></a>
        </div>
    </aside>
    <?php
}

function bh_mobile_menu(): void
{
    ?>
    <!-- Sidebar movil (Offcanvas) -->
    <div class="offcanvas offcanvas-start bh-mobile-menu d-md-none"
        tabindex="-1"
        id="mobileMenu">

        <div class="offcanvas-header">
            <h5 class="offcanvas-title">Menú</h5>
            <button type="button"
                class="btn-close btn-close-white"
                data-bs-dismiss="offcanvas">
            </button>
        </div>

        <div class="offcanvas-body">
            <div class="logo-container text-center mb-4">
                <a href="index.php?r=dashboard/index">
                    <img src="<?= BASE_URL ?>img/logo-benehom.png"
                        alt="Logo Benehom"
                        class="logo-benehom">
                </a>
            </div>

            <?php bh_navigation_list(true); ?>
        </div>
    </div>
    <?php
}
