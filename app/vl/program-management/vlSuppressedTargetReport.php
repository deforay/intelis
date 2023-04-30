<?php

use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;

$title = "VL Quarterly Monitoring Report";

require_once(APPLICATION_PATH . '/header.php');

/** @var MysqliDb $db */
/** @var CommonService $general */
$general = \App\Registries\ContainerRegistry::get(CommonService::class);

$startYear = date("Y", strtotime("-2 month"));
$startMonth = date('m', strtotime('-2 month'));
$endYear = date('Y');
$endMonth = date('m');

$startDate = date('Y-m', strtotime('-2 month'));
$endDate = date('Y-m');

$tsQuery = "SELECT * FROM r_sample_status";
$tsResult = $db->rawQuery($tsQuery);
//config  query

//$arr = $general->getGlobalConfig();

$sQuery = "SELECT * FROM r_vl_sample_type where status='active'";
$sResult = $db->rawQuery($sQuery);


/** @var FacilitiesService $facilitiesService */
$facilitiesService = \App\Registries\ContainerRegistry::get(FacilitiesService::class);


// $healthFacilites = $facilitiesService->getHealthFacilities('vl');
// $facilitiesDropdown = $general->generateSelectOptions($healthFacilites, null, "-- Select --");
$testingLabs = $facilitiesService->getTestingLabs('vl');
$testingLabsDropdown = $general->generateSelectOptions($testingLabs, null, "-- Select --");



?>
<link rel="stylesheet" href="/assets/css/jquery.multiselect.css" type="text/css" />
<link href="/assets/css/datepicker.min.css" rel="stylesheet">


<script src="/assets/js/bootstrap-datepicker.min.js"></script>

<style>
  .bluebox,
  .dashboard-stat2 {
    border: 1px solid #3598DC;
  }

  .mrp-monthdisplay {
    display: inline-block !important;
    border-radius: 0px 5px 5px 0px;

    cursor: pointer;
  }

  .mrp-lowerMonth,
  .mrp-upperMonth {
    color: #40667A;
    font-weight: bold;
    font-size: 11px;
    text-transform: uppercase;
  }

  .mrp-to {
    color: #aaa;
    margin-right: 0px;
    margin-left: 0px;
    font-size: 11px;
    text-transform: uppercase;
    /* background-color: #eee; */
    padding: 5px 3px 5px 3px;
  }

  .mpr-calendar {
    display: inline-block;
    padding: 3px 5px;
    border-right: solid #999 1px;
  }

  .mpr-calendar::last-child {
    border-right: none;
  }

  .mpr-month {
    padding: 20px;
    text-transform: uppercase;
    font-size: 12px;
  }

  .mpr-calendar h5 {
    width: 100%;
    text-align: center;
    font-weight: bold;
    font-size: 18px
  }

  .mpr-selected {}

  .mpr-month:hover {
    border-radius: 5px;
    box-shadow: 0 0 0 1px #ddd inset;
    cursor: pointer;
  }

  .mpr-selected.mpr-month:hover {
    border-radius: 0px;
    box-shadow: none;
  }

  .mpr-calendarholder .col-xs-6 {
    max-width: 250px;
    min-width: 250px;
  }

  .mpr-calendarholder .col-xs-1 {
    max-width: 150px;
    min-width: 150px;
  }

  .mpr-calendarholder .btn-info {
    background-color: #40667A;
    border-color: #406670;
    width: 100%;
    margin-bottom: 10px;
    text-transform: uppercase;
    font-size: 10px;
    padding: 10px 0px;
  }

  .mpr-quickset {
    color: #666;
    text-transform: uppercase;
    text-align: center;
  }

  .mpr-yeardown,
  .mpr-yearup {
    margin-left: 5px;
    cursor: pointer;
    color: #666;
  }

  .mpr-yeardown {
    float: left;
  }

  .mpr-yearup {
    float: right;
  }

  .mpr-yeardown:hover,
  .mpr-yearup:hover {
    color: #40667A;
  }

  .mpr-calendar:first .mpr-selected:first {
    background-color: #40667A;
  }

  .mpr-calendar:last .mpr-selected:last {
    background-color: #40667A;
  }

  .popover {
    max-width: 1920px !important;
  }
