<?php

namespace App\Services;

use COUNTRY;
use Exception;
use Throwable;
use TCPDFBarcode;
use TCPDF2DBarcode;
use App\Utilities\DateUtility;
use App\Utilities\JsonUtility;
use App\Utilities\MemoUtility;
use App\Utilities\MiscUtility;
use App\Registries\AppRegistry;
use App\Services\ConfigService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Exceptions\SystemException;
use App\Services\FacilitiesService;
use App\Utilities\FileCacheUtility;
use Laminas\Diactoros\ServerRequest;
use App\Registries\ContainerRegistry;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Psr\Http\Message\ServerRequestInterface;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;


final class CommonService
{
    protected DatabaseService $db;
    protected FacilitiesService $facilitiesService;
    protected $fileCache;

    public function __construct(
        DatabaseService $db,
        FacilitiesService $facilitiesService,
        FileCacheUtility $fileCache
    ) {
        $this->db = $db;
        $this->facilitiesService = $facilitiesService;
        $this->fileCache = $fileCache;
    }


    public function getAppVersion($composerFilePath = ROOT_PATH . '/composer.json')
    {
        if (!file_exists(filename: $composerFilePath)) {
            return null;
        }

        $key = hash('sha256', 'appVersion-' . str_replace(DIRECTORY_SEPARATOR, "---", $composerFilePath));
        return $this->fileCache->get($key, function () use ($composerFilePath) {
            $composerJson = trim(file_get_contents($composerFilePath));
            return JsonUtility::extractJsonData($composerJson, 'version');
        });
    }

    public function getRemoteURL(): ?string
    {
        return $this->fileCache->get('remoteURL', function () {
            $remoteURL = SYSTEM_CONFIG['remoteURL'];
            if ($remoteURL == '' || empty($remoteURL)) {
                return null;
            }

            // Add https:// if no scheme is present
            if (!preg_match("~^(?:f|ht)tps?://~i", $remoteURL)) {
                $remoteURL = "https://$remoteURL";
            }

            // Parse the URL and get the scheme and host
            $parsedUrl = parse_url($remoteURL);
            if (isset($parsedUrl['scheme']) && isset($parsedUrl['host'])) {
                $baseUrl = $parsedUrl['scheme'] . '://' . $parsedUrl['host'];

                // Remove trailing slash if present
                return rtrim($baseUrl, '/');
            } else {
                return null;
            }
        });
    }

    public function getIntelisSyncAPIKey()
    {
        return $this->fileCache->get('sts-api-key', function () {
            $stsKey = SYSTEM_CONFIG['sts']['api_key'];
            if ($stsKey == '' || empty($stsKey)) {
                /** @var ConfigService $configService */
                $configService = ContainerRegistry::get(ConfigService::class);
                $stsKey = $updatedConfig['sts.api_key'] = $configService->generateAPIKeyForSTS();
                $configService->updateConfig($updatedConfig);
            }
            return $stsKey;
        });
    }

