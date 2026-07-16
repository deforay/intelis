<?php

declare(strict_types=1);

namespace App\HttpHandlers\InterfaceApi;

use App\Http\InterfaceApiResponse;
use App\Services\InterfaceApi\InterfaceConnectionService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class GetConnectionHandler
{
    private const FORBIDDEN_FACILITY_SELECTORS = ['facilityId', 'facility_id', 'labId', 'lab_id'];

    public function __construct(private InterfaceConnectionService $connections)
    {
    }

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $query = $request->getQueryParams();
        foreach (self::FORBIDDEN_FACILITY_SELECTORS as $selector) {
            if (array_key_exists($selector, $query)) {
                return InterfaceApiResponse::error(
                    'facility_selector_not_allowed',
                    'The facility is derived from the authenticated installation.',
                    400
                );
            }
        }

        $installation = $request->getAttribute('interfaceInstallation');
        if (!is_array($installation)) {
            return InterfaceApiResponse::error(
                'invalid_credential',
                'The installation credential is invalid or revoked.',
                401
            );
        }

        return InterfaceApiResponse::json([
            'status' => 'success',
            'connection' => $this->connections->getConnection($installation),
        ]);
    }
}