</style>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
  <!-- Content Header (Page header) -->
  <section class="content-header">
    <h1><em class="fa-solid fa-book"></em> Viral Load Suppression - Monthly Target Report
      <!--<ol class="breadcrumb">-->
      <!--  <li><a href="/"><em class="fa-solid fa-chart-pie"></em> Home</a></li>-->
      <!--  <li class="active">Export Result</li>-->
      <!--</ol>-->

    </h1>
  </section>
  <!-- Main content -->
  <section class="content">
    <div class="row">
      <div class="col-xs-12">
        <div class="box">
          <table class="table" aria-hidden="true" style="margin-left:1%;margin-top:20px;width:98%;">
            <tr>
              <td><strong>Sample Test Date&nbsp;:</strong></td>
              <td>
                <input type="text" id="sampleTestDate" name="sampleTestDate" class="form-control" placeholder="Select Sample Test Date" readonly style="background:#fff;" />
              </td>
              <td><strong>Lab Name :</strong></td>
              <td style="width: 30%;">
                <select class="" id="facilityName" name="facilityName" title="Please select facility name" multiple>
                  <?= $testingLabsDropdown; ?>
                </select>
              </td>

            </tr>
            <tr class="testMon">
              <td><strong>Month :</strong></td>
              <td style="width: 30%;">
                <!-- <select class="form-control" id="month" name="month" title="Please select month" > -->
                <input type="text" class="form-control" name="month" id="month" />
                </select>
              </td>
            </tr>

            <tr>
              <td colspan="4">&nbsp;<input type="button" onclick="loadVlRequestData();" value="Search" class="btn btn-success btn-sm">
                &nbsp;<button class="btn btn-danger btn-sm" onclick="document.location.href = document.location"><span>Reset</span></button>

                <!-- &nbsp;<button class="btn btn-info" type="button" onclick="exportInexcel()">Export to excel</button> -->
              </td>
            </tr>
          </table>
          <!-- /.box-header -->
          <div class="box-body">

            <div class="row">
              <div id="vlSuppressChartDiv"></div>
            </div>
          </div>
          <!-- /.box-body -->
        </div>
        <!-- /.box -->
      </div>
      <!-- /.col -->
    </div>
    <!-- /.row -->
  </section>
  <!-- /.content -->
