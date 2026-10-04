<?php
require_once __DIR__ . '/brand.php';

function lcm_html_attr(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function lcm_head(string $title, array $stylesheets = []): void
{
    echo '<meta charset="utf-8">' . PHP_EOL;
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">' . PHP_EOL;
    echo '<script>document.documentElement.classList.add("lcm-auth-booting");</script>' . PHP_EOL;
    echo '<title>' . lcm_html_attr($title) . ' | LCM</title>' . PHP_EOL;
    echo '<link rel="icon" href="/assets/brand/favicon.svg" type="image/svg+xml">' . PHP_EOL;
    echo '<link rel="stylesheet" href="/assets/css/brand.css">' . PHP_EOL;
    echo '<link rel="stylesheet" href="/assets/css/atlantica.css?v=7">' . PHP_EOL;
    echo '<link rel="stylesheet" href="/assets/css/global-shell.css?v=ux-20261002-1">' . PHP_EOL;

    foreach ($stylesheets as $href) {
        echo '<link rel="stylesheet" href="' . lcm_html_attr($href) . '">' . PHP_EOL;
    }

    echo '<script type="module" src="/assets/js/global-auth.js?v=ux-20261002-2"></script>' . PHP_EOL;
}

function lcm_topbar(string $active = ''): void
{
    echo '<div class="lcm-global-backdrop" id="lcm-global-backdrop" hidden></div>';
    echo '<aside class="lcm-global-sidebar" id="lcm-global-sidebar" aria-label="Navegacion principal"></aside>';
    echo '<header class="lcm-global-header" id="lcm-global-header"></header>';
    echo '<div class="lcm-global-status" id="lcm-global-status"><section class="lcm-global-status-card"><h2>Cargando sesion</h2><p>Estamos validando tu acceso corporativo.</p></section></div>';
}
function lcm_footer(): void
{
    echo '<footer class="lcm-footer">LCM - La Cablera Marplatense · Plataforma de Gestión Grupo Plantel</footer>';
}

function lcm_coming_soon(string $area, string $module, string $description, string $backHref = '/telefonia/menu.php'): void
{
    echo '<main class="lcm-shell lcm-empty-state">';
    echo '<section class="lcm-panel">';
    echo '<span class="lcm-eyebrow">' . lcm_html_attr($area) . '</span>';
    echo '<h1>' . lcm_html_attr($module) . '</h1>';
    echo '<p class="lcm-muted">' . lcm_html_attr($description) . '</p>';
    echo '<div class="lcm-actions">';
    echo '<a class="lcm-action" href="' . lcm_html_attr($backHref) . '">Volver</a>';
    echo '</div>';
    echo '</section>';
    echo '</main>';
}
