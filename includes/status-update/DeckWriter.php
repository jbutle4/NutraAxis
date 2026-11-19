<?php

declare(strict_types=1);

require_once __DIR__ . '/DeckReader.php';
require_once __DIR__ . '/StatusPalette.php';
require_once dirname(__DIR__) . '/status-update-portal.php';

final class DeckWriter
{
    /**
     * @param array<string, list<string>> $valuesByField
     */
    public function render(string $basePptxBytes, array $valuesByField, string $statusDate): array
    {
        $reader = new DeckReader();
        $parsed = $reader->readFromBytes($basePptxBytes);
        if (!$parsed['ok']) {
            return ['ok' => false, 'error' => $parsed['error'] ?? 'Unable to read base deck.', 'bytes' => null];
        }

        $tempIn = tempnam(sys_get_temp_dir(), 'na-deck-in-');
        $tempOut = tempnam(sys_get_temp_dir(), 'na-deck-out-');
        if ($tempIn === false || $tempOut === false) {
            return ['ok' => false, 'error' => 'Unable to create temp files.', 'bytes' => null];
        }

        file_put_contents($tempIn, $basePptxBytes);
        if (!copy($tempIn, $tempOut)) {
            @unlink($tempIn);
            @unlink($tempOut);

            return ['ok' => false, 'error' => 'Unable to copy deck for rendering.', 'bytes' => null];
        }

        $zip = new ZipArchive();
        if ($zip->open($tempOut) !== true) {
            @unlink($tempIn);
            @unlink($tempOut);

            return ['ok' => false, 'error' => 'Unable to open deck copy.', 'bytes' => null];
        }

        try {
            $slidePaths = $parsed['slide_paths'];
            $slidesToTouch = [];
            foreach ($valuesByField as $key => $_value) {
                $slide = (int) explode(':', $key, 2)[0];
                $slidesToTouch[$slide] = true;
            }
            $slidesToTouch[2] = true;
            foreach ([3, 4, 5, 6, 7, 8] as $slide) {
                $slidesToTouch[$slide] = true;
            }

            foreach ($slidesToTouch as $slideNum => $_) {
                $path = $slidePaths[$slideNum] ?? null;
                if ($path === null) {
                    continue;
                }
                $xml = $zip->getFromName($path);
                if ($xml === false) {
                    continue;
                }
                $updated = $this->updateSlideXml(
                    (string) $xml,
                    $slideNum,
                    $valuesByField,
                    $statusDate,
                    $parsed['map']
                );
                if ($updated !== null) {
                    $zip->addFromString($path, $updated);
                }
            }
        } finally {
            $zip->close();
        }

        $bytes = file_get_contents($tempOut);
        @unlink($tempIn);
        @unlink($tempOut);
        if ($bytes === false) {
            return ['ok' => false, 'error' => 'Unable to read rendered deck.', 'bytes' => null];
        }

        return ['ok' => true, 'error' => null, 'bytes' => $bytes];
    }

    /**
     * @param array<string, list<string>> $valuesByField
     * @param array<string, array<string, mixed>> $deckMap
     */
    private function updateSlideXml(
        string $xml,
        int $slideNum,
        array $valuesByField,
        string $statusDate,
        array $deckMap
    ): ?string {
        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = true;
        $dom->formatOutput = false;
        if (!$dom->loadXML($xml, LIBXML_NONET)) {
            return null;
        }

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('p', 'http://schemas.openxmlformats.org/presentationml/2006/main');
        $xpath->registerNamespace('a', 'http://schemas.openxmlformats.org/drawingml/2006/main');

        $eyebrow = status_update_eyebrow_label($statusDate);
        $dateLine = status_update_format_status_date($statusDate);
        foreach ($xpath->query('//p:sp') ?: [] as $sp) {
            if (!$sp instanceof DOMElement) {
                continue;
            }
            $nameNode = $xpath->query('.//p:cNvPr', $sp)?->item(0);
            if (!$nameNode instanceof DOMElement) {
                continue;
            }
            $shapeName = trim($nameNode->getAttribute('name'));
            $key = $slideNum . ':' . $shapeName;

            if ($shapeName === 'Eyebrow') {
                $this->setShapeParagraphs($xpath, $sp, [$eyebrow]);
                continue;
            }
            if ($slideNum === 2 && $shapeName === 'TextBox 2') {
                $this->setShapeParagraphs($xpath, $sp, [$dateLine . ' STATUS UPDATES']);
                continue;
            }

            if (!isset($valuesByField[$key])) {
                continue;
            }

            $lines = $valuesByField[$key];
            $this->setShapeParagraphs($xpath, $sp, $lines);

            $meta = $deckMap[$key] ?? [];
            $kind = (string) ($meta['kind'] ?? 'text');
            if ($kind === 'chip') {
                $colors = StatusPalette::chipColors($lines[0] ?? '');
                if ($colors !== null) {
                    $this->setShapeFillAndFont($xpath, $sp, $colors['fill'], $colors['font']);
                }
            }
        }

        foreach ($xpath->query("//p:graphicFrame[.//p:cNvPr[@name='RAG Matrix']]") ?: [] as $frame) {
            $this->updateRagTable($xpath, $frame, $slideNum, $valuesByField, $deckMap);
        }

        return $dom->saveXML($dom->documentElement, LIBXML_NOEMPTYTAG);
    }

