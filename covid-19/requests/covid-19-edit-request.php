<?php
ob_start();
$title = "COVID-19 | Edit Request";
#require_once('../../startup.php');
include_once(APPLICATION_PATH . '/header.php');
include_once(APPLICATION_PATH . '/models/General.php');
require_once(APPLICATION_PATH . '/models/Covid19.php');
?>
<style>
    .ui_tpicker_second_label,
    .ui_tpicker_second_slider,
    .ui_tpicker_millisec_label,
    .ui_tpicker_millisec_slider,
    .ui_tpicker_microsec_label,
    .ui_tpicker_microsec_slider,
    .ui_tpicker_timezone_label,
    .ui_tpicker_timezone {
        display: none !important;
    }

    .ui_tpicker_time_input {
        width: 100%;
    }
</style>



<?php


$labFieldDisabled = '';

if ($sarr['user_type'] == 'remoteuser') {
    $labFieldDisabled = 'disabled="disabled"';
    $vlfmQuery = "SELECT GROUP_CONCAT(DISTINCT vlfm.facility_id SEPARATOR ',') as facilityId FROM vl_user_facility_map as vlfm where vlfm.user_id='" . $_SESSION['userId'] . "'";
    $vlfmResult = $db->rawQuery($vlfmQuery);
}

$general = new General($db);



$rejectionTypeQuery = "SELECT DISTINCT rejection_type FROM r_covid19_sample_rejection_reasons WHERE rejection_reason_status ='active'";
$rejectionTypeResult = $db->rawQuery($rejectionTypeQuery);

//sample rejection reason
$rejectionQuery = "SELECT * FROM r_covid19_sample_rejection_reasons where rejection_reason_status = 'active'";
$rejectionResult = $db->rawQuery($rejectionQuery);

$condition = "status = 'active'";
if (isset($vlfmResult[0]['facilityId'])) {
    $condition = $condition . " AND facility_id IN(" . $vlfmResult[0]['facilityId'] . ")";
}
$fResult = $general->fetchDataFromTable('facility_details', $condition);


//get lab facility details
$condition = "facility_type='2' AND status='active'";
$lResult = $general->fetchDataFromTable('facility_details', $condition);


$id = base64_decode($_GET['id']);
//$id = ($_GET['id']);
$covid19Query = "SELECT * from form_covid19 where covid19_id=$id";
$covid19Info = $db->rawQueryOne($covid19Query);

$covid19TestQuery = "SELECT * from covid19_tests where covid19_id=$id ORDER BY test_id ASC";
$covid19TestInfo = $db->rawQuery($covid19TestQuery);

//echo "<pre>"; var_dump($covid19Info);die;

$specimenTypeResult = $general->fetchDataFromTable('r_covid19_sample_type', "status = 'active'");

$arr = $general->getGlobalConfig();


if ($arr['covid19_sample_code'] == 'auto' || $arr['covid19_sample_code'] == 'auto2' || $arr['covid19_sample_code'] == 'alphanumeric') {
    $sampleClass = '';
    $maxLength = '';
    if ($arr['covid19_max_length'] != '' && $arr['covid19_sample_code'] == 'alphanumeric') {
        $maxLength = $arr['covid19_max_length'];
        $maxLength = "maxlength=" . $maxLength;
    }
} else {
    $sampleClass = 'checkNum';
    $maxLength = '';
    if ($arr['covid19_max_length'] != '') {
        $maxLength = $arr['covid19_max_length'];
        $maxLength = "maxlength=" . $maxLength;
    }
}


if(isset($covid19Info['sample_collection_date']) && trim($covid19Info['sample_collection_date'])!='' && $covid19Info['sample_collection_date']!='0000-00-00 00:00:00'){
    $sampleCollectionDate = $covid19Info['sample_collection_date'];
    $expStr=explode(" ",$covid19Info['sample_collection_date']);
    $covid19Info['sample_collection_date']=$general->humanDateFormat($expStr[0])." ".$expStr[1];
}else{
    $sampleCollectionDate = '';
    $covid19Info['sample_collection_date']='';
}


$fileArray = array(
    1 => 'forms/edit-southsudan.php',
    2 => 'forms/edit-zimbabwe.php',
    3 => 'forms/edit-drc.php',
    4 => 'forms/edit-zambia.php',
    5 => 'forms/edit-png.php',
    6 => 'forms/edit-who.php',
    7 => 'forms/edit-rwanda.php',
    8 => 'forms/edit-angola.php',
);

require_once($fileArray[$arr['vl_form']]);

?>

