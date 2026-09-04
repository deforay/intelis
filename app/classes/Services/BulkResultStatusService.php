<?php

namespace App\Services;

final class BulkResultStatusService
{
    /** How many sample codes a skip message names before it summarises the rest. */
    private const MAX_SKIPPED_LISTED = 15;

    public function isOverwriteEnabled(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array((string) $value, ['1', 'true', 'yes', 'on'], true);
    }

    public function getBulkUserData(array $currentRow, array $request): array
    {
        $fieldMap = [
            'approver' => [
                'column' => 'result_approved_by',
                'overwriteKey' => 'overwriteApprover',
            ],
            'tester' => [
                'column' => 'tested_by',
                'overwriteKey' => 'overwriteTester',
            ],
            'reviewer' => [
                'column' => 'result_reviewed_by',
                'overwriteKey' => 'overwriteReviewer',
            ],
        ];

        $userData = [];
        foreach ($fieldMap as $requestKey => $config) {
            $requestedValue = $request[$requestKey] ?? '';
            if ($requestedValue === '') {
                continue;
            }

            $currentValue = $currentRow[$config['column']] ?? null;
            $shouldOverwrite = $this->isOverwriteEnabled($request[$config['overwriteKey']] ?? null);
            if ($shouldOverwrite || empty($currentValue)) {
                $userData[$config['column']] = $requestedValue;
            }
        }

        return $userData;
    }

    /**
     * The reply when the run did not finish.
     *
     * The grid used to be told nothing at all when a bulk update threw, which
     * read on screen as if it had worked. Saying so plainly is the point.
     *
     * @return array{status: string, updated: int, skipped: string[], message: string}
     */
    public function failureResponse(): array
    {
        return [
            'status' => 'error',
            'updated' => 0,
            'skipped' => [],
            'message' => _translate('The status could not be updated. Please try again.'),
        ];
    }

    /**
     * The reply the results grid gets back from a bulk status change.
     *
     * A selection is a list of separate patients, so one sample with no result
     * is no reason to refuse the rest: the samples that can take the status get
     * it, and the ones that cannot are named here so somebody can go and find
     * them. Named rather than counted, because a count is not something anyone
     * can act on.
     *
     * @param int $updated rows the status was applied to
     * @param string[] $skippedSampleCodes samples left at their existing status
     * @return array{status: string, updated: int, skipped: string[], message: string}
     */
    public function buildResponse(int $updated, array $skippedSampleCodes): array
    {
        $skippedSampleCodes = array_values(array_filter(array_map(
            static fn($code): string => trim((string) $code),
            $skippedSampleCodes
        ), static fn(string $code): bool => $code !== ''));

        if ($skippedSampleCodes === []) {
            $message = _translate('Updated successfully.');
        } else {
            // A selection of several hundred would otherwise arrive as an alert
            // nobody can read to the end of.
            $shown = array_slice($skippedSampleCodes, 0, self::MAX_SKIPPED_LISTED);
            $codeList = implode(', ', $shown);
            $remaining = count($skippedSampleCodes) - count($shown);
            if ($remaining > 0) {
                $codeList .= ' ' . sprintf(_translate('and %s more'), $remaining);
            }

            $message = sprintf(
                _translate('Updated: %1$s. Not accepted because no result is recorded: %2$s'),
                $updated,
                $codeList
            );
        }

        return [
            'status' => 'ok',
            'updated' => $updated,
            'skipped' => $skippedSampleCodes,
            'message' => $message,
        ];
    }
}
