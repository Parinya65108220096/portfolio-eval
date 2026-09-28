<?php
// ตัวเขียนไฟล์ .xlsx แบบเบา ไม่ต้องใช้ Composer (ต้องเปิดใช้ PHP extension: zip)
// เซลล์: string / int / float / ['f' => 'สูตร'] (สูตร Excel จริง คำนวณตอนเปิดไฟล์)
class SimpleXlsx {
    private array $sheets = [];

    public function addSheet(string $title, array $rows, array $widths = []): void {
        $title = mb_substr(preg_replace('/[\[\]\:\*\?\/\\\\]/u', ' ', $title), 0, 31);
        $this->sheets[] = [$title ?: 'Sheet' . (count($this->sheets) + 1), $rows, $widths];
    }

    public static function col(int $i): string {
        $s = ''; $i++;
        while ($i > 0) { $m = ($i - 1) % 26; $s = chr(65 + $m) . $s; $i = intdiv($i - 1, 26); }
        return $s;
    }

    private function x(string $s): string {
        $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $s);
        return htmlspecialchars($s, ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function cell(string $ref, $v, int $style): string {
        if (is_array($v) && isset($v['f'])) return "<c r=\"$ref\" s=\"$style\"><f>" . $this->x($v['f']) . "</f></c>";
        if (is_int($v) || is_float($v)) return "<c r=\"$ref\" s=\"$style\"><v>$v</v></c>";
        if ($v === null || $v === '') return "<c r=\"$ref\" s=\"$style\"/>";
        return "<c r=\"$ref\" s=\"$style\" t=\"inlineStr\"><is><t xml:space=\"preserve\">" . $this->x((string)$v) . "</t></is></c>";
    }

    private function sheetXml(array $rows, array $widths): string {
        $x = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
        $x .= '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>';
        if ($widths) {
            $x .= '<cols>';
            foreach ($widths as $i => $w) $x .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . $w . '" customWidth="1"/>';
            $x .= '</cols>';
        }
        $x .= '<sheetData>';
        foreach (array_values($rows) as $ri => $row) {
            $x .= '<row r="' . ($ri + 1) . '">';
            foreach (array_values($row) as $ci => $v) $x .= $this->cell(self::col($ci) . ($ri + 1), $v, $ri === 0 ? 1 : 2);
            $x .= '</row>';
        }
        return $x . '</sheetData></worksheet>';
    }

    public function output(string $filename): void {
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        $z = new ZipArchive();
        $z->open($tmp, ZipArchive::OVERWRITE);
        $n = count($this->sheets);
        $ct = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
        $wb = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>';
        $rel = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        foreach ($this->sheets as $i => [$title, $rows, $widths]) {
            $k = $i + 1;
            $ct  .= "<Override PartName=\"/xl/worksheets/sheet$k.xml\" ContentType=\"application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml\"/>";
            $wb  .= '<sheet name="' . $this->x($title) . "\" sheetId=\"$k\" r:id=\"rId$k\"/>";
            $rel .= "<Relationship Id=\"rId$k\" Type=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet\" Target=\"worksheets/sheet$k.xml\"/>";
            $z->addFromString("xl/worksheets/sheet$k.xml", $this->sheetXml($rows, $widths));
        }
        $rel .= '<Relationship Id="rId' . ($n + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';
        $z->addFromString('[Content_Types].xml', $ct . '</Types>');
        $z->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $z->addFromString('xl/workbook.xml', $wb . '</sheets><calcPr fullCalcOnLoad="1"/></workbook>');
        $z->addFromString('xl/_rels/workbook.xml.rels', $rel);
        $z->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Tahoma"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Tahoma"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF0E7490"/></patternFill></fill></fills><borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border><left style="thin"><color rgb="FFE2E8F0"/></left><right style="thin"><color rgb="FFE2E8F0"/></right><top style="thin"><color rgb="FFE2E8F0"/></top><bottom style="thin"><color rgb="FFE2E8F0"/></bottom><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="3"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf><xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>');
        $z->close();

        while (ob_get_level()) ob_end_clean();
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header("Content-Disposition: attachment; filename=\"export.xlsx\"; filename*=UTF-8''" . rawurlencode($filename));
        header('Content-Length: ' . filesize($tmp));
        readfile($tmp);
        unlink($tmp);
    }
}
