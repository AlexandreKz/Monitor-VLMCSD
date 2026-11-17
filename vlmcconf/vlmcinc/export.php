<?php
// ============================================
// ФАЙЛ: vlmcinc/export.php
// ВЕРСИЯ: 1.0.0
// ДАТА: 2026-11-17
// @description: Экспорт таблицы отчёта в XLSX и PDF без внешних библиотек.
//               XLSX: ZIP пишется вручную (без расширения zip). PDF: вручную, со встроенным шрифтом
//               DejaVu Sans (vlmcinc/fonts, поддерживает кириллицу), заголовок отчёта + таблица.
// ============================================

if (basename($_SERVER['PHP_SELF'] ?? '') === 'export.php') {
    http_response_code(403);
    exit('Access denied');
}

define('EXPORT_MAX_ROWS', 2000);
define('EXPORT_MAX_COLS', 20);
define('EXPORT_MAX_CELL', 500);

/**
 * Приводит данные из запроса к безопасному виду.
 * @return array|null [title, headers[], types[], rows[][]] или null, если данные некорректны
 */
function exportNormalize($title, $headers, $types, $rows) {
    if (!is_array($headers) || !is_array($rows) || !count($headers) || count($headers) > EXPORT_MAX_COLS || count($rows) > EXPORT_MAX_ROWS) {
        return null;
    }
    $clean = function ($v) {
        if (is_array($v) || is_object($v)) return '';
        $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', (string)$v);
        if ($v === null) return '';
        return function_exists('mb_substr') ? mb_substr($v, 0, EXPORT_MAX_CELL, 'UTF-8') : substr($v, 0, EXPORT_MAX_CELL);
    };
    $n = count($headers);
    $h = [];
    foreach ($headers as $x) $h[] = $clean($x);
    $t = [];
    for ($i = 0; $i < $n; $i++) $t[] = (is_array($types) && ($types[$i] ?? '') === 'num') ? 'num' : 'text';
    $r = [];
    foreach ($rows as $row) {
        if (!is_array($row)) continue;
        $line = [];
        for ($i = 0; $i < $n; $i++) $line[] = $clean($row[$i] ?? '');
        $r[] = $line;
    }
    return ['title' => $clean($title), 'headers' => $h, 'types' => $t, 'rows' => $r];
}

function exportSafeName($s) {
    $s = preg_replace('/[^A-Za-z0-9_\-]+/', '-', (string)$s);
    return trim($s, '-') ?: 'report';
}

// ====================== XLSX ======================

/** Минимальный ZIP без сжатия (метод STORE). $files: путь => содержимое. */
function exportZipStore(array $files) {
    $out = '';
    $central = '';
    $count = 0;
    $dosTime = 0;
    $dosDate = ((2026 - 1980) << 9) | (11 << 5) | 17;
    foreach ($files as $name => $data) {
        $crc = crc32($data);
        $len = strlen($data);
        $offset = strlen($out);
        $local = pack('VvvvvvVVVvv', 0x04034b50, 20, 0x0800, 0, $dosTime, $dosDate, $crc, $len, $len, strlen($name), 0) . $name;
        $out .= $local . $data;
        $central .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0x0800, 0, $dosTime, $dosDate, $crc, $len, $len, strlen($name), 0, 0, 0, 0, 0, $offset) . $name;
        $count++;
    }
    return $out . $central . pack('VvvvvVVv', 0x06054b50, 0, 0, $count, $count, strlen($central), strlen($out), 0);
}

