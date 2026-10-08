<?php

namespace App\Utilities;

use App\Services\CommonService;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Writes a sample listing to an Excel file, one row per sample, from a column
 * list. Each test module describes its columns once (see VlExportService) and
 * every export of that module (requests, results, ...) goes through here, so
 * the sheets cannot drift apart.
 *
 * The listing query decides which samples and in what order; it only has to
 * select the id column. The module's own query then loads everything the
 * columns need, a chunk of ids at a time, so the listing pages keep a lean
 * SELECT and a column added to the export never has to be added to them.
 *
 * A column is ['heading' => ..., 'value' => fn(array $row, int $no)] plus
 * optional rules, applied by forForm():
 *   'forms'       => [..]  only these country forms show it
 *   'exceptForms' => [..]  these country forms do not
 *   'pii'         => true  only when patient details were asked for
 *   'sts'         => true  not on a standalone instance
 */
final class SampleExportUtility
{
    /** Ids loaded per details query: one primary-key lookup per chunk. */
    private const CHUNK_SIZE = 1000;

    /**
     * @param iterable<array<string, mixed>> $listRows rows of the listing query, in listing order
     * @param callable(list<int>): array<int, array<string, mixed>> $fetchDetails details keyed by id
     * @param list<array{heading: string, value: callable(array<string, mixed>, int): mixed}> $columns
     * @param list<list<string>> $preamble rows written above the headings
     */
    public static function writeXlsx(
        string $filename,
        iterable $listRows,
        string $idColumn,
        callable $fetchDetails,
        array $columns,
        array $preamble = [],
        bool $alphaNumHeadings = false
    ): void {
        $writer = new Writer();
        $writer->openToFile($filename);

        foreach ($preamble as $row) {
            $writer->addRow(Row::fromValues($row));
        }

        $headings = array_map(
            static fn(array $column): string => html_entity_decode($column['heading']),
            $columns
        );
        if ($alphaNumHeadings) {
            $headings = array_map(
                static fn(string $heading): string => (string) preg_replace('/[^A-Za-z0-9\-]/', '', $heading),
                $headings
            );
        }
        $writer->addRow(Row::fromValues($headings));

        $no = 0;
        $writeChunk = static function (array $ids) use (&$no, $writer, $fetchDetails, $columns): void {
            $details = $fetchDetails($ids);
            foreach ($ids as $id) {
                if (!isset($details[$id])) {
                    continue;
                }
                $no++;
                $row = [];
                foreach ($columns as $column) {
                    $row[] = $column['value']($details[$id], $no);
                }
                $writer->addRow(Row::fromValues($row));
                ExportJobUtility::tick();
            }
            gc_collect_cycles();
        };

        $ids = [];
        foreach ($listRows as $listRow) {
            $ids[] = (int) $listRow[$idColumn];
            if (count($ids) === self::CHUNK_SIZE) {
                $writeChunk($ids);
                $ids = [];
            }
        }
        if ($ids !== []) {
            $writeChunk($ids);
        }

        $writer->close();
    }

    /** The columns one instance shows, by the rules described on the class. */
    public static function forForm(array $columns, int $formId, bool $withPatientInfo, bool $standalone): array
    {
        return array_values(array_filter(
            $columns,
            static fn(array $column): bool =>
                (!isset($column['forms']) || in_array($formId, $column['forms'], true))
                && (!isset($column['exceptForms']) || !in_array($formId, $column['exceptForms'], true))
                && (empty($column['pii']) || $withPatientInfo)
                && (empty($column['sts']) || !$standalone)
        ));
    }

    public static function column(string $heading, callable $value, array $rules = []): array
    {
        return ['heading' => $heading, 'value' => $value] + $rules;
    }

    public static function text(string $heading, string $field, array $rules = []): array
    {
        // Form input is saved HTML-escaped ("&lt; 40"); the sheet shows it as typed.
        return self::column(
            $heading,
            static fn(array $r): mixed => is_string($r[$field] ?? null)
                ? html_entity_decode($r[$field], ENT_QUOTES)
                : ($r[$field] ?? null),
            $rules
        );
    }

    public static function date(string $heading, string $field, bool $withTime = false, array $rules = []): array
    {
        return self::column(
            $heading,
            static fn(array $r): mixed => DateUtility::humanReadableDateFormat($r[$field] ?? '', $withTime),
            $rules
        );
    }

    public static function yesNo(string $heading, string $field, array $rules = []): array
    {
        return self::column($heading, static fn(array $r): string => self::yesNoLabel($r[$field] ?? null), $rules);
    }

    private static function yesNoLabel(mixed $value): string
    {
        return match (strtolower(trim((string) $value))) {
            'yes' => _translate('Yes'),
            'no' => _translate('No'),
            'unknown' => _translate('Unknown'),
            default => (string) $value,
        };
    }

