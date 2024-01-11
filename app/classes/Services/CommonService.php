<?php

namespace App\Services;

use COUNTRY;
use Exception;
use TCPDFBarcode;
use TCPDF2DBarcode;
use SodiumException;
use Ramsey\Uuid\Uuid;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Exceptions\SystemException;
use App\Services\FacilitiesService;
use App\Utilities\FileCacheUtility;
use Symfony\Contracts\Cache\ItemInterface;


class CommonService
{
    protected DatabaseService $db;
    protected FacilitiesService $facilitiesService;
    protected $fileCache;

    public function __construct(DatabaseService $db, FacilitiesService $facilitiesService, FileCacheUtility $fileCache)
    {
        $this->db = $db;
        $this->facilitiesService = $facilitiesService;
        $this->fileCache = $fileCache;
    }

    public function getQueryResultAndCount(string $sql, ?array $params = null, ?int $limit = null, ?int $offset = null, bool $returnGenerator = false, bool $unbuffered = false): array
    {
        try {
            $count = 0;
            $limitOffsetSet = isset($limit) && isset($offset);
            $limitSql = "";

            if ($limitOffsetSet) {
                $limitSql = " LIMIT $offset,$limit";
            }

            // Execute the main query
            if ($returnGenerator === true) {
                $queryResult = $this->db->rawQueryGenerator($sql . $limitSql, $params, $unbuffered);
            } else {
                $queryResult = $this->db->rawQuery($sql . $limitSql, $params);
            }

            // Execute the count query if necessary
            if ($limitOffsetSet || $returnGenerator) {
                if (stripos($sql, 'GROUP BY') !== false) {
                    $countSql = "SELECT COUNT(*) as totalCount FROM ($sql) as subquery";
                } else {
                    $countSql = preg_replace('/SELECT.*? FROM/si', 'SELECT COUNT(*) as totalCount FROM', $sql, 1);
                }

                // Generate a unique session key for the count query
                $countQuerySessionKey = md5($countSql);
                if (isset($_SESSION['queryCounters'][$countQuerySessionKey])) {
                    $count = $_SESSION['queryCounters'][$countQuerySessionKey];
                } else {
                    $count = $_SESSION['queryCounters'][$countQuerySessionKey] = (int)$this->db->rawQueryOne($countSql)['totalCount'];
                }
            } else {
                $count = count($queryResult);
            }

            return [$queryResult, $count];
        } catch (Exception $e) {
            throw new SystemException($e->getMessage(), 500, $e);
        }
    }

    /**
     *
     * @param int $length
     * @return string
     * @throws SystemException
     */

