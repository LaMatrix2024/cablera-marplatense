<?php
require_once __DIR__ . '/../../config/conexion.php';
require_once __DIR__ . '/../../shared/layout.php';

function fmtPesos($value): string
{
    if ($value === null) {
        return '—';
    }

    return '$' . number_format((float)$value, 2, ',', '.');
}

function fmtFecha($value): string
{
    if (!$value) {
        return '—';
    }

    if ($value instanceof DateTimeInterface) {
        return $value->format('d/m/Y');
    }

    return date('d/m/Y', strtotime((string)$value));
}

function fmtPeriodo($value): string
{
    $value = trim((string)$value);
    if ($value === '') {
        return '—';
    }

    if (strlen($value) === 5) {
        $value = substr($value, 0, 4) . '0' . substr($value, 4, 1);
    }

    if (strlen($value) === 6) {
        return substr($value, 0, 4) . '-' . substr($value, 4, 2);
    }

    return $value;
}

function q(PDO $pdo, string $sql): array
{
    $stmt = $pdo->query($sql);
    return $stmt === false ? [] : $stmt->fetchAll();
}

$baseWhere = "categoria = '1-TELEFONIA' AND sub_categoria = '1-TASA'";
$warningText = 'Resultados preliminares desde información contable. No representan todavía producción ejecutada.';

