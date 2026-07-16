<?php

declare(strict_types=1);

namespace App\Services\InterfaceApi;

use App\Contracts\InterfaceApi\InterfaceConnectionRepositoryInterface;

final readonly class InterfaceConnectionService
{
    public const ACTIVATION_MAX_ATTEMPTS = 10;
    public const ACTIVATION_WINDOW_SECONDS = 60;
    public const ACTIVATION_MAX_BODY_BYTES = 16384;

    public function __construct(private InterfaceConnectionRepositoryInterface $repository)
    {
    }

    /** @param array<string, mixed> $installation */
    public function getConnection(array $installation): array
    {
        $connection = $this->repository->getConnection((int) $installation['facility_id']);
        $scopes = $installation['credential_scopes'] ?? [];

        return $connection + [
            'capabilities' => [
                'apiVersion' => 'v1',
                'adapter' => [
                    'id' => 'intelis',
                    'managed' => true,
                ],
                'operations' => [
                    'connectionRead' => true,
                    'resultsWrite' => false,
                    'telemetryWrite' => false,
                ],
                'credentialScopes' => array_values(is_array($scopes) ? $scopes : []),
            ],
            'limits' => [
                'activation' => [
                    'maxAttempts' => self::ACTIVATION_MAX_ATTEMPTS,
                    'windowSeconds' => self::ACTIVATION_WINDOW_SECONDS,
                    'maxBodyBytes' => self::ACTIVATION_MAX_BODY_BYTES,
                ],
            ],
        ];
    }
}
