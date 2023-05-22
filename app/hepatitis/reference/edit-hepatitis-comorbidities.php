<?php


require_once APPLICATION_PATH . '/header.php';
// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = $GLOBALS['request'];
$_GET = $request->getQueryParams();
$id = (isset($_GET['id'])) ? base64_decode($_GET['id']) : null;

$comorbidityQuery = "SELECT * from r_hepatitis_comorbidities where comorbidity_id=$id";
$comorbidityInfo = $db->query($comorbidityQuery);
?>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
  <!-- Content Header (Page header) -->
  <section class="content-header">
    <h1><em class="fa-solid fa-square-h"></em> Edit Hepatitis Co-morbidities</h1>
    <ol class="breadcrumb">
      <li><a href="/"><em class="fa-solid fa-chart-pie"></em> Home</a></li>
      <li class="active">Hepatitis Co-morbidities</li>
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
        <form class="form-horizontal" method='post' name='editComorbidityForm' id='editComorbidityForm' autocomplete="off" enctype="multipart/form-data" action="save-hepatitis-comorbidities-helper.php">
          <div class="box-body">
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label for="comorbidityName" class="col-lg-4 control-label">Comorbidity Name <span class="mandatory">*</span></label>
                  <div class="col-lg-7">
                    <input type="text" class="form-control isRequired" id="comorbidityName" name="comorbidityName" placeholder="Comorbidity Name" title="Please enter Comorbidity name" value="<?php echo $comorbidityInfo[0]['comorbidity_name']; ?>" onblur="checkNameValidation('r_hepatitis_comorbidities','comorbidity_name',this,'<?php echo "comorbidity_id##" . $id; ?>','The comorbidity name that you entered already exists.Enter another name',null)" />
                    <input type="hidden" class="form-control isRequired" id="comorbidityId" name="comorbidityId" value="<?php echo base64_encode($comorbidityInfo[0]['comorbidity_id']); ?>" />
                  </div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label for="comorbidityStatus" class="col-lg-4 control-label">Comorbidity Status</label>
                  <div class="col-lg-7">
                    <select class="form-control isRequired" id="comorbidityStatus" name="comorbidityStatus" placeholder="Comorbidity Status" title="Please enter Comorbidity Status">
                      <option value="active" <?php echo ($comorbidityInfo[0]['comorbidity_status'] == "active" ? 'selected' : ''); ?>>Active</option>
                      <option value="inactive" <?php echo ($comorbidityInfo[0]['comorbidity_status'] == "inactive" ? 'selected' : ''); ?>>Inactive</option>
                    </select>
                  </div>
                </div>
              </div>
            </div>

            <br>

          </div>
          <!-- /.box-body -->
          <div class="box-footer">
            <a class="btn btn-primary" href="javascript:void(0);" onclick="validateNow();return false;">Submit</a>
            <a href="hepatitis-comorbidities.php" class="btn btn-default"> Cancel</a>
          </div>
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
      formId: 'editComorbidityForm'
    });

    if (flag) {
      $.blockUI();
      document.getElementById('editComorbidityForm').submit();
    }
  }

  function checkNameValidation(tableName, fieldName, obj, fnct, alrt, callback) {
    let removeDots = obj.value.replace(/\./g, "");
    removeDots = removeDots.replace(/\,/g, "");
    //str=obj.value;
    removeDots = removeDots.replace(/\s{2,}/g, ' ');

    $.post("/includes/checkDuplicate.php", {
        tableName: tableName,
        fieldName: fieldName,
        value: removeDots.trim(),
        fnct: fnct,
        format: "html"
      },
      function(data) {
        if (data === '1') {
          alert(alrt);
          document.getElementById(obj.id).value = "";
        }
      });
  }
</script>

<?php
require_once APPLICATION_PATH . '/footer.php';
