<?php

namespace Vlsm\Models;


/**
 * General functions
 *
 * @author Amit
 */

class Vl
{

    protected $db = null;
    protected $table = 'vl_request_form';
    protected $shortCode = 'VL';
    protected $suppressedArray = array(
        'target not detected',
        'tnd',
        'not detected',
        'below detection limit',
        'below detection level',
        'bdl',
        'suppressed',
        'negative',
        'negat'
    );

    protected $suppressionLimit = 1000;

    public function __construct($db = null)
    {
        $this->db = $db;
    }

    public function generateVLSampleID($provinceCode, $sampleCollectionDate, $sampleFrom = null, $provinceId = '', $maxCodeKeyVal = null)
    {

        $general = new \Vlsm\Models\General($this->db);
        $globalConfig = $general->getGlobalConfig();
        $vlsmSystemConfig = $general->getSystemConfig();
        $sampleID = '';


        $remotePrefix = '';
        $sampleCodeKeyCol = 'sample_code_key';
        $sampleCodeCol = 'sample_code';
        if ($vlsmSystemConfig['sc_user_type'] == 'remoteuser') {
            $remotePrefix = 'R';
            $sampleCodeKeyCol = 'remote_sample_code_key';
            $sampleCodeCol = 'remote_sample_code';
        }

        $sampleColDateTimeArray = explode(" ", $sampleCollectionDate);
        $sampleCollectionDate = $general->dateFormat($sampleColDateTimeArray[0]);
        $sampleColDateArray = explode("-", $sampleCollectionDate);
        $samColDate = substr($sampleColDateArray[0], -2);
        $start_date = $sampleColDateArray[0] . '-01-01';
        $end_date = $sampleColDateArray[0] . '-12-31';
        $mnthYr = $samColDate[0];
        // Checking if sample code format is empty then we set by default 'MMYY'
        $sampleCodeFormat = isset($globalConfig['sample_code']) ? $globalConfig['sample_code'] : 'MMYY';
        $prefixFromConfig = isset($globalConfig['sample_code_prefix']) ? $globalConfig['sample_code_prefix'] : '';

        if ($sampleCodeFormat == 'MMYY') {
            $mnthYr = $sampleColDateArray[1] . $samColDate;
        } else if ($sampleCodeFormat == 'YY') {
            $mnthYr = $samColDate;
        }

        $autoFormatedString = $samColDate . $sampleColDateArray[1] . $sampleColDateArray[2];


        if ($maxCodeKeyVal == null) {
            // If it is PNG form
            if ($globalConfig['vl_form'] == 5) {

                if (empty($provinceId) && !empty($provinceCode)) {
                    $provinceId = $general->getProvinceIDFromCode($provinceCode);
                }

                if (!empty($provinceId)) {
                    $this->db->where('province_id', $provinceId);
                }
            }

            $this->db->where('DATE(sample_collection_date)', array($start_date, $end_date), 'BETWEEN');
            $this->db->where($sampleCodeCol, NULL, 'IS NOT');
            $this->db->orderBy($sampleCodeKeyCol, "DESC");
            $svlResult = $this->db->getOne($this->table, array($sampleCodeKeyCol));

            if ($svlResult) {
                $maxCodeKeyVal = $svlResult[$sampleCodeKeyCol];
            } else {
                $maxCodeKeyVal = null;
            }
        }


        if (!empty($maxCodeKeyVal)) {
            $maxId = $maxCodeKeyVal + 1;
            $strparam = strlen($maxId);
            $zeros = (isset($sampleCodeFormat) && trim($sampleCodeFormat) == 'auto2') ? substr("0000", $strparam) : substr("000", $strparam);
            $maxId = $zeros . $maxId;
        } else {
            $maxId = (isset($sampleCodeFormat) && trim($sampleCodeFormat) == 'auto2') ? '0001' : '001';
        }

        //error_log($maxCodeKeyVal);

        $sCodeKey = (array('maxId' => $maxId, 'mnthYr' => $mnthYr, 'auto' => $autoFormatedString));


        if ($globalConfig['vl_form'] == 5) {
            // PNG format has an additional R in prefix
            $remotePrefix = $remotePrefix . "R";
        }

        if ($sampleCodeFormat == 'auto') {
            //$pNameVal = explode("##", $provinceCode);
            $sCodeKey['sampleCode'] = ($remotePrefix . $provinceCode . $autoFormatedString . $sCodeKey['maxId']);
            $sCodeKey['sampleCodeInText'] = ($remotePrefix . $provinceCode . $autoFormatedString . $sCodeKey['maxId']);
            $sCodeKey['sampleCodeFormat'] = ($remotePrefix . $provinceCode . $autoFormatedString);
            $sCodeKey['sampleCodeKey'] = ($sCodeKey['maxId']);
        } else if ($sampleCodeFormat == 'auto2') {
            $sCodeKey['sampleCode'] = $remotePrefix . date('y', strtotime($sampleCollectionDate)) . $provinceCode . $this->shortCode . $sCodeKey['maxId'];
            $sCodeKey['sampleCodeInText'] = $remotePrefix . date('y', strtotime($sampleCollectionDate)) . $provinceCode . $this->shortCode . $sCodeKey['maxId'];
            $sCodeKey['sampleCodeFormat'] = $remotePrefix . $provinceCode . $autoFormatedString;
            $sCodeKey['sampleCodeKey'] = $sCodeKey['maxId'];
        } else if ($sampleCodeFormat == 'YY' || $sampleCodeFormat == 'MMYY') {
            $sCodeKey['sampleCode'] = $remotePrefix . $prefixFromConfig . $sCodeKey['mnthYr'] . $sCodeKey['maxId'];
            $sCodeKey['sampleCodeInText'] = $remotePrefix . $prefixFromConfig . $sCodeKey['mnthYr'] . $sCodeKey['maxId'];
            $sCodeKey['sampleCodeFormat'] = $remotePrefix . $prefixFromConfig . $sCodeKey['mnthYr'];
            $sCodeKey['sampleCodeKey'] = ($sCodeKey['maxId']);
        }

        $checkQuery = "SELECT $sampleCodeCol, $sampleCodeKeyCol FROM " . $this->table . " where $sampleCodeCol='" . $sCodeKey['sampleCode'] . "'";
        $checkResult = $this->db->rawQueryOne($checkQuery);
        if ($checkResult !== null) {
            return $this->generateVLSampleID($provinceCode, $sampleCollectionDate, $sampleFrom, $provinceId, $checkResult[$sampleCodeKeyCol]);
        }
        return json_encode($sCodeKey);
    }

