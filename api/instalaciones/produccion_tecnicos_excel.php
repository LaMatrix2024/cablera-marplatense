<?php
declare(strict_types=1);

date_default_timezone_set('America/Argentina/Buenos_Aires');

function xlsx_error(string $message, int $status = 400): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function xlsx_xml(string $value): string
{
    return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function xlsx_cell(string $ref, mixed $value, int $style = 0): string
{
    $styleAttr = $style > 0 ? ' s="' . $style . '"' : '';
    if (is_int($value) || is_float($value)) {
        return '<c r="' . $ref . '"' . $styleAttr . '><v>' . $value . '</v></c>';
    }

    $text = xlsx_xml((string) $value);
    return '<c r="' . $ref . '"' . $styleAttr . ' t="inlineStr"><is><t>' . $text . '</t></is></c>';
}

function xlsx_column_name(int $index): string
{
    $name = '';
    while ($index > 0) {
        $index--;
        $name = chr(65 + ($index % 26)) . $name;
        $index = intdiv($index, 26);
    }
    return $name;
}

function xlsx_download_name(string $name): string
{
    $clean = preg_replace('/[^A-Za-z0-9_.-]+/', '_', $name) ?: 'instalaciones_tecnicos.xlsx';
    return str_ends_with(strtolower($clean), '.xlsx') ? $clean : $clean . '.xlsx';
}

function xlsx_sheet_name(string $name, array &$used): string
{
    $clean = trim((string) preg_replace('/[\[\]\:\*\?\/\\\\]+/', ' ', $name));
    $clean = preg_replace('/\s+/', ' ', $clean) ?: 'Hoja';
    $clean = mb_substr($clean, 0, 31, 'UTF-8');
    $base = $clean;
    $suffix = 1;
    while (isset($used[mb_strtoupper($clean, 'UTF-8')])) {
        $tail = ' ' . ++$suffix;
        $clean = mb_substr($base, 0, 31 - mb_strlen($tail, 'UTF-8'), 'UTF-8') . $tail;
    }
    $used[mb_strtoupper($clean, 'UTF-8')] = true;
    return $clean;
}

function xlsx_normalized_sheets(array $payload): array
{
    $sourceSheets = $payload['sheets'] ?? null;
    if (!is_array($sourceSheets)) {
        $sourceSheets = [[
            'name' => 'Instalaciones',
            'headers' => $payload['headers'] ?? [],
            'rows' => $payload['rows'] ?? [],
        ]];
    }

    $usedNames = [];
    $sheets = [];
    $totalRows = 0;
    foreach ($sourceSheets as $index => $sheet) {
        if (!is_array($sheet)) {
            continue;
        }
        $headers = $sheet['headers'] ?? [];
        $rows = $sheet['rows'] ?? [];
        if (!is_array($headers) || !is_array($rows) || !$headers) {
            continue;
        }
        $totalRows += count($rows);
        $sheets[] = [
            'name' => xlsx_sheet_name((string) ($sheet['name'] ?? 'Hoja ' . ($index + 1)), $usedNames),
            'headers' => array_values($headers),
            'rows' => array_values($rows),
        ];
    }

    if (!$sheets) {
        xlsx_error('No hay datos para exportar.');
    }
    if ($totalRows > 20000 || count($sheets) > 20) {
        xlsx_error('Exportacion demasiado grande.');
    }
    foreach ($sheets as $sheet) {
        if (count($sheet['headers']) > 80) {
            xlsx_error('Exportacion demasiado grande.');
        }
    }

    return $sheets;
}

function xlsx_sheet_xml(array $headers, array $rows): string
{
    $sheetRows = [];
    $rowIndex = 1;
    $headerCells = [];
    foreach (array_values($headers) as $colIndex => $header) {
        $headerCells[] = xlsx_cell(xlsx_column_name($colIndex + 1) . $rowIndex, (string) $header, 1);
    }
    $sheetRows[] = '<row r="1">' . implode('', $headerCells) . '</row>';

    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $rowIndex++;
        $cells = [];
        foreach (array_values($row) as $colIndex => $value) {
            $style = $colIndex === 0 ? 0 : 2;
            $cells[] = xlsx_cell(xlsx_column_name($colIndex + 1) . $rowIndex, $value, $style);
        }
        $sheetRows[] = '<row r="' . $rowIndex . '">' . implode('', $cells) . '</row>';
    }

    $lastCol = xlsx_column_name(count($headers));
    $dimension = 'A1:' . $lastCol . max($rowIndex, 1);
    $cols = [];
    foreach ($headers as $index => $header) {
        $width = $index === 0 ? 34 : 13;
        $cols[] = '<col min="' . ($index + 1) . '" max="' . ($index + 1) . '" width="' . $width . '" customWidth="1"/>';
    }

    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<dimension ref="' . $dimension . '"/>'
        . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
        . '<cols>' . implode('', $cols) . '</cols>'
        . '<sheetData>' . implode('', $sheetRows) . '</sheetData>'
        . '<autoFilter ref="' . $dimension . '"/>'
        . '</worksheet>';
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    xlsx_error('Metodo no permitido.', 405);
}
if (!class_exists('ZipArchive')) {
    xlsx_error('ZipArchive no esta disponible en PHP.', 500);
}

