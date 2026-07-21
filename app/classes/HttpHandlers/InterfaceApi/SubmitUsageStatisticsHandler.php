<?php

declare(strict_types=1);

namespace App\HttpHandlers\InterfaceApi;

use App\Exceptions\InterfaceApiException;
use App\Http\InterfaceApiResponse;
use App\Services\InterfaceApi\InterfaceConnectionService;
use App\Services\InstrumentUsageStatisticsService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Accepts the daily test volume an Interface Tool installation reports.
 *
 * These are counts, not results and not events: how many tests an instrument ran on
 * a day, and how many of them failed. Nothing that identifies a sample or a patient
 * belongs here, which the field allowlist below makes structural rather than a rule
 * someone has to remember.
 *
 * Storing is idempotent on the aggregate identifier and revision, so an installation
 * that cannot confirm a delivery should simply send the batch again.
 */
final readonly class SubmitUsageStatisticsHandler
{
    private const TOP_LEVEL_FIELDS = ['summaries'];
    private const REQUIRED_FIELDS = [
        'aggregate_id',
        'activity_date',
        'total_tests',
        'successful_tests',
        'failed_tests',
        'revision',
    ];
    private const OPTIONAL_FIELDS = [
        'instrument_id',
        'machine_type',
        'test_type',
        'first_test_at',
        'last_test_at',
    ];

    public function __construct(private InstrumentUsageStatisticsService $usageStatistics)
    {
    }

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $summaries = $this->readSummaries($request);
            $installation = $request->getAttribute('interfaceInstallation');
            if (!is_array($installation)) {
                throw new InterfaceApiException(
                    'invalid_credential',
                    'The installation credential is invalid or revoked.',
                    401
                );
            }

            // The lab comes from the credential, exactly as it does for results and
            // activity. Whatever lab the tool has configured locally is ignored.
            $labId = (int) ($installation['facility_id'] ?? 0);
            if ($labId <= 0) {
                throw new InterfaceApiException(
                    'facility_unavailable',
                    'The installation facility is unavailable.',
                    404
                );
            }

            $summary = $this->usageStatistics->store(
                $summaries,
                $labId,
                InstrumentUsageStatisticsService::VIA_API,
                (string) ($installation['installation_id'] ?? '')
            );

            return InterfaceApiResponse::json(['status' => 'success'] + $summary);
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
    private function readSummaries(ServerRequestInterface $request): array
    {
        if (!str_starts_with(strtolower($request->getHeaderLine('Content-Type')), 'application/json')) {
            throw new InterfaceApiException(
                'unsupported_media_type',
                'The usage statistics request must use application/json.',
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
                'The usage statistics request contains an unsupported field.',
                400
            );
        }

        $summaries = $input['summaries'] ?? null;
        if (!is_array($summaries) || $summaries === [] || !array_is_list($summaries)) {
            throw new InterfaceApiException(
                'invalid_request',
                'summaries must be a non-empty array of daily summaries.',
                400
            );
        }
        if (count($summaries) > InterfaceConnectionService::USAGE_STATISTICS_MAX_ITEMS) {
            throw new InterfaceApiException(
                'batch_too_large',
                'A batch may contain at most '
                    . InterfaceConnectionService::USAGE_STATISTICS_MAX_ITEMS . ' summaries.',
                413
            );
        }

        foreach ($summaries as $index => $summary) {
            $this->assertSummaryShape($summary, (int) $index);
        }

        return $summaries;
    }

    /** @throws InterfaceApiException */
    private function assertSummaryShape(mixed $summary, int $index): void
    {
        if (!is_array($summary)) {
            throw new InterfaceApiException('invalid_request', "summaries[$index] must be an object.", 400);
        }

        $unexpected = array_diff(
            array_keys($summary),
            array_merge(self::REQUIRED_FIELDS, self::OPTIONAL_FIELDS)
        );
        if ($unexpected !== []) {
            throw new InterfaceApiException(
                'unexpected_field',
                "summaries[$index] contains an unsupported field: " . implode(', ', $unexpected) . '.',
                400
            );
        }

        $missing = array_diff(self::REQUIRED_FIELDS, array_keys($summary));
        if ($missing !== []) {
            throw new InterfaceApiException(
                'invalid_request',
                "summaries[$index] is missing: " . implode(', ', $missing) . '.',
                400
            );
        }
    }
}
