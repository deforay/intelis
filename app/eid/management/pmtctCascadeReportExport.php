<?php

use App\Utilities\MiscUtility;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\AppRegistry;
use App\Registries\ContainerRegistry;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Psr\Http\Message\ServerRequestInterface;

ini_set('memory_limit', '512M');
set_time_limit(600);
ini_set('max_execution_time', 600);

/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);


// ---------------------------------------------------------------------------
// Mirror the filter logic used in getPmtctCascadeReport.php so that the
// exported file always matches what is on screen.
// ---------------------------------------------------------------------------
function pmtctExportFilterConditions(array $post, string $alias = 'e'): array
{
    $where = ["$alias.mother_id IS NOT NULL", "TRIM($alias.mother_id) != ''"];

    if (!empty($post['sampleCollectionDate'])) {
        $range = explode("to", (string) $post['sampleCollectionDate']);
        if (count($range) === 2) {
            $startSql = date('Y-m-d', strtotime(trim($range[0])));
            $endSql   = date('Y-m-d', strtotime(trim($range[1])));
            $where[] = "DATE($alias.sample_collection_date) BETWEEN '" . $startSql . "' AND '" . $endSql . "'";
        }
    }
    if (!empty($post['provinceId']) && ctype_digit((string) $post['provinceId'])) {
        $where[] = "$alias.province_id = " . (int) $post['provinceId'];
    }
    if (!empty($post['labName']) && ctype_digit((string) $post['labName'])) {
        $where[] = "$alias.lab_id = " . (int) $post['labName'];
    }
    return $where;
}

function pmtctExportEidPositiveExpr(string $alias = 'e'): string
{
    return "(LOWER(TRIM($alias.result)) = 'positive'"
        . " OR ($alias.result LIKE '%DETECTED%'"
        . " AND $alias.result NOT LIKE '%NOT DETECTED%'"
        . " AND $alias.result NOT LIKE '%Not Detected%'"
        . " AND $alias.result NOT LIKE '%not detected%'))";
}

function pmtctExportHighVlExpr(string $alias = 'v'): string
{
    return "$alias.vl_result_category = 'not suppressed'";
}

function pmtctExportVlHasResultExpr(string $alias = 'v'): string
{
    return "$alias.vl_result_category IN ('suppressed','not suppressed')";
}

function pmtctExportFormatDate($v): string
{
    if (empty($v) || $v === '0000-00-00' || $v === '0000-00-00 00:00:00') {
        return '';
    }
    $ts = strtotime((string) $v);
    return $ts ? date('d-M-Y', $ts) : '';
}

function pmtctExportIsoDate($v): string
{
    if (empty($v) || str_starts_with((string) $v, '0000-00-00')) {
        return '';
    }
    $ts = strtotime((string) $v);
    return $ts ? date('Y-m-d', $ts) : '';
}

/**
 * Same child-identity rule as the report screen: prefer the recorded Child
 * ID, fall back to the EID record's own id for records without one.
 */
function pmtctExportChildKeyExpr(string $alias = 'e'): string
{
    return "COALESCE(NULLIF(TRIM($alias.child_id), ''), CONCAT('eid:', $alias.eid_id))";
}

/**
 * 'yes' / 'no' / 'unknown' (no usable DOB): did the mother have a documented
 * high VL strictly before the child's date of birth?
 */
function pmtctExportPreBirthFlag(string $childDob, string $firstHighVlDate): string
{
    if ($childDob === '') {
        return 'unknown';
    }
    if ($firstHighVlDate === '' || $firstHighVlDate >= $childDob) {
        return 'no';
    }
    return 'yes';
}

/**
 * Latest categorised VL test (from an ascending list) on or before a date.
 */
function pmtctExportVlStatusAsOf(array $vlTests, string $asOfDate): ?array
{
    if ($asOfDate === '') {
        return null;
    }
    $latest = null;
    foreach ($vlTests as $t) {
        if ($t['date'] === '' || $t['date'] > $asOfDate) {
            continue;
        }
        if ($t['category'] === 'suppressed' || $t['category'] === 'not suppressed') {
            $latest = $t;
        }
    }
    return $latest;
}