$summary = $pdo_laboratorio->query("
    SELECT
        COUNT(*) AS total_registros,
        MIN(fecha) AS fecha_min,
        MAX(fecha) AS fecha_max,
        MIN(
            CASE
                WHEN periodo REGEXP '^[0-9]{5}$' THEN CONCAT(SUBSTRING(periodo, 1, 4), '0', SUBSTRING(periodo, 5, 1))
                WHEN periodo REGEXP '^[0-9]{6}$' THEN periodo
                ELSE NULL
            END
        ) AS periodo_min,
        MAX(
            CASE
                WHEN periodo REGEXP '^[0-9]{5}$' THEN CONCAT(SUBSTRING(periodo, 1, 4), '0', SUBSTRING(periodo, 5, 1))
                WHEN periodo REGEXP '^[0-9]{6}$' THEN periodo
                ELSE NULL
            END
        ) AS periodo_max,
        SUM(total) AS total_original,
        SUM(ars_ajustados) AS total_ajustado
    FROM raw_economico_provisorio
    WHERE {$baseWhere}
")->fetch();

$porPeriodo = q($pdo_laboratorio, "
    SELECT
        CASE
            WHEN periodo REGEXP '^[0-9]{5}$' THEN CONCAT(SUBSTRING(periodo, 1, 4), '0', SUBSTRING(periodo, 5, 1))
            WHEN periodo REGEXP '^[0-9]{6}$' THEN periodo
            ELSE periodo
        END AS periodo_norm,
        COUNT(*) AS registros,
        SUM(total) AS total_original,
        SUM(ars_ajustados) AS total_ajustado
    FROM raw_economico_provisorio
    WHERE {$baseWhere}
    GROUP BY periodo_norm
    ORDER BY periodo_norm
");

$porGrupo = q($pdo_laboratorio, "
    SELECT
        grupo_cuenta,
        COUNT(*) AS registros,
        SUM(total) AS total_original,
        SUM(ars_ajustados) AS total_ajustado
    FROM raw_economico_provisorio
    WHERE {$baseWhere}
    GROUP BY grupo_cuenta
    ORDER BY ABS(SUM(ars_ajustados)) DESC, grupo_cuenta
");

$porSucursal = q($pdo_laboratorio, "
    SELECT
        COALESCE(NULLIF(TRIM(sucursal), ''), 'SIN SUCURSAL') AS sucursal_norm,
        COUNT(*) AS registros,
        SUM(total) AS total_original,
        SUM(ars_ajustados) AS total_ajustado
    FROM raw_economico_provisorio
    WHERE {$baseWhere}
    GROUP BY sucursal_norm
    ORDER BY ABS(SUM(ars_ajustados)) DESC, sucursal_norm
");

$porNegocio = q($pdo_laboratorio, "
    SELECT
        COALESCE(NULLIF(TRIM(negocio), ''), 'SIN NEGOCIO') AS negocio_norm,
        COUNT(*) AS registros,
        SUM(total) AS total_original,
        SUM(ars_ajustados) AS total_ajustado
    FROM raw_economico_provisorio
    WHERE {$baseWhere}
    GROUP BY negocio_norm
    ORDER BY ABS(SUM(ars_ajustados)) DESC, negocio_norm
");

$top20 = q($pdo_laboratorio, "
    SELECT
        codigo_cuenta,
        nombre_cuenta,
        grupo_cuenta,
        COUNT(*) AS registros,
        SUM(ars_ajustados) AS total_ajustado,
        ABS(SUM(ars_ajustados)) AS abs_total_ajustado
    FROM raw_economico_provisorio
    WHERE {$baseWhere}
    GROUP BY codigo_cuenta, nombre_cuenta, grupo_cuenta
    ORDER BY ABS(SUM(ars_ajustados)) DESC, codigo_cuenta
    LIMIT 20
");
?>
<!doctype html>
<html lang="es">
<head>
    <?php lcm_head('Resultados preliminares TMA', [
        '/gerencia/Analisis_Economico_TELCO/assets/modulo.css?v=1',
    ]); ?>
    <style>
        .tma-page { display: grid; gap: 22px; }
        .tma-warning {
            padding: 16px 18px;
            border: 1px solid #d88a00;
            border-left: 5px solid #d88a00;
            border-radius: 14px;
            background: #fff6e6;
            color: #7a4a00;
            font-weight: 700;
        }
        .tma-kpis {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
        }
        .tma-card {
            padding: 18px 20px;
            border: 1px solid var(--atlantic-line);
            border-radius: var(--atlantic-radius);
            background: var(--atlantic-surface);
            box-shadow: var(--atlantic-shadow);
        }
        .tma-card span {
            display: block;
            margin-bottom: 8px;
            color: var(--atlantic-muted);
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .06em;
            text-transform: uppercase;
        }
        .tma-card strong {
            display: block;
            color: var(--atlantic-ink);
            font-size: 22px;
            font-variant-numeric: tabular-nums;
        }
        .tma-section {
            padding: 20px 22px;
            border: 1px solid var(--atlantic-line);
            border-radius: var(--atlantic-radius);
            background: var(--atlantic-surface);
            box-shadow: var(--atlantic-shadow);
            overflow: hidden;
        }
        .tma-section h2 {
            margin: 0 0 14px;
            color: var(--atlantic-ink);
        }
        .tma-table-wrap {
            overflow-x: auto;
        }
        .tma-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 760px;
        }
        .tma-table th,
        .tma-table td {
            padding: 10px 12px;
            border-bottom: 1px solid var(--atlantic-line);
            text-align: left;
            vertical-align: top;
        }
        .tma-table th {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: var(--atlantic-muted);
            background: var(--atlantic-surface-soft);
        }
        .tma-table td.num {
            text-align: right;
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
        }
        .tma-small {
            color: var(--atlantic-muted);
            font-size: 13px;
            line-height: 1.55;
        }
        .tma-grid-2 {
            display: grid;
            gap: 22px;
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        @media (max-width: 980px) {
            .tma-kpis,
            .tma-grid-2 {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body class="lcm-page lcm-page--with-nav">
<?php lcm_topbar('gerencia'); ?>

<main class="lcm-shell tma-page">
    <section class="lcm-page-head">
        <div>
            <span class="lcm-eyebrow">Gerencia</span>
            <h1>Resultados preliminares TMA</h1>
            <p class="lcm-muted">Lectura inicial desde la RAW contable filtrada al universo TMA.</p>
        </div>
        <div class="lcm-actions">
            <a class="lcm-action" href="/gerencia/Analisis_Economico_TELCO/">Volver al módulo</a>
            <a class="lcm-action" href="/gerencia/">Gerencia</a>
        </div>
    </section>

    <div class="tma-warning" role="alert">
        <?php echo htmlspecialchars($warningText, ENT_QUOTES, 'UTF-8'); ?>
    </div>

    <section class="tma-kpis" aria-label="Resumen TMA">
        <article class="tma-card">
            <span>Total de registros TMA</span>
            <strong><?php echo number_format((int)($summary['total_registros'] ?? 0), 0, ',', '.'); ?></strong>
        </article>
        <article class="tma-card">
            <span>Rango de fechas</span>
            <strong><?php echo htmlspecialchars(fmtFecha($summary['fecha_min'] ?? null) . ' a ' . fmtFecha($summary['fecha_max'] ?? null), ENT_QUOTES, 'UTF-8'); ?></strong>
        </article>
        <article class="tma-card">
            <span>Rango de períodos normalizados</span>
            <strong><?php echo htmlspecialchars(fmtPeriodo($summary['periodo_min'] ?? '') . ' a ' . fmtPeriodo($summary['periodo_max'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></strong>
        </article>
        <article class="tma-card">
            <span>Total importe original</span>
            <strong><?php echo htmlspecialchars(fmtPesos($summary['total_original'] ?? null), ENT_QUOTES, 'UTF-8'); ?></strong>
        </article>
        <article class="tma-card">
            <span>Total importe ajustado</span>
            <strong><?php echo htmlspecialchars(fmtPesos($summary['total_ajustado'] ?? null), ENT_QUOTES, 'UTF-8'); ?></strong>
        </article>
        <article class="tma-card">
            <span>Universo filtrado</span>
            <strong>TMA contable</strong>
        </article>
    </section>

    <div class="tma-grid-2">
        <section class="tma-section">
            <h2>Totales por período</h2>
            <div class="tma-table-wrap">
                <table class="tma-table">
                    <thead>
                        <tr>
                            <th>Período</th>
                            <th>Registros</th>
                            <th>Importe original</th>
                            <th>Importe ajustado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($porPeriodo as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars(fmtPeriodo($row['periodo_norm'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td class="num"><?php echo number_format((int)$row['registros'], 0, ',', '.'); ?></td>
                                <td class="num"><?php echo htmlspecialchars(fmtPesos($row['total_original']), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td class="num"><?php echo htmlspecialchars(fmtPesos($row['total_ajustado']), ENT_QUOTES, 'UTF-8'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="tma-section">
            <h2>Totales por grupo de cuenta</h2>
            <div class="tma-table-wrap">
                <table class="tma-table">
                    <thead>
                        <tr>
                            <th>Grupo</th>
                            <th>Registros</th>
                            <th>Importe ajustado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($porGrupo as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars((string)$row['grupo_cuenta'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td class="num"><?php echo number_format((int)$row['registros'], 0, ',', '.'); ?></td>
                                <td class="num"><?php echo htmlspecialchars(fmtPesos($row['total_ajustado']), ENT_QUOTES, 'UTF-8'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <div class="tma-grid-2">
        <section class="tma-section">
            <h2>Totales por sucursal</h2>
            <div class="tma-table-wrap">
                <table class="tma-table">
                    <thead>
                        <tr>
                            <th>Sucursal</th>
                            <th>Registros</th>
                            <th>Importe ajustado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($porSucursal as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars((string)$row['sucursal_norm'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td class="num"><?php echo number_format((int)$row['registros'], 0, ',', '.'); ?></td>
                                <td class="num"><?php echo htmlspecialchars(fmtPesos($row['total_ajustado']), ENT_QUOTES, 'UTF-8'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="tma-section">
            <h2>Totales por negocio</h2>
            <div class="tma-table-wrap">
                <table class="tma-table">
                    <thead>
                        <tr>
                            <th>Negocio</th>
                            <th>Registros</th>
                            <th>Importe ajustado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($porNegocio as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars((string)$row['negocio_norm'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td class="num"><?php echo number_format((int)$row['registros'], 0, ',', '.'); ?></td>
                                <td class="num"><?php echo htmlspecialchars(fmtPesos($row['total_ajustado']), ENT_QUOTES, 'UTF-8'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <section class="tma-section">
        <h2>Top 20 cuentas por importe ajustado absoluto</h2>
        <p class="tma-small">Ordenado por el valor absoluto del importe ajustado dentro del universo TMA contable.</p>
        <div class="tma-table-wrap">
            <table class="tma-table">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Nombre de cuenta</th>
                        <th>Grupo</th>
                        <th>Registros</th>
                        <th>Importe ajustado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($top20 as $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars((string)$row['codigo_cuenta'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string)$row['nombre_cuenta'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string)$row['grupo_cuenta'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="num"><?php echo number_format((int)$row['registros'], 0, ',', '.'); ?></td>
                            <td class="num"><?php echo htmlspecialchars(fmtPesos($row['total_ajustado']), ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>

<?php lcm_footer(); ?>
</body>
</html>