    /**
     * @param list<string> $lines
     */
    private function setShapeParagraphs(DOMXPath $xpath, DOMElement $shape, array $lines): void
    {
        $paragraphs = [];
        foreach ($xpath->query('.//a:p', $shape) ?: [] as $p) {
            if ($p instanceof DOMElement) {
                $paragraphs[] = $p;
            }
        }
        if ($paragraphs === []) {
            return;
        }

        while (count($paragraphs) < count($lines)) {
            $clone = $paragraphs[count($paragraphs) - 1]->cloneNode(true);
            $paragraphs[count($paragraphs) - 1]->parentNode?->appendChild($clone);
            $paragraphs[] = $clone;
        }

        while (count($paragraphs) > count($lines) && count($paragraphs) > 1) {
            $remove = array_pop($paragraphs);
            $remove?->parentNode?->removeChild($remove);
        }

        foreach ($paragraphs as $idx => $p) {
            $text = $lines[$idx] ?? '';
            $runs = $xpath->query('.//a:r', $p);
            if ($runs === false || $runs->length === 0) {
                continue;
            }
            $first = true;
            foreach ($runs as $run) {
                if (!$run instanceof DOMElement) {
                    continue;
                }
                $t = $xpath->query('./a:t', $run)?->item(0);
                if (!$t instanceof DOMElement) {
                    continue;
                }
                $t->nodeValue = $first ? $text : '';
                $first = false;
            }
        }
    }

  private function setShapeFillAndFont(DOMXPath $xpath, DOMElement $shape, string $fill, string $font): void
    {
        $spPr = $xpath->query('./p:spPr', $shape)?->item(0);
        if ($spPr instanceof DOMElement) {
            $solid = $xpath->query('./a:solidFill/a:srgbClr', $spPr)?->item(0);
            if ($solid instanceof DOMElement) {
                $solid->setAttribute('val', $fill);
            }
        }

        foreach ($xpath->query('.//a:r', $shape) ?: [] as $run) {
            if (!$run instanceof DOMElement) {
                continue;
            }
            $rPr = $xpath->query('./a:rPr', $run)?->item(0);
            if ($rPr instanceof DOMElement) {
                $rPr->setAttribute('val', $font);
            }
            $color = $xpath->query('./a:solidFill/a:srgbClr', $rPr)?->item(0);
            if ($color instanceof DOMElement) {
                $color->setAttribute('val', $font);
            }
        }
    }

    /**
     * @param array<string, list<string>> $valuesByField
     * @param array<string, array<string, mixed>> $deckMap
     */
    private function updateRagTable(DOMXPath $xpath, DOMNode $frame, int $slideNum, array $valuesByField, array $deckMap): void
    {
        $legendRed = $valuesByField[$slideNum . ':Legend Red'][0] ?? null;
        $legendHold = $valuesByField[$slideNum . ':Legend On Hold'][0] ?? null;

        $rows = $xpath->query('.//a:tbl/a:tr', $frame);
        if ($rows === false) {
            return;
        }

        for ($row = 0; $row < $rows->length; $row++) {
            $tr = $rows->item($row);
            if (!$tr instanceof DOMElement) {
                continue;
            }
            $cells = $xpath->query('./a:tc', $tr);
            if ($cells === false) {
                continue;
            }
            for ($col = 0; $col < $cells->length; $col++) {
                $key = $slideNum . ':RAG Matrix:r' . $row . 'c' . $col;
                if (!isset($valuesByField[$key])) {
                    continue;
                }
                $tc = $cells->item($col);
                if (!$tc instanceof DOMElement) {
                    continue;
                }
                $lines = $valuesByField[$key];
                $this->setShapeParagraphs($xpath, $tc, $lines);

                $meta = $deckMap[$key] ?? [];
                if (($meta['kind'] ?? '') === 'rag') {
                    $fill = StatusPalette::ragFill($lines[0] ?? '', is_string($legendRed) ? $legendRed : null, is_string($legendHold) ? $legendHold : null);
                    if ($fill !== null) {
                        $tcPr = $xpath->query('./a:tcPr', $tc)?->item(0);
                        if ($tcPr instanceof DOMElement) {
                            $solid = $xpath->query('./a:solidFill/a:srgbClr', $tcPr)?->item(0);
                            if ($solid instanceof DOMElement) {
                                $solid->setAttribute('val', $fill);
                            }
                        }
                    }
                }
            }
        }
    }
}