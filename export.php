<?php
require_once __DIR__ . '/lib/functions.php';

$year = isset($_GET['jahr']) ? (int)$_GET['jahr'] : 0;
if ($year < 2000) {
    http_response_code(400);
    echo 'Ungültiges Jahr.';
    exit;
}

ensure_year_exists($year);
$licenses = get_licensees_for_year($year);

$format = strtolower($_GET['format'] ?? 'csv');
if ($format !== 'xlsx') {
    $format = 'csv';
}

$columns = [
    'Lizenz-ID' => fn(array $row): string => (string)($row['lizenz_id'] ?? ''),
    'Nachname' => fn(array $row): string => trim((string)($row['nachname'] ?? '')),
    'Vorname' => fn(array $row): string => trim((string)($row['vorname'] ?? '')),
    'Straße' => fn(array $row): string => trim((string)($row['strasse'] ?? '')),
    'PLZ' => fn(array $row): string => trim((string)($row['plz'] ?? '')),
    'Ort' => fn(array $row): string => trim((string)($row['ort'] ?? '')),
    'Telefon' => fn(array $row): string => trim((string)($row['telefon'] ?? '')),
    'E-Mail' => fn(array $row): string => trim((string)($row['email'] ?? '')),
    'Fischerkartennummer' => fn(array $row): string => trim((string)($row['fischerkartennummer'] ?? '')),
    'Lizenztyp' => fn(array $row): string => trim((string)($row['lizenztyp'] ?? '')),
    'Kosten (€)' => fn(array $row): string => format_decimal($row['kosten'] ?? null),
    'Trinkgeld (€)' => fn(array $row): string => format_decimal($row['trinkgeld'] ?? null),
    'Gesamt (€)' => fn(array $row): string => format_decimal($row['gesamt'] ?? null),
    'Zahlungsdatum' => fn(array $row): string => format_export_date($row['zahlungsdatum'] ?? null),
    'Lizenznotizen' => fn(array $row): string => normalize_newlines((string)($row['lizenz_notizen'] ?? '')),
    'Bootsnummer' => fn(array $row): string => trim((string)($row['bootnummer'] ?? '')),
    'Boot-Notizen' => fn(array $row): string => normalize_newlines((string)($row['boot_notizen'] ?? '')),
];

$rows = [];
foreach ($licenses as $row) {
    $rows[] = array_values(array_map(fn(callable $extractor) => $extractor($row), $columns));
}

if ($format === 'xlsx') {
    if (!class_exists('ZipArchive')) {
        $format = 'csv';
    } else {
        export_xlsx($year, $columns, $rows);
        exit;
    }
}

export_csv($year, $columns, $rows);

function format_decimal($value): string
{
    if ($value === null || $value === '') {
        return '';
    }
    return number_format((float)$value, 2, ',', '');
}

function format_export_date(?string $value): string
{
    if ($value === null) {
        return '';
    }

    $value = trim($value);
    if ($value === '' || $value === '0000-00-00') {
        return '';
    }

    $date = DateTime::createFromFormat('Y-m-d', $value);
    if (!$date) {
        return $value;
    }

    return $date->format('d.m.Y');
}

function normalize_newlines(string $value): string
{
    return str_replace(["\r\n", "\r"], "\n", trim($value));
}

function export_csv(int $year, array $columns, array $rows): void
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="lizenzen_' . $year . '.csv"');

    $output = fopen('php://output', 'wb');
    if ($output === false) {
        throw new RuntimeException('Konnte Export nicht öffnen.');
    }

    fwrite($output, "\xEF\xBB\xBF");
    fputcsv($output, array_keys($columns), ';');
    foreach ($rows as $row) {
        fputcsv($output, $row, ';');
    }

    fclose($output);
}

function export_xlsx(int $year, array $columns, array $rows): void
{
    $sheetRows = [];
    $sheetRows[] = array_keys($columns);
    foreach ($rows as $row) {
        $sheetRows[] = $row;
    }

    $sheetXml = build_sheet_xml($sheetRows);

    $zip = new ZipArchive();
    $tempFile = tempnam(sys_get_temp_dir(), 'wfv');
    if ($tempFile === false) {
        throw new RuntimeException('Temporäre Datei konnte nicht erstellt werden.');
    }

    if ($zip->open($tempFile, ZipArchive::OVERWRITE) !== true) {
        unlink($tempFile);
        throw new RuntimeException('XLSX-Datei konnte nicht geschrieben werden.');
    }

    $zip->addFromString('[Content_Types].xml', get_content_types_xml());
    $zip->addFromString('_rels/.rels', get_root_rels_xml());
    $zip->addFromString('xl/workbook.xml', get_workbook_xml());
    $zip->addFromString('xl/_rels/workbook.xml.rels', get_workbook_rels_xml());
    $zip->addFromString('xl/styles.xml', get_styles_xml());
    $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);

    $zip->close();

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="lizenzen_' . $year . '.xlsx"');
    header('Content-Length: ' . filesize($tempFile));

    readfile($tempFile);
    unlink($tempFile);
}

function build_sheet_xml(array $rows): string
{
    $xmlRows = [];
    foreach ($rows as $rowIndex => $row) {
        $cells = [];
        foreach ($row as $colIndex => $value) {
            $cellRef = column_letter($colIndex) . ($rowIndex + 1);
            $cells[] = '<c r="' . $cellRef . '" t="inlineStr"><is><t>' . escape_xml($value) . '</t></is></c>';
        }
        $xmlRows[] = '<row r="' . ($rowIndex + 1) . '">' . implode('', $cells) . '</row>';
    }

    return '<?xml version="1.0" encoding="UTF-8"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<sheetData>' . implode('', $xmlRows) . '</sheetData>'
        . '</worksheet>';
}

function column_letter(int $index): string
{
    $index += 1;
    $letters = '';
    while ($index > 0) {
        $mod = ($index - 1) % 26;
        $letters = chr(65 + $mod) . $letters;
        $index = intdiv($index - 1, 26);
    }
    return $letters;
}

function escape_xml(string $value): string
{
    $value = normalize_newlines($value);
    return htmlspecialchars($value, ENT_XML1);
}

function get_content_types_xml(): string
{
    return '<?xml version="1.0" encoding="UTF-8"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
        . '</Types>';
}

function get_root_rels_xml(): string
{
    return '<?xml version="1.0" encoding="UTF-8"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '</Relationships>';
}

function get_workbook_xml(): string
{
    return '<?xml version="1.0" encoding="UTF-8"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheets>'
        . '<sheet name="Lizenzen" sheetId="1" r:id="rId1"/>'
        . '</sheets>'
        . '</workbook>';
}

function get_workbook_rels_xml(): string
{
    return '<?xml version="1.0" encoding="UTF-8"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
        . '</Relationships>';
}

function get_styles_xml(): string
{
    return '<?xml version="1.0" encoding="UTF-8"?>'
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<fonts count="1"><font><sz val="11"/><color theme="1"/><name val="Calibri"/><family val="2"/></font></fonts>'
        . '<fills count="1"><fill><patternFill patternType="none"/></fill></fills>'
        . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs>'
        . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
        . '</styleSheet>';
}
