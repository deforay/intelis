<?php

try {
    $db->delete('temp_sample_import');
    //set session for controller track id in hold_sample_record table
    $cQuery  = "select MAX(import_batch_tracking) FROM hold_sample_import";
    $cResult = $db->query($cQuery);
    if ($cResult[0]['MAX(import_batch_tracking)'] != '') {
        $maxId = $cResult[0]['MAX(import_batch_tracking)'] + 1;
    } else {
        $maxId = 1;
    }
    $_SESSION['controllertrack'] = $maxId;
    
    $allowedExtensions = array(
        'xls',
        'xlsx',
        'csv'
    );
    $fileName          = preg_replace('/[^A-Za-z0-9.]/', '-', $_FILES['resultFile']['name']);
    $fileName          = str_replace(" ", "-", $fileName);
    $ranNumber         = str_pad(rand(0, pow(10, 6) - 1), 6, '0', STR_PAD_LEFT);
    $extension         = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    $fileName          = $ranNumber . "." . $extension;
    
    
    if (!file_exists('../temporary' . DIRECTORY_SEPARATOR . "import-result") && !is_dir('../temporary' . DIRECTORY_SEPARATOR . "import-result")) {
        mkdir('../temporary' . DIRECTORY_SEPARATOR . "import-result");
    }
    if (move_uploaded_file($_FILES['resultFile']['tmp_name'], '../temporary' . DIRECTORY_SEPARATOR . "import-result" . DIRECTORY_SEPARATOR . $fileName)) {
        //$file_info = new finfo(FILEINFO_MIME); // object oriented approach!
        //$mime_type = $file_info->buffer(file_get_contents('../temporary' . DIRECTORY_SEPARATOR . "import-result" . DIRECTORY_SEPARATOR . $fileName)); // e.g. gives "image/jpeg"

        $objPHPExcel = \PHPExcel_IOFactory::load('../temporary' . DIRECTORY_SEPARATOR . "import-result" . DIRECTORY_SEPARATOR . $fileName);
        $sheetData   = $objPHPExcel->getActiveSheet();
        
        
        
        
        
        $bquery    = "select MAX(batch_code_key) from batch_details";
        $bvlResult = $db->rawQuery($bquery);
        if ($bvlResult[0]['MAX(batch_code_key)'] != '' && $bvlResult[0]['MAX(batch_code_key)'] != NULL) {
            $maxBatchCodeKey = $bvlResult[0]['MAX(batch_code_key)'] + 1;
            $maxBatchCodeKey = "00" . $maxBatchCodeKey;
        } else {
            $maxBatchCodeKey = '001';
        }
        
        $newBatchCode = date('Ymd') . $maxBatchCodeKey;
        
          $sheetData = $sheetData->toArray(null, true, true, true);
          
          //echo "<pre>";var_dump($sheetData);echo "</pre>";
          
          $m = 0;
          $skipTillRow = 2;
        
          $sampleIdCol='B';
          $sampleIdRow='2';
          $logValCol='F';
          $logValRow='2';
          $absValCol='E';
          $absValRow='2';
          $txtValCol='G';
          $txtValRow='2';
          $testingDateCol='D';
          $testingDateRow='2';
          $logAndAbsoluteValInSameCol='no';
          $sampleTypeCol = '';
          $batchCodeCol = '';
          $flagCol = '';
          $lotNumberCol = '';
          $reviewByCol = 'H';
          $lotExpirationDateCol = '';
          
          $testingPlatformCol = 'I';
          
        
        foreach ($sheetData as $rowIndex => $row) {
          if ($rowIndex < $skipTillRow)
            continue;
          
          if (strpos(strtolower($row[$testingPlatformCol]), 'viral') === false)
            continue;
          
          
          $sampleCode    = "";
          $batchCode     = "";
          $sampleType    = "";
          $absDecimalVal = "";
          $absVal        = "";
          $logVal        = "";
          $txtVal        = "";
          $resultFlag    = "";
          $testingDate   = "";
          $lotNumberVal = "";
          $reviewBy = "";
          $lotExpirationDateVal = null;
         
          $sampleCode = $row[$sampleIdCol];
          $sampleType = "S";
          $batchCode = "";
          $resultFlag = "";
          $reviewBy = $row[$reviewByCol];
          
          $testingDate = date('Y-m-d H:i', strtotime($row[$testingDateCol]));
          
          if(trim($row[$absValCol])!=""){
              $resVal=(float)$row[$absValCol];
              if($resVal > 0){
                  $absVal= $resVal;
                  $absDecimalVal = $resVal;
                  if($row[$logValCol] != null && trim($row[$logValCol]) != ""){
                    $logVal=(float)$row[$logValCol];
                  }else{
                    $logVal=round(log10($absDecimalVal),4);
                  }
                  $txtVal="";
              }else{
                  $absDecimalVal = $absVal="";
                  $logVal="";                       
                  $txtVal=trim($row[$absValCol]);
              }
          }
          
        
        
          if($sampleCode == ""){
            $sampleCode = $sampleType.$m;
          }
           //   continue;
            
          $infoFromFile[$sampleCode] = array(
              "sampleCode" => $sampleCode,
              "logVal" => trim($logVal),
              "absVal" => $absVal,
              "absDecimalVal" => $absDecimalVal,
              "txtVal" => $txtVal,
              "resultFlag" => $resultFlag,
              "testingDate" => $testingDate,
              "sampleType" => $sampleType,
              "batchCode" => $batchCode,
              "lotNumber" => $lotNumberVal,
              "lotExpirationDate" => $lotExpirationDateVal,
              "reviewBy"=>$reviewBy
          );
            
            $m++;
        }
        
        
        //echo "<pre>";var_dump($infoFromFile);echo "</pre>";die;
        $inc = 0;
        $refno = 0;
        foreach ($infoFromFile as $sampleCode => $d) {
            if($d['sampleCode'] == $d['sampleType'].$inc){
               $d['sampleCode'] = ''; 
            }
            if($d['sampleType'] =='S' || $d['sampleType'] =='s'){
              $refno+=1;  
            }
            $data = array(
                'lab_id' => base64_decode($_POST['labId']),
                'vl_test_platform' => $_POST['vltestPlatform'],
                'import_machine_name' => $_POST['configMachineName'],
                'result_reviewed_by' => $_SESSION['userId'],
                'sample_code' => $d['sampleCode'],
                'result_value_log' => $d['logVal'],
                'sample_type' => $d['sampleType'],
                'result_value_absolute' => $d['absVal'],
                'result_value_text' => $d['txtVal'],
                'result_value_absolute_decimal' => $d['absDecimalVal'],
                'sample_tested_datetime' => $testingDate,
                'result_status' => '6',
                'import_machine_file_name' => $fileName,
                'approver_comments' => $d['resultFlag'],
                'lot_number' => $d['lotNumber'],
                'lot_expiration_date' => $d['lotExpirationDate']
            );
            
            //echo "<pre>";var_dump($data);continue;
            if ($d['absVal'] != "") {
                $data['result'] = $d['absVal'];
            } else if ($d['logVal'] != "") {
                $data['result'] = $d['logVal'];
            } else if ($d['txtVal'] != "") {
                $data['result'] = $d['txtVal'];
            } else {
                $data['result'] = "";
            }
            
            if ($batchCode == '') {
                $data['batch_code']     = $newBatchCode;
                $data['batch_code_key'] = $maxBatchCodeKey;
            } else {
                $data['batch_code'] = $batchCode;
            }
            //get user name
            if($d['reviewBy']!=''){
                $uQuery = "select user_name,user_id from user_details where user_name='".$d['reviewBy']."'";
                $uResult = $db->rawQuery($uQuery);
                if($uResult){
                    $data['sample_review_by'] = $uResult[0]['user_id'];
                }else{
                    $userdata=array(
                    'user_name'=>$d['reviewBy'],
                    'role_id'=>'3',
                    'status'=>'active'
                    );
                    $db->insert('user_details',$userdata);
                    $data['sample_review_by'] = $db->getInsertId();
                }
            }
            
            $query    = "select facility_id,vl_sample_id,result,result_value_log,result_value_absolute,result_value_text,result_value_absolute_decimal from vl_request_form where sample_code='" . $sampleCode . "'";
            $vlResult = $db->rawQuery($query);
            //insert sample controls
            $scQuery = "select r_sample_control_name from r_sample_controls where r_sample_control_name='".trim($d['sampleType'])."'";
            $scResult = $db->rawQuery($scQuery);
            if($scResult==false){
                $scData = array('r_sample_control_name'=>trim($d['sampleType']));
                $scId = $db->insert("r_sample_controls", $scData);
            }
            if ($vlResult && $sampleCode != '') {
                if ($vlResult[0]['result_value_log'] != '' || $vlResult[0]['result_value_absolute'] != '' || $vlResult[0]['result_value_text'] != '' || $vlResult[0]['result_value_absolute_decimal'] != '') {
                    $data['sample_details'] = 'Result already exists';
                } else {
                    $data['result_status'] = '7';
                }
                $data['facility_id'] = $vlResult[0]['facility_id'];
            } else {
                $data['sample_details'] = 'New Sample';
            }
            //echo "<pre>";var_dump($data);echo "</pre>";continue;
            if ($sampleCode != '' || $batchCode != '' || $sampleType != '' || $logVal != '' || $absVal != '' || $absDecimalVal != '') {
                $data['result_imported_datetime'] = $general->getDateTime();
                $id = $db->insert("temp_sample_import", $data);
            }
            $inc++;
        }
       setcookie('refno', $refno, time() + (86400 * 30), "/");
    }

    $_SESSION['alertMsg'] = "Results imported successfully";
    //Add event log
    $eventType            = 'import';
    $action               = ucwords($_SESSION['userName']) . ' imported a new test result with the sample code ' . $sampleCode;
    $resource             = 'import-result';
    $data                 = array(
        'event_type' => $eventType,
        'action' => $action,
        'resource' => $resource,
        'date_time' => $general->getDateTime()
    );
    $db->insert("activity_log", $data);
    
    //new log for update in result
    if(isset($id) && $id > 0){
        $data = array(
        'user_id' => $_SESSION['userId'],
        'vl_sample_id' => $id,
        'updated_on' => $general->getDateTime()
        );
        $db->insert("log_result_updates", $data);
    }
    header("location:../vl-print/vlResultUnApproval.php");
    
}
catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}