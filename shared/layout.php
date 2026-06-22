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
    echo '<title>' . lcm_html_attr($title) . ' | LCM</title>' . PHP_EOL;
    echo '<link rel="icon" href="/assets/brand/favicon.svg" type="image/svg+xml">' . PHP_EOL;
    echo '<link rel="stylesheet" href="/assets/css/brand.css">' . PHP_EOL;

    foreach ($stylesheets as $href) {
        echo '<link rel="stylesheet" href="' . lcm_html_attr($href) . '">' . PHP_EOL;
    }
}

function lcm_topbar(string $active = ''): void
{
    $areas = [
        'direccion' => ['Dirección', '/direccion/'],
        'telefonia' => ['Telefonía', '/telefonia/menu.php'],
        'obras' => ['Obras', '/obras/'],
        'rrhh' => ['RRHH', '/rrhh/'],
        'contable' => ['Contable', '/contable/'],
        'mantenimiento' => ['Mantenimiento', '/mantenimiento/'],
        'licitaciones' => ['Licitaciones', '/licitaciones/'],
    ];

    echo '<header class="lcm-topbar">';
    echo '<a class="lcm-brand" href="/">' . lcm_logo('nav') . '</a>';
    echo '<nav class="lcm-nav" aria-label="Areas de negocio">';

    foreach ($areas as $key => [$label, $href]) {
        $current = $key === $active ? ' aria-current="page"' : '';
        echo '<a href="' . lcm_html_attr($href) . '"' . $current . '>' . lcm_html_attr($label) . '</a>';
    }

    echo '</nav>';
    echo '</header>';
}

function lcm_footer(): void
{
    echo '<footer class="lcm-footer">LCM - La Cablera Marplatense · Plataforma de Gestion Grupo Plantel</footer>';
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