$where = pmtctExportFilterConditions($_POST);
$whereSql = implode(' AND ', $where);
$positiveExpr = pmtctExportEidPositiveExpr('e');
$highVlExpr   = pmtctExportHighVlExpr('v');


// ---------------------------------------------------------------------------
// Precompute mother-keyed counts once. The previous version used correlated
// subqueries inside the per-row SELECTs, which on large datasets repeatedly
// scanned form_vl / form_eid with TRIM() on both sides (no index usable) and
// timed out the export. Two grouped passes here are O(N+M) instead of O(N*M).
// ---------------------------------------------------------------------------
$vlCountByMother  = [];
$eidCountByMother = [];

$vlCountSql = "SELECT TRIM(v.patient_art_no) AS mid, COUNT(*) AS cnt
    FROM form_vl v
    INNER JOIN (
        SELECT DISTINCT TRIM(e.mother_id) AS mid
        FROM form_eid e
        WHERE $whereSql
    ) m ON m.mid = TRIM(v.patient_art_no)
    GROUP BY TRIM(v.patient_art_no)";
foreach ($db->rawQueryGenerator($vlCountSql) as $r) {
    if (!empty($r['mid'])) {
        $vlCountByMother[$r['mid']] = (int) $r['cnt'];
    }
}

$eidCountSql = "SELECT TRIM(e.mother_id) AS mid, COUNT(*) AS cnt
    FROM form_eid e
    WHERE $whereSql
    GROUP BY TRIM(e.mother_id)";
foreach ($db->rawQueryGenerator($eidCountSql) as $r) {
    if (!empty($r['mid'])) {
        $eidCountByMother[$r['mid']] = (int) $r['cnt'];
    }
}


