<?php

require_once __DIR__ . '/../../../config/conexion.php';

if (!class_exists('ZipArchive')) {
    die('ZipArchive no está habilitado en PHP.');
}

$periodosParam = $_GET['periodos'] ?? '';
$zona = $_GET['zona'] ?? null;
$tipo = $_GET['tipo'] ?? 'TODAS';
$tipoContratista = $_GET['tipo_contratista'] ?? 'TODOS';
$contratista = $_GET['contratista'] ?? null;

$periodos = array_values(array_filter(array_map('trim', explode(',', $periodosParam))));

if (!$periodos) {
    die('Sin períodos seleccionados.');
}

$placeholders = implode(',', array_fill(0, count($periodos), '?'));
$params = $periodos;

$where = "WHERE periodo IN ($placeholders)";

if ($zona && $zona !== 'Total compañía') {
    $where .= "
        AND (
            CASE
                WHEN c_responsable = 'AMBA' THEN 'LOMAS'
                WHEN c_responsable = 'LA PLATA' THEN 'La Plata'
                WHEN c_responsable = 'MDP' THEN 'Pcia. Bs. As. y Patag.'
                ELSE COALESCE(c_responsable, 'SIN ZONA')
            END
        ) = ?
    ";
    $params[] = $zona;
}

if ($tipoContratista && strtoupper($tipoContratista) !== 'TODOS') {
    if (strtoupper($tipoContratista) === 'CONT') {
        $where .= " AND c_tipo_contratista IN ('CONT', 'CTTA') ";
    } else {
        $where .= " AND c_tipo_contratista = ? ";
        $params[] = $tipoContratista;
    }
}

if ($tipo && strtoupper($tipo) !== 'TODAS') {
    if (strtoupper($tipo) === 'PTRS') {
        $where .= " AND c_cod_pl_tare_tipo LIKE '%PTR%' ";
    } elseif (strtoupper($tipo) === 'OCRAS') {
        $where .= " AND c_cod_pl_tare_tipo LIKE '%OCRA%' ";
    } elseif (strtoupper($tipo) === 'MAREA') {
        $where .= " AND c_cod_pl_tare_tipo LIKE '%MAREA%' ";
    }
}

if ($contratista) {
    $where .= " AND c_nom_pl_cont LIKE ? ";
    $params[] = '%' . $contratista . '%';
}

$sql = "
    SELECT
        COALESCE(c_nom_pl_cont, 'SIN CONTRATISTA') AS contratista,
        SUM(CASE WHEN c_cod_pl_tare_tipo LIKE '%PTR%' THEN n_totalhs_tasa ELSE 0 END) AS hb_ptrs,
        SUM(CASE WHEN c_cod_pl_tare_tipo LIKE '%OCRA%' THEN n_totalhs_tasa ELSE 0 END) AS hb_ocras,
        SUM(n_totalhs_tasa) AS total_hb,
        SUM(n_valor_tasa_total) AS venta
    FROM raw_produccion_planta
    $where
    GROUP BY contratista
    ORDER BY total_hb DESC, contratista
";

$stmt = $pdo_laboratorio->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$totalHb = array_sum(array_map(fn($r) => (float)$r['total_hb'], $rows));

