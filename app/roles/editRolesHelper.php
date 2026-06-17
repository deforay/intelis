<?php

// Sanitized values from $request object
/** @var ServerRequestInterface $request */
use Psr\Http\Message\ServerRequestInterface;
use App\Registries\AppRegistry;
use App\Registries\ContainerRegistry;
use App\Services\DatabaseService;

$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

// Only a user who can edit roles may reach this helper.
_requirePrivilege('/roles/editRole.php');

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
                // Server-side enforcement of the Access Type -> show_mode boundary
                // (the JS filter in _privilege-matrix.php is UX only; never trust it).
                // The superadmin role (id 1) is exempt -- it keeps every privilege.
                $accessType = $_POST['accessType'] ?? '';
                $enforceMode = ((int) $roleId !== 1);
                $allowedModes = $accessType === 'collection-site'
                        ? ['sts', 'always', '']
                        : ($accessType === 'testing-lab' ? ['lis', 'always', ''] : ['always', '']);
                $privModeMap = [];
                if ($enforceMode) {
                        foreach ($db->rawQuery("SELECT privilege_id, show_mode FROM privileges") as $pm) {
                                $privModeMap[(int) $pm['privilege_id']] = (string) ($pm['show_mode'] ?? 'always');
                        }
                }
                foreach ($_POST['resource'] as $key => $priviId) {
                        if ($priviId == 'allow') {
                                if ($enforceMode) {
                                        $mode = $privModeMap[(int) $key] ?? 'always';
                                        if (!in_array($mode, $allowedModes, true)) {
                                                continue; // privilege not valid for this role's access type
                                        }
                                }
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
