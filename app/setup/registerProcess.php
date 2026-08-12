<?php

use Psr\Http\Message\ServerRequestInterface;
use GuzzleHttp\Client;
use App\Services\UsersService;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\ConfigService;
use App\Services\SystemService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Utilities\FileCacheUtility;
use App\Registries\ContainerRegistry;

// Sanitized values from $request object

/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

$_POST['loginId'] = strtolower(trim((string) $_POST['loginId']));
$_POST['loginId'] = str_replace(' ', '', $_POST['loginId']);


$userName = $_POST['userName'];
$emailId = $_POST['email'];
$loginId = $_POST['loginId'];
// Credentials come from the raw body, never the sanitized copy - see _rawInput().
$password = _rawInput('password');
$vlForm = $_POST['vl_form'];
$timeZone = $_POST['default_time_zone'];
$locale = $_POST['app_locale'];
$remoteURL = $_POST['remoteURL'];
$modulesToEnable = $_POST['enabledModules'];

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);

/** @var ConfigService $configService */
$configService = ContainerRegistry::get(ConfigService::class);

// Refuse to (re)run setup once an admin account already exists. This guard
// mirrors the one on the setup landing page (app/setup/index.php) and prevents
// the unauthenticated processor from being replayed to create a new admin.
$db->where("role_id=1");
if ((int) $db->getValue("user_details", "count(*)") !== 0) {
    header("Location:/login/login.php");
    exit;
}

$activeModulesArr = SystemService::getActiveModules();

$stsURL = $general->getRemoteURL();

function changeModuleWithQuotes($moduleArr): string
{
    return "'$moduleArr'";
}


try {
    if (!empty($userName) && !empty($password)) {

        // UPDATING s_vlsm_instance TABLE

        $data = [
            'vlsm_instance_id' => MiscUtility::generateULID(),
            'instance_mac_address' => MiscUtility::getMacAddress(),
            'instance_added_on' => DateUtility::getCurrentDateTime(),
            'instance_update_on' => DateUtility::getCurrentDateTime()
        ];

        // deleting just in case there is a row already inserted
        $db->delete('s_vlsm_instance');
        $db->insert('s_vlsm_instance', $data);


        // UPDATING SYSTEM CONFIG TABLE
        $instanceType = $_POST['instanceType'];
        $db->where('name', 'sc_user_type');
        $db->update("system_config", ['value' => $instanceType]);

        if (isset($_POST['testingLab']) && $_POST['testingLab'] != "") {
            $db->where('name', 'sc_testing_lab_id');
            $db->update("system_config", ['value' => $_POST['testingLab']]);
        }


        // UPDATING CONFIG FILE

        $updatedConfig = [
            'remoteURL' => $remoteURL,
            'modules.vl' => in_array('vl', $modulesToEnable),
            'modules.eid' => in_array('eid', $modulesToEnable),
            'modules.covid19' => in_array('covid19', $modulesToEnable),
            'modules.hepatitis' => in_array('hepatitis', $modulesToEnable),
            'modules.tb' => in_array('tb', $modulesToEnable),
            'modules.cd4' => in_array('cd4', $modulesToEnable),
            'modules.generic-tests' => in_array('generic-tests', $modulesToEnable),
            // Database credentials are written verbatim into config.production.php,
            // so they must be the raw submitted values. Sanitizing them turned a
            // password like mko)(*&^ into mko)(*&amp;^ and broke every connection
            // the installed instance ever made.
            'database.host' => (empty(_rawInput('dbHostName'))) ? '127.0.0.1' : _rawInput('dbHostName'),
            'database.username' => (empty(_rawInput('dbUserName'))) ? 'root' : _rawInput('dbUserName'),
            'database.password' => (empty(_rawInput('dbPassword'))) ? 'zaq12345' : _rawInput('dbPassword'),
            'database.db' => (empty(_rawInput('dbName'))) ? 'vlsm' : _rawInput('dbName'),
            'database.port' => (empty(_rawInput('dbPort'))) ? 3306 : _rawInput('dbPort'),
        ];

        if (isset($instanceType) && trim((string) $instanceType) === 'stsmode') {
            $updatedConfig['sts.api_key'] = $configService->generateAPIKeyForSTS();
        }


        $configService->updateConfig($updatedConfig);

        // If 'instance' is set in session, unset it
        if (isset($_SESSION['instance'])) {
            unset($_SESSION['instance']);
        }
        // Clear the file cache
        (ContainerRegistry::get(FileCacheUtility::class))->clear();


        $userPassword = $usersService->passwordHash($password);
        $userId = MiscUtility::generateUUID();

        $insertData = [
            'user_id' => $userId,
            'user_name' => $userName,
            'email' => $emailId,
            'login_id' => $loginId,
            'password' => $userPassword,
            'user_locale' => $locale,
            'role_id' => 1,
            'status' => 'active'
        ];
        $db->insert('user_details', $insertData);

        $configFields = [
            'vl_form',
            'default_time_zone',
            'app_locale'
        ];

        foreach ($configFields as $field) {
            if (isset($_POST[$field]) && !in_array(trim((string) $_POST[$field]), ['', '0'], true)) {
                $data = ['value' => trim((string) $_POST[$field])];
                $db->where('name', $field);
                $id = $db->update('global_config', $data);
            }
        }

        $modules = array_map("changeModuleWithQuotes", $activeModulesArr);

        $activeModules = implode(",", $modules);

        $privilegesSql = "SELECT p.privilege_id
                            FROM privileges AS p
                            INNER JOIN resources AS r ON r.resource_id=p.resource_id
                            WHERE r.module IN ($activeModules)";
        $privileges = $db->query($privilegesSql);
        foreach ($privileges as $privilege) {
            $privilegeId = (int) $privilege['privilege_id'];
            $db->rawQuery("INSERT IGNORE INTO roles_privileges_map(role_id,privilege_id) VALUES (?, ?)", [1, $privilegeId]);
        }

        if (!empty($stsURL) && $general->isLISInstance()) {
            $insertData['userId'] = $userId;
            $insertData['loginId'] = null; // We don't want to unintentionally end up creating admin users on STS
            $insertData['password'] = null; // We don't want to unintentionally end up creating admin users on STS
            $insertData['hashAlgorithm'] = 'phb'; // We don't want to unintentionally end up creating admin users on STS
            $insertData['role'] = 0; // We don't want to unintentionally end up creating admin users on STS
            $insertData['status'] = 'inactive';


            $apiUrl = $stsURL . "/api/v1.1/user/save-user-profile.php";
            $post = [
                'post' => json_encode($insertData),
                'x-api-key' => ConfigService::generateAPIKeyForSTS($stsURL)
            ];

            $client = new Client();
            $response = $client->post($apiUrl, [
                'form_params' => $post
            ]);

            $result = $response->getBody()->getContents();
        }

        $_SESSION['alertMsg'] = "New admin user added successfully";

        // Store credentials to flash on login page one time
        $_SESSION['setup_credentials'] = [
            'loginId' => $loginId,
            'password' => $password
        ];
    }
    header("Location:/login/login.php");
} catch (Throwable $exc) {
    LoggerUtility::logError($exc->getFile() . ':' . $exc->getLine() . ':' . $exc->getMessage(), [
        'exception' => $exc->getMessage(),
        'line' => $exc->getLine(),
        'file' => $exc->getFile()
    ]);
    throw $exc;
}
