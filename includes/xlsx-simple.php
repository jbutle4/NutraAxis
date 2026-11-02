<?php
/**
 * Minimal .xlsx (OOXML) writer — no PhpSpreadsheet required.
 * Builds a single-sheet workbook from a header row + data rows.
 */

declare(strict_types=1);

function xlsx_simple_escape(string $value): string
{
    return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

/**
 * @param list<string> $headers
 * @param list<list<scalar|null>> $rows
 */
function xlsx_simple_build(array $headers, array $rows, string $sheetName = 'Sheet1'): string
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('Excel export requires ZipArchive.');
    }

    $sheetName = trim($sheetName) !== '' ? $sheetName : 'Sheet1';
    $sheetXml = xlsx_simple_sheet_xml($headers, $rows);
    $workbookXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
        . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheets><sheet name="' . xlsx_simple_escape($sheetName) . '" sheetId="1" r:id="rId1"/></sheets>'
        . '</workbook>';

    $tmp = tempnam(sys_get_temp_dir(), 'nxlsx');
    if ($tmp === false) {
        throw new RuntimeException('Could not create a temporary Excel file.');
    }

    $zip = new ZipArchive();
    if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
        @unlink($tmp);
        throw new RuntimeException('Could not open Excel zip archive.');
    }

    $zip->addFromString(
        '[Content_Types].xml',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
        . '</Types>'
    );
    $zip->addFromString(
        '_rels/.rels',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '</Relationships>'
    );
    $zip->addFromString('xl/workbook.xml', $workbookXml);
    $zip->addFromString(
        'xl/_rels/workbook.xml.rels',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
        . '</Relationships>'
    );
    $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
    $zip->addFromString(
        'xl/styles.xml',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts>'
        . '<fills count="1"><fill><patternFill patternType="none"/></fill></fills>'
        . '<borders count="1"><border/></borders>'
        . '<cellStyleXfs count="1"><xf/></cellStyleXfs>'
        . '<cellXfs count="1"><xf/></cellXfs>'
        . '</styleSheet>'
    );
    $zip->close();

    $bytes = (string) file_get_contents($tmp);
    @unlink($tmp);

    if ($bytes === '') {
        throw new RuntimeException('Excel file was empty after write.');
    }

    return $bytes;
}

/**
 * @param list<string> $headers
 * @param list<list<scalar|null>> $rows
 */
function xlsx_simple_sheet_xml(array $headers, array $rows): string
{
    $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<sheetData>';

    $xml .= xlsx_simple_row_xml(1, $headers);
    $rowNum = 2;
    foreach ($rows as $row) {
        $xml .= xlsx_simple_row_xml($rowNum, $row);
        $rowNum++;
    }

    $xml .= '</sheetData></worksheet>';

    return $xml;
}

/**
 * @param list<scalar|null> $cells
 */
function xlsx_simple_row_xml(int $rowNum, array $cells): string
{
    $xml = '<row r="' . $rowNum . '">';
    $col = 0;
    foreach ($cells as $value) {
        $ref = xlsx_simple_cell_ref($col, $rowNum);
        $col++;
        if ($value === null || $value === '') {
            continue;
        }
        if (is_int($value) || is_float($value)) {
            $xml .= '<c r="' . $ref . '"><v>' . xlsx_simple_escape((string) $value) . '</v></c>';
            continue;
        }
        if (is_bool($value)) {
            $xml .= '<c r="' . $ref . '"><v>' . ($value ? '1' : '0') . '</v></c>';
            continue;
        }
        $text = (string) $value;
        if (is_numeric($text) && !preg_match('/^0\d/', $text) && strlen($text) < 16) {
            $xml .= '<c r="' . $ref . '"><v>' . xlsx_simple_escape($text) . '</v></c>';
            continue;
        }
        $xml .= '<c r="' . $ref . '" t="inlineStr"><is><t>' . xlsx_simple_escape($text) . '</t></is></c>';
    }
    $xml .= '</row>';

    return $xml;
}

function xlsx_simple_cell_ref(int $colIndex, int $rowNum): string
{
    $letters = '';
    $n = $colIndex;
    do {
        $letters = chr(65 + ($n % 26)) . $letters;
        $n = intdiv($n, 26) - 1;
    } while ($n >= 0);

    return $letters . $rowNum;
}
