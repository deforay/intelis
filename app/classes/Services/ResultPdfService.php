<?php

namespace App\Services;

use App\Utilities\MiscUtility;
use App\Services\DatabaseService;

final class ResultPdfService
{
    public function __construct(protected DatabaseService $db)
    {
    }

    public function getReportTemplate(?string $labId): ?string
    {
        if ($labId === null || $labId === '' || $labId === '0') {
            return null;
        }
        $sql = "SELECT facility_attributes->>'$.report_template' as `report_template` FROM facility_details WHERE facility_id = ?";
        $params = [$labId];
        $result = $this->db->rawQueryOne($sql, $params);
        $reportTemplate = $result['report_template'] ?? null;
        $reportTemplatePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . "labs"  . DIRECTORY_SEPARATOR . $labId  . DIRECTORY_SEPARATOR . "report-template" . DIRECTORY_SEPARATOR . $reportTemplate;
        if (!empty($reportTemplate) && MiscUtility::fileExists($reportTemplatePath)) {
            return $reportTemplatePath;
        } else {
            return null;
        }
    }
}
