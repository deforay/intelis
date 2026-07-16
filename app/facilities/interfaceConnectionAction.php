<?php

declare(strict_types=1);

use App\Exceptions\InterfaceApiException;
use App\Registries\AppRegistry;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\InterfaceApi\InterfaceFacilityAccessService;
use App\Services\InterfaceApi\InterfaceInstallationService;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// AJAX routes bypass the ACL middleware. The global CSRF middleware still
// protects this POST, and this explicit privilege check protects direct calls.
_requirePrivilege('/facilities/editFacility.php');

/** @var Psr\Http\Message\ServerRequestInterface $request */
$request = AppRegistry::get('request');
$input = _sanitizeInput($request->getParsedBody(), nullifyEmptyStrings: true);
$action = is_array($input) ? (string) ($input['action'] ?? '') : '';
$facilityId = is_array($input) ? (int) ($input['facilityId'] ?? 0) : 0;
$installationId = is_array($input) ? (string) ($input['installationId'] ?? '') : '';
$connectionCodeId = is_array($input) ? (int) ($input['activationCodeId'] ?? 0) : 0;

/** @var CommonService $commonService */
$commonService = ContainerRegistry::get(CommonService::class);
/** @var InterfaceFacilityAccessService $facilityAccess */
$facilityAccess = ContainerRegistry::get(InterfaceFacilityAccessService::class);
/** @var InterfaceInstallationService $installations */
$installations = ContainerRegistry::get(InterfaceInstallationService::class);

try {
    if (strtoupper($request->getMethod()) !== 'POST') {
        throw new InterfaceApiException('method_not_allowed', 'This action requires POST.', 405);
    }
    if (strtolower((string) $commonService->getGlobalConfig('interface_api_enabled')) !== 'yes') {
        throw new InterfaceApiException(
            'interface_api_disabled',
            'Interface Tool connection management is not enabled.',
            503
        );
    }
    $facilityAccess->assertCanManage($facilityId);
    $createdBy = 'user:' . (int) ($_SESSION['userId'] ?? 0);

    switch ($action) {
        case 'generate-new':
            $connectionCode = $installations->createActivationCode($facilityId, 30, $createdBy);
            $commonService->activityLog(
                'interface-connection-code-generated',
                "Generated a new Interface Tool connection code for facility {$facilityId}",
                'facility'
            );
            echo json_encode(['status' => 'success', 'connectionCode' => $connectionCode], JSON_THROW_ON_ERROR);
            break;

        case 'generate-reconnect':
            $connectionCode = $installations->createReconnectCode(
                $facilityId,
                $installationId,
                15,
                $createdBy
            );
            $auditMessage = "Generated an Interface Tool reconnect code for installation {$installationId}"
                . " at facility {$facilityId}";
            $commonService->activityLog(
                'interface-reconnect-code-generated',
                $auditMessage,
                'facility'
            );
            echo json_encode(['status' => 'success', 'connectionCode' => $connectionCode], JSON_THROW_ON_ERROR);
            break;

        case 'revoke-installation':
            if (!$installations->revokeForFacility($installationId, $facilityId)) {
                throw new InterfaceApiException(
                    'installation_not_found',
                    'The installation does not belong to this facility.',
                    404
                );
            }
            $commonService->activityLog(
                'interface-installation-revoked',
                "Revoked Interface Tool installation {$installationId} at facility {$facilityId}",
                'facility'
            );
            echo json_encode(['status' => 'success'], JSON_THROW_ON_ERROR);
            break;

        case 'revoke-code':
            if (!$installations->revokeActivationCode($connectionCodeId, $facilityId)) {
                throw new InterfaceApiException(
                    'connection_code_not_found',
                    'The connection code is no longer active.',
                    404
                );
            }
            $commonService->activityLog(
                'interface-connection-code-revoked',
                "Revoked Interface Tool connection code {$connectionCodeId} at facility {$facilityId}",
                'facility'
            );
            echo json_encode(['status' => 'success'], JSON_THROW_ON_ERROR);
            break;

        default:
            throw new InterfaceApiException('invalid_action', 'The requested action is invalid.', 422);
    }
} catch (InterfaceApiException $exception) {
    http_response_code($exception->getHttpStatus());
    echo json_encode([
        'status' => 'error',
        'error' => [
            'code' => $exception->getErrorCode(),
            'message' => $exception->getMessage(),
        ],
    ], JSON_THROW_ON_ERROR);
}
