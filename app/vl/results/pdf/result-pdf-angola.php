<?php

// This file is included in /vl/results/generate-result-pdf.php


use App\Helpers\PdfConcatenateHelper;
use App\Helpers\PdfWatermarkHelper;
use App\Utilities\DateUtility;

class MYPDFANG extends TCPDF
{
     //Page header
     public function setHeading($logo, $text, $lab)
     {
          $this->logo = $logo;
          //$this->text = $text;
          //$this->lab = $lab;
     }
     public function imageExists($filePath)
     {
          return (!empty($filePath) && file_exists($filePath) && !is_dir($filePath) && filesize($filePath) > 0 && false !== getimagesize($filePath));
     }
     //Page header
     public function Header()
     {
          // Logo
          //$imageFilePath = K_PATH_IMAGES.'logo_example.jpg';
          //$this->Image($imageFilePath, 10, 10, 15, '', 'JPG', '', 'T', false, 300, '', false, false, 0, false, false, false);
          // Set font
          if (trim($this->logo) != '') {
               if (file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logo)) {
                    $imageFilePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logo;
                    $this->Image($imageFilePath, 95, 3, 15, '', '', '', 'T');
               }
          }
          //$this->SetFont('helvetica', 'B', 7);
          //$this->writeHTMLCell(30,0,16,28,$this->text, 0, 0, 0, true, 'A', true);
          $this->SetFont('helvetica', '', 7);
          $this->writeHTMLCell(0, 0, 10, 18, 'República de Angola', 0, 0, 0, true, 'C');
          $this->SetFont('helvetica', '', 7);
          $this->writeHTMLCell(0, 0, 10, 22, 'Ministério da Saúde', 0, 0, 0, true, 'C');
          $this->SetFont('helvetica', '', 7);
          $this->writeHTMLCell(0, 0, 10, 26, 'Instituto Nacional de Luta contra a SIDA', 0, 0, 0, true, 'C');
          $this->SetFont('helvetica', 'B', 8);
          $this->writeHTMLCell(0, 0, 10, 30, 'RELATÓRIO DE RESULTADOS DE QUANTIFICAÇÃO DE CARGA VIRAL DE VIH', 0, 0, 0, true, 'C');
          //if(trim($this->lab)!= ''){
          // $this->SetFont('helvetica', '', 9);
          //$this->writeHTMLCell(0,0,10,26,strtoupper($this->lab), 0, 0, 0, true, 'C', true);
          //}
          $this->writeHTMLCell(0, 0, 15, 36, '<hr>', 0, 0, 0, true, 'C');
     }

     // Page footer
     public function Footer()
     {
          // Position at 15 mm from bottom
          $this->SetY(-15);
          // Set font
          $this->SetFont('helvetica', '', 8);
          // Page number
          $this->Cell(0, 10, 'Page' . $_SESSION['aliasPage'] . '/' . $_SESSION['nbPages'], 0, false, 'C', 0);
     }
}

