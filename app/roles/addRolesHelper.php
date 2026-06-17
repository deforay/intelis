<?php

use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

// Only a user who can add roles may reach this helper.
_requirePrivilege('/roles/addRole.php');

$tableName1 = "roles";
$tableName2 = "roles_privileges_map";
try {
        if (isset($_POST['roleName']) && trim((string) $_POST['roleName']) !== "") {
                $data = ['role_name' => $_POST['roleName'], 'role_code' => $_POST['roleCode'], 'status' => $_POST['status'], 'access_type' => $_POST['accessType'], 'landing_page' => $_POST['landingPage']];
                $db->insert($tableName1, $data);
                $lastId = $db->getInsertId();
                if ($lastId != 0 && $lastId != '') {
                        if (isset($_POST['resource']) && $_POST['resource'] != '') {
                                // Server-side enforcement of the Access Type -> show_mode boundary
                                // (the JS filter in _privilege-matrix.php is UX only; never trust it).
                                $accessType = $_POST['accessType'] ?? '';
                                $allowedModes = $accessType === 'collection-site'
                                        ? ['sts', 'always', '']
                                        : ($accessType === 'testing-lab' ? ['lis', 'always', ''] : ['always', '']);
                                $privModeMap = [];
                                foreach ($db->rawQuery("SELECT privilege_id, show_mode FROM privileges") as $pm) {
                                        $privModeMap[(int) $pm['privilege_id']] = (string) ($pm['show_mode'] ?? 'always');
                                }
                                foreach ($_POST['resource'] as $key => $priviId) {
                                        if ($priviId == 'allow') {
                                                $mode = $privModeMap[(int) $key] ?? 'always';
                                                if (!in_array($mode, $allowedModes, true)) {
                                                        continue; // privilege not valid for this role's access type
                                                }
                                                $value = ['role_id' => $lastId, 'privilege_id' => $key];
                                                $db->insert($tableName2, $value);
                                        }
                                }
                        }
                        $_SESSION['alertMsg'] = _translate("Roles Added successfully");
                }
        }
        header("Location:roles.php");
} catch (Exception $exc) {
        LoggerUtility::logError($exc->getMessage());
}
