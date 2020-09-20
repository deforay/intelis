<?php
ob_start();
$title = "Enter EID Result";
#require_once('../../startup.php');
include_once(APPLICATION_PATH . '/header.php');

$id = base64_decode($_GET['id']);


$facilitiesDb = new \Vlsm\Models\Facilities($db);

$healthFacilities = $facilitiesDb->getHealthFacilities('eid');
$testingLabs = $facilitiesDb->getTestingLabs('eid');

//get import config
$importQuery = "SELECT * FROM import_config WHERE status = 'active'";
$importResult = $db->query($importQuery);

$userQuery = "SELECT * FROM user_details where status='active'";
$userResult = $db->rawQuery($userQuery);

//sample rejection reason
$rejectionQuery = "SELECT * FROM r_eid_sample_rejection_reasons where rejection_reason_status = 'active'";
$rejectionResult = $db->rawQuery($rejectionQuery);
//rejection type
$rejectionTypeQuery = "SELECT DISTINCT rejection_type FROM r_eid_sample_rejection_reasons WHERE rejection_reason_status ='active'";
$rejectionTypeResult = $db->rawQuery($rejectionTypeQuery);
//sample status
$statusQuery = "SELECT * FROM r_sample_status where status = 'active' AND status_id NOT IN(9,8,6)";
$statusResult = $db->rawQuery($statusQuery);

$pdQuery = "SELECT * from province_details";
$pdResult = $db->query($pdQuery);

$sQuery = "SELECT * from r_eid_sample_type where status='active'";
$sResult = $db->query($sQuery);

//get vl test reason list
$vlTestReasonQuery = "SELECT * from r_eid_test_reasons where test_reason_status = 'active'";
$vlTestReasonResult = $db->query($vlTestReasonQuery);

$id = base64_decode($_GET['id']);
$eidQuery = "SELECT * from eid_form where eid_id=$id";
$eidInfo = $db->rawQueryOne($eidQuery);


$disable = "disabled = 'disabled'";


?>
<style>
  .disabledForm {
    background: #efefef;
  }

  :disabled,
  .disabledForm .input-group-addon {
    background: none !important;
    border: none !important;
  }

  .ui_tpicker_second_label {
    display: none !important;
  }

  .ui_tpicker_second_slider {
    display: none !important;
  }

  .ui_tpicker_millisec_label {
    display: none !important;
  }

  .ui_tpicker_millisec_slider {
    display: none !important;
  }

  .ui_tpicker_microsec_label {
    display: none !important;
  }

  .ui_tpicker_microsec_slider {
    display: none !important;
  }

  .ui_tpicker_timezone_label {
    display: none !important;
  }

  .ui_tpicker_timezone {
    display: none !important;
  }

  .ui_tpicker_time_input {
    width: 100%;
  }
</style>
<?php


$fileArray = array(
  1 => 'forms/update-southsudan-result.php',
  2 => 'forms/update-zimbabwe-result.php',
  3 => 'forms/update-drc-result.php',
  4 => 'forms/update-zambia-result.php',
  5 => 'forms/update-png-result.php',
  6 => 'forms/update-who-result.php',
  7 => 'forms/update-rwanda-result.php',
  8 => 'forms/update-angola-result.php',
);

require_once($fileArray[$arr['vl_form']]);


?>

<script>
  $(document).ready(function() {
    $('.date').datepicker({
      changeMonth: true,
      changeYear: true,
      dateFormat: 'dd-M-yy',
      timeFormat: "hh:mm TT",
      maxDate: "Today",
      yearRange: <?php echo (date('Y') - 100); ?> + ":" + "<?php echo (date('Y')) ?>"
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
    //$('.date').mask('99-aaa-9999');
    //$('.dateTime').mask('99-aaa-9999 99:99');
  });
</script>


<?php
include(APPLICATION_PATH . '/footer.php');
?>