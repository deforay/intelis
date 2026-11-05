<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Utilities\DateUtility;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Border;
use OpenSpout\Common\Entity\Style\BorderName;
use OpenSpout\Common\Entity\Style\BorderPart;
use OpenSpout\Common\Entity\Style\BorderStyle;
use OpenSpout\Common\Entity\Style\BorderWidth;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\CellVerticalAlignment;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;
use App\Utilities\SampleCountUtility;
use App\Utilities\ExportJobUtility;

// The page asked for a background export: queue it and answer at once, so the
// user can move on while bin/export-worker.php runs this same script.
if (ExportJobUtility::queueRequested(__FILE__, null, ['vlMonitoringResultQuery'], countsRows: false)) {
	return;
}




/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

if (isset($_SESSION['vlMonitoringResultQuery']) && trim((string) $_SESSION['vlMonitoringResultQuery']) !== "") {
    $rResult = $db->rawQuery($_SESSION['vlMonitoringResultQuery']);

    //get current quarter total samples tested
    $sQuery = "SELECT vl.facility_id,f.facility_name,f.facility_code,f.facility_state,f.facility_district,

        SUM(CASE WHEN (vl_result_category = 'suppressed') THEN 1 ELSE 0 END) AS suppressed,
        SUM(CASE WHEN (vl_result_category = 'not suppressed') THEN 1 ELSE 0 END) AS NotSuppressed,
        SUM(CASE WHEN (vl_result_category = 'suppressed' AND (patient_gender='male' OR patient_gender='MALE')) THEN 1 ELSE 0 END) AS ltMale1000,
        SUM(CASE WHEN (vl_result_category = 'not suppressed' AND (patient_gender='male' OR patient_gender='MALE')) THEN 1 ELSE 0 END) AS gtMale1000,
        SUM(CASE WHEN (vl_result_category = 'suppressed' AND (patient_gender='female' OR patient_gender='FEMALE')) THEN 1 ELSE 0 END) AS ltFemale1000,
        SUM(CASE WHEN (vl_result_category = 'not suppressed' AND (patient_gender='female' OR patient_gender='FEMALE')) THEN 1 ELSE 0 END) AS gtFemale1000,
        SUM(CASE WHEN (vl_result_category = 'suppressed' AND (patient_gender='not_specified')) THEN 1 ELSE 0 END) AS ltNotSpecified1000,
        SUM(CASE WHEN (vl_result_category = 'not suppressed' AND (patient_gender='not_specified')) THEN 1 ELSE 0 END) AS gtNotSpecified1000,
        SUM(CASE WHEN (vl_result_category = 'suppressed' AND patient_gender!='') THEN 1 ELSE 0 END) AS ltTotalGender1000,
        SUM(CASE WHEN (vl_result_category = 'not suppressed' AND patient_gender!='') THEN 1 ELSE 0 END) AS gtTotalGender1000,
        SUM(CASE WHEN (patient_age_in_years ='' AND patient_age_in_months<=12 AND vl_result_category = 'suppressed') THEN 1 ELSE 0 END) AS ltAgeOne1000,
        SUM(CASE WHEN (patient_age_in_years ='' AND patient_age_in_months<=12 AND vl_result_category = 'not suppressed') THEN 1 ELSE 0 END) AS gtAgeOne1000,
        SUM(CASE WHEN (patient_age_in_years >= 1 AND patient_age_in_years<=9 AND vl_result_category = 'suppressed') THEN 1 ELSE 0 END) AS ltAgeOnetoNine1000,
        SUM(CASE WHEN (patient_age_in_years >= 1 AND patient_age_in_years<=9 AND vl_result_category = 'not suppressed') THEN 1 ELSE 0 END) AS gtAgeOnetoNine1000,
        SUM(CASE WHEN (patient_age_in_years >= 10 AND patient_age_in_years<=14 AND vl_result_category = 'suppressed') THEN 1 ELSE 0 END) AS ltAgeTentoFourteen1000,
        SUM(CASE WHEN (patient_age_in_years >= 10 AND patient_age_in_years<=14 AND vl_result_category = 'not suppressed') THEN 1 ELSE 0 END) AS gtAgeTentoFourteen1000,
        SUM(CASE WHEN (patient_age_in_years<=15 AND vl_result_category = 'suppressed') THEN 1 ELSE 0 END) AS ltAgeTotalFifteen1000,
        SUM(CASE WHEN (patient_age_in_years<=15 AND vl_result_category = 'not suppressed') THEN 1 ELSE 0 END) AS gtAgeTotalFifteen1000,
        SUM(CASE WHEN (patient_age_in_years >= 15 AND patient_age_in_years<=19 AND vl_result_category = 'suppressed') THEN 1 ELSE 0 END) AS ltAgeFifteentoNineteen1000,
        SUM(CASE WHEN (patient_age_in_years >= 15 AND patient_age_in_years<=19 AND vl_result_category = 'not suppressed') THEN 1 ELSE 0 END) AS gtAgeFifteentoNineteen1000,
        SUM(CASE WHEN (patient_age_in_years >= 20 AND patient_age_in_years<=24 AND vl_result_category = 'suppressed') THEN 1 ELSE 0 END) AS ltAgeTwentytoTwentyFour1000,
        SUM(CASE WHEN (patient_age_in_years >= 20 AND patient_age_in_years<=24 AND vl_result_category = 'not suppressed') THEN 1 ELSE 0 END) AS gtAgeTwentytoTwentyFour1000,
        SUM(CASE WHEN (patient_age_in_years >= 15 AND patient_age_in_years<=24 AND vl_result_category = 'suppressed') THEN 1 ELSE 0 END) AS ltAgeFifteentoTwentyFour1000,
        SUM(CASE WHEN (patient_age_in_years >= 15 AND patient_age_in_years<=24 AND vl_result_category = 'not suppressed') THEN 1 ELSE 0 END) AS gtAgeFifteentoTwentyFour1000,
        SUM(CASE WHEN (patient_age_in_years >= 25 AND vl_result_category = 'suppressed') THEN 1 ELSE 0 END) AS ltAgetwentyFive1000,
        SUM(CASE WHEN (patient_age_in_years >= 25 AND vl_result_category = 'not suppressed') THEN 1 ELSE 0 END) AS gtAgetwentyFive1000,
        SUM(CASE WHEN (patient_age_in_years is NULL AND vl_result_category = 'suppressed') THEN 1 ELSE 0 END) AS ltAgeNotSpecified1000,
        SUM(CASE WHEN (patient_age_in_years is NULL AND vl_result_category = 'not suppressed') THEN 1 ELSE 0 END) AS gtAgeNotSpecified1000,
        SUM(CASE WHEN (vl_result_category = 'suppressed') THEN 1 ELSE 0 END) AS ltAgeTotal1000,
        SUM(CASE WHEN (vl_result_category = 'not suppressed') THEN 1 ELSE 0 END) AS gtAgeTotal1000,
        SUM(CASE WHEN (is_patient_pregnant = 'yes' AND vl_result_category = 'suppressed') THEN 1 ELSE 0 END) AS ltPatientPregnant1000,
        SUM(CASE WHEN (is_patient_pregnant = 'yes' AND vl_result_category = 'not suppressed') THEN 1 ELSE 0 END) AS gtPatientPregnant1000,
        SUM(CASE WHEN (is_patient_breastfeeding = 'yes' AND vl_result_category = 'suppressed') THEN 1 ELSE 0 END) AS ltPatientBreastFeeding1000,
        SUM(CASE WHEN (is_patient_breastfeeding = 'yes' AND vl_result_category = 'not suppressed') THEN 1 ELSE 0 END) AS gtPatientBreastFeeding1000
		FROM form_vl as vl JOIN facility_details as f ON vl.facility_id=f.facility_id
        WHERE IFNULL(reason_for_vl_testing, 0)  != 9999";

    [$start_date, $end_date] = DateUtility::convertDateRange($_POST['sampleCollectionDate'] ?? '');
    [$sTestDate, $eTestDate] = DateUtility::convertDateRange($_POST['sampleTestDate'] ?? '');

    $sWhere = [];
    if (!empty($_POST['sampleCollectionDate'])) {
        if (trim((string) $start_date) === trim((string) $end_date)) {
            $sWhere[] = ' DATE(vl.sample_collection_date) = "' . $start_date . '"';
        } else {
            $sWhere[] =  ' DATE(vl.sample_collection_date) >= "' . $start_date . '" AND DATE(vl.sample_collection_date) <= "' . $end_date . '"';
        }
    }
    if (isset($_POST['district']) && trim((string) $_POST['district']) !== '') {
        $sWhere[] = " f.facility_district LIKE '%" . $db->escapeLike($_POST['district']) . "%' ";
    }
    if (isset($_POST['state']) && trim((string) $_POST['state']) !== '') {
        $sWhere[] = " f.facility_state LIKE '%" . $db->escapeLike($_POST['state']) . "%' ";
    }
    if (isset($_POST['facilityName']) && trim((string) $_POST['facilityName']) !== '') {
        $sWhere[] =  ' f.facility_id = "' . $db->escape((string) $_POST['facilityName']) . '"';
    }
    // A cancelled sample was called off before testing, so it is not
    // work this report should count.
    $sWhere[] = SampleCountUtility::countableWhere('vl');
    $sQuery = $sQuery . ' AND ' . implode(" AND ", $sWhere) . ' AND vl.result!=""';

    $sResult = $db->rawQuery($sQuery);

    //question two query
    //first check empty results
    $sWhere = [];
    $checkEmptyResultQuery = "SELECT vl.sample_collection_date,
                                vl.sample_tested_datetime,
                                f.facility_name,
                                f.facility_code,
                                f.facility_state,
                                f.facility_district
                                FROM form_vl as vl
                                JOIN facility_details as f ON vl.facility_id=f.facility_id";
    if (!empty($_POST['sampleCollectionDate'])) {
        if (trim((string) $start_date) === trim((string) $end_date)) {
            $sWhere[] = ' DATE(vl.sample_collection_date) = "' . $start_date . '"';
        } else {
            $sWhere[] = ' DATE(vl.sample_collection_date) >= "' . $start_date . '" AND DATE(vl.sample_collection_date) <= "' . $end_date . '"';
        }
    }
    if (isset($_POST['district']) && trim((string) $_POST['district']) !== '') {
        $sWhere[] = " f.facility_district LIKE '%" . $db->escapeLike($_POST['district']) . "%' ";
    }
    if (isset($_POST['state']) && trim((string) $_POST['state']) !== '') {
        $sWhere[] =  " f.facility_state LIKE '%" . $db->escapeLike($_POST['state']) . "%' ";
    }
    if (isset($_POST['facilityName']) && trim((string) $_POST['facilityName']) !== '') {
        $sWhere[] = ' f.facility_id = "' . $db->escape((string) $_POST['facilityName']) . '"';
    }

    // A cancelled sample was called off before testing, so it is not
    // work this report should count.
    $sWhere[] = SampleCountUtility::countableWhere('vl');
    $checkEmptyResultQuery = $checkEmptyResultQuery . ' WHERE ' . implode(" AND ", $sWhere) . ' AND vl.sample_tested_datetime IS NULL AND vl.specimen_type!="" AND vl.sample_collection_date < "' . DateUtility::getCurrentDateTime() . '" - INTERVAL 1 MONTH AND IFNULL(reason_for_vl_testing, 0)  != 9999';
    $checkEmptyResult = $db->rawQuery($checkEmptyResultQuery);
    //get all sample type
    $sampleType = "Select * from r_vl_sample_type where status='active'";
    $sampleTypeResult = $db->rawQuery($sampleType);
    if (count($checkEmptyResult) > 0) {
        foreach ($sampleTypeResult as $sample) {
            $sWhere = [];
            $checkEmptyResultSampleQuery = 'SELECT vl.sample_collection_date,
                                                vl.sample_tested_datetime,
                                                COUNT(vl_sample_id) as total,
                                                f.facility_name,
                                                f.facility_code,
                                                f.facility_state,
                                                f.facility_district
                                                FROM form_vl as vl
                                                JOIN facility_details as f ON vl.facility_id=f.facility_id
                                                WHERE vl.sample_tested_datetime IS NULL
                                                AND vl.specimen_type="' . $sample['sample_id'] . '"
                                                AND vl.sample_collection_date < "' . DateUtility::getCurrentDateTime() . '" - INTERVAL 1 MONTH
                                                AND IFNULL(reason_for_vl_testing, 0)  != 9999';

            if (!empty($_POST['sampleCollectionDate'])) {
                if (trim((string) $start_date) === trim((string) $end_date)) {
                    $sWhere[] = '  DATE(vl.sample_collection_date) = "' . $start_date . '"';
                } else {
                    $sWhere[] =  '  DATE(vl.sample_collection_date) >= "' . $start_date . '" AND DATE(vl.sample_collection_date) <= "' . $end_date . '"';
                }
            }

            if (isset($_POST['district']) && trim((string) $_POST['district']) !== '') {
                $sWhere[] =  "  f.facility_district LIKE '%" . $db->escapeLike($_POST['district']) . "%' ";
            }
            if (isset($_POST['state']) && trim((string) $_POST['state']) !== '') {
                $sWhere[] = " f.facility_state LIKE '%" . $db->escapeLike($_POST['state']) . "%' ";
            }
            if (isset($_POST['facilityName']) && trim((string) $_POST['facilityName']) !== '') {
                $sWhere[] = ' f.facility_id = "' . $db->escape((string) $_POST['facilityName']) . '"';
            }
            // A cancelled sample was called off before testing, so it is not
            // work this report should count.
            $sWhere[] = SampleCountUtility::countableWhere('vl');
            $checkEmptyResultSampleQuery .= ' AND ' . implode(" AND ", $sWhere);
            $checkEmptySampleResult[$sample['sample_name']] = $db->rawQuery($checkEmptyResultSampleQuery);
        }
    }

    //question three

    $s_c_date = explode(" to ", (string) ($_POST['sampleCollectionDate'] ?? ''));
    $start_date = DateUtility::isoDateFormat(trim($s_c_date[0]));
    $end_date = DateUtility::isoDateFormat(trim($s_c_date[1] ?? $s_c_date[0]));
    $startMonth = date("Y-m", strtotime((string) $start_date));
    $endMonth = date("Y-m", strtotime((string) $end_date));
    $start = $month = strtotime($startMonth);
    $end = strtotime($endMonth);
    $i = 0;
    $j = 0;
    $avgResult = [];
    while ($month <= $end) {
        $sWhere = [];
        $mnth = date('m', $month);
        $year = date('Y', $month);
        $dFormat = date("M-Y", $month);
        $checkResultAvgQuery = "SELECT vl.sample_collection_date,
                                vl.sample_tested_datetime,
                                f.facility_name,
                                f.facility_code,
                                f.facility_state,
                                f.facility_district,
                                DATEDIFF(sample_tested_datetime,sample_collection_date) as diff
                                FROM form_vl as vl
                                JOIN facility_details as f ON vl.facility_id=f.facility_id
                                WHERE vl.sample_tested_datetime IS NOT NULL
                                AND MONTH(sample_collection_date)='$mnth'
                                AND YEAR(sample_collection_date)='$year'";
        if (!empty($_POST['sampleCollectionDate'])) {
            if (trim((string) $start_date) === trim((string) $end_date)) {
                $sWhere[] = '  DATE(vl.sample_collection_date) = "' . $start_date . '"';
            } else {
                $sWhere[] =  '  DATE(vl.sample_collection_date) >= "' . $start_date . '" AND DATE(vl.sample_collection_date) <= "' . $end_date . '"';
            }
        }
        if (isset($_POST['sampleTestDate']) && trim((string) $_POST['sampleTestDate']) !== '') {
            if (trim((string) $sTestDate) === trim((string) $eTestDate)) {
                $sWhere[] =  '  DATE(vl.sample_tested_datetime) = "' . $sTestDate . '"';
            } else {
                $sWhere[] =  '  DATE(vl.sample_tested_datetime) >= "' . $sTestDate . '" AND DATE(vl.sample_tested_datetime) <= "' . $eTestDate . '"';
            }
        }
        if (isset($_POST['district']) && trim((string) $_POST['district']) !== '') {
            $sWhere[] = "  f.facility_district LIKE '%" . $db->escapeLike($_POST['district']) . "%' ";
        }
        if (isset($_POST['state']) && trim((string) $_POST['state']) !== '') {
            $sWhere[] =  "  f.facility_state LIKE '%" . $db->escapeLike($_POST['state']) . "%' ";
        }

        if (isset($_POST['facilityName']) && trim((string) $_POST['facilityName']) !== '') {
            $sWhere[] =  '  f.facility_id = "' . $db->escape((string) $_POST['facilityName']) . '"';
        }
        // A cancelled sample was called off before testing, so it is not
        // work this report should count.
        $sWhere[] = SampleCountUtility::countableWhere('vl');
        $checkResultAvgQuery = $checkResultAvgQuery . ' AND ' . implode(" AND ", $sWhere) . ' AND vl.result=""';
        $checkResultAvgResult = $db->rawQuery($checkResultAvgQuery);
        if (count($checkResultAvgResult) > 0) {
            $total = 0;
            foreach ($checkResultAvgResult as $data) {
                $total += $data['diff'];
            }
            $avgResult[$j] = round($total / count($checkResultAvgResult));
        }
        $month = strtotime("+1 month", $month);
        $i++;
        $j++;
    }
    //total avg
    $totalAvg = 0;
    $totalAvg = count($avgResult) == 0 ? 0 : round(array_sum($avgResult) / count($avgResult));

    $startMonth = date("M-Y", strtotime((string) $start_date));
    $endMonth = date("M-Y", strtotime((string) $end_date));

    // The report is laid out on a grid first and then written top to bottom.
    // Its merged blocks span several rows, so a cell's borders are only known
    // once every block around it is placed.
    $grid = [];   // [row][column] => ['value' => ..., 'style' => [...]]
    $merges = []; // [firstColumn, firstRow, lastColumn, lastRow], columns from 0

    $parseRange = static function (string $range): array {
        [$from, $to] = array_pad(explode(':', $range), 2, null);
        $to ??= $from;
        preg_match('/^([A-M])(\d+)$/', $from, $a);
        preg_match('/^([A-M])(\d+)$/', $to, $b);
        return [ord($a[1]) - 65, (int) $a[2], ord($b[1]) - 65, (int) $b[2]];
    };

    // Sets a cell's value, or merges a range and puts the value in its first cell
    $block = static function (string $range, int|string|null $value = null) use (&$grid, &$merges, $parseRange): void {
        [$c1, $r1, $c2, $r2] = $parseRange($range);
        if ($c1 !== $c2 || $r1 !== $r2) {
            $merges[] = [$c1, $r1, $c2, $r2];
        }
        if ($value !== null) {
            $grid[$r1][$c1]['value'] = $value;
        }
    };

    // Adds style properties to every cell in the range
    $style = static function (string $range, array $properties) use (&$grid, $parseRange): void {
        [$c1, $r1, $c2, $r2] = $parseRange($range);
        for ($r = $r1; $r <= $r2; $r++) {
            for ($c = $c1; $c <= $c2; $c++) {
                $grid[$r][$c]['style'] = array_merge($grid[$r][$c]['style'] ?? [], $properties);
            }
        }
    };

    // Thick line under the last row of A..M
    $underline = static function (int $row) use ($style): void {
        $style("A$row:M$row", ['bottom' => BorderWidth::THICK]);
    };

    // Border around the outside edge of a range
    $outline = static function (string $range, BorderWidth $width) use ($style, $parseRange): void {
        [$c1, $r1, $c2, $r2] = $parseRange($range);
        [$first, $last] = [chr(65 + $c1), chr(65 + $c2)];
        $style("$first$r1:$last$r1", ['top' => $width]);
        $style("$first$r2:$last$r2", ['bottom' => $width]);
        $style("$first$r1:$first$r2", ['left' => $width]);
        $style("$last$r1:$last$r2", ['right' => $width]);
    };

    $titleStyle = ['bold' => true, 'size' => 13, 'color' => 'FFFFFF', 'h' => CellAlignment::CENTER, 'fill' => '5C5C5C'];
    $questionStyle = ['fill' => 'A9A9A9'];
    $subHeadingStyle = ['fill' => 'A9A9A9', 'h' => CellAlignment::LEFT];

    if (($_POST['fyName'] ?? '') === '-- Select --') {
        $_POST['fyName'] = '';
    }
    $atomcolumns = '';
    $atomcolumns .= "Region/Province/State:" . ($_POST['state'] ?? '') . "&nbsp;&nbsp;&nbsp;";
    $atomcolumns .= "District/County:" . ($_POST['district'] ?? '') . "\n\n";
    $atomcolumns .= "Laboratory Name:" . ($_POST['fyName'] ?? '') . "\n\n";
    $atomcolumns .= "Reporting POC Name:________________";
    $atomcolumns .= "Title:________________";
    $atomcolumns .= "Email:________________\n\n";
    $atomcolumns .= "Date(MM/DD/YYYY): " . date('M/d/Y') . "&nbsp;&nbsp;&nbsp;";
    $atomcolumns .= "Reporting Quarter: " . $startMonth . " to " . $endMonth;

    $block('A1:M2', 'Viral Load Quarterly Monitoring Tool ');
    $style('A1', $titleStyle);
    $block('A3:M10', html_entity_decode($atomcolumns, ENT_QUOTES, 'UTF-8'));
    $style('A3', ['h' => CellAlignment::LEFT, 'v' => CellVerticalAlignment::CENTER]);
    $outline('A3', BorderWidth::THIN);

    $block('A11:A12');
    $block('B11:F12', 'Question');
    $block('G11:I12', 'Value');
    $block('J11:M12', 'Comments');
    $style('B11', $titleStyle);
    $style('G11', $titleStyle);
    $style('J11', $titleStyle);
    $underline(12);

    // Q1
    $count = static fn(string $key): int => (int) ($sResult[0][$key] ?? 0);
    $block('A13:A14', 'Q1');
    $block('B13:F14', 'Number Of Viral Load tests reported by the laboratory during the current quarter');
    $block('G13:I14', count($rResult));
    $block('J13:M14');
    $block('A15:A16', 'Q1.1');
    $block('B15:F17', 'Of the number of Viral Load test results reported by lab,how many were: ');
    $block('G15:G16', 'Suppressed < 1000 copies/mL ');
    $block('H15:I16', 'Suppressed Failure >= 1000 copies/mL ');
    $block('G17', $count('suppressed'));
    $block('H17:I17', $count('NotSuppressed'));
    $block('J15:M17');
    $style('G15:H15', $questionStyle);
    $block('A18', 'Q1.2');
    $block('B18:M18', 'Sex');
    $block('A23', 'Q1.3');
    $block('B23:M23', 'Age');

    // row => [question, label, suppressed column, not suppressed column]
    $breakdown = [
        19 => [null, 'Male', 'ltMale1000', 'gtMale1000'],
        20 => [null, 'Female', 'ltFemale1000', 'gtFemale1000'],
        21 => [null, 'Not Specified', 'ltNotSpecified1000', 'gtNotSpecified1000'],
        22 => [null, 'Total', 'ltTotalGender1000', 'gtTotalGender1000'],
        24 => [null, '<1', 'ltAgeOne1000', 'gtAgeOne1000'],
        25 => [null, '1-9', 'ltAgeOnetoNine1000', 'gtAgeOnetoNine1000'],
        26 => [null, '10-14', 'ltAgeTentoFourteen1000', 'gtAgeTentoFourteen1000'],
        27 => [null, '<15(Subtotal)', 'ltAgeTotalFifteen1000', 'gtAgeTotalFifteen1000'],
        28 => [null, '15-19', 'ltAgeFifteentoNineteen1000', 'gtAgeFifteentoNineteen1000'],
        29 => [null, '20-24', 'ltAgeTwentytoTwentyFour1000', 'gtAgeTwentytoTwentyFour1000'],
        30 => [null, '15-24', 'ltAgeFifteentoTwentyFour1000', 'gtAgeFifteentoTwentyFour1000'],
        31 => [null, '25+', 'ltAgetwentyFive1000', 'gtAgetwentyFive1000'],
        32 => [null, 'Not Specified', 'ltAgeNotSpecified1000', 'gtAgeNotSpecified1000'],
        33 => [null, 'Total', 'ltAgeTotal1000', 'gtAgeTotal1000'],
        34 => ['Q1.4', 'Pregnant Women', 'ltPatientPregnant1000', 'gtPatientPregnant1000'],
        35 => ['Q1.5', 'Women that are breastfeeding', 'ltPatientBreastFeeding1000', 'gtPatientBreastFeeding1000'],
    ];
    foreach ($breakdown as $row => [$question, $label, $suppressed, $notSuppressed]) {
        if ($question !== null) {
            $block("A$row", $question);
        }
        $block("B$row:F$row", $label);
        $block("G$row", $count($suppressed));
        $block("H$row:I$row", $count($notSuppressed));
        $block("J$row:M$row");
    }
    $underline(35);

    // Q2
    $block('A36', 'Q2');
    $block('B36:F37', 'Is there a backlog for viral load testing? (greater than 1 month testing volume) Choose from list');
    $block('G36:I37', count($checkEmptyResult));
    $block('J36:M37', 'Reasons:');
    $s = 38;
    $ss = 39;
    $q2End = 37;
    if (count($checkEmptyResult) > 0) {
        foreach ($sampleTypeResult as $sampleName) {
            $backlog = (int) ($checkEmptySampleResult[$sampleName['sample_name']][0]['total'] ?? 0);
            if ($backlog !== 0) {
                $block("B$s:F$ss", 'If yes,which type of samples?');
                $block("G$s:I$ss", (string) $sampleName['sample_name']);
                $block("J$s:M$ss");
                $c = $s + 2;
                $cc = $ss + 1;
                $block("A$c:A$cc", 'Q2.1');
                $block("B$c:F$cc", 'If yes,how many samples?');
                $block("G$c:I$cc", $backlog);
                $block("J$c:M$cc");
                $q2End = $cc;
                $s = $c + 1;
                $ss = $cc + 2;
            }
        }
    }
    $underline($q2End);

    // Q3 to Q6: one question per two-row block
    $row = $s;
    $addQuestion = static function (?string $question, string $text, int|string|null $value = null, ?string $comment = null) use (&$row, $block): int {
        $last = $row + 1;
        if ($question !== null) {
            $block("A$row:A$last", $question);
        }
        $block("B$row:F$last", $text);
        $block("G$row:I$last", $value);
        $block("J$row:M$last", $comment);
        $row += 2;
        return $last;
    };
    $underline($addQuestion('Q3', 'What is the average monthly/quarterly TAT? (sample collection to lab result release)', (int) $totalAvg));

    $addQuestion('Q4', 'Are there planned procurements within this fiscal year? Choose from list');
    $addQuestion('Q4.1', 'If yes, please list:');
    $addQuestion(null, 'Platform Type:');
    $addQuestion(null, 'Quantity:');
    $underline($addQuestion(null, 'Planned location of placement:'));

    $addQuestion('Q5', 'Number of Early Infant Diagnosis tests reported by the lab:');
    $underline($addQuestion('Q5.1', 'Number of Early Infant Diagnosis tests with a positive result:'));

    $addQuestion('Q6', 'Is there a backlog for Early Infant Diagnosis testing (greater than 1 month testing volume)? Choose from list', null, 'Reasons:');
    $underline($addQuestion('Q6.1', 'If yes, how many samples?'));

    // Q7: one row per month of the collection period
    $q7Start = $row;
    if (!empty($_POST['sampleCollectionDate'])) {
        $month = strtotime(date('Y-m', strtotime((string) $start_date)));
        $end = strtotime(date('Y-m', strtotime((string) $end_date)));
        while ($month <= $end) {
            $mnth = date('m', $month);
            $year = date('Y', $month);
            $invalidResultQuery = "SELECT
                    SUM(CASE WHEN (result!='' AND result!='Target Not Detected') AND (result > 10000000 OR result < 20) THEN 1 ELSE 0 END) AS invalidTotal
                    FROM form_vl as vl
                    JOIN facility_details as f ON vl.facility_id=f.facility_id
                    WHERE MONTH(sample_collection_date)='$mnth'
                    AND YEAR(sample_collection_date)='$year'
                    AND " . SampleCountUtility::countableWhere('vl');
            $invalidResult = $db->rawQuery($invalidResultQuery);

            $block("G$row", date('M-Y', $month));
            $block("H$row:I$row", (int) ($invalidResult[0]['invalidTotal'] ?? 0));
            $block("J$row:M$row");
            $row++;
            $month = strtotime('+1 month', $month);
        }
    }
    $q7End = max($row, $q7Start + 1) - 1;
    $block("A$q7Start:A$q7End", 'Q7');
    $block("B$q7Start:F$q7End", 'Number of invalid VL and EID tests reported by the lab per month:');
    $underline($q7End);

    // Q8: six instrument rows, then the reason
    $q8Start = $q7End + 1;
    $q8End = $q8Start + 5;
    $block("A$q8Start:A$q8End", 'Q8');
    $block("B$q8Start:F$q8End", 'Duration of equipment breakdown in number of days (provide per instrument type):');
    for ($r = $q8Start; $r <= $q8End; $r++) {
        $block("H$r:I$r");
        $block("J$r:M$r");
    }
    $block("G$q8Start", 'Instrument');
    $block("H$q8Start", 'Days Down');
    $style("G$q8Start:M$q8Start", $questionStyle);
    $row = $q8End + 1;
    $q81Start = $row;
    $lastRow = $addQuestion('Q8.1', 'Reason for instrument breakdown: ');

    $style("A13:F$q81Start", $questionStyle);
    foreach (['B18', 'B23', 'B33', 'B34'] as $cell) {
        $style($cell, $subHeadingStyle);
    }
    $style("A$q7Start:F$q7Start", ['v' => CellVerticalAlignment::CENTER]);
    $style("A$q8Start:F$q8Start", ['v' => CellVerticalAlignment::CENTER]);
    $outline("A11:M$lastRow", BorderWidth::THICK);

    // Turn each cell's style properties into an OpenSpout style, once per combination
    $styles = [];
    $toStyle = static function (array $properties) use (&$styles): Style {
        $key = serialize($properties);
        if (isset($styles[$key])) {
            return $styles[$key];
        }
        $cellStyle = (new Style())
            ->withFontName('Calibri')
            ->withFontSize($properties['size'] ?? 11)
            ->withFontBold($properties['bold'] ?? false)
            ->withFontColor($properties['color'] ?? Color::BLACK)
            ->withCellAlignment($properties['h'] ?? null)
            ->withCellVerticalAlignment($properties['v'] ?? null);
        if (isset($properties['fill'])) {
            $cellStyle = $cellStyle->withBackgroundColor($properties['fill']);
        }
        $parts = [];
        foreach (['top' => BorderName::TOP, 'right' => BorderName::RIGHT, 'bottom' => BorderName::BOTTOM, 'left' => BorderName::LEFT] as $side => $name) {
            if (isset($properties[$side])) {
                $parts[] = new BorderPart($name, Color::BLACK, $properties[$side], BorderStyle::SOLID);
            }
        }
        if ($parts !== []) {
            $cellStyle = $cellStyle->withBorder(new Border(...$parts));
        }
        return $styles[$key] = $cellStyle;
    };

    $options = new Options(new Style(fontSize: 11, fontName: 'Calibri'));
    foreach ($merges as [$c1, $r1, $c2, $r2]) {
        $options->mergeCells($c1, $r1, $c2, $r2);
    }

    $filename = 'InteLIS-Quarterly-Monitoring-Report-' . date('d-M-Y-H-i-s') . '.xlsx';
    $writer = new Writer($options);
    $writer->openToFile(TEMP_PATH . DIRECTORY_SEPARATOR . $filename);
    for ($r = 1; $r <= $lastRow; $r++) {
        $cells = [];
        $hasValue = false;
        for ($c = 0; $c <= 12; $c++) {
            $cell = $grid[$r][$c] ?? [];
            $hasValue = $hasValue || isset($cell['value']);
            $cells[] = Cell::fromValue($cell['value'] ?? null, isset($cell['style']) ? $toStyle($cell['style']) : null);
        }
        // OpenSpout skips a row whose cells are all empty, and its fills and
        // borders with it. An empty string in the first styled cell keeps it.
        if (!$hasValue) {
            foreach ($cells as $c => $cell) {
                if ($cell->style !== null) {
                    $cells[$c] = new StringCell('', $cell->style);
                    break;
                }
            }
        }
        $writer->addRow(new Row($cells));
    }
    $writer->close();
    echo _downloadToken($filename);
}
