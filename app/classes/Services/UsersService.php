<?php

namespace App\Services;

use DateTime;
use MysqliDb;
use App\Utilities\DateUtility;
use App\Registries\ContainerRegistry;
use Laminas\Diactoros\ServerRequest;



class UsersService
{

    protected ?MysqliDb $db = null;
    protected $applicationConfig = null;
    protected string $table = 'user_details';
    protected CommonService $commonService;

    public function __construct($db = null, $applicationConfig = null, $commonService = null)
    {
        $this->db = $db ?? ContainerRegistry::get('db');
        $this->applicationConfig = $applicationConfig;
        $this->commonService = $commonService;
    }

    public function isAllowed($currentRequest): bool
    {
        return once(function () use ($currentRequest) {
            if (empty($_SESSION['privileges']) || empty($currentRequest)) {
                return false;
            }

            if ($currentRequest instanceof ServerRequest) {
                $uri = $currentRequest->getUri();
                $path = $uri->getPath();
                $query = $uri->getQuery();
                // Clean up the URI Path for double slashes or dots
                $path = preg_replace('/([\/.])\1+/', '$1', $path);
                $baseFileName = basename($path);
                $currentRequest = $path . ($query ? '?' . $query : '');
            } else {
                $parsedInput = parse_url($currentRequest);
                $path = $parsedInput['path'];
                $baseFileName = basename($path);
            }

            $sharedPrivileges = $this->getSharedPrivileges();

            // check if $currentRequest contains a '?'
            if (strpos($currentRequest, '?') !== false) {
                list($pathWithoutQuery, $queryString) = explode('?', $currentRequest);
                $queryParams = explode('&', $queryString);

                // Initialize the array with the URL with all query parameters
                $requestArray = [$currentRequest];

                // Remove query parameters one by one from the end
                // and add the result to the array
                for ($i = count($queryParams) - 1; $i > 0; $i--) {
                    $currentRequest = substr($currentRequest, 0, strrpos($currentRequest, '&'));
                    $requestArray[] = $currentRequest;
                }

                // Add the URL without any query parameters to the array
                $requestArray[] = $pathWithoutQuery;
            } else {
                // Handle case where $currentRequest does not contain a '?'
                $requestArray = [$currentRequest];
            }

            $requestArray[] = $baseFileName;
            $requestArray[] = $path;

            // Does the current file share privileges with another privilege?
            foreach ($requestArray as $requestUrl) {
                if (isset($sharedPrivileges[$requestUrl])) {
                    $currentRequest = $sharedPrivileges[$requestUrl];
                    break;
                }
            }
            $requestUrls = [$path, $currentRequest, $baseFileName];
            $privileges = $this->getAllPrivileges();

            foreach ($requestUrls as $requestUrl) {
                if (isset($privileges[$requestUrl])) {
                    return true;
                }
            }
            return false;
        });
    }

    public function getAllPrivileges(): array
    {
        return once(function () {
            return array_flip(array_merge($this->getSkippedPrivileges(), $_SESSION['privileges']));
        });
    }