<script>
    function checkSampleNameValidation(tableName, fieldName, id, fnct, alrt) {
        if ($.trim($("#" + id).val()) != '') {
            $.blockUI();
            $.post("/covid-19/requests/check-sample-duplicate.php", {
                    tableName: tableName,
                    fieldName: fieldName,
                    value: $("#" + id).val(),
                    fnct: fnct,
                    format: "html"
                },
                function(data) {
                    if (data != 0) {
                        <?php if (isset($sarr['user_type']) && ($sarr['user_type'] == 'remoteuser' || $sarr['user_type'] == 'standalone')) { ?>
                            alert(alrt);
                            $("#" + id).val('');
                        <?php } else { ?>
                            data = data.split("##");
                            document.location.href = " /covid-19/requests/covid-19-edit-request.php?id=" + data[0] + "&c=" + data[1];
                        <?php } ?>
                    }
                });
            $.unblockUI();
        }
    }

    $(document).ready(function() {
        $('.date').datepicker({
            changeMonth: true,
            changeYear: true,
            onSelect: function() {
                $(this).change();
            },
            dateFormat: 'dd-M-yy',
            timeFormat: "hh:mm TT",
            maxDate: "Today",
            yearRange: <?php echo (date('Y') - 100); ?> + ":" + "<?php echo (date('Y')) ?>"
        }).click(function() {
            $('.ui-datepicker-calendar').show();
        });


        $("#patientDob").datepicker({
            changeMonth: true,
            changeYear: true,
            dateFormat: 'dd-M-yy',
            maxDate: "Today",
            yearRange: <?php echo (date('Y') - 120); ?> + ":" + "<?php echo (date('Y')) ?>",
            onSelect: function(dateText, inst) {
                $("#sampleCollectionDate").datepicker("option", "minDate", $("#patientDob").datepicker("getDate"));
                $(this).change();
            }
        }).click(function() {
            $('.ui-datepicker-calendar').show();
        });


        $('.dateTime').datetimepicker({
            changeMonth: true,
            changeYear: true,
            dateFormat: 'dd-M-yy',
            timeFormat: "HH:mm",
            maxDate: "Today",
            onChangeMonthYear: function(year, month, widget) {
                setTimeout(function() {
                    $('.ui-datepicker-calendar').show();
                });
            },
            yearRange: <?php echo (date('Y') - 100); ?> + ":" + "<?php echo (date('Y')) ?>"
        }).click(function() {
            $('.ui-datepicker-calendar').show();
        });
        
        $('#sampleCollectionDate').datetimepicker({
            changeMonth: true,
            changeYear: true,
            dateFormat: 'dd-M-yy',
            timeFormat: "HH:mm",
            maxDate: "Today",
            onChangeMonthYear: function(year, month, widget) {
                setTimeout(function() {
                    $('.ui-datepicker-calendar').show();
                });
            },
            onSelect:function(e){
                $('#sampleReceivedDate').val('');
                $('#sampleReceivedDate').datetimepicker('option', 'minDate', e);
            },
            yearRange: <?php echo (date('Y') - 100); ?> + ":" + "<?php echo (date('Y')) ?>"
        }).click(function() {
            $('.ui-datepicker-calendar').show();
        });

        $('#sampleReceivedDate').datetimepicker({
            changeMonth: true,
            changeYear: true,
            dateFormat: 'dd-M-yy',
            timeFormat: "HH:mm",
            maxDate: "Today",
            onChangeMonthYear: function(year, month, widget) {
                setTimeout(function() {
                    $('.ui-datepicker-calendar').show();
                });
            },
            onSelect:function(e){
                $('#sampleTestedDateTime').val('');
                $('#sampleTestedDateTime').datetimepicker('option', 'minDate', e);
            },
            yearRange: <?php echo (date('Y') - 100); ?> + ":" + "<?php echo (date('Y')) ?>"
        }).click(function() {
            $('.ui-datepicker-calendar').show();
        });

        //$('.date').mask('99-aaa-9999');
        //$('.dateTime').mask('99-aaa-9999 99:99');

        $('#isSampleRejected').change(function(e) {
			changeReject(this.value);
		});
        $('#hasRecentTravelHistory').change(function(e){
            changeHistory(this.value);
        });
		changeReject($('#isSampleRejected').val());
        changeHistory($('#hasRecentTravelHistory').val());
    });
    function changeHistory(val){
        if(val == 'no' || val == 'unknown'){
            $('.historyfield').hide();
            $('#countryName,#returnDate').removeClass('isRequired');
        }else if(val == 'yes'){
            $('.historyfield').show();
            $('#countryName,#returnDate').addClass('isRequired');
        }
    }
    function changeReject(val){
		if (val == 'yes') {
            $('.show-rejection').show();
            $('.test-name-table-input').prop('disabled',true);
            $('.test-name-table').addClass('disabled');
			$('#sampleRejectionReason,#rejectionDate').addClass('isRequired');
			$('#sampleTestedDateTime,#result,.test-name-table-input').removeClass('isRequired');
			$('#result').prop('disabled', true);
			$('#sampleRejectionReason').prop('disabled', false);
		} else if (val == 'no') {
            $('#rejectionDate').val('');
            $('.show-rejection').hide();
            $('.test-name-table-input').prop('disabled',false);
            $('.test-name-table').removeClass('disabled');
			$('#sampleRejectionReason,#rejectionDate').removeClass('isRequired');
			$('#sampleTestedDateTime,#result,.test-name-table-input').addClass('isRequired');
			$('#result').prop('disabled', false);
			$('#sampleRejectionReason').prop('disabled', true);
		}
        <?php if(isset($arr['covid19_positive_confirmatory_tests_required_by_central_lab']) && $arr['covid19_positive_confirmatory_tests_required_by_central_lab'] == 'yes'){ ?>
            checkPostive();
        <?php }?>
	}


    function calculateAgeInYears() {
        var dateOfBirth = moment($("#patientDob").val(), "DD-MMM-YYYY");
        $("#patientAge").val(moment().diff(dateOfBirth, 'years'));
    }
</script>
<?php include_once(APPLICATION_PATH . '/footer.php');