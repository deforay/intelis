<?php

declare(strict_types=1);

namespace App\Contracts\InterfaceApi;

interface InterfaceConnectionRepositoryInterface
{
    /**
     * Loads connection metadata for a server-derived facility only.
     *
     * @return array<string, mixed>
     */
    public function getConnection(int $facilityId): array;
}
