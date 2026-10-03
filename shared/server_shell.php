<?php
declare(strict_types=1);

function lcm_server_shell_escape(mixed $value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function lcm_server_shell_icon(string $name): string
{
    $icons = [
        'apps' => '<path d="M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h6v6h-6z"/>',
        'home' => '<path d="m3 11 9-8 9 8"/><path d="M5 10v11h14V10M10 21v-6h4v6"/>',
        'dashboard' => '<path d="M4 13h7V4H4zM13 20h7V4h-7zM4 20h7v-5H4z"/>',
        'settings' => '<path d="M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z"/>',
        'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'logout' => '<path d="M10 17v2a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h3a2 2 0 0 1 2 2v2M15 17l5-5-5-5M20 12H9"/>',
    ];
    return '<span class="lcm-global-icon" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false">' . ($icons[$name] ?? $icons['apps']) . '</svg></span>';
}

function lcm_server_shell_modules(array $profile): array
{
    $modules = [];
    foreach ((array)($profile['modules'] ?? []) as $module) {
        if (!is_array($module) || ($module['aplicacion'] ?? '') !== 'CABLERAMARPLATENSE' || ($module['permissions']['puede_ver'] ?? false) !== true || ($module['estado'] ?? true) === false) {
            continue;
        }
        $module['ruta'] = (string)($module['ruta'] ?? '');
        if (!preg_match('#^/[A-Za-z0-9/_-]+/?$#', $module['ruta'])) {
            continue;
        }
        $modules[] = $module;
    }
    usort($modules, static fn(array $a, array $b): int => ((int)($a['orden'] ?? 0) <=> (int)($b['orden'] ?? 0)) ?: strcasecmp((string)($a['nombre'] ?? $a['codigo'] ?? ''), (string)($b['nombre'] ?? $b['codigo'] ?? '')));
    return $modules;
}

function lcm_server_shell_markup(array $profile, string $activePath): array
{
    $modules = lcm_server_shell_modules($profile);
    $groups = [];
    foreach ($modules as $module) {
        $groups[(string)($module['grupo'] ?? 'Aplicaciones')][] = $module;
    }
    $active = null;
    foreach ($modules as $module) {
        $route = rtrim((string)$module['ruta'], '/') . '/';
        if ($activePath === $route || str_starts_with($activePath, $route)) {
            $active = $module;
            break;
        }
    }
    $activeGroup = (string)($active['grupo'] ?? '');
    $groupHtml = '';
    foreach ($groups as $group => $items) {
        $groupHtml .= '<section class="lcm-nav-group" data-nav-group="' . lcm_server_shell_escape($group) . '">';
        $groupHtml .= '<button type="button" class="lcm-nav-group-toggle" aria-expanded="' . ($activeGroup === $group ? 'true' : 'false') . '" title="' . lcm_server_shell_escape($group) . '">' . lcm_server_shell_icon('apps') . '<span class="lcm-nav-label">' . lcm_server_shell_escape($group) . '</span><span class="lcm-nav-chevron" aria-hidden="true">⌄</span></button>';
        $groupHtml .= '<div class="lcm-nav-group-items"' . ($activeGroup === $group ? '' : ' hidden') . '>';
        foreach ($items as $module) {
            $route = (string)$module['ruta'];
            $current = $active && ($active['codigo'] ?? '') === ($module['codigo'] ?? '');
            $groupHtml .= '<a class="lcm-global-nav-link' . ($current ? ' active' : '') . '" data-app-name="' . lcm_server_shell_escape($module['nombre'] ?? $module['codigo'] ?? '') . '" data-route="' . lcm_server_shell_escape($route) . '" href="' . lcm_server_shell_escape($route) . '"' . ($current ? ' aria-current="page"' : '') . ' title="' . lcm_server_shell_escape($module['nombre'] ?? $module['codigo'] ?? '') . '">' . lcm_server_shell_icon((string)($module['icono'] ?? 'apps')) . '<span class="lcm-nav-label">' . lcm_server_shell_escape($module['nombre'] ?? $module['codigo'] ?? '') . '</span></a>';
        }
        $groupHtml .= '</div></section>';
    }
    $user = (array)($profile['user'] ?? []);
    $name = trim((string)($user['nombre'] ?? '') . ' ' . (string)($user['apellido'] ?? '')) ?: (string)($user['email'] ?? 'Usuario');
    $parts = preg_split('/\s+/', $name, -1, PREG_SPLIT_NO_EMPTY) ?: ['Usuario'];
    $initials = strtoupper(substr((string)$parts[0], 0, 1) . substr((string)($parts[1] ?? $parts[0]), 0, 1));
    $sidebar = '<div class="lcm-global-brand"><div class="lcm-global-mark">LCM</div><div class="lcm-global-brand-text"><strong>LA CABLERA</strong><span>MARPLATENSE</span></div></div><label class="lcm-global-search"><span class="sr-only">Buscar aplicaciones</span><span class="lcm-search-icon">⌕</span><input id="lcm-global-search-input" type="search" placeholder="Buscar aplicaciones" autocomplete="off"></label><nav class="lcm-global-nav" aria-label="Navegación principal"><a class="lcm-global-nav-link lcm-home-link' . (!$active ? ' active' : '') . '" href="/" title="Inicio">' . lcm_server_shell_icon('home') . '<span class="lcm-nav-label">Inicio</span></a>' . $groupHtml . '<p class="lcm-nav-empty" hidden>No hay aplicaciones para mostrar.</p></nav><div class="lcm-global-footer"><div class="lcm-global-profile"><div class="lcm-global-avatar">' . lcm_server_shell_escape($initials) . '</div><div class="lcm-global-profile-text"><strong>' . lcm_server_shell_escape($name) . '</strong><span>' . ((int)($user['es_superadmin'] ?? 0) === 1 ? 'Administrador' : 'Usuario corporativo') . '</span></div></div><button class="lcm-global-signout" type="button" data-lcm-action="signout" title="Cerrar sesión">' . lcm_server_shell_icon('logout') . '<span class="lcm-nav-label">Cerrar sesión</span></button></div>';
    $title = (string)($active['nombre'] ?? 'La Cablera Marplatense');
    $description = (string)($active['descripcion'] ?? 'Plataforma corporativa');
    $header = '<div class="lcm-global-header-left"><button class="lcm-global-icon-btn" type="button" data-lcm-action="toggle-sidebar" aria-label="Abrir navegación">' . lcm_server_shell_icon('menu') . '</button><div class="lcm-global-header-title"><strong>' . lcm_server_shell_escape($title) . '</strong><span>' . lcm_server_shell_escape($description) . '</span></div></div><div class="lcm-global-header-right"><button class="lcm-global-user-btn" type="button" data-lcm-action="toggle-user-menu" aria-expanded="false"><span class="lcm-global-avatar">' . lcm_server_shell_escape($initials) . '</span><span class="lcm-user-name">' . lcm_server_shell_escape($name) . '</span><span aria-hidden="true">⌄</span></button><div class="lcm-global-user-menu" id="lcm-global-user-menu" hidden><button type="button" data-lcm-action="reset-password">Cambiar contraseña</button><button type="button" data-lcm-action="signout">Cerrar sesión</button></div></div>';
    return ['sidebar' => $sidebar, 'header' => $header, 'modules' => $modules];
}