</div>
<script type="text/javascript" src="/assets/js/jquery.multiselect.js"></script>
<script src="/assets/js/moment.min.js"></script>
<script type="text/javascript" src="/assets/plugins/daterangepicker/daterangepicker.js"></script>
<script src="/assets/js/highcharts.js"></script>
<script src="/assets/js/exporting.js"></script>
<script src="/assets/js/accessibility.js"></script>
<script type="text/javascript">
  var startDate = "";
  var endDate = "";
  var oTable = null;
  $(document).ready(function() {
    $("#facilityName").multipleSelect({
      placeholder: 'Select facility name',
      width: '100%'
    });
    $('#sampleTestDate').daterangepicker({
        locale: {
          cancelLabel: "<?= _("Clear"); ?>",
          format: 'DD-MMM-YYYY',
          separator: ' to ',
        },
        showDropdowns: true,
        alwaysShowCalendars: false,
        startDate: moment().subtract(28, 'days'),
        endDate: moment(),
        maxDate: moment(),
        ranges: {
          'Today': [moment(), moment()],
          'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
          'Last 7 Days': [moment().subtract(6, 'days'), moment()],
          'This Month': [moment().startOf('month'), moment().endOf('month')],
          'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')],
          'Last 30 Days': [moment().subtract(29, 'days'), moment()],
          'Last 90 Days': [moment().subtract(89, 'days'), moment()],
          'Last 120 Days': [moment().subtract(119, 'days'), moment()],
          'Last 180 Days': [moment().subtract(179, 'days'), moment()],
          'Last 12 Months': [moment().subtract(12, 'month').startOf('month'), moment().endOf('month')]
        }
      },
      function(start, end) {
        startDate = start.format('YYYY-MM-DD');
        endDate = end.format('YYYY-MM-DD');
      });
    $('#sampleTestDate').val("");
    $("#month").datepicker({
      format: "mm-yyyy",
      startView: "months",
      minViewMode: "months"
    });
    loadVlRequestData();
  });

  function loadVlRequestData() {
    $.blockUI();
    var monthYear = $("#month").val();
    $.post("/vl/program-management/getSuppressedTargetReport.php", {
        // sampleCollectionDate: $("#eidSampleCollectionDate").val(),
        // batchCode: '',
        monthYear: monthYear,
        facilityName: $("#facilityName").val(),
      },
      function(data) {
        if ($.trim(data) != '') {
          $("#vlSuppressChartDiv").html(data);
        }

      });
    $.unblockUI();
  }

  var MONTHS = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];

  $(function() {
    startMonth = <?php echo $startMonth; ?>;
    startYear = <?php echo $startYear; ?>;
    endMonth = <?php echo $endMonth ?>;
    endYear = <?php echo $endYear ?>;
    fiscalMonth = 7;
    if (startMonth < 10)
      startDate = parseInt("" + startYear + '0' + startMonth + "");
    else
      startDate = parseInt("" + startYear + startMonth + "");
    if (endMonth < 10)
      endDate = parseInt("" + endYear + '0' + endMonth + "");
    else
      endDate = parseInt("" + endYear + endMonth + "");

    content = '<div class="row mpr-calendarholder">';
    calendarCount = endYear - startYear;
    if (calendarCount == 0)
      calendarCount++;
    var d = new Date();
    for (y = 0; y < 2; y++) {
      content += '<div class="col-xs-6" ><div class="mpr-calendar row" id="mpr-calendar-' + (y + 1) + '">' +
        '<h5 class="col-xs-12"><em class="mpr-yeardown fa fa-chevron-circle-left"></em><span>' + (startYear + y).toString() + '</span><em class="mpr-yearup fa fa-chevron-circle-right"></em></h5><div class="mpr-monthsContainer"><div class="mpr-MonthsWrapper">';
      for (m = 0; m < 12; m++) {
        var monthval;
        if ((m + 1) < 10)
          monthval = "0" + (m + 1);
        else
          monthval = "" + (m + 1);
        content += '<span data-month="' + monthval + '" class="col-xs-3 mpr-month">' + MONTHS[m] + '</span>';
      }
      content += '</div></div></div></div>';
    }
    content += '<div class="col-xs-1">';
    content += '<button class="btn btn-info mpr-close">Apply</button>';
    content += '</div>';
    content += '</div>';

    $(document).on('click', '.mpr-month', function(e) {
      e.stopPropagation();
      $month = $(this);
      var monthnum = $month.data('month');
      var year = $month.parents('.mpr-calendar').children('h5').children('span').html();
      if ($month.parents('#mpr-calendar-1').size() > 0) {
        //Start Date
        startDate = parseInt("" + year + monthnum);
        if (startDate > endDate) {

          if (year != parseInt(endDate / 100))
            $('.mpr-calendar:last h5 span').html(year);
          endDate = startDate;
        }
      } else {
        //End Date
        endDate = parseInt("" + year + monthnum);
        if (startDate > endDate) {
          if (year != parseInt(startDate / 100))
            $('.mpr-calendar:first h5 span').html(year);
          startDate = endDate;
        }
      }

      paintMonths();
    });


    $(document).on('click', '.mpr-yearup', function(e) {
      $('.mpr-month').css("color", "black");
      e.stopPropagation();
      var year = parseInt($(this).prev().html());
      year++;
      $(this).prev().html("" + year);
      $(this).parents('.mpr-calendar').find('.mpr-MonthsWrapper').fadeOut(175, function() {
        paintMonths();
        $(this).parents('.mpr-calendar').find('.mpr-MonthsWrapper').fadeIn(175);
      });
    });

    $(document).on('click', '.mpr-yeardown', function(e) {
      $('.mpr-month').css("color", "black");
      e.stopPropagation();
      var year = parseInt($(this).next().html());
      year--;
      $(this).next().html("" + year);
      //paintMonths();
      $(this).parents('.mpr-calendar').find('.mpr-MonthsWrapper').fadeOut(175, function() {
        paintMonths();
        $(this).parents('.mpr-calendar').find('.mpr-MonthsWrapper').fadeIn(175);
      });
    });

    $(document).on('click', '.mpr-ytd', function(e) {
      e.stopPropagation();
      var d = new Date();
      startDate = parseInt(d.getFullYear() + "01");
      var month = d.getMonth() + 1;
      if (month < 9)
        month = "0" + month;
      endDate = parseInt("" + d.getFullYear() + month);
      $('.mpr-calendar').each(function() {
        var $cal = $(this);
        var year = $('h5 span', $cal).html(d.getFullYear());
      });
      $('.mpr-calendar').find('.mpr-MonthsWrapper').fadeOut(175, function() {
        paintMonths();
        $('.mpr-calendar').find('.mpr-MonthsWrapper').fadeIn(175);
      });
    });

    $(document).on('click', '.mpr-prev-year', function(e) {
      e.stopPropagation();
      var d = new Date();
      var year = d.getFullYear() - 1;
      startDate = parseInt(year + "01");
      endDate = parseInt(year + "12");
      $('.mpr-calendar').each(function() {
        var $cal = $(this);
        $('h5 span', $cal).html(year);
      });
      $('.mpr-calendar').find('.mpr-MonthsWrapper').fadeOut(175, function() {
        paintMonths();
        $('.mpr-calendar').find('.mpr-MonthsWrapper').fadeIn(175);
      });
    });

    $(document).on('click', '.mpr-fiscal-ytd', function(e) {
      e.stopPropagation();
      var d = new Date();
      var year;
      if ((d.getMonth() + 1) < fiscalMonth)
        year = d.getFullYear() - 1;
      else
        year = d.getFullYear();
      if (fiscalMonth < 10)
        fm = "0" + fiscalMonth;
      else
        fm = fiscalMonth;
      if (d.getMonth() + 1 < 10)
        cm = "0" + (d.getMonth() + 1);
      else
        cm = (d.getMonth() + 1);
      startDate = parseInt("" + year + fm);
      endDate = parseInt("" + d.getFullYear() + cm);
      $('.mpr-calendar').each(function(i) {
        var $cal = $(this);
        if (i == 0)
          $('h5 span', $cal).html(year);
        else
          $('h5 span', $cal).html(d.getFullYear());
      });
      $('.mpr-calendar').find('.mpr-MonthsWrapper').fadeOut(175, function() {
        paintMonths();
        $('.mpr-calendar').find('.mpr-MonthsWrapper').fadeIn(175);
      });
    });

    $(document).on('click', '.mpr-prev-fiscal', function() {
      var d = new Date();
      var year;
      if ((d.getMonth() + 1) < fiscalMonth)
        year = d.getFullYear() - 2;
      else
        year = d.getFullYear() - 1;
      if (fiscalMonth < 10)
        fm = "0" + fiscalMonth;
      else
        fm = fiscalMonth;
      if (fiscalMonth - 1 < 10)
        efm = "0" + (fiscalMonth - 1);
      else
        efm = (fiscalMonth - 1);
      startDate = parseInt("" + year + fm);
      endDate = parseInt("" + (d.getFullYear() - 1) + efm);
      $('.mpr-calendar').each(function(i) {
        var $cal = $(this);
        if (i == 0)
          $('h5 span', $cal).html(year);
        else
          $('h5 span', $cal).html(d.getFullYear() - 1);
      });
      $('.mpr-calendar').find('.mpr-MonthsWrapper').fadeOut(175, function() {
        paintMonths();
        $('.mpr-calendar').find('.mpr-MonthsWrapper').fadeIn(175);
      });
    });

    var mprVisible = false;
    var mprpopover = $('.mrp-container').popover({
      container: "body",
      placement: "bottom",
      html: true,
      content: content
    }).on('show.bs.popover', function() {
      $('.popover').remove();
      var waiter = setInterval(function() {
        if ($('.popover').size() > 0) {
          clearInterval(waiter);
          setViewToCurrentYears();
          paintMonths();
        }
      }, 50);
    }).on('shown.bs.popover', function() {
      mprVisible = true;
    }).on('hidden.bs.popover', function() {
      mprVisible = false;
    });

    $(document).on('click', '.mpr-calendarholder', function(e) {
      e.preventDefault();
      e.stopPropagation();
    });
    $(document).on("click", ".mrp-container", function(e) {
      if (mprVisible) {
        e.preventDefault();
        e.stopPropagation();
        mprVisible = false;
      }
    });

    $(document).on("click", function(e) {

      if (mprVisible) {
        $('.mpr-calendarholder').parents('.popover').fadeOut(200, function() {
          $('.mpr-calendarholder').parents('.popover').remove();
          $('.mrp-container').trigger('click');
        });
        mprVisible = false;
      }
    });

    $(document).on('click', '.mpr-close', function(e) {
      //console.log(e);
      if (mprVisible) {
        $('.mpr-calendarholder').parents('.popover').fadeOut(200, function() {
          $('.mpr-calendarholder').parents('.popover').remove();
          $('.mrp-container').trigger('click');
        });
        mprVisible = false;
      }
    });

  });

  function setViewToCurrentYears() {
    var startyear = parseInt(startDate / 100);
    var endyear = parseInt(endDate / 100);
    $('.mpr-calendar h5 span').eq(0).html(startyear);
    $('.mpr-calendar h5 span').eq(1).html(endyear);
  }

  function paintMonths() {
    $('.mpr-calendar').each(function() {
      var $cal = $(this);
      var year = $('h5 span', $cal).html();
      $('.mpr-month', $cal).each(function(i) {
        if ((i + 1) > 9)
          cDate = parseInt("" + year + (i + 1));
        else
          cDate = parseInt("" + year + '0' + (i + 1));
        if (cDate >= startDate && cDate <= endDate) {
          $(this).addClass('mpr-selected');
        } else {
          $(this).removeClass('mpr-selected');
        }
      });
    });

    $('.mpr-calendar .mpr-month').css("background", "");
    //Write Text
    var startyear = parseInt(startDate / 100);
    var startmonth = parseInt(safeRound((startDate / 100 - startyear)) * 100);
    var endyear = parseInt(endDate / 100);
    var endmonth = parseInt(safeRound((endDate / 100 - endyear)) * 100);
    $('.mrp-monthdisplay .mrp-lowerMonth').html(MONTHS[startmonth - 1] + " " + startyear);
    $('.mrp-monthdisplay .mrp-upperMonth').html(MONTHS[endmonth - 1] + " " + endyear);
    //$('#mrp-lowerDate').val(startDate);
    //$('#mrp-upperDate').val(endDate);

    if (startmonth < 10)
      startmonth = '0' + startmonth;
    else
      startmonth = startmonth;

    if (endmonth < 10) {
      endmonth = '0' + endmonth;
    } else {
      endmonth = endmonth;
    }
    $('#mrp-lowerDate').val(startyear + '-' + startmonth);
    $('#mrp-upperDate').val(endyear + '-' + endmonth);


    if (startyear == parseInt($('.mpr-calendar:first h5 span').html()))
      //$('.mpr-calendar:first .mpr-selected:first').css("background","#40667A");

      $('.mpr-month').css("color", "black");
    if (endyear == parseInt($('.mpr-calendar:last h5 span').html()))
      //$('.mpr-calendar:last .mpr-selected:last').css("background","#40667A");
      $('.mpr-month').css("color", "black");
    $('.mpr-calendar:first .mpr-selected:first').css({
      "background-color": "#40667A",
      "color": "#fff"
    });
    $('.mpr-calendar:last .mpr-selected:last').css({
      "background-color": "#40667A",
      "color": "#fff"
    });

  }

  function safeRound(val) {
    return Math.round(((val) + 0.00001) * 100) / 100;
  }
</script>
<?php
require_once(APPLICATION_PATH . '/footer.php');