    public function getSharedPrivileges(): array
    {
        return once(function () {
            // on the left put intermediate/inner file, on the right put the file
            // which has entry in privileges table.
            $sharedPrivileges = [
                '/import-result/imported-results.php'           => '/import-result/import-file.php',
                '/import-result/importedStatistics.php'         => '/import-result/import-file.php',
                'mapTestType.php'                               => 'addFacility.php',
                'add-province.php'                              => 'province-details.php',
                'edit-province.php'                             => 'province-details.php',
                'implementation-partners.php'                   => 'province-details.php',
                'add-implementation-partners.php'               => 'province-details.php',
                'edit-implementation-partners.php'              => 'province-details.php',
                'funding-sources.php'                           => 'province-details.php',
                'add-funding-sources.php'                       => 'province-details.php',
                'edit-funding-sources.php'                      => 'province-details.php'
            ];

            if (
                isset($this->applicationConfig['modules']['genericTests']) &&
                $this->applicationConfig['modules']['genericTests'] === true
            ) {
                $sharedGenericPrivileges = [
                    '/batch/delete-batch.php?type=generic-tests'              => '/batch/edit-batch.php?type=generic-tests',
                    '/batch/generate-batch-pdf.php?type=generic-tests'        => '/batch/batches.php?type=generic-tests',
                    '/batch/add-batch-position.php?type=generic-tests'         => '/batch/add-batch.php?type=generic-tests',
                    '/batch/edit-batch-position.php?type=generic-tests'        => '/batch/edit-batch.php?type=generic-tests',
                    '/generic-tests/results/update-generic-test-result.php' => '/generic-tests/results/generic-test-results.php'
                ];

                $sharedPrivileges = array_merge($sharedPrivileges, $sharedGenericPrivileges);
            }

            if (
                isset($this->applicationConfig['modules']['vl']) &&
                $this->applicationConfig['modules']['vl'] === true
            ) {
                $sharedVLPrivileges = [
                    '/batch/delete-batch.php?type=vl'              => '/batch/edit-batch.php?type=vl',
                    '/batch/generate-batch-pdf.php?type=vl'        => '/batch/batches.php?type=vl',
                    '/batch/add-batch-position.php?type=vl'         => '/batch/add-batch.php?type=vl',
                    '/batch/edit-batch-position.php?type=vl'        => '/batch/edit-batch.php?type=vl',
                    '/vl/results/updateVlTestResult.php'                => '/vl/results/vlTestResult.php',
                    '/vl/results/vl-failed-results.php'                 => '/vl/results/vlTestResult.php',
                    '/vl/reference/add-vl-art-code-details.php'           => '/vl/reference/vl-art-code-details.php',
                    '/vl/reference/edit-vl-art-code-details.php'          => '/vl/reference/vl-art-code-details.php',
                    '/vl/reference/add-vl-results.php'                    => '/vl/reference/vl-art-code-details.php',
                    '/vl/reference/edit-vl-results.php'                   => '/vl/reference/vl-art-code-details.php',
                    '/vl/reference/vl-sample-rejection-reasons.php'       => '/vl/reference/vl-art-code-details.php',
                    '/vl/reference/add-vl-sample-rejection-reasons.php'   => '/vl/reference/vl-art-code-details.php',
                    '/vl/reference/edit-vl-sample-rejection-reasons.php'  => '/vl/reference/vl-art-code-details.php',
                    '/vl/reference/vl-sample-type.php'                    => '/vl/reference/vl-art-code-details.php',
                    '/vl/reference/edit-vl-sample-type.php'               => '/vl/reference/vl-art-code-details.php',
                    '/vl/reference/add-vl-sample-type.php'                => '/vl/reference/vl-art-code-details.php',
                    '/vl/reference/vl-test-reasons.php'                   => '/vl/reference/vl-art-code-details.php',
                    '/vl/reference/add-vl-test-reasons.php'               => '/vl/reference/vl-art-code-details.php',
                    '/vl/reference/edit-vl-test-reasons.php'              => '/vl/reference/vl-art-code-details.php',
                    '/vl/reference/vl-test-failure-reasons.php'           => '/vl/reference/vl-art-code-details.php',
                    '/vl/referencea/dd-vl-test-failure-reason.php'        => '/vl/reference/vl-art-code-details.php',
                    '/vl/reference/edit-vl-test-failure-reason.php'       => '/vl/reference/vl-art-code-details.php',
                    '/vl/program-management/vlTestingTargetReport.php'    => '/vl/program-management/vlMonthlyThresholdReport.php',
                    '/vl/program-management/vlSuppressedTargetReport.php' => '/vl/program-management/vlMonthlyThresholdReport.php'
                ];

                $sharedPrivileges = array_merge($sharedPrivileges, $sharedVLPrivileges);
            }

            if (
                isset($this->applicationConfig['modules']['eid']) &&
                $this->applicationConfig['modules']['eid'] === true
            ) {
                $sharedEIDPrivileges = [
                    '/batch/delete-batch.php?type=eid'              => '/batch/edit-batch.php?type=eid',
                    '/batch/generate-batch-pdf.php?type=eid'        => '/batch/batches.php?type=eid',
                    '/batch/add-batch-position.php?type=eid'         => '/batch/add-batch.php?type=eid',
                    '/batch/edit-batch-position.php?type=eid'        => '/batch/edit-batch.php?type=eid',
                    '/eid/results/eid-update-result.php'                 => '/eid/results/eid-manual-results.php',
                    '/eid/results/eid-failed-results.php'                => '/eid/results/eid-manual-results.php',
                    '/eid/requests/eid-bulk-import-request.php'           => '/eid/requests/eid-add-request.php',
                    '/eid/reference/eid-sample-rejection-reasons.php'      => '/eid/reference/eid-sample-type.php',
                    '/eid/reference/add-eid-sample-rejection-reasons.php'  => '/eid/reference/eid-sample-type.php',
                    'edit-eid-sample-rejection-reasons.php' => '/eid/reference/eid-sample-type.php',
                    '/eid/reference/add-eid-sample-type.php'               => '/eid/reference/eid-sample-type.php',
                    '/eid/reference/edit-eid-sample-type.php'              => '/eid/reference/eid-sample-type.php',
                    '/eid/reference/eid-test-reasons.php'                  => '/eid/reference/eid-sample-type.php',
                    '/eid/reference/add-eid-test-reasons.php'              => '/eid/reference/eid-sample-type.php',
                    '/eid/reference/edit-eid-test-reasons.php'             => '/eid/reference/eid-sample-type.php',
                    '/eid/reference/eid-results.php'                       => '/eid/reference/eid-sample-type.php',
                    '/eid/reference/add-eid-results.php'                   => '/eid/reference/eid-sample-type.php',
                    '/eid/reference/edit-eid-results.php'                  => '/eid/reference/eid-sample-type.php',
                    '/eid/management/eidTestingTargetReport.php'            => '/eid/management/eidMonthlyThresholdReport.php',
                    '/eid/management/eidSuppressedTargetReport.php'         => '/eid/management/eidMonthlyThresholdReport.php'
                ];
                $sharedPrivileges = array_merge($sharedPrivileges, $sharedEIDPrivileges);
            }

            if (
                isset($this->applicationConfig['modules']['covid19']) &&
                $this->applicationConfig['modules']['covid19'] === true
            ) {
                $sharedCovid19Privileges = [
                    '/batch/delete-batch.php?type=covid19'              => '/batch/edit-batch.php?type=covid19',
                    '/batch/generate-batch-pdf.php?type=covid19'        => '/batch/batches.php?type=covid19',
                    '/batch/add-batch-position.php?type=covid19'         => '/batch/add-batch.php?type=covid19',
                    '/batch/edit-batch-position.php?type=covid19'        => '/batch/edit-batch.php?type=covid19',
                    '/covid-19/mail/mail-covid-19-results.php'                 => '/covid-19/results/covid-19-print-results.php',
                    '/covid-19/mail/covid-19-result-mail-confirm.php'          => '/covid-19/results/covid-19-print-results.php',
                    '/covid-19/results/covid-19-update-result.php'                => '/covid-19/results/covid-19-manual-results.php',
                    '/covid-19/results/covid-19-failed-results.php'               => '/covid-19/results/covid-19-manual-results.php',
                    '/covid-19/requests/covid-19-bulk-import-request.php'          => '/covid-19/requests/covid-19-add-request.php',
                    '/covid-19/requests/covid-19-quick-add.php'                    => '/covid-19/requests/covid-19-add-request.php',
                    '/covid-19/reference/covid19-sample-rejection-reasons.php'      => '/covid-19/reference/covid19-sample-type.php',
                    '/covid-19/reference/add-covid19-sample-rejection-reason.php'   => '/covid-19/reference/covid19-sample-type.php',
                    '/covid-19/reference/covid19-comorbidities.php'                 => '/covid-19/reference/covid19-sample-type.php',
                    '/covid-19/reference/add-covid19-comorbidities.php'             => '/covid-19/reference/covid19-sample-type.php',
                    '/covid-19/reference/covid19-symptoms.php'                      => '/covid-19/reference/covid19-sample-type.php',
                    '/covid-19/reference/add-covid19-sample-type.php'               => '/covid-19/reference/covid19-sample-type.php',
                    '/covid-19/reference/covid19-test-symptoms.php'                 => '/covid-19/reference/covid19-sample-type.php',
                    '/covid-19/reference/add-covid19-symptoms.php'                  => '/covid-19/reference/covid19-sample-type.php',
                    '/covid-19/reference/covid19-test-reasons.php'                  => '/covid-19/reference/covid19-sample-type.php',
                    '/covid-19/reference/add-covid19-test-reasons.php'              => '/covid-19/reference/covid19-sample-type.php',
                    '/covid-19/reference/covid19-results.php'                       => '/covid-19/reference/covid19-sample-type.php',
                    '/covid-19/reference/add-covid19-results.php'                   => '/covid-19/reference/covid19-sample-type.php',
                    '/covid-19/management/covid19TestingTargetReport.php'            => '/covid-19/management/covid19MonthlyThresholdReport.php',
                    '/covid-19/management/covid19SuppressedTargetReport.php'         => '/covid-19/management/covid19MonthlyThresholdReport.php',
                    '/covid-19/interop/dhis2/covid-19-init.php'                         => '/covid-19/requests/covid-19-dhis2.php',
                    '/covid-19/interop/dhis2/covid-19-send.php'                         => '/covid-19/requests/covid-19-dhis2.php',
                    '/covid-19/interop/dhis2/covid-19-receive.php'                      => '/covid-19/requests/covid-19-dhis2.php',
                    '/covid-19/reference/covid19-qc-test-kits.php'                          => '/covid-19/reference/covid19-sample-type.php',
                    '/covid-19/reference/add-covid19-qc-test-kit.php'               => '/covid-19/reference/covid19-sample-type.php',
                    '/covid-19/reference/edit-covid19-qc-test-kit.php'              => '/covid-19/reference/covid19-sample-type.php'
                ];
                $sharedPrivileges = array_merge($sharedPrivileges, $sharedCovid19Privileges);
            }

            if (
                isset($this->applicationConfig['modules']['hepatitis']) &&
                $this->applicationConfig['modules']['hepatitis'] === true
            ) {
                $sharedHepPrivileges = [
                    '/batch/delete-batch.php?type=hepatitis'              => '/batch/edit-batch.php?type=hepatitis',
                    '/batch/generate-batch-pdf.php?type=hepatitis'        => '/batch/batches.php?type=hepatitis',
                    '/batch/add-batch-position.php?type=hepatitis'         => '/batch/add-batch.php?type=hepatitis',
                    '/batch/edit-batch-position.php?type=hepatitis'        => '/batch/edit-batch.php?type=hepatitis',
                    '/hepatitis/results/hepatitis-update-result.php'                   => '/hepatitis/results/hepatitis-manual-results.php',
                    '/hepatitis/results/hepatitis-failed-results.php'                  => '/hepatitis/results/hepatitis-manual-results.php',
                    '/hepatitis/mail/mail-hepatitis-results.php'                    => '/hepatitis/results/hepatitis-print-results.php',
                    'hepatitis-result-mail-confirm.php'             => '/hepatitis/results/hepatitis-print-results.php',
                    '/hepatitis/reference/hepatitis-sample-rejection-reasons.php'        => '/hepatitis/reference/hepatitis-sample-type.php',
                    '/hepatitis/reference/add-hepatitis-sample-rejection-reasons.php'    => '/hepatitis/reference/hepatitis-sample-type.php',
                    '/hepatitis/reference/hepatitis-comorbidities.php'                   => '/hepatitis/reference/hepatitis-sample-type.php',
                    '/hepatitis/reference/add-hepatitis-comorbidities.php'               => '/hepatitis/reference/hepatitis-sample-type.php',
                    '/hepatitis/reference/add-hepatitis-sample-type.php'                 => '/hepatitis/reference/hepatitis-sample-type.php',
                    '/hepatitis/reference/hepatitis-results.php'                         => '/hepatitis/reference/hepatitis-sample-type.php',
                    '/hepatitis/reference/add-hepatitis-results.php'                     => '/hepatitis/reference/hepatitis-sample-type.php',
                    '/hepatitis/reference/hepatitis-risk-factors.php'                    => '/hepatitis/reference/hepatitis-sample-type.php',
                    '/hepatitis/reference/add-hepatitis-risk-factors.php'                => '/hepatitis/reference/hepatitis-sample-type.php',
                    '/hepatitis/reference/hepatitis-test-reasons.php'                    => '/hepatitis/reference/hepatitis-sample-type.php',
                    '/hepatitis/reference/add-hepatitis-test-reasons.php'                => '/hepatitis/reference/hepatitis-sample-type.php',
                    '/hepatitis/interop/dhis2/hepatitis-init.php'                            => '/hepatitis/requests/hepatitis-dhis2.php',
                    '/hepatitis/interop/dhis2/hepatitis-send.php'                            => '/hepatitis/requests/hepatitis-dhis2.php',
                    '/hepatitis/interop/dhis2/hepatitis-receive.php'                         => '/hepatitis/requests/hepatitis-dhis2.php'
                ];
                $sharedPrivileges = array_merge($sharedPrivileges, $sharedHepPrivileges);
            }

            if (
                isset($this->applicationConfig['modules']['tb']) &&
                $this->applicationConfig['modules']['tb'] === true
            ) {
                $sharedTbPrivileges = [
                    '/batch/delete-batch.php?type=tb'              => '/batch/edit-batch.php?type=tb',
                    '/batch/generate-batch-pdf.php?type=tb'        => '/batch/batches.php?type=tb',
                    '/batch/add-batch-position.php?type=tb'         => '/batch/add-batch.php?type=tb',
                    '/batch/edit-batch-position.php?type=tb'        => '/batch/edit-batch.php?type=tb',
                    '/tb/results/tb-update-result.php'                    => '/tb/results/tb-manual-results.php',
                    '/tb/results/tb-failed-results.php'                   => '/tb/results/tb-manual-results.php',
                    '/tb/reference/add-tb-sample-type.php'                => 'tb-sample-type.php',
                    '/tb/reference/tb-sample-rejection-reasons.php'       => 'tb-sample-type.php',
                    '/tb/reference/add-tb-sample-rejection-reason.php'    => 'tb-sample-type.php',
                    '/tb/reference/tb-test-reasons.php'                   => 'tb-sample-type.php',
                    '/tb/reference/add-tb-test-reasons.php'               => 'tb-sample-type.php',
                    '/tb/reference/tb-results.php'                        => 'tb-sample-type.php',
                    '/tb/reference/add-tb-results.php'                    => 'tb-sample-type.php',
                ];
                $sharedPrivileges = array_merge($sharedPrivileges, $sharedTbPrivileges);
            }

            return $sharedPrivileges;
        });
    }

