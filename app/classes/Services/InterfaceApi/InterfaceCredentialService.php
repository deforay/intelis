<?php

declare(strict_types=1);

namespace App\Services\InterfaceApi;

final class InterfaceCredentialService
{
    private const TOKEN_PREFIX = 'ifc';

    /** @return array{secret: string, hash: string} */
    public function issueSecret(): array
    {
        $secret = $this->base64UrlEncode(random_bytes(32));

        return [
            'secret' => $secret,
            'hash' => hash('sha256', $secret),
        ];
    }

    public function formatToken(string $installationId, string $secret): string
    {
        return self::TOKEN_PREFIX . '_' . $installationId . '.' . $secret;
    }

    /** @return array{installationId: string, secret: string}|null */
    public function parse(string $token): ?array
    {
        $pattern = '/^' . self::TOKEN_PREFIX . '_([0-9a-f-]{36})\.([A-Za-z0-9_-]{43})$/D';
        if (preg_match($pattern, $token, $matches) !== 1) {
            return null;
        }

        return [
            'installationId' => $matches[1],
            'secret' => $matches[2],
        ];
    }

    private function base64UrlEncode(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
