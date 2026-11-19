<?php

// The filters and scope of the Testing Target Report, read once for the grid,
// the Excel export and the dashboard alert, so the three always agree.

use App\Services\CommonService;
use App\Utilities\DateUtility;
use App\Services\TestingTargetReportService;

if (!function_exists('_testingTargetFilters')) {
    /**
     * @param array<string, mixed> $post the sanitized request body
     * @return array{from:string, to:string, labIds:list<int>, targetType:int, scope:list<string>, fill:bool}
     */
    function _testingTargetFilters(array $post, CommonService $general): array
    {
        // The page posts dateRange. The dashboard posts its own range as
        // sampleTestDate. Both read "dd-Mon-yyyy to dd-Mon-yyyy".
        $range = trim((string) ($post['dateRange'] ?? $post['sampleTestDate'] ?? ''));
        $parts = array_map('trim', explode(' to ', $range));
        $from = DateUtility::isoDateFormat($parts[0] ?? '');
        $to = DateUtility::isoDateFormat($parts[1] ?? ($parts[0] ?? ''));
        if ($from === null || $to === null) {
            // No range: the last 12 months, this one included.
            $from = date('Y-m-01', strtotime('first day of -11 months'));
            $to = date('Y-m-d');
        }

        $labIds = array_values(array_filter(array_map('intval', (array) ($post['labIds'] ?? []))));

        // A lab install sees its own lab only.
        $scope = [];
        $labScope = $general->labScopeWhere('t');
        if ($labScope !== '') {
            $scope[] = $labScope;
            $ownLab = (int) ($_SESSION['labId'] ?? 0);
            $labIds = $labIds === [] || in_array($ownLab, $labIds, true) ? [$ownLab] : [-1];
        }

        // A facility user on the STS sees the samples of their facilities only.
        // Their counts are a part of each lab's work, so labs they sent nothing
        // to are not listed at zero.
        $fill = true;
        if ($general->isSTSInstance() && !empty($_SESSION['facilityMap'])) {
            $facilityIds = array_filter(array_map('intval', explode(',', (string) $_SESSION['facilityMap'])));
            $scope[] = 't.facility_id IN (' . (implode(',', $facilityIds) ?: '0') . ')';
            $fill = false;
        }

        $targetType = (int) ($post['targetType'] ?? TestingTargetReportService::TARGET_ALL);
        $narrowing = [TestingTargetReportService::TARGET_NOT_MET, TestingTargetReportService::TARGET_MET];
        if (!in_array($targetType, $narrowing, true)) {
            $targetType = TestingTargetReportService::TARGET_ALL;
        }

        return [
            'from' => $from,
            'to' => $to,
            'labIds' => $labIds,
            'targetType' => $targetType,
            'scope' => $scope,
            'fill' => $fill,
        ];
    }

    /**
     * Month as the report shows it, for example Nov-2026.
     */
    function _testingTargetMonth(string $month): string
    {
        return date('M-Y', strtotime($month . '-01'));
    }
}
