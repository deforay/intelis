<?php

namespace App\Services;

use DateTime;
use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Services\DatabaseService;
use Laminas\Diactoros\ServerRequest;

class UsersService
{

    protected $db;
    protected string $table = 'user_details';
    protected $commonService;

    public function __construct(DatabaseService $db, CommonService $commonService)
    {
        $this->db = $db;
        $this->commonService = $commonService;
    }

    public function isAllowed($currentRequest, $privileges = null): bool
    {
        $privileges = $privileges ?? $_SESSION['privileges'] ?? null;

        if (empty($currentRequest) || empty($privileges)) {
            return false;
        }

        $sessionKey = base64_encode(is_string($currentRequest) ? $currentRequest : $currentRequest->getUri());

        // If the result is already stored in the session, return it
        if (isset($_SESSION['access'][$sessionKey])) {
            return $_SESSION['access'][$sessionKey];
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
        $_SESSION['access'][$sessionKey] = $isAllowed;
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
                    OR (JSON_CONTAINS(LOWER(interface_user_name), JSON_QUOTE(LOWER(?)), '$'))";

        $result = $this->db->rawQueryOne($uQuery, [$name, $name]);
        if ($result == null) {

            $userId = $this->commonService->generateUUID();
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
        return base64_encode($this->commonService->generateUUID() .
            "-" . $this->commonService->generateRandomString($size ?? 8));
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
                $result = $this->db->getOne($this->table, 'user_id');
            }
            return !empty($result);
        });
    }


    public function getAuthToken(?string $token, $uId = null): ?array
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
        } else {
            $data['api_token'] = $this->generateAuthToken();
            $data['api_token_generated_datetime'] = DateUtility::getCurrentDateTime();
            $this->db = $this->db->where('user_id', $uId);
            $id = $this->db->update($this->table, $data);
            $result['token'] = $data['api_token'] ?? null;
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

    public function saveUserAttributes($data, $userId){
        if(!isset($data) || empty($data) || !isset($userId) || empty($userId)){
            return null;
        }
        $saveData['user_attributes'] = !empty($data) ? $this->db->func($data) : null;
        $this->db->where('user_id', $userId);
        return $this->db->update($this->table, $saveData);
    }
}