    /**
     * A field saved as one of a form's fixed choices (or a comma-separated
     * list of them), shown in the user's language: each choice goes through
     * $labels when it has an entry there, and then through translation.
     */
    public static function choice(string $heading, string $field, array $rules = [], array $labels = []): array
    {
        return self::column($heading, static function (array $r) use ($field, $labels): string {
            $choices = array_filter(
                array_map('trim', explode(',', html_entity_decode((string) ($r[$field] ?? ''), ENT_QUOTES))),
                'strlen'
            );
            return implode(', ', array_map(
                static fn(string $choice): string => _translate((string) ($labels[$choice] ?? $choice)),
                $choices
            ));
        }, $rules);
    }

    public static function sex(string $heading, string $field): array
    {
        return self::column(
            $heading,
            static fn(array $r): string => MiscUtility::getGenderFromString($r[$field] ?? null)
        );
    }

    /**
     * A list field (comma-separated choices) followed by its "Other" text,
     * e.g. "ARV Initiated prior to Pregnancy, Other - Option B+".
     */
    public static function withOther(
        string $heading,
        string $field,
        ?string $otherField = null,
        array $rules = []
    ): array {
        $choices = self::choice($heading, $field)['value'];
        return self::column($heading, static function (array $r) use ($choices, $otherField): string {
            $other = $otherField === null ? '' : trim((string) ($r[$otherField] ?? ''));
            return self::fullName(
                $choices($r, 0),
                $other === '' ? '' : '- ' . html_entity_decode($other, ENT_QUOTES)
            );
        }, $rules);
    }

    /** Whether the sample was rejected, and why. */
    /**
     * What the analyzer reported about the run (5.7.85), with the reagent lot: filled
     * for Interface Tool results; empty for results entered by hand.
     *
     * @return list<array<string, mixed>>
     */
    public static function analyzerColumns(): array
    {
        return [
            self::text(_translate('Instrument Model'), 'instrument_model'),
            self::text(_translate('Instrument Serial Number'), 'instrument_serial'),
            self::text(_translate('Analyzer Run ID'), 'analyzer_run_id'),
            self::text(_translate('Reagent Lot'), 'lot_number'),
            self::date(_translate('Reagent Lot Expiry'), 'lot_expiration_date'),
            self::text(_translate('Analyzer Message'), 'analyzer_message'),
            // "HIV-1 Ct: 26.4; IC: 15.70 CN", read from the stored JSON list.
            self::column(_translate('Analyzer Readings'), static function (array $r): ?string {
                $readings = json_decode((string) ($r['analyzer_readings'] ?? ''), true);
                if (!is_array($readings) || $readings === []) {
                    return null;
                }
                return implode('; ', array_map(
                    static fn(array $reading): string => trim(
                        ($reading['name'] ?? '') . ': ' . ($reading['value'] ?? '') . ' ' . ($reading['unit'] ?? '')
                    ),
                    array_filter($readings, 'is_array')
                ));
            }),
        ];
    }

    public static function rejectionColumns(): array
    {
        return [
            self::column(
                _translate('Is Sample Rejected?'),
                static fn(array $r): string => $r['is_rejected'] ? _translate('Yes') : _translate('No')
            ),
            self::column(
                _translate('Rejection Reason'),
                static fn(array $r): string => $r['is_rejected']
                    ? SampleRejectionUtility::reasonLabel($r['rejection_reason_name'] ?? null)
                    : ''
            ),
            self::date(_translate('Rejection Date'), 'rejection_on'),
        ];
    }

    /**
     * Columns every test table shares (lab, facility, status, batch, funding,
     * partner, instrument, and the users who created, changed, tested,
     * reviewed and approved the record), for a details query on $alias.
     *
     * @return array{select: string, joins: string}
     */
    public static function sharedDetailsSql(string $alias): array
    {
        $a = $alias;
        // In days, to one decimal: the old exports gave hours under a day and
        // days above it in the same column, so 23 and 2 could not be compared.
        $tat = static fn(string $from, string $to): string =>
            "ROUND(TIMESTAMPDIFF(MINUTE, $a.$from, $a.$to) / 1440, 1)";

        return [
            'select' => "{$tat('sample_collection_date', 'result_approved_datetime')} AS turnaround_collection,
                {$tat('sample_received_at_lab_datetime', 'result_approved_datetime')} AS turnaround_reception,
                {$tat('sample_collection_date', 'sample_received_at_lab_datetime')} AS collection_lab_reception_delay,
                f.facility_name, f.facility_code, f.facility_state, f.facility_district,
                lab.facility_name AS lab_name,
                ts.status_name,
                b.batch_code,
                fs.funding_source_name,
                ip.i_partner_name,
                ins.lower_limit, ins.higher_limit,
                u_created.user_name AS created_by_name,
                u_modified.user_name AS modified_by_name,
                u_tested.user_name AS tested_by_name,
                u_reviewed.user_name AS reviewed_by_name,
                u_approved.user_name AS approved_by_name",
            'joins' => "LEFT JOIN facility_details AS f ON f.facility_id = $a.facility_id
                LEFT JOIN facility_details AS lab ON lab.facility_id = $a.lab_id
                LEFT JOIN r_sample_status AS ts ON ts.status_id = $a.result_status
                LEFT JOIN batch_details AS b ON b.batch_id = $a.sample_batch_id
                LEFT JOIN r_funding_sources AS fs ON fs.funding_source_id = $a.funding_source
                LEFT JOIN r_implementation_partners AS ip ON ip.i_partner_id = $a.implementing_partner
                LEFT JOIN instruments AS ins ON ins.instrument_id = $a.instrument_id
                LEFT JOIN user_details AS u_created ON u_created.user_id = $a.request_created_by
                LEFT JOIN user_details AS u_modified ON u_modified.user_id = $a.last_modified_by
                LEFT JOIN user_details AS u_tested ON u_tested.user_id = $a.tested_by
                LEFT JOIN user_details AS u_reviewed ON u_reviewed.user_id = $a.result_reviewed_by
                LEFT JOIN user_details AS u_approved ON u_approved.user_id = $a.result_approved_by",
        ];
    }

