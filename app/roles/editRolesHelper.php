<?php

// Sanitized values from $request object
/** @var ServerRequest $request */
use Laminas\Diactoros\ServerRequest;
use App\Registries\AppRegistry;
use App\Registries\ContainerRegistry;
use App\Services\DatabaseService;

$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

$tableName1 = "roles";
$db->beginTransaction();
try {
        $roleId = base64_decode((string) $_POST['roleId']);


        $db->where('role_id', $roleId);
        $db->delete("roles_privileges_map");

        if (isset($_POST['roleName']) && trim((string) $_POST['roleName']) !== "") {
                $data = ['role_name' => $_POST['roleName'], 'role_code' => $_POST['roleCode'], 'status' => $_POST['status'], 'access_type' => $_POST['accessType'], 'landing_page' => $_POST['landingPage']];
                $db->where('role_id', $roleId);
                $db->update($tableName1, $data);
        }
        $roleQuery = "SELECT * from roles_privileges_map where role_id=?";
        $roleInfo = $db->rawQuery($roleQuery, [$roleId]);

        if (!empty($roleId) && $roleId > 0) {
                foreach ($_POST['resource'] as $key => $priviId) {
                        if ($priviId == 'allow') {
                                $value = ['role_id' => $roleId, 'privilege_id' => $key];
                                $db->insert("roles_privileges_map", $value);
                        }
                }
                $_SESSION['alertMsg'] = _translate("Role updated successfully");
        }
        $db->commitTransaction();

        header("Location:roles.php");
} catch (Exception $exc) {
        error_log($exc->getMessage());

        $db->rollbackTransaction();
}
