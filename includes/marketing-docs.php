<?php

/**
 * Documents for Output Generator and Reports: one document array, rendered as a print page ("Print / save as PDF")
 * or as a Word file.
 *
 * Document: title, subtitle?, meta [[label, value]…]?, notice?, filename, blocks.
 * Blocks (type => fields):
 *   h1 / h2 / h3 => text          p => text          note => text         pagebreak
 *   bullets => items, ordered?     kv => rows [[label, value]…]
 *   table => head, rows, widths? (percent per column), empty?
 *   metrics => items [[label, value, change?, hint?]…]
 *   markdown => text (expanded to the blocks above)
 * Text accepts **bold**, *italic* and [label](https://…) links; everything else is literal.
 */

const MKT_DOC_INTERNAL_NOTICE = 'Internal use only — not approved for distribution outside NutraAxis.';

/* ---------- Inline text ---------- */

/** Split text into runs: [['text' => …, 'b' => bool, 'i' => bool, 'link' => ?string]…]. */
function mkt_doc_runs(string $text): array
{
    $runs = [];
    $pattern = '/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)|\*\*(.+?)\*\*|(?<![\w*])\*(?!\s)(.+?)(?<!\s)\*(?![\w*])/su';
    $offset = 0;
    while (preg_match($pattern, $text, $m, PREG_OFFSET_CAPTURE, $offset)) {
        $start = $m[0][1];
        if ($start > $offset) {
            $runs[] = ['text' => substr($text, $offset, $start - $offset), 'b' => false, 'i' => false, 'link' => null];
        }
        if (($m[1][0] ?? '') !== '' && $m[1][1] >= 0) {
            $runs[] = ['text' => $m[1][0], 'b' => false, 'i' => false, 'link' => $m[2][0]];
        } elseif (isset($m[3]) && $m[3][1] >= 0) {
            $runs[] = ['text' => $m[3][0], 'b' => true, 'i' => false, 'link' => null];
        } else {
            $runs[] = ['text' => $m[4][0], 'b' => false, 'i' => true, 'link' => null];
        }
        $offset = $start + strlen($m[0][0]);
    }
    if ($offset < strlen($text)) {
        $runs[] = ['text' => substr($text, $offset), 'b' => false, 'i' => false, 'link' => null];
    }

    return $runs;
}

function mkt_doc_inline_html(string $text): string
{
    $html = '';
    foreach (mkt_doc_runs($text) as $run) {
        $part = nl2br(htmlspecialchars($run['text'], ENT_QUOTES));
        if ($run['link'] !== null) {
            $part = '<a href="' . htmlspecialchars($run['link'], ENT_QUOTES) . '" target="_blank" rel="noopener">' . $part . '</a>';
        }
        $html .= $run['b'] ? "<strong>{$part}</strong>" : ($run['i'] ? "<em>{$part}</em>" : $part);
    }

    return $html;
}

/** Escape a user or data value so it prints literally inside document text (no bold/italic/link markup). */
function mkt_doc_literal(?string $value): string
{
    return str_replace(['*', '[', ']'], ['∗', '［', '］'], (string) $value);
}

/** "Generated" meta row: Central time and the signed-in user. */
function mkt_doc_generated_meta(): array
{
    return ['Generated', (new DateTimeImmutable('now', new DateTimeZone('America/Chicago')))->format('M j, Y g:i A') . ' by ' . mkt_doc_literal((string) (auth_user()['UserName'] ?? ''))];
}

/** Record a generated document. Logging never blocks the download. */
function mkt_output_log(string $type, string $format, string $title, string $href): void
{
    try {
        db()->prepare('INSERT INTO dbo.MktOutputLog (OutputType, Format, Title, Href, UserID) VALUES (:t, :f, :title, :h, :u)')->execute([
            't' => mb_substr($type, 0, 40), 'f' => $format === 'docx' ? 'docx' : 'print', 'title' => mb_substr($title, 0, 300),
            'h' => mb_substr($href, 0, 500), 'u' => marketing_user_id(),
        ]);
    } catch (PDOException $e) {
        error_log('mkt_output_log: ' . $e->getMessage());
    }
}