function x($value): string
{
    return htmlspecialchars((string)$value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function cStr($ref, $value, $style = 0): string
{
    return '<c r="' . $ref . '" t="inlineStr" s="' . $style . '"><is><t>' . x($value) . '</t></is></c>';
}

function cNum($ref, $value, $style = 0): string
{
    return '<c r="' . $ref . '" s="' . $style . '"><v>' . $value . '</v></c>';
}

function cFormula(string $ref, string $formula, int $style = 0, $cachedValue = 0): string
{
    return '<c r="' . $ref . '" s="' . $style . '"><f>'
        . x($formula) . '</f><v>' . (float)$cachedValue . '</v></c>';
}

function periodoLegible(string $periodo): string
{
    return preg_match('/^(\d{4})(\d{2})$/', $periodo, $m) ? $m[2] . '/' . $m[1] : $periodo;
}

$sheetRows = '';

$sheetRows .= '<row r="1" ht="28" customHeight="1">' . cStr('A1', 'LCM · La Cablera Marplatense', 1) . '</row>';
$sheetRows .= '<row r="2" ht="24" customHeight="1">' . cStr('A2', 'Producción Planta Externa', 2) . '</row>';
$sheetRows .= '<row r="3" ht="24" customHeight="1">' . cStr('A3', 'Resumen por contratista', 13) . '</row>';

$detalle = 'Períodos: ' . implode(', ', array_map('periodoLegible', $periodos))
    . ' | Zona: ' . ($zona ?: 'Todas')
    . ' | Tipo de contratista: ' . $tipoContratista
    . ' | Tareas: ' . $tipo
    . ' | Contratista: ' . ($contratista ?: 'Todos');

$sheetRows .= '<row r="4">' . cStr('A4', $detalle, 3) . '</row>';

$headers = ['Contratista', 'HB PTRS', 'HB OCRAS', 'Total HB', 'Venta', '% Sobre total'];

$sheetRows .= '<row r="6" ht="24" customHeight="1">';
$cols = ['A', 'B', 'C', 'D', 'E', 'F'];
foreach ($headers as $i => $h) {
    $sheetRows .= cStr($cols[$i] . '6', $h, 4);
}
$sheetRows .= '</row>';

$rowNum = 7;
$tPtr = 0;
$tOcra = 0;
$tHb = 0;
$tVenta = 0;

foreach ($rows as $r) {
    $ptr = (float)$r['hb_ptrs'];
    $ocra = (float)$r['hb_ocras'];
    $hb = (float)$r['total_hb'];
    $venta = (float)$r['venta'];
    $share = $totalHb > 0 ? $hb / $totalHb : 0;

    $tPtr += $ptr;
    $tOcra += $ocra;
    $tHb += $hb;
    $tVenta += $venta;

    $sheetRows .= '<row r="' . $rowNum . '" ht="30" customHeight="1">';
    $sheetRows .= cStr('A' . $rowNum, $r['contratista'], 5);
    $sheetRows .= cNum('B' . $rowNum, $ptr, 6);
    $sheetRows .= cNum('C' . $rowNum, $ocra, 6);
    $sheetRows .= cNum('D' . $rowNum, $hb, 6);
    $sheetRows .= cNum('E' . $rowNum, $venta, 7);
    $sheetRows .= cNum('F' . $rowNum, $share, 8);
    $sheetRows .= '</row>';

    $rowNum++;
}

$lastDataRow = max(6, $rowNum - 1);
$hasRows = count($rows) > 0;
$sheetRows .= '<row r="' . $rowNum . '"/>';
$rowNum++;

$sheetRows .= '<row r="' . $rowNum . '" ht="22" customHeight="1">';
$sheetRows .= cStr('A' . $rowNum, 'Total', 9);
$sheetRows .= $hasRows ? cFormula('B' . $rowNum, 'SUBTOTAL(109,B7:B' . $lastDataRow . ')', 10, $tPtr) : cNum('B' . $rowNum, 0, 10);
$sheetRows .= $hasRows ? cFormula('C' . $rowNum, 'SUBTOTAL(109,C7:C' . $lastDataRow . ')', 10, $tOcra) : cNum('C' . $rowNum, 0, 10);
$sheetRows .= $hasRows ? cFormula('D' . $rowNum, 'SUBTOTAL(109,D7:D' . $lastDataRow . ')', 10, $tHb) : cNum('D' . $rowNum, 0, 10);
$sheetRows .= $hasRows ? cFormula('E' . $rowNum, 'SUBTOTAL(109,E7:E' . $lastDataRow . ')', 11, $tVenta) : cNum('E' . $rowNum, 0, 11);
$sheetRows .= cNum('F' . $rowNum, 1, 12);
$sheetRows .= '</row>';

$lastRow = $rowNum;

$sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"
           xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
    <sheetPr><pageSetUpPr fitToPage="1"/></sheetPr>
    <dimension ref="A1:F' . $lastRow . '"/>
    <sheetViews>
        <sheetView workbookViewId="0" showGridLines="0">
            <pane ySplit="6" topLeftCell="A7" activePane="bottomLeft" state="frozen"/>
            <selection pane="bottomLeft"/>
        </sheetView>
    </sheetViews>
    <sheetFormatPr defaultRowHeight="19"/>
    <cols>
        <col min="1" max="1" width="42" customWidth="1"/>
        <col min="2" max="4" width="13" customWidth="1"/>
        <col min="5" max="5" width="18" customWidth="1"/>
        <col min="6" max="6" width="16" customWidth="1"/>
    </cols>
    <sheetData>' . $sheetRows . '</sheetData>
    <autoFilter ref="A6:F' . $lastDataRow . '"/>
    <mergeCells count="4">
        <mergeCell ref="A1:F1"/>
        <mergeCell ref="A2:F2"/>
        <mergeCell ref="A3:F3"/>
        <mergeCell ref="A4:F4"/>
    </mergeCells>
    <pageMargins left="0.3" right="0.3" top="0.5" bottom="0.5" header="0.2" footer="0.2"/>
    <pageSetup orientation="landscape" fitToWidth="1" fitToHeight="0" paperSize="9"/>
</worksheet>';

$styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
    <numFmts count="3">
        <numFmt numFmtId="164" formatCode="#,##0.00"/>
        <numFmt numFmtId="165" formatCode="$ #,##0.00"/>
        <numFmt numFmtId="166" formatCode="0.00%"/>
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
    <cellStyleXfs count="1">
        <xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>
    </cellStyleXfs>
    <cellXfs count="14">
        <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
        <xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>
        <xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/>
        <xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1"/>
        <xf numFmtId="0" fontId="4" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment horizontal="center"/></xf>
        <xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>
        <xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right"/></xf>
        <xf numFmtId="165" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right"/></xf>
        <xf numFmtId="166" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right"/></xf>
        <xf numFmtId="0" fontId="4" fillId="4" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"/>
        <xf numFmtId="164" fontId="4" fillId="4" borderId="1" xfId="0" applyFont="1" applyFill="1" applyNumberFormat="1" applyBorder="1"/>
        <xf numFmtId="165" fontId="4" fillId="4" borderId="1" xfId="0" applyFont="1" applyFill="1" applyNumberFormat="1" applyBorder="1"/>
        <xf numFmtId="166" fontId="4" fillId="4" borderId="1" xfId="0" applyFont="1" applyFill="1" applyNumberFormat="1" applyBorder="1"/>
        <xf numFmtId="0" fontId="5" fillId="0" borderId="0" xfId="0" applyFont="1"/>
    </cellXfs>
    <cellStyles count="1">
        <cellStyle name="Normal" xfId="0" builtinId="0"/>
    </cellStyles>
</styleSheet>';

$workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"
          xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
    <sheets>
        <sheet name="Producción Planta" sheetId="1" r:id="rId1"/>
    </sheets>
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
    <Relationship Id="rId1"
                  Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument"
                  Target="xl/workbook.xml"/>
</Relationships>';

$workbookRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1"
                  Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet"
                  Target="worksheets/sheet1.xml"/>
    <Relationship Id="rId2"
                  Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles"
                  Target="styles.xml"/>
</Relationships>';

$filename = 'produccion_planta_' . date('Ymd_His') . '.xlsx';
$tmp = tempnam(sys_get_temp_dir(), 'lcm_xlsx_');

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
