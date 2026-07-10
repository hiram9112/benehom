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
            'href' => BASE_URL . 'index.php?r=dashboard/index',
            'icon' => 'ti ti-home',
        ],
        [
            'label' => 'Proyecciones',
            'route' => 'proyecciones/index',
            'href' => BASE_URL . 'index.php?r=proyecciones/index',
            'icon' => 'ti ti-adjustments-horizontal',
        ],
        [
            'label' => 'Blog',
            'route' => 'blog/index',
            'href' => bh_blog_url(),
            'icon' => 'ti ti-notebook',
        ],
        [
            'label' => 'Cuenta',
            'route' => 'cuenta/index',
            'href' => BASE_URL . 'index.php?r=cuenta/index',
            'icon' => 'ti ti-user',
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
        '<a class="%s"%s href="%s"><i class="%s" aria-hidden="true"></i><span>%s</span></a>',
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
        echo '<a class="nav-link bh-nav-logout" href="' . htmlspecialchars(BASE_URL . 'index.php?r=auth/logout', ENT_QUOTES, 'UTF-8') . '"><i class="ti ti-logout" aria-hidden="true"></i><span>Cerrar sesión</span></a>';
        echo '</li>';
    }

    echo '</ul>';
}

function bh_mobile_nav(): void
{
    ?>
    <!-- Boton menu movil -->
    <header class="bh-home-nav d-md-none" aria-label="Navegación principal">
        <div class="bh-home-wrap">
            <a class="bh-home-brand" href="<?= BASE_URL ?>index.php?r=dashboard/index" aria-label="BeneHom, ir al dashboard">
                <img src="<?= BASE_URL ?>img/logo-benehom.png" alt="BeneHom" width="120" height="80">
            </a>

            <button class="bh-btn bh-btn-primary bh-btn-icon bh-home-mobile-trigger"
                type="button"
                data-bs-toggle="offcanvas"
                data-bs-target="#mobileMenu"
                aria-controls="mobileMenu"
                aria-label="Abrir menú">
                <i class="ti ti-menu-2" aria-hidden="true"></i>
            </button>
        </div>
    </header>
    <?php
}

function bh_sidebar(): void
{
    ?>
    <!-- Panel Lateral izquierdo -->
    <aside class="d-none d-md-flex bh-sidebar">
        <!-- Logo Benehom-->
        <div class="logo-container text-center mb-4">
            <a href="<?= BASE_URL ?>index.php?r=dashboard/index">
                <img src="<?= BASE_URL ?>img/logo-benehom.png" alt="Logo Benehom" class="logo-benehom">
            </a>
        </div>

        <nav aria-label="Navegacion principal">
            <?php bh_navigation_list(); ?>
        </nav>

        <!--Enlace para cerrar sesion-->
        <div class="bh-sidebar-footer">
            <a class="nav-link bh-nav-logout" href="<?= BASE_URL ?>index.php?r=auth/logout"><i class="ti ti-logout" aria-hidden="true"></i><span>Cerrar sesión</span></a>
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
        id="mobileMenu"
        aria-labelledby="mobileMenuTitle">

        <div class="offcanvas-header">
            <h5 class="offcanvas-title" id="mobileMenuTitle">Menú</h5>
            <button type="button"
                class="btn-close btn-close-white"
                data-bs-dismiss="offcanvas"
                aria-label="Cerrar menú">
            </button>
        </div>

        <div class="offcanvas-body">
            <div class="logo-container text-center mb-4">
                <a href="<?= BASE_URL ?>index.php?r=dashboard/index">
                    <img src="<?= BASE_URL ?>img/logo-benehom.png"
                        alt="Logo Benehom"
                        class="logo-benehom">
                </a>
            </div>

            <nav aria-label="Navegación principal móvil">
                <?php bh_navigation_list(true); ?>
            </nav>
        </div>
    </div>
    <?php
}
