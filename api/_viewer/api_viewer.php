<?php
declare(strict_types=1);

if (!isset($viewerData) || !is_array($viewerData)) {
    $viewerData = [
        'ok' => false,
        'generated_at' => date(DATE_ATOM),
        'filters' => [],
        'count' => 0,
        'totals' => [],
        'warnings' => [],
        'rows' => [],
    ];
}

if (!isset($viewerConfig) || !is_array($viewerConfig)) {
    $viewerConfig = [];
}

function api_viewer_h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function api_viewer_display(mixed $value): string
{
    if (is_array($value)) {
        if (!$value) {
            return 'Todos';
        }
        return implode(', ', array_map(static fn (mixed $item): string => (string) $item, $value));
    }
    if ($value === null || $value === '') {
        return 'Todos';
    }
    return (string) $value;
}

function api_viewer_number(mixed $value, int $decimals = 2): string
{
    return number_format((float) ($value ?? 0), $decimals, ',', '.');
}

function api_viewer_money(mixed $value): string
{
    return '$ ' . api_viewer_number($value, 2);
}

function api_viewer_date(mixed $value): string
{
    $text = trim((string) $value);
    if ($text === '') {
        return '';
    }

    try {
        return (new DateTimeImmutable($text))->format('d/m/Y H:i:s');
    } catch (Throwable) {
        return $text;
    }
}

function api_viewer_value(array $row, string $field): mixed
{
    return array_key_exists($field, $row) ? $row[$field] : null;
}

$title = (string) ($viewerConfig['title'] ?? 'API Plantel');
$subtitle = (string) ($viewerConfig['subtitle'] ?? 'Resumen de respuesta');
$assetBase = rtrim((string) ($viewerConfig['asset_base'] ?? '../_viewer'), '/') . '/';
$columns = is_array($viewerConfig['columns'] ?? null) ? $viewerConfig['columns'] : [];
$totalsConfig = is_array($viewerConfig['totals'] ?? null) ? $viewerConfig['totals'] : [];
$filters = is_array($viewerData['filters'] ?? null) ? $viewerData['filters'] : [];
$totals = is_array($viewerData['totals'] ?? null) ? $viewerData['totals'] : [];
$warnings = is_array($viewerData['warnings'] ?? null) ? $viewerData['warnings'] : [];
$rows = is_array($viewerData['rows'] ?? null) ? $viewerData['rows'] : [];
$statusOk = (bool) ($viewerData['ok'] ?? false);

$query = $_GET;
$query['format'] = 'json';
$requestPath = strtok((string) ($_SERVER['REQUEST_URI'] ?? ''), '?') ?: '';
$jsonUrl = $requestPath . '?' . http_build_query($query);
$tableQuery = $_GET;
unset($tableQuery['format']);
$tableUrl = $requestPath . ($tableQuery ? '?' . http_build_query($tableQuery) : '');
$viewerJson = json_encode($viewerData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);

header('Content-Type: text/html; charset=utf-8');
?><!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= api_viewer_h($title) ?></title>
  <link rel="stylesheet" href="<?= api_viewer_h($assetBase . 'api_viewer.css') ?>">
