<?php

declare(strict_types=1);

namespace App\HttpHandlers\InterfaceApi;

use App\Exceptions\InterfaceApiException;
use App\Http\InterfaceApiResponse;
use App\Services\InterfaceApi\InterfaceConnectionService;
use App\Services\InterfacingService;
use App\Services\TestResultsService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Accepts analyzer results from an Interface Tool installation.
 *
 * A whole run should arrive in one request: the copies-versus-log rule compares
 * rows against each other and can only be applied within a single batch.
 *
 * Every row is reported back individually. A row that cannot be applied does not
 * fail the request, because one unmatched sample must not block the rest of a run.
 */
final readonly class SubmitResultsHandler
{
    private const TOP_LEVEL_FIELDS = ['results'];
    private const REQUIRED_FIELDS = ['id', 'order_id', 'test_id', 'results', 'test_unit', 'machine_used'];
    private const OPTIONAL_FIELDS = [
        'instrument_id',
        'tested_by',
        'authorised_date_time',
        'result_accepted_date_time',
        'raw_text',
    ];

    public function __construct(
        private InterfacingService $interfacing,
        private TestResultsService $testResults
    ) {
    }

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $rows = $this->readRows($request);
            $installation = $request->getAttribute('interfaceInstallation');
            if (!is_array($installation)) {
                throw new InterfaceApiException(
                    'invalid_credential',
                    'The installation credential is invalid or revoked.',
                    401
                );
            }

            // The lab comes from the credential. The payload has no say in it.
            $labId = (int) ($installation['facility_id'] ?? 0);
            if ($labId <= 0) {
                throw new InterfaceApiException(
                    'facility_unavailable',
                    'The installation facility is unavailable.',
                    404
                );
            }

            $report = $this->interfacing->importBatch($rows, $labId);
            $imported = count(array_filter($report, static fn(array $r): bool => $r['outcome'] === 'accepted'));

            if ($imported > 0) {
                $this->testResults->resultImportStats(
                    $imported,
                    'interface-api',
                    'installation:' . ($installation['installation_id'] ?? '')
                );
            }

            return InterfaceApiResponse::json([
                'status' => 'success',
                'imported' => $imported,
                'results' => $report,
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
    private function readRows(ServerRequestInterface $request): array
    {
        if (!str_starts_with(strtolower($request->getHeaderLine('Content-Type')), 'application/json')) {
            throw new InterfaceApiException(
                'unsupported_media_type',
                'The results request must use application/json.',
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
                'The results request contains an unsupported field.',
                400
            );
        }

        $rows = $input['results'] ?? null;
        if (!is_array($rows) || $rows === [] || array_is_list($rows) === false) {
            throw new InterfaceApiException(
                'invalid_request',
                'results must be a non-empty array of analyzer results.',
                400
            );
        }
        if (count($rows) > InterfaceConnectionService::RESULTS_MAX_ITEMS) {
            throw new InterfaceApiException(
                'batch_too_large',
                'A batch may contain at most ' . InterfaceConnectionService::RESULTS_MAX_ITEMS . ' results.',
                413
            );
        }

        foreach ($rows as $index => $row) {
            $this->assertRowShape($row, (int) $index);
        }

        return $rows;
    }

    /** @throws InterfaceApiException */
    private function assertRowShape(mixed $row, int $index): void
    {
        if (!is_array($row)) {
            throw new InterfaceApiException('invalid_request', "results[$index] must be an object.", 400);
        }

        $allowed = array_merge(self::REQUIRED_FIELDS, self::OPTIONAL_FIELDS);
        $unexpected = array_diff(array_keys($row), $allowed);
        if ($unexpected !== []) {
            throw new InterfaceApiException(
                'unexpected_field',
                "results[$index] contains an unsupported field: " . implode(', ', $unexpected) . '.',
                400
            );
        }

        $missing = array_diff(self::REQUIRED_FIELDS, array_keys($row));
        if ($missing !== []) {
            throw new InterfaceApiException(
                'invalid_request',
                "results[$index] is missing: " . implode(', ', $missing) . '.',
                400
            );
        }

        if (!is_int($row['id'])) {
            throw new InterfaceApiException('invalid_request', "results[$index].id must be an integer.", 400);
        }
        if (!is_string($row['order_id']) || !is_string($row['test_id'])) {
            throw new InterfaceApiException(
                'invalid_request',
                "results[$index].order_id and test_id must be strings.",
                400
            );
        }
    }
}
