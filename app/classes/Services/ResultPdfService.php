<?php

namespace App\Services;

use App\Services\DatabaseService;
use App\Utilities\MiscUtility;

class ResultPdfService
{
    protected DatabaseService $db;
    public function __construct(DatabaseService $db)
    {
        $this->db = $db;
    }

    public function getReportTemplate($labId): ?string
    {
        if (empty($labId)) return null;
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
