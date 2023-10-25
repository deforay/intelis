<?php



namespace App\Services;

use MysqliDb;
use Exception;
use TCPDFBarcode;
use Ramsey\Uuid\Uuid;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Exceptions\SystemException;
use App\Registries\ContainerRegistry;

class CommonService
{

    protected ?MysqliDb $db = null;


    public function __construct(?MysqliDb $db = null)
    {
        $this->db = $db ?? ContainerRegistry::get('db');
    }

    public function getQueryResultAndCount(string $sql, ?array $params = null, ?int $limit = null, ?int $offset = null): array
    {
        try {
            $count = 0;
            $limitOffsetSet = isset($limit) && isset($offset);
            // Modify the SQL query to include limit and offset if they are set.
            $limitSql = "";
            if ($limitOffsetSet) {
                $limitSql = " LIMIT $offset,$limit";
            }

            // Execute the main query.
            $queryResult = $this->db->rawQuery($sql . $limitSql, $params);

            // If limit and offset are set, execute the count query.
            if ($limitOffsetSet) {
                if (stripos($sql, 'GROUP BY') !== false) {
                    // If the query contains GROUP BY
                    $countSql = "SELECT COUNT(*) as totalCount FROM ($sql) as subquery";
                    $count = (int)$this->db->rawQueryOne($countSql)['totalCount'];
                } else {
                    // If the query does not contain GROUP BY
                    $countSql = preg_replace('/SELECT.*? FROM/si', 'SELECT COUNT(*) as qCount FROM', $sql, 1);
                    $count = (int)$this->db->rawQueryOne($countSql)['qCount'];
                }
            } else {
                $count = count($queryResult);
            }

            return [$queryResult, $count];
        } catch (Exception $e) {
            throw new SystemException($e->getMessage());
        }
    }



    /**
     *
     * @param int $length
     * @return string
     * @throws SystemException
     */

    public function generateRandomString($length = 32): string
    {
        // Ensure $length is always even
        if ($length % 2 != 0) {
            $length++;
        }

        $attempts = 0;
        while ($attempts < 3) {
            try {
                return bin2hex(random_bytes($length / 2));
            } catch (SystemException $e) {
                error_log($e->getMessage());
                $attempts++;
            }
        }
        throw new SystemException('Could not generate a random string');
    }


    // Returns a UUID format string
    public function generateUUID($attachExtraString = true): string
    {
        $uuid = (Uuid::uuid4())->toString();
        $uuid .= $attachExtraString ? '-' . $this->generateRandomString(6) : '';
        return $uuid;
    }

    public function getClientIpAddress()
    {
        return once(function () {
            $ipAddress = null;

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
        });
    }

