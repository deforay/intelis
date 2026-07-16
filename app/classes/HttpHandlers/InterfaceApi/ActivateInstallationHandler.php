<?php

declare(strict_types=1);

namespace App\HttpHandlers\InterfaceApi;

use App\Exceptions\InterfaceApiException;
use App\Http\InterfaceApiResponse;
use App\Services\InterfaceApi\InterfaceInstallationService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class ActivateInstallationHandler
{
    private const INPUT_FIELDS = ['activationCode', 'sourceInstallationId', 'displayName'];

    public function __construct(private InterfaceInstallationService $installations)
    {
    }

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        try {
            if (!str_starts_with(strtolower($request->getHeaderLine('Content-Type')), 'application/json')) {
                throw new InterfaceApiException(
                    'unsupported_media_type',
                    'The activation request must use application/json.',
                    415
                );
            }

            $input = $request->getParsedBody();
            if (!is_array($input)) {
                throw new InterfaceApiException('invalid_request', 'A JSON request body is required.', 400);
            }
            if (array_diff(array_keys($input), self::INPUT_FIELDS) !== []) {
                throw new InterfaceApiException(
                    'unexpected_field',
                    'The activation request contains an unsupported field.',
                    400
                );
            }

            $activationCode = is_string($input['activationCode'] ?? null) ? $input['activationCode'] : '';
            $sourceInstallationId = is_string($input['sourceInstallationId'] ?? null)
                ? $input['sourceInstallationId']
                : null;
            $displayName = is_string($input['displayName'] ?? null) ? $input['displayName'] : null;
            $activation = $this->installations->activate(
                $activationCode,
                $sourceInstallationId,
                $displayName
            );

            return InterfaceApiResponse::json([
                'status' => 'success',
                'installation' => $activation,
            ], 201);
        } catch (InterfaceApiException $exception) {
            return InterfaceApiResponse::error(
                $exception->getErrorCode(),
                $exception->getMessage(),
                $exception->getHttpStatus()
            );
        }
    }
}