    function vlResultCategory($result)
    {
        $res = NULL;
        if ($result >= 1000)
            $res = 'not suppressed';
        else if ($result < 1000)
            $res = 'suppressed';
        return $res;
    }

    public function getVlSampleTypesByName($name = "")
    {
        $where = "";
        if (!empty($name)) {
            $where = " AND sample_name LIKE '$name%'";
        }
        $query = "SELECT * FROM r_vl_sample_type where status='active'$where";
        return $this->db->rawQuery($query);
    }

    public function getVlSampleTypes()
    {
        $results = $this->db->rawQuery("SELECT * FROM r_vl_sample_type where status='active'");
        $response = array();
        foreach ($results as $row) {
            $response[$row['sample_id']] = $row['sample_name'];
        }
        return $response;
    }

    public function getVLResultCategory($resultStatus, $finalResult)
    {

        $vlResultCategory = null;
        if ($resultStatus == 4) {
            $vlResultCategory = 'Rejected';
        } else if ($resultStatus == 7) {
            if (is_numeric($finalResult) && $finalResult > 0 && $finalResult == round($finalResult, 0)) {
                $finalResult = (float)filter_var($finalResult, FILTER_SANITIZE_NUMBER_FLOAT);

                if ($finalResult < $this->suppressionLimit) {
                    $vlResultCategory = 'suppressed';
                } else if ($finalResult >= $this->suppressionLimit) {
                    $vlResultCategory = 'not suppressed';
                }
            } else {

                $textResult = NULL;

                if (in_array(strtolower($finalResult), $this->suppressedArray)) {
                    $textResult = 20;
                } else {
                    $textResult = (float)filter_var($finalResult, FILTER_SANITIZE_NUMBER_FLOAT);
                }

                if ($textResult < $this->suppressionLimit) {
                    $vlResultCategory = 'suppressed';
                } else if ($textResult >= $this->suppressionLimit) {
                    $vlResultCategory = 'not suppressed';
                }
            }
        }
        if (!empty($vlResultCategory)) {
            // $this->db->where('vl_sample_id', $sampleId);
            // return $this->db->update($this->table, $dataForUpdate);
            return $vlResultCategory;
        } else {
            return null;
        }
    }
}