    // These files don't need privileges check
    public function getSkippedPrivileges(): array
    {
        return [
            '401.php',
            '404.php',
            'error.php',
            'editProfile.php'
        ];
    }

    public function getUserInfo($userId, $columns = '*')
    {
        if (is_array($columns)) {
            $columns = implode(",", $columns);
        }
        $uQuery = "SELECT $columns FROM $this->table WHERE user_id= ?";
        return $this->db->rawQueryOne($uQuery, [$userId]);
    }

    public function getAllUsers($facilityMap = null, $status = null, $type = null, $updatedDateTime = null)
    {
        return once(function () use ($facilityMap, $status, $type, $updatedDateTime) {

            if (!empty($facilityMap)) {
                $facilityMap = explode(",", $facilityMap);
                $this->db->join("user_facility_map map", "map.user_id=u.user_id", "INNER");
                $this->db->where('map.facility_id', $facilityMap, 'IN');
            }
            if ($status == 'active') {
                $this->db->where("status='active'");
            }

            if ($updatedDateTime) {
                $this->db->where("updated_datetime >= '$updatedDateTime'");
            }

            $this->db->orderBy("user_name", "asc");

            if (isset($type) && $type == 'drop-down') {
                $result =  $this->db->get('user_details u');
                $userDetails = [];
                foreach ($result as $user) {
                    $userDetails[$user['user_id']] = ($user['user_name']);
                }
                return $userDetails;
            } else {
                return $this->db->get('user_details u');
            }
        });
    }

