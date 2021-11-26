<?php

namespace Vlsm\Models;

/**
 * General functions
 *
 * @author Amit
 */

class Users
{

    protected $db = null;
    protected $table = 'user_details';

    public function __construct($db = null)
    {
        $this->db = !empty($db) ? $db : \MysqliDb::getInstance();
    }


    public function isAllowed($currentFileName, $systemConfig)
    {
        $skippedPrivileges = $this->getSkippedPrivileges($systemConfig);
        $sharedPrivileges = $this->getSharedPrivileges($systemConfig);

        // Does the current file share privileges with another privilege ?
        $currentFileName = isset($sharedPrivileges[$currentFileName]) ? $sharedPrivileges[$currentFileName] : $currentFileName;


        if (!in_array($currentFileName, $skippedPrivileges)) {
            if (isset($_SESSION['privileges']) && !in_array($currentFileName, $_SESSION['privileges'])) {
                return false;
            }
        }

        return true;
    }

    public function getSharedPrivileges($systemConfig)
    {

        // on the left put intermediate/inner file, on the right put the file
        // which has entry in privileges table.
        $sharedPrivileges = array(
            'imported-results.php'              => 'addImportResult.php',
            'importedStatistics.php'            => 'addImportResult.php',
            'mapTestType.php'                   => 'addFacility.php',
            'add-province.php'                  => 'province-details.php',
            'edit-province.php'                 => 'province-details.php',
            'implementation-partners.php'       => 'province-details.php',
            'add-implementation-partners.php'   => 'province-details.php',
            'edit-implementation-partners.php'  => 'province-details.php',
            'funding-sources.php'               => 'province-details.php',
            'add-funding-sources.php'           => 'province-details.php',
            'edit-funding-sources.php'          => 'province-details.php'
        );

        if (isset($systemConfig['modules']['vl']) && $systemConfig['modules']['vl'] == true) {
            $sharedVLPrivileges = array(
                'updateVlTestResult.php'                => 'vlTestResult.php',
                'vl-failed-results.php'                 => 'vlTestResult.php',
                'add-vl-art-code-details.php'           => 'vl-art-code-details.php',
                'edit-vl-art-code-details.php'          => 'vl-art-code-details.php',
                'vl-sample-rejection-reasons.php'       => 'vl-art-code-details.php',
                'add-vl-sample-rejection-reasons.php'   => 'vl-art-code-details.php',
                'edit-vl-sample-rejection-reasons.php'  => 'vl-art-code-details.php',
                'vl-sample-type.php'                    => 'vl-art-code-details.php',
                'edit-vl-sample-type.php'               => 'vl-art-code-details.php',
                'add-vl-sample-type.php'                => 'vl-art-code-details.php',
                'vl-test-reasons.php'                   => 'vl-art-code-details.php',
                'add-vl-test-reasons.php'               => 'vl-art-code-details.php',
                'edit-vl-test-reasons.php'              => 'vl-art-code-details.php',
                'vlTestingTargetReport.php'             => 'vlMonthlyThresholdReport.php',
                'vlSuppressedTargetReport.php'          => 'vlMonthlyThresholdReport.php'
            );

            $sharedPrivileges = array_merge($sharedPrivileges, $sharedVLPrivileges);
        }

        if (isset($systemConfig['modules']['eid']) && $systemConfig['modules']['eid'] == true) {
            $sharedEIDPrivileges = array(
                'eid-add-batch-position.php'            => 'eid-add-batch.php',
                'eid-edit-batch-position.php'           => 'eid-edit-batch.php',
                'eid-update-result.php'                 => 'eid-manual-results.php',
                'eid-failed-results.php'                => 'eid-manual-results.php',
                'eid-bulk-import-request.php'           => 'eid-add-request.php',
                'eid-sample-rejection-reasons.php'      => 'eid-sample-type.php',
                'add-eid-sample-rejection-reasons.php'  => 'eid-sample-type.php',
                'edit-eid-sample-rejection-reasons.php' => 'eid-sample-type.php',
                'add-eid-sample-type.php'               => 'eid-sample-type.php',
                'edit-eid-sample-type.php'              => 'eid-sample-type.php',
                'eid-test-reasons.php'                  => 'eid-sample-type.php',
                'add-eid-test-reasons.php'              => 'eid-sample-type.php',
                'edit-eid-test-reasons.php'             => 'eid-sample-type.php',
                'eidTestingTargetReport.php'            => 'eidMonthlyThresholdReport.php',
                'eidSuppressedTargetReport.php'         => 'eidMonthlyThresholdReport.php'
            );
            $sharedPrivileges = array_merge($sharedPrivileges, $sharedEIDPrivileges);
        }

        if (isset($systemConfig['modules']['covid19']) && $systemConfig['modules']['covid19'] == true) {
            $sharedCovid19Privileges = array(
                'covid-19-add-batch-position.php'           => 'covid-19-add-batch.php',
                'mail-covid-19-results.php'                 => 'covid-19-print-results.php',
                'covid-19-result-mail-confirm.php'          => 'covid-19-print-results.php',
                'covid-19-edit-batch-position.php'          => 'covid-19-edit-batch.php',
                'covid-19-update-result.php'                => 'covid-19-manual-results.php',
                'covid-19-failed-results.php'               => 'covid-19-manual-results.php',
                'covid-19-bulk-import-request.php'          => 'covid-19-add-request.php',
                'covid-19-quick-add.php'                    => 'covid-19-add-request.php',
                'covid19-sample-rejection-reasons.php'      => 'covid19-sample-type.php',
                'add-covid19-sample-rejection-reason.php'   => 'covid19-sample-type.php',
                'edit-covid19-sample-rejection-reason.php'  => 'covid19-sample-type.php',
                'covid19-comorbidities.php'                 => 'covid19-sample-type.php',
                'add-covid19-comorbidities.php'             => 'covid19-sample-type.php',
                'edit-covid19-comorbidities.php'            => 'covid19-sample-type.php',
                'covid19-symptoms.php'                      => 'covid19-sample-type.php',
                'covid19-test-reasons.php'                  => 'covid19-sample-type.php',
                'add-covid19-sample-type.php'               => 'covid19-sample-type.php',
                'edit-covid19-sample-type.php'              => 'covid19-sample-type.php',
                'covid19-test-symptoms.php'                 => 'covid19-sample-type.php',
                'add-covid19-symptoms.php'                  => 'covid19-sample-type.php',
                'edit-covid19-symptoms.php'                 => 'covid19-sample-type.php',
                'covid19-test-reasons.php'                  => 'covid19-sample-type.php',
                'add-covid19-test-reasons.php'              => 'covid19-sample-type.php',
                'edit-covid19-test-reasons.php'             => 'covid19-sample-type.php',
                'covid19TestingTargetReport.php'            => 'covid19MonthlyThresholdReport.php',
                'covid19SuppressedTargetReport.php'         => 'covid19MonthlyThresholdReport.php',
                'covid-19-init.php'                         => 'covid-19-dhis2.php',
                'covid-19-send.php'                         => 'covid-19-dhis2.php',
                'covid-19-receive.php'                      => 'covid-19-dhis2.php'
            );
            $sharedPrivileges = array_merge($sharedPrivileges, $sharedCovid19Privileges);
        }

        if (isset($systemConfig['modules']['hepatitis']) && $systemConfig['modules']['hepatitis'] == true) {
            $sharedHepPrivileges = array(
                'hepatitis-update-result.php'                   => 'hepatitis-manual-results.php',
                'mail-hepatitis-results.php'                    => 'hepatitis-print-results.php',
                'hepatitis-result-mail-confirm.php'             => 'hepatitis-print-results.php',
                'hepatitis-sample-rejection-reasons.php'        => 'hepatitis-sample-type.php',
                'add-hepatitis-sample-rejection-reasons.php'    => 'hepatitis-sample-type.php',
                'edit-hepatitis-sample-rejection-reasons.php'   => 'hepatitis-sample-type.php',
                'hepatitis-comorbidities.php'                   => 'hepatitis-sample-type.php',
                'add-hepatitis-comorbidities.php'               => 'hepatitis-sample-type.php',
                'edit-hepatitis-comorbidities.php'              => 'hepatitis-sample-type.php',
                'hepatitis-test-reasons.php'                    => 'hepatitis-sample-type.php',
                'add-hepatitis-sample-type.php'                 => 'hepatitis-sample-type.php',
                'edit-hepatitis-sample-type.php'                => 'hepatitis-sample-type.php',
                'hepatitis-results.php'                         => 'hepatitis-sample-type.php',
                'add-hepatitis-results.php'                     => 'hepatitis-sample-type.php',
                'edit-hepatitis-results.php'                    => 'hepatitis-sample-type.php',
                'hepatitis-risk-factors.php'                    => 'hepatitis-sample-type.php',
                'add-hepatitis-risk-factors.php'                => 'hepatitis-sample-type.php',
                'edit-hepatitis-risk-factors.php'               => 'hepatitis-sample-type.php',
                'hepatitis-test-reasons.php'                    => 'hepatitis-sample-type.php',
                'add-hepatitis-test-reasons.php'                => 'hepatitis-sample-type.php',
                'edit-hepatitis-test-reasons.php'               => 'hepatitis-sample-type.php',
                'hepatitis-init.php'                            => 'hepatitis-dhis2.php',
                'hepatitis-send.php'                            => 'hepatitis-dhis2.php',
                'hepatitis-receive.php'                         => 'hepatitis-dhis2.php'
            );
            $sharedPrivileges = array_merge($sharedPrivileges, $sharedHepPrivileges);
        }

        if (isset($systemConfig['modules']['tb']) && $systemConfig['modules']['tb'] == true) {
            $sharedHepPrivileges = array(
                'tb-update-result.php' => 'tb-manual-results.php',
                'tb-sample-rejection-reasons.php'  => 'tb-sample-type.php',
            );
            $sharedPrivileges = array_merge($sharedPrivileges, $sharedHepPrivileges);
        }

        return $sharedPrivileges;
    }

