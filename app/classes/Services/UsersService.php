<?php

namespace App\Services;

use DateTime;
use App\Services\ApiService;
use App\Utilities\DateUtility;
use App\Utilities\JsonUtility;
use App\Utilities\MiscUtility;
use App\Services\CommonService;
use App\Services\DatabaseService;
use Laminas\Diactoros\ServerRequest;

final class UsersService
{

    protected $db;
    protected string $table = 'user_details';
    protected $commonService;

    public function __construct(DatabaseService $db, CommonService $commonService)
    {
        $this->db = $db;
        $this->commonService = $commonService;
    }

    public function isAllowed(mixed $currentRequest, mixed $privileges = null): bool
    {
        $privileges = $privileges ?? $_SESSION['privileges'] ?? null;

        if (empty($currentRequest) || empty($privileges)) {
            return false;
        }

        $sessionKey = md5(is_string($currentRequest) ? $currentRequest : $currentRequest->getUri());

        // If the result is already stored in the session, return it
        if (isset($_SESSION['acl'][$sessionKey])) {
            return $_SESSION['acl'][$sessionKey];
        }

        $isAllowed = false;
        if (!empty($privileges) && !empty($currentRequest)) {
            $requestArray = $this->getRequestArray($currentRequest);
            foreach ($requestArray as $requestUrl) {
                if (isset($privileges[$requestUrl])) {
                    $isAllowed = true;
                    break;
                }
            }
        }
        $_SESSION['acl'][$sessionKey] = $isAllowed;
        return $isAllowed;
    }

    private function getRequestArray($currentRequest)
    {
        if ($currentRequest instanceof ServerRequest) {
            $uri = $currentRequest->getUri();
            $path = $uri->getPath();
            $query = $uri->getQuery();
            // Clean up the URI Path for double slashes or dots
            $path = preg_replace('/([\\/\\.])\\1+/', '$1', $path);
            $baseFileName = basename($path);
            $currentRequest = $path . ($query ? '?' . $query : '');
        } else {
            $parsedInput = parse_url((string) $currentRequest);
            $path = $parsedInput['path'];
            $baseFileName = basename($path);
        }

        $urlParts = parse_url((string) $currentRequest);
        $requestArray = [$currentRequest, $baseFileName, $path];

        if (isset($urlParts['query'])) {
            $queryParams = [];
            parse_str($urlParts['query'], $queryParams);

            $pathWithoutQuery = $urlParts['path'];

            while (count($queryParams) > 0) {
                array_pop($queryParams);
                $requestArray[] = $pathWithoutQuery . (count($queryParams) > 0 ? '?' . http_build_query($queryParams) : '');
            }
        }

        return array_unique($requestArray, SORT_REGULAR);
    }

    public function getAllPrivileges(string $role): array
    {
        $modules = $privileges = [];
        $privilegesQuery = "SELECT p.privilege_name, rp.privilege_id, r.module
                            FROM roles_privileges_map as rp
                            INNER JOIN privileges as p ON p.privilege_id=rp.privilege_id
                            INNER JOIN resources as r ON r.resource_id=p.resource_id
                            WHERE rp.role_id= ?";
        $privilegesResult = $this->db->rawQuery($privilegesQuery, [$role]);
        if (!empty($privilegesResult)) {
            $modules = array_unique(array_column($privilegesResult, 'module'));

            $privileges = array_column($privilegesResult, 'privilege_name');
            $matchingKeys = array_keys(array_intersect($this->getSharedPrivileges(), $privileges));
            $privileges = array_merge($this->getSkippedPrivileges(), $privileges, $matchingKeys);
            // Create an array with both full paths and basenames
            $fullPathsAndBasenames = [];
            foreach ($privileges as $privilege) {
                $fullPathsAndBasenames[$privilege] = $privilege; // Full path as key and value
                $basename = basename($privilege);
                if ($basename == 'index.php') {
                    continue;
                }
                $fullPathsAndBasenames[$basename] = $basename; // Basename as key and value
            }

            $modules = array_combine($modules, $modules);
            $privileges = array_combine($fullPathsAndBasenames, $fullPathsAndBasenames);
        }
        return [$modules, $privileges];
    }