$payload = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($payload)) {
    xlsx_error('Payload invalido.');
}

$filename = xlsx_download_name((string) ($payload['filename'] ?? 'instalaciones_tecnicos.xlsx'));
$sheets = xlsx_normalized_sheets($payload);

$workbookSheets = [];
$workbookRelationships = [];
$contentTypeSheets = [];
foreach ($sheets as $index => $sheet) {
    $sheetNumber = $index + 1;
    $workbookSheets[] = '<sheet name="' . xlsx_xml($sheet['name']) . '" sheetId="' . $sheetNumber . '" r:id="rId' . $sheetNumber . '"/>';
    $workbookRelationships[] = '<Relationship Id="rId' . $sheetNumber . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $sheetNumber . '.xml"/>';
    $contentTypeSheets[] = '<Override PartName="/xl/worksheets/sheet' . $sheetNumber . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
}
$stylesRelationId = count($sheets) + 1;

$workbookXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
    . '<sheets>' . implode('', $workbookSheets) . '</sheets>'
    . '</workbook>';

$stylesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
    . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/><color rgb="FFFFFFFF"/></font></fonts>'
    . '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF2563EB"/><bgColor indexed="64"/></patternFill></fill></fills>'
    . '<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border><left style="thin"><color rgb="FFD9E0EA"/></left><right style="thin"><color rgb="FFD9E0EA"/></right><top style="thin"><color rgb="FFD9E0EA"/></top><bottom style="thin"><color rgb="FFD9E0EA"/></bottom><diagonal/></border></borders>'
    . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
    . '<cellXfs count="3"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"/><xf numFmtId="4" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"/></cellXfs>'
    . '</styleSheet>';

$contentTypesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
    . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
    . '<Default Extension="xml" ContentType="application/xml"/>'
    . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
    . implode('', $contentTypeSheets)
    . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
    . '</Types>';

$relsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
    . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
    . '</Relationships>';

$workbookRelsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
    . implode('', $workbookRelationships)
    . '<Relationship Id="rId' . $stylesRelationId . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
    . '</Relationships>';

$tmp = tempnam(sys_get_temp_dir(), 'toa_xlsx_');
$zip = new ZipArchive();
if ($tmp === false || $zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
    xlsx_error('No se pudo crear el archivo XLSX.', 500);
}
$zip->addFromString('[Content_Types].xml', $contentTypesXml);
$zip->addFromString('_rels/.rels', $relsXml);
$zip->addFromString('xl/workbook.xml', $workbookXml);
$zip->addFromString('xl/_rels/workbook.xml.rels', $workbookRelsXml);
$zip->addFromString('xl/styles.xml', $stylesXml);
foreach ($sheets as $index => $sheet) {
    $zip->addFromString('xl/worksheets/sheet' . ($index + 1) . '.xml', xlsx_sheet_xml($sheet['headers'], $sheet['rows']));
}
$zip->close();

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($tmp));
readfile($tmp);
@unlink($tmp);
