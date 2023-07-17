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

    public function isAllowed($currentRequest, $privileges = null): bool
    {

        $sessionKey = base64_encode(is_string($currentRequest) ? $currentRequest : $currentRequest->getUri());

        // If the result is already stored in the session, return it
        if (isset($_SESSION['access'][$sessionKey])) {
            return $_SESSION['access'][$sessionKey];
        }

        $privileges = $privileges ?? $_SESSION['privileges'] ?? null;
        $isAllowed = false;
        if (!empty($privileges) && !empty($currentRequest)) {

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

            $urlParts = parse_url($currentRequest);
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

    public function getAllPrivileges(?array $privileges = []): array
    {
        $privileges = !empty($privileges) ? $privileges : array_flip($_SESSION['privileges']);
        $matchingKeys = array_keys(array_intersect($this->getSharedPrivileges(), $privileges));
        return array_flip(array_merge($this->getSkippedPrivileges(), $privileges, $matchingKeys));
    }

    public function getSharedPrivileges(): array
    {

        $sql = "SELECT privilege_name, shared_privileges FROM privileges";
        $results = $this->db->rawQuery($sql);
        $sharedPrivileges = [];
    
        // Fetch each row from the result set
        foreach ($results as $row) {
            $privileges = json_decode($row['shared_privileges'], true);
    
            foreach ($privileges as $privilege) {
                $sharedPrivileges[$privilege] = $row['privilege_name'];
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
                $result = $this->db->get('user_details u');
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
        return base64_encode($this->commonService->generateUUID() .
            "-" . $this->commonService->generateRandomString($size));
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

            $row['display_name'] = strtolower(trim(preg_replace("![^a-z0-9]+!i", " ", $row['display_name'])));
            $row['display_name'] = preg_replace("![^a-z0-9]+!i", "-", $row['display_name']);
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

        return password_hash($password, PASSWORD_BCRYPT, $options);
    }
}