    public function getActiveUsers($facilityMap = null, $updatedDateTime = null)
    {
        return $this->getAllUsers($facilityMap, 'active', null, $updatedDateTime);
    }

    public function addUserIfNotExists($name, $status = 'inactive', $role = 4)
    {
        $uQuery = "SELECT `user_id`
                    FROM $this->table
                    WHERE (`user_name` LIKE ?)
                    OR (JSON_CONTAINS(LOWER(interface_user_name), JSON_QUOTE(LOWER(?)), '$'))";

        $result = $this->db->rawQueryOne($uQuery, [$name, $name]);
        if ($result == null) {

            $userId = $this->commonService->generateUUID();
            $userData = [
                'user_id' => $userId,
                'user_name' => $name,
                'interface_user_name' => $name,
                'role_id' => $role,
                'status' => $status
            ];
            $this->db->insert($this->table, $userData);
        } else {
            $userId = $result['user_id'];
        }

        return $userId;
    }


    public function getUserByToken($token = null): ?array
    {
        return once(function () use ($token) {
            if (!empty($token)) {
                $this->db->where('u.api_token', $token);
                $this->db->where('u.status', 'active');
                $this->db->join("roles r", "u.role_id=r.role_id", "INNER");
                $return = $this->db->getOne("$this->table as u");
            } else {
                $return = null;
            }
            return $return;
        });
    }