$resultFilename = '';
if (!empty($requestResult)) {
     $_SESSION['rVal'] = $general->generateRandomString(6);
     $pathFront = (TEMP_PATH . DIRECTORY_SEPARATOR .  $_SESSION['rVal']);
     if (!file_exists($pathFront) && !is_dir($pathFront)) {
          mkdir(TEMP_PATH . DIRECTORY_SEPARATOR . $_SESSION['rVal'], 0777, true);
          $pathFront = realpath(TEMP_PATH . DIRECTORY_SEPARATOR . $_SESSION['rVal']);
     }
     $pages = [];
     $page = 1;
     foreach ($requestResult as $result) {
          $_SESSION['aliasPage'] = $page;
          if (!isset($result['labName'])) {
               $result['labName'] = '';
          }
          $draftTextShow = false;
          //Set watermark text
          for ($m = 0; $m < count($mFieldArray); $m++) {
               if (!isset($result[$mFieldArray[$m]]) || trim($result[$mFieldArray[$m]]) == '' || $result[$mFieldArray[$m]] == null || $result[$mFieldArray[$m]] == '0000-00-00 00:00:00') {
                    $draftTextShow = true;
                    break;
               }
          }
          // create new PDF document
          $pdf = new MYPDFANG(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
          if (file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo" . DIRECTORY_SEPARATOR . $result['lab_id'] . DIRECTORY_SEPARATOR . $result['facilityLogo'])) {
               $logoPrintInPdf = $result['facilityLogo'];
          } else {
               $logoPrintInPdf = $arr['logo'];
          }
          $pdf->setHeading($logoPrintInPdf, $arr['header'], $result['labName']);
          // set document information
          $pdf->SetCreator(PDF_CREATOR);
          //$pdf->SetAuthor('Pal');
          $pdf->SetTitle('Viral Load Test Result');
          //$pdf->SetSubject('TCPDF Tutorial');
          //$pdf->SetKeywords('TCPDF, PDF, example, test, guide');

          // set default header data
          $pdf->SetHeaderData(PDF_HEADER_LOGO, PDF_HEADER_LOGO_WIDTH, PDF_HEADER_TITLE, PDF_HEADER_STRING);

          // set header and footer fonts
          $pdf->setHeaderFont(array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
          $pdf->setFooterFont(array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

          // set default monospaced font
          $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

          // set margins
          $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP + 14, PDF_MARGIN_RIGHT);
          $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
          $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

          // set auto page breaks
          $pdf->SetAutoPageBreak(true, PDF_MARGIN_BOTTOM);

          // set image scale factor
          $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);



          // set font
          $pdf->SetFont('helvetica', '', 18);

          $pdf->AddPage();
          if (!isset($result['facility_code']) || trim($result['facility_code']) == '') {
               $result['facility_code'] = '';
          }
          if (!isset($result['facility_state']) || trim($result['facility_state']) == '') {
               $result['facility_state'] = '';
          }
          if (!isset($result['facility_district']) || trim($result['facility_district']) == '') {
               $result['facility_district'] = '';
          }
          if (!isset($result['facility_name']) || trim($result['facility_name']) == '') {
               $result['facility_name'] = '';
          }
          if (!isset($result['labName']) || trim($result['labName']) == '') {
               $result['labName'] = '';
          }
          //Set Age
          $age = 'Unknown';
          if (isset($result['patient_dob']) && trim($result['patient_dob']) != '' && $result['patient_dob'] != '0000-00-00') {
               $todayDate = strtotime(date('Y-m-d'));
               $dob = strtotime($result['patient_dob']);
               $difference = $todayDate - $dob;
               $seconds_per_year = 60 * 60 * 24 * 365;
               $age = round($difference / $seconds_per_year);
          } elseif (isset($result['patient_age_in_years']) && trim($result['patient_age_in_years']) != '' && trim($result['patient_age_in_years']) > 0) {
               $age = $result['patient_age_in_years'];
          } elseif (isset($result['patient_age_in_months']) && trim($result['patient_age_in_months']) != '' && trim($result['patient_age_in_months']) > 0) {
               if ($result['patient_age_in_months'] > 1) {
                    $age = $result['patient_age_in_months'] . ' months';
               } else {
                    $age = $result['patient_age_in_months'] . ' month';
               }
          }

          if (isset($result['sample_collection_date']) && trim($result['sample_collection_date']) != '' && $result['sample_collection_date'] != '0000-00-00 00:00:00') {
               $expStr = explode(" ", $result['sample_collection_date']);
               $result['sample_collection_date'] = DateUtility::humanReadableDateFormat($expStr[0]);
               $sampleCollectionTime = $expStr[1];
          } else {
               $result['sample_collection_date'] = '';
               $sampleCollectionTime = '';
          }
          $sampleReceivedDate = '';
          $sampleReceivedTime = '';
          if (isset($result['sample_received_at_vl_lab_datetime']) && trim($result['sample_received_at_vl_lab_datetime']) != '' && $result['sample_received_at_vl_lab_datetime'] != '0000-00-00 00:00:00') {
               $expStr = explode(" ", $result['sample_received_at_vl_lab_datetime']);
               $sampleReceivedDate = DateUtility::humanReadableDateFormat($expStr[0]);
               $sampleReceivedTime = $expStr[1];
          }

          if (isset($result['sample_tested_datetime']) && trim($result['sample_tested_datetime']) != '' && $result['sample_tested_datetime'] != '0000-00-00 00:00:00') {
               $expStr = explode(" ", $result['sample_tested_datetime']);
               $result['sample_tested_datetime'] = DateUtility::humanReadableDateFormat($expStr[0]) . " " . $expStr[1];
          } else {
               $result['sample_tested_datetime'] = '';
          }

          if (isset($result['last_viral_load_date']) && trim($result['last_viral_load_date']) != '' && $result['last_viral_load_date'] != '0000-00-00') {
               $result['last_viral_load_date'] = DateUtility::humanReadableDateFormat($result['last_viral_load_date']);
          } else {
               $result['last_viral_load_date'] = '';
          }
          if (!isset($result['patient_gender']) || trim($result['patient_gender']) == '') {
               $result['patient_gender'] = 'not reported';
          }
          if (isset($result['approvedBy']) && trim($result['approvedBy']) != '') {
               $resultApprovedBy = ($result['approvedBy']);
          } else {
               $resultApprovedBy  = '';
          }

          $smileyContent = '';
          $showMessage = '';
          $tndMessage = '';
          $messageTextSize = '15px';


          if (!empty($result['vl_result_category']) && $result['vl_result_category'] == 'suppressed') {
               $smileyContent = '<img src="/assets/img/smiley_smile.png" style="width:50px;" alt="smile_face"/>';
               $showMessage = ($arr['l_vl_msg']);
          } elseif (!empty($result['vl_result_category']) && $result['vl_result_category'] == 'not suppressed') {
               $smileyContent = '<img src="/assets/img/smiley_frown.png" style="width:50px;" alt="frown_face"/>';
               $showMessage = ($arr['h_vl_msg']);
          } elseif ($result['result_status'] == '4' || $result['is_sample_rejected'] == 'yes') {
               $smileyContent = '<img src="/assets/img/cross.png" style="width:50px;" alt="rejected"/>';
          }

          if (isset($arr['show_smiley']) && trim($arr['show_smiley']) == "no") {
               $smileyContent = '';
          } else {
               $smileyContent = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' . $smileyContent;
          }
          $html = '<table style="padding:0px 2px 2px 2px;">';
          $html .= '<tr>';
          $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">Nº da amostra</td>';
          $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">Data da colheita de amostra</td>';
          $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">Nº Processo Clínico</td>';
          $html .= '</tr>';
          $html .= '<tr>';
          $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $result['sample_code'] . '</td>';
          $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $result['sample_collection_date'] . " " . $sampleCollectionTime . '</td>';
          $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $result['patient_art_no'] . '</td>';
          $html .= '</tr>';
          $html .= '<tr>';
          $html .= '<td colspan="3" style="line-height:10px;"></td>';
          $html .= '</tr>';
          if ($arr['patient_name_pdf'] == 'fullname') {
               $html .= '<tr>';
               $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">Nome completo</td>';
               $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">Contacto</td>';
               $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">Idade (em meses se &lt;1ano)</td>';
               $html .= '</tr>';
               $html .= '<tr>';

               $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . ($result['patient_first_name'] . " " . $result['patient_last_name']) . '</td>';
               $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $result['patient_mobile_number'] . '</td>';
               $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $age . '</td>';
               $html .= '</tr>';
               $html .= '<tr>';
               $html .= '<td colspan="3" style="line-height:10px;"></td>';
               $html .= '</tr>';
               $html .= '<tr>';
               $html .= '<td colspan="3" style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">Género</td>';
               $html .= '</tr>';
               $html .= '<tr>';
               $html .= '<td colspan="3" style="line-height:11px;font-size:11px;text-align:left;">' . (str_replace("_", " ", $result['patient_gender'])) . '</td>';
               $html .= '</tr>';
          } else if ($arr['patient_name_pdf'] == 'hidename') {
               $html .= '<tr>';
               $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">MOBILE NO.</td>';
               $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">AGE</td>';
               $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">Género</td>';
               $html .= '</tr>';
               $html .= '<tr>';
               $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $result['patient_mobile_number'] . '</td>';
               $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $age . '</td>';
               $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . (str_replace("_", " ", $result['patient_gender'])) . '</td>';
               $html .= '</tr>';
               $html .= '<tr>';
               $html .= '<td colspan="3" style="line-height:10px;"></td>';
               $html .= '</tr>';
          } else {
               $html .= '<tr>';
               $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">PATIENT FIRST NAME</td>';
               $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">MOBILE NO.</td>';
               $html .= '</tr>';
               $html .= '<tr>';

               $patientFname = ($general->crypto('doNothing', $result['patient_first_name'], $result['patient_art_no']));

               $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $patientFname . '</td>';
               $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $result['patient_mobile_number'] . '</td>';
               $html .= '</tr>';
               $html .= '<tr>';
               $html .= '<td colspan="3" style="line-height:10px;"></td>';
               $html .= '</tr>';
               $html .= '<tr>';
               $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">AGE</td>';
               $html .= '<td colspan="2" style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">GENDER</td>';
               $html .= '</tr>';
               $html .= '<tr>';
               $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $age . '</td>';
               $html .= '<td colspan="2" style="line-height:11px;font-size:11px;text-align:left;">' . (str_replace("_", " ", $result['patient_gender'])) . '</td>';
               $html .= '</tr>';
          }
          $html .= '<tr>';
          $html .= '<td colspan="3" style="line-height:10px;"></td>';
          $html .= '</tr>';
          $html .= '<tr>';
          $html .= '<td colspan="3" style="line-height:2px;border-bottom:2px solid #d3d3d3;"></td>';
          $html .= '</tr>';
          $html .= '<tr>';
          $html .= '<td colspan="3" style="line-height:10px;"></td>';
          $html .= '</tr>';
          $html .= '<tr>';
          $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">Unidade de Saúde</td>';
          $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">Província</td>';
          $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">Município</td>';
          $html .= '</tr>';
          $html .= '<tr>';
          $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $result['facility_code'] . '</td>';
          $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . ($result['facility_state']) . '</td>';
          $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . ($result['facility_district']) . '</td>';
          $html .= '</tr>';
          $html .= '<tr>';
          $html .= '<td colspan="3" style="line-height:10px;"></td>';
          $html .= '</tr>';
          $html .= '<tr>';
          $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">Unidade de Saúde</td>';
          $html .= '<td colspan="2" style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">Responsável pela colheita</td>';
          $html .= '</tr>';
          $html .= '<tr>';
          $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . ($result['facility_name']) . '</td>';
          $html .= '<td colspan="2" style="line-height:11px;font-size:11px;text-align:left;">' . ($result['request_clinician_name']) . '</td>';
          $html .= '</tr>';
          $html .= '<tr>';
          $html .= '<td colspan="3" style="line-height:10px;"></td>';
          $html .= '</tr>';
          $html .= '<tr>';
          $html .= '<td colspan="3" style="line-height:2px;border-bottom:2px solid #d3d3d3;"></td>';
          $html .= '</tr>';
          $html .= '<tr>';
          $html .= '<td colspan="3" style="line-height:10px;"></td>';
          $html .= '</tr>';
          $html .= '<tr>';
          $html .= '<td colspan="3">';
          $html .= '<table style="padding:2px;">';
          $html .= '<tr>';
          $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">Data de Recepção de Amostras</td>';
          $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">Data da Quantificação</td>';
          $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">Tipo de amostra</td>';
          $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">Plataforma usada</td>';
          $html .= '</tr>';
          $html .= '<tr>';
          $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $sampleReceivedDate . " " . $sampleReceivedTime . '</td>';
          $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $result['sample_tested_datetime'] . '</td>';
          $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . ($result['sample_name']) . '</td>';
          $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . ($result['vl_test_platform']) . '</td>';
          $html .= '</tr>';
          $html .= '<tr>';
          $html .= '<td colspan="4" style="line-height:16px;"></td>';
          $html .= '</tr>';
          $html .= '<tr>';
          $html .= '<td colspan="3"></td>';
          $html .= '<td rowspan="3" style="text-align:left;">' . $smileyContent . '</td>';
          $html .= '</tr>';
          $html .= '<tr><td colspan="3" style="line-height:26px;font-size:12px;font-weight:bold;text-align:left;background-color:#dbdbdb;">&nbsp;&nbsp;Resultado (copies/ml)&nbsp;&nbsp;&nbsp;&nbsp;:&nbsp;&nbsp;&nbsp;&nbsp;' . htmlspecialchars($result['result']) . '</td></tr>';
          $html .= '<tr><td colspan="3"></td></tr>';
          $html .= '</table>';
          $html .= '</td>';
          $html .= '</tr>';
          if (trim($showMessage) != '') {
               $html .= '<tr>';
               $html .= '<td colspan="3" style="line-height:13px;font-size:' . $messageTextSize . ';text-align:left;">' . $showMessage . '</td>';
               $html .= '</tr>';
               $html .= '<tr>';
               $html .= '<td colspan="3" style="line-height:16px;"></td>';
               $html .= '</tr>';
          }
          if (trim($tndMessage) != '') {
               $html .= '<tr>';
               $html .= '<td colspan="3" style="line-height:13px;font-size:18px;text-align:left;">' . $tndMessage . '</td>';
               $html .= '</tr>';
               $html .= '<tr>';
               $html .= '<td colspan="3" style="line-height:16px;"></td>';
               $html .= '</tr>';
          }
          // $html .='<tr>';
          // $html .='<td colspan="3" style="line-height:11px;font-size:11px;font-weight:bold;">APPROVED BY&nbsp;&nbsp;:&nbsp;&nbsp;<span style="font-weight:normal;">'.$resultApprovedBy.'</span></td>';
          // $html .='</tr>';
          $html .= '<tr>';
          $html .= '<td colspan="3" style="line-height:10px;"></td>';
          $html .= '</tr>';
          if (trim($result['lab_tech_comments']) != '') {
               $html .= '<tr>';
               $html .= '<td colspan="3" style="line-height:11px;font-size:11px;font-weight:bold;">LAB COMMENTS&nbsp;&nbsp;:&nbsp;&nbsp;<span style="font-weight:normal;">' . ($result['lab_tech_comments']) . '</span></td>';
               $html .= '</tr>';
               $html .= '<tr>';
               $html .= '<td colspan="3" style="line-height:10px;"></td>';
               $html .= '</tr>';
          }
          $html .= '<tr>';
          $html .= '<td colspan="3" style="line-height:2px;border-bottom:2px solid #d3d3d3;"></td>';
          $html .= '</tr>';
          $html .= '<tr>';
          $html .= '<td colspan="3" style="line-height:14px;"></td>';
          $html .= '</tr>';
          // $html .='<tr>';
          // $html .='<td colspan="3" style="line-height:11px;font-size:11px;font-weight:bold;">PREVIOUS RESULTS</td>';
          // $html .='</tr>';
          // $html .='<tr>';
          // $html .='<td colspan="3" style="line-height:8px;"></td>';
          // $html .='</tr>';
          // $html .='<tr>';
          // $html .='<td colspan="3" style="line-height:11px;font-size:11px;font-weight:bold;">Date of Last VL Test&nbsp;&nbsp;:&nbsp;&nbsp;<span style="font-weight:normal;">'.$result['last_viral_load_date'].'</span></td>';
          // $html .='</tr>';
          // $html .='<tr>';
          // $html .='<td colspan="3" style="line-height:11px;font-size:11px;font-weight:bold;">Result of previous viral load(copies/ml)&nbsp;&nbsp;:&nbsp;&nbsp;<span style="font-weight:normal;">'.$result['last_viral_load_result'].'</span></td>';
          // $html .='</tr>';
          $html .= '<tr>';
          $html .= '<td colspan="3" style="line-height:110px;border-bottom:2px solid #d3d3d3;"></td>';
          $html .= '</tr>';
          $html .= '<tr>';
          $html .= '<td colspan="3" style="line-height:2px;"></td>';
          $html .= '</tr>';
          $html .= '<tr>';
          $html .= '<td colspan="3">';
          $html .= '<table>';
          $html .= '<tr>';
          $html .= '<td style="font-size:10px;text-align:left;width:60%;"><img src="/assets/img/smiley_smile.png" alt="smile_face" style="width:10px;height:10px;"/> = VL < = 1000 copies/ml: Continue on current regimen</td>';
          $html .= '<td style="font-size:10px;text-align:left;">Printed on : ' . $printDate . '&nbsp;&nbsp;' . '</td>';
          $html .= '</tr>';
          $html .= '<tr>';
          $html .= '<td colspan="2" style="font-size:10px;text-align:left;width:60%;"><img src="/assets/img/smiley_frown.png" alt="frown_face" style="width:10px;height:10px;"/> = VL > 1000 copies/ml: copies/ml: Clinical and counselling action required</td>';
          $html .= '</tr>';
          $html .= '</table>';
          $html .= '</td>';
          $html .= '</tr>';
          $html .= '</table>';
          if ($result['result'] != '') {
               $pdf->writeHTML($html);
               $pdf->lastPage();
               $filename = $pathFront . DIRECTORY_SEPARATOR . 'p' . $page . '.pdf';
               $pdf->Output($filename, "F");
               if ($draftTextShow) {
                    //Watermark section
                    $watermark = new PdfWatermarkHelper();
                    $watermark->setFullPathToFile($filename);
                    $fullPathToFile = $filename;
                    $watermark->Output($filename, "F");
               }
               $pages[] = $filename;
               $page++;
          }
          if (isset($_POST['source']) && trim($_POST['source']) == 'print') {
               //Add event log
               $eventType = 'print-result';
               $action = $_SESSION['userName'] . ' printed the test result with Patient ID/Code ' . $result['patient_art_no'];
               $resource = 'print-test-result';
               $data = array(
                    'event_type' => $eventType,
                    'action' => $action,
                    'resource' => $resource,
                    'date_time' => DateUtility::getCurrentDateTime()
               );
               $db->insert($tableName1, $data);
               //Update print datetime in VL tbl.
               $vlQuery = "SELECT result_printed_datetime FROM form_vl as vl WHERE vl.vl_sample_id ='" . $result['vl_sample_id'] . "'";
               $vlResult = $db->query($vlQuery);
               if ($vlResult[0]['result_printed_datetime'] == null || trim($vlResult[0]['result_printed_datetime']) == '' || $vlResult[0]['result_printed_datetime'] == '0000-00-00 00:00:00') {
                    $db = $db->where('vl_sample_id', $result['vl_sample_id']);
                    $db->update($tableName2, array('result_printed_datetime' => DateUtility::getCurrentDateTime()));
               }
          }
     }

     if (!empty($pages)) {
          $resultPdf = new PdfConcatenateHelper();
          $resultPdf->setFiles($pages);
          $resultPdf->setPrintHeader(false);
          $resultPdf->setPrintFooter(false);
          $resultPdf->concat();
          $resultFilename = 'VLSM-VL-Test-result-' . date('d-M-Y-H-i-s') . "-" . $general->generateRandomString(6) . '.pdf';
          $resultPdf->Output(TEMP_PATH . DIRECTORY_SEPARATOR . $resultFilename, "F");
          $general->removeDirectory($pathFront);
          unset($_SESSION['rVal']);
     }
}

echo base64_encode(TEMP_PATH . DIRECTORY_SEPARATOR . $resultFilename);