    public static function getClientIpAddress(?ServerRequestInterface $request = null): ?string
    {
        if ($request === null) {
            $request = AppRegistry::get('request');
        }

        $headers = [
            'CF-Connecting-IP',       // Cloudflare
            'Client-IP',              // Proxy
            'X-Forwarded-For',        // Load balancer/proxy
            'X-Forwarded',            // Proxy
            'X-Cluster-Client-IP',    // Cluster
            'Forwarded-For',          // Proxy
            'Forwarded',              // Proxy
            'X-Real-IP',              // Common fallback
        ];

        foreach ($headers as $header) {
            $headerValue = $request->getHeaderLine($header);
            if (!empty($headerValue)) {
                // In case of multiple IPs, take the first
                $ip = trim(explode(',', $headerValue)[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        // Fallback: REMOTE_ADDR from server params
        $serverParams = $request->getServerParams();
        $remoteAddr = $serverParams['REMOTE_ADDR'] ?? null;

        if ($remoteAddr && filter_var($remoteAddr, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return $remoteAddr;
        }

        return null;
    }


    public function getInstruments($staus = 'active')
    {
        $key = 'instruments_list';
        return $this->fileCache->get($key, function () use ($staus) {
            $this->db->where("status", $staus);
            $this->db->orderBy("machine_name", "ASC");
            return $this->db->get("instruments");
        }, ['instruments']);
    }

    public function getInstrumentsCount()
    {
        $key = 'instruments_count';
        return $this->fileCache->get($key, function () {
            $this->db->where("status", "active");
            return $this->db->getValue("instruments", "count(*)");
        }, ['instruments']);
    }

    public function getNonAdminUsersCount()
    {
        $key = 'users_count';
        return $this->fileCache->get($key, function () {
            $this->db->where("role_id != 1 and status = 'active'");
            return $this->db->getValue("user_details", "count(*)");
        }, ['users_count']);
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


        if (session_status() != PHP_SESSION_NONE && empty($_SESSION['instance'])) {
            $instanceResult = $this->db->rawQueryOne("SELECT vlsm_instance_id, instance_facility_name FROM s_vlsm_instance");
            $_SESSION['instance']['type'] = $allConfigs['sc_user_type'] ?? 'standalone';
            $_SESSION['instance']['labId'] = $allConfigs['sc_testing_lab_id'] ?? null;
            $_SESSION['instance']['facilityName'] = $instanceResult['instance_facility_name'];
        }

        return $name ? ($allConfigs[$name] ?? null) : ($allConfigs ?? []);
    }

    // get data from the global_config table from database
    public function getGlobalConfig(?string $name = null): string|array|null
    {
        $cacheKey = 'app_global_config';
        $db = $this->db;
        $allConfigs =  $this->fileCache->get($cacheKey, function () use ($db) {
            $returnConfig = [];
            $configResult = $db->get('global_config');
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
            $query .= " WHERE $condition";
        }

        if (!empty($group)) {
            $query .= " GROUP BY $group";
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


    /**
     *
     * @param string $tableName
     * @param string|array|null $conditions
     * @param string|array|null $columns
     * @param int|array|null $numRows number of rows to fetch
     * @return array|null
     * @throws Exception
     */
    public function fetchDataFromTable(string $tableName, string|array|null $conditions = [], string|array|null $columns = '*', $numRows = null): ?array
    {
        if ($this->db == null || empty($tableName)) {
            return null;
        }

        if (!isset($columns) || $columns == '' || empty($columns)) {
            $columns = '*';
        }

        if ($conditions !== '' && !empty($conditions)) {
            $conditions = is_array($conditions) ? $conditions : [$conditions];
            foreach ($conditions as $where) {
                $this->db->where($where);
            }
        }

        return $this->db->get($tableName, $numRows, $columns);
    }

    public static function encrypt($message, $key): string
    {
        try {
            $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

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
        } catch (Throwable $e) {
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
        } catch (Throwable $e) {
            return '';
        }
    }

    public static function crypto(?string $action, ?string $inputString, $key = null): ?string
    {
        if ($inputString === null || $inputString === '') {
            return $inputString;
        }
        return match ($action) {
            'encrypt' => self::encrypt($inputString, $key),
            'decrypt' => self::decrypt($inputString, $key),
            'doNothing' => $inputString,
            default => null,
        };
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

    public function getUserMappedProvinces($facilityMap = null)
    {
        return MemoUtility::remember(function () use ($facilityMap) {
            $facilityMap ??= $_SESSION['facilityMap'] ?? null;

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
                return "<option data-code='{$row['geo_code']}' data-province-id='{$row['geo_id']}' data-name='{$row['geo_name']}' value='{$row['geo_id']}##{$row['geo_code']}'> {$row['geo_name']} </option>";
            }, $result);

            array_unshift($options, "<option value=''>" . _translate("-- Select --") . " </option>");

            return implode('', $options);
        });
    }

    public function generateSelectOptions($optionList, $selectedOptions = [], $emptySelectText = false)
    {
        return MemoUtility::remember(function () use ($optionList, $selectedOptions, $emptySelectText) {

            $response = '';

            if (empty($optionList)) {
                return '';
            }
            if ($emptySelectText !== false) {
                $response .= "<option value=''>" . htmlspecialchars($emptySelectText) . "</option>";
            }

            foreach ($optionList as $optId => $optName) {
                $selectedText = '';
                if (
                    !empty($selectedOptions) &&
                    (
                        ($optId == $selectedOptions) ||
                        (is_array($selectedOptions) && in_array($optId, $selectedOptions))
                    )
                ) {
                    $selectedText = "selected='selected'";
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
        return MemoUtility::remember(function () use ($tablename, $fieldname, $fieldValue, $condition) {
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

        if (empty($fieldValue) || empty($fieldName) || empty($tableName) || empty($returnFieldName)) {
            return null;
        }


        return MemoUtility::remember(function () use ($fieldValue, $fieldName, $tableName, $returnFieldName) {
            $this->db->where($fieldName, $fieldValue);
            return $this->db->getValue($tableName, $returnFieldName);
        });
    }


    public function getLocaleList(?int $formId = null)
    {
        if (empty($formId) || $formId == 0) {
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
        $this->db->where("vlsm_country_id", $this->getGlobalConfig('vl_form'));
        return $this->db->getValue("s_available_country_forms", "short_name");
    }

    public function trackQRPageViews($type, $typeId, $sampleCode)
    {

        /** @var ServerRequest $request */
        $request = AppRegistry::get('request');

        $data = [
            'test_type' => $type,
            'test_type_id' => $typeId,
            'sample_code' => $sampleCode,
            'browser' => $this->getClientBrowser($request),
            'operating_system' => $this->getClientOS($request),
            'date_time' => DateUtility::getCurrentDateTime(),
            'ip_address' => $this->getClientIpAddress($request),
        ];

        $this->db->insert('track_qr_code_page', $data);
    }

    public function getClientOS(?ServerRequest $request = null): string
    {
        if ($request === null) {
            $request = AppRegistry::get('request');
        }

        $userAgent = (string) ($request->getServerParams()['HTTP_USER_AGENT'] ?? '');

        if ($userAgent === '') {
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
            if (preg_match($regex, $userAgent)) {
                return $value;
            }
        }

        // Return fallback message
        return "Unknown OS";
    }



    public function getClientBrowser(?ServerRequest $request = null): string
    {
        if ($request === null) {
            $request = AppRegistry::get('request');
        }

        $userAgent = (string) ($request->getServerParams()['HTTP_USER_AGENT'] ?? '');

        if ($userAgent === '') {
            return "Unknown Browser";
        }

        $browserArray = [
            '/brave/i' => 'Brave',
            '/edge/i' => 'Edge',
            '/chrome/i' => 'Chrome', // Must come before Safari
            '/safari/i' => 'Safari',
            '/firefox/i' => 'Firefox',
            '/opera|opr/i' => 'Opera', // Include OPR for modern Opera
            '/msie|trident/i' => 'Internet Explorer',
            '/netscape/i' => 'Netscape',
            '/maxthon/i' => 'Maxthon',
            '/konqueror/i' => 'Konqueror',
            '/mobile/i' => 'Mobile Browser',
            '/applewebkit/i' => 'Webkit Browser',
        ];

        foreach ($browserArray as $regex => $value) {
            if (preg_match($regex, $userAgent)) {
                return $value;
            }
        }

        return "Unknown Browser";
    }

    public static function isAjaxRequest(ServerRequestInterface|ServerRequest $request): bool
    {
        return strtolower($request->getHeaderLine('X-Requested-With')) === 'xmlhttprequest';
    }

    public static function isCliRequest(): bool
    {
        return php_sapi_name() === 'cli';
    }

    // Helper function to check if the current URI is in the excluded list
    public static function isExcludedUri(string $uri, $excludedUris): bool
    {
        foreach ($excludedUris as $excludedUri) {
            if (fnmatch($excludedUri, $uri)) {
                return true;
            }
        }
        return false;
    }


    // Returns the current Instance ID
    public function getInstanceId(): ?string
    {
        return $this->db->getValue("s_vlsm_instance", "vlsm_instance_id");
    }
    public function getSTSToken(): ?string
    {
        return $this->db->getValue("s_vlsm_instance", "sts_token");
    }

    public function getInstanceType(): ?string
    {
        return $_SESSION['instance']['type'] ?? $this->getSystemConfig('sc_user_type') ?? $this->db->getValue("s_vlsm_instance", "instance_type");
    }

    public function isSTSInstance(): bool
    {
        return $this->getInstanceType() === 'remoteuser';
    }

    public function isLISInstance(): bool
    {
        return $this->getInstanceType() === 'vluser';
    }

    public function isStandaloneInstance(): bool
    {
        return $this->getInstanceType() === 'standalone';
    }


    public function getLISLabName(): ?string
    {
        return $this->fileCache->get('lisLabName', function () {
            if ($this->isLISInstance()) {
                $labId = $this->getSystemConfig('sc_testing_lab_id') ?? null;
                if (!empty($labId)) {
                    $lab = $this->facilitiesService->getFacilityById($labId);
                    return $lab['facility_name'] ?? null;
                }
            }
            return null;
        });
    }

    public function getLastSTSSyncDateTime()
    {
        if ($this->isSTSInstance()) {
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
            $requestData = JsonUtility::encodeUtf8Json($requestData ?? '{}');
            $responseData = JsonUtility::encodeUtf8Json($responseData ?? '{}');

            $folderPath = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'track-api';
            if (!empty($requestData) && $requestData != '[]') {
                MiscUtility::makeDirectory($folderPath . DIRECTORY_SEPARATOR . 'requests');
                MiscUtility::dataToZippedFile($requestData, "$folderPath/requests/$transactionId.json");
            }
            if (!empty($responseData) && $responseData != '[]') {
                MiscUtility::makeDirectory($folderPath . DIRECTORY_SEPARATOR . 'responses');
                MiscUtility::dataToZippedFile($responseData, "$folderPath/responses/$transactionId.json");
            }
            $this->db->reset();
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
        } catch (Throwable $exc) {
            if (!empty($this->db->getLastError())) {
                LoggerUtility::log('error', 'Error in track_api_requests : ' . $this->db->getLastErrno() . ':' . $this->db->getLastError());
            }
            LoggerUtility::log('error', $exc->getFile() . ":" . $exc->getLine() . " - " . $exc->getMessage());
            return 0;
        }
    }

    public function updateSyncDateTime($testType, $facilityIds, $labId, $syncType): void
    {
        try {


            if ($testType == 'generic-tests') {
                $testType = 'genericTests';
            }

            $currentDateTime = DateUtility::getCurrentDateTime();

            if (!empty($facilityIds)) {

                $facilityAttributes = [
                    "remote{$syncType}Sync" => $currentDateTime,
                    "{$testType}Remote{$syncType}Sync" => $currentDateTime
                ];

                $facilityAttributes = JsonUtility::jsonToSetString(json_encode($facilityAttributes), 'facility_attributes');

                $data = [
                    'facility_attributes' => $this->db->func($facilityAttributes)
                ];

                if (is_array($facilityIds)) {
                    $facilityIds = implode(",", array_unique(array_filter($facilityIds)));
                    $this->db->where('facility_id', [$facilityIds], 'IN');
                } else {
                    $this->db->where('facility_id', $facilityIds);
                }

                $this->db->update('facility_details', $data);
            }

            if (!empty($labId)) {
                $facilityAttributes = [
                    "last{$syncType}Sync" => $currentDateTime,
                    "{$testType}Last{$syncType}Sync" => $currentDateTime
                ];
                $facilityAttributes = JsonUtility::jsonToSetString(json_encode($facilityAttributes), 'facility_attributes');
                $data = [
                    'facility_attributes' => $this->db->func($facilityAttributes)
                ];

                if (is_array($labId)) {
                    $labId = implode(",", array_unique(array_filter($labId)));
                    $this->db->where('facility_id', [$labId], 'IN');
                } else {
                    $this->db->where('facility_id', $labId);
                }
                $this->db->update('facility_details', $data);
            }
        } catch (Throwable $e) {
            LoggerUtility::logError("Error while updating timestamps : " . $e->getFile() . ':' . $e->getLine() . ":" . $this->db->getLastError());
            LoggerUtility::logError("Error while updating timestamps : " . $e->getFile() . ':' . $e->getLine() . ":" . $this->db->getLastQuery());
            LoggerUtility::logError("Error while updating timestamps : " . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    public function updateTestRequestsSyncDateTime($testType, $facilityIds, $labId): void
    {
        $this->updateSyncDateTime($testType, $facilityIds, $labId, 'Requests');
    }

    public function updateResultSyncDateTime($testType, $facilityIds, $labId): void
    {
        $this->updateSyncDateTime($testType, $facilityIds, $labId, 'Results');
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
        $key = 'implementation_partners_list';
        return $this->fileCache->get($key, function () {
            $this->db->where("i_partner_status", "active");
            $this->db->orderBy('i_partner_name', "ASC");
            return $this->db->get('r_implementation_partners');
        }, ['r_implementation_partners']);
    }

    public function getFundingSources()
    {
        $key = 'funding_sources_list';
        return $this->fileCache->get($key, function () {
            $this->db->where("funding_source_status", "active");
            $this->db->orderBy('funding_source_name', "ASC");
            return $this->db->get('r_funding_sources');
        }, ['r_funding_sources']);
    }

    public function getSourcesOfTestRequests($table, $asNameValuePair = false)
    {
        $key = "sources_of_test_requests_list_{$table}_" . ($asNameValuePair ? 'name_value' : 'full_data');
        return $this->fileCache->get($key, function () use ($table, $asNameValuePair) {

            $commonSources = [
                'vlsm' => 'LIS',
                'lis' => 'LIS',
                'vlsts' => 'STS',
                'sts' => 'STS',
                'app' => 'Tablet',
                'api' => 'API',
                'dhis2' => 'DHIS2'
            ];

            $srcQuery = "SELECT DISTINCT source_of_request
                        FROM $table
                        WHERE IFNULL(source_of_request, '') != ''";
            $data = $this->db->rawQuery($srcQuery);
            if ($asNameValuePair) {
                $processedData = [];
                foreach ($data as $list) {
                    $processedData[$list['source_of_request']] = !empty($commonSources[$list['source_of_request']]) ? $commonSources[$list['source_of_request']] : $list['source_of_request'];
                }
                return $processedData;
            }

            return $data;
        }, ['sources_of_test_requests_list', $table]);
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
    public function multipleColumnSearch(?string $searchText, ?array $allColumns, bool $splitSearch = false): ?string
    {
        // Initialize the where clause array
        $sWhere = [];

        // Ensure the search text and columns are not empty
        if (!empty($searchText) && !empty($allColumns) && is_array($allColumns)) {
            // Trim the search text
            $searchText = trim($searchText);

            // Add the condition for the entire search string
            $sWhereSub = [];
            foreach ($allColumns as $column) {
                if (!empty($column)) {
                    // Escape the entire search text to prevent SQL injection
                    $escapedSearchText = $this->db->escape($searchText);
                    // Add the search condition for the current column
                    $sWhereSub[] = "$column LIKE '%$escapedSearchText%'";
                }
            }
            if (!empty($sWhereSub)) {
                $sWhere[] = " (" . implode(' OR ', $sWhereSub) . ") ";
            }

            if ($splitSearch) {
                // Split the search query into separate words
                $searchArray = array_filter(explode(" ", $searchText));

                // Loop through each search word
                foreach ($searchArray as $search) {
                    if (!empty($search)) {
                        // Initialize sub where clause array
                        $sWhereSub = [];

                        // Loop through each column to generate search conditions for each word
                        foreach ($allColumns as $column) {
                            if (!empty($column)) {
                                // Escape the search term to prevent SQL injection
                                $escapedSearch = $this->db->escape($search);
                                // Add the search condition for the current column
                                $sWhereSub[] = "$column LIKE '%$escapedSearch%'";
                            }
                        }
                        if (!empty($sWhereSub)) {
                            $sWhere[] = " (" . implode(' OR ', $sWhereSub) . ") ";
                        }
                    }
                }
            }
        }

        // Combine all where clauses into a single SQL string
        return !empty($sWhere) ? implode(' OR ', $sWhere) : null;
    }


    public function generateDataTablesSorting($postData, $orderColumns)
    {
        $sOrder = null;
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

        $query = "SELECT tl.test_type,
                        f.facility_id,
                        f.facility_name,
                        f.facility_code,
                        f.other_id,
                        f.facility_state_id,
                        f.facility_state,
                        f.facility_district_id,
                        f.facility_district,
                        f.testing_points,
                        f.status,
                        gd.geo_id,
                        gd.geo_name
                    FROM testing_labs AS tl
                    INNER JOIN facility_details as f ON tl.facility_id=f.facility_id
                    LEFT JOIN geographical_divisions as gd ON gd.geo_id=f.facility_state_id";
        $where = [];
        if (!empty($user)) {
            $facilityMap = $this->facilitiesService->getUserFacilityMap($user);
            if (!empty($facilityMap)) {
                $where[] = " f.facility_id IN ($facilityMap)";
            }
        }
        if (!$module) {
            $activeModule = str_replace(",", "','", (string) $activeModule);
            if (!empty($activeModule)) {
                $where[] = " tl.test_type IN ('$activeModule')";
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
        if (!empty($activeModule)) {
            $query .= $whereStr . ' ORDER BY facility_name ASC';
        } else {
            $query .= $whereStr . ' GROUP BY facility_name ORDER BY facility_name ASC';
        }
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

    public function getProvinceDetailsApi($user = null, $onlyActive = false, $updatedDateTime = null)
    {
        // Query to get provinces (top-level geographical divisions)
        $query = "
        SELECT DISTINCT
            p.geo_id as province_id,
            p.geo_name as province_name
        FROM
            geographical_divisions p
        LEFT JOIN
            geographical_divisions d ON d.geo_parent = p.geo_id
        LEFT JOIN
            facility_details f ON d.geo_id = f.facility_district_id
        WHERE
            p.geo_parent = 0";  // Only get top-level provinces

        $where = [];

        // Add user facility mapping filter if user is provided
        if (!empty($user)) {
            $facilityMap = $this->facilitiesService->getUserFacilityMap($user);
            if (!empty($facilityMap)) {
                $where[] = " f.facility_id IN ($facilityMap)";
            }
        }

        // Add active status filter if requested
        if ($onlyActive) {
            $where[] = " f.status = 'active'";
        }

        // Add last update datetime filter if provided
        if ($updatedDateTime) {
            $where[] = " p.updated_datetime >= '$updatedDateTime'";
        }

        // Combine all WHERE conditions
        if (!empty($where)) {
            $query .= " AND " . implode(" AND ", $where);
        }

        // Order by province name
        $query .= " ORDER BY p.geo_name ASC";

        // Execute the query
        $result = $this->db->rawQuery($query);

        $response = [];
        foreach ($result as $key => $row) {
            $response[$key]['value'] = $row['province_id'];
            $response[$key]['show'] = $row['province_name'];

            // Get districts in this province
            $response[$key]['districtDetails'] = $this->getSubFields(
                'geographical_divisions',
                'geo_id',
                'geo_name',
                "geo_parent = {$row['province_id']}"
            );
        }

        return $response;
    }

    public function getDistrictDetailsApi($user = null, $onlyActive = false, $updatedDateTime = null)
    {
        // Query to get districts with their province information
        $query = "
        SELECT DISTINCT
            d.geo_id as district_id,
            d.geo_name as district_name,
            p.geo_id as province_id,
            p.geo_name as province_name
        FROM
            geographical_divisions d
        JOIN
            geographical_divisions p ON d.geo_parent = p.geo_id
        LEFT JOIN
            facility_details f ON d.geo_id = f.facility_district_id
        WHERE
            p.geo_parent = 0";  // Ensure p is a province

        $where = [];

        // Add user facility mapping filter if user is provided
        if (!empty($user)) {
            $facilityMap = $this->facilitiesService->getUserFacilityMap($user);
            if (!empty($facilityMap)) {
                $where[] = " f.facility_id IN ($facilityMap)";
            }
        }

        // Add active status filter if requested
        if ($onlyActive) {
            $where[] = " f.status = 'active'";
        }

        // Add last update datetime filter if provided
        if ($updatedDateTime) {
            $where[] = " d.updated_datetime >= '$updatedDateTime'";
        }

        // Combine all WHERE conditions
        if (!empty($where)) {
            $query .= " AND " . implode(" AND ", $where);
        }

        // Order by district name
        $query .= " ORDER BY d.geo_name ASC";

        // Execute the query
        $result = $this->db->rawQuery($query);

        $response = [];
        foreach ($result as $key => $row) {
            $response[$key]['value'] = $row['district_id'];
            $response[$key]['show'] = $row['district_name'];

            // Get facilities in this district
            $response[$key]['facilityDetails'] = $this->getSubFields(
                'facility_details',
                'facility_id',
                'facility_name',
                "facility_district_id = {$row['district_id']}"
            );

            // Format province details directly from the query result
            $response[$key]['provinceDetails'] = [
                [
                    'value' => $row['province_id'],
                    'show' => $row['province_name']
                ]
            ];
        }

        return $response;
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
                $where[] = " f.facility_id IN ($facilityMap)";
            }
        }

        if (!$module && $facilityType == 1) {
            if (!empty($activeModule)) {
                $where[] = " hf.test_type IN ('$activeModule')";
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
        $query .= "$whereStr GROUP BY facility_name ORDER BY facility_name ASC ";
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

    public function getSubFields($tableName, $primary, $name, $condition = null)
    {

        if (!empty($condition)) {
            $this->db->where($condition);
        }
        $this->db->orderBy($name, "ASC");
        $result = $this->db->get($tableName);

        $response = [];
        foreach ($result as $key => $row) {
            $response[$key]['value'] = $row[$primary];
            $response[$key]['show'] = $row[$name];
        }
        return $response;
    }

    /**
     * Function to check if STS URL is working by hitting the version API
     */
    public static function validateStsUrl($url, $labId = null)
    {
        // Get VERSION constant or default
        $version = defined('VERSION') ? VERSION : '1.0';

        // Use current lab ID if available, or a default test value
        $testLabId = $labId ?: '1';

        $apiUrl = rtrim($url, '/') . "/api/version.php?labId=" . $testLabId . "&version=" . $version;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpCode === 200;
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
        if (str_contains($viewId, '#')) {
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

    public function applyBordersToSheet(Worksheet $sheet)
    {
        // Retrieve the highest row and highest column
        $highestRow = $sheet->getHighestRow();
        $highestColumn = $sheet->getHighestColumn();

        // Define border style
        $borderStyle = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => '000000'],
                ],
            ],
        ];

        // Calculate the range that covers all your data
        $range = "A1:{$highestColumn}{$highestRow}";

        // Apply border style to the range
        $sheet->getStyle($range)->applyFromArray($borderStyle);

        return $sheet;
    }

    public function setAllColumnWidths(Worksheet $sheet, $width = 20)
    {
        // Set the default width for all columns in the sheet
        $sheet->getDefaultColumnDimension()->setWidth($width);

        return $sheet;
    }

    public function centerAndBoldRowInSheet(Worksheet $sheet, $startCell = 'A1')
    {
        // Extract the row number from the start cell
        $startRow = preg_replace('/[^0-9]/', '', $startCell);

        // Retrieve the highest column
        $highestColumn = $sheet->getHighestColumn();

        // Calculate the range for the entire row
        $range = $startCell . ':' . $highestColumn . $startRow;

        // Set alignment to center and font to bold for the range
        $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle($range)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle($range)->getFont()->setBold(true);

        return $sheet;
    }

    public function getTableFieldsAsArray(string $tableName, array $unwantedColumns = []): array
    {
        $tableFieldsAsArray = [];
        if (!empty($tableName) && $tableName != '') {
            try {
                $tableFieldsAsArray = $this->db->getTableFieldsAsArray($tableName, $unwantedColumns);
            } catch (Throwable $e) {
                $tableFieldsAsArray = [];
                LoggerUtility::logError($e->getMessage(), [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'last_db_error' => $this->db->getLastError(),
                    'last_db_query' => $this->db->getLastQuery(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        return $tableFieldsAsArray;
    }

    public function updateCurrentDateTime($tables)
    {
        $status = false;

        if (!isset($tables) || empty($tables)) {
            return false;
        }
        if (is_array($tables)) {
            foreach ($tables as $table) {
                $status = $this->db->update($table, ["updated_datetime" => DateUtility::getCurrentDateTime()]);
            }
        } else {
            $status = $this->db->update($tables, ["updated_datetime" => DateUtility::getCurrentDateTime()]);
        }
        return $status;
    }

    public function getMaxSampleBatchId($table)
    {
        $maxSampleBatchIdQuery = "SELECT MAX(CONVERT(sample_batch_id, UNSIGNED)) AS max_sample_batch_id FROM $table";
        $result = $this->db->rawQuery($maxSampleBatchIdQuery);
        return isset($result[0]['max_sample_batch_id']) ? (int)$result[0]['max_sample_batch_id'] : 0;
    }
    public function getMaxBatchId($table)
    {
        $maxBatchIdQuery = "SELECT MAX(CONVERT(batch_id, UNSIGNED)) AS max_batch_id FROM $table";
        $result = $this->db->rawQuery($maxBatchIdQuery);
        return isset($result[0]['max_batch_id']) ? (int)$result[0]['max_batch_id'] : 0;
    }

    public function updateNullColumnsWithDefaults($tableName, $columnsDefaults)
    {
        // Construct the SET clause of the update query
        $setClauses = [];
        foreach ($columnsDefaults as $column => $defaultValue) {
            $setClauses[] = "$column = CASE WHEN $column IS NULL THEN '$defaultValue' ELSE $column END";
        }
        $setClause = implode(", ", $setClauses);

        // Construct the full update query
        $updateQuery = "UPDATE $tableName SET $setClause;";

        // Execute the update query
        $this->db->rawQuery($updateQuery);
    }

    public static function validateUploadedFile($uploadedFilePath, $formatFilePath)
    {
        // Load the uploaded Excel file
        $uploadedSpreadsheet = IOFactory::load($uploadedFilePath);

        // Load the template Excel file
        $templateSpreadsheet = IOFactory::load($formatFilePath);

        // Get the first sheet of the uploaded file
        $uploadedSheet = $uploadedSpreadsheet->getSheet(0);

        // Get the first sheet of the template file
        $templateSheet = $templateSpreadsheet->getSheet(0);

        // Extract headers from both sheets for comparison
        $uploadedHeaders = $uploadedSheet->rangeToArray('A1:Z1')[0];  // Adjust range as needed
        $templateHeaders = $templateSheet->rangeToArray('A1:Z1')[0];  // Adjust range as needed

        // Normalize headers for case-insensitive comparison and remove spaces/newlines
        $normalizedUploadedHeaders = array_map(function ($header) {
            return strtolower(preg_replace('/\s+/', '', $header));
        }, $uploadedHeaders);

        $normalizedTemplateHeaders = array_map(function ($header) {
            return strtolower(preg_replace('/\s+/', '', $header));
        }, $templateHeaders);

        // Compare the column headers
        if ($normalizedUploadedHeaders !== $normalizedTemplateHeaders) {
            // The column headers do not match the template
            return false;
        }

        // Compare additional formatting, data types, or any other specific requirements
        // ...

        // If all checks pass, return true
        return true;
    }
}
