<?php

declare(strict_types=1);

namespace App\Utilities;

/**
 * Reads the assay, the reagent lot and its expiry out of the analyzer message the
 * Interface Tool keeps with every result (orders.raw_text).
 *
 * Every Interface Tool version sends raw_text, over the results API and in the
 * orders table alike, while test_type is missing from the API and some analyzers put
 * the real assay name only in the result record. Reading the message here works for
 * every tool already installed, with no change on the tool's side.
 *
 * What is read, from the messages captured from production analyzers:
 *
 *   ASTM, every analyzer  assay from R.3, or from O.5 when there is no R record
 *                         TaqMan: R.3 "^^^HI2CAP96" (O.5 only says "ALL")
 *                         GeneXpert: R.3 "^^^HIV-1_VL 2 2^Xpert_HIV-1 Viral Load^2^^"
 *   Abbott m2000          lot from R.3 "^^^HIV0.6ml^HIV0.6ml^402072^10004668^^F"
 *   GeneXpert             lot and expiry from the cartridge in R.14
 *                         "Cepheid-1F21001^806911^630912^1113243530^72203^20260825"
 *   HL7, every analyzer   assay from OBR-4, or the first OBX-3: "1006^HIV-1^99ABT"
 *   Abbott Alinity m      lot from INV-16 of the INV for the assay code, expiry from
 *                         INV-12 of the reagents of that lot
 *
 * The cobas analyzers send no reagent lot. Anything not found is null, and the
 * caller falls back to test_type.
 *
 * Also read, for the run rather than the result:
 *
 *   model, serial  HL7 OBX-18 "Alinity m^Abbott~M01133^Abbott", "c5800^Roche~c5800.2709";
 *                  m2000 H.5 "m2000^8.1.9.0^275020144"; GeneXpert R.14 system
 *                  "Cepheid-1F21001"; TaqMan R.14 "Cobas TaqMan" and the AMPLILINK id
 *   run ID         cobas OBX-21 "5-2709-20260404-1526", m2000 O.4 "...^HIV120126A^F7",
 *                  GeneXpert R.14 cartridge serial
 *   message        what the analyzer said about the run: HL7 OBX-8 codes with text
 *                  ("U06T Pipetting anomaly ..."), ASTM instrument comments (C records:
 *                  "Sample clotted", "Error 2014 ...", "TM40 STEP_CORR-2")
 *   readings       the other values reported with the result: cycle numbers, Ct,
 *                  internal control, as name, value and unit
 */
final class AnalyzerRawText
{
    /** Most readings kept for one run, and the longest value of one. */
    private const MAX_READINGS = 30;
    private const MAX_READING_LENGTH = 100;

    /**
     * @param string ...$sampleIds the sample's identifiers as the analyzer sent them
     *                             (order ID, test ID); either one picks out its sample
     * @return array{assay: ?string, lot: ?string, lotExpiry: ?string, model: ?string, serial: ?string,
     *               runId: ?string, message: ?string,
     *               readings: list<array{name: string, value: string, unit: ?string}>}
     */
    public static function read(?string $rawText, string ...$sampleIds): array
    {
        $none = [
            'assay' => null, 'lot' => null, 'lotExpiry' => null, 'model' => null, 'serial' => null,
            'runId' => null, 'message' => null, 'readings' => [],
        ];
        $records = self::records((string) $rawText);
        if ($records === []) {
            return $none;
        }

        $isHl7 = array_any($records, static fn(string $record): bool => str_starts_with($record, 'MSH|'));
        $records = self::ownRecords($records, $sampleIds, $isHl7 ? 'SPM' : 'O');
        if ($records === null) {
            return $none;
        }

        return ($isHl7 ? self::readHl7($records) : self::readAstm($records)) + $none;
    }

    /**
     * The records or segments, one per entry, with the framing older tool versions
     * stored taken out: control characters and their <CR>-style markers, a frame's
     * end and checksum, and the frame number in front of a record. The clean-up
     * the Interface Tool applies to its own stored text, with continuation frames
     * joined first.
     *
     * @return list<string>
     */
    private static function records(string $text): array
    {
        // A record too long for one frame goes on in the next: the end of the
        // intermediate frame, its checksum and the next frame's start and number join
        // the two halves, which may break mid-field.
        $text = preg_replace(
            '/(?:<ETB>|\x17)[0-9A-Fa-f]{0,2}(?:<CR>|\r)?(?:<LF>|\n)?(?:(?:<STX>|\x02)[0-7])?/',
            '',
            $text
        ) ?? '';
        $text = preg_replace('/(?:<ETX>|\x03)[0-9A-Fa-f]{0,2}/', "\n", $text) ?? '';
        $text = preg_replace('/<(?:CR|LF)>|[\r\n]/', "\n", $text) ?? '';
        $text = preg_replace('/<(?:STX|EOT|ENQ|ACK|NAK)>/', '', $text) ?? '';
        $text = preg_replace('/[\x00-\x09\x0b-\x1f\x7f]/', '', $text) ?? '';

        $records = [];
        foreach (explode("\n", $text) as $record) {
            $record = preg_replace('/^\d(?=[A-Z][A-Z0-9]{0,2}\|)/', '', trim($record)) ?? '';
            if ($record !== '' && preg_match('/^[0-9A-Fa-f]{1,2}$/', $record) !== 1) {
                $records[] = $record;
            }
        }
        return $records;
    }

