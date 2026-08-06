<?php

declare(strict_types=1);

namespace Modules\Catalog\Support;

use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

/**
 * Reads an uploaded CSV/TSV/XLSX file into a plain 2D grid of trimmed strings,
 * with no external dependencies (ZipArchive + SimpleXML for XLSX, native CSV
 * for the rest). Blank rows are preserved as empty arrays because they act as
 * meaningful section separators in some seller spreadsheets.
 *
 * @phpstan-type Grid list<list<string>>
 */
final class SpreadsheetReader
{
    public const MAX_ROWS = 2000;

    public const MAX_COLS = 40;

    /**
     * @return Grid
     */
    public function read(string $path, string $originalName): array
    {
        $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));

        return match ($extension) {
            'csv', 'txt' => $this->readDelimited($path),
            'tsv' => $this->readDelimited($path, "\t"),
            'xlsx' => $this->readXlsx($path),
            default => $this->sniff($path),
        };
    }

    /**
     * @return Grid
     */
    private function sniff(string $path): array
    {
        // XLSX is a ZIP; anything else we treat as delimited text.
        $handle = @fopen($path, 'rb');
        $magic = $handle ? (string) fread($handle, 2) : '';
        if ($handle) {
            fclose($handle);
        }

        return $magic === 'PK' ? $this->readXlsx($path) : $this->readDelimited($path);
    }

    /**
     * @return Grid
     */
    private function readDelimited(string $path, ?string $delimiter = null): array
    {
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('The uploaded file could not be opened.');
        }

        $delimiter ??= $this->detectDelimiter($path);

        $grid = [];
        $rowCount = 0;
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            if ($rowCount >= self::MAX_ROWS) {
                break;
            }
            $rowCount++;

            // fgetcsv returns [null] for a blank line — normalize to an empty row.
            if ($row === [null]) {
                $grid[] = [];

                continue;
            }

            $cells = array_map(static fn ($cell): string => trim((string) $cell), array_slice($row, 0, self::MAX_COLS));
            $grid[] = $this->trimTrailingEmpties($cells);
        }

        fclose($handle);

        return $grid;
    }

    private function detectDelimiter(string $path): string
    {
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return ',';
        }

        $sample = (string) fread($handle, 8192);
        fclose($handle);

        $firstLine = strtok($sample, "\r\n") ?: $sample;
        $counts = [
            ',' => substr_count($firstLine, ','),
            "\t" => substr_count($firstLine, "\t"),
            ';' => substr_count($firstLine, ';'),
            '|' => substr_count($firstLine, '|'),
        ];
        arsort($counts);
        $best = array_key_first($counts);

        return ($best !== null && $counts[$best] > 0) ? $best : ',';
    }

    /**
     * @return Grid
     */
    private function readXlsx(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException('The Excel file could not be opened. Please re-save it as .xlsx or .csv and try again.');
        }

        try {
            $sharedStrings = $this->readSharedStrings($zip);
            $sheetName = $this->firstWorksheetName($zip);
            $sheetXml = $sheetName !== null ? $zip->getFromName($sheetName) : false;

            if ($sheetXml === false) {
                throw new RuntimeException('No readable worksheet was found in the Excel file.');
            }

            return $this->parseSheet($sheetXml, $sharedStrings);
        } finally {
            $zip->close();
        }
    }

    /**
     * @return list<string>
     */
    private function readSharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false || trim($xml) === '') {
            return [];
        }

        $document = $this->loadXml($xml);
        if (! $document instanceof SimpleXMLElement) {
            return [];
        }

        $strings = [];
        foreach ($document->si as $item) {
            $strings[] = $this->siText($item);
        }

        return $strings;
    }

    private function siText(SimpleXMLElement $si): string
    {
        // A shared string is either a single <t> or a set of rich-text <r><t> runs.
        $text = (string) $si->t;
        if ($text !== '') {
            return $text;
        }

        $buffer = '';
        foreach ($si->r as $run) {
            $buffer .= (string) $run->t;
        }

        return $buffer;
    }

    /**
     * Parse the OOXML, stripping the default spreadsheet namespace so plain
     * SimpleXML access (`->row`, `$cell['r']`) works without namespace juggling.
     */
    private function loadXml(string $xml): ?SimpleXMLElement
    {
        $xml = preg_replace('/\sxmlns="[^"]*"/', '', $xml, 1) ?? $xml;
        $document = @simplexml_load_string($xml);

        return $document instanceof SimpleXMLElement ? $document : null;
    }

    private function firstWorksheetName(ZipArchive $zip): ?string
    {
        $sheets = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            if (preg_match('#^xl/worksheets/sheet(\d+)\.xml$#', $name, $matches) === 1) {
                $sheets[(int) $matches[1]] = $name;
            }
        }

        if ($sheets === []) {
            return null;
        }

        ksort($sheets);

        return (string) reset($sheets);
    }

    /**
     * @param  list<string>  $sharedStrings
     * @return Grid
     */
    private function parseSheet(string $sheetXml, array $sharedStrings): array
    {
        $document = $this->loadXml($sheetXml);
        if (! $document instanceof SimpleXMLElement) {
            throw new RuntimeException('The worksheet could not be read.');
        }

        $grid = [];
        $expectedRow = 1;

        foreach ($document->sheetData->row as $row) {
            $rowIndex = (int) $row['r'] ?: ($expectedRow);

            // Pad skipped (blank) rows so section separators survive.
            while ($expectedRow < $rowIndex && count($grid) < self::MAX_ROWS) {
                $grid[] = [];
                $expectedRow++;
            }

            if (count($grid) >= self::MAX_ROWS) {
                break;
            }

            $cells = [];
            foreach ($row->c as $cell) {
                $reference = (string) $cell['r'];
                $columnIndex = $this->columnIndex($reference);
                if ($columnIndex >= self::MAX_COLS) {
                    continue;
                }
                $cells[$columnIndex] = $this->cellValue($cell, $sharedStrings);
            }

            $grid[] = $this->flatten($cells);
            $expectedRow = $rowIndex + 1;
        }

        return $grid;
    }

    /**
     * @param  list<string>  $sharedStrings
     */
    private function cellValue(SimpleXMLElement $cell, array $sharedStrings): string
    {
        $type = (string) $cell['t'];

        if ($type === 's') {
            $index = (int) $cell->v;

            return trim($sharedStrings[$index] ?? '');
        }

        if ($type === 'inlineStr') {
            return trim($this->siText($cell->is));
        }

        // 'str' (formula result), numbers, booleans — take the raw value.
        return trim((string) $cell->v);
    }

    private function columnIndex(string $reference): int
    {
        if (preg_match('/^([A-Z]+)/', strtoupper($reference), $matches) !== 1) {
            return 0;
        }

        $letters = $matches[1];
        $index = 0;
        $length = strlen($letters);
        for ($i = 0; $i < $length; $i++) {
            $index = $index * 26 + (ord($letters[$i]) - 64);
        }

        return $index - 1;
    }

    /**
     * Turn a sparse [colIndex => value] map into a dense, trailing-trimmed row.
     *
     * @param  array<int, string>  $cells
     * @return list<string>
     */
    private function flatten(array $cells): array
    {
        if ($cells === []) {
            return [];
        }

        $max = max(array_keys($cells));
        $row = [];
        for ($i = 0; $i <= $max; $i++) {
            $row[] = $cells[$i] ?? '';
        }

        return $this->trimTrailingEmpties($row);
    }

    /**
     * @param  list<string>  $row
     * @return list<string>
     */
    private function trimTrailingEmpties(array $row): array
    {
        while ($row !== [] && trim((string) end($row)) === '') {
            array_pop($row);
        }

        return array_values($row);
    }
}