// ---------------------------------------------------------------------------
// Summary KPIs
// ---------------------------------------------------------------------------
$childRow = $db->rawQueryOne("SELECT
        COUNT(DISTINCT e.eid_id) AS totalChildrenWithMotherId,
        COUNT(DISTINCT CASE WHEN v.vl_sample_id IS NOT NULL THEN e.eid_id END) AS matchedChildren,
        COUNT(DISTINCT CASE WHEN v.vl_sample_id IS NOT NULL
                             AND e.result IS NOT NULL AND TRIM(e.result) != ''
                        THEN e.eid_id END) AS testedChildren,
        COUNT(DISTINCT CASE WHEN v.vl_sample_id IS NOT NULL AND $positiveExpr
                        THEN e.eid_id END) AS positiveChildren,
        COUNT(DISTINCT CASE WHEN v.vl_sample_id IS NULL THEN e.eid_id END) AS unmatchedChildren
    FROM form_eid e
    LEFT JOIN form_vl v ON TRIM(e.mother_id) = TRIM(v.patient_art_no)
    WHERE $whereSql");

$hasVlResult = pmtctExportVlHasResultExpr('v');
$motherRow = $db->rawQueryOne("SELECT
        COUNT(DISTINCT v.patient_art_no) AS distinctMothers,
        COUNT(DISTINCT v.vl_sample_id) AS vlTests,
        COUNT(DISTINCT CASE WHEN $hasVlResult THEN v.vl_sample_id END) AS vlTestsWithResult,
        COUNT(DISTINCT CASE WHEN $hasVlResult THEN v.patient_art_no END) AS mothersWithResult,
        COUNT(DISTINCT CASE WHEN $highVlExpr THEN v.patient_art_no END) AS mothersHighVl
    FROM form_eid e
    INNER JOIN form_vl v ON TRIM(e.mother_id) = TRIM(v.patient_art_no)
    WHERE $whereSql");


// ---------------------------------------------------------------------------
// Positive-children working set: every distinct HIV-positive matched child in
// the filter window, plus each of their mothers' full VL history (all time).
// Powers two Summary indicators and the "Positive Children History" sheet.
// ---------------------------------------------------------------------------
$childKeyExpr   = pmtctExportChildKeyExpr('e');
$whereSqlE2     = implode(' AND ', pmtctExportFilterConditions($_POST, 'e2'));
$positiveExprE2 = pmtctExportEidPositiveExpr('e2');

$posMotherSubquery = "SELECT DISTINCT TRIM(e2.mother_id) AS mid
    FROM form_eid e2
    WHERE $whereSqlE2 AND $positiveExprE2";

// Mothers' VL histories (ascending), keyed by trimmed mother ID.
$vlHistoryByMother = [];
$vlHistorySql = "SELECT TRIM(v.patient_art_no) AS mid,
        DATE(v.sample_collection_date) AS cdate,
        v.result, v.vl_result_category
    FROM form_vl v
    INNER JOIN ($posMotherSubquery) pm ON pm.mid = TRIM(v.patient_art_no)
    ORDER BY v.sample_collection_date ASC, v.vl_sample_id ASC";
foreach ($db->rawQueryGenerator($vlHistorySql) as $r) {
    $mid = (string) ($r['mid'] ?? '');
    if ($mid === '') {
        continue;
    }
    $vlHistoryByMother[$mid][] = [
        'date'     => pmtctExportIsoDate($r['cdate'] ?? null),
        'result'   => (string) ($r['result'] ?? ''),
        'category' => (string) ($r['vl_result_category'] ?? ''),
    ];
}

// Distinct positive children in the window (matched = mother appears in VL).
$posChildSql = "SELECT
        $childKeyExpr AS childKey,
        MAX(TRIM(e.child_id)) AS childId,
        MAX(e.eid_id) AS anyEidId,
        MAX(TRIM(e.mother_id)) AS motherId,
        MAX(e.child_dob) AS childDob
    FROM form_eid e
    INNER JOIN (
        SELECT DISTINCT TRIM(v.patient_art_no) AS mid FROM form_vl v
    ) vm ON vm.mid = TRIM(e.mother_id)
    WHERE $whereSql AND $positiveExpr
    GROUP BY childKey";

$positiveChildren = [];
foreach ($db->rawQueryGenerator($posChildSql) as $r) {
    $motherId = (string) ($r['motherId'] ?? '');
    $childDob = pmtctExportIsoDate($r['childDob'] ?? null);

    $firstHighVlDate = '';
    foreach ($vlHistoryByMother[$motherId] ?? [] as $t) {
        if ($t['category'] === 'not suppressed' && $t['date'] !== '') {
            $firstHighVlDate = $t['date'];
            break;
        }
    }

    $positiveChildren[(string) $r['childKey']] = [
        'childId'  => (string) ($r['childId'] ?? ''),
        'anyEidId' => (int) ($r['anyEidId'] ?? 0),
        'motherId' => $motherId,
        'childDob' => $childDob,
        'preBirth' => pmtctExportPreBirthFlag($childDob, $firstHighVlDate),
    ];
}

$positiveChildrenDistinct = count($positiveChildren);
$preBirthHighVlChildren   = count(array_filter($positiveChildren, static fn ($c) => $c['preBirth'] === 'yes'));


// ---------------------------------------------------------------------------
// Open the workbook (4 sheets: Summary, EID Data, VL Data, Positive Children
// History). EID and VL are
// kept on separate sheets — joining them inline duplicates child/mother
// demographics whenever a mother has more than one VL test, which makes the
// file hard to read and easy to double-count from. Mother ID is the link.
// ---------------------------------------------------------------------------
$filename = 'InteLIS-PMTCT-Cascade-Report-' . date('d-M-Y-H-i-s') . '-' . MiscUtility::generateRandomString(5) . '.xlsx';
$filepath = TEMP_PATH . DIRECTORY_SEPARATOR . $filename;

$writer = new Writer();
$writer->openToFile($filepath);

$headerStyle = (new Style())->withFontBold(true);


// --- Sheet 1: Summary ------------------------------------------------------
$summarySheet = $writer->getCurrentSheet();
$summarySheet->setName(_translate('Summary'));

$writer->addRow(Row::fromValues([_translate('PMTCT Cascade Report')]));
$writer->addRow(Row::fromValues([_translate('Generated'), date('d-M-Y H:i:s')]));
$writer->addRow(Row::fromValues([_translate('Filter: EID Sample Collection Date'), $_POST['sampleCollectionDate'] ?? _translate('All dates')]));
$writer->addRow(Row::fromValues([_translate('Filter: Province'), !empty($_POST['provinceId']) ? (string) $_POST['provinceId'] : _translate('All')]));
$writer->addRow(Row::fromValues([_translate('Filter: Testing Lab'), !empty($_POST['labName']) ? (string) $_POST['labName'] : _translate('All')]));
$writer->addRow(Row::fromValues([])); // spacer

$writer->addRow(Row::fromValuesWithStyle([_translate('Indicator'), _translate('Value')], $headerStyle));
$summaryRows = [
    [_translate('Children with a mother ID recorded on EID'),                (int) ($childRow['totalChildrenWithMotherId'] ?? 0)],
    [_translate('Children with matching mother in VL data'),                 (int) ($childRow['matchedChildren'] ?? 0)],
    [_translate('Of those, with EID test result available'),                 (int) ($childRow['testedChildren'] ?? 0)],
    [_translate('Of those, HIV positive'),                                   (int) ($childRow['positiveChildren'] ?? 0)],
    [_translate('Children NOT matched in VL data'),                          (int) ($childRow['unmatchedChildren'] ?? 0)],
    [_translate('Distinct mothers matched in VL data'),                      (int) ($motherRow['distinctMothers'] ?? 0)],
    [_translate('VL tests on record for matched mothers (all time)'),        (int) ($motherRow['vlTests'] ?? 0)],
    [_translate('VL tests with result reported'),                            (int) ($motherRow['vlTestsWithResult'] ?? 0)],
    [_translate('VL tests pending result'),                                  max(0, (int) ($motherRow['vlTests'] ?? 0) - (int) ($motherRow['vlTestsWithResult'] ?? 0))],
    [_translate('Mothers with VL result available'),                         (int) ($motherRow['mothersWithResult'] ?? 0)],
    [_translate('Mothers with high VL (>= 1000 cp/mL)'),                     (int) ($motherRow['mothersHighVl'] ?? 0)],
    [_translate('Distinct HIV-positive children'),                           $positiveChildrenDistinct],
    [_translate('Of those, mother had high VL before the child\'s birth'),   $preBirthHighVlChildren],
];
foreach ($summaryRows as $r) {
    $writer->addRow(Row::fromValues($r));
}


// --- Sheet 2: EID Data -----------------------------------------------------
// One row per EID record (no duplication if the mother has multiple VL tests).
$eidSheet = $writer->addNewSheetAndMakeItCurrent();
$eidSheet->setName(_translate('EID Data'));

$eidHeaders = [
    // Child block
    _translate('Child ID'),
    _translate('Child DOB'),
    _translate('Child Age in Months'),
    _translate('Child Age in Weeks'),
    _translate('Child Sex'),
    _translate('Infant ART Status'),
    _translate('Infant on PMTCT Prophylaxis'),
    _translate('Infant on CTX Prophylaxis'),
    // Mother block (as declared on EID form)
    _translate('Mother ID'),
    _translate('Mother Alive'),
    _translate('Mother DOB'),
    _translate('Mother Age (Years)'),
    _translate('Mother Marital Status'),
    _translate('Mother HIV Test Date'),
    _translate('Mother Regimen'),
    _translate('Mother MTCT Risk'),
    _translate('Mother Treatment Initiation Date'),
    _translate('Mother CD4'),
    _translate('Mother CD4 Test Date'),
    _translate('Mother VL Result (declared on EID)'),
    _translate('Mother VL Test Date (declared on EID)'),
    // EID test block
    _translate('EID Sample Code'),
    _translate('Remote EID Sample Code'),
    _translate('EID Sample Collection Date'),
    _translate('EID Sample Tested Date'),
    _translate('EID Sample Status'),
    _translate('EID Result'),
    _translate('EID Test Platform'),
    _translate('EID Testing Lab'),
    _translate('EID Facility'),
    // Link indicator
    _translate('Mother Has VL Match'),
    _translate('VL Tests for Mother'),
];
$writer->addRow(Row::fromValuesWithStyle($eidHeaders, $headerStyle));

$eidSql = "SELECT
        e.eid_id,
        e.sample_code AS eid_sample_code,
        e.remote_sample_code AS eid_remote_sample_code,
        e.sample_collection_date AS eid_collection_date,
        e.sample_tested_datetime AS eid_tested_date,
        rs.status_name AS eid_status_name,
        e.result AS eid_result,
        e.eid_test_platform,
        fel.facility_name AS eid_testing_lab,
        e.child_id, e.child_dob, e.child_age, e.child_age_in_weeks, e.child_gender,
        e.infant_art_status, e.infant_on_pmtct_prophylaxis, e.infant_on_ctx_prophylaxis,
        e.mother_id, e.is_mother_alive, e.mother_dob, e.mother_age_in_years,
        e.mother_marital_status, e.mother_hiv_test_date,
        e.mother_regimen, e.mother_mtct_risk,
        e.mother_treatment_initiation_date, e.mother_cd4, e.mother_cd4_test_date,
        e.mother_vl_result AS eid_mother_vl_result,
        e.mother_vl_test_date AS eid_mother_vl_test_date,
        fe.facility_name AS eid_facility_name
    FROM form_eid e
    LEFT JOIN facility_details fe ON fe.facility_id = e.facility_id
    LEFT JOIN facility_details fel ON fel.facility_id = e.lab_id
    LEFT JOIN r_sample_status rs ON rs.status_id = e.result_status
    WHERE $whereSql
    ORDER BY e.sample_collection_date DESC, e.eid_id";

$rowCount = 0;
foreach ($db->rawQueryGenerator($eidSql) as $r) {
    $motherKey = trim((string) ($r['mother_id'] ?? ''));
    $vlCount = ($motherKey !== '') ? ($vlCountByMother[$motherKey] ?? 0) : 0;
    $writer->addRow(Row::fromValues([
        // Child
        $r['child_id'] ?? '',
        pmtctExportFormatDate($r['child_dob'] ?? null),
        $r['child_age'] ?? '',
        $r['child_age_in_weeks'] ?? '',
        $r['child_gender'] ?? '',
        $r['infant_art_status'] ?? '',
        $r['infant_on_pmtct_prophylaxis'] ?? '',
        $r['infant_on_ctx_prophylaxis'] ?? '',
        // Mother
        $r['mother_id'] ?? '',
        $r['is_mother_alive'] ?? '',
        pmtctExportFormatDate($r['mother_dob'] ?? null),
        $r['mother_age_in_years'] ?? '',
        $r['mother_marital_status'] ?? '',
        pmtctExportFormatDate($r['mother_hiv_test_date'] ?? null),
        $r['mother_regimen'] ?? '',
        $r['mother_mtct_risk'] ?? '',
        pmtctExportFormatDate($r['mother_treatment_initiation_date'] ?? null),
        $r['mother_cd4'] ?? '',
        pmtctExportFormatDate($r['mother_cd4_test_date'] ?? null),
        $r['eid_mother_vl_result'] ?? '',
        pmtctExportFormatDate($r['eid_mother_vl_test_date'] ?? null),
        // EID test
        $r['eid_sample_code'] ?? '',
        $r['eid_remote_sample_code'] ?? '',
        pmtctExportFormatDate($r['eid_collection_date'] ?? null),
        pmtctExportFormatDate($r['eid_tested_date'] ?? null),
        $r['eid_status_name'] ?? '',
        !empty($r['eid_result']) ? ucfirst((string) $r['eid_result']) : '',
        $r['eid_test_platform'] ?? '',
        $r['eid_testing_lab'] ?? '',
        $r['eid_facility_name'] ?? '',
        // Link indicator
        $vlCount > 0 ? _translate('Yes') : _translate('No'),
        $vlCount,
    ]));

    $rowCount++;
    if ($rowCount % 5000 === 0) {
        gc_collect_cycles();
    }
}


// --- Sheet 3: VL Data ------------------------------------------------------
// One row per VL record whose patient_art_no matches a Mother ID from the
// EID set above. Same Mother ID column links back to Sheet 2.
$vlSheet = $writer->addNewSheetAndMakeItCurrent();
$vlSheet->setName(_translate('VL Data'));

$vlHeaders = [
    _translate('Mother ID'),
    _translate('Patient Name'),
    _translate('Patient DOB'),
    _translate('Patient Sex'),
    _translate('VL Sample Code'),
    _translate('Remote VL Sample Code'),
    _translate('VL Sample Collection Date'),
    _translate('VL Sample Tested Date'),
    _translate('VL Result'),
    _translate('VL Result (Absolute cp/mL)'),
    _translate('VL Result (Log)'),
    _translate('VL Result Interpretation'),
    _translate('VL Is High (>= 1000 cp/mL)'),
    _translate('VL Test Platform'),
    _translate('VL Testing Lab'),
    _translate('VL Facility'),
    _translate('Matched EID Records for Mother'),
];
$writer->addRow(Row::fromValuesWithStyle($vlHeaders, $headerStyle));

$vlSql = "SELECT
        v.patient_art_no,
        v.patient_first_name,
        v.patient_last_name,
        v.patient_dob,
        v.patient_gender,
        v.sample_code AS vl_sample_code,
        v.remote_sample_code AS vl_remote_sample_code,
        v.sample_collection_date AS vl_collection_date,
        v.sample_tested_datetime AS vl_tested_date,
        v.result AS vl_result,
        v.result_value_absolute,
        v.result_value_log,
        v.vl_result_category,
        ($highVlExpr) AS vl_is_high,
        v.vl_test_platform,
        fvl.facility_name AS vl_testing_lab,
        fv.facility_name AS vl_facility_name
    FROM form_vl v
    INNER JOIN (
        SELECT DISTINCT TRIM(e.mother_id) AS mid
        FROM form_eid e
        WHERE $whereSql
    ) m ON m.mid = TRIM(v.patient_art_no)
    LEFT JOIN facility_details fv ON fv.facility_id = v.facility_id
    LEFT JOIN facility_details fvl ON fvl.facility_id = v.lab_id
    ORDER BY v.patient_art_no, v.sample_collection_date DESC";

$rowCount = 0;
foreach ($db->rawQueryGenerator($vlSql) as $r) {
    $patientName = trim(($r['patient_first_name'] ?? '') . ' ' . ($r['patient_last_name'] ?? ''));
    $motherKey = trim((string) ($r['patient_art_no'] ?? ''));
    $matchedEidCount = ($motherKey !== '') ? ($eidCountByMother[$motherKey] ?? 0) : 0;
    $writer->addRow(Row::fromValues([
        $r['patient_art_no'] ?? '',
        $patientName,
        pmtctExportFormatDate($r['patient_dob'] ?? null),
        $r['patient_gender'] ?? '',
        $r['vl_sample_code'] ?? '',
        $r['vl_remote_sample_code'] ?? '',
        pmtctExportFormatDate($r['vl_collection_date'] ?? null),
        pmtctExportFormatDate($r['vl_tested_date'] ?? null),
        $r['vl_result'] ?? '',
        $r['result_value_absolute'] ?? '',
        $r['result_value_log'] ?? '',
        !empty($r['vl_result_category']) ? ucwords((string) $r['vl_result_category']) : '',
        ((int) ($r['vl_is_high'] ?? 0)) === 1 ? _translate('Yes') : _translate('No'),
        $r['vl_test_platform'] ?? '',
        $r['vl_testing_lab'] ?? '',
        $r['vl_facility_name'] ?? '',
        $matchedEidCount,
    ]));

    $rowCount++;
    if ($rowCount % 5000 === 0) {
        gc_collect_cycles();
    }
}


// --- Sheet 4: Positive Children History ------------------------------------
// One row per EID test (all time) of every HIV-positive child from the filter
// window, each stamped with the mother's VL status as of that test date and
// whether she had a documented high VL before the child's birth. The mothers'
// full VL timelines are on the VL Data sheet (Mother ID links the two).
$historySheet = $writer->addNewSheetAndMakeItCurrent();
$historySheet->setName(_translate('Positive Children History'));

$historyHeaders = [
    _translate('Child ID'),
    _translate('Child DOB'),
    _translate('Child Sex'),
    _translate('Mother ID'),
    _translate('EID Sample Code'),
    _translate('Remote EID Sample Code'),
    _translate('EID Sample Collection Date'),
    _translate('EID Sample Tested Date'),
    _translate('EID Sample Status'),
    _translate('EID Result'),
    _translate('EID Test Platform'),
    _translate('EID Testing Lab'),
    _translate('Mother VL Status at This Test'),
    _translate('Mother VL Result at This Test'),
    _translate('Mother VL Test Date at This Test'),
    _translate('Mother Had High VL Before Birth'),
];
$writer->addRow(Row::fromValuesWithStyle($historyHeaders, $headerStyle));

// Split the child set into "has a Child ID" (history keyed on it) and
// "no Child ID" (single EID record, keyed on eid_id).
$childIdList = [];
$eidIdList   = [];
foreach ($positiveChildren as $c) {
    if ($c['childId'] !== '') {
        $childIdList[] = $c['childId'];
    } elseif ($c['anyEidId'] > 0) {
        $eidIdList[] = $c['anyEidId'];
    }
}

$historyEidSelect = "SELECT
        e.eid_id, TRIM(e.child_id) AS child_id, e.child_dob, e.child_gender,
        TRIM(e.mother_id) AS mother_id,
        e.sample_code, e.remote_sample_code,
        e.sample_collection_date, e.sample_tested_datetime,
        e.result, e.eid_test_platform,
        rs.status_name,
        fel.facility_name AS testing_lab
    FROM form_eid e
    LEFT JOIN facility_details fel ON fel.facility_id = e.lab_id
    LEFT JOIN r_sample_status rs ON rs.status_id = e.result_status";

$writeHistoryRows = static function (array $rows) use ($writer, $positiveChildren, $vlHistoryByMother): void {
    foreach ($rows as $r) {
        $childId  = (string) ($r['child_id'] ?? '');
        $childKey = $childId !== '' ? $childId : 'eid:' . (int) ($r['eid_id'] ?? 0);
        $child    = $positiveChildren[$childKey] ?? null;
        $motherId = (string) ($r['mother_id'] ?? '');

        $collectionDate = pmtctExportIsoDate($r['sample_collection_date'] ?? null);
        $atTest = pmtctExportVlStatusAsOf($vlHistoryByMother[$motherId] ?? [], $collectionDate);

        $statusLabel = _translate('No VL result yet');
        if ($atTest !== null) {
            $statusLabel = $atTest['category'] === 'not suppressed'
                ? _translate('High VL (>= 1000 cp/mL)')
                : _translate('Suppressed');
        }

        $preBirth = $child['preBirth'] ?? 'unknown';
        $preBirthLabel = match ($preBirth) {
            'yes'   => _translate('Yes'),
            'no'    => _translate('No'),
            default => _translate('Unknown (no DOB)'),
        };

        $writer->addRow(Row::fromValues([
            $childId,
            pmtctExportFormatDate($r['child_dob'] ?? null),
            $r['child_gender'] ?? '',
            $motherId,
            $r['sample_code'] ?? '',
            $r['remote_sample_code'] ?? '',
            pmtctExportFormatDate($r['sample_collection_date'] ?? null),
            pmtctExportFormatDate($r['sample_tested_datetime'] ?? null),
            $r['status_name'] ?? '',
            !empty($r['result']) ? ucfirst((string) $r['result']) : '',
            $r['eid_test_platform'] ?? '',
            $r['testing_lab'] ?? '',
            $statusLabel,
            $atTest['result'] ?? '',
            pmtctExportFormatDate($atTest['date'] ?? null),
            $preBirthLabel,
        ]));
    }
};

foreach (array_chunk($childIdList, 500) as $chunk) {
    $in = "'" . implode("','", array_map(static fn ($v) => $db->escape($v), $chunk)) . "'";
    $rows = $db->rawQuery("$historyEidSelect WHERE TRIM(e.child_id) IN ($in)
        ORDER BY TRIM(e.child_id), e.sample_collection_date ASC, e.eid_id ASC") ?: [];
    $writeHistoryRows($rows);
}
foreach (array_chunk($eidIdList, 500) as $chunk) {
    $in = implode(',', array_map('intval', $chunk));
    $rows = $db->rawQuery("$historyEidSelect WHERE e.eid_id IN ($in)
        ORDER BY e.sample_collection_date ASC, e.eid_id ASC") ?: [];
    $writeHistoryRows($rows);
}


$writer->close();

echo urlencode($filename);