    public function generateAuthToken($size = 8): string
    {
        return  base64_encode($this->commonService->generateUUID() . "-" . $this->commonService->generateToken($size));
    }

    public function getUserByUserId(?string $userId = null): ?array
    {
        return once(function () use ($userId) {
            if (!empty($userId)) {
                $this->db->where('u.user_id', $userId);
                $this->db->where('u.status', 'active');
                $this->db->join("roles r", "u.role_id=r.role_id", "INNER");
                $return = $this->db->getOne("$this->table as u");
            } else {
                $return = null;
            }
            return $return;
        });
    }

    public function validateAuthToken(?string $token = null): bool
    {
        return once(function () use ($token) {
            $result = null;
            if (!empty($token)) {
                $this->db->where('api_token', $token);
                $this->db->where('status', 'active');
                $result = $this->db->getOne($this->table, ['user_id']);
            }
            return !empty($result);
        });
    }


    public function getAuthToken(?string $token): array
    {
        $result = $this->getUserByToken($token) ?? null;

        if (!empty($result)) {
            $tokenExpiration = $result['api_token_exipiration_days'] ?? 0;

            $id = false;
            $data = [];
            // Tokens with expiration = 0 are tokens that never expire
            if ($tokenExpiration > 0 || empty($result['api_token'])) {
                $today = new DateTime();
                $lastTokenDate = new DateTime($result['api_token_generated_datetime'] ?? null);
                if (empty($result['api_token']) || $today->diff($lastTokenDate)->days > $tokenExpiration) {
                    $data['api_token'] = $this->generateAuthToken();
                    $data['api_token_generated_datetime'] = DateUtility::getCurrentDateTime();

                    $this->db = $this->db->where('user_id', $result['user_id']);
                    $id = $this->db->update($this->table, $data);
                }
            }

            $result['token_updated'] = $id === true && !empty($data);
            $result['new_token'] = $result['token_updated'] ? $data['api_token'] : null;
            $result['token'] = $result['api_token'] ?? null;
        }

        return $result;
    }

