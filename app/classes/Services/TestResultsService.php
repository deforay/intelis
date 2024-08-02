<?php

namespace App\Services;

use DateTimeImmutable;
use App\Utilities\DateUtility;
use App\Services\DatabaseService;

final class TestResultsService
{
    protected ?DatabaseService $db;

    public function __construct(?DatabaseService $db)
    {
        $this->db = $db;
    }
    public function clearPreviousImportsByUser($userId = null, $module = null)
    {
        $userId = $userId ?? $_SESSION['userId'] ?? null;
        $this->db->where('imported_by', $userId);
        if (!empty($module)) {
            $this->db->where('module', $module);
        }
        return $this->db->delete('temp_sample_import');
    }

    public function getMaxIDForHoldingSamples()
    {
        $bquery = "SELECT IFNULL(MAX(import_batch_tracking), 0) + 1 AS maxId FROM `hold_sample_import`";
        $result = $this->db->rawQueryOne($bquery);
        return $result['maxId'];
    }


    // This function removes control characters from the strings in the CSV file.
    // https://en.wikipedia.org/wiki/Control_character#ASCII_control_characters
    // Also checks UTF-8 encoding and converts if needed
    public function removeCntrlCharsAndEncode($inputString, $encodeToUTF8 = true): string
    {
        return once(function () use ($inputString, $encodeToUTF8) {
            $inputString = preg_replace('/[[:cntrl:]]/', '', (string) $inputString);
            if ($encodeToUTF8 === true && mb_detect_encoding($inputString, 'UTF-8', true) === false) {
                $inputString = mb_convert_encoding($inputString, 'UTF-8');
            }
            return $inputString;
        });
    }

    public function abbottTestingDateFormatter($testDate, $testDateFormat, $interpretFormat = true): ?array
    {

        if (empty($testDate) || empty($testDateFormat)) {
            return null;
        }

        $testDateFormat = trim((string) $testDateFormat);

        if ($interpretFormat === true) {
            $find = ['am', 'pm', 'dd', 'mm', 'yyyy', 'yy'];
            $replace = ['', '', 'd', 'm', 'Y', 'y'];
            $testDateFormat = trim(str_ireplace($find, $replace, strtolower($testDateFormat)));
        }

        $testingDateFormat = substr_count((string) $testDate, ':') === 1 ? "$testDateFormat h:i" : "$testDateFormat H:i:s";

        if (stripos((string) $testDate, 'am') !== false || stripos((string) $testDate, 'pm') !== false) {
            $testingDateFormat .= ' A';
        }

        $timestamp = DateTimeImmutable::createFromFormat("!" . $testingDateFormat, $testDate);
        $testingDate = $timestamp ? $timestamp->format('Y-m-d H:i') : null;

        return [
            'testingDate' => $testingDate,
            'dateFormat' => $testDateFormat,
        ];
    }

    public function resultImportStats($numberOfResults, $importMode, $importedBy)
    {

        $data = [
            'no_of_results_imported' => $numberOfResults,
            'imported_on' => DateUtility::getCurrentDateTime(),
            'import_mode' => $importMode,
            'imported_by' => $importedBy
        ];

        $this->db->insert('result_import_stats', $data);
    }

    public function updateEmailTestResultsInfo($testType,$emailInfo){
        $testName = TestsService::getTestTypes();
        $tableName = $testName[$testType]['tableName'];
        $primaryKey = $testName[$testType]['primaryKey'];
        $this->db->where("$primaryKey IN (" . $emailInfo['samples'] . ")");
        $result = $this->db->get($tableName,NULL,"result_dispatched_datetime,form_attributes,is_result_mail_sent");
        foreach($result as $val){
            if(!empty($val['form_attributes'])){
                    $formAttributes = json_decode($val['form_attributes']);
                    $formAttributes->email_sent_to = $emailInfo['to_mail'];
            }
            else{
                $formAttributes = array('email_sent_to'=>$emailInfo['to_mail']);
            }

            $data = array(
                'is_result_mail_sent' => 'yes',
                'form_attributes' => json_encode($formAttributes),
            );

            if($val['result_dispatched_datetime'] == ""){
                $data['result_dispatched_datetime'] = DateUtility::getCurrentDateTime();
            }

            $this->db->update($tableName,$data);
        }

    }
}