    /**
     * The header and the sample's own group when the text holds several samples, so
     * one sample never takes another's assay or lot. Null when none of them is the
     * sample asked for.
     *
     * @param list<string> $records
     * @param array<int, string> $sampleIds
     * @return list<string>|null
     */
    private static function ownRecords(array $records, array $sampleIds, string $groupType): ?array
    {
        $header = [];
        $groups = [];
        foreach ($records as $record) {
            if (self::type($record) === $groupType) {
                $groups[] = [];
            }
            if ($groups === []) {
                $header[] = $record;
            } else {
                $groups[array_key_last($groups)][] = $record;
            }
        }

        if (count($groups) <= 1) {
            return $records;
        }

        // Older tool versions could store an order twice; every group of the sample
        // is kept.
        $wanted = array_filter(array_map('trim', $sampleIds), static fn(string $id): bool => $id !== '');
        $own = array_filter(
            $groups,
            static fn(array $group): bool => array_intersect($wanted, self::sampleIds($group, $groupType)) !== []
        );
        return $own === [] ? null : array_merge($header, ...array_values($own));
    }

    /**
     * @param list<string> $group
     * @return list<string>
     */
    private static function sampleIds(array $group, string $groupType): array
    {
        $first = static fn(string $value): string => trim(preg_split('/[\^&]/', $value)[0] ?? '');
        $fields = explode('|', $group[0]);
        if ($groupType === 'O') {
            return [$first($fields[2] ?? ''), $first($fields[3] ?? '')];
        }

        $ids = [$first($fields[2] ?? '')];
        foreach ($group as $record) {
            if (self::type($record) === 'SAC') {
                $ids[] = $first(explode('|', $record)[3] ?? '');
            }
        }
        return $ids;
    }

    /**
     * @param list<string> $records
     * @return array<string, mixed>
     */
    private static function readAstm(array $records): array
    {
        $result = self::first($records, 'R');
        $order = self::first($records, 'O');
        $sender = explode('^', self::first($records, 'H')[4] ?? '');

        $testId = $result[2] ?? $order[4] ?? '';
        $assay = self::longestName(explode('^', $testId));
        if ($assay === null && $order !== null) {
            $assay = self::longestName(explode('^', $order[4] ?? ''));
        }

        $lot = $lotExpiry = $model = $serial = $runId = null;
        $components = explode('^', $testId);
        $cartridge = explode('^', $result[13] ?? '');
        if (str_starts_with($cartridge[0], 'Cepheid')) {
            // GeneXpert: system^module^...^cartridge serial^reagent lot^expiry
            $lot = self::lot($cartridge[4] ?? '');
            $lotExpiry = self::date($cartridge[5] ?? '');
            $model = 'GeneXpert';
            $serial = self::text($cartridge[0]);
            $runId = self::text($cartridge[3] ?? '');
        } elseif (count($components) >= 9 && $components[3] !== '') {
            // Abbott m2000: ^^^assay^protocol^assay lot^reagent serial^^result type
            $lot = self::lot($components[5]);
        }
        if (strcasecmp(trim($sender[0]), 'm2000') === 0) {
            // m2000^software^serial^record types; the run is O.4 sample^run^well.
            $model = 'm2000';
            $serial = self::text($sender[2] ?? '') ?? self::text($result[13] ?? '');
            $runId = self::text(explode('^', $order[3] ?? '')[1] ?? '');
        } elseif (in_array('AMPLILINK', array_map('trim', $sender), true)) {
            // AMPLILINK id^Roche^AMPLILINK^version^protocol^instrument^address; the
            // instrument names itself in R.14 ("Cobas TaqMan", "Taqman96").
            $model = self::text($result[13] ?? '');
            $serial = self::text($sender[5] ?? '') ?? self::text($sender[0]);
        }

        // Instrument comments: m2000 "Sample clotted", GeneXpert
        // "Error^2014^Operation terminated^description^time", TaqMan flags "TM40^ STEP_CORR-2".
        $messages = [];
        foreach ($records as $record) {
            $fields = explode('|', $record);
            if ($fields[0] === 'C' && trim($fields[2] ?? '') === 'I') {
                $parts = array_filter(
                    array_map('trim', explode('^', $fields[3] ?? '')),
                    static fn(string $part): bool => $part !== '' && preg_match('/^\d{12,14}$/', $part) !== 1
                );
                $messages[] = implode(' ', $parts);
            }
        }

        // The result's other records: GeneXpert "^^^HIV-1_VL 2 2^^^HIV-1^Ct" = 26.4,
        // m2000 "...^402072^10004668^^P" = -1.00 "cycle number". The name is what
        // follows the assay, lot and reagent numbers left out; m2000 names the
        // result type by a letter.
        $m2000Types = ['I' => 'Interpretation', 'P' => 'Cycle number'];
        $readings = [];
        $resultRecords = array_values(array_filter($records, static fn(string $r): bool => self::type($r) === 'R'));
        foreach (array_slice($resultRecords, 1) as $record) {
            $fields = explode('|', $record);
            $name = implode(' ', array_filter(
                array_map('trim', array_slice(explode('^', $fields[2] ?? ''), 6)),
                static fn(string $part): bool => $part !== '' && !ctype_digit($part)
            ));
            if ($model === 'm2000') {
                $name = $m2000Types[$name] ?? $name;
            }
            $readings[] = self::reading($name, $fields[3] ?? '', $fields[4] ?? '');
        }

        return [
            'assay' => $assay, 'lot' => $lot, 'lotExpiry' => $lotExpiry,
            'model' => $model, 'serial' => $serial, 'runId' => $runId,
            'message' => self::message($messages), 'readings' => self::readings($readings),
        ];
    }

