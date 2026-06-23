<?php

require_once __DIR__ . '/../../../config/conexion.php';
require_once __DIR__ . '/_obras_query.php';

if (!class_exists('ZipArchive')) {
    http_response_code(500);
    die('ZipArchive no está habilitado en PHP.');
}

try {
    $detalle = obtenerDetalleObras($pdo_laboratorio, $_GET);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    die($e->getMessage());
} catch (Throwable $e) {
    http_response_code(500);
    error_log('Excel de obras: ' . $e->getMessage());
    die('No se pudo generar el archivo XLSX.');
}

function obrasXml($value): string
{
    return htmlspecialchars((string)$value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function obrasStr(string $ref, $value, int $style = 0): string
{
    return '<c r="' . $ref . '" t="inlineStr" s="' . $style . '"><is><t>'
        . obrasXml($value) . '</t></is></c>';
}

function obrasNum(string $ref, $value, int $style = 0): string
{
    return '<c r="' . $ref . '" s="' . $style . '"><v>'
        . (float)$value . '</v></c>';
}

function obrasFormula(string $ref, string $formula, int $style = 0, $cachedValue = 0): string
{
    return '<c r="' . $ref . '" s="' . $style . '"><f>'
        . obrasXml($formula) . '</f><v>' . (float)$cachedValue . '</v></c>';
}

function periodoLegible(string $periodo): string
{
    return preg_match('/^(\d{4})(\d{2})$/', $periodo, $m) ? $m[2] . '/' . $m[1] : $periodo;
}

$rows = $detalle['rows'];
$sheetRows = '';
$sheetRows .= '<row r="1" ht="28" customHeight="1">' . obrasStr('A1', 'LCM · La Cablera Marplatense', 1) . '</row>';
$sheetRows .= '<row r="2" ht="24" customHeight="1">' . obrasStr('A2', 'Detalle de producción por Cuadrilla/Contratista', 2) . '</row>';
$sheetRows .= '<row r="3" ht="24" customHeight="1">' . obrasStr('A3', 'Contratista: ' . $detalle['contratista'], 11) . '</row>';

$filtros = 'Períodos: ' . implode(', ', array_map('periodoLegible', $detalle['periodos']))
    . ' | Zona: ' . ($detalle['zona'] ?: 'Todas')
    . ' | Tareas: ' . $detalle['tipo'];
$sheetRows .= '<row r="4">' . obrasStr('A4', $filtros, 3) . '</row>';

$headers = ['SIGEST', 'Sucursal', 'Descripción', 'Tarea', 'HS L', 'HS N', 'HS LZ', 'HS LC', 'Total HB', 'Precio', 'Valor Venta'];
$cols = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K'];
$sheetRows .= '<row r="6" ht="24" customHeight="1">';
foreach ($headers as $index => $header) {
    $sheetRows .= obrasStr($cols[$index] . '6', $header, 4);
}
$sheetRows .= '</row>';

$rowNumber = 7;
foreach ($rows as $row) {
    $sheetRows .= '<row r="' . $rowNumber . '">';
    $sheetRows .= ctype_digit((string)$row['sigest'])
        ? obrasNum('A' . $rowNumber, (int)$row['sigest'], 12)
        : obrasStr('A' . $rowNumber, $row['sigest'], 12);
    $sheetRows .= obrasStr('B' . $rowNumber, $row['sucursal'], 5);
    $sheetRows .= obrasStr('C' . $rowNumber, $row['descripcion'], 5);
    $sheetRows .= obrasStr('D' . $rowNumber, $row['tipo'], 5);
    $sheetRows .= obrasNum('E' . $rowNumber, $row['nl'], 6);
    $sheetRows .= obrasNum('F' . $rowNumber, $row['nn'], 6);
    $sheetRows .= obrasNum('G' . $rowNumber, $row['nlz'], 6);
    $sheetRows .= obrasNum('H' . $rowNumber, $row['nlc'], 6);
    $sheetRows .= obrasNum('I' . $rowNumber, $row['total_hb'], 6);
    $sheetRows .= obrasNum('J' . $rowNumber, $row['precio'], 13);
    $sheetRows .= obrasNum('K' . $rowNumber, $row['valor_venta'], 7);
    $sheetRows .= '</row>';
    $rowNumber++;
}

$lastDataRow = max(6, $rowNumber - 1);
$hasRows = count($rows) > 0;
$sheetRows .= '<row r="' . $rowNumber . '"/>';
$rowNumber++;

$sheetRows .= '<row r="' . $rowNumber . '" ht="22" customHeight="1">';
$sheetRows .= obrasStr('A' . $rowNumber, 'Total', 8);
$sheetRows .= obrasStr('B' . $rowNumber, '', 8);
$sheetRows .= obrasStr('C' . $rowNumber, '', 8);
$sheetRows .= obrasStr('D' . $rowNumber, '', 8);
$sheetRows .= $hasRows ? obrasFormula('E' . $rowNumber, 'SUBTOTAL(109,E7:E' . $lastDataRow . ')', 9, $detalle['totales']['nl']) : obrasNum('E' . $rowNumber, 0, 9);
$sheetRows .= $hasRows ? obrasFormula('F' . $rowNumber, 'SUBTOTAL(109,F7:F' . $lastDataRow . ')', 9, $detalle['totales']['nn']) : obrasNum('F' . $rowNumber, 0, 9);
$sheetRows .= $hasRows ? obrasFormula('G' . $rowNumber, 'SUBTOTAL(109,G7:G' . $lastDataRow . ')', 9, $detalle['totales']['nlz']) : obrasNum('G' . $rowNumber, 0, 9);
$sheetRows .= $hasRows ? obrasFormula('H' . $rowNumber, 'SUBTOTAL(109,H7:H' . $lastDataRow . ')', 9, $detalle['totales']['nlc']) : obrasNum('H' . $rowNumber, 0, 9);
$sheetRows .= $hasRows ? obrasFormula('I' . $rowNumber, 'SUBTOTAL(109,I7:I' . $lastDataRow . ')', 9, $detalle['totales']['total_hb']) : obrasNum('I' . $rowNumber, 0, 9);
$sheetRows .= obrasStr('J' . $rowNumber, '', 8);
$sheetRows .= $hasRows ? obrasFormula('K' . $rowNumber, 'SUBTOTAL(109,K7:K' . $lastDataRow . ')', 10, $detalle['totales']['valor_venta']) : obrasNum('K' . $rowNumber, 0, 10);
$sheetRows .= '</row>';

$sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"
           xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
    <sheetPr><pageSetUpPr fitToPage="1"/></sheetPr>
    <dimension ref="A1:K' . $rowNumber . '"/>
    <sheetViews>
        <sheetView workbookViewId="0" showGridLines="0">
            <pane ySplit="6" topLeftCell="A7" activePane="bottomLeft" state="frozen"/>
            <selection pane="bottomLeft"/>
        </sheetView>
    </sheetViews>
    <sheetFormatPr defaultRowHeight="19"/>
    <cols>
        <col min="1" max="1" width="16" customWidth="1"/>
        <col min="2" max="2" width="24" customWidth="1"/>
        <col min="3" max="3" width="58" customWidth="1"/>
        <col min="4" max="4" width="14" customWidth="1"/>
        <col min="5" max="9" width="12" customWidth="1"/>
        <col min="10" max="11" width="20" customWidth="1"/>
    </cols>
    <sheetData>' . $sheetRows . '</sheetData>
    <autoFilter ref="A6:K' . $lastDataRow . '"/>
    <mergeCells count="4">
        <mergeCell ref="A1:K1"/>
        <mergeCell ref="A2:K2"/>
        <mergeCell ref="A3:K3"/>
        <mergeCell ref="A4:K4"/>
    </mergeCells>
    <pageMargins left="0.3" right="0.3" top="0.5" bottom="0.5" header="0.2" footer="0.2"/>
    <pageSetup orientation="landscape" fitToWidth="1" fitToHeight="0" paperSize="9"/>
</worksheet>';

$styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
    <numFmts count="2">
        <numFmt numFmtId="164" formatCode="#,##0.00"/>
        <numFmt numFmtId="165" formatCode="$ #,##0.00"/>
    </numFmts>
    <fonts count="6">
        <font><sz val="11"/><name val="Calibri"/></font>
        <font><b/><sz val="16"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>
        <font><b/><sz val="13"/><color rgb="FFFF6B35"/><name val="Calibri"/></font>
        <font><sz val="10"/><color rgb="FF777777"/><name val="Calibri"/></font>
        <font><b/><sz val="11"/><color rgb="FF111111"/><name val="Calibri"/></font>
        <font><b/><sz val="14"/><color rgb="FF111111"/><name val="Calibri"/></font>
    </fonts>
    <fills count="5">
        <fill><patternFill patternType="none"/></fill>
        <fill><patternFill patternType="gray125"/></fill>
        <fill><patternFill patternType="solid"><fgColor rgb="FF111111"/></patternFill></fill>
        <fill><patternFill patternType="solid"><fgColor rgb="FFFF6B35"/></patternFill></fill>
        <fill><patternFill patternType="solid"><fgColor rgb="FFFFF3E8"/></patternFill></fill>
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
    <cellXfs count="14">
        <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
        <xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>
        <xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/>
        <xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1"/>
        <xf numFmtId="0" fontId="4" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment horizontal="center" vertical="center"/></xf>
        <xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"><alignment vertical="top" wrapText="1"/></xf>
        <xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"><alignment horizontal="right"/></xf>
        <xf numFmtId="165" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"><alignment horizontal="right"/></xf>
        <xf numFmtId="0" fontId="4" fillId="4" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"/>
        <xf numFmtId="164" fontId="4" fillId="4" borderId="1" xfId="0" applyFont="1" applyFill="1" applyNumberFormat="1" applyBorder="1"/>
        <xf numFmtId="165" fontId="4" fillId="4" borderId="1" xfId="0" applyFont="1" applyFill="1" applyNumberFormat="1" applyBorder="1"/>
        <xf numFmtId="0" fontId="5" fillId="0" borderId="0" xfId="0" applyFont="1"/>
        <xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"><alignment horizontal="center" vertical="center"/></xf>
        <xf numFmtId="1" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"><alignment horizontal="center" vertical="center"/></xf>
    </cellXfs>
    <cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>
</styleSheet>';

$workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"
          xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
    <sheets><sheet name="Detalle de obras" sheetId="1" r:id="rId1"/></sheets>
    <calcPr calcId="191029" calcMode="auto" fullCalcOnLoad="1" forceFullCalc="1"/>
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

$safeContractor = preg_replace('/[^A-Za-z0-9_-]+/', '_', $detalle['contratista']);
$filename = 'obras_' . trim($safeContractor, '_') . '_' . date('Ymd_His') . '.xlsx';
$tmp = tempnam(sys_get_temp_dir(), 'lcm_obras_');
$zip = new ZipArchive();

if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
    http_response_code(500);
    die('No se pudo crear el archivo XLSX.');
}

$zip->addFromString('[Content_Types].xml', $contentTypes);
$zip->addFromString('_rels/.rels', $rels);
$zip->addFromString('xl/workbook.xml', $workbook);
$zip->addFromString('xl/_rels/workbook.xml.rels', $workbookRels);
$zip->addFromString('xl/styles.xml', $styles);
$zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
$zip->close();

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($tmp));
header('Cache-Control: max-age=0');

readfile($tmp);
unlink($tmp);
exit;
