<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;


$title = "Bulk Import Test Requests";

require_once(APPLICATION_PATH . '/header.php');
/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$countryFormId = $general->getGlobalConfig('vl_form');
$fileName = WEB_ROOT. DIRECTORY_SEPARATOR . 'files' . DIRECTORY_SEPARATOR . 'covid-19' . DIRECTORY_SEPARATOR . $countryFormId . DIRECTORY_SEPARATOR . 'Covid19_Bulk_Import_Excel_Format.xlsx';
?>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
  <!-- Content Header (Page header) -->
  <section class="content-header">
    <h1><em class="fa-solid fa-pen-to-square"></em> <?php echo _("Import Test Requests In Bulk");?></h1>
    <ol class="breadcrumb">
      <li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?php echo _("Home");?></a></li>
      <li class="active"><?php echo _("Import Test Requests In Bulk");?></li>
    </ol>
  </section>

  <!-- Main content -->
  <section class="content">
    
    <div class="box box-default">
      <div class="box-header with-border">
        <div class="pull-right" style="font-size:15px;"><span class="mandatory">*</span> indicates required field &nbsp;</div>
      </div>
      <!-- /.box-header -->
      <div class="box-body">
        <!-- form start -->
        <div style="font-size:1.1em;padding:1em;">
          <p>Please note that the columns marked in <span class="mandatory">red</span> are mandatory. </p>
        </div>
        <form class="form-horizontal" method='post' name='addImportRequestForm' id='addImportRequestForm' enctype="multipart/form-data" autocomplete="off" action="covid-19-bulk-import-request-helper.php">
          <div class="box-body">
            <div class="wizard_content">
              <div class="row setup-content step" id="step-1" style="display:block;">
                <div class="col-xs-12">
                  <div class="col-md-12" id="stepOneForm">
                    <div class="row">
                      <div class="col-md-6">
                        <div class="form-group">
                          <label class="col-lg-4 control-label" for="requestFile">Upload File <span class="mandatory">*</span></label>
                          <div class="col-lg-7">
                            <input type="file" class="isRequired" name="requestFile" id="requestFile" title="Please select a file to upload">
                            (Upload xls, xlsx, csv format)
                          </div>
                        </div>
                      </div>
                      <?php if(file_exists($fileName)) {?>
                      <div class="col-md-6">
                        <a href="<?php echo "/files/covid-19/{$countryFormId}/Covid19_Bulk_Import_Excel_Format.xlsx"; ?>" target="_blank"  rel="noopener" class="btn btn-sm btn-primary" download><em class="fa-solid fa-download"></em> Download Example Format</a>
                      </div>
                      <?php } ?>
                    </div>
                  </div>
                </div>
              </div>
              <div class="row setup-content step" id="step-2">
                <div class="col-xs-12">
                  <div class="col-md-12" id="stepTwoForm">
                    <div class="row form-group">
                      <div class="box-footer">
                        <a class="btn btn-primary" href="javascript:void(0);" onclick="validateNow();return false;">Submit</a>
                        <a href="/covid-19/requests/covid-19-requests.php" class="btn btn-default"> Cancel</a>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <!-- /.box-body -->

          <!-- /.box-footer -->
        </form>
        <!-- /.row -->
      </div>

    </div>
    <!-- /.box -->

  </section>
  <!-- /.content -->
</div>

<script type="text/javascript">
  function validateNow() {
    flag = deforayValidator.init({
      formId: 'addImportRequestForm'
    });
    if (flag) {
      document.getElementById('addImportRequestForm').submit();
    }
  }
</script>
<?php
require_once(APPLICATION_PATH . '/footer.php');
