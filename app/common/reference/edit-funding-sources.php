<?php

$title = "Implementation Partners";

require_once APPLICATION_PATH . '/header.php';

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = $GLOBALS['request'];
$_GET = $request->getQueryParams();
$id = (isset($_GET['id'])) ? base64_decode($_GET['id']) : null;

if (!isset($id) || $id == "") {
    $_SESSION['alertMsg'] = "Something went wrong in Implementation Partners edit page";
    header("Location:province-details.php");
}
$query = "SELECT * from r_funding_sources where funding_source_id = $id";
$partnerInfo = $db->query($query);
?>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <h1><em class="fa-solid fa-gears"></em> Edit Implementation Partners</h1>
        <ol class="breadcrumb">
            <li><a href="/"><em class="fa-solid fa-chart-pie"></em> Home</a></li>
            <li class="active">Implementation Partners</li>
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
                <form class="form-horizontal" method='post' name='fundingSrcNameForm' id='fundingSrcNameForm' autocomplete="off" enctype="multipart/form-data" action="save-funding-sources-helper.php">
                    <div class="box-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="fundingSrcName" class="col-lg-4 control-label">Funding Source Name <span class="mandatory">*</span></label>
                                    <div class="col-lg-7">
                                        <input type="text" value="<?php echo $partnerInfo[0]['funding_source_name']; ?>" class="form-control isRequired" id="fundingSrcName" name="fundingSrcName" placeholder="Funding Source" title="Please enter Funding Source" onblur="checkNameValidation('r_funding_sources','funding_source_name',this,'<?php echo "funding_source_id##" . $id; ?>','The Funding Source that you entered already exists.Enter another Funding Source',null)" />
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="fundingStatus" class="col-lg-4 control-label">Funding Source Status<span class="mandatory">*</span></label>
                                    <div class="col-lg-7">
                                        <select class="form-control isRequired" id="fundingStatus" name="fundingStatus" title="Please select Funding Source status">
                                            <option value="">--Select--</option>
                                            <option value="active" <?php echo ($partnerInfo[0]['funding_source_status'] == "active" ? 'selected' : ''); ?>>Active</option>
                                            <option value="inactive" <?php echo ($partnerInfo[0]['funding_source_status'] == "inactive" ? 'selected' : ''); ?>>Inactive</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <br>
                    </div>
                    <!-- /.box-body -->
                    <div class="box-footer">
                        <input type="hidden" name="fundingId" name="fundingId" value="<?php echo htmlspecialchars($_GET['id']); ?>">
                        <a class="btn btn-primary" href="javascript:void(0);" onclick="validateNow();return false;">Submit</a>
                        <a href="funding-sources.php" class="btn btn-default"> Cancel</a>
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
            formId: 'fundingSrcNameForm'
        });

        if (flag) {
            $.blockUI();
            document.getElementById('fundingSrcNameForm').submit();
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
