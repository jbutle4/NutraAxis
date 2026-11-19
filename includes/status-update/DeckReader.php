<?php

declare(strict_types=1);

require_once __DIR__ . '/StatusUpdateDeckSpec.php';

final class DeckReader
{
    /**
     * @return array{ok: bool, error: ?string, map: array<string, array<string, mixed>>, slide_paths: array<int, string>, problems: list<string>}
     */
    public function readFromBytes(string $pptxBytes): array
    {
        $temp = tempnam(sys_get_temp_dir(), 'na-deck-');
        if ($temp === false) {
            return ['ok' => false, 'error' => 'Unable to create a temp file.', 'map' => [], 'slide_paths' => [], 'problems' => []];
        }

        file_put_contents($temp, $pptxBytes);
        try {
            return $this->readFromPath($temp);
        } finally {
            @unlink($temp);
        }
    }

    /**
     * @return array{ok: bool, error: ?string, map: array<string, array<string, mixed>>, slide_paths: array<int, string>, problems: list<string>}
     */
    public function readFromPath(string $path): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            return ['ok' => false, 'error' => 'The file is not a valid .pptx archive.', 'map' => [], 'slide_paths' => [], 'problems' => []];
        }

        try {
            $slidePaths = $this->resolveSlidePaths($zip);
            if ($slidePaths === []) {
                return ['ok' => false, 'error' => 'Unable to resolve slide order from presentation.xml.', 'map' => [], 'slide_paths' => [], 'problems' => []];
            }

            $map = [];
            $problems = [];

            foreach (StatusUpdateDeckSpec::EDITABLE_SLIDES as $slideNum) {
                $slidePath = $slidePaths[$slideNum] ?? null;
                if ($slidePath === null || $zip->getFromName($slidePath) === false) {
                    $problems[] = 'Slide ' . $slideNum . ' is missing from the deck.';
                    continue;
                }

                $xml = (string) $zip->getFromName($slidePath);
                $slideFields = $this->readSlide($slideNum, $xml, $problems);
                foreach ($slideFields as $key => $field) {
                    $map[$key] = $field;
                }
            }

            if ($problems !== []) {
                return ['ok' => false, 'error' => 'Template validation failed.', 'map' => $map, 'slide_paths' => $slidePaths, 'problems' => $problems];
            }

            return ['ok' => true, 'error' => null, 'map' => $map, 'slide_paths' => $slidePaths, 'problems' => []];
        } finally {
            $zip->close();
        }
    }

    /**
     * @return array<int, string> 1-based slide number => zip path
     */
    private function resolveSlidePaths(ZipArchive $zip): array
    {
        $presentation = $zip->getFromName('ppt/presentation.xml');
        if ($presentation === false) {
            return [];
        }

        $presDom = $this->loadXml($presentation);
        if ($presDom === null) {
            return [];
        }

        $presXpath = new DOMXPath($presDom);
        $presXpath->registerNamespace('p', 'http://schemas.openxmlformats.org/presentationml/2006/main');
        $presXpath->registerNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');

        $relsXml = $zip->getFromName('ppt/_rels/presentation.xml.rels');
        if ($relsXml === false) {
            return [];
        }

        $relsDom = $this->loadXml($relsXml);
        if ($relsDom === null) {
            return [];
        }

        $relsXpath = new DOMXPath($relsDom);
        $relsXpath->registerNamespace('rel', 'http://schemas.openxmlformats.org/package/2006/relationships');
        $targets = [];
        foreach ($relsXpath->query('//rel:Relationship') ?: [] as $rel) {
            if (!$rel instanceof DOMElement) {
                continue;
            }
            $id = $rel->getAttribute('Id');
            $target = $rel->getAttribute('Target');
            if ($id !== '' && $target !== '') {
                $targets[$id] = 'ppt/' . ltrim(str_replace('../', '', $target), '/');
            }
        }

        $result = [];
        $slideIndex = 0;
        foreach ($presXpath->query('//p:sldId') ?: [] as $sldId) {
            if (!$sldId instanceof DOMElement) {
                continue;
            }
            $slideIndex++;
            $rid = $sldId->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'id');
            if ($rid === '' && $sldId->hasAttribute('r:id')) {
                $rid = $sldId->getAttribute('r:id');
            }
            if ($rid !== '' && isset($targets[$rid])) {
                $result[$slideIndex] = $targets[$rid];
            }
        }

        return $result;
    }

    /**
     * @param list<string> $problems
     * @return array<string, array<string, mixed>>
     */
    private function readSlide(int $slideNum, string $xml, array &$problems): array
    {
        $dom = $this->loadXml($xml);
        if ($dom === null) {
            $problems[] = 'Slide ' . $slideNum . ' XML is invalid.';

            return [];
        }

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('p', 'http://schemas.openxmlformats.org/presentationml/2006/main');
        $xpath->registerNamespace('a', 'http://schemas.openxmlformats.org/drawingml/2006/main');

        $expected = StatusUpdateDeckSpec::shapeNamesForSlide($slideNum);
        $foundNames = [];
        $fields = [];
        $allNames = [];

        foreach ($xpath->query('//p:cNvPr[@name]') ?: [] as $nameNode) {
            if (!$nameNode instanceof DOMElement) {
                continue;
            }
            $shapeName = trim($nameNode->getAttribute('name'));
            if ($shapeName === '') {
                continue;
            }
            $allNames[$shapeName] = true;
            if (!in_array($shapeName, $expected, true)) {
                continue;
            }
            if (isset($foundNames[$shapeName])) {
                $problems[] = 'Slide ' . $slideNum . ': duplicate editable shape name "' . $shapeName . '".';
                continue;
            }
            $foundNames[$shapeName] = true;

            $context = $this->shapeTextContext($nameNode);
            if ($context === null) {
                $problems[] = 'Slide ' . $slideNum . ': shape "' . $shapeName . '" has no readable text container.';
                continue;
            }

            $paragraphs = $this->readParagraphs($xpath, $context);
            $key = $slideNum . ':' . $shapeName;
            $kind = StatusUpdateDeckSpec::inferFieldKind($key, $shapeName);
            $fields[$key] = [
                'field'      => $key,
                'slide'      => $slideNum,
                'shape'      => $shapeName,
                'kind'       => $kind,
                'value'      => $paragraphs,
                'editable'   => $shapeName !== 'Legend Red' && $shapeName !== 'Legend On Hold',
            ];
        }

        if (in_array('RAG Matrix', $expected, true)) {
            foreach ($xpath->query("//p:graphicFrame[.//p:cNvPr[@name=\"RAG Matrix\"]]") ?: [] as $frame) {
                $tableFields = $this->readRagTable($slideNum, $xpath, $frame, $problems);
                $fields = array_merge($fields, $tableFields);
            }
        }

        foreach ($expected as $shapeName) {
            if ($shapeName === 'RAG Matrix') {
                if ($slideNum === 7 || $slideNum === 8) {
                    $hasTable = false;
                    foreach (array_keys($fields) as $key) {
                        if (str_starts_with($key, $slideNum . ':RAG Matrix:')) {
                            $hasTable = true;
                            break;
                        }
                    }
                    if (!$hasTable) {
                        $problems[] = 'Slide ' . $slideNum . ': missing table "RAG Matrix".';
                    }
                }
                continue;
            }
            if (!isset($foundNames[$shapeName]) && !in_array($shapeName, ['Legend Red', 'Legend On Hold'], true)) {
                $problems[] = 'Slide ' . $slideNum . ': missing shape "' . $shapeName . '".';
            }
        }

        if ($problems !== [] && $allNames !== []) {
            $names = array_keys($allNames);
            sort($names);
            $problems[] = 'Slide ' . $slideNum . ' shape names found in file: ' . implode(', ', array_slice($names, 0, 40))
                . (count($names) > 40 ? '…' : '');
        }

        return $fields;
    }

    private function shapeTextContext(DOMElement $cNvPr): ?DOMElement
    {
        $node = $cNvPr->parentNode;
        while ($node instanceof DOMElement) {
            $local = strtolower($node->localName ?? $node->tagName);
            if ($local === 'sp' || $local === 'tc') {
                return $node;
            }
            $node = $node->parentNode;
        }

        return null;
    }

    /**
     * @param list<string> $problems
     * @return array<string, array<string, mixed>>
     */
    private function readRagTable(int $slideNum, DOMXPath $xpath, DOMNode $frame, array &$problems): array
    {
        $fields = [];
        $rows = $xpath->query('.//a:tbl/a:tr', $frame);
        if ($rows === false || $rows->length === 0) {
            $problems[] = 'Slide ' . $slideNum . ': RAG Matrix has no rows.';

            return [];
        }

        for ($row = 0; $row < $rows->length && $row <= StatusUpdateDeckSpec::RAG_TABLE_ROWS; $row++) {
            $tr = $rows->item($row);
            if (!$tr instanceof DOMElement) {
                continue;
            }
            $cells = $xpath->query('./a:tc', $tr);
            if ($cells === false) {
                continue;
            }
            for ($col = 0; $col < $cells->length && $col < StatusUpdateDeckSpec::RAG_TABLE_COLS; $col++) {
                $tc = $cells->item($col);
                if (!$tc instanceof DOMElement) {
                    continue;
                }
                $key = $slideNum . ':RAG Matrix:r' . $row . 'c' . $col;
                $paragraphs = $this->readParagraphs($xpath, $tc);
                $kind = $row === 0 ? 'readonly' : ($col === 4 ? 'rag' : 'text');
                $fields[$key] = [
                    'field'    => $key,
                    'slide'    => $slideNum,
                    'shape'    => 'RAG Matrix',
                    'kind'     => $kind,
                    'value'    => $paragraphs,
                    'editable' => $row > 0,
                ];
            }
        }

        return $fields;
    }

    /**
     * @return list<string>
     */
    private function readParagraphs(DOMXPath $xpath, DOMNode $context): array
    {
        $paragraphs = [];
        foreach ($xpath->query('.//a:p', $context) ?: [] as $p) {
            $text = '';
            foreach ($xpath->query('.//a:t', $p) ?: [] as $t) {
                $text .= $t->textContent ?? '';
            }
            $paragraphs[] = $text;
        }

        if ($paragraphs === []) {
            $paragraphs[] = '';
        }

        return $paragraphs;
    }

    private function loadXml(string $xml): ?DOMDocument
    {
        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = true;
        $dom->formatOutput = false;
        $prev = libxml_use_internal_errors(true);
        $loaded = $dom->loadXML($xml, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        return $loaded ? $dom : null;
    }
}