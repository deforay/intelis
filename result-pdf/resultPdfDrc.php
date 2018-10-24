<?php
$resultFilename = '';
if(sizeof($requestResult)> 0){
    $_SESSION['rVal'] = $general->generateRandomString(6);
    if (!file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . $_SESSION['rVal']) && !is_dir(UPLOAD_PATH . DIRECTORY_SEPARATOR . $_SESSION['rVal'])) {
      mkdir(UPLOAD_PATH . DIRECTORY_SEPARATOR . $_SESSION['rVal']);
    }
    $pathFront = realpath('../uploads/'.$_SESSION['rVal'].'/');
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
        $pdf->setHeading($arr['logo'],$arr['header'],$result['labName']);
        // set document information
        $pdf->SetCreator(PDF_CREATOR);
        //$pdf->SetAuthor('Pal');
        $pdf->SetTitle('PROGRAMME NATIONAL DE LUTTE CONTRE LE SIDA ET IST');
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
        //if (@file_exists(dirname(__FILE__).'/lang/eng.php')) {
        //    require_once(dirname(__FILE__).'/lang/eng.php');
        //    $pdf->setLanguageArray($l);
        //}

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

        if(isset($result['result_printed_datetime']) && trim($result['result_printed_datetime'])!='' && $result['result_printed_datetime']!='0000-00-00 00:00:00'){
          $expStr=explode(" ",$result['result_printed_datetime']);
          $result['result_printed_datetime']=$general->humanDateFormat($expStr[0])." ".$expStr[1];
        }else{
          $result['result_printed_datetime']='';
        }

        if(isset($result['last_viral_load_date']) && trim($result['last_viral_load_date'])!='' && $result['last_viral_load_date']!='0000-00-00'){
          $result['last_viral_load_date']=$general->humanDateFormat($result['last_viral_load_date']);
        }else{
          $result['last_viral_load_date']='';
        }
        if(!isset($result['patient_gender']) || trim($result['patient_gender'])== ''){
          $result['patient_gender'] = 'not reported';
        }
        $resultApprovedBy  = '';
        if(isset($result['approvedBy']) && trim($result['approvedBy'])!=''){
          $resultApprovedBy = ucwords($result['approvedBy']);
        }
        $vlResult = '';
        $smileyContent = '';
        $showMessage = '';
        $tndMessage = '';
        $messageTextSize = '12px';
        if($result['result']!= NULL && trim($result['result'])!= '') {
          $resultType = is_numeric($result['result']);
          if(in_array(strtolower(trim($result['result'])),array("tnd","target not detected"))){
            $vlResult = 'TND*';
            $smileyContent = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<img src="../assets/img/smiley_smile.png" alt="smile_face"/>';
            $showMessage = ucfirst($arr['l_vl_msg']);
            $tndMessage = 'TND* - Target not Detected';
          }else if(in_array(strtolower(trim($result['result'])),array("failed","fail","no_sample"))){
            $vlResult = $result['result'];
            $smileyContent = '';
            $showMessage = '';
            $messageTextSize = '14px';
          }else if(trim($result['result']) > 1000 && $result['result']<=10000000){
            $vlResult = $result['result'];
            $smileyContent = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<img src="../assets/img/smiley_frown.png" alt="frown_face"/>';
            $showMessage = ucfirst($arr['h_vl_msg']);
            $messageTextSize = '15px';
          }else if(trim($result['result']) <= 1000 && $result['result']>=20){
            $vlResult = $result['result'];
            $smileyContent = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<img src="../assets/img/smiley_smile.png" alt="smile_face"/>';
            $showMessage = ucfirst($arr['l_vl_msg']);
          }else if(trim($result['result'] > 10000000) && $resultType){
            $vlResult = $result['result'];
            $smileyContent = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<img src="../assets/img/smiley_frown.png" alt="frown_face"/>';
            //$showMessage = 'Value outside machine detection limit';
          }else if(trim($result['result'] < 20) && $resultType){
            $vlResult = $result['result'];
            $smileyContent = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<img src="../assets/img/smiley_smile.png" alt="smile_face"/>';
            //$showMessage = 'Value outside machine detection limit';
          }else if(trim($result['result'])=='<20'){
            $vlResult = '&lt;20';
            $smileyContent = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<img src="../assets/img/smiley_smile.png" alt="smile_face"/>';
            $showMessage = ucfirst($arr['l_vl_msg']);
          }else if(trim($result['result'])=='>10000000'){
            $vlResult = $result['result'];
            $smileyContent = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<img src="../assets/img/smiley_frown.png" alt="frown_face"/>';
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
          }
            
            $chkSign = '';
            $chkSign = strchr($result['result'],'<');
            if($chkSign!=''){
              $smileyShow =str_replace("<","",$result['result']);
              $vlResult = str_replace("<","&lt;",$result['result']);
              //$showMessage = 'Invalid value';
            }
            if(isset($smileyShow) && $smileyShow!='' && $smileyShow <= $arr['viral_load_threshold_limit']){
              $smileyContent = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<img src="../assets/img/smiley_smile.png" alt="smile_face"/>';
            }else if(isset($smileyShow) && $smileyShow!='' && $smileyShow > $arr['viral_load_threshold_limit']){
              $smileyContent = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<img src="../assets/img/smiley_frown.png" alt="frown_face"/>';
            }
          
        }
        if(isset($arr['show_smiley']) && trim($arr['show_smiley']) == "no"){
          $smileyContent = '';
        }
        if($result['result_status']=='4'){
          $smileyContent = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<img src="../assets/img/cross.png" alt="rejected"/>';
        }
        $html = '';
            $html.='<table style="padding:0px 2px 2px 2px;">';
              $html .='<tr>';
               $html .='<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">Échantillon id</td>';
               $html .='<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">Date du prélèvement</td>';
               $html .='<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">Code du patient</td>';
              $html .='</tr>';
              $html .='<tr>';
                $html .='<td style="line-height:11px;font-size:11px;text-align:left;">'.$result['sample_code'].'</td>';
                $html .='<td style="line-height:11px;font-size:11px;text-align:left;">'.$result['sample_collection_date']." ".$sampleCollectionTime.'</td>';
                $html .='<td style="line-height:11px;font-size:11px;text-align:left;">'.$result['patient_art_no'].'</td>';
              $html .='</tr>';
              $html .='<tr>';
               $html .='<td colspan="3" style="line-height:10px;"></td>';
              $html .='</tr>';
              //$html .='<tr>';
              // $html .='<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">Pr�nom du patient</td>';
              // $html .='<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">Nom de famille du patient</td>';
              // $html .='<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">Mobile No.</td>';
              //$html .='</tr>';
              //$html .='<tr>';
              //  $html .='<td style="line-height:11px;font-size:11px;text-align:left;">'.ucwords($result['patient_first_name']).'</td>';
              //  $html .='<td style="line-height:11px;font-size:11px;text-align:left;">'.ucwords($result['patient_last_name']).'</td>';
              //  $html .='<td style="line-height:11px;font-size:11px;text-align:left;">'.$result['patient_mobile_number'].'</td>';
              //$html .='</tr>';
              //$html .='<tr>';
               //$html .='<td colspan="3" style="line-height:10px;"></td>';
              //$html .='</tr>';
              $html .='<tr>';
               $html .='<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">Âge</td>';
               $html .='<td colspan="2" style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">Sexe</td>';
              $html .='</tr>';
              $html .='<tr>';
                $html .='<td style="line-height:11px;font-size:11px;text-align:left;">'.$age.'</td>';
                $html .='<td colspan="2" style="line-height:11px;font-size:11px;text-align:left;">'.ucwords(str_replace("_"," ",$result['patient_gender'])).'</td>';
              $html .='</tr>';
              $html .='<tr>';
               $html .='<td colspan="3" style="line-height:10px;"></td>';
              $html .='</tr>';
              $html .='<tr>';
               $html .='<td colspan="3" style="line-height:2px;border-bottom:2px solid #d3d3d3;"></td>';
              $html .='</tr>';
              $html .='<tr>';
               $html .='<td colspan="3" style="line-height:10px;"></td>';
              $html .='</tr>';
              $html .='<tr>';
               $html .='<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">Code Clinique</td>';
               $html .='<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">Province</td>';
               $html .='<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">Zone de santé</td>';
              $html .='</tr>';
              $html .='<tr>';
                $html .='<td style="line-height:11px;font-size:11px;text-align:left;">'.$result['facility_code'].'</td>';
                $html .='<td style="line-height:11px;font-size:11px;text-align:left;">'.ucwords($result['facility_state']).'</td>';
                $html .='<td style="line-height:11px;font-size:11px;text-align:left;">'.ucwords($result['facility_district']).'</td>';
              $html .='</tr>';
              $html .='<tr>';
               $html .='<td colspan="3" style="line-height:10px;"></td>';
              $html .='</tr>';
              $html .='<tr>';
               $html .='<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">Nom de l�installation</td>';
               $html .='<td colspan="2" style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">Nom clinicien</td>';
              $html .='</tr>';
              $html .='<tr>';
                $html .='<td style="line-height:11px;font-size:11px;text-align:left;">'.ucwords($result['facility_name']).'</td>';
                $html .='<td colspan="2" style="line-height:11px;font-size:11px;text-align:left;">'.ucwords($result['request_clinician_name']).'</td>';
              $html .='</tr>';
              $html .='<tr>';
               $html .='<td colspan="3" style="line-height:10px;"></td>';
              $html .='</tr>';
              $html .='<tr>';
               $html .='<td colspan="3" style="line-height:2px;border-bottom:2px solid #d3d3d3;"></td>';
              $html .='</tr>';
              $html .='<tr>';
               $html .='<td colspan="3" style="line-height:10px;"></td>';
              $html .='</tr>';
              $html .='<tr>';
                $html .='<td colspan="3">';
                 $html .='<table style="padding:2px;">';
                   $html .='<tr>';
                    $html .='<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">Date de réception de léchantillon</td>';
                    $html .='<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">Date de remise du résultat</td>';
                    $html .='<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">Type déchantillon</td>';
                    $html .='<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">Technique utilisée</td>';
                   $html .='</tr>';
                   $html .='<tr>';
                     $html .='<td style="line-height:11px;font-size:11px;text-align:left;">'.$sampleReceivedDate." ".$sampleReceivedTime.'</td>';
                     $html .='<td style="line-height:11px;font-size:11px;text-align:left;">'.$result['result_printed_datetime'].'</td>';
                     $html .='<td style="line-height:11px;font-size:11px;text-align:left;">'.ucwords($result['sample_name']).'</td>';
                     $html .='<td style="line-height:11px;font-size:11px;text-align:left;">'.ucwords($result['vl_test_platform']).'</td>';
                   $html .='</tr>';
                   $html .='<tr>';
                     $html .='<td colspan="4" style="line-height:16px;"></td>';
                   $html .='</tr>';
                   $html .='<tr>';
                    $html .='<td colspan="3"></td>';
                    $html .='<td rowspan="3" style="text-align:left;">'.$smileyContent.'</td>';
                   $html .='</tr>';
                   $logValue = '';
                   if($result['result_value_log']!=''){
                    $logValue = '<br/>&nbsp;&nbsp;Log Value&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;:&nbsp;&nbsp;&nbsp;&nbsp;'.$result['result_value_log'];
                   }else{
                    $logValue = '<br/>&nbsp;&nbsp;Log Value&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;:&nbsp;&nbsp;&nbsp;&nbsp;0.0';
                   }
                   $html .='<tr><td colspan="3" style="line-height:26px;font-size:12px;font-weight:bold;text-align:left;background-color:#dbdbdb;">&nbsp;&nbsp;Résultat(copies/ml)&nbsp;&nbsp;&nbsp;&nbsp;:&nbsp;&nbsp;&nbsp;&nbsp;'.$vlResult.$logValue.'</td></tr>';
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
              $html .='<tr>';
               $html .='<td colspan="3" style="line-height:11px;font-size:11px;font-weight:bold;">Approuv� par&nbsp;&nbsp;:&nbsp;&nbsp;<span style="font-weight:normal;">'.$resultApprovedBy.'</span></td>';
              $html .='</tr>';
              $html .='<tr>';
               $html .='<td colspan="3" style="line-height:10px;"></td>';
              $html .='</tr>';
              if(trim($result['approver_comments'])!= ''){
                $html .='<tr>';
                 $html .='<td colspan="3" style="line-height:11px;font-size:11px;font-weight:bold;">Commentaires du laboratoire&nbsp;&nbsp;:&nbsp;&nbsp;<span style="font-weight:normal;">'.ucfirst($result['approver_comments']).'</span></td>';
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
               $html .='<td colspan="3" style="line-height:11px;font-size:11px;font-weight:bold;">R�sultats pr�c�dents</td>';
              $html .='</tr>';
              $html .='<tr>';
               $html .='<td colspan="3" style="line-height:8px;"></td>';
              $html .='</tr>';
              $html .='<tr>';
               $html .='<td colspan="3" style="line-height:11px;font-size:11px;font-weight:bold;">Date derni�re charge virale (demande)&nbsp;&nbsp;:&nbsp;&nbsp;<span style="font-weight:normal;">'.$result['last_viral_load_date'].'</span></td>';
              $html .='</tr>';
              $html .='<tr>';
               $html .='<td colspan="3" style="line-height:11px;font-size:11px;font-weight:bold;">R�sultat derni�re charge virale(copies/ml)&nbsp;&nbsp;:&nbsp;&nbsp;<span style="font-weight:normal;">'.$result['last_viral_load_result'].'</span></td>';
              $html .='</tr>';
              $html .='<tr>';
               $html .='<td colspan="3" style="line-height:110px;border-bottom:2px solid #d3d3d3;"></td>';
              $html .='</tr>';
              $html .='<tr>';
               $html .='<td colspan="3" style="line-height:2px;"></td>';
              $html .='</tr>';
              $html .='<tr>';
                $html .='<td colspan="3">';
                 $html .='<table>';
                  $html .='<tr>';
                    $html .='<td style="font-size:10px;text-align:left;width:60%;"><img src="../assets/img/smiley_smile.png" alt="smile_face" style="width:10px;height:10px;"/> = VL < = 1000 copies/ml: Continue on current regimen</td>';
                    $html .='<td style="font-size:10px;text-align:left;">Printed on : '.$printDate.'&nbsp;&nbsp;'.$printDateTime.'</td>';
                  $html .='</tr>';
                  $html .='<tr>';
                    $html .='<td colspan="2" style="font-size:10px;text-align:left;width:60%;"><img src="../assets/img/smiley_frown.png" alt="frown_face" style="width:10px;height:10px;"/> = VL > 1000 copies/ml: copies/ml: Clinical and counselling action required</td>';
                  $html .='</tr>';
                 $html .='</table>';
                $html .='</td>';
              $html .='</tr>';
            $html.='</table>';
        if($result['result']!=''){
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
        $resultFilename = 'VLSM-Test-Result-' . date('d-M-Y-H-i-s') .'.pdf';
        $resultPdf->Output(UPLOAD_PATH. DIRECTORY_SEPARATOR.$resultFilename, "F");
        $general->removeDirectory($pathFront);
        unset($_SESSION['rVal']);
    }
}

echo $resultFilename;
?>