<?php
/**
 * Componente visual de marca LCM.
 *
 * Uso:
 * require_once __DIR__ . '/shared/brand.php';
 * echo lcm_logo('nav');
 */

function lcm_logo(string $tipo = 'nav', string $alt = 'LCM - La Cablera Marplatense'): string
{
    $map = [
        'horizontal' => '/assets/brand/lcm-logo-horizontal.svg',
        'nav'        => '/assets/brand/lcm-logo-nav.svg',
        'compact'    => '/assets/brand/lcm-logo-compact.svg',
        'icon'       => '/assets/brand/lcm-icon.svg',
    ];

    $src = $map[$tipo] ?? $map['nav'];

    return '<img class="lcm-brand__logo lcm-brand__logo--' . htmlspecialchars($tipo, ENT_QUOTES, 'UTF-8') . '" src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars($alt, ENT_QUOTES, 'UTF-8') . '">';
}
