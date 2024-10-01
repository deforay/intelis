<?php


_includeHeader();
$rejQuery = "SELECT * from r_covid19_symptoms WHERE symptom_status ='active'";
$rejInfo = $db->query($rejQuery);
?>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
  <!-- Content Header (Page header) -->
  <section class="content-header">
    <h1><em class="fa-solid fa-virus-covid"></em> <?php echo _translate("Add Covid-19 Symptoms"); ?></h1>
    <ol class="breadcrumb">
      <li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?php echo _translate("Home"); ?></a></li>
      <li class="active"><?php echo _translate("Covid-19 Symptoms"); ?></li>
    </ol>
  </section>

  <!-- Main content -->
  <section class="content">

    <div class="box box-default">
      <div class="box-header with-border">
        <div class="pull-right" style="font-size:15px;"><span class="mandatory">*</span> <?= _translate("indicates required fields"); ?> &nbsp;</div>
      </div>
      <!-- /.box-header -->
      <div class="box-body">
        <!-- form start -->
        <form class="form-horizontal" method='post' name='addSympForm' id='addSympForm' autocomplete="off" enctype="multipart/form-data" action="add-symptoms-helper.php">
          <div class="box-body">
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label for="symptomsName" class="col-lg-4 control-label"><?php echo _translate("Symptom Name"); ?> <span class="mandatory">*</span></label>
                  <div class="col-lg-7">
                    <input type="text" class="form-control isRequired" id="symptomsName" name="symptomsName" placeholder="<?php echo _translate('Symptom Name'); ?>" title="<?php echo _translate('Please enter Symptom name'); ?>" onblur='checkNameValidation("r_covid19_symptoms","symptom_name",this,null,"<?php echo _translate("The Symptom name that you entered already exists.Enter another name"); ?>",null)' />
                  </div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label for="parentSymptom" class="col-lg-4 control-label"><?php echo _translate("Parent Symptom"); ?></label>
                  <div class="col-lg-7">
                    <select class="form-control" id="parentSymptom" name="parentSymptom" placeholder="<?php echo _translate('Parent Symptom'); ?>" title="<?php echo _translate('Please enter Parent Symptom'); ?>">
                      <option value="0"> <?php echo _translate("-- Select --"); ?> </option>
                      <?php
                      foreach ($rejInfo as $type) {
                      ?>
                        <option value="<?php echo $type['symptom_id']; ?>"><?php echo ($type['symptom_name']); ?></option>
                      <?php
                      }
                      ?>
                    </select>
                  </div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label for="symptomsStatus" class="col-lg-4 control-label"><?php echo _translate("Symptom Status"); ?></label>
                  <div class="col-lg-7">
                    <select class="form-control isRequired" id="symptomsStatus" name="symptomsStatus" placeholder="<?php echo _translate('Symptom Status'); ?>" title="<?php echo _translate('Please enter Symptom Status'); ?>">
                      <option value="active"><?php echo _translate("Active"); ?></option>
                      <option value="inactive"><?php echo _translate("Inactive"); ?></option>
                    </select>
                  </div>
                </div>
              </div>
            </div>

            <br>

          </div>
          <!-- /.box-body -->
          <div class="box-footer">
            <a class="btn btn-primary" href="javascript:void(0);" onclick="validateNow();return false;"><?php echo _translate("Submit"); ?></a>
            <a href="covid19-symptoms.php" class="btn btn-default"> <?php echo _translate("Cancel"); ?></a>
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
      formId: 'addSympForm'
    });

    if (flag) {
      $.blockUI();
      document.getElementById('addSympForm').submit();
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
_includeFooter();
