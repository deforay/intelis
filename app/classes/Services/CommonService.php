<?php

/**
 * General functions
 *
 * @author Amit
 */

namespace App\Services;

use MysqliDb;
use Exception;
use ZipArchive;
use TCPDFBarcode;
use Ramsey\Uuid\Uuid;
use App\Utilities\DateUtility;
use App\Exceptions\SystemException;
use App\Registries\ContainerRegistry;


class CommonService
{

    protected ?MysqliDb $db = null;


    public function __construct($db = null)
    {
        $this->db = $db ?? ContainerRegistry::get('db');
    }

    public function generateRandomString($length = 32)
    {
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $number = random_int(0, 36);
            $character = base_convert($number, 10, 36);
            $randomString .= $character;
        }
        return $randomString;
    }

    public function escape($inputArray, $db = null)
    {
        $db = !empty($db) ? $db : $this->db;
        $escapedArray = [];
        foreach ($inputArray as $key => $value) {
            $escapedArray[$key] = $db->escape($value);
        }
        return $escapedArray;
    }

    // Returns a UUID format string
    public function generateUUID($attachExtraString = true)
    {
        $uuid = (Uuid::uuid4())->toString();

        if ($attachExtraString === true) {
            $uuid .= "-" . $this->generateRandomString(6);
        }
        return $uuid;
    }

    public function getClientIpAddress()
    {
        $ipAddress = '';

        if (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ipAddress = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ipAddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED'])) {
            $ipAddress = $_SERVER['HTTP_X_FORWARDED'];
        } elseif (isset($_SERVER['HTTP_FORWARDED_FOR'])) {
            $ipAddress = $_SERVER['HTTP_FORWARDED_FOR'];
        } elseif (isset($_SERVER['HTTP_FORWARDED'])) {
            $ipAddress = $_SERVER['HTTP_FORWARDED'];
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ipAddress = $_SERVER['REMOTE_ADDR'];
        }

        return $ipAddress;
    }


    //This will return a hex token
    public function generateToken($length = 32)
    {
        try {
            // Ensure $length is always even
            if ($length % 2 != 0) {
                $length++;
            }

            return bin2hex(random_bytes($length / 2));
        } catch (Exception $e) {
            throw new SystemException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function removeDirectory($dirname)
    {
        // Sanity check
        if (!file_exists($dirname)) {
            return false;
        }

        // Simple delete for a file
        if (is_file($dirname) || is_link($dirname)) {
            return unlink($dirname);
        }

        // Loop through the folder
        $dir = dir($dirname);
        while (false !== ($entry = $dir->read())) {
            // Skip pointers
            if ($entry == '.' || $entry == '..') {
                continue;
            }

            // Recurse
            $this->removeDirectory($dirname . DIRECTORY_SEPARATOR . $entry);
        }

        // Clean up
        $dir->close();
        return rmdir($dirname);
    }

    // get data from the system_config table from database
    public function getSystemConfig($name = null)
    {
        if ($this->db == null) {
            return false;
        }

        return once(function () use ($name) {
            if (!empty($name)) {
                $this->db->where('name', $name);
            }

            $systemConfigResult = $this->db->get('system_config');

            $sarr = [];
            // now we create an associative array so that we can easily create view variables
            for ($i = 0; $i < sizeof($systemConfigResult); $i++) {
                $sarr[$systemConfigResult[$i]['name']] = $systemConfigResult[$i]['value'];
            }

            if (empty($name)) {
                return $sarr;
            } else {
                return $sarr[$name] ?? null;
            }
        });
    }

    // get data from the global_config table from database
    public function getGlobalConfig($name = null)
    {

        if ($this->db == null) {
            return false;
        }
        return once(function () use ($name) {

            if (!empty($name)) {
                $this->db->where('name', $name);
                return $this->db->getValue("global_config", "value");
            } else {
                $garr = [];
                $globalConfigResult = $this->db->get('global_config');
                // now we create an associative array so that we can easily create view variables
                for ($i = 0; $i < sizeof($globalConfigResult); $i++) {
                    $garr[$globalConfigResult[$i]['name']] = $globalConfigResult[$i]['value'];
                }

                return $garr;
            }
        });
    }

    public function fetchDataFromTable($tableName = null, $condition = null, $fieldName = null)
    {
        if ($this->db == null || empty($tableName)) {
            return false;
        }

        $fieldName = ($fieldName != null) ? $fieldName : '*';

        $configQuery = "SELECT $fieldName FROM $tableName";

        if ($condition != null) {
            $configQuery .= " WHERE $condition ";
        }

        if ($tableName == "testing_labs") {
            $configQuery = "SELECT test_type, facility_id, updated_datetime, monthly_target, suppressed_monthly_target from $tableName";
            if ($condition != null) {
                $configQuery .= " WHERE $condition ";
            }
        }
        return $this->db->query($configQuery);
    }

    // checking if the provided field list has any empty or null values
    public function checkMandatoryFields($field)
    {
        foreach ($field as $chkField) {
            if (empty(trim($chkField))) {
                return true;
            }
        }

        return false;
    }

    public static function encrypt($message, $key)
    {
        $nonce = random_bytes(
            SODIUM_CRYPTO_SECRETBOX_NONCEBYTES
        );

        $cipher = sodium_bin2base64(
            $nonce .
                sodium_crypto_secretbox(
                    $message,
                    $nonce,
                    $key
                ),
            SODIUM_BASE64_VARIANT_URLSAFE
        );
        sodium_memzero($message);
        sodium_memzero($key);
        return $cipher;
    }

    public static function decrypt($encrypted, $key)
    {
        $decoded = sodium_base642bin($encrypted, SODIUM_BASE64_VARIANT_URLSAFE);
        if (empty($decoded)) {
            throw new SystemException('The message encoding failed');
        }
        if (mb_strlen($decoded, '8bit') < (SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES)) {
            throw new SystemException('The message was truncated');
        }
        $nonce = mb_substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, '8bit');
        $ciphertext = mb_substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, null, '8bit');

        $plain = sodium_crypto_secretbox_open(
            $ciphertext,
            $nonce,
            $key
        );
        if ($plain === false) {
            throw new SystemException('The message was tampered with in transit');
        }
        sodium_memzero($ciphertext);
        sodium_memzero($key);
        return $plain;
    }

    public function crypto($action, $inputString, $key)
    {
        if (empty($inputString) || $action === 'doNothing') {
            return $inputString;
        } elseif ($action === 'encrypt') {
            return self::encrypt($inputString, $key);
        } elseif ($action === 'decrypt') {
            return self::decrypt($inputString, $key);
        }
    }

    public function activityLog($eventType, $action, $resource)
    {

        $ipaddress = $this->getIPAddress();

        $data = array(
            'event_type' => $eventType,
            'action' => $action,
            'resource' => $resource,
            'user_id' => (!empty($_SESSION['userId'])) ? $_SESSION['userId'] : null,
            'date_time' => DateUtility::getCurrentDateTime(),
            'ip_address' => $ipaddress,
        );

        $this->db->insert('activity_log', $data);
    }

    public function resultImportStats($numberOfResults, $importMode, $importedBy)
    {

        $data = array(
            'no_of_results_imported' => $numberOfResults,
            'imported_on' => DateUtility::getCurrentDateTime(),
            'import_mode' => $importMode,
            'imported_by' => $importedBy,
        );

        $this->db->insert('result_import_stats', $data);
    }


    // public function getFacilitiesByUser($userId = null)
    // {

    //     $fQuery = "SELECT * FROM facility_details where status='active'";

    //     $facilityWhereCondition = '';

    //     if (!empty($userId)) {
    //         $userfacilityMapQuery = "SELECT GROUP_CONCAT(DISTINCT `facility_id` SEPARATOR ',') as `facility_id` FROM user_facility_map WHERE user_id='" . $userId . "'";
    //         $userfacilityMapresult = $this->db->rawQuery($userfacilityMapQuery);
    //         if ($userfacilityMapresult[0]['facility_id'] != null && $userfacilityMapresult[0]['facility_id'] != '') {
    //             $facilityWhereCondition = " AND facility_id IN (" . $userfacilityMapresult[0]['facility_id'] . ") ";
    //         }
    //     }

    //     return $this->db->rawQuery($fQuery . $facilityWhereCondition . " ORDER BY facility_name ASC");
    // }

    public function startsWith($string, $startString)
    {
        $len = strlen($startString);
        return (substr($string, 0, $len) === $startString);
    }

    public function generateSelectOptions($optionList, $selectedOptions = [], $emptySelectText = false)
    {
        if (empty($optionList)) {
            return '';
        }
        return once(function () use ($optionList, $selectedOptions, $emptySelectText) {
            $response = '';
            if ($emptySelectText !== false) {
                $response .= "<option value=''>$emptySelectText</option>";
            }

            foreach ($optionList as $optId => $optName) {
                $selectedText = '';
                if (!empty($selectedOptions)) {
                    if (is_array($selectedOptions) && in_array($optId, $selectedOptions)) {
                        $selectedText = "selected='selected'";
                    } elseif ($optId == $selectedOptions) {
                        $selectedText = "selected='selected'";
                    }
                }
                $response .= "<option value='" . addslashes($optId) . "' $selectedText>" . addslashes($optName) . "</option>";
            }
            return $response;
        });
    }

    public function getLastModifiedDateTime($tableName, $modifiedDateTimeColName = 'updated_datetime')
    {
        $query = "SELECT $modifiedDateTimeColName FROM $tableName ORDER BY $modifiedDateTimeColName DESC LIMIT 1";

        $result = $this->db->rawQueryOne($query);

        if (isset($result[$modifiedDateTimeColName]) && $result[$modifiedDateTimeColName] != '' && $result[$modifiedDateTimeColName] != null && !$this->startsWith($result[$modifiedDateTimeColName], '0000-00-00')) {
            return $result[$modifiedDateTimeColName];
        } else {
            return null;
        }
    }

    public function getHeader($key)
    {
        $headers = null;
        if (function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
        } else {
            $headers = getallheaders();
        }
        foreach ($headers as $header => $value) {
            if (strtolower($key) === strtolower($header)) {
                return $value;
            }
        }

        return null;
    }

    public function getAuthorizationBearerToken()
    {
        $headers = null;
        if (function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
        } else {
            $headers = getallheaders();
        }

        if (isset($headers['Authorization'])) {
            $authorizationHeader = $headers['Authorization'];
        } elseif (isset($headers['authorization'])) {
            // Fallback for case-insensitive header check
            $authorizationHeader = $headers['authorization'];
        } else {
            return null;
        }

        if (preg_match('/Bearer\s(\S+)/', $authorizationHeader, $matches)) {
            return $matches[1];
        } else {
            return null;
        }
    }


    public function getTestingPlatforms($testType = null)
    {

        if (!empty($testType)) {
            $this->db->where("(JSON_SEARCH(supported_tests, 'all', '$testType') IS NOT NULL) OR (supported_tests IS NULL)");
        }
        $this->db->where("status", "active");
        $this->db->orderBy('machine_name', "ASC");
        return $this->db->get('instruments');
    }

    public function getDuplicateDataFromField($tablename, $fieldname, $fieldValue, $lab = "")
    {
        $query = 'SELECT * FROM ' . $tablename . ' WHERE ' . $fieldname . ' =  "' . $fieldValue . '"';
        if ($lab != "") {
            $query .= " AND $lab like 2";
        }
        $query .= " LIMIT 1";
        return $this->db->rawQueryOne($query);
    }

    public function getRejectionReasons($testType)
    {
        $rejArray = array('general', 'whole blood', 'plasma', 'dbs', 'testing');
        if ($testType == "vl") {
            $rejArray = array('general', 'whole blood', 'plasma', 'dbs', 'testing');
        }
        if ($testType == "eid") {
            $rejArray = array('general', 'whole blood', 'plasma', 'dbs', 'testing');
        }
        if ($testType == "covid19") {
            $rejArray = array('general', 'whole blood', 'plasma', 'dbs', 'testing');
        }
        if ($testType == "hepatitis") {
            $rejArray = array('general', 'whole blood', 'plasma', 'dbs', 'testing');
        }
        if ($testType == "tb") {
            $rejArray = array('general', 'whole blood', 'plasma', 'dbs', 'testing');
        }
        if ($testType == "generic-tests") {
            $rejArray = array('general', 'whole blood', 'plasma', 'dbs', 'testing');
        }
        foreach ($rejArray as $rej) {
            $rejReaons[$rej] = ($rej);
        }
        return $rejReaons;
    }

    public function getValueByName($name = null, $condtionField = null, $tableName = null, $id = null, $exact = false)
    {
        if (empty($name)) {
            return null;
        }

        if ($exact) {
            $where = $condtionField . " LIKE '%$name%'";
        } else {
            $where = $condtionField . " LIKE '$name%'";
        }

        $query = "SELECT " . $id . " FROM " . $tableName . " where " . $where;
        $result =  $this->db->rawQuery($query);
        return $result[0][$id];
    }

    public function getLocaleLists()
    {
        $path = APPLICATION_PATH . DIRECTORY_SEPARATOR . 'locales';
        $localeLists = scandir($path);
        foreach (array(".", "..", ".DS_Store") as $delVal) {
            if (($key = array_search($delVal, $localeLists)) !== false) {
                unset($localeLists[$key]);
            }
        }
        return $localeLists;
    }

    public function activeReportFormats($module, $countryShortCode)
    {

        $list = [];

        $pdfFormat = glob(APPLICATION_PATH . "/$module/results/pdf/result-pdf-$countryShortCode*.{php}", GLOB_BRACE);

        if (false !== $pdfFormat && !empty($pdfFormat)) {
            foreach ($pdfFormat as $formatPath) {
                $baseName = basename($formatPath);
                $value = str_replace(array('.php', 'result-pdf-'), '', $baseName);
                $list["pdf/$baseName"] = strtoupper($value);
            }
        }

        $list["pdf/result-pdf-$countryShortCode.php"] = "Default";

        return $list;
    }

    public function getCountryShortCode()
    {

        if ($this->db == null) {
            return false;
        }
        return once(function () {
            $this->db->where("vlsm_country_id", $this->getGlobalConfig('vl_form'));
            return $this->db->getValue("s_available_country_forms", "short_name");
        });
    }

    public function trackQrViewPage($type, $typeId, $sampleCode)
    {
        $userAgent = $_SERVER['HTTP_USER_AGENT'];

        $data = array(
            'test_type' => $type,
            'test_type_id' => $typeId,
            'sample_code' => $sampleCode,
            'browser' => $this->getBrowser($userAgent),
            'operating_system' => $this->getOperatingSystem($userAgent),
            'date_time' => DateUtility::getCurrentDateTime(),
            'ip_address' => $this->getIPAddress(),
        );

        $this->db->insert('track_qr_code_page', $data);
    }

    public function getIPAddress()
    {
        return once(function () {
            if (getenv('HTTP_CLIENT_IP')) {
                $ipaddress = getenv('HTTP_CLIENT_IP');
            } elseif (getenv('HTTP_X_FORWARDED_FOR')) {
                $ipaddress = getenv('HTTP_X_FORWARDED_FOR');
            } elseif (getenv('HTTP_X_FORWARDED')) {
                $ipaddress = getenv('HTTP_X_FORWARDED');
            } elseif (getenv('HTTP_FORWARDED_FOR')) {
                $ipaddress = getenv('HTTP_FORWARDED_FOR');
            } elseif (getenv('HTTP_FORWARDED')) {
                $ipaddress = getenv('HTTP_FORWARDED');
            } elseif (getenv('REMOTE_ADDR')) {
                $ipaddress = getenv('REMOTE_ADDR');
            } else {
                $ipaddress = 'UNKNOWN';
            }
            return $ipaddress;
        });
    }

    public function getOperatingSystem($userAgent = null)
    {
        $osPlatform = "Unknown OS - " . $userAgent;

        $osArray =  array(
            '/windows nt 6.3/i'     =>  'Windows 8.1',
            '/windows nt 6.2/i'     =>  'Windows 8',
            '/windows nt 6.1/i'     =>  'Windows 7',
            '/windows nt 6.0/i'     =>  'Windows Vista',
            '/windows nt 5.2/i'     =>  'Windows Server 2003/XP x64',
            '/windows nt 5.1/i'     =>  'Windows XP',
            '/windows xp/i'         =>  'Windows XP',
            '/windows nt 5.0/i'     =>  'Windows 2000',
            '/windows me/i'         =>  'Windows ME',
            '/win98/i'              =>  'Windows 98',
            '/win95/i'              =>  'Windows 95',
            '/win16/i'              =>  'Windows 3.11',
            '/macintosh|mac os x/i' =>  'Mac OS X',
            '/mac_powerpc/i'        =>  'Mac OS 9',
            '/linux/i'              =>  'Linux',
            '/ubuntu/i'             =>  'Ubuntu',
            '/iphone/i'             =>  'iPhone',
            '/ipod/i'               =>  'iPod',
            '/ipad/i'               =>  'iPad',
            '/android/i'            =>  'Android',
            '/blackberry/i'         =>  'BlackBerry',
            '/webos/i'              =>  'Mobile'
        );

        foreach ($osArray as $regex => $value) {
            if (preg_match($regex, $userAgent)) {
                $osPlatform    =   $value;
            }
        }
        return $osPlatform;
    }

    public function getBrowser($userAgent = null)
    {
        $browser        =   "Unknown Browser - " . $userAgent;
        $browserArray  =   array(
            '/msie/i'       =>  'Internet Explorer',
            '/firefox/i'    =>  'Firefox',
            '/safari/i'     =>  'Safari',
            '/chrome/i'     =>  'Chrome',
            '/opera/i'      =>  'Opera',
            '/netscape/i'   =>  'Netscape',
            '/maxthon/i'    =>  'Maxthon',
            '/konqueror/i'  =>  'Konqueror',
            '/mobile/i'     =>  'Handheld Browser'
        );

        foreach ($browserArray as $regex => $value) {

            if (preg_match($regex, $userAgent)) {
                $browser    =   $value;
            }
        }

        return $browser;
    }

    // Returns the current Instance ID
    public function getInstanceId(): ?string
    {
        return once(function () {
            $this->db->getValue("s_vlsm_instance", "vlsm_instance_id");
        });
    }
    public function getLastSyncDateTime()
    {
        if (isset($_SESSION['instanceType']) && $_SESSION['instanceType'] == 'remoteuser') {
            $dateTime = $this->db->rawQueryOne("SELECT MAX(`requested_on`) AS `dateTime` FROM `track_api_requests`");
        } else {
            $dateTime = $this->db->rawQueryOne("SELECT GREATEST(COALESCE(last_remote_requests_sync, 0), COALESCE(last_remote_results_sync, 0), COALESCE(last_remote_reference_data_sync, 0)) AS dateTime FROM s_vlsm_instance");
        }
        return (isset($dateTime['dateTime']) && $dateTime['dateTime'] != "") ? date('d-M-Y h:i:s a', strtotime($dateTime['dateTime'])) : null;
    }

    public function existBatchCode($code)
    {
        $this->db->where("batch_code", $code);
        return $this->db->getOne("batch_details");
    }

    public function createBatchCode()
    {
        $batchQuery = 'SELECT MAX(batch_code_key) FROM batch_details as bd WHERE DATE(bd.request_created_datetime) = CURRENT_DATE';
        $batchResult = $this->db->query($batchQuery);

        if ($batchResult[0]['MAX(batch_code_key)'] != '' && $batchResult[0]['MAX(batch_code_key)'] != null) {
            $code = $batchResult[0]['MAX(batch_code_key)'] + 1;
            $length = strlen($code);
            if ($length == 1) {
                $code = "00" . $code;
            } else if ($length == 2) {
                $code = "0" . $code;
            }
        } else {
            $code = '001';
        }
        return $code;
    }

    public function excelColumnRange($lower, $upper)
    {
        ++$upper;
        for ($i = $lower; $i !== $upper; ++$i) {
            yield $i;
        }
    }

    public function fileExists($filePath): bool
    {
        return (!empty($filePath) && file_exists($filePath) && !is_dir($filePath) && filesize($filePath) > 0);
    }

    public function imageExists($filePath): bool
    {
        return (!empty($filePath) && file_exists($filePath) && !is_dir($filePath) && filesize($filePath) > 0 && false !== getimagesize($filePath));
    }


    // This function removes control characters from the strings in the CSV file.
    // https://en.wikipedia.org/wiki/Control_character#ASCII_control_characters
    // Also checks UTF-8 encoding and converts if needed
    public function removeCntrlCharsAndEncode($inputString, $encodeToUTF8 = true): string
    {
        $inputString = preg_replace('/[[:cntrl:]]/', '',  $inputString);
        if ($encodeToUTF8 && mb_detect_encoding($inputString, 'UTF-8', true) === false) {
            $inputString = mb_convert_encoding($inputString, 'UTF-8');
        }
        return $inputString;
    }

    //dump the contents of a variable to the error log in a readable format
    public static function errorLog($object = null): void
    {
        ob_start();
        var_dump($object);
        error_log(ob_get_clean());
    }

    // Returns false if string not matched, and returns string if matched
    public function checkIfStringExists(string $sourceString, array $itemsToSearch, int $offset = 0)
    {
        $response = false;
        foreach ($itemsToSearch as $needle) {
            if (stripos($sourceString, $needle, $offset) !== false) {
                return $needle; // stop on first true result
            }
        }
        return $response;
    }

    public function isJSON($string)
    {
        return is_string($string) &&
            is_array(json_decode($string, true)) &&
            (json_last_error() == JSON_ERROR_NONE);
    }

    public function prettyJson($json)
    {
        if (is_array($json)) {
            return stripslashes(json_encode($json, JSON_PRETTY_PRINT));
        } else {
            return stripslashes(json_encode(json_decode($json), JSON_PRETTY_PRINT));
        }
    }


    public function addApiTracking($transactionId, $user, $numberOfRecords, $requestType, $testType, $url = null, $requestData = null, $responseData = null, $format = null, $labId = null, $facilityId = null)
    {

        try {
            $requestData = (!empty($requestData) && !$this->isJSON($requestData)) ? json_encode($requestData, JSON_UNESCAPED_SLASHES) : $requestData;
            $responseData = (!empty($responseData) && !$this->isJSON($responseData)) ? json_encode($responseData, JSON_UNESCAPED_SLASHES) : $responseData;


            $folderPath = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'track-api';
            if (!file_exists($folderPath)) {
                mkdir($folderPath, 0777, true);
            }
            if (!file_exists($folderPath . DIRECTORY_SEPARATOR . 'requests')) {
                mkdir($folderPath . DIRECTORY_SEPARATOR . 'requests', 0777, true);
            }
            if (!file_exists($folderPath . DIRECTORY_SEPARATOR . 'responses')) {
                mkdir($folderPath . DIRECTORY_SEPARATOR . 'responses', 0777, true);
            }

            if (!empty($requestData) && $requestData != '[]') {
                $path = $folderPath
                    . DIRECTORY_SEPARATOR
                    . 'requests'
                    . DIRECTORY_SEPARATOR
                    . $transactionId . '.json';
                //file_put_contents($path, $requestData);

                $zip = new ZipArchive();
                if ($zip->open($path . '.zip', ZIPARCHIVE::CREATE) === true) {
                    $zip->addFromString(basename($path), $requestData);
                    //$zip->close();
                    //unlink($path);
                }
            }

            if (!empty($responseData) && $responseData != '[]') {
                $path = $folderPath
                    . DIRECTORY_SEPARATOR
                    . 'responses'
                    . DIRECTORY_SEPARATOR
                    . $transactionId . '.json';
                //file_put_contents($path, $responseData);

                $zip = new ZipArchive();
                if ($zip->open($path . '.zip', ZIPARCHIVE::CREATE) === true) {
                    $zip->addFromString(basename($path), $responseData);
                    //$zip->close();
                    //unlink($path);
                }
            }

            $data = array(
                'transaction_id'    => $transactionId ?: null,
                'requested_by'      => $user ?: 'vlsm-system',
                'requested_on'      => DateUtility::getCurrentDateTime(),
                'number_of_records' => $numberOfRecords ?: 0,
                'request_type'      => $requestType ?: null,
                'test_type'         => $testType ?: null,
                'api_url'           => $url ?: null,
                'facility_id'       => $labId ?: null,
                'data_format'       => $format ?: null
            );
            return $this->db->insert("track_api_requests", $data);
        } catch (Exception $exc) {
            error_log($exc->getMessage());
            error_log($this->db->getLastError());
            error_log($exc->getTraceAsString());
            return 0;
        }
    }

    public function getBarcodeImageContent($code, $type = 'C39', $width = 2, $height = 30, $color = array(0, 0, 0))
    {
        $barcodeobj = new TCPDFBarcode($code, $type);
        return 'data:image/png;base64,' . base64_encode($barcodeobj->getBarcodePngData($width, $height, $color));
    }

    public function getSampleType($testTypeId)
    {
        $sampleTypeQry = "SELECT * FROM r_generic_sample_types as st INNER JOIN generic_test_sample_type_map as map ON map.sample_type_id=st.sample_type_id WHERE map.test_type_id=$testTypeId AND st.sample_type_status='active'";
        return $this->db->query($sampleTypeQry);
    }

    /**
     * Convert a JSON string to a string that can be used with JSON_SET()
     *
     * @param string $json The JSON string to convert
     * @param string $column The name of the JSON column
     * @param array $newData An optional array of new key-value pairs to add to the JSON
     * @return string The string that can be used with JSON_SET()
     */
    public function jsonToSetString($json, $column, $newData = [])
    {
        $data = json_decode($json, true);
        $setString = '';

        foreach ($data as $key => $value) {
            if (is_null($value)) {
                $setString .= ', "$.' . $key . '", null';
            } elseif (is_bool($value)) {
                $setString .= ', "$.' . $key . '", ' . ($value ? 'true' : 'false');
            } elseif (is_numeric($value)) {
                $setString .= ', "$.' . $key . '", ' . $value;
            } else {
                $setString .= ', "$.' . $key . '", "' . addslashes($value) . '"';
            }
        }

        foreach ($newData as $key => $value) {
            if (is_null($value)) {
                $setString .= ', "$.' . $key . '", null';
            } elseif (is_bool($value)) {
                $setString .= ', "$.' . $key . '", ' . ($value ? 'true' : 'false');
            } elseif (is_numeric($value)) {
                $setString .= ', "$.' . $key . '", ' . $value;
            } else {
                $setString .= ', "$.' . $key . '", "' . addslashes($value) . '"';
            }
        }

        return 'JSON_SET(COALESCE(' . $column . ', "{}")' . $setString . ')';
    }
}