function exportXmlEscape($s) {
    return htmlspecialchars((string)$s, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function exportColName($i) {   // 0 -> A, 25 -> Z, 26 -> AA
    $s = '';
    for ($i++; $i > 0; $i = intdiv($i - 1, 26)) $s = chr(65 + ($i - 1) % 26) . $s;
    return $s;
}

function exportStrLen($s) {
    return function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : strlen($s);
}

/** Файл XLSX (строка с содержимым). */
function exportXlsx(array $d) {
    $n = count($d['headers']);
    $sheetName = preg_replace('/[\[\]\:\*\?\/\\\\]/u', ' ', $d['title']);
    $sheetName = function_exists('mb_substr') ? mb_substr(trim($sheetName), 0, 31, 'UTF-8') : substr(trim($sheetName), 0, 31);
    if ($sheetName === '') $sheetName = 'Report';

    // ширина колонок по содержимому
    $widths = [];
    for ($c = 0; $c < $n; $c++) {
        $w = exportStrLen($d['headers'][$c]) + 3;
        foreach ($d['rows'] as $row) $w = max($w, min(60, exportStrLen($row[$c]) + 2));
        $widths[$c] = $w;
    }

    $rowsXml = '';
    $rowsXml .= '<row r="1"><c r="A1" s="1" t="inlineStr"><is><t xml:space="preserve">' . exportXmlEscape($d['title']) . '</t></is></c></row>';
    $hr = '';
    for ($c = 0; $c < $n; $c++) {
        $hr .= '<c r="' . exportColName($c) . '3" s="2" t="inlineStr"><is><t xml:space="preserve">' . exportXmlEscape($d['headers'][$c]) . '</t></is></c>';
    }
    $rowsXml .= '<row r="3">' . $hr . '</row>';
    $r = 4;
    foreach ($d['rows'] as $row) {
        $cells = '';
        for ($c = 0; $c < $n; $c++) {
            $ref = exportColName($c) . $r;
            $v = $row[$c];
            if ($d['types'][$c] === 'num' && $v !== '' && is_numeric($v)) {
                $cells .= '<c r="' . $ref . '"><v>' . $v . '</v></c>';
            } elseif ($v !== '') {
                $cells .= '<c r="' . $ref . '" t="inlineStr"><is><t xml:space="preserve">' . exportXmlEscape($v) . '</t></is></c>';
            }
        }
        $rowsXml .= '<row r="' . $r . '">' . $cells . '</row>';
        $r++;
    }
    $last = exportColName($n - 1) . max(3, $r - 1);
    $cols = '<cols>';
    for ($c = 0; $c < $n; $c++) $cols .= '<col min="' . ($c + 1) . '" max="' . ($c + 1) . '" width="' . $widths[$c] . '" customWidth="1"/>';
    $cols .= '</cols>';

    $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
        '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' .
        '<sheetViews><sheetView workbookViewId="0"><pane ySplit="3" topLeftCell="A4" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>' .
        $cols . '<sheetData>' . $rowsXml . '</sheetData>' .
        '<autoFilter ref="A3:' . $last . '"/></worksheet>';

    $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
        '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' .
        '<fonts count="3"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="14"/><name val="Calibri"/></font></fonts>' .
        '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFD9E2F3"/><bgColor indexed="64"/></patternFill></fill></fills>' .
        '<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border><left/><right/><top/><bottom style="thin"><color rgb="FF8EA9DB"/></bottom><diagonal/></border></borders>' .
        '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>' .
        '<cellXfs count="3"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>' .
        '<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/>' .
        '<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"/></cellXfs>' .
        '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';

    return exportZipStore([
        '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>',
        '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
        'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="' . exportXmlEscape($sheetName) . '" sheetId="1" r:id="rId1"/></sheets></workbook>',
        'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
        'xl/styles.xml' => $styles,
        'xl/worksheets/sheet1.xml' => $sheet,
    ]);
}

// ====================== PDF ======================

/** Шрифт TrueType: ширины глифов и таблица символов (формат cmap 4). */
class ExportTtf {
    public $data;
    public $unitsPerEm = 1000;
    public $ascent = 0, $descent = 0, $bbox = [0, 0, 0, 0];
    public $advance = [];   // gid => ширина в единицах шрифта
    public $cmap = [];      // кодпоинт => gid

    public function __construct($file) {
        $this->data = (string)@file_get_contents($file);
        if (strlen($this->data) < 12) throw new RuntimeException('font');
        $tables = [];
        $num = $this->u16(4);
        for ($i = 0; $i < $num; $i++) {
            $o = 12 + $i * 16;
            $tables[substr($this->data, $o, 4)] = [$this->u32($o + 8), $this->u32($o + 12)];
        }
        foreach (['head', 'hhea', 'hmtx', 'maxp', 'cmap'] as $t) if (!isset($tables[$t])) throw new RuntimeException('font');
        $head = $tables['head'][0];
        $this->unitsPerEm = $this->u16($head + 18) ?: 1000;
        $this->bbox = [$this->s16($head + 36), $this->s16($head + 38), $this->s16($head + 40), $this->s16($head + 42)];
        $hhea = $tables['hhea'][0];
        $this->ascent = $this->s16($hhea + 4);
        $this->descent = $this->s16($hhea + 6);
        $nh = $this->u16($hhea + 34);
        $numGlyphs = $this->u16($tables['maxp'][0] + 4);
        $hmtx = $tables['hmtx'][0];
        $lastAdv = 0;
        for ($g = 0; $g < $numGlyphs; $g++) {
            if ($g < $nh) $lastAdv = $this->u16($hmtx + $g * 4);
            $this->advance[$g] = $lastAdv;
        }
        // cmap: формат 4 (Unicode BMP)
        $cm = $tables['cmap'][0];
        $nt = $this->u16($cm + 2);
        $sub = null;
        for ($i = 0; $i < $nt; $i++) {
            $pid = $this->u16($cm + 4 + $i * 8);
            $eid = $this->u16($cm + 6 + $i * 8);
            $off = $this->u32($cm + 8 + $i * 8);
            if ((($pid === 3 && $eid === 1) || $pid === 0) && $this->u16($cm + $off) === 4) { $sub = $cm + $off; break; }
        }
        if ($sub === null) throw new RuntimeException('font');
        $segX2 = $this->u16($sub + 6);
        $seg = intdiv($segX2, 2);
        $endO = $sub + 14;
        $startO = $endO + $segX2 + 2;
        $deltaO = $startO + $segX2;
        $rangeO = $deltaO + $segX2;
        for ($s = 0; $s < $seg; $s++) {
            $end = $this->u16($endO + $s * 2);
            $start = $this->u16($startO + $s * 2);
            $delta = $this->s16($deltaO + $s * 2);
            $ro = $this->u16($rangeO + $s * 2);
            if ($start === 0xFFFF) continue;
            for ($c = $start; $c <= $end; $c++) {
                if ($ro === 0) {
                    $g = ($c + $delta) & 0xFFFF;
                } else {
                    $p = $rangeO + $s * 2 + $ro + ($c - $start) * 2;
                    $g = $this->u16($p);
                    if ($g !== 0) $g = ($g + $delta) & 0xFFFF;
                }
                if ($g !== 0) $this->cmap[$c] = $g;
            }
        }
    }
    private function u16($o) { return (ord($this->data[$o]) << 8) | ord($this->data[$o + 1]); }
    private function s16($o) { $v = $this->u16($o); return $v >= 0x8000 ? $v - 0x10000 : $v; }
    private function u32($o) { return ($this->u16($o) << 16) | $this->u16($o + 2); }
}

class ExportPdf {
    private $font;
    private $used = [];   // gid => кодпоинт
    private $pages = [];  // содержимое страниц
    private $cur = '';
    private $pw, $ph, $mx = 36, $my = 40;
    private $fs = 8.5;    // размер шрифта таблицы
    private $lh = 11.5;   // высота строки
    private $pad = 4;

    public function __construct($fontFile) { $this->font = new ExportTtf($fontFile); }

    /** Строка из символов, которые есть в шрифте (эмодзи и прочее убирается). */
    private function clean($s) {
        $out = '';
        foreach (preg_split('//u', (string)$s, -1, PREG_SPLIT_NO_EMPTY) as $ch) {
            $cp = $this->cp($ch);
            if ($cp === 0x09 || $cp === 0x0A || $cp === 0x0D) { $out .= ' '; continue; }
            if (isset($this->font->cmap[$cp])) $out .= $ch;
        }
        return $out;
    }
    private function cp($ch) {
        if (function_exists('mb_ord')) return mb_ord($ch, 'UTF-8');
        $u = unpack('N', mb_convert_encoding($ch, 'UCS-4BE', 'UTF-8'));
        return $u[1];
    }
    /** Ширина строки в пунктах. */
    private function width($s, $size) {
        $w = 0;
        foreach (preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY) as $ch) {
            $g = $this->font->cmap[$this->cp($ch)] ?? 0;
            $w += $this->font->advance[$g] ?? 0;
        }
        return $w * $size / $this->font->unitsPerEm;
    }
    /** Перенос по ширине: слова по пробелам, слишком длинные слова — по символам. */
    private function wrap($s, $maxW, $size) {
        $lines = [];
        foreach (explode("\n", $s) as $para) {
            $line = '';
            foreach (preg_split('/ +/', $para) as $word) {
                $try = $line === '' ? $word : $line . ' ' . $word;
                if ($this->width($try, $size) <= $maxW) { $line = $try; continue; }
                if ($line !== '') { $lines[] = $line; $line = ''; }
                if ($this->width($word, $size) <= $maxW) { $line = $word; continue; }
                $chunk = '';
                foreach (preg_split('//u', $word, -1, PREG_SPLIT_NO_EMPTY) as $ch) {
                    if ($this->width($chunk . $ch, $size) > $maxW && $chunk !== '') { $lines[] = $chunk; $chunk = ''; }
                    $chunk .= $ch;
                }
                $line = $chunk;
            }
            $lines[] = $line;
        }
        return $lines;
    }
    private function hex($s) {
        $h = '';
        foreach (preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY) as $ch) {
            $cp = $this->cp($ch);
            $g = $this->font->cmap[$cp] ?? 0;
            if ($g === 0) continue;
            $this->used[$g] = $cp;
            $h .= sprintf('%04X', $g);
        }
        return $h;
    }
    private function f($n) { return rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.') ?: '0'; }

    private function text($x, $y, $s, $size, $rgb = [0, 0, 0]) {
        $h = $this->hex($s);
        if ($h === '') return;
        $this->cur .= sprintf("BT %s %s %s rg /F1 %s Tf %s %s Td <%s> Tj ET\n", $this->f($rgb[0]), $this->f($rgb[1]), $this->f($rgb[2]), $this->f($size), $this->f($x), $this->f($y), $h);
    }
    private function rect($x, $y, $w, $h, $rgb) {
        $this->cur .= sprintf("%s %s %s rg %s %s %s %s re f\n", $this->f($rgb[0]), $this->f($rgb[1]), $this->f($rgb[2]), $this->f($x), $this->f($y), $this->f($w), $this->f($h));
    }
    private function hline($x1, $x2, $y, $rgb) {
        $this->cur .= sprintf("%s %s %s RG 0.5 w %s %s m %s %s l S\n", $this->f($rgb[0]), $this->f($rgb[1]), $this->f($rgb[2]), $this->f($x1), $this->f($y), $this->f($x2), $this->f($y));
    }

    public function build(array $d) {
        $headers = array_map([$this, 'clean'], $d['headers']);
        $rows = [];
        foreach ($d['rows'] as $r) $rows[] = array_map([$this, 'clean'], $r);
        $title = $this->clean($d['title']);
        $n = count($headers);
        $fs = $this->fs;

        // естественная и минимальная ширина колонок
        $nat = []; $min = [];
        for ($c = 0; $c < $n; $c++) {
            $nw = $this->width($headers[$c], $fs);
            $mw = 0;
            foreach (preg_split('/ +/', $headers[$c]) as $w) $mw = max($mw, $this->width($w, $fs));
            foreach ($rows as $r) {
                $nw = max($nw, $this->width($r[$c], $fs));
                foreach (preg_split('/ +/', $r[$c]) as $w) $mw = max($mw, min($this->width($w, $fs), 120));
            }
            $nat[$c] = $nw + 2 * $this->pad;
            $min[$c] = min($nw, $mw) + 2 * $this->pad;
        }
        $total = array_sum($nat);
        // ориентация: альбомная, если таблица не помещается в книжную
        $portraitW = 595.28 - 2 * $this->mx;
        if ($total > $portraitW) { $this->pw = 841.89; $this->ph = 595.28; } else { $this->pw = 595.28; $this->ph = 841.89; }
        $avail = $this->pw - 2 * $this->mx;
        $widths = $nat;
        if ($total > $avail) {
            // сжимаем пропорционально, но не меньше минимума; остаток перераспределяем
            for ($pass = 0; $pass < 5; $pass++) {
                $fixed = 0; $flex = 0;
                foreach ($widths as $c => $w) { if ($w <= $min[$c] + 0.01) $fixed += $w; else $flex += $nat[$c]; }
                $space = $avail - $fixed;
                foreach ($widths as $c => $w) {
                    if ($w <= $min[$c] + 0.01 && $nat[$c] > $min[$c]) continue;
                    $widths[$c] = max($min[$c], $nat[$c] * $space / max(1, $flex));
                }
            }
            $sum = array_sum($widths);
            if ($sum > $avail) { foreach ($widths as $c => $w) $widths[$c] = $w * $avail / $sum; }
        } else {
            $extra = ($avail - $total) / $n;     // растягиваем до ширины страницы
            foreach ($widths as $c => $w) $widths[$c] = $w + $extra;
        }

        $this->pages = [];
        $this->newPage();
        $y = $this->ph - $this->my;
        if ($title !== '') {
            foreach ($this->wrap($title, $avail, 15) as $tl) {
                $y -= 15;
                $this->text($this->mx, $y, $tl, 15, [0.1, 0.15, 0.25]);
                $y -= 4;
            }
            $y -= 8;
        }
        $drawHeader = function () use (&$y, $headers, $widths, $n, $fs, $d) {
            $h = $this->lh + 2 * 3;
            $this->rect($this->mx, $y - $h, array_sum($widths), $h, [0.86, 0.9, 0.96]);
            $x = $this->mx;
            for ($c = 0; $c < $n; $c++) {
                $lines = $this->wrap($headers[$c], $widths[$c] - 2 * $this->pad, $fs);
                $hl = $lines[0] ?? '';
                $hx = $x + $this->pad;
                if (($d['types'][$c] ?? '') === 'num') $hx = $x + $widths[$c] - $this->pad - $this->width($hl, $fs);
                $this->text($hx, $y - 3 - $this->lh + 3, $hl, $fs, [0.1, 0.15, 0.25]);
                $x += $widths[$c];
            }
            $y -= $h;
        };
        $drawHeader();
        foreach ($rows as $r) {
            $cellLines = [];
            $maxLines = 1;
            for ($c = 0; $c < $n; $c++) {
                $cellLines[$c] = $this->wrap($r[$c], $widths[$c] - 2 * $this->pad, $fs);
                $maxLines = max($maxLines, count($cellLines[$c]));
            }
            $h = $maxLines * $this->lh + 2 * 3;
            if ($y - $h < $this->my) {
                $this->endPage();
                $this->newPage();
                $y = $this->ph - $this->my;
                $drawHeader();
            }
            $x = $this->mx;
            for ($c = 0; $c < $n; $c++) {
                $ty = $y - 3 - $this->lh + 3;
                foreach ($cellLines[$c] as $line) {
                    $tx = $x + $this->pad;
                    if (($d['types'][$c] ?? '') === 'num') $tx = $x + $widths[$c] - $this->pad - $this->width($line, $fs);
                    $this->text($tx, $ty, $line, $fs);
                    $ty -= $this->lh;
                }
                $x += $widths[$c];
            }
            $y -= $h;
            $this->hline($this->mx, $this->mx + array_sum($widths), $y, [0.8, 0.83, 0.88]);
        }
        $this->endPage();
        return $this->serialize();
    }

    private function newPage() { $this->cur = ''; }
    private function endPage() { $this->pages[] = $this->cur; $this->cur = ''; }

    private function serialize() {
        $objs = [];                      // номер => тело
        $addObj = function ($body) use (&$objs) { $objs[] = $body; return count($objs); };
        $objs[] = '';                    // 1: Catalog (заполним ниже)
        $objs[] = '';                    // 2: Pages
        $pageCount = count($this->pages);
        $fontObj = 3 + $pageCount * 2;   // после страниц и их потоков
        $kids = [];
        for ($i = 0; $i < $pageCount; $i++) {
            $pageNo = 3 + $i * 2;
            $contentNo = $pageNo + 1;
            $kids[] = $pageNo . ' 0 R';
            $stream = $this->pages[$i];
            $z = function_exists('gzcompress') ? gzcompress($stream, 6) : false;
            $filter = $z !== false ? ' /Filter /FlateDecode' : '';
            $data = $z !== false ? $z : $stream;
            $objs[] = sprintf('<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %s %s] /Resources << /Font << /F1 %d 0 R >> >> /Contents %d 0 R >>', $this->f($this->pw), $this->f($this->ph), $fontObj, $contentNo);
            $objs[] = '<< /Length ' . strlen($data) . $filter . ' >>' . "\nstream\n" . $data . "\nendstream";
        }
        $objs[0] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objs[1] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . $pageCount . ' >>';

        $F = $this->font;
        $scale = 1000 / $F->unitsPerEm;
        // Type0 -> CIDFontType2 -> FontDescriptor -> FontFile2; ToUnicode
        $type0 = $fontObj; $cid = $fontObj + 1; $desc = $fontObj + 2; $file = $fontObj + 3; $tou = $fontObj + 4;
        $objs[] = '<< /Type /Font /Subtype /Type0 /BaseFont /AAAAAA+DejaVuSans /Encoding /Identity-H /DescendantFonts [' . $cid . ' 0 R] /ToUnicode ' . $tou . ' 0 R >>';
        ksort($this->used);
        $w = '';
        foreach ($this->used as $g => $cp) $w .= $g . ' [' . round(($F->advance[$g] ?? 0) * $scale) . '] ';
        $objs[] = '<< /Type /Font /Subtype /CIDFontType2 /BaseFont /AAAAAA+DejaVuSans /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /FontDescriptor ' . $desc . ' 0 R /CIDToGIDMap /Identity /DW 600 /W [' . $w . '] >>';
        $bb = $F->bbox;
        $objs[] = sprintf('<< /Type /FontDescriptor /FontName /AAAAAA+DejaVuSans /Flags 4 /FontBBox [%d %d %d %d] /ItalicAngle 0 /Ascent %d /Descent %d /CapHeight 729 /StemV 80 /FontFile2 %d 0 R >>',
            round($bb[0] * $scale), round($bb[1] * $scale), round($bb[2] * $scale), round($bb[3] * $scale), round($F->ascent * $scale), round($F->descent * $scale), $file);
        $fz = function_exists('gzcompress') ? gzcompress($F->data, 6) : false;
        $fd = $fz !== false ? $fz : $F->data;
        $objs[] = '<< /Length ' . strlen($fd) . ' /Length1 ' . strlen($F->data) . ($fz !== false ? ' /Filter /FlateDecode' : '') . ' >>' . "\nstream\n" . $fd . "\nendstream";
        // ToUnicode
        $cm = "/CIDInit /ProcSet findresource begin\n12 dict begin\nbegincmap\n/CIDSystemInfo << /Registry (Adobe) /Ordering (UCS) /Supplement 0 >> def\n/CMapName /Adobe-Identity-UCS def\n/CMapType 2 def\n1 begincodespacerange\n<0000> <FFFF>\nendcodespacerange\n";
        $chunks = array_chunk($this->used, 100, true);
        foreach ($chunks as $chunk) {
            $cm .= count($chunk) . " beginbfchar\n";
            foreach ($chunk as $g => $cp) $cm .= sprintf("<%04X> <%04X>\n", $g, $cp);
            $cm .= "endbfchar\n";
        }
        $cm .= "endcmap\nCMapName currentdict /CMap defineresource pop\nend\nend";
        $objs[] = '<< /Length ' . strlen($cm) . ' >>' . "\nstream\n" . $cm . "\nendstream";

        $pdf = "%PDF-1.5\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];
        foreach ($objs as $i => $body) {
            $offsets[$i + 1] = strlen($pdf);
            $pdf .= ($i + 1) . " 0 obj\n" . $body . "\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objs) + 1) . "\n0000000000 65535 f \n";
        foreach ($offsets as $o) $pdf .= sprintf("%010d 00000 n \n", $o);
        $pdf .= "trailer\n<< /Size " . (count($objs) + 1) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF";
        return $pdf;
    }
}

/** PDF (строка) с заголовком отчёта и таблицей. Бросает RuntimeException, если шрифт недоступен. */
function exportPdf(array $d) {
    $p = new ExportPdf(__DIR__ . '/fonts/DejaVuSans-subset.ttf');
    return $p->build($d);
}
