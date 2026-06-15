#!/usr/bin/env php
<?php

// STS admin tool for backup key recovery.
//
//   php bin/backup-key-admin.php status [--lab <id>]
//       List escrowed keys and their release state.
//   php bin/backup-key-admin.php approve --lab <id> [--note "..."] [--ttl <minutes>]
//       Approve a release and mint a ONE-TIME recovery token for the operator to
//       enter on the replacement machine (setup.sh --recovery-token=...).
//   php bin/backup-key-admin.php show-code --lab <id>
//       Decrypt and print the offline recovery code (the key itself) for pure
//       break-glass when the new machine can't reach the STS. Read it to the
//       operator; they pass it as setup.sh --encryption-password=...
//   php bin/backup-key-admin.php revoke --lab <id>
//       Cancel an outstanding approval.
//
// Runs on the STS (that's where keys are escrowed). Read/approve only — it never
// mutates LIS data and is additive: existing flows are unaffected.

require_once __DIR__ . "/../bootstrap.php";

use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Utilities\CryptoUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

if (PHP_SAPI !== 'cli') {
    exit(CLI\ERROR);
}

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

$io = new SymfonyStyle(new ArgvInput(), new ConsoleOutput());

if (!$general->isSTSInstance()) {
    $io->warning('This instance is not an STS. Escrowed keys live on the STS; there is likely nothing to manage here.');
}

// Manual arg parse (not getopt): getopt() stops at the first non-option, which
// breaks the "subcommand then --flags" form (e.g. `approve --lab 5`).
$args = $_SERVER['argv'] ?? [];
array_shift($args); // drop script name
$cmd = 'status';
$lab = null;
$note = null;
$ttl = null;
$positionals = [];
for ($i = 0, $n = count($args); $i < $n; $i++) {
    $a = $args[$i];
    if ($a === '--lab') {
        $lab = (int) ($args[++$i] ?? 0);
    } elseif (str_starts_with($a, '--lab=')) {
        $lab = (int) substr($a, 6);
    } elseif ($a === '--note') {
        $note = $args[++$i] ?? null;
    } elseif (str_starts_with($a, '--note=')) {
        $note = substr($a, 7);
    } elseif ($a === '--ttl') {
        $ttl = (int) ($args[++$i] ?? 0);
    } elseif (str_starts_with($a, '--ttl=')) {
        $ttl = (int) substr($a, 6);
    } elseif (!str_starts_with($a, '-')) {
        $positionals[] = $a;
    }
}
$cmd = $positionals[0] ?? 'status';

/** Crockford base32 (no I/L/O/U), 16 chars ~= 80 bits, dictatable in 4 groups of 4. */
function bka_generate_token(): string
{
    $alphabet = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
    $t = '';
    for ($i = 0; $i < 16; $i++) {
        $t .= $alphabet[random_int(0, 31)];
    }
    return $t;
}

/** Normalize for hashing/comparison: strip separators, uppercase, fold look-alikes. */
function bka_normalize_token(string $t): string
{
    $t = strtoupper(preg_replace('/[^0-9A-Za-z]/', '', $t) ?? '');
    return strtr($t, ['I' => '1', 'L' => '1', 'O' => '0', 'U' => 'V']);
}

function bka_format_token(string $t): string
{
    return trim(chunk_split($t, 4, '-'), '-');
}

/** Latest escrowed row for a lab (highest key_version). */
function bka_latest_row(DatabaseService $db, int $lab): ?array
{
    $db->where('facility_id', $lab);
    $db->orderBy('key_version', 'DESC');
    $row = $db->getOne('s_lis_backup_key_recovery');
    return $row ?: null;
}

