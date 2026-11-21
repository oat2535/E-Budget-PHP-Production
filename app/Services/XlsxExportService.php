<?php

namespace App\Services;

use ZipArchive;

class XlsxExportService
{
    public const EXPORT_COLUMNS = [
        ['row_order', 'row_order', 'int', 10],
        ['general_ledger_code', 'general_ledger_code', 'str', 18],
        ['gl_name', 'gl_name', 'str', 42],
        ['actual_value', 'Act จริง', 'num', 16],
        ['per_month_value', 'ต่อเดือน', 'num', 15],
        ['annualized_value', '12', 'num', 16],
        ['sales_ratio', 'สัดส่วน/ยอดขาย', 'num', 16],
        ['growth_rate', '% การโต', 'num', 13],
        ['january', 'january', 'num', 15],
        ['february', 'february', 'num', 15],
        ['march', 'march', 'num', 15],
        ['april', 'april', 'num', 15],
        ['may', 'may', 'num', 15],
        ['june', 'june', 'num', 15],
        ['july', 'july', 'num', 15],
        ['august', 'august', 'num', 15],
        ['september', 'september', 'num', 15],
        ['october', 'october', 'num', 15],
        ['november', 'november', 'num', 15],
        ['december', 'december', 'num', 15],
        ['total', 'total', 'num', 17],
        ['pct_jan', 'pct_jan', 'num', 12],
        ['pct_feb', 'pct_feb', 'num', 12],
        ['pct_mar', 'pct_mar', 'num', 12],
        ['pct_apr', 'pct_apr', 'num', 12],
        ['pct_may', 'pct_may', 'num', 12],
        ['pct_jun', 'pct_jun', 'num', 12],
        ['pct_jul', 'pct_jul', 'num', 12],
        ['pct_aug', 'pct_aug', 'num', 12],
        ['pct_sep', 'pct_sep', 'num', 12],
        ['pct_oct', 'pct_oct', 'num', 12],
        ['pct_nov', 'pct_nov', 'num', 12],
        ['pct_dec', 'pct_dec', 'num', 12],
    ];

    public static function colLetter(int $colIndex1Based): string
    {
        $letters = '';
        $n = $colIndex1Based;
        while ($n > 0) {
            $rem = ($n - 1) % 26;
            $letters = chr(65 + $rem) . $letters;
            $n = intdiv($n - 1, 26);
        }
        return $letters;
    }

