<?php
require_once __DIR__ . '/../../shared/layout.php';
require_once __DIR__ . '/services/IdentityApiClient.php';

$client = new IdentityApiClient();
$health = $client->health();

$statusLabel = static function (array $payload, string $key): string {
    $value = (string) ($payload[$key] ?? 'unknown');

    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
};

$statusClass = static function (array $payload, string $key): string {
    $value = (string) ($payload[$key] ?? 'unknown');

    return in_array($value, ['ready', 'configured'], true) ? 'identity-status--ok' : 'identity-status--pending';
};
?>
<!doctype html>
<html lang="es">
<head>
    <?php lcm_head('Centro de Identidades', [
        '/telefonia/identidades/assets/identidades.css?v=1'
    ]); ?>
</head>
<body class="lcm-page lcm-page--with-nav identity-page">
<?php lcm_topbar('telefonia'); ?>

<main class="identity-shell">
    <section class="identity-heading">
        <div>
            <span class="identity-eyebrow">Telefonia · Identity Core</span>
            <h1>Centro de Identidades</h1>
            <p>Administracion centralizada de personas, invitaciones, solicitudes y accesos.</p>
        </div>
        <a class="identity-back" href="/telefonia/menu.php">Volver a Telefonia</a>
    </section>

    <section class="identity-panel" aria-label="Estado del servicio">
        <div class="identity-panel__header">
            <h2>Estado del servicio Identity Core</h2>
            <span class="identity-pill <?php echo !empty($health['ok']) ? 'identity-pill--ok' : 'identity-pill--pending'; ?>">
                <?php echo !empty($health['ok']) ? 'Operativo' : 'Configuracion pendiente'; ?>
            </span>
        </div>

        <div class="identity-status-grid">
            <article class="identity-status <?php echo $statusClass($health, 'firebaseAdmin'); ?>">
                <span>Firebase Admin</span>
                <strong><?php echo $statusLabel($health, 'firebaseAdmin'); ?></strong>
            </article>
            <article class="identity-status <?php echo $statusClass($health, 'firestore'); ?>">
                <span>Firestore</span>
                <strong><?php echo $statusLabel($health, 'firestore'); ?></strong>
            </article>
            <article class="identity-status <?php echo $statusClass($health, 'smtp'); ?>">
                <span>SMTP</span>
                <strong><?php echo $statusLabel($health, 'smtp'); ?></strong>
            </article>
            <article class="identity-status">
                <span>Ambiente</span>
                <strong><?php echo $statusLabel($health, 'environment'); ?></strong>
            </article>
        </div>

        <?php if (!empty($health['missingConfiguration']) && is_array($health['missingConfiguration'])): ?>
            <div class="identity-warning">
                <strong>Configuracion faltante</strong>
                <p><?php echo htmlspecialchars(implode(', ', $health['missingConfiguration']), ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
        <?php endif; ?>
    </section>

    <section class="identity-grid" aria-label="Modulos del Centro de Identidades">
        <?php
        $items = [
            ['Invitaciones', 'Crear, reenviar, revocar y consultar estado. Proximamente.'],
            ['Personas', 'Identidad real y cuentas asociadas. Proximamente.'],
            ['Solicitudes', 'Revision de altas pendientes. Proximamente.'],
            ['Accesos', 'Aplicaciones, modulos y roles autorizados. Proximamente.'],
            ['Aplicaciones', 'Catalogo administrable de apps. Proximamente.'],
            ['Modulos', 'Unidades funcionales por aplicacion. Proximamente.'],
            ['Roles', 'Roles y permisos explicitos. Proximamente.'],
            ['Auditoria', 'Trazabilidad de acciones sensibles. Proximamente.'],
        ];
        ?>
        <?php foreach ($items as [$title, $description]): ?>
            <article class="identity-card">
                <strong><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></strong>
                <small><?php echo htmlspecialchars($description, ENT_QUOTES, 'UTF-8'); ?></small>
            </article>
        <?php endforeach; ?>
    </section>
</main>

<?php lcm_footer(); ?>
</body>
</html>