switch ($cmd) {
    case 'status':
    case 'list':
        if ($lab !== null) {
            $db->where('facility_id', $lab);
        }
        $db->orderBy('facility_id', 'ASC');
        $db->orderBy('key_version', 'DESC');
        $rows = $db->get('s_lis_backup_key_recovery') ?: [];
        if (empty($rows)) {
            $io->note($lab !== null ? "No escrowed key for lab {$lab}." : 'No escrowed keys yet.');
            exit(CLI\OK);
        }
        $table = [];
        foreach ($rows as $r) {
            $exp = $r['release_token_expires'] ?? null;
            $expired = $exp !== null && strtotime((string) $exp) < time();
            $table[] = [
                $r['facility_id'],
                $r['key_version'],
                substr((string) $r['fingerprint'], 0, 12) . '…',
                $r['release_status'],
                $r['saved_at'] ?? '',
                $exp ? ($exp . ($expired ? ' (expired)' : '')) : '-',
                $r['released_at'] ?? '-',
            ];
        }
        $io->table(['lab', 'ver', 'fingerprint', 'release_status', 'saved_at', 'token_expires', 'released_at'], $table);
        break;

    case 'approve':
        if ($lab === null) {
            $io->error('Usage: backup-key-admin.php approve --lab <id> [--note "..."] [--ttl <minutes>]');
            exit(CLI\ERROR);
        }
        $row = bka_latest_row($db, $lab);
        if ($row === null) {
            $io->error("No escrowed key for lab {$lab}. Nothing to approve.");
            exit(CLI\ERROR);
        }
        $ttlMin = $ttl !== null ? max(5, $ttl) : 1440; // default 24h
        $token = bka_generate_token();
        $expires = date('Y-m-d H:i:s', strtotime(DateUtility::getCurrentDateTime() . " +{$ttlMin} minutes"));

        $db->where('id', (int) $row['id']);
        $db->update('s_lis_backup_key_recovery', [
            'release_status'        => 'release_approved',
            'release_token_hash'    => hash('sha256', bka_normalize_token($token)),
            'release_token_expires' => $expires,
            'released_at'           => null,
            'release_note'          => $note !== null ? substr((string) $note, 0, 255) : null,
        ]);

        $io->success("Release approved for lab {$lab} (key v{$row['key_version']}).");
        $io->block(
            [
                'ONE-TIME RECOVERY TOKEN — give this to the operator:',
                '',
                '    ' . bka_format_token($token),
                '',
                "Valid for {$ttlMin} minutes, single use. On the new machine:",
                "    sudo bash setup.sh --db latest:/path/to/backups --recovery-token=" . bka_format_token($token),
            ],
            'RECOVERY TOKEN',
            'fg=black;bg=cyan',
            ' ',
            true
        );
        break;

    case 'show-code':
        if ($lab === null) {
            $io->error('Usage: backup-key-admin.php show-code --lab <id>');
            exit(CLI\ERROR);
        }
        $row = bka_latest_row($db, $lab);
        if ($row === null) {
            $io->error("No escrowed key for lab {$lab}.");
            exit(CLI\ERROR);
        }
        $code = CryptoUtility::decrypt((string) $row['encrypted_key']);
        if (!hash_equals((string) $row['fingerprint'], hash('sha256', $code))) {
            $io->error('Decrypted key failed its fingerprint check — store may be corrupt.');
            exit(CLI\ERROR);
        }
        $io->warning('Offline recovery code (the key itself) for lab ' . $lab . ', v' . $row['key_version'] . '. Treat as a secret.');
        $io->block(
            [
                'RECOVERY CODE — read to the operator only over a trusted channel:',
                '',
                '    ' . $code,
                '',
                'On the new machine, when the STS is unreachable:',
                '    sudo bash setup.sh --db <backup.gpg> --encryption-password=<code>',
            ],
            'RECOVERY CODE',
            'fg=black;bg=yellow',
            ' ',
            true
        );
        break;

    case 'revoke':
        if ($lab === null) {
            $io->error('Usage: backup-key-admin.php revoke --lab <id>');
            exit(CLI\ERROR);
        }
        $row = bka_latest_row($db, $lab);
        if ($row === null) {
            $io->error("No escrowed key for lab {$lab}.");
            exit(CLI\ERROR);
        }
        $db->where('id', (int) $row['id']);
        $db->update('s_lis_backup_key_recovery', [
            'release_status'        => 'stored',
            'release_token_hash'    => null,
            'release_token_expires' => null,
        ]);
        $io->success("Revoked any outstanding release approval for lab {$lab}.");
        break;

    default:
        $io->writeln('Backup key recovery admin (STS).');
        $io->listing([
            'status [--lab <id>]            list escrowed keys + release state',
            'approve --lab <id> [--note ..] [--ttl <min>]   mint a one-time recovery token',
            'show-code --lab <id>           print the offline recovery code (the key)',
            'revoke --lab <id>              cancel an outstanding approval',
        ]);
        break;
}
