<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/conexion.php';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if (!class_exists('ZipArchive')) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'ZipArchive no está habilitado en PHP.';
    exit;
}

function normalizePeriodoParamLocal(string $value): ?string
{
    $value = trim($value);
    if (!preg_match('/^(\d{4})-?(\d{1,2})$/', $value, $m)) {
        return null;
    }
    return $m[1] . '-' . str_pad((string) (int) $m[2], 2, '0', STR_PAD_LEFT);
}

function splitList(?string $value): array
{
    if ($value === null || trim($value) === '') {
        return [];
    }
    return array_values(array_filter(array_map('trim', explode(',', $value))));
}

function periodoExpr(string $campo): string
{
    return "CASE
        WHEN {$campo} IS NULL THEN NULL
        WHEN CHAR_LENGTH(CAST({$campo} AS CHAR)) = 5 THEN CONCAT(LEFT(CAST({$campo} AS CHAR), 4), '-0', RIGHT(CAST({$campo} AS CHAR), 1))
        WHEN CHAR_LENGTH(CAST({$campo} AS CHAR)) = 6 THEN CONCAT(LEFT(CAST({$campo} AS CHAR), 4), '-', RIGHT(CAST({$campo} AS CHAR), 2))
        ELSE NULL
    END";
}

function xlsxEscape(string $value): string
{
    return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function cellInline(string $ref, string $value, int $style = 0): string
{
    return '<c r="' . $ref . '" t="inlineStr" s="' . $style . '"><is><t>' . xlsxEscape($value) . '</t></is></c>';
}

function cellNum(string $ref, $value, int $style = 0): string
{
    return '<c r="' . $ref . '" s="' . $style . '"><v>' . ((float) $value) . '</v></c>';
}

function colLetter(int $index): string
{
    $letter = '';
    while ($index > 0) {
        $index--;
        $letter = chr(65 + ($index % 26)) . $letter;
        $index = intdiv($index, 26);
    }
    return $letter;
}

function sanitizeFilename(string $value, string $fallback = 'detalle'): string
{
    $value = trim($value);
    $value = preg_replace('/\s+/', '_', $value);
    $value = preg_replace('/[^a-z0-9_-]+/i', '_', $value);
    $value = trim((string) $value, '_');
    return $value !== '' ? $value : $fallback;
}

$periodos = splitList((string) ($_GET['periodos'] ?? $_GET['periodo'] ?? ''));
$periodosNorm = [];
foreach ($periodos as $periodo) {
    $norm = normalizePeriodoParamLocal($periodo);
    if ($norm !== null) {
        $periodosNorm[] = $norm;
    }
}
$periodosNorm = array_values(array_unique($periodosNorm));

$negocio = trim((string) ($_GET['negocio'] ?? ''));
$sucursal = trim((string) ($_GET['sucursal'] ?? ''));
$grupo = trim((string) ($_GET['grupo'] ?? ''));
$codigo = trim((string) ($_GET['codigo'] ?? ''));
$nombre = trim((string) ($_GET['nombre'] ?? ''));
$title = $nombre !== '' ? $nombre : ($grupo !== '' ? $grupo : 'Detalle');

$params = [];
$where = "WHERE categoria = '1-TELEFONIA' AND sub_categoria = '1-TASA'";
$periodExpr = periodoExpr('periodo');
if ($periodosNorm) {
    $placeholders = implode(',', array_fill(0, count($periodosNorm), '?'));
    $where .= " AND {$periodExpr} IN ($placeholders)";
    $params = array_merge($params, $periodosNorm);
}
if ($negocio !== '') {
    $where .= " AND COALESCE(NULLIF(TRIM(negocio), ''), 'SIN NEGOCIO') = ?";
    $params[] = $negocio;
}
if ($sucursal !== '') {
    $where .= " AND COALESCE(NULLIF(TRIM(sucursal), ''), 'SIN SUCURSAL') = ?";
    $params[] = $sucursal;
}
if ($grupo !== '') {
    $where .= " AND COALESCE(NULLIF(TRIM(grupo_cuenta), ''), 'SIN GRUPO') = ?";
    $params[] = $grupo;
}
if ($codigo !== '') {
    $where .= " AND COALESCE(NULLIF(TRIM(codigo_cuenta), ''), '—') = ?";
    $params[] = $codigo;
}
if ($nombre !== '') {
    $where .= " AND COALESCE(NULLIF(TRIM(nombre_cuenta), ''), 'SIN NOMBRE') = ?";
    $params[] = $nombre;
}

$sql = "
    SELECT
        COALESCE(NULLIF(TRIM(ot_agrupada), ''), 'SIN OT AGRUPADA') AS ot_agrupada,
        COALESCE(NULLIF(TRIM(ot_nombre), ''), 'SIN OT NOMBRE') AS ot_nombre,
        COALESCE(NULLIF(TRIM(detalle), ''), 'SIN DETALLE') AS detalle,
        SUM(COALESCE(total, 0)) AS total_original,
        SUM(COALESCE(ars_ajustados, 0)) AS total_ajustado
    FROM raw_economico_provisorio
    $where
    GROUP BY
        COALESCE(NULLIF(TRIM(ot_agrupada), ''), 'SIN OT AGRUPADA'),
        COALESCE(NULLIF(TRIM(ot_nombre), ''), 'SIN OT NOMBRE'),
        COALESCE(NULLIF(TRIM(detalle), ''), 'SIN DETALLE')
    ORDER BY
        COALESCE(NULLIF(TRIM(ot_agrupada), ''), 'SIN OT AGRUPADA'),
        COALESCE(NULLIF(TRIM(ot_nombre), ''), 'SIN OT NOMBRE'),
        COALESCE(NULLIF(TRIM(detalle), ''), 'SIN DETALLE')
";

$stmt = $pdo_laboratorio->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalOriginal = 0.0;
$totalAjustado = 0.0;
foreach ($rows as $row) {
    $totalOriginal += (float) ($row['total_original'] ?? 0);
    $totalAjustado += (float) ($row['total_ajustado'] ?? 0);
}

$headers = ['OT agrupada', 'OT nombre', 'Importe original', 'Importe ajustado', 'Detalle'];
$sheetRows = '';
$sheetRows .= '<row r="1" ht="24" customHeight="1">' . cellInline('A1', $title, 1) . '</row>';
$sheetRows .= '<row r="2" ht="18" customHeight="1">' . cellInline('A2', $codigo !== '' ? 'Código ' . $codigo : 'Detalle', 2) . '</row>';
$sheetRows .= '<row r="3" ht="18" customHeight="1">';
$sheetRows .= cellInline('A3', 'Registros: ' . count($rows), 2);
$sheetRows .= cellInline('B3', 'Importe original: ', 2);
$sheetRows .= cellNum('C3', $totalOriginal, 2);
$sheetRows .= cellInline('D3', 'Importe ajustado: ', 2);
$sheetRows .= cellNum('E3', $totalAjustado, 2);
$sheetRows .= '</row>';

$sheetRows .= '<row r="5" ht="20" customHeight="1">';
foreach ($headers as $idx => $header) {
    $sheetRows .= cellInline(colLetter($idx + 1) . '5', $header, 3);
}
$sheetRows .= '</row>';

$rowNum = 6;
foreach ($rows as $row) {
    $sheetRows .= '<row r="' . $rowNum . '" ht="18" customHeight="1">';
    $sheetRows .= cellInline('A' . $rowNum, (string) ($row['ot_agrupada'] ?? 'SIN OT AGRUPADA'), 4);
    $sheetRows .= cellInline('B' . $rowNum, (string) ($row['ot_nombre'] ?? 'SIN OT NOMBRE'), 4);
    $sheetRows .= cellNum('C' . $rowNum, $row['total_original'] ?? 0, 5);
    $sheetRows .= cellNum('D' . $rowNum, $row['total_ajustado'] ?? 0, 5);
    $sheetRows .= cellInline('E' . $rowNum, (string) ($row['detalle'] ?? 'SIN DETALLE'), 4);
    $sheetRows .= '</row>';
    $rowNum++;
}

if (count($rows) === 0) {
    $sheetRows .= '<row r="6" ht="18" customHeight="1">' . cellInline('A6', 'Sin datos para exportar.', 4) . '</row>';
}

$lastDataRow = max(6, $rowNum - 1);

$sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"
           xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
    <sheetViews>
        <sheetView workbookViewId="0" showGridLines="0">
            <pane ySplit="5" topLeftCell="A6" activePane="bottomLeft" state="frozen"/>
            <selection pane="bottomLeft"/>
        </sheetView>
    </sheetViews>
    <sheetFormatPr defaultRowHeight="18"/>
    <dimension ref="A1:E' . $lastDataRow . '"/>
    <cols>
        <col min="1" max="1" width="18" customWidth="1"/>
        <col min="2" max="2" width="34" customWidth="1"/>
        <col min="3" max="3" width="18" customWidth="1"/>
        <col min="4" max="4" width="18" customWidth="1"/>
        <col min="5" max="5" width="68" customWidth="1"/>
    </cols>
    <sheetData>' . $sheetRows . '</sheetData>
    <autoFilter ref="A5:E' . $lastDataRow . '"/>
    <pageMargins left="0.3" right="0.3" top="0.5" bottom="0.5" header="0.2" footer="0.2"/>
    <pageSetup orientation="landscape" fitToWidth="1" fitToHeight="0" paperSize="9"/>
</worksheet>';

$styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
    <numFmts count="1">
        <numFmt numFmtId="164" formatCode="#,##0;[Red]-#,##0"/>
    </numFmts>
    <fonts count="4">
        <font><sz val="11"/><name val="Calibri"/></font>
        <font><b/><sz val="15"/><name val="Calibri"/></font>
        <font><sz val="10"/><color rgb="FF666666"/><name val="Calibri"/></font>
        <font><b/><sz val="11"/><name val="Calibri"/></font>
    </fonts>
    <fills count="5">
        <fill><patternFill patternType="none"/></fill>
        <fill><patternFill patternType="gray125"/></fill>
        <fill><patternFill patternType="solid"><fgColor rgb="FFF3F6FA"/></patternFill></fill>
        <fill><patternFill patternType="solid"><fgColor rgb="FFEFEFEF"/></patternFill></fill>
        <fill><patternFill patternType="solid"><fgColor rgb="FFF7FAF5"/></patternFill></fill>
    </fills>
    <borders count="2">
        <border/>
        <border>
            <left style="thin"><color rgb="FFE0E0E0"/></left>
            <right style="thin"><color rgb="FFE0E0E0"/></right>
            <top style="thin"><color rgb="FFE0E0E0"/></top>
            <bottom style="thin"><color rgb="FFE0E0E0"/></bottom>
        </border>
    </borders>
    <cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
    <cellXfs count="6">
        <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
        <xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>
        <xf numFmtId="0" fontId="2" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>
        <xf numFmtId="0" fontId="3" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"/>
        <xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>
        <xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"><alignment horizontal="right"/></xf>
    </cellXfs>
</styleSheet>';

$workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"
          xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
    <sheets>
        <sheet name="Detalle" sheetId="1" r:id="rId1"/>
    </sheets>
</workbook>';

$contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
    <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
    <Default Extension="xml" ContentType="application/xml"/>
    <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
    <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
    <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
</Types>';

$rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>';

$workbookRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
    <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>';

$tmp = tempnam(sys_get_temp_dir(), 'detalle_xlsx_v3_');
if ($tmp === false) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'No se pudo preparar el archivo temporal.';
    exit;
}

$zip = new ZipArchive();
if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'No se pudo crear el archivo XLSX.';
    @unlink($tmp);
    exit;
}

$zip->addFromString('[Content_Types].xml', $contentTypes);
$zip->addFromString('_rels/.rels', $rels);
$zip->addFromString('xl/workbook.xml', $workbook);
$zip->addFromString('xl/_rels/workbook.xml.rels', $workbookRels);
$zip->addFromString('xl/styles.xml', $styles);
$zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
$zip->close();

$filename = sanitizeFilename($codigo !== '' ? $codigo : $title) . '.xlsx';
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($tmp));
readfile($tmp);
@unlink($tmp);
exit;
