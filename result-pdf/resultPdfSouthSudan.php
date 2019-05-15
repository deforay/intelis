<?php
$resultFilename = '';

$userRes = $users->getUserInfo($_SESSION['userId'],'user_signature');
$userSignaturePath = null;

if(!empty($userRes['user_signature'])){
  $userSignaturePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . "users-signature" . DIRECTORY_SEPARATOR . $userRes['user_signature'];
}


if(sizeof($requestResult)> 0){
     $_SESSION['rVal'] = $general->generateRandomString(6);
     $pathFront = (UPLOAD_PATH . DIRECTORY_SEPARATOR .  $_SESSION['rVal']);
     if (!file_exists($pathFront) && !is_dir($pathFront)) {
       mkdir(UPLOAD_PATH . DIRECTORY_SEPARATOR . $_SESSION['rVal']);
       $pathFront = realpath(UPLOAD_PATH . DIRECTORY_SEPARATOR . $_SESSION['rVal']);
     }
     $pages = array();
     $page = 1;
     foreach($requestResult as $result){
          $_SESSION['aliasPage'] = $page;
          if(!isset($result['labName'])){
               $result['labName'] = '';
          }
          $draftTextShow = false;
          //Set watermark text
          for($m=0;$m<count($mFieldArray);$m++){
               if(!isset($result[$mFieldArray[$m]]) || trim($result[$mFieldArray[$m]]) == '' || $result[$mFieldArray[$m]] == null || $result[$mFieldArray[$m]] == '0000-00-00 00:00:00'){
                    $draftTextShow = true;
                    break;
               }
          }
          // create new PDF document
          $pdf = new MYPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT,true, 'UTF-8', false);
          $pdf->setHeading($arr['logo'],$arr['header'],$result['labName'],$title='VIRAL LOAD PATIENT REPORT');
          // set document information
          $pdf->SetCreator('VLSM');
          $pdf->SetTitle('Viral Load Patient Report');
          //$pdf->SetSubject('TCPDF Tutorial');
          //$pdf->SetKeywords('TCPDF, PDF, example, test, guide');

          // set default header data
          $pdf->SetHeaderData(PDF_HEADER_LOGO, PDF_HEADER_LOGO_WIDTH,PDF_HEADER_TITLE, PDF_HEADER_STRING);

          // set header and footer fonts
          $pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '',PDF_FONT_SIZE_MAIN));
          $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '',PDF_FONT_SIZE_DATA));

          // set default monospaced font
          $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

          // set margins
          $pdf->SetMargins(PDF_MARGIN_LEFT,PDF_MARGIN_TOP+14,PDF_MARGIN_RIGHT);
          $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
          $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

          // set auto page breaks
          $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

          // set image scale factor
          $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

          // set some language-dependent strings (optional)
          if (@file_exists(dirname(__FILE__).'/lang/eng.php')) {
               require_once(dirname(__FILE__).'/lang/eng.php');
               $pdf->setLanguageArray($l);
          }

          // ---------------------------------------------------------

          // set font
          $pdf->SetFont('helvetica', '', 18);

          $pdf->AddPage();
          if(!isset($result['facility_code']) || trim($result['facility_code']) == ''){
               $result['facility_code'] = '';
          }
          if(!isset($result['facility_state']) || trim($result['facility_state']) == ''){
               $result['facility_state'] = '';
          }
          if(!isset($result['facility_district']) || trim($result['facility_district']) == ''){
               $result['facility_district'] = '';
          }
          if(!isset($result['facility_name']) || trim($result['facility_name']) == ''){
               $result['facility_name'] = '';
          }
          if(!isset($result['labName']) || trim($result['labName']) == ''){
               $result['labName'] = '';
          }
          //Set Age
          $age = 'Unknown';
          if(isset($result['patient_dob']) && trim($result['patient_dob'])!='' && $result['patient_dob']!='0000-00-00'){
               $todayDate = strtotime(date('Y-m-d'));
               $dob = strtotime($result['patient_dob']);
               $difference = $todayDate - $dob;
               $seconds_per_year = 60*60*24*365;
               $age = round($difference / $seconds_per_year);
          }elseif(isset($result['patient_age_in_years']) && trim($result['patient_age_in_years'])!='' && trim($result['patient_age_in_years']) >0){
               $age = $result['patient_age_in_years'];
          }elseif(isset($result['patient_age_in_months']) && trim($result['patient_age_in_months'])!='' && trim($result['patient_age_in_months']) >0){
               if($result['patient_age_in_months'] > 1){
                    $age = $result['patient_age_in_months'].' months';
               }else{
                    $age = $result['patient_age_in_months'].' month';
               }
          }

          if(isset($result['sample_collection_date']) && trim($result['sample_collection_date'])!='' && $result['sample_collection_date']!='0000-00-00 00:00:00'){
               $expStr=explode(" ",$result['sample_collection_date']);
               $result['sample_collection_date']=$general->humanDateFormat($expStr[0]);
               $sampleCollectionTime = $expStr[1];
          }else{
               $result['sample_collection_date']='';
               $sampleCollectionTime = '';
          }
          $sampleReceivedDate='';
          $sampleReceivedTime='';
          if(isset($result['sample_received_at_vl_lab_datetime']) && trim($result['sample_received_at_vl_lab_datetime'])!='' && $result['sample_received_at_vl_lab_datetime']!='0000-00-00 00:00:00'){
               $expStr=explode(" ",$result['sample_received_at_vl_lab_datetime']);
               $sampleReceivedDate=$general->humanDateFormat($expStr[0]);
               $sampleReceivedTime =$expStr[1];
          }
          $sampleDisbatchDate='';
          $sampleDisbatchTime='';
          if(isset($result['result_dispatched_datetime']) && trim($result['result_dispatched_datetime'])!='' && $result['result_dispatched_datetime']!='0000-00-00 00:00:00'){
               $expStr=explode(" ",$result['result_dispatched_datetime']);
               $sampleDisbatchDate=$general->humanDateFormat($expStr[0]);
               $sampleDisbatchTime =$expStr[1];
          }

          if(isset($result['sample_tested_datetime']) && trim($result['sample_tested_datetime'])!='' && $result['sample_tested_datetime']!='0000-00-00 00:00:00'){
               $expStr=explode(" ",$result['sample_tested_datetime']);
               $result['sample_tested_datetime']=$general->humanDateFormat($expStr[0])." ".$expStr[1];
          }else{
               $result['sample_tested_datetime']='';
          }

          if(isset($result['last_viral_load_date']) && trim($result['last_viral_load_date'])!='' && $result['last_viral_load_date']!='0000-00-00'){
               $result['last_viral_load_date']=$general->humanDateFormat($result['last_viral_load_date']);
          }else{
               $result['last_viral_load_date']='';
          }
          if(!isset($result['patient_gender']) || trim($result['patient_gender'])== ''){
               $result['patient_gender'] = 'not reported';
          }
          if(isset($result['approvedBy']) && trim($result['approvedBy'])!=''){
               $resultApprovedBy = ucwords($result['approvedBy']);
          }else{
               $resultApprovedBy  = '';
          }
          $vlResult = '';
          $smileyContent = '';
          $showMessage = '';
          $tndMessage = '';
          $messageTextSize = '12px';
          if($result['result']!= NULL && trim($result['result'])!= '') {
               $resultType = is_numeric($result['result']);
               if(in_array(strtolower(trim($result['result'])),array("below detection level",'bdl','BDL'))){
                    $vlResult = $result['result'];
                    $smileyContent = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<img src="/assets/img/smiley_smile.png" alt="smile_face"/>';
                    $showMessage = ucfirst($arr['l_vl_msg']);
               }else if(in_array(strtolower(trim($result['result'])),array("tnd","target not detected",'ldl','LDL'))){
                    if(trim($result['result'])=='ldl' || trim($result['result'])=='LDL'){
                         $vlResult = 'LDL';
                    }else{
                         $vlResult = 'TND*';
                         $tndMessage = 'TND* - Target not Detected';
                    }
                    $smileyContent = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<img src="/assets/img/smiley_smile.png" alt="smile_face"/>';
                    $showMessage = ucfirst($arr['l_vl_msg']);
               }else if(in_array(strtolower(trim($result['result'])),array("failed","fail","no_sample"))){
                    $vlResult = $result['result'];
                    $smileyContent = '';
                    $showMessage = '';
                    $messageTextSize = '14px';
               }else if(trim($result['result']) > 1000 && $result['result']<=10000000){
                    $vlResult = $result['result'];
                    $smileyContent = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<img src="/assets/img/smiley_frown.png" alt="frown_face"/>';
                    $showMessage = ucfirst($arr['h_vl_msg']);
                    $messageTextSize = '15px';
               }else if(trim($result['result']) <= 1000 && $result['result']>=20){
                    $vlResult = $result['result'];
                    $smileyContent = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<img src="/assets/img/smiley_smile.png" alt="smile_face"/>';
                    $showMessage = ucfirst($arr['l_vl_msg']);
               }else if(trim($result['result'] > 10000000) && $resultType){
                    $vlResult = $result['result'];
                    $smileyContent = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<img src="/assets/img/smiley_frown.png" alt="frown_face"/>';
                    //$showMessage = 'Value outside machine detection limit';
               }else if(trim($result['result'] < 20) && $resultType){
                    $vlResult = $result['result'];
                    $smileyContent = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<img src="/assets/img/smiley_smile.png" alt="smile_face"/>';
                    //$showMessage = 'Value outside machine detection limit';
               }else if(trim($result['result'])=='<20'){
                    $vlResult = '&lt;20';
                    $smileyContent = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<img src="/assets/img/smiley_smile.png" alt="smile_face"/>';
                    $showMessage = ucfirst($arr['l_vl_msg']);
               }else if(trim($result['result'])=='>10000000'){
                    $vlResult = $result['result'];
                    $smileyContent = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<img src="/assets/img/smiley_frown.png" alt="frown_face"/>';
                    $showMessage = ucfirst($arr['h_vl_msg']);
               }else if($result['vl_test_platform']=='Roche'){
                    $chkSign = '';
                    $smileyShow = '';
                    $chkSign = strchr($result['result'],'>');
                    if($chkSign!=''){
                         $smileyShow =str_replace(">","",$result['result']);
                         $vlResult = $result['result'];
                         //$showMessage = 'Invalid value';
                    }
                    $chkSign = '';
                    $chkSign = strchr($result['result'],'<');
                    if($chkSign!=''){
                         $smileyShow =str_replace("<","",$result['result']);
                         $vlResult = str_replace("<","&lt;",$result['result']);
                         //$showMessage = 'Invalid value';
                    }
                    if($smileyShow!='' && $smileyShow <= $arr['viral_load_threshold_limit']){
                         $smileyContent = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<img src="/assets/img/smiley_smile.png" alt="smile_face"/>';
                    }else if($smileyShow!='' && $smileyShow > $arr['viral_load_threshold_limit']){
                         $smileyContent = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<img src="/assets/img/smiley_frown.png" alt="frown_face"/>';
                    }
               }
          }
          if(isset($arr['show_smiley']) && trim($arr['show_smiley']) == "no"){
               $smileyContent = '';
          }
          if($result['result_status']=='4'){
               $smileyContent = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<img src="/assets/img/cross.png" alt="rejected"/>';
          }
          $html = '';
          $html.='<table style="padding:0px 2px 2px 2px;">';
          $html .='<tr>';

          $html .='<td colspan="3">';
          $html .='<table style="padding:2px;">';
          $html .='<tr>';
          $html .='<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">REQUESTING HEALTH CENTER NAME</td>';
          $html .='<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">HEALTH FACILITY CODE</td>';
          $html .='<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">Province/State</td>';
          $html .='<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">District/County</td>';
          $html .='</tr>';
          $html .='<tr>';
          $html .='<td style="line-height:11px;font-size:11px;text-align:left;">'.ucwords($result['facility_name']).'</td>';
          $html .='<td style="line-height:11px;font-size:11px;text-align:left;">'.ucwords($result['facility_code']).'</td>';
          $html .='<td style="line-height:11px;font-size:11px;text-align:left;">'.ucwords($result['facility_state']).'</td>';
          $html .='<td style="line-height:11px;font-size:11px;text-align:left;">'.ucwords($result['facility_district']).'</td>';
          $html .='</tr>';
          $html .='</table>';
          $html .='</td>';
          $html .='</tr>';
          $html .='<tr>';
          $html .='<td colspan="4" style="line-height:10px;border-bottom:1px thin #eee;"></td>';
          $html .='</tr>';

          $html .='<tr>';

          $html .='<td colspan="3">';
          $html .='<table style="padding:8px 2px 2px 2px;">';
          $html .='<tr>';
          $html .='<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">PATIENT NAME</td>';
          $html .='<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">ART (TRACNET) NO.</td>';
          $html .='<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">REASON FOR VL TESTING</td>';
          $html .='<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;"></td>';
          $html .='</tr>';
          $html .='<tr>';

          $patientFname = ucwords($general->crypto('decrypt',$result['patient_first_name'],$result['patient_art_no']));


          $html .='<td style="line-height:11px;font-size:11px;text-align:left;">'.$patientFname.'</td>';

          $html .='<td style="line-height:11px;font-size:11px;text-align:left;">'.$result['patient_art_no'].'</td>';
          $html .='<td style="line-height:11px;font-size:11px;text-align:left;">'.ucwords($result['test_reason_name']).'</td>';
          $html .='<td style="line-height:11px;font-size:11px;text-align:left;"></td>';
          $html .='</tr>';
          $html .='</table>';
          $html .='</td>';
          $html .='</tr>';

          $html .='<tr>';
          $html .='<td colspan="3" style="line-height:10px;border-bottom:1px thin #eee;"></td>';
          $html .='</tr>';

          $html .='<tr>';
          $html .='<td colspan="3">';
          $html .='<table style="padding:8px 2px 2px 2px;">';
          $html .='<tr>';
          $html .='<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">AGE</td>';
          $html .='<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">GENDER</td>';
          if($result['patient_gender']=='female'){
               $html .='<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">BREAST FEEDING</td>';
               $html .='<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">PREGNANCY STATUS</td>';
          }
          $html .='</tr>';
          $html .='<tr>';
          $html .='<td style="line-height:11px;font-size:11px;text-align:left;">'.$age.'</td>';
          $html .='<td style="line-height:11px;font-size:11px;text-align:left;">'.ucwords(str_replace("_"," ",$result['patient_gender'])).'</td>';
          if($result['patient_gender']=='female'){
               $html .='<td style="line-height:11px;font-size:11px;text-align:left;">'.ucwords(str_replace("_"," ",$result['is_patient_breastfeeding'])).'</td>';
               $html .='<td style="line-height:11px;font-size:11px;text-align:left;">'.ucwords(str_replace("_"," ",$result['is_patient_pregnant'])).'</td>';
          }
          $html .='</tr>';
          $html .='</table>';
          $html .='</td>';
          $html .='</tr>';

          $html .='<tr>';
          $html .='<td colspan="3" style="line-height:10px;border-bottom:1px thin #eee;"></td>';
          $html .='</tr>';
          $html .='<tr>';
          $html .='<td colspan="3" style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;"><br/><br/>REQUESTING CLINICIAN NAME</td>';
          $html .='</tr>';
          $html .='<tr>';
          $html .='<td colspan="3" style="line-height:11px;font-size:11px;text-align:left;">'.ucwords($result['request_clinician_name']).'</td>';
          $html .='</tr>';

          $html .='<tr>';
          $html .='<td colspan="3" style="line-height:2px;border-bottom:2px solid #d3d3d3;"></td>';
          $html .='</tr>';
          $html .='<tr>';
          $html .='<td colspan="3" style="line-height:10px;"></td>';
          $html .='</tr>';
          $html .='<tr>';
          $html .='<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">SAMPLE ID</td>';
          $html .='<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">SAMPLE COLLECTION DATE</td>';
          $html .='<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">SAMPLE RECEIPT DATE</td>';
          $html .='</tr>';
          $html .='<tr>';
          $html .='<td style="line-height:11px;font-size:11px;text-align:left;">'.$result['sample_code'].'</td>';
          $html .='<td style="line-height:11px;font-size:11px;text-align:left;">'.$result['sample_collection_date']." ".$sampleCollectionTime.'</td>';
          $html .='<td style="line-height:11px;font-size:11px;text-align:left;">'.$sampleReceivedDate." ".$sampleReceivedTime.'</td>';
          $html .='</tr>';
          $html .='<tr>';
          $html .='<td colspan="3" style="line-height:10px;"></td>';
          $html .='</tr>';
          $html .='<tr>';
          $html .='<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">SAMPLE TEST DATE</td>';
          $html .='<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">RESULT RELEASE DATE</td>';
          $html .='<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">SAMPLE TYPE</td>';
          $html .='</tr>';
          $html .='<tr>';
          $html .='<td style="line-height:11px;font-size:11px;text-align:left;">'.$result['sample_tested_datetime'].'</td>';
          $html .='<td style="line-height:11px;font-size:11px;text-align:left;">'.$sampleDisbatchDate." ".$sampleDisbatchTime.'</td>';
          $html .='<td style="line-height:11px;font-size:11px;text-align:left;">'.ucwords($result['sample_name']).'</td>';
          $html .='</tr>';
          $html .='<tr>';
          $html .='<td colspan="3" style="line-height:10px;"></td>';
          $html .='</tr>';

          $html .='<tr>';
          $html .='<td colspan="3">';
          $html .='<table style="padding:12px 2px 2px 2px;">';
          $logValue = '';
          if($result['result_value_log']!='' && $result['result_value_log']!=null && ($result['reason_for_sample_rejection']=='' || $result['reason_for_sample_rejection']==null)){
               $logValue = '<br/>&nbsp;&nbsp;Log Value&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;:&nbsp;&nbsp;&nbsp;&nbsp;'.$result['result_value_log'];
          }else{
               $logValue = '<br/>&nbsp;&nbsp;Log Value&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;:&nbsp;&nbsp;&nbsp;&nbsp;0.0';
          }
          $html .='<tr style="background-color:#dbdbdb;"><td colspan="2" style="line-height:26px;font-size:12px;font-weight:bold;">&nbsp;&nbsp;Viral Load Result (copies/ml)&nbsp;&nbsp;&nbsp;&nbsp;:&nbsp;&nbsp;&nbsp;&nbsp;'.$result['result'].$logValue.'</td><td style="">'.$smileyContent.'</td></tr>';
          if($result['reason_for_sample_rejection']!=''){
               $html .='<tr><td colspan="3" style="line-height:26px;font-size:12px;font-weight:bold;text-align:left;">&nbsp;&nbsp;Rejection Reason&nbsp;&nbsp;&nbsp;&nbsp;:&nbsp;&nbsp;&nbsp;&nbsp;'.$result['rejection_reason_name'].'</td></tr>';
          }
          if(strtolower($result['vl_test_platform']) == 'abbott'){
            $html .='<tr>';
            $html .='<td colspan="3" style="line-height:8px;font-size:10px;padding-top:10px;">Abbott Linear Detection range: 839 copies/ml - 10 million copies/ml</td>';
            $html .='</tr>';
          }          
          $html .='<tr><td colspan="3"></td></tr>';
          $html .='</table>';
          $html .='</td>';
          $html .='</tr>';
          if(trim($showMessage)!= ''){
               $html .='<tr>';
               $html .='<td colspan="3" style="line-height:13px;font-size:'.$messageTextSize.';text-align:left;">'.$showMessage.'</td>';
               $html .='</tr>';
               $html .='<tr>';
               $html .='<td colspan="3" style="line-height:16px;"></td>';
               $html .='</tr>';
          }
          if(trim($tndMessage)!= ''){
               $html .='<tr>';
               $html .='<td colspan="3" style="line-height:13px;font-size:18px;text-align:left;">'.$tndMessage.'</td>';
               $html .='</tr>';
               $html .='<tr>';
               $html .='<td colspan="3" style="line-height:16px;"></td>';
               $html .='</tr>';
          }
          if(trim($result['approver_comments'])!= ''){
               $html .='<tr>';
               $html .='<td colspan="3" style="line-height:11px;font-size:11px;font-weight:bold;">LAB COMMENTS&nbsp;&nbsp;:&nbsp;&nbsp;<span style="font-weight:normal;">'.ucfirst($result['approver_comments']).'</span></td>';
               $html .='</tr>';
               $html .='<tr>';
               $html .='<td colspan="3" style="line-height:10px;"></td>';
               $html .='</tr>';
          }
          $html .='<tr>';
          $html .='<td colspan="3" style="line-height:2px;border-bottom:2px solid #d3d3d3;"></td>';
          $html .='</tr>';
          $html .='<tr>';
          $html .='<td colspan="3" style="line-height:14px;"></td>';
          $html .='</tr>';
          $html .='<tr>';
          $html .='<td colspan="3" style="line-height:11px;font-size:11px;font-weight:bold;">TEST PLATFORM &nbsp;&nbsp;:&nbsp;&nbsp; <span style="font-weight:normal;">'.ucwords($result['vl_test_platform']).'</span></td>';
          $html .='</tr>';
          $html .='<tr>';
          $html .='<td colspan="3" style="line-height:8px;"></td>';
          $html .='</tr>';
          if(isset($result['last_viral_load_result']) && $result['last_viral_load_result'] != null){          
            $html .='<tr>';
            $html .='<td colspan="3" style="line-height:11px;font-size:11px;font-weight:bold;">PREVIOUS RESULTS</td>';
            $html .='</tr>';
            $html .='<tr>';
            $html .='<td colspan="3" style="line-height:8px;"></td>';
            $html .='</tr>';
            $html .='<tr>';
            $html .='<td colspan="3" style="line-height:11px;font-size:11px;font-weight:bold;">Date of Last Viral Load Test&nbsp;&nbsp;:&nbsp;&nbsp;<span style="font-weight:normal;">'.$result['last_viral_load_date'].'</span></td>';
            $html .='</tr>';
            $html .='<tr>';
            $html .='<td colspan="3" style="line-height:11px;font-size:11px;font-weight:bold;">Result of previous viral load(copies/ml)&nbsp;&nbsp;:&nbsp;&nbsp;<span style="font-weight:normal;">'.$result['last_viral_load_result'].'</span></td>';
            $html .='</tr>';
          }
          $html .='<tr>';
          $html .='<td colspan="3" style="line-height:8px;border-bottom:2px solid #d3d3d3;"></td>';
          $html .='</tr>';
          $html .='<tr>';
          $html .='<td colspan="3" style="line-height:8px;"></td>';
          $html .='</tr>';
          $html .='<tr>';
          $html .='<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">TESTED BY</td>';
          $html .='<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">SIGNATURE</td>';
          $html .='<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">DATE</td>';
          $html .='</tr>';
          $html .='<tr>';
          $html .='<td style="line-height:11px;font-size:11px;text-align:left;"></td>';
          $html .='<td style="line-height:11px;font-size:11px;text-align:left;"></td>';
          $html .='<td style="line-height:11px;font-size:11px;text-align:left;"></td>';
          $html .='</tr>';
          $html .='<tr>';
          $html .='<td colspan="3" style="line-height:8px;"></td>';
          $html .='</tr>';
          $html .='<tr>';
          $html .='<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">APPROVED BY</td>';
          $html .='<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">SIGNATURE</td>';
          $html .='<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">DATE</td>';
          $html .='</tr>';
          $html .='<tr>';
          $html .='<td style="line-height:11px;font-size:11px;text-align:left;">'.$resultApprovedBy.'</td>';
          if(!empty($userSignaturePath) && file_exists($userSignaturePath)){
            $html .='<td style="line-height:11px;font-size:11px;text-align:left;"><img src="'.$userSignaturePath.'" style="width:100px;" /></td>';
          }else{
            $html .='<td style="line-height:11px;font-size:11px;text-align:left;"></td>';
          }
          
          $html .='<td style="line-height:11px;font-size:11px;text-align:left;"></td>';
          $html .='</tr>';

          $html .='<tr>';
          $html .='<td colspan="3" style="line-height:20px;border-bottom:2px solid #d3d3d3;"></td>';
          $html .='</tr>';
          $html .='<tr>';
          $html .='<td colspan="3" style="line-height:2px;"></td>';
          $html .='</tr>';
          $html .='<tr>';
          $html .='<td colspan="3">';
          $html .='<table>';
          $html .='<tr>';
          $html .='<td style="font-size:10px;text-align:left;width:60%;"><img src="/assets/img/smiley_smile.png" alt="smile_face" style="width:10px;height:10px;"/> = VL < = 1000 copies/ml: Continue on current regimen</td>';
          $html .='<td style="font-size:10px;text-align:left;">Printed on : '.$printDate.'&nbsp;&nbsp;'.$printDateTime.'</td>';
          $html .='</tr>';
          $html .='<tr>';
          $html .='<td colspan="2" style="font-size:10px;text-align:left;width:60%;"><img src="/assets/img/smiley_frown.png" alt="frown_face" style="width:10px;height:10px;"/> = VL > 1000 copies/ml: copies/ml: Clinical and counselling action required</td>';
          $html .='</tr>';
          $html .='</table>';
          $html .='</td>';
          $html .='</tr>';
          $html.='</table>';
          if($result['result']!='' || ($result['result']=='' && $result['result_status']=='4')){
               $pdf->writeHTML($html);
               $pdf->lastPage();
               $filename = $pathFront. DIRECTORY_SEPARATOR .'p'.$page. '.pdf';
               $pdf->Output($filename,"F");
               if($draftTextShow){
                    //Watermark section
                    $watermark = new Watermark();
                    $fullPathToFile = $filename;
                    $watermark->Output($filename,"F");
               }
               $pages[] = $filename;
               $page++;
          }
          if(isset($_POST['source']) && trim($_POST['source']) == 'print'){
               //Add event log
               $eventType = 'print-result';
               $action = ucwords($_SESSION['userName']).' print the test result with patient code '.$result['patient_art_no'];
               $resource = 'print-test-result';
               $data=array(
                    'event_type'=>$eventType,
                    'action'=>$action,
                    'resource'=>$resource,
                    'date_time'=>$general->getDateTime()
               );
               $db->insert($tableName1,$data);
               //Update print datetime in VL tbl.
               $vlQuery = "SELECT result_printed_datetime FROM vl_request_form as vl WHERE vl.vl_sample_id ='".$result['vl_sample_id']."'";
               $vlResult = $db->query($vlQuery);
               if($vlResult[0]['result_printed_datetime'] == NULL || trim($vlResult[0]['result_printed_datetime']) == '' || $vlResult[0]['result_printed_datetime'] =='0000-00-00 00:00:00'){
                    $db = $db->where('vl_sample_id',$result['vl_sample_id']);
                    $db->update($tableName2,array('result_printed_datetime'=>$general->getDateTime()));
               }
          }
     }

     if(count($pages) >0){
          $resultPdf = new Pdf_concat();
          $resultPdf->setFiles($pages);
          $resultPdf->setPrintHeader(false);
          $resultPdf->setPrintFooter(false);
          $resultPdf->concat();
          $resultFilename = 'VLSM-Test-result-' . date('d-M-Y-H-i-s') .'.pdf';
          $resultPdf->Output(UPLOAD_PATH. DIRECTORY_SEPARATOR.$resultFilename, "F");
          $general->removeDirectory($pathFront);
          unset($_SESSION['rVal']);
     }
}

echo $resultFilename;
