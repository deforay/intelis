<?php

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */

use App\Registries\ContainerRegistry;
use App\Services\DatabaseService;

$request = $GLOBALS['request'];
$_POST = $request->getParsedBody();

/** @var DatabaseService $db */
$db = ContainerRegistry::get('db');

$tableName1 = "roles";
$db->beginTransaction();
try {
        $lastId = base64_decode((string) $_POST['roleId']);


        $db = $db->where('role_id', $lastId);
        $db->delete("roles_privileges_map");

        if (isset($_POST['roleName']) && trim((string) $_POST['roleName']) != "") {
                $data = array(
                        'role_name' => $_POST['roleName'],
                        'role_code' => $_POST['roleCode'],
                        'status' => $_POST['status'],
                        'access_type' => $_POST['accessType'],
                        'landing_page' => $_POST['landingPage']
                );
                $db = $db->where('role_id', $lastId);
                $db->update($tableName1, $data);
        }
        $roleQuery = "SELECT * from roles_privileges_map where role_id=?";
        $roleInfo = $db->rawQuery($roleQuery, [$lastId]);

        if ($lastId != 0 && $lastId != '') {
                foreach ($_POST['resource'] as $key => $priviId) {
                        if ($priviId == 'allow') {
                                $value = array('role_id' => $lastId, 'privilege_id' => $key);
                                $db->insert("roles_privileges_map", $value);
                        }
                }
                $_SESSION['alertMsg'] = _translate("Role updated successfully");
        }
        $db->commitTransaction();
        header("Location:roles.php");
} catch (Exception $exc) {
        error_log($exc->getMessage());
        error_log($exc->getTraceAsString());
        $db->rollbackTransaction();
}
