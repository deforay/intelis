<?php

namespace App\Services;

final class BulkResultStatusService
{
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
}
