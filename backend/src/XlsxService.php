<?php

declare(strict_types=1);

namespace QrControl;

use ZipStream\ZipStream;

final class XlsxService
{
    public static function make(array $rows): string
    {
        $stream = fopen('php://temp', 'w+b');
        if ($stream === false) throw new \RuntimeException('Não foi possível criar a planilha temporária.');

        $zip = new ZipStream(
            outputStream: $stream,
            sendHttpHeaders: false,
            contentType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        );
        $zip->addFile('[Content_Types].xml', self::contentTypes());
        $zip->addFile('_rels/.rels', self::rootRelationships());
        $zip->addFile('xl/workbook.xml', self::workbook());
        $zip->addFile('xl/_rels/workbook.xml.rels', self::workbookRelationships());
        $zip->addFile('xl/styles.xml', self::styles());
        $zip->addFile('xl/worksheets/sheet1.xml', self::sheet($rows));
        $zip->addFile('xl/worksheets/_rels/sheet1.xml.rels', self::sheetRelationships($rows));
        $zip->finish();

        rewind($stream);
        $data = stream_get_contents($stream);
        fclose($stream);
        if ($data === false) throw new \RuntimeException('Não foi possível finalizar a planilha.');
        return $data;
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private static function cell(string $ref, string $value, int $style = 0): string
    {
        return sprintf('<c r="%s" t="inlineStr" s="%d"><is><t xml:space="preserve">%s</t></is></c>', $ref, $style, self::escape($value));
    }

    private static function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '</Types>';
    }

    private static function rootRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }
    private static function workbook(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="Conteúdo" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>';
    }

    private static function workbookRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
    }

    private static function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><color rgb="FFFFFFFF"/><sz val="11"/><name val="Calibri"/></font></fonts>'
            . '<fills count="4"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF4B8F6B"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFE8F3EC"/><bgColor indexed="64"/></patternFill></fill></fills>'
            . '<borders count="2"><border/><border><left style="thin"><color rgb="FFD4D4D4"/></left><right style="thin"><color rgb="FFD4D4D4"/></right><top style="thin"><color rgb="FFD4D4D4"/></top><bottom style="thin"><color rgb="FFD4D4D4"/></bottom></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="3"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf><xf numFmtId="0" fontId="0" fillId="3" borderId="1" applyFill="1" applyBorder="1"/></cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }
    private static function sheet(array $rows): string
    {
        $lastRow = count($rows) + 1;
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheetViews><sheetView showGridLines="0" workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            . '<cols><col min="1" max="1" width="9" customWidth="1"/><col min="2" max="2" width="16" customWidth="1"/><col min="3" max="3" width="58" customWidth="1"/></cols>'
            . '<sheetData><row r="1" ht="24" customHeight="1">'
            . self::cell('A1', 'N°', 1) . self::cell('B1', 'Código', 1) . self::cell('C1', 'Link permanente', 1) . '</row>';

        foreach (array_values($rows) as $index => $row) {
            $number = $index + 1;
            $code = Support::formatCode((string) $row['code']);
            $url = Config::appUrl() . '/q/' . $code;
            $style = $number % 2 === 0 ? 2 : 0;
            $xml .= '<row r="' . ($number + 1) . '" ht="21" customHeight="1">'
                . '<c r="A' . ($number + 1) . '" t="n" s="' . $style . '"><v>' . $number . '</v></c>'
                . self::cell('B' . ($number + 1), $code, $style)
                . self::cell('C' . ($number + 1), $url, $style)
                . '</row>';
        }

        return $xml . '</sheetData><autoFilter ref="A1:C' . $lastRow . '"/></worksheet>';
    }

    private static function sheetRelationships(array $rows): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        foreach (array_values($rows) as $index => $row) {
            $code = Support::formatCode((string) $row['code']);
            $url = self::escape(Config::appUrl() . '/q/' . $code);
            $xml .= '<Relationship Id="rId' . ($index + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="' . $url . '" TargetMode="External"/>';
        }
        return $xml . '</Relationships>';
    }
}