    // These files don't need privileges check
    public function getSkippedPrivileges()
    {
        $skippedPrivileges = array(
            '401.php',
            '404.php',
            'editProfile.php',
            'vlExportField.php'
        );

        return $skippedPrivileges;
    }

    public function getUserInfo($userId, $columns = '*')
    {
        if (is_array($columns)) {
            $columns = implode(",", $columns);
        }
        $uQuery = "SELECT $columns FROM " . $this->table . " where user_id='$userId'";
        return $this->db->rawQueryOne($uQuery);
    }

    public function getActiveUsers($facilityMap = null)
    {
        if (!empty($facilityMap)) {
            $facilityMap = explode(",", $facilityMap);
            $this->db->join("vl_user_facility_map map", "map.user_id=u.user_id", "INNER");
            $this->db->where('map.facility_id', $facilityMap, 'IN');
        }
        $this->db->where("status='active'");
        return $this->db->get('user_details u');
    }

    public function addUserIfNotExists($name, $status = 'inactive', $role = 4)
    {
        $uQuery = "SELECT `user_id` FROM $this->table WHERE (`user_name` LIKE '$name') OR (JSON_CONTAINS(LOWER(interface_user_name), JSON_QUOTE(LOWER('$name')), '$'))";

        $result = $this->db->rawQueryOne($uQuery);
        if ($result == null) {
            $general = new \Vlsm\Models\General($this->db);
            $userId = $general->generateUUID();
            $userData = array(
                'user_id' => $userId,
                'user_name' => $name,
                'interface_user_name' => $name,
                'role_id' => $role,
                'status' => $status
            );
            $this->db->insert($this->table, $userData);
        } else {
            $userId = $result['user_id'];
        }

        return $userId;
    }



