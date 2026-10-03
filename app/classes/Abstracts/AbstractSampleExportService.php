<?php

namespace App\Abstracts;

use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Utilities\MiscUtility;
use App\Utilities\SampleExportUtility;

/**
 * A test module's Excel export: one column list for every country and every
 * export page of the module. A module supplies its columns and the query that
 * loads them; writing, chunking, patient-detail rules and country tags are
 * shared (SampleExportUtility).
 */
abstract class AbstractSampleExportService
{
    public function __construct(
        protected readonly DatabaseService $db,
        protected readonly CommonService $general
    ) {
    }

    /** The primary key the listing queries select. */
    abstract protected function idColumn(): string;

    /** Every column the module can show, before country and patient rules. */
    abstract protected function allColumns(): array;

    /**
     * Everything the columns read, for one chunk of samples, keyed by id.
     * $key is empty when patient details were not asked for (nothing to decrypt).
     *
     * @param list<int> $ids
     * @return array<int, array<string, mixed>>
     */
    abstract protected function fetchDetails(array $ids, string $key): array;

    /**
     * Writes the export for a listing query and returns the file path.
     *
     * @param list<list<string>> $preamble rows written above the headings
     */
    public function export(
        string $listQuery,
        string $filePrefix,
        bool $withPatientInfo,
        array $preamble = [],
        bool $alphaNumHeadings = false
    ): string {
        $filename = TEMP_PATH . DIRECTORY_SEPARATOR . $filePrefix . '-' . date('d-M-Y-H-i-s')
            . '-' . MiscUtility::generateRandomString(6) . '.xlsx';
        $key = $withPatientInfo ? (string) $this->general->getGlobalConfig('key') : '';

        SampleExportUtility::writeXlsx(
            $filename,
            $this->db->rawQueryGenerator($listQuery),
            $this->idColumn(),
            fn(array $ids): array => $this->fetchDetails($ids, $key),
            $this->columns($withPatientInfo),
            $preamble,
            $alphaNumHeadings
        );

        return $filename;
    }

    /**
     * The WHERE clause of a details query (table alias vl) for a chunk of ids.
     * The listing was lab-scoped when it ran, but a sample can move to another
     * lab before its chunk loads, so the scope is applied again here.
     */
    protected function detailsWhere(array $ids): string
    {
        $where = "vl.{$this->idColumn()} IN (" . $this->db->inIntList($ids) . ")";
        $labScope = $this->general->labScopeWhere('vl');
        return $labScope === '' ? $where : "$where AND $labScope";
    }

    /** The columns this instance shows. */
    public function columns(bool $withPatientInfo): array
    {
        return SampleExportUtility::forForm(
            $this->allColumns(),
            (int) $this->general->getGlobalConfig('vl_form'),
            $withPatientInfo,
            $this->general->isStandaloneInstance()
        );
    }
}