function mkt_output_recent(int $limit = 15): array
{
    $limit = max(1, min(100, $limit));

    return db()->query("SELECT TOP ($limit) l.OutputType, l.Format, l.Title, l.Href, CONVERT(varchar(19), l.CreatedAt, 120) AS CreatedAt, u.UserName
        FROM dbo.MktOutputLog l LEFT JOIN dbo.[User] u ON u.UserID = l.UserID ORDER BY l.CreatedAt DESC, l.OutputLogID DESC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/* ---------- Markdown ---------- */

/** Blocks for the Markdown subset the content prompts produce (headings, paragraphs, lists, quotes, tables). */
function mkt_doc_markdown_blocks(string $markdown): array
{
    $blocks = [];
    $para = [];
    $list = null;
    $items = [];
    $rows = [];
    $flushPara = static function () use (&$para, &$blocks): void {
        if ($para !== []) {
            $blocks[] = ['type' => 'p', 'text' => implode(' ', $para)];
            $para = [];
        }
    };
    $flushList = static function () use (&$list, &$items, &$blocks): void {
        if ($list !== null) {
            $blocks[] = ['type' => 'bullets', 'items' => $items, 'ordered' => $list === 'ol'];
            $list = null;
            $items = [];
        }
    };
    $flushTable = static function () use (&$rows, &$blocks): void {
        if ($rows === []) {
            return;
        }
        $split = static fn(string $row): array => array_map('trim', explode('|', trim($row, '| ')));
        $header = count($rows) > 1 && preg_match('/^\|?\s*:?-{3,}/', $rows[1]) === 1;
        $body = array_map($split, $header ? array_slice($rows, 2) : $rows);
        $blocks[] = ['type' => 'table', 'head' => $header ? $split($rows[0]) : [], 'rows' => $body];
        $rows = [];
    };
    foreach (preg_split('/\r?\n/', $markdown) ?: [] as $line) {
        $trim = trim($line);
        if (str_starts_with($trim, '|')) {
            $flushPara();
            $flushList();
            $rows[] = $trim;
            continue;
        }
        $flushTable();
        if ($trim === '') {
            $flushPara();
            $flushList();
            continue;
        }
        if (preg_match('/^(#{1,4})\s+(.+)$/', $trim, $m)) {
            $flushPara();
            $flushList();
            $blocks[] = ['type' => strlen($m[1]) <= 2 ? 'h2' : 'h3', 'text' => $m[2]];
            continue;
        }
        if (preg_match('/^(-{3,}|\*{3,})$/', $trim)) {
            $flushPara();
            $flushList();
            continue;
        }
        if (preg_match('/^[-*]\s+(.+)$/', $trim, $m) || preg_match('/^\d+[.)]\s+(.+)$/', $trim, $m)) {
            $flushPara();
            $kind = preg_match('/^\d/', $trim) ? 'ol' : 'ul';
            if ($list !== $kind) {
                $flushList();
                $list = $kind;
            }
            $items[] = $m[1];
            continue;
        }
        if (str_starts_with($trim, '>')) {
            $flushPara();
            $flushList();
            $blocks[] = ['type' => 'note', 'text' => ltrim(substr($trim, 1))];
            continue;
        }
        $flushList();
        $para[] = $trim;
    }
    $flushPara();
    $flushList();
    $flushTable();

    return $blocks;
}

/** The document's blocks with markdown blocks expanded. */
function mkt_doc_blocks(array $doc): array
{
    $out = [];
    foreach ($doc['blocks'] ?? [] as $block) {
        if (($block['type'] ?? '') === 'markdown') {
            array_push($out, ...mkt_doc_markdown_blocks((string) $block['text']));
        } else {
            $out[] = $block;
        }
    }

    return $out;
}

/* ---------- HTML (print page) ---------- */

function mkt_doc_html(array $doc): string
{
    $e = static fn(?string $s): string => htmlspecialchars((string) $s, ENT_QUOTES);
    $html = '<article class="mkt-doc">';
    $html .= '<header class="mkt-doc-head"><p class="mkt-doc-brand">NutraAxis</p><h1>' . $e($doc['title']) . '</h1>';
    if (!empty($doc['subtitle'])) {
        $html .= '<p class="mkt-doc-subtitle">' . mkt_doc_inline_html((string) $doc['subtitle']) . '</p>';
    }
    if (!empty($doc['meta'])) {
        $html .= '<dl class="mkt-doc-meta">';
        foreach ($doc['meta'] as [$label, $value]) {
            $html .= '<div><dt>' . $e($label) . '</dt><dd>' . mkt_doc_inline_html((string) $value) . '</dd></div>';
        }
        $html .= '</dl>';
    }
    $html .= '</header>';

    foreach (mkt_doc_blocks($doc) as $block) {
        $type = (string) ($block['type'] ?? '');
        $html .= match ($type) {
            'h1', 'h2', 'h3' => "<{$type}>" . mkt_doc_inline_html((string) $block['text']) . "</{$type}>",
            'p'         => '<p>' . mkt_doc_inline_html((string) $block['text']) . '</p>',
            'note'      => '<p class="mkt-doc-note">' . mkt_doc_inline_html((string) $block['text']) . '</p>',
            'pagebreak' => '<div class="mkt-doc-pagebreak"></div>',
            'bullets'   => mkt_doc_html_list($block),
            'kv'        => mkt_doc_html_kv($block),
            'table'     => mkt_doc_html_table($block),
            'metrics'   => mkt_doc_html_metrics($block),
            default     => '',
        };
    }
    if (!empty($doc['notice'])) {
        $html .= '<footer class="mkt-doc-notice">' . $e($doc['notice']) . '</footer>';
    }

    return $html . '</article>';
}

function mkt_doc_html_list(array $block): string
{
    $tag = !empty($block['ordered']) ? 'ol' : 'ul';

    return "<{$tag}>" . implode('', array_map(static fn($i): string => '<li>' . mkt_doc_inline_html((string) $i) . '</li>', $block['items'] ?? [])) . "</{$tag}>";
}

function mkt_doc_html_kv(array $block): string
{
    $html = '<dl class="mkt-doc-kv">';
    foreach ($block['rows'] ?? [] as [$label, $value]) {
        $html .= '<dt>' . htmlspecialchars((string) $label) . '</dt><dd>' . mkt_doc_inline_html((string) $value) . '</dd>';
    }

    return $html . '</dl>';
}

function mkt_doc_html_table(array $block): string
{
    $rows = $block['rows'] ?? [];
    if ($rows === []) {
        return '<p class="mkt-doc-note">' . htmlspecialchars((string) ($block['empty'] ?? 'Nothing to show.')) . '</p>';
    }
    $widths = $block['widths'] ?? [];
    $html = '<table class="mkt-doc-table">';
    if (!empty($block['head'])) {
        $html .= '<thead><tr>';
        foreach ($block['head'] as $i => $cell) {
            $style = isset($widths[$i]) ? ' style="width:' . (int) $widths[$i] . '%"' : '';
            $html .= "<th{$style}>" . htmlspecialchars((string) $cell) . '</th>';
        }
        $html .= '</tr></thead>';
    }
    $html .= '<tbody>';
    foreach ($rows as $row) {
        $html .= '<tr>' . implode('', array_map(static fn($c): string => '<td>' . mkt_doc_inline_html((string) $c) . '</td>', $row)) . '</tr>';
    }

    return $html . '</tbody></table>';
}

function mkt_doc_html_metrics(array $block): string
{
    $html = '<div class="mkt-doc-metrics">';
    foreach ($block['items'] ?? [] as $item) {
        [$label, $value] = $item;
        $change = (string) ($item[2] ?? '');
        $class = str_starts_with($change, '+') || $change === 'new' ? ' is-up' : (str_starts_with($change, '−') ? ' is-down' : '');
        $html .= '<div class="mkt-doc-metric"><span class="mkt-doc-metric-label">' . htmlspecialchars((string) $label) . '</span>'
            . '<span class="mkt-doc-metric-value">' . htmlspecialchars((string) $value) . '</span>'
            . ($change !== '' ? '<span class="mkt-doc-metric-change' . $class . '">' . htmlspecialchars($change) . '</span>' : '')
            . (!empty($item[3]) ? '<span class="mkt-doc-metric-hint">' . htmlspecialchars((string) $item[3]) . '</span>' : '')
            . '</div>';
    }

    return $html . '</div>';
}

/** Standalone print page for a document: toolbar (back, Word, print) and the document. */
function mkt_doc_render_print_page(array $doc, string $backHref, string $backLabel, ?string $wordHref): void
{
    $e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES);
    $css = (int) @filemtime(dirname(__DIR__) . '/assets/css/marketing-docs.css');
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8" /><meta name="viewport" content="width=device-width, initial-scale=1.0" />'
        . '<title>' . $e((string) $doc['title']) . ' | NutraAxis</title><meta name="robots" content="noindex" />'
        . '<link rel="icon" type="image/svg+xml" href="/favicon.svg" />'
        . '<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />'
        . '<link rel="stylesheet" href="/assets/css/marketing-docs.css?v=' . $css . '" /></head><body class="mkt-doc-page">'
        . '<div class="mkt-doc-toolbar"><a href="' . $e($backHref) . '">‹ ' . $e($backLabel) . '</a><span>'
        . ($wordHref !== null ? '<a class="mkt-doc-btn" href="' . $e($wordHref) . '">Download Word</a>' : '')
        . '<button type="button" class="mkt-doc-btn is-primary" onclick="window.print()">Print / save as PDF</button></span></div>'
        . mkt_doc_html($doc) . '</body></html>';
}

/* ---------- Word (.docx) ---------- */

const MKT_DOCX_TEXT_WIDTH = 10080;
const MKT_DOCX_TEAL = '2A6B65';
const MKT_DOCX_MUTED = '5A7170';

function mkt_docx_x(string $s): string
{
    $s = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $s) ?? '';

    return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

/** Builds document.xml parts and collects hyperlink relationships and numbering instances. */
final class MktDocxWriter
{
    /** @var array<string, string> url => rId */
    public array $links = [];
    /** @var list<int> numIds of ordered lists (each restarts at 1) */
    public array $orderedNums = [];

    public function runs(string $text, array $style = []): string
    {
        $xml = '';
        foreach (mkt_doc_runs($text) as $run) {
            $props = $run['link'] !== null ? '<w:rStyle w:val="Hyperlink"/>' : '';
            if ($run['b'] || !empty($style['b'])) {
                $props .= '<w:b/>';
            }
            if ($run['i'] || !empty($style['i'])) {
                $props .= '<w:i/>';
            }
            if (!empty($style['color'])) {
                $props .= '<w:color w:val="' . $style['color'] . '"/>';
            }
            if (!empty($style['size'])) {
                $props .= '<w:sz w:val="' . (int) $style['size'] . '"/><w:szCs w:val="' . (int) $style['size'] . '"/>';
            }
            $lines = explode("\n", $run['text']);
            $body = '';
            foreach ($lines as $i => $line) {
                $body .= ($i > 0 ? '<w:br/>' : '') . '<w:t xml:space="preserve">' . mkt_docx_x($line) . '</w:t>';
            }
            $r = '<w:r>' . ($props !== '' ? "<w:rPr>{$props}</w:rPr>" : '') . $body . '</w:r>';
            if ($run['link'] !== null) {
                $id = $this->links[$run['link']] ??= 'rIdL' . (count($this->links) + 1);
                $r = '<w:hyperlink r:id="' . $id . '">' . $r . '</w:hyperlink>';
            }
            $xml .= $r;
        }

        return $xml;
    }

    public function para(string $text, string $pStyle = '', array $runStyle = [], string $extraPPr = ''): string
    {
        $ppr = ($pStyle !== '' ? '<w:pStyle w:val="' . $pStyle . '"/>' : '') . $extraPPr;

        return '<w:p>' . ($ppr !== '' ? "<w:pPr>{$ppr}</w:pPr>" : '') . $this->runs($text, $runStyle) . '</w:p>';
    }

    public function table(array $head, array $rows, array $widths = [], bool $grid = true): string
    {
        $cols = max(count($head), ...array_map('count', $rows ?: [[]]));
        if ($cols === 0) {
            return '';
        }
        $w = [];
        $given = array_sum(array_map('floatval', array_slice($widths, 0, $cols)));
        for ($i = 0; $i < $cols; $i++) {
            $pct = isset($widths[$i]) && $given > 0 ? (float) $widths[$i] / $given : 1 / $cols;
            $w[] = (int) round(MKT_DOCX_TEXT_WIDTH * $pct);
        }
        $border = $grid ? 'D6ECEA' : 'FFFFFF';
        $xml = '<w:tbl><w:tblPr><w:tblW w:w="' . MKT_DOCX_TEXT_WIDTH . '" w:type="dxa"/>'
            . '<w:tblBorders>' . implode('', array_map(static fn($s) => "<w:{$s} w:val=\"single\" w:sz=\"4\" w:space=\"0\" w:color=\"{$border}\"/>", ['top', 'left', 'bottom', 'right', 'insideH', 'insideV'])) . '</w:tblBorders>'
            . '<w:tblLayout w:type="fixed"/><w:tblCellMar><w:top w:w="60" w:type="dxa"/><w:left w:w="100" w:type="dxa"/><w:bottom w:w="60" w:type="dxa"/><w:right w:w="100" w:type="dxa"/></w:tblCellMar>'
            . '</w:tblPr><w:tblGrid>' . implode('', array_map(static fn($x) => "<w:gridCol w:w=\"{$x}\"/>", $w)) . '</w:tblGrid>';
        $cell = function (string $text, int $width, bool $isHead) {
            $shade = $isHead ? '<w:shd w:val="clear" w:color="auto" w:fill="E8F5F4"/>' : '';

            return '<w:tc><w:tcPr><w:tcW w:w="' . $width . '" w:type="dxa"/>' . $shade . '</w:tcPr>'
                . $this->para($text, 'TableText', $isHead ? ['b' => true, 'color' => MKT_DOCX_TEAL] : []) . '</w:tc>';
        };
        if ($head !== []) {
            $xml .= '<w:tr><w:trPr><w:tblHeader/><w:cantSplit/></w:trPr>';
            for ($i = 0; $i < $cols; $i++) {
                $xml .= $cell((string) ($head[$i] ?? ''), $w[$i], true);
            }
            $xml .= '</w:tr>';
        }
        foreach ($rows as $row) {
            $xml .= '<w:tr><w:trPr><w:cantSplit/></w:trPr>';
            for ($i = 0; $i < $cols; $i++) {
                $xml .= $cell((string) ($row[$i] ?? ''), $w[$i], false);
            }
            $xml .= '</w:tr>';
        }

        return $xml . '</w:tbl>' . $this->para('', 'Spacer');
    }

    public function block(array $block): string
    {
        $type = (string) ($block['type'] ?? '');

        return match ($type) {
            'h1'        => $this->para((string) $block['text'], 'Heading1'),
            'h2'        => $this->para((string) $block['text'], 'Heading2'),
            'h3'        => $this->para((string) $block['text'], 'Heading3'),
            'p'         => $this->para((string) $block['text']),
            'note'      => $this->para((string) $block['text'], 'Note'),
            'pagebreak' => '<w:p><w:r><w:br w:type="page"/></w:r></w:p>',
            'bullets'   => $this->bullets($block),
            'kv'        => $this->table([], array_map(static fn($r) => ['**' . mkt_doc_literal((string) $r[0]) . '**', (string) $r[1]], $block['rows'] ?? []), [28, 72], false),
            'table'     => ($block['rows'] ?? []) === []
                ? $this->para((string) ($block['empty'] ?? 'Nothing to show.'), 'Note')
                : $this->table($block['head'] ?? [], $block['rows'], $block['widths'] ?? []),
            'metrics'   => $this->metrics($block['items'] ?? []),
            default     => '',
        };
    }

    private function bullets(array $block): string
    {
        $numId = 1;
        if (!empty($block['ordered'])) {
            $numId = 2 + count($this->orderedNums);
            $this->orderedNums[] = $numId;
        }
        $xml = '';
        foreach ($block['items'] ?? [] as $item) {
            $xml .= $this->para((string) $item, 'ListParagraph', [], '<w:numPr><w:ilvl w:val="0"/><w:numId w:val="' . $numId . '"/></w:numPr>');
        }

        return $xml;
    }

    private function metrics(array $items): string
    {
        $xml = '';
        foreach (array_chunk($items, 4) as $chunk) {
            $labels = array_map(static fn($i) => mkt_doc_literal((string) $i[0]), $chunk);
            $values = array_map(static fn($i) => '**' . mkt_doc_literal((string) $i[1]) . '**' . (!empty($i[2]) ? '  ' . mkt_doc_literal((string) $i[2]) : ''), $chunk);
            $hints = array_map(static fn($i) => mkt_doc_literal((string) ($i[3] ?? '')), $chunk);
            $rows = [$values];
            if (array_filter($hints) !== []) {
                $rows[] = $hints;
            }
            $xml .= $this->table($labels, $rows);
        }

        return $xml;
    }
}

/** The document as a .docx file (binary string). */
function mkt_doc_docx(array $doc): string
{
    $w = new MktDocxWriter();
    $body = $w->para('NutraAxis', 'Brand') . $w->para((string) $doc['title'], 'Title');
    if (!empty($doc['subtitle'])) {
        $body .= $w->para((string) $doc['subtitle'], 'Subtitle');
    }
    if (!empty($doc['meta'])) {
        $body .= $w->block(['type' => 'kv', 'rows' => $doc['meta']]);
    }
    foreach (mkt_doc_blocks($doc) as $block) {
        $body .= $w->block($block);
    }
    $notice = (string) ($doc['notice'] ?? '');
    $sect = '<w:sectPr><w:footerReference w:type="default" r:id="rIdFooter"/><w:pgSz w:w="12240" w:h="15840"/>'
        . '<w:pgMar w:top="1080" w:right="1080" w:bottom="1080" w:left="1080" w:header="540" w:footer="540" w:gutter="0"/></w:sectPr>';
    $ns = 'xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"';
    $document = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:document ' . $ns . '><w:body>' . $body . $sect . '</w:body></w:document>';

    $footer = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:ftr ' . $ns . '><w:p><w:pPr><w:pStyle w:val="Footer"/><w:tabs><w:tab w:val="right" w:pos="' . MKT_DOCX_TEXT_WIDTH . '"/></w:tabs></w:pPr>'
        . '<w:r><w:t xml:space="preserve">' . mkt_docx_x($notice !== '' ? $notice : (string) $doc['title']) . '</w:t></w:r><w:r><w:tab/><w:t xml:space="preserve">Page </w:t></w:r>'
        . '<w:r><w:fldChar w:fldCharType="begin"/></w:r><w:r><w:instrText xml:space="preserve"> PAGE </w:instrText></w:r><w:r><w:fldChar w:fldCharType="separate"/></w:r><w:r><w:t>1</w:t></w:r><w:r><w:fldChar w:fldCharType="end"/></w:r>'
        . '</w:p></w:ftr>';

    $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rIdStyles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
        . '<Relationship Id="rIdNumbering" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/numbering" Target="numbering.xml"/>'
        . '<Relationship Id="rIdFooter" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/footer" Target="footer1.xml"/>';
    foreach ($w->links as $url => $id) {
        $rels .= '<Relationship Id="' . $id . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="' . mkt_docx_x($url) . '" TargetMode="External"/>';
    }
    $rels .= '</Relationships>';

    $numbering = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:numbering ' . $ns . '>'
        . '<w:abstractNum w:abstractNumId="0"><w:multiLevelType w:val="singleLevel"/><w:lvl w:ilvl="0"><w:start w:val="1"/><w:numFmt w:val="bullet"/><w:lvlText w:val="•"/><w:lvlJc w:val="left"/><w:pPr><w:ind w:left="360" w:hanging="260"/></w:pPr></w:lvl></w:abstractNum>'
        . '<w:abstractNum w:abstractNumId="1"><w:multiLevelType w:val="singleLevel"/><w:lvl w:ilvl="0"><w:start w:val="1"/><w:numFmt w:val="decimal"/><w:lvlText w:val="%1."/><w:lvlJc w:val="left"/><w:pPr><w:ind w:left="360" w:hanging="300"/></w:pPr></w:lvl></w:abstractNum>'
        . '<w:num w:numId="1"><w:abstractNumId w:val="0"/></w:num>';
    foreach ($w->orderedNums as $numId) {
        $numbering .= '<w:num w:numId="' . $numId . '"><w:abstractNumId w:val="1"/><w:lvlOverride w:ilvl="0"><w:startOverride w:val="1"/></w:lvlOverride></w:num>';
    }
    $numbering .= '</w:numbering>';

    $tmp = tempnam(sys_get_temp_dir(), 'mktdocx');
    $zip = new ZipArchive();
    if ($tmp === false || $zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Could not create the Word file.');
    }
    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
        . '<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>'
        . '<Override PartName="/word/numbering.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.numbering+xml"/>'
        . '<Override PartName="/word/footer1.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.footer+xml"/>'
        . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/></Types>');
    $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
        . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/></Relationships>');
    $zip->addFromString('docProps/core.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
        . '<dc:title>' . mkt_docx_x((string) $doc['title']) . '</dc:title><dc:creator>NutraAxis Operations</dc:creator>'
        . '<dcterms:created xsi:type="dcterms:W3CDTF">' . gmdate('Y-m-d\TH:i:s\Z') . '</dcterms:created></cp:coreProperties>');
    $zip->addFromString('word/document.xml', $document);
    $zip->addFromString('word/styles.xml', mkt_docx_styles());
    $zip->addFromString('word/numbering.xml', $numbering);
    $zip->addFromString('word/footer1.xml', $footer);
    $zip->addFromString('word/_rels/document.xml.rels', $rels);
    $zip->close();
    $bytes = (string) file_get_contents($tmp);
    @unlink($tmp);

    return $bytes;
}

function mkt_docx_styles(): string
{
    $teal = MKT_DOCX_TEAL;
    $muted = MKT_DOCX_MUTED;
    $style = static fn(string $id, string $name, string $ppr, string $rpr, string $type = 'paragraph', string $extra = '') =>
        "<w:style w:type=\"{$type}\" w:styleId=\"{$id}\"><w:name w:val=\"{$name}\"/>{$extra}<w:pPr>{$ppr}</w:pPr><w:rPr>{$rpr}</w:rPr></w:style>";

    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
        . '<w:docDefaults><w:rPrDefault><w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri" w:eastAsia="Calibri" w:cs="Calibri"/><w:sz w:val="21"/><w:szCs w:val="21"/><w:color w:val="1A2E2D"/><w:lang w:val="en-US"/></w:rPr></w:rPrDefault>'
        . '<w:pPrDefault><w:pPr><w:spacing w:after="120" w:line="264" w:lineRule="auto"/></w:pPr></w:pPrDefault></w:docDefaults>'
        . $style('Normal', 'Normal', '', '', 'paragraph', '<w:qFormat/>')
        . $style('Brand', 'Brand', '<w:spacing w:after="40"/>', "<w:b/><w:caps/><w:color w:val=\"{$teal}\"/><w:sz w:val=\"17\"/><w:spacing w:val=\"20\"/>", 'paragraph', '<w:basedOn w:val="Normal"/>')
        . $style('Title', 'Title', '<w:spacing w:after="80"/>', "<w:b/><w:color w:val=\"1A2E2D\"/><w:sz w:val=\"40\"/>", 'paragraph', '<w:basedOn w:val="Normal"/><w:qFormat/>')
        . $style('Subtitle', 'Subtitle', '<w:spacing w:after="200"/>', "<w:color w:val=\"{$muted}\"/><w:sz w:val=\"24\"/>", 'paragraph', '<w:basedOn w:val="Normal"/><w:qFormat/>')
        . $style('Heading1', 'heading 1', '<w:keepNext/><w:pBdr><w:bottom w:val="single" w:sz="6" w:space="4" w:color="D6ECEA"/></w:pBdr><w:spacing w:before="360" w:after="120"/><w:outlineLvl w:val="0"/>', "<w:b/><w:color w:val=\"{$teal}\"/><w:sz w:val=\"30\"/>", 'paragraph', '<w:basedOn w:val="Normal"/><w:next w:val="Normal"/><w:qFormat/>')
        . $style('Heading2', 'heading 2', '<w:keepNext/><w:spacing w:before="280" w:after="100"/><w:outlineLvl w:val="1"/>', "<w:b/><w:color w:val=\"{$teal}\"/><w:sz w:val=\"26\"/>", 'paragraph', '<w:basedOn w:val="Normal"/><w:next w:val="Normal"/><w:qFormat/>')
        . $style('Heading3', 'heading 3', '<w:keepNext/><w:spacing w:before="200" w:after="80"/><w:outlineLvl w:val="2"/>', '<w:b/><w:sz w:val="22"/>', 'paragraph', '<w:basedOn w:val="Normal"/><w:next w:val="Normal"/><w:qFormat/>')
        . $style('Note', 'Note', '<w:spacing w:after="120"/>', "<w:i/><w:color w:val=\"{$muted}\"/><w:sz w:val=\"19\"/>", 'paragraph', '<w:basedOn w:val="Normal"/>')
        . $style('TableText', 'Table Text', '<w:spacing w:after="0" w:line="252" w:lineRule="auto"/>', '<w:sz w:val="19"/>', 'paragraph', '<w:basedOn w:val="Normal"/>')
        . $style('Spacer', 'Spacer', '<w:spacing w:after="80" w:line="120" w:lineRule="exact"/>', '<w:sz w:val="8"/>', 'paragraph', '<w:basedOn w:val="Normal"/>')
        . $style('ListParagraph', 'List Paragraph', '<w:spacing w:after="60"/>', '', 'paragraph', '<w:basedOn w:val="Normal"/>')
        . $style('Footer', 'footer', '', "<w:color w:val=\"{$muted}\"/><w:sz w:val=\"16\"/>", 'paragraph', '<w:basedOn w:val="Normal"/>')
        . '<w:style w:type="character" w:styleId="Hyperlink"><w:name w:val="Hyperlink"/><w:rPr><w:color w:val="0284C7"/><w:u w:val="single"/></w:rPr></w:style>'
        . '</w:styles>';
}

/** Send the document as a Word download and stop. */
function mkt_doc_send_docx(array $doc): never
{
    $bytes = mkt_doc_docx($doc);
    $name = preg_replace('/[^a-z0-9\-]+/', '-', strtolower((string) ($doc['filename'] ?? 'document'))) ?: 'document';
    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment; filename="' . trim($name, '-') . '.docx"');
    header('Content-Length: ' . strlen($bytes));
    header('Cache-Control: private, no-store');
    echo $bytes;
    exit;
}
