<?php

use App\Utilities\DateUtility;
use App\Services\CommonService;

/**
 * Filter conditions shared by the VL Sample Status / TAT table endpoint
 * (getVlSampleTATDetails.php) and its Excel export
 * (vlSampleTATDetailsExportInExcel.php).
 *
 * Both endpoints rebuild the conditions from the posted filters so the export
 * always matches what is on screen without relying on a session snapshot
 * written by whichever request happened to run last.
 *
 * @return array{0: string[], 1: array<int, scalar>} [conditions, bind params]
 */
function vlSampleTatFilterConditions(array $post, CommonService $general): array
{
    $where = [];
    $params = [];

    if ($general->isSTSInstance()) {
        if (!empty($_SESSION['facilityMap'])) {
            $where[] = " vl.facility_id IN (" . $_SESSION['facilityMap'] . ")";
        }
    } else {
        $where[] = " vl.result_status != " . SAMPLE_STATUS\RECEIVED_AT_CLINIC;
    }

    // Returns '' unless this instance is lab-scoped, so it is safe everywhere.
    if ($labScope = $general->labScopeWhere('vl')) {
        $where[] = $labScope;
    }

    if (!empty($post['batchCode'])) {
        $where[] = ' b.batch_code = ?';
        $params[] = (string) $post['batchCode'];
    }
    if (!empty($post['sampleCollectionDate'])) {
        [$startDate, $endDate] = DateUtility::convertDateRange($post['sampleCollectionDate']);
        if ($startDate !== '' && $endDate !== '') {
            $where[] = " DATE(vl.sample_collection_date) BETWEEN ? AND ?";
            $params[] = $startDate;
            $params[] = $endDate;
        }
    }
    if (!empty($post['sampleReceivedDateAtLab'])) {
        [$labStartDate, $labEndDate] = DateUtility::convertDateRange($post['sampleReceivedDateAtLab']);
        if ($labStartDate !== '' && $labEndDate !== '') {
            $where[] = " DATE(vl.sample_received_at_lab_datetime) BETWEEN ? AND ?";
            $params[] = $labStartDate;
            $params[] = $labEndDate;
        }
    }
    if (!empty($post['sampleTestedDate'])) {
        [$testedStartDate, $testedEndDate] = DateUtility::convertDateRange($post['sampleTestedDate']);
        if ($testedStartDate !== '' && $testedEndDate !== '') {
            $where[] = " DATE(vl.sample_tested_datetime) BETWEEN ? AND ?";
            $params[] = $testedStartDate;
            $params[] = $testedEndDate;
        }
    }
    if (!empty($post['sampleType'])) {
        $where[] = ' vl.specimen_type = ?';
        $params[] = (int) $post['sampleType'];
    }
    // The filter dropdown lists testing labs, so it matches the testing lab on
    // the sample, the same column the charts on this page filter on.
    if (!empty($post['labName'])) {
        $where[] = ' vl.lab_id = ?';
        $params[] = (int) $post['labName'];
    }
    if (!empty($post['facilityName'])) {
        $where[] = ' f.facility_id IN (' . $post['facilityName'] . ')';
    }

    return [$where, $params];
}
