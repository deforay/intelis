#!/usr/bin/env php
<?php

declare(strict_types=1);

// Focused operator utility for the independent Interface API.
//
//   php bin/interface-api.php activation:create --facility-id <id> [--ttl <minutes>]
//   php bin/interface-api.php activation:reconnect --installation-id <uuid> [--ttl <minutes>]
//   php bin/interface-api.php installation:list [--facility-id <id>]
//   php bin/interface-api.php installation:revoke --installation-id <uuid>
//   php bin/interface-api.php code:revoke --code-id <id> --facility-id <id>

require_once __DIR__ . '/../bootstrap.php';

use App\Exceptions\InterfaceApiException;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\InterfaceApi\InterfaceInstallationService;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

if (PHP_SAPI !== 'cli') {
    exit(CLI\ERROR);
}

$io = new SymfonyStyle(new ArgvInput(), new ConsoleOutput());
/** @var InterfaceInstallationService $installations */
$installations = ContainerRegistry::get(InterfaceInstallationService::class);
/** @var CommonService $commonService */
$commonService = ContainerRegistry::get(CommonService::class);

$args = $_SERVER['argv'] ?? [];
array_shift($args);
$command = null;
$facilityId = null;
$installationId = null;
$ttl = 30;
$codeId = null;

for ($i = 0, $count = count($args); $i < $count; $i++) {
    $argument = $args[$i];
    if ($argument === '--facility-id') {
        $facilityId = (int) ($args[++$i] ?? 0);
    } elseif (str_starts_with($argument, '--facility-id=')) {
        $facilityId = (int) substr($argument, 14);
    } elseif ($argument === '--installation-id') {
        $installationId = (string) ($args[++$i] ?? '');
    } elseif (str_starts_with($argument, '--installation-id=')) {
        $installationId = substr($argument, 18);
    } elseif ($argument === '--ttl') {
        $ttl = (int) ($args[++$i] ?? 30);
    } elseif (str_starts_with($argument, '--ttl=')) {
        $ttl = (int) substr($argument, 6);
    } elseif ($argument === '--code-id') {
        $codeId = (int) ($args[++$i] ?? 0);
    } elseif (str_starts_with($argument, '--code-id=')) {
        $codeId = (int) substr($argument, 10);
    } elseif (!str_starts_with($argument, '-')) {
        $command ??= $argument;
    }
}

$command ??= 'help';

try {
    switch ($command) {
        case 'activation:create':
            if ($facilityId === null || $facilityId <= 0) {
                $io->error('Usage: interface-api.php activation:create --facility-id <id> [--ttl <minutes>]');
                exit(CLI\ERROR);
            }
            if (strtolower((string) $commonService->getGlobalConfig('interface_api_enabled')) !== 'yes') {
                $io->warning(
                    'The Interface API is disabled. This code cannot be used until an administrator enables the API.'
                );
            }
            $activation = $installations->createActivationCode($facilityId, $ttl, get_current_user() ?: 'cli');
            $io->success('Activation code created. It is shown once; share it through a secure channel.');
            $io->definitionList(
                ['Facility ID' => (string) $facilityId],
                ['Activation code' => $activation['activationCode']],
                ['Expires at' => $activation['expiresAt']]
            );
            break;

        case 'activation:reconnect':
            if ($installationId === null || $installationId === '') {
                $io->error(
                    'Usage: interface-api.php activation:reconnect --installation-id <uuid> [--ttl <minutes>]'
                );
                exit(CLI\ERROR);
            }
            $installation = $installations->getInstallation($installationId);
            if ($installation === null) {
                $io->error('Installation not found.');
                exit(CLI\ERROR);
            }
            $activation = $installations->createReconnectCode(
                (int) $installation['facility_id'],
                $installationId,
                $ttl,
                get_current_user() ?: 'cli'
            );
            $io->success('Reconnect code created. It is shown once; share it through a secure channel.');
            $io->definitionList(
                ['Installation ID' => $installationId],
                ['Facility ID' => (string) $installation['facility_id']],
                ['Connection code' => $activation['activationCode']],
                ['Expires at' => $activation['expiresAt']]
            );
            break;

        case 'installation:list':
            $rows = $installations->listInstallations($facilityId);
            if ($rows === []) {
                $io->note('No Interface Tool installations found.');
                break;
            }
            $table = [];
            foreach ($rows as $row) {
                $table[] = [
                    $row['installation_id'],
                    $row['source_installation_id'],
                    $row['facility_id'],
                    $row['facility_code'],
                    $row['display_name'],
                    $row['status'],
                    $row['last_seen_at'] ?? '-',
                ];
            }
            $io->table(
                ['installation', 'source', 'facility', 'code', 'name', 'status', 'last seen'],
                $table
            );
            break;

        case 'installation:revoke':
            if ($installationId === null || $installationId === '') {
                $io->error('Usage: interface-api.php installation:revoke --installation-id <uuid>');
                exit(CLI\ERROR);
            }
            if (!$installations->revoke($installationId)) {
                $io->error('Installation not found or could not be revoked.');
                exit(CLI\ERROR);
            }
            $io->success("Installation {$installationId} revoked.");
            break;

        case 'code:revoke':
            if ($codeId === null || $codeId <= 0 || $facilityId === null || $facilityId <= 0) {
                $io->error('Usage: interface-api.php code:revoke --code-id <id> --facility-id <id>');
                exit(CLI\ERROR);
            }
            if (!$installations->revokeActivationCode($codeId, $facilityId)) {
                $io->error('Connection code not found, already used, expired, or already revoked.');
                exit(CLI\ERROR);
            }
            $io->success("Connection code {$codeId} revoked.");
            break;

        default:
            $io->writeln([
                'Interface API operator utility',
                '',
                '  activation:create  --facility-id <id> [--ttl <minutes>]',
                '  activation:reconnect --installation-id <uuid> [--ttl <minutes>]',
                '  installation:list  [--facility-id <id>]',
                '  installation:revoke --installation-id <uuid>',
                '  code:revoke --code-id <id> --facility-id <id>',
            ]);
            break;
    }
} catch (InterfaceApiException $exception) {
    $io->error($exception->getMessage());
    exit(CLI\ERROR);
} catch (Throwable) {
    // Do not print database details or request secrets from this credential tool.
    $io->error('The Interface API operation failed. Check that migrations are current and try again.');
    exit(CLI\ERROR);
}

exit(CLI\OK);