    public function generateRandomString(int $length = 32): string
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
    }

    // get data from the system_config table from database
    public function getSystemConfig(?string $name = null)
    {
        $cacheKey = 'app_system_config';

        $allConfigs = $this->fileCache->get($cacheKey, function () {
            $returnConfig = [];
            $systemConfigResult = $this->db->get('system_config');
            foreach ($systemConfigResult as $config) {
                $returnConfig[$config['name']] = $config['value'];
            }
            return $returnConfig;
        });

        return $name ? ($allConfigs[$name] ?? null) : ($allConfigs ?? []);
    }

    // get data from the global_config table from database
    public function getGlobalConfig(?string $name = null): string|array|null
    {
        $cacheKey = 'app_global_config';

        $allConfigs = $this->fileCache->get($cacheKey, function () {
            $returnConfig = [];
            $configResult = $this->db->get('global_config');
            foreach ($configResult as $config) {
                $returnConfig[$config['name']] = $config['value'];
            }
            return $returnConfig;
        });

        return $name ? ($allConfigs[$name] ?? null) : ($allConfigs ?? []);
    }


    public function getDataByTableAndFields($table, $fields, $option = true, $condition = null, $group = null)
    {
        $response = [];
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
            // Check is array or not
            $fieldName = is_array($fieldName) ? implode(",", $fieldName) : $fieldName;

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

    public static function encrypt($message, $key): string
    {
        try {
            $nonce = random_bytes(
                SODIUM_CRYPTO_SECRETBOX_NONCEBYTES
            );

            $cipher = sodium_bin2base64(
                $nonce .
                    sodium_crypto_secretbox(
                        (string) $message,
                        $nonce,
                        (string) $key
                    ),
                SODIUM_BASE64_VARIANT_URLSAFE
            );
            sodium_memzero($message);
            sodium_memzero($key);
            return $cipher;
        } catch (SodiumException $e) {
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
            if (strlen($decoded) < (SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES)) {
                throw new SystemException('The message was truncated');
            }
            $nonce = substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $ciphertext = substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

            $plain = sodium_crypto_secretbox_open($ciphertext, $nonce, $key);
            if ($plain === false) {
                throw new SystemException('The message was tampered with in transit');
            }
            sodium_memzero($ciphertext);
            sodium_memzero($key);
            return $plain;
        } catch (SodiumException | SystemException $e) {
            // Log the exception and return an empty string or specific error message
            return ''; // or a specific error message
        }
    }

    public static function crypto(?string $action, ?string $inputString, $key): ?string
    {
        if (is_null($inputString)) {
            return null;
        }
        switch ($action) {
            case 'encrypt':
                return self::encrypt($inputString, $key);
            case 'decrypt':
                return self::decrypt($inputString, $key);
            case 'doNothing':
                return $inputString;
            default:
                return null;
        }
    }

    public function activityLog($eventType, $action, $resource)
    {

        $ipaddress = $this->getClientIpAddress();

        $data = [
            'event_type' => $eventType,
            'action' => $action,
            'resource' => $resource,
            'user_id' => (!empty($_SESSION['userId'])) ? $_SESSION['userId'] : null,
            'date_time' => DateUtility::getCurrentDateTime(),
            'ip_address' => $ipaddress,
        ];

        $this->db->insert('activity_log', $data);
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

            $response = '';

            if (empty($optionList)) {
                return '';
            }
            if ($emptySelectText !== false) {
                $response .= "<option value=''>" . htmlspecialchars($emptySelectText) . "</option>";
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
                $response .= "<option value='" . htmlspecialchars($optId) . "' $selectedText>" . htmlspecialchars($optName) . "</option>";
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
            if (strtolower((string) $key) === strtolower($header)) {
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

        if (preg_match('/Bearer\s(\S+)/', (string) $authorizationHeader, $matches)) {
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
        $rejReaons = [];
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

    public function getLocaleList(int $formId = null)
    {
        if (empty($formId)) {
            $formId = (int)$this->getGlobalConfig('vl_form') ?? 0;
        }
        // Locale mapping
        $localeMap = [
            'en_US' => 'English',
            'fr_FR' => 'French',
            'en_CM' => 'English_Cameroon',
            'fr_CM' => 'French_Cameroon'
        ];

        // Define Cameroon locales
        $cameroonLocales = ['en_CM', 'fr_CM'];

        if ($formId === COUNTRY\CAMEROON) {
            // Keep only Cameroon locales
            $localeMap = array_intersect_key($localeMap, array_flip($cameroonLocales));
        } elseif ($formId !== 0) {
            // Remove Cameroon locales for other specific countries
            $localeMap = array_diff_key($localeMap, array_flip($cameroonLocales));
        }
        // If 0, keep all locales in $localeMap

        return $localeMap;
    }

    public function activeReportFormats($module): array
    {
        $countryShortCode = $this->getCountryShortCode();

        $pdfFormatPaths = glob(APPLICATION_PATH . "/$module/results/pdf/result-pdf-$countryShortCode*.{php}", GLOB_BRACE);

        if (empty($pdfFormatPaths)) {
            return [];
        }

        return array_map(function ($formatPath) use ($countryShortCode) {
            $baseName = pathinfo($formatPath, PATHINFO_BASENAME);
            $formatName = str_replace(['.php', "result-pdf-$countryShortCode"], '', $baseName);

            if ($baseName == "result-pdf-$countryShortCode.php") {
                return ["pdf/$baseName" => "Default"];
            }

            return ["pdf/$baseName" => $countryShortCode . strtoupper($formatName)];
        }, $pdfFormatPaths);
    }


    public function getCountryShortCode(): string
    {
        return once(function () {
            $this->db->where("vlsm_country_id", $this->getGlobalConfig('vl_form'));
            return $this->db->getValue("s_available_country_forms", "short_name");
        });
    }

    public function trackQRPageViews($type, $typeId, $sampleCode)
    {
        $userAgent = $_SERVER['HTTP_USER_AGENT'];

        $data = [
            'test_type' => $type,
            'test_type_id' => $typeId,
            'sample_code' => $sampleCode,
            'browser' => $this->getBrowser($userAgent),
            'operating_system' => $this->getOperatingSystem($userAgent),
            'date_time' => DateUtility::getCurrentDateTime(),
            'ip_address' => $this->getClientIpAddress(),
        ];

        $this->db->insert('track_qr_code_page', $data);
    }

    public function getOperatingSystem($userAgent = null): string
    {

        if ($userAgent === null) {
            return "Unknown OS";
        }

        $osArray = [
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
        ];

        foreach ($osArray as $regex => $value) {
            if (preg_match($regex, (string) $userAgent)) {
                return $value;
            }
        }

        return "Unknown OS - " . $userAgent;
    }


    public function getBrowser($userAgent = null): string
    {

        if ($userAgent === null) {
            return "Unknown Browser";
        }

        $browserArray = [
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
        ];

        foreach ($browserArray as $regex => $value) {
            if (preg_match($regex, (string) $userAgent)) {
                return $value;
            }
        }

        return "Unknown Browser - " . $userAgent;
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
        return isset($_SESSION['instanceType']) && $_SESSION['instanceType'] == 'remoteuser';
    }
    public function getLastRemoteSyncDateTime()
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
            if (stripos($sourceString, (string) $needle, $offset) !== false) {
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
        } catch (Exception | SystemException $exc) {
            //if ($this->db->getLastErrno() > 0) {
            error_log('Error in track_api_requests : ' . $this->db->getLastErrno());
            error_log('Error in track_api_requests : ' . $this->db->getLastError());
            error_log('Error in track_api_requests : ' . $this->db->getLastQuery());
            //}
            LoggerUtility::log('error', $exc->getFile() . ":" . $exc->getLine() . " - " . $exc->getMessage());
            return 0;
        }
    }

    public function updateSyncDateTime($testType, $testTable, $columnForWhereCondition, $sampleIds, $transactionId, $facilityIds, $labId, $syncType): void
    {
        try {
            $currentDateTime = DateUtility::getCurrentDateTime();
            // $batchSize = 100;


            // if (!empty($sampleIds)) {
            //     $sampleIdsBatches = array_chunk($sampleIds, $batchSize);

            //     foreach ($sampleIdsBatches as $batch) {
            //         $sampleIdsStr = "'" . implode("','", $batch) . "'";
            //         $formAttributes = [
            //             "remote{$syncType}Sync" => $currentDateTime,
            //             "{$syncType}SyncTransactionId" => $transactionId
            //         ];
            //         $formAttributes = $this->jsonToSetString(json_encode($formAttributes), 'form_attributes');
            //         $data = [
            //             'form_attributes' => $this->db->func($formAttributes),
            //             'data_sync' => 1
            //         ];
            //         $this->db->where($columnForWhereCondition, [$sampleIdsStr], 'IN');
            //         $this->db->update($testTable, $data);
            //     }
            // }

            if (!empty($facilityIds)) {
                $facilityIdsStr = implode(",", array_unique(array_filter($facilityIds)));
                $facilityAttributes = [
                    "remote{$syncType}Sync" => $currentDateTime,
                    "{$testType}Remote{$syncType}Sync" => $currentDateTime
                ];
                $facilityAttributes = $this->jsonToSetString(json_encode($facilityAttributes), 'facility_attributes');
                $data = [
                    'facility_attributes' => $this->db->func($facilityAttributes)
                ];
                $this->db->where('facility_id', [$facilityIdsStr], 'IN');
                $this->db->update('facility_details', $data);
            }

            if (!empty($labId)) {
                $facilityAttributes = [
                    "last{$syncType}Sync" => $currentDateTime,
                    "{$testType}Last{$syncType}Sync" => $currentDateTime
                ];
                $facilityAttributes = $this->jsonToSetString(json_encode($facilityAttributes), 'facility_attributes');
                $data = [
                    'facility_attributes' => $this->db->func($facilityAttributes)
                ];
                $this->db->where('facility_id', $labId);
                $this->db->update('facility_details', $data);
            }
        } catch (Exception | SystemException $exc) {
            if ($this->db->getLastErrno() > 0) {
                error_log($this->db->getLastError());
                error_log($this->db->getLastQuery());
            }
            LoggerUtility::log('error', "Error while updating timestamps : " . $exc->getFile() . ":" . $exc->getLine() . " - " . $exc->getMessage());
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

    public function getBarcodeImageContent($code, $type = 'C39', $width = 2, $height = 30, $color = [0, 0, 0]): string
    {
        $barcodeobj = new TCPDFBarcode($code, $type);
        return 'data:image/png;base64,' . base64_encode($barcodeobj->getBarcodePngData($width, $height, $color));
    }

    public function get2DBarcodeImageContent($code, $type = 'QRCODE', $width = 2, $height = 30, $color = [0, 0, 0])
    {
        $barcodeobj = new TCPDF2DBarcode($code, $type);
        return 'data:image/png;base64,' . base64_encode($barcodeobj->getBarcodePngData($width, $height, $color));
    }

    /**
     * Convert a JSON string to a string that can be used with JSON_SET()
     *
     * @param string|null $json The JSON string to convert
     * @param string $column The name of the JSON column
     * @param array|string $newData An optional array or JSON string of new key-value pairs to add to the JSON
     * @return string|null The string that can be used with JSON_SET()
     */
    public function jsonToSetString(?string $json, string $column, $newData = []): ?string
    {
        // Decode JSON string to array
        $jsonData = $json && MiscUtility::isJSON($json) ? json_decode($json, true) : [];

        // Decode newData if it's a string
        if (is_string($newData)) {
            $newData = json_decode($newData, true);
        }

        // Combine original data and new data
        $data = array_merge($jsonData, $newData);

        // Return null if there's nothing to set
        if (empty($data)) {
            return null;
        }

        // Build the set string
        $setString = '';
        foreach ($data as $key => $value) {
            $setString .= ', "$.' . $key . '", JSON_UNQUOTE(' . $this->jsonValueToString($value) . ')';
        }

        // Construct and return the JSON_SET query
        return 'JSON_SET(COALESCE(' . $column . ', "{}")' . $setString . ')';
    }

    /**
     * Convert a value to a JSON-compatible string representation
     *
     * @param mixed $value The value to convert
     * @return string The JSON-compatible string representation
     */
    private function jsonValueToString($value): string
    {
        if (is_null($value)) {
            return 'null';
        } elseif (is_bool($value)) {
            return $value ? 'true' : 'false';
        } elseif (is_numeric($value)) {
            return (string) $value;
        } elseif (is_array($value)) {
            return "'" . addslashes(json_encode($value)) . "'";
        } else {
            return "'" . addslashes((string) $value) . "'";
        }
    }



    public function stringToCamelCase($string, $character = "_", $capitalizeFirstCharacter = false)
    {
        $str = str_replace($character, '', ucwords((string) $string, $character));
        return (!$capitalizeFirstCharacter) ? lcfirst($str) : null;
    }

    public function getPrimaryKeyField($table)
    {
        if (empty($table)) {
            return null;
        }

        $table = $this->db->escape($table);
        $response = $this->db->rawQueryOne("SHOW KEYS FROM $table WHERE Key_name = 'PRIMARY'");
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
        $srcQuery = "SELECT DISTINCT source_of_request
                        FROM $table
                        WHERE source_of_request IS NOT NULL AND
                        source_of_request not like ''";
        return $this->db->rawQuery($srcQuery);
    }

    public function getSampleStatus($api = false)
    {
        $this->db->where("status", "active");
        $this->db->orderBy('status_name', "ASC");
        $result =  $this->db->get('r_sample_status');
        $response = [];
        if ($api) {
            foreach ($result as $row) {
                $response[$row['status_id']] = $row['status_name'];
            }
        } else {
            $response = $result;
        }
        return $response;
    }
    public function multipleColumnSearch($searchText, $allColumns)
    {
        $sWhere = [];

        if (!empty($searchText)) {
            // Split the search query into separate words
            $searchArray = explode(" ", (string) $searchText);
            $colSize = count($allColumns);

            foreach ($searchArray as $search) {
                $sWhereSub = [];

                for ($i = 0; $i < $colSize; $i++) {
                    if (empty($allColumns[$i])) {
                        continue;
                    }
                    $sWhereSub[] = "$allColumns[$i] LIKE '%$search%'";
                }

                $sWhere[] = " (" . implode(' OR ', array_filter($sWhereSub)) . ") ";
            }
        }

        return $sWhere;
    }

    public function generateDataTablesSorting($postData, $orderColumns)
    {
        $sOrder = "";
        if (isset($postData['iSortCol_0'])) {
            for ($i = 0; $i < (int) $postData['iSortingCols']; $i++) {
                if ($postData['bSortable_' . (int) $postData['iSortCol_' . $i]] == "true") {
                    $sOrder .= $orderColumns[(int) $postData['iSortCol_' . $i]] . " " . ($postData['sSortDir_' . $i]) . ", ";
                }
            }
            $sOrder = substr_replace($sOrder, "", -2);
        }

        return $sOrder;
    }

    public function generateSelectOptionsAPI($options): array
    {
        $i = 0;
        $response = [];
        foreach ($options as $key => $show) {
            $response[$i] = [];
            $response[$i]['value'] = $key;
            $response[$i]['show'] = $show;
            $i++;
        }
        return $response;
    }

    public function getTestingLabsAPI($testType = null, $user = null, $onlyActive = false, $module = false, $activeModule = null, $updatedDateTime = null): array
    {

        $query = "SELECT tl.test_type, f.facility_id, f.facility_name, f.facility_code, f.other_id, f.facility_state_id, f.facility_state, f.facility_district_id, f.facility_district, f.testing_points, f.status, gd.geo_id, gd.geo_name
                    from testing_labs AS tl
                    INNER JOIN facility_details as f ON tl.facility_id=f.facility_id
                    LEFT JOIN geographical_divisions as gd ON gd.geo_id=f.facility_state_id";
        $where = [];
        if (!empty($user)) {
            $facilityMap = $this->facilitiesService->getUserFacilityMap($user);
            if (!empty($facilityMap)) {
                $where[] = " f.facility_id IN (" . $facilityMap . ")";
            }
        }

        if (!$module) {
            $activeModule = str_replace(",", "','", (string) $activeModule);
            if (!empty($activeModule)) {
                $where[] = " tl.test_type IN ('" . $activeModule . "')";
            }
        }

        if (!empty($testType)) {
            $where[] = " tl.test_type like '$testType'";
        }

        if ($onlyActive) {
            $where[] = " f.status like 'active'";
        }

        if ($updatedDateTime) {
            $where[] = " f.updated_datetime >= '$updatedDateTime'";
        }
        $whereStr = "";
        if (!empty($where)) {
            $whereStr = " WHERE " . implode(" AND ", $where);
        }
        $query .= $whereStr . ' GROUP BY facility_name ORDER BY facility_name ASC';
        // die($query);
        $result = $this->db->rawQuery($query);
        $response = [];
        foreach ($result as $key => $row) {
            $response[$key] = [];
            $response[$key]['value'] = $row['facility_id'];
            $response[$key]['show'] = $row['facility_name'] . ' (' . $row['facility_code'] . ')';
            $response[$key]['state'] = $row['facility_state'];
            $response[$key]['district'] = $row['facility_district'];
            if (!$module) {
                $response[$key]['test_type'] = $row['test_type'];
                $response[$key]['monthly_target'] = $row['monthly_target'] ?? 0;
                $response[$key]['suppressed_monthly_target'] = $row['suppressed_monthly_target'] ?? 0;
            }
        }
        return $response;
    }

    public function getDistrictDetailsApi($user = null, $onlyActive = false, $updatedDateTime = null)
    {
        return once(function () use ($user, $onlyActive, $updatedDateTime) {

            $query = "SELECT f.facility_id, f.facility_name,
                    f.facility_code,
                    gd.geo_id,
                    gd.geo_name,
                    f.facility_district
                    FROM geographical_divisions AS gd
                    LEFT JOIN facility_details as f ON gd.geo_id=f.facility_state_id";
            $where = [];
            if (!empty($user)) {
                $facilityMap = $this->facilitiesService->getUserFacilityMap($user);
                if (!empty($facilityMap)) {
                    $where[] = " f.facility_id IN (" . $facilityMap . ")";
                }
            }

            if ($onlyActive) {
                $where[] = " f.status like 'active'";
            }

            if ($updatedDateTime) {
                $where[] = " gd.updated_datetime >= '$updatedDateTime'";
            }
            $whereStr = "";
            if (!empty($where)) {
                $whereStr = " WHERE " . implode(" AND ", $where);
            }
            $query .= $whereStr . ' GROUP BY facility_district ORDER BY facility_district ASC';
            // die($query);
            $result = $this->db->rawQuery($query);
            $response = [];
            foreach ($result as $key => $row) {
                $condition1 = " facility_district like '" . $row['facility_district'] . "%'";
                $condition2 = " geo_name like '" . $row['geo_name'] . "%'";

                $response[$key]['value'] = $row['facility_district'];
                $response[$key]['show'] = $row['facility_district'];
                $response[$key]['facilityDetails'] = $this->getSubFields('facility_details', 'facility_id', 'facility_name', $condition1);
                $response[$key]['provinceDetails'] = $this->getSubFields('geographical_divisions', 'geo_id', 'geo_name', $condition2);
            }
            return $response;
        });
    }

    public function getProvinceDetailsApi($user = null, $onlyActive = false, $updatedDateTime = null)
    {
        return once(function () use ($user, $onlyActive, $updatedDateTime) {

            $query = "SELECT f.facility_id,
                            f.facility_name,
                            f.facility_code,
                            gd.geo_id,
                            gd.geo_name,
                            f.facility_district,
                            f.facility_type
                    FROM geographical_divisions AS gd
                    LEFT JOIN facility_details as f ON gd.geo_id=f.facility_state_id";
            $where = [];
            if (!empty($user)) {
                $facilityMap = $this->facilitiesService->getUserFacilityMap($user);
                if (!empty($facilityMap)) {
                    $where[] = " f.facility_id IN (" . $facilityMap . ")";
                }
            }

            if ($onlyActive) {
                $where[] = " f.status like 'active'";
            }

            if ($updatedDateTime) {
                $where[] = " gd.updated_datetime >= '$updatedDateTime'";
            }
            $whereStr = "";
            if (!empty($where)) {
                $whereStr = " WHERE " . implode(" AND ", $where);
            }
            $query .= $whereStr . ' GROUP BY geo_name ORDER BY geo_name ASC';
            $result = $this->db->rawQuery($query);
            foreach ($result as $key => $row) {
                $condition1 = " facility_state like '" . $row['geo_name'] . "%'";

                $response[$key]['value'] = $row['geo_id'];
                $response[$key]['show'] = $row['geo_name'];
                $response[$key]['districtDetails'] = $this->getSubFields('facility_details', 'facility_district', 'facility_district', $condition1);
            }
            return $response;
        });
    }

    public function getAppHealthFacilitiesAPI($testType = null, $user = null, $onlyActive = false, $facilityType = 0, $module = false, $activeModule = null, $updatedDateTime = null): array
    {

        $query = "SELECT hf.test_type,
                        f.facility_id,
                        f.facility_name,
                        f.facility_code, f.other_id,
                        f.facility_state_id,
                        f.facility_state,
                        f.facility_district_id,
                        f.facility_district,
                        f.testing_points,
                        f.facility_attributes,
                        f.status,
                        gd.geo_id as province_id,
                        gd.geo_name as province_name
                    FROM health_facilities AS hf
                    INNER JOIN facility_details as f ON hf.facility_id=f.facility_id
                    INNER JOIN geographical_divisions as gd ON gd.geo_id=f.facility_state_id";
        $where = [];
        if (!empty($user)) {
            $facilityMap = $this->facilitiesService->getUserFacilityMap($user);
            if (!empty($facilityMap)) {
                $where[] = " f.facility_id IN (" . $facilityMap . ")";
            }
        }

        if (!$module && $facilityType == 1) {
            if (!empty($activeModule)) {
                $where[] = " hf.test_type IN ('" . $activeModule . "')";
            }
        }

        if (!empty($testType)) {
            $where[] = " hf.test_type like '$testType'";
        }

        if ($onlyActive) {
            $where[] = " f.status like 'active'";
        }

        if ($facilityType > 0) {
            $where[] = " f.facility_type = '$facilityType'";
        }
        if ($updatedDateTime) {
            $where[] = " f.updated_datetime >= '$updatedDateTime'";
        }
        $whereStr = "";
        if (!empty($where)) {
            $whereStr = " WHERE " . implode(" AND ", $where);
        }
        $query .= $whereStr . ' GROUP BY facility_name ORDER BY facility_name ASC ';
        $result = $this->db->rawQuery($query);
        $response = [];
        foreach ($result as $key => $row) {
            // $condition1 = " province_name like '" . $row['province_name'] . "%'";
            // $condition2 = " (facility_state like '" . $row['province_name'] . "%' OR facility_district_id like )";
            if ($module) {
                $response[$key]['value'] = $row['facility_id'];
                $response[$key]['show'] = $row['facility_name'] . ' (' . $row['facility_code'] . ')';
            } else {
                $response[$key]['facility_id'] = $row['facility_id'];
                $response[$key]['facility_name'] = $row['facility_name'];
                $response[$key]['facility_code'] = $row['facility_code'];
                $response[$key]['other_id'] = $row['other_id'];
                $response[$key]['facility_state_id'] = $row['facility_state_id'];
                $response[$key]['facility_state'] = $row['facility_state'];
                $response[$key]['facility_district_id'] = $row['facility_district_id'];
                $response[$key]['facility_district'] = $row['facility_district'];
                $response[$key]['facility_attributes'] = $row['facility_attributes'];
                $response[$key]['testing_points'] = $row['testing_points'];
                $response[$key]['status'] = $row['status'];
            }
            if (!$module && $facilityType == 1) {
                $response[$key]['test_type'] = $row['test_type'];
            }
        }
        return $response;
    }

    public function getSubFields($tableName, $primary, $name, $condition)
    {
        return once(function () use ($tableName, $primary, $name, $condition) {
            $query = "SELECT $primary, $name from $tableName where $condition group by $name";
            $result = $this->db->rawQuery($query);
            $response = [];
            foreach ($result as $key => $row) {
                $response[$key]['value'] = $row[$primary];
                $response[$key]['show'] = $row[$name];
            }
            return $response;
        });
    }

    public static function encryptViewQRCode($uniqueId)
    {
        $ciphering = "AES-128-CTR";
        $options = 0;
        $simple_string = $uniqueId . "&&&qr";
        $encryption_iv = random_bytes(openssl_cipher_iv_length($ciphering));
        $encryption_key = SYSTEM_CONFIG['tryCrypt'];
        return openssl_encrypt(
            $simple_string,
            $ciphering,
            $encryption_key,
            $options,
            $encryption_iv
        ) . '#' . bin2hex($encryption_iv);
    }
    public static function decryptViewQRCode($viewId)
    {
        $ciphering = "AES-128-CTR";
        $options = 0;
        if (strpos($viewId, '#') !== false) {
            list($encryptedData, $ivHex) = explode('#', $viewId, 2);
            // Convert the hex string back to binary for the IV
            $decryption_iv = hex2bin($ivHex);
        } else {
            // Use the fixed IV from config
            $encryptedData = $viewId;
            $decryption_iv = SYSTEM_CONFIG['tryCrypt'];
        }

        $decryption_key = SYSTEM_CONFIG['tryCrypt'];
        return openssl_decrypt(
            $encryptedData,
            $ciphering,
            $decryption_key,
            $options,
            $decryption_iv
        );
    }
}
