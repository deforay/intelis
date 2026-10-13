<?php

namespace App\Utilities;

use InvalidArgumentException;

/**
 * The requests-receiver.php arguments for a resend-requests command, from the
 * params the STS queued with it.
 *
 * The STS's queue dialog offers "Resend data from last N days" and sends it as
 * days, but the lab used to pass on only the module and manifest: the days were
 * dropped and the lab pulled only what the STS still had waiting, which leaves
 * out everything it already sent. requests-receiver.php takes a bare number as
 * "since that many days ago".
 */
final class ResendRequestsArgs
{
    private const MODULES = ['vl', 'eid', 'covid19', 'hepatitis', 'tb', 'cd4', 'generic-tests'];

    /**
     * @param array<string, mixed> $params
     * @return list<string> the arguments after the script path
     * @throws InvalidArgumentException for a param the lab must not act on
     */
    public static function from(array $params): array
    {
        $args = [];
        $module = $params['module'] ?? null;
        if (!empty($module)) {
            if (!in_array($module, self::MODULES, true)) {
                throw new InvalidArgumentException('Invalid module: ' . (string) $module);
            }
            array_push($args, '-t', (string) $module);
        }
        $manifest = $params['manifest'] ?? null;
        if (!empty($manifest)) {
            if (!preg_match('/^[A-Za-z0-9_\-\.]{1,64}$/', (string) $manifest)) {
                throw new InvalidArgumentException('Invalid manifest code');
            }
            array_push($args, '-m', (string) $manifest);
        }
        if (isset($params['days']) && $params['days'] !== '' && $params['days'] !== null) {
            $days = filter_var(
                $params['days'],
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1, 'max_range' => 3650]]
            );
            if ($days === false) {
                throw new InvalidArgumentException('Days out of range (1..3650)');
            }
            $args[] = (string) $days;
        }
        return $args;
    }
}
