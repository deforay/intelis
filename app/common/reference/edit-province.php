<?php

$title = "Province";

require_once APPLICATION_PATH . '/header.php';

// Sanitize values before using them below
$_GET = array_map('htmlspecialchars', $_GET);
$id = (isset($_GET['id'])) ? base64_decode($_GET['id']) : null;

if (!isset($id) || $id == "") {
    $_SESSION['alertMsg'] = "Something went wrong in province edit page";
    header("Location:province-details.php");
}
$query = "SELECT * from province_details where province_id = $id";
$provinceInfo = $db->query($query);
?>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <h1><em class="fa-solid fa-gears"></em> Edit Province</h1>
        <ol class="breadcrumb">
            <li><a href="/"><em class="fa-solid fa-chart-pie"></em> Home</a></li>
            <li class="active">Edit Province</li>
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
                <form class="form-horizontal" method='post' name='provinceDetails' id='provinceDetails' autocomplete="off" enctype="multipart/form-data" action="save-province-helper.php">
                    <div class="box-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="provinceName" class="col-lg-4 control-label">Province Name <span class="mandatory">*</span></label>
                                    <div class="col-lg-7">
                                        <input type="text" value="<?php echo $provinceInfo[0]['province_name']; ?>" class="form-control isRequired" id="provinceName" name="provinceName" placeholder="Province Name" title="Please enter Province name" onblur="checkNameValidation('province_details','province_name',this,'<?php echo "province_id##" . $id; ?>','The province name that you entered already exists.Enter another name',null)" />
                                        <input type="hidden" value="<?php echo $provinceInfo[0]['province_name']; ?>" class="form-control" id="provinceNameOld" name="provinceNameOld">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="provinceCode" class="col-lg-4 control-label">Province Code <span class="mandatory">*</span></label>
                                    <div class="col-lg-7">
                                        <input type="text" value="<?php echo $provinceInfo[0]['province_code']; ?>" class="form-control isRequired" id="provinceCode" name="provinceCode" placeholder="Province code" title="Please enter Province code" onblur="checkNameValidation('province_details','province_code',this,'<?php echo "province_id##" . $id; ?>','The province code that you entered already exists.Enter another code',null)" />
                                    </div>
                                </div>
                            </div>
                        </div>
                        <br>
                    </div>
                    <!-- /.box-body -->
                    <div class="box-footer">
                        <input type="hidden" name="provinceId" name="provinceId" value="<?php echo htmlspecialchars($_GET['id']); ?>">
                        <a class="btn btn-primary" href="javascript:void(0);" onclick="validateNow();return false;">Submit</a>
                        <a href="province-details.php" class="btn btn-default"> Cancel</a>
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
            formId: 'provinceDetails'
        });

        if (flag) {
            $.blockUI();
            document.getElementById('provinceDetails').submit();
        }
    }

    function checkNameValidation(tableName, fieldName, obj, fnct, alrt, callback) {
        var removeDots = obj.value.replace(/\./g, "");
        var removeDots = removeDots.replace(/\,/g, "");
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
