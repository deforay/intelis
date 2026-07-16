<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Contracts\InterfaceApi\InterfaceConnectionRepositoryInterface;
use App\Exceptions\InterfaceApiException;

final readonly class InMemoryInterfaceConnectionRepository implements InterfaceConnectionRepositoryInterface
{
    /** @param array<int, array<string, mixed>> $connections */
    public function __construct(private array $connections)
    {
    }

    public function getConnection(int $facilityId): array
    {
        return $this->connections[$facilityId]
            ?? throw new InterfaceApiException(
                'facility_unavailable',
                'The installation facility is unavailable.',
                404
            );
    }
}