    /**
     * @param list<string> $records
     * @return array<string, mixed>
     */
    private static function readHl7(array $records): array
    {
        $testId = '';
        foreach ($records as $record) {
            $field = trim(explode('|', $record)[4] ?? '');
            if (self::type($record) === 'OBR' && $field !== '' && $field !== '""') {
                $testId = $field;
                break;
            }
        }
        if ($testId === '') {
            $testId = self::first($records, 'OBX')[3] ?? '';
        }

        // A coded element: identifier^text^coding system^alternate identifier^
        // alternate text^alternate coding system. The coding systems are no assay.
        $components = explode('^', $testId);
        $assay = self::longestName(array_intersect_key($components, [0 => 1, 1 => 1, 3 => 1, 4 => 1]));

        // The reagent lot: the INV for the assay code carries the lot in INV-16, and
        // the reagents of that lot their expiry in INV-12.
        $code = trim($components[0]);
        $lot = null;
        $lotExpiry = null;
        $inventory = array_map(
            static fn(string $record): array => explode('|', $record),
            array_values(array_filter($records, static fn(string $record): bool => self::type($record) === 'INV'))
        );
        foreach ($inventory as $fields) {
            if ($code !== '' && trim(explode('^', $fields[1] ?? '')[0]) === $code) {
                $lot = self::lot(explode('^', $fields[16] ?? '')[0]);
                if ($lot !== null) {
                    break;
                }
            }
        }
        if ($lot !== null) {
            foreach ($inventory as $fields) {
                $expiry = self::date($fields[12] ?? '');
                if ($expiry !== null && self::lot(explode('^', $fields[16] ?? '')[0]) === $lot) {
                    $lotExpiry = $lotExpiry === null ? $expiry : min($lotExpiry, $expiry);
                }
            }
        }

        // The result is the OBX for the assay code (the cobas 4800 sends a run-time
        // OBX first); every other OBX is a reading reported with it.
        $observations = array_map(
            static fn(string $record): array => explode('|', $record),
            array_values(array_filter($records, static fn(string $record): bool => self::type($record) === 'OBX'))
        );
        $resultIndex = 0;
        foreach ($observations as $index => $fields) {
            if ($code !== '' && trim(explode('^', $fields[3] ?? '')[0]) === $code) {
                $resultIndex = $index;
                break;
            }
        }
        $result = $observations[$resultIndex] ?? [];

        // OBX-18, the equipment: model^maker~serial^maker, a serial of "Unknown" skipped.
        $equipment = array_map(
            static fn(string $repetition): string => trim(explode('^', $repetition)[0]),
            explode('~', $result[18] ?? '')
        );
        $serial = null;
        foreach (array_slice($equipment, 1) as $candidate) {
            if ($candidate !== '' && strcasecmp($candidate, 'Unknown') !== 0) {
                $serial = self::text($candidate);
                break;
            }
        }

        // OBX-8, codes with their text: "U06T^Pipetting anomaly ...^99ROC~...".
        $messages = [];
        foreach (explode('~', $result[8] ?? '') as $flag) {
            $parts = explode('^', $flag);
            if (trim($parts[1] ?? '') !== '') {
                $messages[] = trim($parts[0] . ' ' . $parts[1]);
            }
        }

        $readings = [];
        foreach ($observations as $index => $fields) {
            if ($index === $resultIndex || trim($fields[2] ?? '') === 'EI') {
                continue;
            }
            $name = trim(explode('^', $fields[3] ?? '')[0] . ' ' . trim($fields[4] ?? ''));
            $unit = explode('^', $fields[6] ?? '');
            $readings[] = self::reading($name, $fields[5] ?? '', trim($unit[1] ?? '') !== '' ? $unit[1] : $unit[0]);
        }

        return [
            'assay' => $assay, 'lot' => $lot, 'lotExpiry' => $lotExpiry,
            'model' => self::text($equipment[0] ?? ''), 'serial' => $serial,
            'runId' => self::text(explode('^', $result[21] ?? '')[0]),
            'message' => self::message($messages), 'readings' => self::readings($readings),
        ];
    }

