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
function pmtctExportFilterConditions(array $post): array
{
    $where = ["e.mother_id IS NOT NULL", "TRIM(e.mother_id) != ''"];

    if (!empty($post['sampleCollectionDate'])) {
        $range = explode("to", (string) $post['sampleCollectionDate']);
        if (count($range) === 2) {
            $startSql = date('Y-m-d', strtotime(trim($range[0])));
            $endSql   = date('Y-m-d', strtotime(trim($range[1])));
            $where[] = "DATE(e.sample_collection_date) BETWEEN '" . $startSql . "' AND '" . $endSql . "'";
        }
    }
    if (!empty($post['provinceId']) && ctype_digit((string) $post['provinceId'])) {
        $where[] = "e.province_id = " . (int) $post['provinceId'];
    }
    if (!empty($post['labName']) && ctype_digit((string) $post['labName'])) {
        $where[] = "e.lab_id = " . (int) $post['labName'];
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
    return "(($alias.result_value_absolute IS NOT NULL AND $alias.result_value_absolute >= 1000)"
        . " OR ($alias.result_value_log IS NOT NULL AND $alias.result_value_log >= 3))";
}

function pmtctExportFormatDate($v): string
{
    if (empty($v) || $v === '0000-00-00' || $v === '0000-00-00 00:00:00') {
        return '';
    }
    $ts = strtotime((string) $v);
    return $ts ? date('d-M-Y', $ts) : '';
}


$where = pmtctExportFilterConditions($_POST);
$whereSql = implode(' AND ', $where);
$positiveExpr = pmtctExportEidPositiveExpr('e');
$highVlExpr   = pmtctExportHighVlExpr('v');


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

$motherRow = $db->rawQueryOne("SELECT
        COUNT(DISTINCT v.patient_art_no) AS distinctMothers,
        COUNT(DISTINCT v.vl_sample_id) AS vlTests,
        COUNT(DISTINCT CASE WHEN v.result IS NOT NULL AND TRIM(v.result) != ''
                        THEN v.patient_art_no END) AS mothersWithResult,
        COUNT(DISTINCT CASE WHEN $highVlExpr THEN v.patient_art_no END) AS mothersHighVl
    FROM form_eid e
    INNER JOIN form_vl v ON TRIM(e.mother_id) = TRIM(v.patient_art_no)
    WHERE $whereSql");


// ---------------------------------------------------------------------------
// Open the workbook (3 sheets: Summary, Linked Pairs, Unmatched Children)
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
    [_translate('VL tests reported for matched mothers'),                    (int) ($motherRow['vlTests'] ?? 0)],
    [_translate('Mothers with VL result available'),                         (int) ($motherRow['mothersWithResult'] ?? 0)],
    [_translate('Mothers with high VL (>= 1000 cp/mL)'),                     (int) ($motherRow['mothersHighVl'] ?? 0)],
];
foreach ($summaryRows as $r) {
    $writer->addRow(Row::fromValues($r));
}


// --- Sheet 2: Linked Pairs -------------------------------------------------
$linkedSheet = $writer->addNewSheetAndMakeItCurrent();
$linkedSheet->setName(_translate('Linked Pairs'));

$linkedHeaders = [
    // Child block
    _translate('Child ID'),
    _translate('Child DOB'),
    _translate('Child Age in Months'),
    _translate('Child Age in Weeks'),
    _translate('Child Sex'),
    _translate('Infant ART Status'),
    _translate('Infant on PMTCT Prophylaxis'),
    _translate('Infant on CTX Prophylaxis'),
    // Mother block
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
    // EID block
    _translate('EID Sample Code'),
    _translate('Remote EID Sample Code'),
    _translate('EID Sample Collection Date'),
    _translate('EID Sample Tested Date'),
    _translate('EID Sample Status'),
    _translate('EID Result'),
    _translate('EID Test Platform'),
    _translate('EID Testing Lab'),
    _translate('EID Facility'),
    // VL block
    _translate('VL Sample Code'),
    _translate('VL Sample Collection Date'),
    _translate('VL Sample Tested Date'),
    _translate('VL Result'),
    _translate('VL Result (Absolute cp/mL)'),
    _translate('VL Is High (>= 1000 cp/mL)'),
    _translate('VL Test Platform'),
    _translate('VL Testing Lab'),
];
$writer->addRow(Row::fromValuesWithStyle($linkedHeaders, $headerStyle));

$linkedSql = "SELECT
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
        e.mother_marital_status, e.mother_hiv_test_date, e.mother_hiv_status,
        e.mother_art_status, e.mother_regimen, e.mother_mtct_risk,
        e.mother_treatment_initiation_date, e.mother_cd4, e.mother_cd4_test_date,
        e.mother_vl_result AS eid_mother_vl_result,
        e.mother_vl_test_date AS eid_mother_vl_test_date,
        v.sample_code AS vl_sample_code,
        v.sample_collection_date AS vl_collection_date,
        v.sample_tested_datetime AS vl_tested_date,
        v.result AS vl_result,
        v.result_value_log,
        v.result_value_absolute,
        $highVlExpr AS vl_is_high,
        v.vl_test_platform,
        fvl.facility_name AS vl_testing_lab,
        fv.facility_name AS vl_facility_name,
        fe.facility_name AS eid_facility_name
    FROM form_eid e
    INNER JOIN form_vl v ON TRIM(e.mother_id) = TRIM(v.patient_art_no)
    LEFT JOIN facility_details fv ON fv.facility_id = v.facility_id
    LEFT JOIN facility_details fe ON fe.facility_id = e.facility_id
    LEFT JOIN facility_details fel ON fel.facility_id = e.lab_id
    LEFT JOIN facility_details fvl ON fvl.facility_id = v.lab_id
    LEFT JOIN r_sample_status rs ON rs.status_id = e.result_status
    WHERE $whereSql
    ORDER BY e.sample_collection_date DESC, e.eid_id, v.sample_collection_date";

$rowCount = 0;
foreach ($db->rawQueryGenerator($linkedSql) as $r) {
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
        // EID
        $r['eid_sample_code'] ?? '',
        $r['eid_remote_sample_code'] ?? '',
        pmtctExportFormatDate($r['eid_collection_date'] ?? null),
        pmtctExportFormatDate($r['eid_tested_date'] ?? null),
        $r['eid_status_name'] ?? '',
        !empty($r['eid_result']) ? ucfirst((string) $r['eid_result']) : '',
        $r['eid_test_platform'] ?? '',
        $r['eid_testing_lab'] ?? '',
        $r['eid_facility_name'] ?? '',
        // VL
        $r['vl_sample_code'] ?? '',
        pmtctExportFormatDate($r['vl_collection_date'] ?? null),
        pmtctExportFormatDate($r['vl_tested_date'] ?? null),
        $r['vl_result'] ?? '',
        $r['result_value_absolute'] ?? '',
        ((int) ($r['vl_is_high'] ?? 0)) === 1 ? _translate('Yes') : _translate('No'),
        $r['vl_test_platform'] ?? '',
        $r['vl_testing_lab'] ?? '',
    ]));

    $rowCount++;
    if ($rowCount % 5000 === 0) {
        gc_collect_cycles();
    }
}


$writer->close();

echo urlencode($filename);