    public static function sanitizeText(?string $val): string
    {
        if ($val === null) {
            return '';
        }
        $cleaned = preg_replace('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/', '', $val);
        return htmlspecialchars($cleaned, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    public static function styleForRow(array $row, string $kind): int
    {
        $code = $row['general_ledger_code'] ?? '';
        $isPnlSummary = ! empty($row['is_pnl_summary']) && in_array($code, ['FH1-H3', 'SP-B', 'H6', 'H8', 'FH8'], true);

        if ($isPnlSummary) {
            $base = 11;
        } elseif (($row['row_type'] ?? '') === 'subtotal') {
            $base = 8;
        } elseif (($row['row_type'] ?? '') === 'header') {
            $base = 5;
        } else {
            $base = 2;
        }

        if ($kind === 'int') {
            return $base;
        }
        if ($kind === 'str') {
            return $base + 1;
        }
        return $base + 2;
    }

    public static function buildDashboardXlsx(array $dashboardPayload): string
    {
        $rows = $dashboardPayload['rows'] ?? [];
        $lastColLetter = self::colLetter(count(self::EXPORT_COLUMNS));
        $lastRowNum = count($rows) + 1;

        $parts = [];
        $parts[] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $parts[] = '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
        $parts[] = "<dimension ref=\"A1:{$lastColLetter}{$lastRowNum}\"/>";
        $parts[] = '<sheetViews><sheetView tabSelected="1" workbookViewId="0"><pane xSplit="3" ySplit="1" topLeftCell="D2" activePane="bottomRight" state="frozen"/></sheetView></sheetViews>';
        $parts[] = '<sheetFormatPr defaultRowHeight="19"/>';
        $parts[] = '<cols>';
        foreach (self::EXPORT_COLUMNS as $idx => $col) {
            $colNum = $idx + 1;
            $parts[] = "<col min=\"{$colNum}\" max=\"{$colNum}\" width=\"{$col[3]}\" customWidth=\"1\"/>";
        }
        $parts[] = '</cols>';
        $parts[] = '<sheetData>';

        // Header Row (Row 1)
        $parts[] = '<row r="1" ht="26" customHeight="1">';
        foreach (self::EXPORT_COLUMNS as $idx => $col) {
            $ref = self::colLetter($idx + 1) . '1';
            $headerLabel = self::sanitizeText($col[1]);
            $parts[] = "<c r=\"{$ref}\" s=\"1\" t=\"inlineStr\"><is><t>{$headerLabel}</t></is></c>";
        }
        $parts[] = '</row>';

        // Data Rows
        foreach ($rows as $rowIdx => $row) {
            $rowNum = $rowIdx + 2;
            $rowHeight = (($row['row_type'] ?? '') === 'header' || ! empty($row['is_pnl_summary'])) ? '22' : '20';
            $parts[] = "<row r=\"{$rowNum}\" ht=\"{$rowHeight}\" customHeight=\"1\">";

            foreach (self::EXPORT_COLUMNS as $colIdx => $col) {
                $ref = self::colLetter($colIdx + 1) . $rowNum;
                $key = $col[0];
                $kind = $col[2];
                $style = self::styleForRow($row, $kind);
                $rawVal = $row[$key] ?? null;

                if ($kind === 'int') {
                    $intVal = (int) $rawVal;
                    $parts[] = "<c r=\"{$ref}\" s=\"{$style}\"><v>{$intVal}</v></c>";
                } elseif ($kind === 'str') {
                    $textVal = self::sanitizeText($rawVal);
                    $parts[] = "<c r=\"{$ref}\" s=\"{$style}\" t=\"inlineStr\"><is><t>{$textVal}</t></is></c>";
                } else {
                    $numVal = (float) ($rawVal ?? 0);
                    $formatted = number_format($numVal, 2, '.', '');
                    $parts[] = "<c r=\"{$ref}\" s=\"{$style}\"><v>{$formatted}</v></c>";
                }
            }

            $parts[] = '</row>';
        }

        $parts[] = '</sheetData></worksheet>';
        $sheetXml = implode('', $parts);

        // Build ZIP in memory
        $tempFile = tempnam(sys_get_temp_dir(), 'xlsx_');
        $zip = new ZipArchive();
        $zip->open($tempFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $zip->addFromString('[Content_Types].xml', self::getContentTypesXml());
        $zip->addFromString('_rels/.rels', self::getRelsXml());
        $zip->addFromString('xl/_rels/workbook.xml.rels', self::getWorkbookRelsXml());
        $zip->addFromString('xl/workbook.xml', self::getWorkbookXml());
        $zip->addFromString('xl/styles.xml', self::getStylesXml());
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);

        $zip->close();
        $binary = file_get_contents($tempFile);
        @unlink($tempFile);

        return $binary;
    }

    protected static function getContentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>';
    }

    protected static function getRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    protected static function getWorkbookXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="Dashboard" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>';
    }

    protected static function getWorkbookRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
    }

    protected static function getStylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<numFmts count="2">'
            . '<numFmt numFmtId="164" formatCode="#,##0.00;[Red]-#,##0.00"/>'
            . '<numFmt numFmtId="165" formatCode="0"/>'
            . '</numFmts>'
            . '<fonts count="3">'
            . '<font><sz val="11"/><color rgb="FF0F172A"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="11"/><color rgb="FF0F172A"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
            . '</fonts>'
            . '<fills count="6">'
            . '<fill><patternFill patternType="none"/></fill>'
            . '<fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF1E3A8A"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFEEF2F7"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFF7F3E8"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFDBEAFE"/></patternFill></fill>'
            . '</fills>'
            . '<borders count="2">'
            . '<border><left/><right/><top/><bottom/><diagonal/></border>'
            . '<border>'
            . '<left style="thin"><color rgb="FFCBD5E1"/></left>'
            . '<right style="thin"><color rgb="FFCBD5E1"/></right>'
            . '<top style="thin"><color rgb="FFCBD5E1"/></top>'
            . '<bottom style="thin"><color rgb="FFCBD5E1"/></bottom>'
            . '<diagonal/>'
            . '</border>'
            . '</borders>'
            . '<cellStyleXfs count="1">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>'
            . '</cellStyleXfs>'
            . '<cellXfs count="14">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="2" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            . '<xf numFmtId="165" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf>'
            . '<xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>'
            . '<xf numFmtId="165" fontId="1" fillId="3" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="1" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf>'
            . '<xf numFmtId="164" fontId="1" fillId="3" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>'
            . '<xf numFmtId="165" fontId="1" fillId="4" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="1" fillId="4" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf>'
            . '<xf numFmtId="164" fontId="1" fillId="4" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>'
            . '<xf numFmtId="165" fontId="1" fillId="5" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="1" fillId="5" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf>'
            . '<xf numFmtId="164" fontId="1" fillId="5" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>'
            . '</cellXfs>'
            . '<cellStyles count="1">'
            . '<cellStyle name="Normal" xfId="0" builtinId="0"/>'
            . '</cellStyles>'
            . '</styleSheet>';
    }
}