    public function getSharedPrivileges(): array
    {
        $sql = "SELECT privilege_name, shared_privileges
                    FROM privileges";
        $results = $this->db->rawQuery($sql);
        $sharedPrivileges = [];

        // Fetch each row from the result set
        foreach ($results as $row) {
            $privileges = json_decode((string) $row['shared_privileges'], true);
            if (empty($privileges)) {
                continue;
            } else {
                foreach ($privileges as $privilege) {
                    $sharedPrivileges[$privilege] = $row['privilege_name'];
                }
            }
        }
        // Return the array of shared privileges
        return $sharedPrivileges;
    }

    // These files don't need privileges check
    public function getSkippedPrivileges(): array
    {
        return [
            '401.php',
            '404.php',
            'error.php',
            'edit-profile.php'
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
                $facilityMap = explode(",", (string) $facilityMap);
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
                $result = $this->db->setQueryOption('DISTINCT')->get('user_details u');
                $userDetails = [];
                foreach ($result as $user) {
                    $userDetails[$user['user_id']] = ($user['user_name']);
                }
                return $userDetails;
            } else {
                return $this->db->setQueryOption('DISTINCT')->get('user_details u');
            }
        });
    }

    public function getActiveUsers($facilityMap = null, $updatedDateTime = null)
    {
        return $this->getAllUsers($facilityMap, 'active', null, $updatedDateTime);
    }

    public function getOrCreateUser($name, $status = 'inactive', $role = 4)
    {
        $uQuery = "SELECT `user_id`
                    FROM $this->table
                        WHERE (`user_name` LIKE ?)
                            OR (JSON_VALID(interface_user_name) AND
                            JSON_CONTAINS(LOWER(interface_user_name), JSON_QUOTE(LOWER(?)), '$'))";


        $result = $this->db->rawQueryOne($uQuery, [$name, $name]);
        if ($result == null) {

            $userId = MiscUtility::generateUUID();
            $userData = [
                'user_id' => $userId,
                'user_name' => $name,
                'role_id' => $role,
                'status' => $status
            ];
            $this->db->insert($this->table, $userData);
        } else {
            $userId = $result['user_id'];
        }

        return $userId;
    }


    public function getUserByToken(?string $token = null): ?array
    {
        if (empty($token)) {
            return null;
        }

        $this->db->where('u.api_token', $token);
        $this->db->where('u.status', 'active');
        $this->db->join("roles r", "u.role_id=r.role_id", "INNER");
        $user = $this->db->getOne("$this->table as u");

        // in case of empty access_type, set default access_type
        // if the instance is STS, set access_type to collection-site
        // else set access_type to testing-lab
        if (empty($user['access_type']) || $user['access_type'] == '') {
            if ($this->commonService->isSTSInstance()) {
                $user['access_type'] = 'collection-site';
            } else {
                $user['access_type'] = 'testing-lab';
            }
        }
        return $user;
    }

    public function getUserByUserId(?string $userId = null): ?array
    {
        if (!empty($userId)) {
            $this->db->where('u.user_id', $userId);
            $this->db->where('u.status', 'active');
            $this->db->join("roles r", "u.role_id=r.role_id", "INNER");
            $return = $this->db->getOne("$this->table as u");
        } else {
            $return = null;
        }
        return $return;
    }

    public function validateAuthToken(?string $token = null): bool
    {
        $result = null;
        if (!empty($token)) {
            $this->db->where('api_token', $token);
            $this->db->where('status', 'active');
            $result = $this->db->getOne($this->table, 'user_id');
        }
        return !empty($result);
    }

    public function getAuthToken(?string $token, ?string $userId = null): ?array
    {
        $result = $this->getUserByToken($token) ?? null;

        if (!empty($result)) {
            $tokenExpiration = $result['api_token_exipiration_days'] ?? 0;
            $lastTokenDate = $result['api_token_generated_datetime'] ?? null;

            if ($this->shouldUpdateToken($tokenExpiration, $lastTokenDate)) {
                $newToken = $this->updateUserToken($result['user_id']);
                $result['token_updated'] = ($newToken !== null);
                $result['new_token'] = $newToken;
            } else {
                $result['token_updated'] = false;
                $result['new_token'] = null;
            }

            $result['token'] = $result['api_token'] ?? null;
        } elseif ($userId !== null) {
            $newToken = $this->updateUserToken($userId);
            $result = [
                'token' => $newToken,
                'token_updated' => true,
                'new_token' => $newToken
            ];
        } else {
            return null;
        }

        return $result;
    }

    private function shouldUpdateToken(int $tokenExpiration, ?string $lastTokenDate): bool
    {
        // Tokens with expiration = 0 are tokens that never expire
        // if $lastTokenDate is empty, the token was manually generated and should not be updated
        if ($tokenExpiration === 0 || empty($lastTokenDate)) {
            return false;
        }

        return DateUtility::compareDateWithInterval($lastTokenDate, '>', "$tokenExpiration days");
    }

    private function updateUserToken(int $userId): ?string
    {
        $newToken = ApiService::generateAuthToken();
        $data = [
            'api_token' => $newToken,
            'api_token_generated_datetime' => DateUtility::getCurrentDateTime(),
        ];

        $this->db->where('user_id', $userId);
        if (!$this->db->update($this->table, $data)) {
            // Log error or handle failed update scenario
            error_log("Failed to update the user token in the database for user ID: $userId");
            return null;
        }
        return $newToken;
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

            $row['display_name'] = strtolower(trim(preg_replace("![^a-z0-9]+!i", " ", (string) $row['display_name'])));
            $row['display_name'] = preg_replace("![^a-z0-9]+!i", "-", $row['display_name']);
            $response['privileges'][$row['module']][$row['resource_id']][] = $row['display_name'];
        }
        return $response;
    }

    public function recordLoginAttempt($loginId, $loginStatus, $userId = null)
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
            'browser' => $browserAgent,
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

        return password_hash((string) $password, PASSWORD_BCRYPT, $options);
    }

    public function saveUserAttributes($data, $userId)
    {
        if (!isset($data) || empty($data) || !isset($userId) || empty($userId)) {
            return null;
        }
        $saveData['user_attributes'] = !empty($data) ? $this->db->func($data) : null;
        $this->db->where('user_id', $userId);
        return $this->db->update($this->table, $saveData);
    }

    public function continuousFailedLogins($loginId, $intervalMinutes = 15)
    {
        // Get current date and time
        $currentDateTime = DateUtility::getCurrentDateTime();

        // Query database for login attempts within the specified interval
        $loginAttempts = $this->db->rawQueryOne(
            "SELECT
            SUM(CASE WHEN ulh.login_status = 'failed' THEN 1 ELSE 0 END) AS failedAttempts,
            SUM(CASE WHEN ulh.login_status = 'success' THEN 1 ELSE 0 END) AS successAttempts
        FROM user_login_history ulh
        WHERE ulh.login_id = ? AND
        ulh.login_attempted_datetime >= DATE_SUB(?, INTERVAL ? MINUTE)",
            [$loginId, $currentDateTime, $intervalMinutes]
        );

        // Ensure the results are not null
        $failedAttempts = $loginAttempts['failedAttempts'] ?? 0;
        $successAttempts = $loginAttempts['successAttempts'] ?? 0;

        // Check if the user has failed to login continuously
        return ($failedAttempts >= 3 && $successAttempts == 0);
    }

    public function savePreferences(int $userId, string $page, array $newPreferences): bool
    {
        $pageId = MiscUtility::generateUUIDv5($page); // Generating UUIDv5 based on page name

        // Retrieve current preferences
        $currentPreferencesJson = $this->getPreferencesJson($userId, $pageId);

        // Validate and decode current preferences, ensuring it's always an array
        $currentPreferences = JsonUtility::isJSON($currentPreferencesJson) ? JsonUtility::decodeJson($currentPreferencesJson) : [];

        // Merge current preferences with new preferences
        $updatedPreferences = array_merge($currentPreferences, $newPreferences);

        // Use JsonUtility to safely encode the merged preferences
        $updatedPreferencesJson = JsonUtility::toJSON($updatedPreferences);

        $data = [
            'user_id' => $userId,
            'page_id' => $pageId,
            'preferences' => $updatedPreferencesJson,
            'updated_datetime' => DateUtility::getCurrentDateTime() // Assuming you have a DateUtility to get the current date and time
        ];

        // Define which columns should be updated on duplicate key
        $updateColumns = [
            'preferences' => $updatedPreferencesJson,
            'updated_datetime' => DateUtility::getCurrentDateTime()
        ];

        // Use the upsert method from DatabaseService
        return $this->db->upsert('user_preferences', $data, $updateColumns);
    }

    public function getPreferencesJson(int $userId, string $pageId): ?string
    {
        $query = "SELECT preferences FROM user_preferences WHERE user_id = ? AND page_id = ?";
        $result = $this->db->rawQueryOne($query, [$userId, $pageId]);
        return $result ? $result['preferences'] : null;
    }
}
