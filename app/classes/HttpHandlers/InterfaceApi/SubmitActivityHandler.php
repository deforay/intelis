<?php

declare(strict_types=1);

namespace App\HttpHandlers\InterfaceApi;

use App\Exceptions\InterfaceApiException;
use App\Http\InterfaceApiResponse;
use App\Services\InterfaceApi\InterfaceConnectionService;
use App\Services\InstrumentActivityService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Accepts the activity an Interface Tool installation records about its instruments.
 *
 * This is operational data, not results: connection attempts, connection failures
 * and application starts. Storing is idempotent on the event identifier, so a client
 * that cannot confirm a delivery should simply send the batch again.
 */
final readonly class SubmitActivityHandler
{
    private const TOP_LEVEL_FIELDS = ['events'];
    private const REQUIRED_FIELDS = ['event_id', 'event_type', 'event_category', 'occurred_at'];
    private const OPTIONAL_FIELDS = [
        'instrument_id',
        'machine_type',
        'protocol',
        'connection_mode',
        'test_type',
        'outcome',
        'failure_code',
        'event_count',
        'app_version',
    ];

    public function __construct(private InstrumentActivityService $activity)
    {
    }

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $events = $this->readEvents($request);
            $installation = $request->getAttribute('interfaceInstallation');
            if (!is_array($installation)) {
                throw new InterfaceApiException(
                    'invalid_credential',
                    'The installation credential is invalid or revoked.',
                    401
                );
            }

            // The lab comes from the credential. Whatever lab the tool has configured
            // locally is ignored, exactly as it is for results.
            $labId = (int) ($installation['facility_id'] ?? 0);
            if ($labId <= 0) {
                throw new InterfaceApiException(
                    'facility_unavailable',
                    'The installation facility is unavailable.',
                    404
                );
            }

            $summary = $this->activity->store(
                $events,
                $labId,
                InstrumentActivityService::VIA_API,
                (string) ($installation['installation_id'] ?? '')
            );

            return InterfaceApiResponse::json([
                'status' => 'success',
                'stored' => $summary['stored'],
                'duplicates' => $summary['duplicates'],
                'skipped' => $summary['skipped'],
            ]);
        } catch (InterfaceApiException $exception) {
            return InterfaceApiResponse::error(
                $exception->getErrorCode(),
                $exception->getMessage(),
                $exception->getHttpStatus()
            );
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     * @throws InterfaceApiException
     */
    private function readEvents(ServerRequestInterface $request): array
    {
        if (!str_starts_with(strtolower($request->getHeaderLine('Content-Type')), 'application/json')) {
            throw new InterfaceApiException(
                'unsupported_media_type',
                'The activity request must use application/json.',
                415
            );
        }

        $input = $request->getParsedBody();
        if (!is_array($input)) {
            throw new InterfaceApiException('invalid_request', 'A JSON request body is required.', 400);
        }
        if (array_diff(array_keys($input), self::TOP_LEVEL_FIELDS) !== []) {
            throw new InterfaceApiException(
                'unexpected_field',
                'The activity request contains an unsupported field.',
                400
            );
        }

        $events = $input['events'] ?? null;
        if (!is_array($events) || $events === [] || !array_is_list($events)) {
            throw new InterfaceApiException(
                'invalid_request',
                'events must be a non-empty array of activity events.',
                400
            );
        }
        if (count($events) > InterfaceConnectionService::ACTIVITY_MAX_ITEMS) {
            throw new InterfaceApiException(
                'batch_too_large',
                'A batch may contain at most ' . InterfaceConnectionService::ACTIVITY_MAX_ITEMS . ' events.',
                413
            );
        }

        foreach ($events as $index => $event) {
            $this->assertEventShape($event, (int) $index);
        }

        return $events;
    }

    /** @throws InterfaceApiException */
    private function assertEventShape(mixed $event, int $index): void
    {
        if (!is_array($event)) {
            throw new InterfaceApiException('invalid_request', "events[$index] must be an object.", 400);
        }

        $unexpected = array_diff(
            array_keys($event),
            array_merge(self::REQUIRED_FIELDS, self::OPTIONAL_FIELDS)
        );
        if ($unexpected !== []) {
            throw new InterfaceApiException(
                'unexpected_field',
                "events[$index] contains an unsupported field: " . implode(', ', $unexpected) . '.',
                400
            );
        }

        $missing = array_diff(self::REQUIRED_FIELDS, array_keys($event));
        if ($missing !== []) {
            throw new InterfaceApiException(
                'invalid_request',
                "events[$index] is missing: " . implode(', ', $missing) . '.',
                400
            );
        }
    }
}