    public function getAuthToken($token)
    {

        if (empty($token)) {
            return null;
        }

        $result = array();

        $query = "SELECT * FROM $this->table as ud INNER JOIN roles as r ON ud.role_id=r.role_id WHERE api_token = ? and ud.`status` = 'active'";
        $result = $this->db->rawQueryOne($query, array($token));
        $tokenExpiration = !empty($result['api_token_exipiration_days']) ? $result['api_token_exipiration_days'] : 0;


        // Tokens with expiration = 0 are tokens that never expire
        if ($tokenExpiration != 0) {
            $today = new \DateTime();
            $lastTokenDate = null;
            if (!empty($result['api_token_generated_datetime'])) {
                $lastTokenDate = new \DateTime($result['api_token_generated_datetime']);
            }
            if ((empty($lastTokenDate) || $today->diff($lastTokenDate)->days > $tokenExpiration)) {
                $general = new \Vlsm\Models\General($this->db);
                $data['api_token'] = base64_encode($result['user_id'] . "-" . $general->generateToken(3));
                $data['api_token_generated_datetime'] = $general->getDateTime();

                $this->db = $this->db->where('user_id', $result['user_id']);
                $id = $this->db->update($this->table, $data);

                if ($id > 0) {
                    $result['token_updated'] = true;
                    $result['new_token'] = $data['api_token'];
                } else {
                    $result['token_updated'] = false;
                }
            }
        }

        return $result;
    }

