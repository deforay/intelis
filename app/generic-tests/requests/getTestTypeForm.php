<?php
use App\Utilities\DateUtility;

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$testTypeForm = array();
if(isset($_POST['testTypeForm']) && !empty($_POST['testTypeForm'])){
    $testTypeForm = (array)json_decode(base64_decode($_POST['testTypeForm']));
}
$disabled = "";
if(isset($_POST['formType']) && !empty($_POST['formType']) && $_POST['formType'] == 'update-form'){
    $disabled = ' disabled ';
}
$testTypeQuery = "SELECT * FROM r_test_types where test_type_id=".$_POST['testType'];
$testTypeResult = $db->rawQuery($testTypeQuery);
$testAttribute=json_decode($testTypeResult[0]['test_form_config'],true);

$facilityForm=array();
$patientForm=array();
$specimenForm=array();
$lapForm=array();
$otherForm=array();
$n=count($testAttribute['field_name']);
if ($n > 0) {
    for ($i=0;$i<$n;$i++){
        //$textBox="<input type='text' class='form-control isRequired' name='testType'>";
        $isRequired="";
        $mandatory="";
        $fieldType="";
        if($testAttribute['mandatory_field'][$i]=='yes'){
            $isRequired="isRequired";
            $mandatory='<span class="mandatory">*</span>';
        }
        $value = "";
        if(isset($testTypeForm[$testAttribute['field_id'][$i]]) && !empty($testTypeForm[$testAttribute['field_id'][$i]])){
            $value = $testTypeForm[$testAttribute['field_id'][$i]];
        }
        if($testAttribute['field_type'][$i]=='number'){
            $fieldType=" forceNumeric";
        }else if($testAttribute['field_type'][$i]=='date'){
            $fieldType=" date";
        }
        

        if(isset($_POST['formType']) && !empty($_POST['formType']) && $_POST['formType'] == 'update-form' && $testAttribute['section'][$i] != 'lap'){
            $isRequired = "";
        }
        if($testAttribute['section'][$i] == 'facility'){
            $facilityForm[]='<div class="col-xs-4 col-md-4"><div class="form-group"><label>'.$testAttribute['field_name'][$i].$mandatory.'</label><input type="text" class="form-control '.$isRequired.$fieldType.$disabled.'" placeholder="'.$testAttribute['field_name'][$i].'" id="'.$testAttribute['field_id'][$i].'" name="dynamicFields['.$testAttribute['field_id'][$i].']" value="'.$value.'" '.$disabled.'><input type="hidden" class="form-control" name="testTypeId[]" value="'.$testAttribute['field_id'][$i].'"></div></div>';
        }
        else if($testAttribute['section'][$i] == 'patient'){

            $patientForm[]='<div class="col-xs-4 col-md-4"><div class="form-group"><label>'.$testAttribute['field_name'][$i].$mandatory.'</label><input type="text" class="form-control '.$isRequired.$fieldType.$disabled.'" placeholder="'.$testAttribute['field_name'][$i].'" id="'.$testAttribute['field_id'][$i].'" name="dynamicFields['.$testAttribute['field_id'][$i].']" value="'.$value.'" '.$disabled.'><input type="hidden" class="form-control" name="testTypeId[]" value="'.$testAttribute['field_id'][$i].'"></div></div>';
        }
        else if($testAttribute['section'][$i] == 'specimen'){
            $specimenForm[]='<div class="col-xs-4 col-md-4"><div class="form-group"><label>'.$testAttribute['field_name'][$i].$mandatory.'</label><input type="text" class="form-control '.$isRequired.$fieldType.$disabled.'" placeholder="'.$testAttribute['field_name'][$i].'" id="'.$testAttribute['field_id'][$i].'" name="dynamicFields['.$testAttribute['field_id'][$i].']" value="'.$value.'" '.$disabled.'><input type="hidden" class="form-control" name="testTypeId[]" value="'.$testAttribute['field_id'][$i].'"></div></div>';
        }
        else if($testAttribute['section'][$i] == 'lap'){
            $lapForm[]='<div class="col-xs-4 col-md-4"><div class="form-group"><label>'.$testAttribute['field_name'][$i].$mandatory.'</label><input type="text" class="form-control '.$isRequired.$fieldType.$disabled.'" placeholder="'.$testAttribute['field_name'][$i].'" id="'.$testAttribute['field_id'][$i].'" name="dynamicFields['.$testAttribute['field_id'][$i].']" value="'.$value.'"><input type="hidden" class="form-control" name="testTypeId[]" value="'.$testAttribute['field_id'][$i].'"></div></div>';
        }else if($testAttribute['section'][$i] == 'other'){
            $otherForm[]='<div class="box-header with-border"><h3 class="box-title">'.$testAttribute['section_other'][$i].'</h3></div><div class="col-xs-4 col-md-4"><div class="form-group"><label>'.$testAttribute['field_name'][$i].$mandatory.'</label><input type="text" class="form-control '.$isRequired.$fieldType.$disabled.'" placeholder="'.$testAttribute['field_name'][$i].'" id="'.$testAttribute['field_id'][$i].'" name="dynamicFields['.$testAttribute['field_id'][$i].']" value="'.$value.'" '.$disabled.'><input type="hidden" class="form-control" name="testTypeId[]" value="'.$testAttribute['field_id'][$i].'"></div></div>';
        }
    }
}
$result=array('facility'=>$facilityForm,'patient'=>$patientForm,'specimen'=>$specimenForm,'lap'=>$lapForm,'others'=>$otherForm);

echo(json_encode($result));