</head>
<body>
  <main class="api-shell">
    <header class="api-header">
      <div class="api-title-group">
        <div class="api-mark">{}</div>
        <div>
          <div class="api-title-line">
            <h1><?= api_viewer_h($title) ?></h1>
            <span class="api-status <?= $statusOk ? 'ok' : 'error' ?>"><?= $statusOk ? 'OK' : 'ERROR' ?></span>
          </div>
          <p><?= api_viewer_h($subtitle) ?></p>
        </div>
      </div>
      <div class="api-actions">
        <span class="api-generated"><?= api_viewer_h(api_viewer_date($viewerData['generated_at'] ?? '')) ?></span>
        <a class="api-button" href="<?= api_viewer_h($jsonUrl) ?>">JSON</a>
        <a class="api-button active" href="<?= api_viewer_h($tableUrl) ?>">Tabla</a>
        <button class="api-button" type="button" data-export-csv>CSV</button>
      </div>
    </header>

    <section class="api-grid">
      <article class="api-panel">
        <h2>Filtros</h2>
        <dl class="api-dl">
          <?php foreach ($filters as $label => $value): ?>
            <div>
              <dt><?= api_viewer_h(ucfirst(str_replace('_', ' ', (string) $label))) ?></dt>
              <dd><?= api_viewer_h(api_viewer_display($value)) ?></dd>
            </div>
          <?php endforeach; ?>
        </dl>
      </article>

      <article class="api-panel">
        <h2>Metadata</h2>
        <dl class="api-dl">
          <div><dt>ok</dt><dd><?= $statusOk ? 'true' : 'false' ?></dd></div>
          <div><dt>generated_at</dt><dd><?= api_viewer_h(api_viewer_date($viewerData['generated_at'] ?? '')) ?></dd></div>
          <div><dt>count</dt><dd><?= api_viewer_h((string) ($viewerData['count'] ?? count($rows))) ?> registros</dd></div>
        </dl>
      </article>
    </section>

    <?php if ($totalsConfig): ?>
      <section class="api-kpis">
        <?php foreach ($totalsConfig as $item):
            $field = (string) ($item['field'] ?? '');
            $kind = (string) ($item['kind'] ?? 'number');
            $value = $totals[$field] ?? 0;
            $suffix = (string) ($item['suffix'] ?? '');
            $formatted = $kind === 'money' ? api_viewer_money($value) : api_viewer_number($value, 2) . ($suffix !== '' ? ' ' . $suffix : '');
        ?>
          <article class="api-kpi <?= api_viewer_h((string) ($item['tone'] ?? '')) ?>">
            <span><?= api_viewer_h($item['label'] ?? $field) ?></span>
            <strong class="<?= (float) $value < 0 ? 'negative' : '' ?>"><?= api_viewer_h($formatted) ?></strong>
          </article>
        <?php endforeach; ?>
      </section>
    <?php endif; ?>

    <?php if ($warnings): ?>
      <section class="api-warning-panel">
        <h2>Advertencias (<?= count($warnings) ?>)</h2>
        <p>Se encontraron valores que requieren revisión sin modificar el dato original.</p>
        <div class="api-warning-list">
          <?php foreach ($warnings as $warning): ?>
            <article>
              <strong><?= api_viewer_h($warning['field'] ?? $warning['type'] ?? 'warning') ?></strong>
              <span>Sucursal: <?= api_viewer_h($warning['sucursal'] ?? 'Sin sucursal') ?></span>
              <span>Valor: <?= api_viewer_h(api_viewer_number($warning['value'] ?? 0, 2)) ?></span>
            </article>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>

    <section class="api-table-panel">
      <div class="api-table-head">
        <h2>Detalle por sucursal (<?= api_viewer_h((string) ($viewerData['count'] ?? count($rows))) ?> registros)</h2>
        <label class="api-search">
          <span>Buscar</span>
          <input type="search" placeholder="Periodo, contrato o sucursal" data-search>
        </label>
      </div>
      <div class="api-table-wrap">
        <table data-api-table>
          <thead>
            <tr>
              <?php foreach ($columns as $column): ?>
                <th class="<?= !empty($column['numeric']) ? 'num' : '' ?>"><?= api_viewer_h($column['label'] ?? $column['field'] ?? '') ?></th>
              <?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $row): ?>
              <tr data-search-text="<?= api_viewer_h(($row['periodo'] ?? '') . ' ' . ($row['contrato'] ?? '') . ' ' . ($row['sucursal'] ?? '')) ?>">
                <?php foreach ($columns as $column):
                    $field = (string) ($column['field'] ?? '');
                    $kind = (string) ($column['kind'] ?? 'text');
                    $value = api_viewer_value($row, $field);
                    $isNegative = is_numeric($value) && (float) $value < 0;
                    if ($kind === 'money') {
                        $display = api_viewer_money($value);
                    } elseif ($kind === 'number') {
                        $display = api_viewer_number($value, (int) ($column['decimals'] ?? 2));
                    } else {
                        $display = (string) ($value ?? '');
                    }
                ?>
                  <td class="<?= !empty($column['numeric']) ? 'num' : '' ?> <?= $isNegative ? 'negative' : '' ?>"><?= api_viewer_h($display) ?></td>
                <?php endforeach; ?>
              </tr>
            <?php endforeach; ?>
          </tbody>
          <?php if ($totals): ?>
            <tfoot>
              <tr>
                <?php foreach ($columns as $index => $column):
                    $field = (string) ($column['field'] ?? '');
                    $kind = (string) ($column['kind'] ?? 'text');
                    $hasTotal = array_key_exists($field, $totals);
                    $value = $totals[$field] ?? null;
                    $isNegative = is_numeric($value) && (float) $value < 0;
                    if ($index === 0) {
                        $display = 'TOTALES';
                    } elseif (!$hasTotal) {
                        $display = '';
                    } elseif ($kind === 'money') {
                        $display = api_viewer_money($value);
                    } elseif ($kind === 'number') {
                        $display = api_viewer_number($value, (int) ($column['decimals'] ?? 2));
                    } else {
                        $display = (string) $value;
                    }
                ?>
                  <td class="<?= !empty($column['numeric']) ? 'num' : '' ?> <?= $isNegative ? 'negative' : '' ?>"><?= api_viewer_h($display) ?></td>
                <?php endforeach; ?>
              </tr>
            </tfoot>
          <?php endif; ?>
        </table>
      </div>
    </section>
  </main>

  <script type="application/json" id="api-viewer-data"><?= $viewerJson ?: '{}' ?></script>
  <script src="<?= api_viewer_h($assetBase . 'api_viewer.js') ?>"></script>
</body>
</html>