    public function getUserRole($userId)
    {
        $query = "SELECT r.*
                    FROM roles as r
                    INNER JOIN user_details as ud ON ud.role_id=r.role_id 
                    WHERE ud.user_id = ?";
        return $this->db->rawQueryOne($query, array($userId));
    }

    public function getUserRolePrivileges($userId)
    {
        $response = array();
        $query = "SELECT r.role_id, r.role_code,r.role_name, r.access_type, res.module,p.privilege_id,p.resource_id, p.privilege_name, p.display_name
                    FROM roles as r
                    INNER JOIN roles_privileges_map as rpm ON rpm.role_id=r.role_id 
                    INNER JOIN privileges as p ON rpm.privilege_id=p.privilege_id 
                    INNER JOIN resources as res ON res.resource_id=p.resource_id 
                    INNER JOIN user_details as ud ON ud.role_id=r.role_id 
                    WHERE ud.user_id = ?
                    ORDER by res.module, p.resource_id, p.display_name";
        $resultSet = $this->db->rawQuery($query, array($userId));
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

    public function userHistoryLog($userName, $loginStatus)
    {
        $general = new \Vlsm\Models\General($this->db);
        $ipaddress = '';
        $browserAgent = $_SERVER['HTTP_USER_AGENT'];
        $os = PHP_OS;
        if (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
        } else if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else if (isset($_SERVER['HTTP_X_FORWARDED'])) {
            $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
        } else if (isset($_SERVER['HTTP_FORWARDED_FOR'])) {
            $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
        } else if (isset($_SERVER['HTTP_FORWARDED'])) {
            $ipaddress = $_SERVER['HTTP_FORWARDED'];
        } else if (isset($_SERVER['REMOTE_ADDR'])) {
            $ipaddress = $_SERVER['REMOTE_ADDR'];
        } else {
            $ipaddress = 'UNKNOWN';
        }

        $data = array(
            'login_id' => $userName,
            'login_attempted_datetime' => $general->getDateTime(),
            'login_status' => $loginStatus,
            'ip_address' => $ipaddress,
            'browser'    => $browserAgent,
            'operating_system' => $os
        );
        $this->db->insert('user_login_history', $data);
    }
}