    public function getUserRole(string $userId)
    {
        $query = "SELECT r.*
                    FROM roles as r
                    INNER JOIN user_details as u ON u.role_id=r.role_id
                    WHERE u.user_id = ?";
        return $this->db->rawQueryOne($query, [$userId]);
    }

    public function getUserRolePrivileges(string $userId): array
    {
        $response = [];
        $query = "SELECT r.role_id,
                    r.role_code,
                    r.role_name,
                    r.access_type,
                    res.module,
                    p.privilege_id,
                    p.resource_id,
                    p.privilege_name,
                    p.display_name
                    FROM roles as r
                    INNER JOIN roles_privileges_map as rpm ON rpm.role_id=r.role_id
                    INNER JOIN privileges as p ON rpm.privilege_id=p.privilege_id
                    INNER JOIN resources as res ON res.resource_id=p.resource_id
                    INNER JOIN user_details as u ON u.role_id=r.role_id
                    WHERE u.user_id = ?
                    ORDER by res.module, p.resource_id, p.display_name";
        $resultSet = $this->db->rawQuery($query, [$userId]);
        foreach ($resultSet as $row) {

            $response['role']['name'] = $row['role_name'];
            $response['role']['code'] = $row['role_code'];
            $response['role']['type'] = $row['access_type'];

            $row['display_name'] =  strtolower(trim(preg_replace("![^a-z0-9]+!i", " ", $row['display_name'])));
            $row['display_name'] =  preg_replace("![^a-z0-9]+!i", "-", $row['display_name']);
            $response['privileges'][$row['module']][$row['resource_id']][] = $row['display_name'];
        }
        return $response;
    }

    public function userHistoryLog($loginId, $loginStatus, $userId = null)
    {
        $browserAgent = $_SERVER['HTTP_USER_AGENT'];
        $os = PHP_OS;
        $ipaddress = $this->commonService->getClientIpAddress();

        $data = [
            'login_id' => $loginId,
            'user_id' => $userId,
            'login_attempted_datetime' => DateUtility::getCurrentDateTime(),
            'login_status' => $loginStatus,
            'ip_address' => $ipaddress,
            'browser'    => $browserAgent,
            'operating_system' => $os
        ];
        $this->db->insert('user_login_history', $data);
    }

    public function passwordHash($password)
    {
        if (empty($password)) {
            return null;
        }

        $options = [
            'cost' => 14
        ];

        return password_hash($password, PASSWORD_BCRYPT, $options);
    }
}
