<?php

declare(strict_types=1);

namespace App\Utilities;

use App\Services\TestsService;
use App\Services\CommonService;
use App\Services\DatabaseService;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

/**
 * The Sample Testing tab of the seven clinic report pages.
 *
 * Each module used to carry its own copy of this report, and every copy drew
 * four statuses in a stack beside a total that counted all of them. A sample
 * awaiting approval, on hold, failed or referred was in the total and in no
 * bar, so the bars never reached the number printed over them. The buckets
 * below cover every status that counts, once each, so they always sum to the
 * total.
 */
final class SampleTestingReportUtility
{
    /**
     * Report bucket => the statuses it holds. Order is the stacking order.
     * Anything not listed (lost, expired, referred, or a status added later)
     * lands in "other", so a new status can never fall out of the sum.
     */
    public const BUCKETS = [
        'tested' => [\SAMPLE_STATUS\ACCEPTED],
        'awaitingApproval' => [\SAMPLE_STATUS\PENDING_APPROVAL],
        'awaitingTesting' => [
            \SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB,
            \SAMPLE_STATUS\ON_HOLD,
            \SAMPLE_STATUS\REORDERED_FOR_TESTING,
        ],
        'notAtLab' => [\SAMPLE_STATUS\RECEIVED_AT_CLINIC],
        'failed' => [\SAMPLE_STATUS\TEST_FAILED, \SAMPLE_STATUS\NO_RESULT],
        'rejected' => [\SAMPLE_STATUS\REJECTED],
    ];

    /**
     * Per-facility counts for the filters posted by the tab.
     *
     * @param array<string, mixed> $filters sanitized $_POST
     * @return array{rows: list<array<string, mixed>>, startDate: string, endDate: string}
     */
    public static function fetch(string $testType, array $filters, DatabaseService $db, CommonService $general): array
    {
        $a = ClinicReportUtility::type($testType)['alias'];
        $table = TestsService::getTestTableName($testType);

        [$startDate, $endDate] = ClinicReportUtility::dateRange((string) ($filters['sampleCollectionDate'] ?? ''));
        [$from, $to] = ClinicReportUtility::datetimeBounds($startDate, $endDate);

        // A bare range rather than DATE(col) BETWEEN, which hides the index.
        $where = array_merge(
            [
                "$a.sample_collection_date >= '$from'",
                "$a.sample_collection_date < '$to'",
            ],
            ClinicReportUtility::scopeClauses($testType, $db, $general),
            ClinicReportUtility::filterClauses($testType, $filters, $db)
        );

        $sums = [];
        foreach (self::BUCKETS as $bucket => $statuses) {
            $sums[] = "SUM($a.result_status IN (" . implode(',', $statuses) . ")) AS `$bucket`";
        }

        $sql = "SELECT f.facility_id, f.facility_name, f.facility_state, f.facility_district,
                    COUNT(*) AS total, " . implode(', ', $sums) . "
                FROM $table AS $a
                JOIN facility_details AS f ON f.facility_id = $a.facility_id
                WHERE " . implode(' AND ', $where) . "
                GROUP BY f.facility_id
                ORDER BY total DESC, f.facility_name";
        $rows = array_map(self::shapeRow(...), $db->rawQuery($sql) ?: []);

        // The export replays this query. One entry per test type, so a search
        // in another module's clinic report (in another browser tab, say)
        // never changes what this one exports. A row per facility, so the
        // rows already in hand are the export's row count.
        $key = self::sessionKey($testType);
        $_SESSION[$key] = $sql;
        $_SESSION[$key . 'Count'] = count($rows);

        return ['rows' => $rows, 'startDate' => $startDate, 'endDate' => $endDate];
    }

    /**
     * Session entry holding the tab's last query for $testType. Its row count
     * is stored under the same name plus "Count", as ExportJobUtility expects.
     */
    public static function sessionKey(string $testType): string
    {
        return 'sampleTestingReport_' . $testType;
    }

    /**
     * One facility's counts as the tab and the export show them: every bucket,
     * plus "other" for whatever the buckets do not hold.
     *
     * @param array<string, mixed> $row a row of the query built by fetch()
     * @return array<string, int|string>
     */
    public static function shapeRow(array $row): array
    {
        $out = [
            'facility' => (string) $row['facility_name'],
            'state' => (string) ($row['facility_state'] ?? ''),
            'district' => (string) ($row['facility_district'] ?? ''),
            'total' => (int) $row['total'],
        ];
        $counted = 0;
        foreach (array_keys(self::BUCKETS) as $bucket) {
            $out[$bucket] = (int) ($row[$bucket] ?? 0);
            $counted += $out[$bucket];
        }
        $out['other'] = $out['total'] - $counted;
        return $out;
    }

    /**
     * Column => heading, in the order of the tab's facility table. The tab
     * and the export both take their headings from here.
     *
     * @return array<string, string>
     */
    public static function columns(): array
    {
        return [
            'facility' => _translate('Facility Name'),
            'state' => _translate('Province/State'),
            'district' => _translate('District/County'),
            'total' => _translate('Samples Collected'),
            'tested' => _translate('Tested'),
            'awaitingApproval' => _translate('Awaiting Approval'),
            'awaitingTesting' => _translate('Awaiting Testing'),
            'notAtLab' => _translate('Not Yet at Lab'),
            'failed' => _translate('Failed or No Result'),
            'rejected' => _translate('Rejected'),
            'other' => _translate('Lost, Expired or Referred'),
        ];
    }

    /**
     * One export row, in the order of columns().
     *
     * @param array<string, mixed> $row a row of the query built by fetch()
     * @return list<int|string>
     */
    public static function exportRow(array $row): array
    {
        $shaped = self::shapeRow($row);
        return array_map(fn(string $column) => $shaped[$column], array_keys(self::columns()));
    }

    /**
     * Writes the tab's last result for $testType to a spreadsheet. Every
     * bucket gets its own column whether or not it holds samples, so files
     * from different periods and modules line up. Returns the file written,
     * or null when the tab has not been searched in this session.
     */
    public static function export(string $testType, DatabaseService $db): ?string
    {
        $sql = trim((string) ($_SESSION[self::sessionKey($testType)] ?? ''));
        if ($sql === '') {
            return null;
        }

        $filename = TEMP_PATH . DIRECTORY_SEPARATOR . 'InteLIS-Sample-Testing-' . $testType . '-'
            . date('d-M-Y-H-i-s') . '-' . bin2hex(random_bytes(4)) . '.xlsx';

        $writer = new Writer();
        $writer->openToFile($filename);
        $writer->addRow(Row::fromValues(array_values(self::columns())));
        foreach ($db->rawQueryGenerator($sql) as $row) {
            ExportJobUtility::tick();
            $writer->addRow(Row::fromValues(self::exportRow($row)));
        }
        $writer->close();

        return $filename;
    }
}
