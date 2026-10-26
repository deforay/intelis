<?php

use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use OpenSpout\Writer\Exception\InvalidSheetNameException;
use App\Utilities\SampleCountUtility;
use App\Utilities\ExportJobUtility;

// The page asked for a background export: queue it and answer at once, so the
// user can move on while bin/export-worker.php runs this same script.
if (ExportJobUtility::queueRequested(__FILE__)) {
	return;
}


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$sarr = $general->getSystemConfig();

if (isset($_POST['reportedDate']) && trim((string) $_POST['reportedDate']) !== '') {
    $s_t_date = explode("to", (string) $_POST['reportedDate']);
    if (isset($s_t_date[0]) && trim($s_t_date[0]) !== "") {
        $start_date = DateUtility::isoDateFormat(trim($s_t_date[0]));
    }
    if (isset($s_t_date[1]) && trim($s_t_date[1]) !== "") {
        $end_date = DateUtility::isoDateFormat(trim($s_t_date[1]));
    }
}



if (isset($_POST['lab']) && trim((string) $_POST['lab']) !== '') {
    $labId = ($_POST['lab']);
}

$sQuery = "SELECT
            vl.lab_id,
            vl.facility_id,
            f.facility_code,
            f.facility_state,
            f.facility_district,
            f.facility_name,
            lab.facility_name as lab_name,
            lab.facility_code as fcode,

		SUM(CASE
            WHEN (reason_for_sample_rejection IS NOT NULL AND reason_for_sample_rejection!= '' AND reason_for_sample_rejection!= 0) THEN 1
            ELSE 0
            END) AS rejections,
		SUM(CASE
            WHEN ((patient_age_in_years >= 0 AND patient_age_in_years <= 15) AND vl.vl_result_category like 'suppressed') THEN 1
            ELSE 0
            END) AS lt15suppressed,
		SUM(CASE
            WHEN ((patient_age_in_years >= 0 AND patient_age_in_years <= 15) AND vl.vl_result_category like 'not suppressed') THEN 1
            ELSE 0
            END) AS lt15NotSuppressed,
		SUM(CASE
            WHEN (patient_age_in_years > 15 AND patient_gender IN ('m','male','M','MALE') AND vl.vl_result_category like 'suppressed') THEN 1
            ELSE 0
            END) AS gt15suppressedM,
		SUM(CASE
            WHEN (patient_age_in_years > 15 AND patient_gender IN ('m','male','M','MALE') AND vl.vl_result_category like 'not suppressed') THEN 1
            ELSE 0
            END) AS gt15NotSuppressedM,
		SUM(CASE
            WHEN (patient_age_in_years > 15 AND patient_gender IN ('f','female','F','FEMALE') AND vl.vl_result_category like 'suppressed') THEN 1
            ELSE 0
            END) AS gt15suppressedF,
		SUM(CASE
            WHEN (patient_age_in_years > 15 AND patient_gender IN ('f','female','F','FEMALE') AND vl.vl_result_category like 'not suppressed') THEN 1
            ELSE 0
            END) AS gt15NotSuppressedF,
		SUM(CASE
            WHEN (is_patient_pregnant like 'yes' AND vl.vl_result_category like 'suppressed') THEN 1
            ELSE 0
            END) AS pregSuppressed,
		SUM(CASE
            WHEN (is_patient_pregnant like 'yes' AND vl.vl_result_category like 'not suppressed') THEN 1
            ELSE 0
            END) AS pregNotSuppressed,
		SUM(CASE
            WHEN ((patient_gender = '' OR patient_gender = 'unknown' OR patient_gender = 'unreported' OR patient_gender is NULL) AND vl.vl_result_category like 'suppressed') THEN 1
            ELSE 0
            END) AS genderUnknownSuppressed,
		SUM(CASE
            WHEN ((patient_gender = '' OR patient_gender = 'unknown' OR patient_gender = 'unreported' OR patient_gender is NULL) AND vl.vl_result_category like 'not suppressed') THEN 1
            ELSE 0
            END) AS genderUnknownNotSuppressed,
		SUM(CASE
            WHEN (vl.vl_result_category like 'suppressed') THEN 1
            ELSE 0
            END) AS totalLessThan1000,
		SUM(CASE
            WHEN (vl.vl_result_category like 'not suppressed') THEN 1
            ELSE 0
            END) AS totalGreaterThan1000,
		COUNT(result) as total


        FROM form_vl as vl
        INNER JOIN facility_details as f ON f.facility_id=vl.facility_id
        INNER JOIN facility_details as lab ON lab.facility_id=vl.lab_id
        WHERE vl.lab_id is NOT NULL AND IFNULL(reason_for_vl_testing, 0)  != 9999
        AND " . SampleCountUtility::countableWhere('vl') . " ";

if (!empty($labId)) {
    $sQuery .= " AND vl.lab_id IN (" . $db->inIntList($labId) . ")";
}