    /**
     * Values every test export derives the same way: rejection, the latest
     * reason given for a result change, and the storage slot.
     */
    public static function prepareShared(array $row, string $resultChangeColumn): array
    {
        $row['is_rejected'] = SampleRejectionUtility::isRejected($row);

        $changes = MiscUtility::genuineResultChangeHistory($row[$resultChangeColumn] ?? null);
        $row['result_change_reason'] = $changes === [] ? '' : (string) (end($changes)['msg'] ?? '');

        // Storage was saved as a JSON string with freezerCode by older
        // versions, and as an object with storageCode since.
        $attributes = empty($row['form_attributes']) ? [] : JsonUtility::decodeJson($row['form_attributes']);
        $storage = is_array($attributes) ? ($attributes['storage'] ?? []) : [];
        if (is_string($storage)) {
            $storage = JsonUtility::decodeJson($storage);
        }
        $storage = is_array($storage) ? $storage : [];
        $row['storage_freezer'] = $storage['storageCode'] ?? $storage['freezerCode'] ?? '';
        $row['storage_rack'] = $storage['rack'] ?? '';
        $row['storage_box'] = $storage['box'] ?? '';
        $row['storage_position'] = $storage['position'] ?? '';
        $row['storage_volume'] = $storage['volume'] ?? '';

        return $row;
    }

    /** Decrypts the named fields of a row saved with patient details encrypted. */
    public static function decryptFields(array $row, array $fields, string $key): array
    {
        if ($key === '' || ($row['is_encrypted'] ?? '') !== 'yes') {
            return $row;
        }
        foreach ($fields as $field) {
            $row[$field] = CommonService::crypto('decrypt', $row[$field] ?? null, $key);
        }
        return $row;
    }

    /** Joins non-empty name parts with single spaces. */
    public static function fullName(mixed ...$parts): string
    {
        return implode(' ', array_filter(array_map(static fn($p): string => trim((string) $p), $parts), 'strlen'));
    }

    /**
     * Columns every test export ends with, in this order: lab sign-off,
     * status, and who made and last changed the record.
     */
    public static function recordColumns(): array
    {
        return [
            self::text(_translate('Tested By'), 'tested_by_name'),
            self::text(_translate('Reviewed By'), 'reviewed_by_name'),
            self::date(_translate('Reviewed On'), 'result_reviewed_datetime', true),
            self::text(_translate('Approved By'), 'approved_by_name'),
            self::date(_translate('Approved On'), 'result_approved_datetime', true),
            self::text(_translate('Reason for Result Change'), 'result_change_reason'),
            self::choice(_translate('Sample Status'), 'status_name'),
            self::date(_translate('Result Printed Date'), 'result_printed_datetime', true),
            self::date(_translate('Result Sent On'), 'result_dispatched_datetime', true),
            self::text(_translate('Collection to Lab Reception (days)'), 'collection_lab_reception_delay'),
            self::text(_translate('Reception to Result Approval (days)'), 'turnaround_reception'),
            self::text(_translate('Collection to Result Approval (days)'), 'turnaround_collection'),
            self::date(_translate('Request Created On'), 'request_created_datetime', true),
            self::text(_translate('Request Created By'), 'created_by_name'),
            self::date(_translate('Last Modified On'), 'last_modified_datetime', true),
            self::text(_translate('Last Modified By'), 'modified_by_name'),
        ];
    }

    /** The storage slot columns (forms that record where a sample is kept). */
    public static function storageColumns(array $rules): array
    {
        return [
            self::text(_translate('Freezer'), 'storage_freezer', $rules),
            self::text(_translate('Rack'), 'storage_rack', $rules),
            self::text(_translate('Box'), 'storage_box', $rules),
            self::text(_translate('Position'), 'storage_position', $rules),
            self::text(_translate('Volume (ml)'), 'storage_volume', $rules),
        ];
    }
}