    // get data from the system_config table from database
    public function getSystemConfig($name = null)
    {

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
        return once(function () use ($name) {

            if (!empty($name)) {
                $this->db->where('name', $name);
                return $this->db->getValue("global_config", "value") ?? null;
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

    public function getDataByTableAndFields($table, $fields, $option = true, $condition = null, $group = null)
    {
        if (!is_array($fields)) {
            $fields = [$fields];
        }

        $query = "SELECT " . implode(",", $fields) . " FROM " . $table;
        if ($condition) {
            $query .= " WHERE " . $condition;
        }

        if (!empty($group)) {
            $query .= " GROUP BY " . $group;
        }
        $results = $this->db->rawQuery($query);
        if ($option) {
            foreach ($results as $row) {
                $response[$row[$fields[0]]] = $row[$fields[1]];
            }
        } else {
            $response = $results;
        }
        return $response;
    }

    public function fetchDataFromTable($tableName = null, $condition = null, $fieldName = null)
    {
        return once(function () use ($tableName, $condition, $fieldName) {

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
        });
    }

    // checking if the provided field list has any empty or null values
    public function checkMandatoryFields($field): bool
    {
        foreach ($field as $chkField) {
            if (empty(trim($chkField))) {
                return true;
            }
        }

        return false;
    }

    public static function encrypt($message, $key): string
    {
        try {
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
        } catch (\SodiumException $e) {
            return $message;
        }
    }

    public static function decrypt($encrypted, $key): string
    {
        try {
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
        } catch (\SodiumException $e) {
            return $encrypted;
        }
    }

    public function crypto($action, $inputString, $key)
    {
        if (!empty($inputString) && $action === 'encrypt') {
            return self::encrypt($inputString, $key);
        } elseif (!empty($inputString) && $action === 'decrypt') {
            return self::decrypt($inputString, $key);
        } else {
            return $inputString;
        }
    }

    public function activityLog($eventType, $action, $resource)
    {

        $ipaddress = $this->getClientIpAddress();

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

    public function getUserMappedProvinces($facilityMap = null)
    {
        return once(function () use ($facilityMap) {
            $facilityMap = $facilityMap ?? $_SESSION['facilityMap'] ?? null;

            $query = "SELECT gd.geo_name, gd.geo_id, gd.geo_code
                        FROM geographical_divisions as gd";

            if (!empty($facilityMap)) {
                $query .= " JOIN facility_details as f ON f.facility_state_id=gd.geo_id
                    WHERE gd.geo_parent = 0 AND
                    gd.geo_status='active' AND
                    f.facility_id IN (?)";
                $result = $this->db->rawQuery($query, [$facilityMap]);
            } else {
                $query .= " WHERE gd.geo_parent = 0 AND gd.geo_status='active'";
                $result = $this->db->rawQuery($query);
            }

            $options = array_map(function ($row) {
                return "<option data-code='{$row['geo_code']}' data-province-id='{$row['geo_id']}' data-name='{$row['geo_name']}' value='{$row['geo_name']}##{$row['geo_code']}'> {$row['geo_name']} </option>";
            }, $result);

            array_unshift($options, "<option value=''>" . _translate("-- Select --") . " </option>");

            return implode('', $options);
        });
    }


    public function generateSelectOptions($optionList, $selectedOptions = [], $emptySelectText = false)
    {
        return once(function () use ($optionList, $selectedOptions, $emptySelectText) {
            if (empty($optionList)) {
                return '';
            }
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
        $query = "SELECT $modifiedDateTimeColName
                    FROM $tableName
                    ORDER BY $modifiedDateTimeColName DESC
                    LIMIT 1";

        $result = $this->db->rawQueryOne($query);

        if (DateUtility::isDateValid($result[$modifiedDateTimeColName] ?? null)) {
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

    public function getAuthorizationBearerToken(): ?string
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

    public function getDataFromOneFieldAndValue($tablename, $fieldname, $fieldValue, $condition = null)
    {
        return once(function () use ($tablename, $fieldname, $fieldValue, $condition) {
            $query = "SELECT * FROM $tablename WHERE $fieldname = ?";
            if (!empty($condition) && $condition != '') {
                $query .= " AND $condition";
            }
            return $this->db->rawQueryOne($query, [$fieldValue]);
        });
    }

    public function getRejectionReasons($testType): array
    {
        $rejArray = ['general', 'whole blood', 'plasma', 'dbs', 'testing'];
        if (in_array($testType, ['vl', 'eid', 'covid19', 'hepatitis', 'tb', 'generic-tests'])) {
            foreach ($rejArray as $rej) {
                $rejReaons[$rej] = $rej;
            }
        }
        return $rejReaons;
    }

    public function getValueByName($fieldValue = null, $fieldName = null, $tableName = null, $returnFieldName = null)
    {
        return once(function () use ($fieldValue, $fieldName, $tableName, $returnFieldName) {
            if (empty($fieldValue) || empty($fieldName) || empty($tableName) || empty($returnFieldName)) {
                return null;
            }

            $this->db->where($fieldName, $fieldValue);
            return $this->db->getValue($tableName, $returnFieldName);
        });
    }

    public function getLocaleList()
    {
        $localeMap = [
            'en_US' => 'English_United States',
            'en_CM' => 'English_Cameroon',
            'fr_CM' => 'French_Cameroon',
            'fr_FR' => 'French',
            'lo_LA' => 'Lao'
        ];

        $path = APPLICATION_PATH . DIRECTORY_SEPARATOR . 'locales';

        // Filter out unwanted entries directly
        $localeList = array_diff(scandir($path), ['.', '..', '.DS_Store']);

        $locales = [];
        foreach ($localeList as $locale) {
            $locales[$locale] = $localeMap[$locale] ?? $locale;
        }

        return $locales;
    }


    public function activeReportFormats($module): array
    {
        $activeReportFormats = [];
        $countryShortCode = $this->getCountryShortCode();
        $pdfFormatPaths = glob(APPLICATION_PATH . "/$module/results/pdf/result-pdf-$countryShortCode*.{php}", GLOB_BRACE);

        if (!empty($pdfFormatPaths)) {
            $activeReportFormats = array_map(function ($formatPath) {
                $baseName = basename($formatPath);
                $countryShortCode = $this->getCountryShortCode();
                $formatName = str_replace(['.php', 'result-pdf-'], '', $baseName);
                if ($baseName == "result-pdf-$countryShortCode.php")
                    return ["pdf/$baseName" => "Default"];
                else
                    return ["pdf/$baseName" => strtoupper($formatName)];
            }, $pdfFormatPaths);
        }

        return $activeReportFormats;
    }


    public function getCountryShortCode()
    {
        return once(function () {
            $this->db->where("vlsm_country_id", $this->getGlobalConfig('vl_form'));
            return $this->db->getValue("s_available_country_forms", "short_name");
        });
    }

    public function trackQRPageViews($type, $typeId, $sampleCode)
    {
        $userAgent = $_SERVER['HTTP_USER_AGENT'];

        $data = array(
            'test_type' => $type,
            'test_type_id' => $typeId,
            'sample_code' => $sampleCode,
            'browser' => $this->getBrowser($userAgent),
            'operating_system' => $this->getOperatingSystem($userAgent),
            'date_time' => DateUtility::getCurrentDateTime(),
            'ip_address' => $this->getClientIpAddress(),
        );

        $this->db->insert('track_qr_code_page', $data);
    }

    public function getOperatingSystem($userAgent = null): string
    {

        return once(function () use ($userAgent) {
            if ($userAgent === null) {
                return "Unknown OS";
            }

            $osArray = array(
                '/windows nt 10/i' => 'Windows 10',
                '/windows nt 6.3/i' => 'Windows 8.1',
                '/windows nt 6.2/i' => 'Windows 8',
                '/windows nt 6.1/i' => 'Windows 7',
                '/windows nt 6.0/i' => 'Windows Vista',
                '/windows nt 5.2/i' => 'Windows Server 2003/XP x64',
                '/windows nt 5.1/i' => 'Windows XP',
                '/windows xp/i' => 'Windows XP',
                '/windows nt 5.0/i' => 'Windows 2000',
                '/windows me/i' => 'Windows ME',
                '/win98/i' => 'Windows 98',
                '/win95/i' => 'Windows 95',
                '/win16/i' => 'Windows 3.11',
                '/macintosh|mac os x/i' => 'Mac OS X',
                '/mac_powerpc/i' => 'Mac OS 9',
                '/linux/i' => 'Linux',
                '/ubuntu/i' => 'Ubuntu',
                '/iphone/i' => 'iPhone',
                '/ipod/i' => 'iPod',
                '/ipad/i' => 'iPad',
                '/android/i' => 'Android',
                '/blackberry/i' => 'BlackBerry',
                '/webos/i' => 'Mobile',
                '/fedora/i' => 'Fedora',
                '/debian/i' => 'Debian',
                '/freebsd/i' => 'FreeBSD',
                '/openbsd/i' => 'OpenBSD',
                '/netbsd/i' => 'NetBSD',
                '/sunos/i' => 'SunOS',
                '/solaris/i' => 'Solaris',
                '/aix/i' => 'AIX'
            );

            foreach ($osArray as $regex => $value) {
                if (preg_match($regex, $userAgent)) {
                    return $value;
                }
            }

            return "Unknown OS - " . $userAgent;
        });
    }


    public function getBrowser($userAgent = null): string
    {
        return once(function () use ($userAgent) {
            if ($userAgent === null) {
                return "Unknown Browser";
            }

            $browserArray = array(
                '/msie/i' => 'Internet Explorer',
                '/trident/i' => 'Internet Explorer',
                '/firefox/i' => 'Firefox',
                '/safari/i' => 'Safari',
                '/chrome/i' => 'Chrome',
                '/edge/i' => 'Edge',
                '/opera/i' => 'Opera',
                '/netscape/i' => 'Netscape',
                '/maxthon/i' => 'Maxthon',
                '/konqueror/i' => 'Konqueror',
                '/mobile/i' => 'Mobile Browser',
                '/applewebkit/i' => 'Webkit Browser',
                '/brave/i' => 'Brave'
            );

            foreach ($browserArray as $regex => $value) {
                if (preg_match($regex, $userAgent)) {
                    return $value;
                }
            }

            return "Unknown Browser - " . $userAgent;
        });
    }


    // Returns the current Instance ID
    public function getInstanceId(): ?string
    {
        return once(function () {
            return $this->db->getValue("s_vlsm_instance", "vlsm_instance_id");
        });
    }

    public function isRemoteUser(): bool
    {
        return once(function () {
            return isset($_SESSION['instanceType']) && $_SESSION['instanceType'] == 'remoteuser';
        });
    }
    public function getLastSyncDateTime()
    {
        if ($this->isRemoteUser()) {
            $dateTime = $this->db->rawQueryOne("SELECT MAX(`requested_on`) AS `dateTime`
                                                    FROM `track_api_requests`");
        } else {
            $lastSyncQuery = "SELECT GREATEST(COALESCE(last_remote_requests_sync, 0),
                                                COALESCE(last_remote_results_sync, 0),
                                                COALESCE(last_remote_reference_data_sync, 0)
                                            ) AS dateTime
                                FROM s_vlsm_instance";
            $dateTime = $this->db->rawQueryOne($lastSyncQuery);
        }
        return (isset($dateTime['dateTime']) && $dateTime['dateTime'] != "") ?
            DateUtility::humanReadableDateFormat($dateTime['dateTime'], false, 'd-M-Y h:i:s a')
            : null;
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

    public function getLastApiSyncByType(string $syncType): ?string
    {
        $lastSyncQuery = "SELECT MAX(`requested_on`) AS `dateTime`
                            FROM `track_api_requests`
                            WHERE `request_type` = ?";
        $dateTime = $this->db->rawQueryOne($lastSyncQuery, [$syncType]);
        return $dateTime['dateTime'] ?? null;
    }

    public function addApiTracking($transactionId, $user, $numberOfRecords, $requestType, $testType, $url = null, $requestData = null, $responseData = null, $format = null, $labId = null, $facilityId = null)
    {
        try {

            $requestData = MiscUtility::toJSON($requestData);
            $responseData = MiscUtility::toJSON($responseData);

            $folderPath = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'track-api';
            if (!empty($requestData) && $requestData != '[]') {
                MiscUtility::makeDirectory($folderPath . DIRECTORY_SEPARATOR . 'requests');
                MiscUtility::zipJson($requestData, "$folderPath/requests/$transactionId.json");
            }
            if (!empty($responseData) && $responseData != '[]') {
                MiscUtility::makeDirectory($folderPath . DIRECTORY_SEPARATOR . 'responses');
                MiscUtility::zipJson($responseData, "$folderPath/responses/$transactionId.json");
            }

            $data = [
                'transaction_id' => $transactionId ?? null,
                'requested_by' => $user ?? 'system',
                'requested_on' => DateUtility::getCurrentDateTime(),
                'number_of_records' => $numberOfRecords ?? 0,
                'request_type' => $requestType ?? null,
                'test_type' => $testType ?? null,
                'api_url' => $url ?? null,
                'facility_id' => $labId ?? null,
                'data_format' => $format ?? null
            ];
            return $this->db->insert("track_api_requests", $data);
        } catch (Exception $exc) {
            error_log($exc->getMessage());
            error_log($this->db->getLastError());
            error_log($exc->getTraceAsString());
            return 0;
        }
    }

    public function updateSyncDateTime($testType, $testTable, $columnForWhereCondition, $sampleIds, $transactionId, $facilityIds, $labId, $syncType): void
    {
        $currentDateTime = DateUtility::getCurrentDateTime();
        $sampleIdsStr = is_array($sampleIds) ? "'" . implode("','", $sampleIds) . "'" : $sampleIds;

        if (!empty($sampleIds)) {
            $sql = "UPDATE $testTable SET data_sync = 1,
                    form_attributes = JSON_SET(COALESCE(form_attributes, '{}'), '$.remote{$syncType}Sync', '$currentDateTime', '{$syncType}SyncTransactionId', '$transactionId')
                    WHERE $columnForWhereCondition IN ($sampleIdsStr)";
            $this->db->rawQuery($sql);
        }


        if (!empty($facilityIds)) {
            $facilityIdsStr = implode(",", array_unique(array_filter($facilityIds)));
            $sql = "UPDATE facility_details
                SET facility_attributes = JSON_SET(COALESCE(facility_attributes, '{}'), '$.remote{$syncType}Sync', '$currentDateTime', '$.{$testType}Remote{$syncType}Sync', '$currentDateTime')
                WHERE facility_id IN ($facilityIdsStr)";
            $this->db->rawQuery($sql);
        }

        if (!empty($labId)) {
            $sql = "UPDATE facility_details
            SET facility_attributes = JSON_SET(COALESCE(facility_attributes, '{}'), '$.last{$syncType}Sync', '$currentDateTime', '$.{$testType}Last{$syncType}Sync', '$currentDateTime')
            WHERE facility_id = ?";
            $this->db->rawQuery($sql, [$labId]);
        }
    }

    public function updateTestRequestsSyncDateTime($testType, $testTable, $testTablePrimaryKey, $sampleIds, $transactionId, $facilityIds, $labId): void
    {
        $this->updateSyncDateTime($testType, $testTable, $testTablePrimaryKey, $sampleIds, $transactionId, $facilityIds, $labId, 'Requests');
    }

    public function updateResultSyncDateTime($testType, $testTable, $sampleCodes, $transactionId, $facilityIds, $labId): void
    {
        $this->updateSyncDateTime($testType, $testTable, 'sample_code', $sampleCodes, $transactionId, $facilityIds, $labId, 'Results');
    }

    public function getBarcodeImageContent($code, $type = 'C39', $width = 2, $height = 30, $color = array(0, 0, 0)): string
    {
        $barcodeobj = new TCPDFBarcode($code, $type);
        return 'data:image/png;base64,' . base64_encode($barcodeobj->getBarcodePngData($width, $height, $color));
    }

    public function get2DBarcodeImageContent($code, $type = 'QRCODE', $width = 2, $height = 30, $color = array(0, 0, 0))
    {
        $barcodeobj = new \TCPDF2DBarcode($code, $type);
        return 'data:image/png;base64,' . base64_encode($barcodeobj->getBarcodePngData($width, $height, $color));
    }

    /**
     * Convert a JSON string to a string that can be used with JSON_SET()
     *
     * @param string $json The JSON string to convert
     * @param string $column The name of the JSON column
     * @param array|string $newData An optional array or JSON string of new key-value pairs to add to the JSON
     * @return string|null The string that can be used with JSON_SET()
     */
    public function jsonToSetString(?string $json, string $column, $newData = []): ?string
    {
        $data = [];
        if (MiscUtility::isJSON($json)) {
            $data = json_decode($json, true);
        }
        $setString = '';

        if (is_string($newData)) {
            $newData = json_decode($newData, true);
        }

        foreach (array_merge($data, $newData) as $key => $value) {
            $setString .= ', "$.' . $key . '", ';
            if (is_null($value)) {
                $setString .= 'null';
            } elseif (is_bool($value)) {
                $setString .= $value ? 'true' : 'false';
            } elseif (is_numeric($value)) {
                $setString .= $value;
            } elseif (is_array($value)) {
                $setString .= "'" . addslashes(json_encode($value)) . "'";
            } else {
                $setString .= "'" . addslashes($value) . "'";
            }
        }

        if (empty($setString)) {
            return null;
        } else {
            return 'JSON_SET(COALESCE(' . $column . ', "{}")' . $setString . ')';
        }
    }




    public function stringToCamelCase($string, $character = "_", $capitalizeFirstCharacter = false)
    {
        $str = str_replace($character, '', ucwords($string, $character));
        return (!$capitalizeFirstCharacter) ? lcfirst($str) : null;
    }

    public function getPrimaryKeyField($table)
    {
        if (!$table) {
            return null;
        }
        $response = $this->db->rawQueryOne("SHOW KEYS FROM " . $table . " WHERE Key_name = 'PRIMARY';");
        return $response['Column_name'] ?? null;
    }

    public function getImplementationPartners()
    {
        $this->db->where("i_partner_status", "active");
        $this->db->orderBy('i_partner_name', "ASC");
        return $this->db->get('r_implementation_partners');
    }

    public function getFundingSources()
    {
        $this->db->where("funding_source_status", "active");
        $this->db->orderBy('funding_source_name', "ASC");
        return $this->db->get('r_funding_sources');
    }

    public function getSourceOfRequest($table)
    {
        $srcQuery = "SELECT DISTINCT source_of_request from $table where source_of_request is not null AND source_of_request not like ''";
        $srcResults = $this->db->rawQuery($srcQuery);
        return $srcResults;
    }

    public function getSampleStatus()
    {
        $this->db->where("status", "active");
        $this->db->orderBy('status_name', "ASC");
        return $this->db->get('r_sample_status');
    }
}