    /** A value as text, or null when empty or the HL7 empty value. */
    private static function text(string $value, int $length = 100): ?string
    {
        $value = trim($value);
        return $value === '' || $value === '""' ? null : mb_substr($value, 0, $length);
    }

    /**
     * What the analyzer said about the run, each thing once, in the order said.
     *
     * @param list<string> $messages
     */
    private static function message(array $messages): ?string
    {
        $messages = array_values(array_unique(array_filter(
            array_map(static fn(string $m): string => (string) preg_replace('/\s+/', ' ', trim($m)), $messages),
            static fn(string $m): bool => $m !== ''
        )));
        return $messages === [] ? null : mb_substr(implode('; ', $messages), 0, 500);
    }

    /** @return ?array{name: string, value: string, unit: ?string} */
    private static function reading(string $name, string $value, string $unit): ?array
    {
        // Components of a value ("^26.4", "36.51^^37.15") read as one.
        $value = trim((string) preg_replace('/\s+/', ' ', str_replace('^', ' ', $value)));
        $name = trim($name);
        if ($value === '' || $value === '""' || $name === '') {
            return null;
        }
        return [
            'name' => mb_substr($name, 0, self::MAX_READING_LENGTH),
            'value' => mb_substr($value, 0, self::MAX_READING_LENGTH),
            'unit' => self::text($unit, self::MAX_READING_LENGTH),
        ];
    }

    /**
     * @param list<?array{name: string, value: string, unit: ?string}> $readings
     * @return list<array{name: string, value: string, unit: ?string}>
     */
    private static function readings(array $readings): array
    {
        return array_slice(array_values(array_filter($readings)), 0, self::MAX_READINGS);
    }

    private static function type(string $record): string
    {
        return explode('|', $record, 2)[0];
    }

    /**
     * @param list<string> $records
     * @return list<string>|null the first record of that type, split into fields
     */
    private static function first(array $records, string $type): ?array
    {
        foreach ($records as $record) {
            if (self::type($record) === $type) {
                return explode('|', $record);
            }
        }
        return null;
    }

    /**
     * The longest component with a letter in it, which is the assay's name: lot
     * numbers, counts and one-letter flags are shorter or have none.
     *
     * @param array<int, string> $components
     */
    public static function longestName(array $components): ?string
    {
        $name = '';
        foreach ($components as $component) {
            $component = trim($component);
            if (preg_match('/\p{L}/u', $component) === 1 && mb_strlen($component) > mb_strlen($name)) {
                $name = $component;
            }
        }
        return $name === '' ? null : mb_substr($name, 0, 255);
    }

    private static function lot(string $value): ?string
    {
        $value = trim($value);
        return preg_match('/^[A-Za-z0-9][A-Za-z0-9-]{1,49}$/', $value) === 1 ? $value : null;
    }

    /** YYYYMMDD, with or without a time after it, as Y-m-d. */
    private static function date(string $value): ?string
    {
        if (preg_match('/^(\d{4})(\d{2})(\d{2})/', trim($value), $m) !== 1) {
            return null;
        }
        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? "$m[1]-$m[2]-$m[3]" : null;
    }
}