if (isset($_POST['reportedDate']) && trim((string) $_POST['reportedDate']) !== '') {
    if (trim((string) $start_date) === trim((string) $end_date)) {
        $sQuery = $sQuery . ' AND DATE(vl.sample_tested_datetime) = "' . $start_date . '"';
    } else {
        $sQuery = $sQuery . ' AND DATE(vl.sample_tested_datetime) >= "' . $start_date . '" AND DATE(vl.sample_tested_datetime) <= "' . $end_date . '"';
    }
}

$sQuery .= ' GROUP BY lab_name, vl.facility_id';

$resultSet = $db->rawQuery($sQuery);

$excelResultSet = [];
foreach ($resultSet as $row) {
    ExportJobUtility::tick();
    $excelResultSet[$row['lab_name'] . '-' . $row['fcode']][] = $row;
}

//Statistics sheet start
if ($excelResultSet === []) {
    echo '';
    return;
}

$filename = 'InteLIS-VL-Lab-Weekly-Report-' . date('d-M-Y-H-i-s') . '.xlsx';
$writer = new Writer();
$writer->openToFile(TEMP_PATH . DIRECTORY_SEPARATOR . $filename);

$c = 0;
foreach ($excelResultSet as $vlLab => $labResult) {

    if ($c > 0) {
        $writer->addNewSheetAndMakeItCurrent();
    }
    $vlLab = preg_replace('/\s+/', ' ', ($vlLab));

    // A name Excel would refuse (too long, a repeat, odd characters) keeps the default "SheetN".
    // The facility code, read from the row itself: a hyphen in the lab name would shift a split of the group key.
    $sheetName = trim((string) ($labResult[0]['fcode'] ?? ''));
    if ($sheetName !== '') {
        try {
            $writer->getCurrentSheet()->setName($sheetName);
        } catch (InvalidSheetNameException) {
        }
    }

    $decode = static fn($value): string => html_entity_decode((string) $value, ENT_QUOTES, 'UTF-8');
    $writer->addRow(Row::fromValues(array_map($decode, ['', 'Reported Date ', $_POST['reportedDate'], 'Lab Name ', $vlLab])));
    $writer->addRow(Row::fromValues(array_map($decode, ['', 'Province/State ', 'District/County ', 'Site Name ', 'Facility ID ', 'No. of Rejections ', 'Viral Load Result- Peds ', '', 'Viral Load Result- Adults ', '', '', '', 'Viral Load Results- Pregnant/Breastfeeding Women ', '', 'Age/Sex Unknown ', '', 'Totals ', '', 'Total Registered ', 'Comments '])));
    $writer->addRow(Row::fromValues(array_map($decode, ['', '', '', '', '', '', '<15 yrs <=1000 copies/mL ', '<15 yrs >1000 copies/mL ', '>15yrs Male <=1000 copies/mL ', '>15yrs Male >1000 copies/mL ', '>15yrs Female <=1000 copies/mL ', '>15yrs  Female >1000 copies/mL ', '<= 1000 copies/mL ', '> 1000 copies/mL ', 'Unknown Age/Sex <= 1000ml ', 'Unknown Age/Sex > 1000ml ', '<= 1000 copies/mL ', '> 1000 copies/mL '])));

    $output = [];
    $r = 1;
    if ($labResult !== []) {
        foreach ($labResult as $aRow) {
            $row = [];
            $row[] = $r;
            $row[] = ($aRow['facility_state']);
            $row[] = ($aRow['facility_district']);
            $row[] = ($aRow['facility_name']);
            $row[] = $aRow['facility_code'];
            $row[] = $aRow['rejections'];
            $row[] = $aRow['lt15suppressed'];
            $row[] = $aRow['lt15NotSuppressed'];
            $row[] = $aRow['gt15suppressedM'];
            $row[] = $aRow['gt15NotSuppressedM'];
            $row[] = $aRow['gt15suppressedF'];
            $row[] = $aRow['gt15NotSuppressedF'];
            $row[] = $aRow['pregSuppressed'];
            $row[] = $aRow['pregNotSuppressed'];
            $row[] = $aRow['genderUnknownSuppressed'];
            $row[] = $aRow['genderUnknownNotSuppressed'];
            $row[] = $aRow['totalLessThan1000'];
            $row[] = $aRow['totalGreaterThan1000'];
            $row[] = $aRow['total'];
            $row[] = '';
            $output[] = $row;
            $r++;
        }
    } else {
        $row = [];
        $row[] = '';
        $row[] = '';
        $row[] = '';
        $row[] = '';
        $row[] = '';
        $row[] = '';
        $row[] = '';
        $row[] = '';
        $row[] = '';
        $row[] = '';
        $row[] = '';
        $row[] = '';
        $row[] = '';
        $row[] = '';
        $row[] = '';
        $row[] = '';
        $row[] = '';
        $row[] = '';
        $row[] = '';
        $row[] = '';
        $output[] = $row;
        $r++;
    }

    foreach ($output as $rowData) {
        $writer->addRow(Row::fromValues(array_map(static fn($value) => is_string($value) ? html_entity_decode($value) : $value, $rowData)));
    }
    $c++;
}
//Statistics sheet end
$writer->close();

echo _downloadToken($filename);
