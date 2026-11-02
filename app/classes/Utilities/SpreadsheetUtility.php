<?php

declare(strict_types=1);

namespace App\Utilities;

use InvalidArgumentException;
use OpenSpout\Reader\XLSX\Reader;

/**
 * Reads uploaded spreadsheets with OpenSpout.
 */
final class SpreadsheetUtility
{
    /**
     * Rows of the first sheet, heading row and blank rows left out, each keyed by
     * column letter ('A', 'B', ... 'AA') with cells as text, or null when empty.
     * Every row carries the same columns, as wide as the widest row.
     *
     * @return list<array<string, ?string>>
     */
    public static function readRowsByColumnLetter(string $path): array
    {
        if (MiscUtility::getFileExtension($path) !== 'xlsx') {
            throw new InvalidArgumentException(_translate('Save the file as .xlsx and upload it again.'));
        }

        $reader = new Reader();
        $reader->open($path);
        $rows = [];
        $width = 0;
        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                $isHeading = true;
                foreach ($sheet->getRowIterator() as $sheetRow) {
                    if ($isHeading) {
                        $isHeading = false;
                        continue;
                    }
                    $values = array_map(self::cellToString(...), $sheetRow->toArray());
                    if (array_filter($values, static fn(?string $value): bool => $value !== null) === []) {
                        continue;
                    }
                    $width = max($width, count($values));
                    $rows[] = $values;
                }
                break; // first sheet only
            }
        } finally {
            $reader->close();
        }

        $letters = array_map(self::columnLetter(...), range(1, max($width, 1)));
        return array_map(
            static fn(array $values): array => array_combine($letters, array_pad($values, count($letters), null)),
            $rows
        );
    }

    /** 1 => 'A', 26 => 'Z', 27 => 'AA', 53 => 'BA' */
    public static function columnLetter(int $column): string
    {
        $letter = '';
        while ($column > 0) {
            $remainder = ($column - 1) % 26;
            $letter = chr(65 + $remainder) . $letter;
            $column = intdiv($column - 1, 26);
        }
        return $letter;
    }

    private static function cellToString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }
        if ($value instanceof \DateInterval) {
            return $value->format('%H:%I:%S');
        }
        if (is_bool($value)) {
            return $value ? 'TRUE' : 'FALSE';
        }
        if (is_float($value) && floor($value) === $value && abs($value) < PHP_INT_MAX) {
            return (string) (int) $value;
        }
        return (string) $value;
    }
}
