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
        $this->db = $db;
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

    public function getActiveUserInfo()
    {
        $uQuery = "SELECT * FROM user_details where status='active'";
        return $this->db->rawQuery($uQuery);
    }

    public function addUserIfNotExists($name, $status = 'inactive', $role = 4)
    {
        $uQuery = "SELECT user_id FROM $this->table where user_name like '$name'";
        $result = $this->db->rawQueryOne($uQuery);
        if ($result == null) {
            $general = new \Vlsm\Models\General($this->db);
            $userId = $general->generateUserID();
            $userData = array(
                'user_id' => $userId,
                'user_name' => $name,
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
        $tokenExpiration = 30; //default is 30 days

        $query = "SELECT * FROM $this->table WHERE api_token = ? and `status` = 'active'";
        $result = $this->db->rawQueryOne($query, array($token));
        $tokenExpiration = !empty($result['api_token_exipiration_days']) ? $result['api_token_exipiration_days'] : 30;

        // Tokens with expiration = 0 are tokens that never expire
        if ($tokenExpiration == 0) {
            // do nothing
        } else if (
            empty($result['api_token_generated_datetime'])
            || $result['api_token_generated_datetime'] < date('Y-m-d H:i:s', strtotime("-$tokenExpiration days"))
        ) {
            $general = new \Vlsm\Models\General($this->db);
            $token = $general->generateUserID(6);
            $data['api_token'] = base64_encode($result['user_id']."-".$token);
            $data['api_token_generated_datetime'] = $general->getDateTime();

            $this->db = $this->db->where('user_id', $result['user_id']);
            $id = $this->db->update($this->table, $data);

            if ($id > 0) {
                $result['token_updated'] = true;
                $result['new_token'] = $token;
            } else {
                $result['token_updated'] = false;
            }
        }

        return $result;
    }
}
