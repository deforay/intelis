<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}




use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Utilities\MiscUtility;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

/** @var DatabaseService $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

//system config
$systemConfigQuery = "SELECT * from system_config";
$systemConfigResult = $db->query($systemConfigQuery);
$sarr = [];
// now we create an associative array so that we can easily create view variables
for ($i = 0; $i < sizeof($systemConfigResult); $i++) {
    $sarr[$systemConfigResult[$i]['name']] = $systemConfigResult[$i]['value'];
}

$arr = $general->getGlobalConfig();

$delimiter = $arr['default_csv_delimiter'] ?? ',';
$enclosure = $arr['default_csv_enclosure'] ?? '"';


if (isset($_SESSION['resultNotAvailable']) && trim((string) $_SESSION['resultNotAvailable']) != "") {

    $output = [];

    $headings = array('Sample ID', 'Remote Sample ID', "Facility Name", "Patient Id.", "Patient Name", "Sample Collection Date", "Lab Name", "Sample Status");
    if ($sarr['sc_user_type'] == 'standalone') {
        if (($key = array_search("Remote Sample ID", $headings)) !== false) {
            unset($headings[$key]);
        }
    }



    foreach ($db->rawQueryGenerator($_SESSION['resultNotAvailable']) as $aRow) {
        $row = [];
        //sample collecion date
        $sampleCollectionDate = '';
        if ($aRow['sample_collection_date'] != null && trim((string) $aRow['sample_collection_date']) != '' && $aRow['sample_collection_date'] != '0000-00-00 00:00:00') {
            $expStr = explode(" ", (string) $aRow['sample_collection_date']);
            $sampleCollectionDate = date("d-m-Y", strtotime($expStr[0]));
        }
        if ($aRow['remote_sample'] == 'yes') {
            $decrypt = 'remote_sample_code';
        } else {
            $decrypt = 'sample_code';
        }
        $patientFname = ($general->crypto('doNothing', $aRow['patient_first_name'], $aRow[$decrypt]));
        $row[] = $aRow['sample_code'];
        if ($_SESSION['instanceType'] != 'standalone') {
            $row[] = $aRow['remote_sample_code'];
        }
        if (!empty($aRow['is_encrypted']) && $aRow['is_encrypted'] == 'yes') {
            $key = (string) $general->getGlobalConfig('key');
            $aRow['patient_id'] = $general->crypto('decrypt', $aRow['patient_id'], $key);
            $aRow['patient_name'] = $general->crypto('decrypt', $aRow['patient_name'], $key);
        }
        $row[] = $aRow['facility_name'];
        $row[] = $aRow['patient_id'];
        $row[] = ($aRow['patient_name']);
        $row[] = $sampleCollectionDate;
        $row[] = ($aRow['labName']);
        $row[] = ($aRow['status_name']);
        $output[] = $row;
    }

    if (isset($_SESSION['resultNotAvailableCount']) && $_SESSION['resultNotAvailableCount'] > 75000) {
        $fileName = TEMP_PATH . DIRECTORY_SEPARATOR . 'VLSM-COVID19-Rejected-Data-report' . date('d-M-Y-H-i-s') . '.csv';
        $fileName = MiscUtility::generateCsv($headings, $output, $fileName, $delimiter, $enclosure);
        // we dont need the $output variable anymore
        unset($output);
        echo base64_encode((string) $fileName);
    } else {
        $colNo = 1;

        $excel = new Spreadsheet();
        $sheet = $excel->getActiveSheet();

        $styleArray = array(
            'font' => array(
                'bold' => true,
                'size' => '13',
            ),
            'alignment' => array(
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ),
            'borders' => array(
                'outline' => array(
                    'style' => Border::BORDER_THIN,
                ),
            ),
        );


        $sheet->mergeCells('A1:AE1');
        $nameValue = '';
        foreach ($_POST as $key => $value) {
            if (trim((string) $value) != '' && trim((string) $value) != '-- Select --') {
                $nameValue .= str_replace("_", " ", $key) . " : " . $value . "&nbsp;&nbsp;";
            }
        }
        $sheet->getCell(Coordinate::stringFromColumnIndex($colNo) . '1')
            ->setValueExplicit(html_entity_decode($nameValue));

        $sheet->getStyle('A3:A3')->applyFromArray($styleArray);
        $sheet->getStyle('B3:B3')->applyFromArray($styleArray);
        $sheet->getStyle('C3:C3')->applyFromArray($styleArray);
        $sheet->getStyle('D3:D3')->applyFromArray($styleArray);
        $sheet->getStyle('E3:E3')->applyFromArray($styleArray);
        $sheet->getStyle('F3:F3')->applyFromArray($styleArray);
        $sheet->getStyle('G3:G3')->applyFromArray($styleArray);
        if ($_SESSION['instanceType'] != 'standalone') {
            $sheet->getStyle('H3:H3')->applyFromArray($styleArray);
        }
        $sheet->fromArray($headings, null, 'A3');

        foreach ($output as $rowNo => $rowData) {
            $rRowCount = $rowNo + 4;
            $sheet->fromArray($rowData, null, 'A' . $rRowCount);
        }
        $writer = IOFactory::createWriter($excel, IOFactory::READER_XLSX);
        $filename = TEMP_PATH . DIRECTORY_SEPARATOR . 'VLSM-Covid-19-Results-Not-Available-Report-' . date('d-M-Y-H-i-s') . '.xlsx';
        $writer->save($filename);
        echo base64_encode($filename);
    }
}
