<?php

use App\Registries\AppRegistry;

use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);


/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$title = _translate("Geographical Divisions");

require_once APPLICATION_PATH . '/header.php';

// Sanitized values from $request object
/** @var Psr\Http\Message\ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_GET = _sanitizeInput($request->getQueryParams());
$id = isset($_GET['id']) ? base64_decode((string) $_GET['id']) : null;

if (!isset($id) || $id == "") {
    $_SESSION['alertMsg'] = _translate("Cannot edit this geographical division. Please try again.");
    header("Location:geographical-divisions-details.php");
}

$geoQuery = "SELECT geo_id, geo_name FROM geographical_divisions WHERE geo_status ='active' AND geo_parent = 0 ORDER BY geo_name";
$geoParentInfo = $db->query($geoQuery);
$geoArray = [];
foreach ($geoParentInfo as $type) {
    $geoArray[$type['geo_id']] = ($type['geo_name']);
}
$query = "SELECT * FROM geographical_divisions WHERE geo_id = ?";
$geoInfo = $db->rawQueryOne($query, [$id]);

$childDistricts = [];
$provinceFacilities = [];
$districtFacilities = [];

$isProvince = isset($geoInfo['geo_parent']) ? ((int) $geoInfo['geo_parent'] === 0) : false;

$provinceMoveOptions = [];
$districtMoveOptions = [];

if ($isProvince) {
    $childDistricts = $db->rawQuery(
        "SELECT geo_id, geo_name, geo_code, geo_status
            FROM geographical_divisions
            WHERE geo_parent = ?
            ORDER BY geo_name",
        [$id]
    );

    $provinceFacilities = $db->rawQuery(
        "SELECT facility_id, facility_name, facility_code, status
            FROM facility_details
            WHERE facility_state_id = ?
            ORDER BY facility_name",
        [$id]
    );

    $provinceMoveRecords = $db->rawQuery(
        "SELECT geo_id, geo_name
            FROM geographical_divisions
            WHERE geo_parent = 0
            AND geo_status = 'active'
            AND geo_id != ?
            ORDER BY geo_name",
        [$id]
    );
    foreach ($provinceMoveRecords as $row) {
        $provinceMoveOptions[$row['geo_id']] = $row['geo_name'];
    }
} else {
    $districtFacilities = $db->rawQuery(
        "SELECT facility_id, facility_name, facility_code, status
            FROM facility_details
            WHERE facility_district_id = ?
            ORDER BY facility_name",
        [$id]
    );

    $districtMoveRecords = $db->rawQuery(
        "SELECT d.geo_id,
                d.geo_name,
                CAST(NULLIF(d.geo_parent, '') AS UNSIGNED) AS province_id,
                p.geo_name AS province_name
            FROM geographical_divisions d
            LEFT JOIN geographical_divisions p ON p.geo_id = CAST(NULLIF(d.geo_parent, '') AS UNSIGNED)
            WHERE d.geo_parent != 0
            AND d.geo_status = 'active'
            AND d.geo_id != ?
            ORDER BY p.geo_name, d.geo_name",
        [$id]
    );
    foreach ($districtMoveRecords as $row) {
        $provinceName = $row['province_name'] ?? _translate("Unassigned Province");
        $districtMoveOptions[$provinceName][] = $row;
    }
}

$districtMoveOptionsHtml = '';
if (!$isProvince) {
    $districtMoveOptionsHtml .= "<option value=''>" . _translate("-- Select --") . "</option>";
    foreach ($districtMoveOptions as $provinceName => $districts) {
        $districtMoveOptionsHtml .= "<optgroup label='" . htmlspecialchars((string) $provinceName) . "'>";
        foreach ($districts as $district) {
            $districtMoveOptionsHtml .= "<option value='" . htmlspecialchars((string) $district['geo_id']) . "'>" .
                htmlspecialchars((string) $district['geo_name']) . "</option>";
        }
        $districtMoveOptionsHtml .= "</optgroup>";
    }
}
?>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <h1><em class="fa-solid fa-gears"></em> <?php echo _translate("Edit Geographical Divisions"); ?></h1>
        <ol class="breadcrumb">
            <li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?php echo _translate("Home"); ?></a></li>
            <li class="active"><?php echo _translate("Edit Geographical Divisions"); ?></li>
        </ol>
    </section>

    <!-- Main content -->
    <section class="content">

        <div class="box box-default">
            <div class="box-header with-border">
                <div class="pull-right" style="font-size:15px;"><span class="mandatory">*</span>
                    <?php echo _translate("indicates required fields"); ?> &nbsp;</div>
            </div>
            <form class="form-horizontal" method='post' name='geographicalDivisionsDetails'
                id='geographicalDivisionsDetails' autocomplete="off" enctype="multipart/form-data"
                action="save-geographical-divisions-helper.php">
                <!-- /.box-header -->
                <div class="box-body">
                    <!-- form start -->
                    <div class="box-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="geoName"
                                        class="col-lg-4 control-label"><?php echo _translate("Geographical Division Name"); ?>
                                        <span class="mandatory">*</span></label>
                                    <div class="col-lg-7">
                                        <input type="text" class="form-control isRequired"
                                            value="<?php echo $geoInfo['geo_name']; ?>" id="geoName" name="geoName"
                                            placeholder="<?php echo _translate('Geo Division Name'); ?>"
                                            title="<?php echo _translate('Please enter Geographical Division name'); ?>"
                                            onblur="checkNameValidation('geographical_divisions','geo_name',this,'<?php echo 'geo_id##' . htmlspecialchars((string) $id); ?>','<?php echo _translate("The Geographical Division name that you entered already exists. Please enter another name"); ?>',null)" />
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="geoCode"
                                        class="col-lg-4 control-label"><?php echo _translate("Geographical Division Code"); ?>
                                        <span class="mandatory">*</span></label>
                                    <div class="col-lg-7">
                                        <input type="text" class="form-control isRequired"
                                            value="<?php echo $geoInfo['geo_code']; ?>" id="geoCode" name="geoCode"
                                            placeholder="<?php echo _translate('Geographical Divisions code'); ?>"
                                            title="<?php echo _translate('Please enter Geographical Division code'); ?>"
                                            onblur="checkNameValidation('geographical_divisions','geo_code',this,'<?php echo 'geo_id##' . htmlspecialchars((string) $id); ?>','<?php echo _translate("The Geographical Division code that you entered already exists. Please enter another code"); ?>',null)" />
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="geoParent"
                                        class="col-lg-4 control-label"><?php echo _translate("Parent Geographical Division"); ?></label>
                                    <div class="col-lg-7">
                                        <select class="form-control select2-element" id="geoParent" name="geoParent"
                                            placeholder="<?php echo _translate('Parent Division'); ?>"
                                            title="<?php echo _translate('Please select Parent division'); ?>">
                                            <?= $general->generateSelectOptions($geoArray, $geoInfo['geo_parent'], _translate("-- Select --")); ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="geoStatus"
                                        class="col-lg-4 control-label"><?php echo _translate("Status"); ?><span
                                            class="mandatory">*</span></label>
                                    <div class="col-lg-7">
                                        <select class="form-control isRequired" id="geoStatus" name="geoStatus"
                                            title="<?php echo _translate('Please select status'); ?>">
                                            <option value=""><?php echo _translate("--Select--"); ?></option>
                                            <option value="active" <?php echo ($geoInfo['geo_status'] == "active" ? 'selected' : ''); ?>><?php echo _translate("Active"); ?></option>
                                            <option value="inactive" <?php echo ($geoInfo['geo_status'] == "inactive" ? 'selected' : ''); ?>><?php echo _translate("Inactive"); ?></option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php if ($isProvince) { ?>
                            <div class="row" id="provinceMoveWrapper" style="display:none;">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="moveToProvince"
                                            class="col-lg-4 control-label"><?php echo _translate("Move Active Districts and Facilities To"); ?></label>
                                        <div class="col-lg-7">
                                            <select class="form-control select2-element" id="moveToProvince"
                                                name="moveToProvince"
                                                title="<?php echo _translate('Select a province to move active records'); ?>">
                                                <?= $general->generateSelectOptions($provinceMoveOptions, null, _translate("-- Select --")); ?>
                                            </select>
                                            <p class="help-block">
                                                <?php echo _translate("Choose an active province to receive all active districts and facilities before deactivating this province."); ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php } else { ?>
                            <div class="row" id="districtMoveWrapper" style="display:none;">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="moveToDistrict"
                                            class="col-lg-4 control-label"><?php echo _translate("Move Active Facilities To"); ?></label>
                                        <div class="col-lg-7">
                                            <select class="form-control select2-element" id="moveToDistrict"
                                                name="moveToDistrict"
                                                title="<?php echo _translate('Select a district to move active facilities'); ?>">
                                                <?php echo $districtMoveOptionsHtml; ?>
                                            </select>
                                            <p class="help-block">
                                                <?php echo _translate("Choose an active district to receive all active facilities before deactivating this district."); ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                    <div class="box-footer">
                        <input type="hidden" name="geoId" name="geoId" value="<?php echo $_GET['id']; ?>">
                        <a class="btn btn-primary" href="javascript:void(0);"
                            onclick="validateNow();return false;"><?php echo _translate("Submit"); ?></a>
                        <a href="geographical-divisions-details.php" class="btn btn-default">
                            <?php echo _translate("Cancel"); ?></a>
                    </div>
                </div>
                <!-- /.box-footer -->
            </form>
            <!-- /.row -->
        </div>
        <?php if ($isProvince) { ?>
            <div class="box box-default">
                <div class="box-header with-border">
                    <h3 class="box-title"><?php echo _translate("Districts/Counties under this Province"); ?></h3>
                </div>
                <div class="box-body table-responsive">
                    <?php if (!empty($childDistricts)) { ?>
                        <table class="table table-bordered table-striped" aria-hidden="true">
                            <thead>
                                <tr>
                                    <th><?php echo _translate("Name"); ?></th>
                                    <th><?php echo _translate("Code"); ?></th>
                                    <th><?php echo _translate("Status"); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($childDistricts as $district) { ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars((string) $district['geo_name']); ?></td>
                                        <td><?php echo htmlspecialchars((string) $district['geo_code']); ?></td>
                                        <td><?php echo htmlspecialchars((string) ucfirst((string) $district['geo_status'])); ?></td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    <?php } else { ?>
                        <p class="text-center text-muted">
                            <?php echo _translate("No districts/counties found for this province."); ?>
                        </p>
                    <?php } ?>
                </div>
            </div>
            <div class="box box-default">
                <div class="box-header with-border">
                    <h3 class="box-title"><?php echo _translate("Facilities mapped to this Province"); ?></h3>
                </div>
                <div class="box-body table-responsive">
                    <?php if (!empty($provinceFacilities)) { ?>
                        <table class="table table-bordered table-striped" aria-hidden="true">
                            <thead>
                                <tr>
                                    <th><?php echo _translate("Facility Name"); ?></th>
                                    <th><?php echo _translate("Facility Code"); ?></th>
                                    <th><?php echo _translate("Status"); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($provinceFacilities as $facility) { ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars((string) $facility['facility_name']); ?></td>
                                        <td><?php echo htmlspecialchars((string) $facility['facility_code']); ?></td>
                                        <td><?php echo htmlspecialchars((string) ucfirst((string) $facility['status'])); ?></td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    <?php } else { ?>
                        <p class="text-center text-muted"><?php echo _translate("No facilities found for this province."); ?>
                        </p>
                    <?php } ?>
                </div>
            </div>
        <?php } else { ?>
            <div class="box box-default">
                <div class="box-header with-border">
                    <h3 class="box-title"><?php echo _translate("Facilities mapped to this District/County"); ?></h3>
                </div>
                <div class="box-body table-responsive">
                    <?php if (!empty($districtFacilities)) { ?>
                        <table class="table table-bordered table-striped" aria-hidden="true">
                            <thead>
                                <tr>
                                    <th><?php echo _translate("Facility Name"); ?></th>
                                    <th><?php echo _translate("Facility Code"); ?></th>
                                    <th><?php echo _translate("Status"); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($districtFacilities as $facility) { ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars((string) $facility['facility_name']); ?></td>
                                        <td><?php echo htmlspecialchars((string) $facility['facility_code']); ?></td>
                                        <td><?php echo htmlspecialchars((string) ucfirst((string) $facility['status'])); ?></td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    <?php } else { ?>
                        <p class="text-center text-muted">
                            <?php echo _translate("No facilities found for this district/county."); ?>
                        </p>
                    <?php } ?>
                </div>
            </div>
        <?php } ?>
</div>
<!-- /.box -->

</section>
<!-- /.content -->
</div>

<script type="text/javascript">
    $(document).ready(function () {
        $('#geoParent').select2({
            width: '100%',
            allowClear: true,
            placeholder: "<?php echo _translate('-- Select --'); ?>"
        });
    });

    var isProvinceDivision = <?php echo $isProvince ? 'true' : 'false'; ?>;
    var moveProvinceRequiredMsg = "<?php echo _translate("Please select an active province to move existing districts and facilities."); ?>";
    var moveDistrictRequiredMsg = "<?php echo _translate("Please select an active district to move existing facilities."); ?>";
    var selectPlaceholderText = "<?php echo _translate("-- Select --"); ?>";

    function validateNow() {

        flag = deforayValidator.init({
            formId: 'geographicalDivisionsDetails'
        });

        if (flag) {
            var selectedStatus = $('#geoStatus').val();
            if (selectedStatus === 'inactive') {
                if (isProvinceDivision && $('#moveToProvince').length && !$('#moveToProvince').val()) {
                    alert(moveProvinceRequiredMsg);
                    return false;
                }
                if (!isProvinceDivision && $('#moveToDistrict').length && !$('#moveToDistrict').val()) {
                    alert(moveDistrictRequiredMsg);
                    return false;
                }
            }
            $.blockUI();
            document.getElementById('geographicalDivisionsDetails').submit();
        }
    }

    function toggleMoveSections() {
        var selectedStatus = $('#geoStatus').val();
        var $provinceWrapper = $('#provinceMoveWrapper');
        var $districtWrapper = $('#districtMoveWrapper');

        if (selectedStatus === 'inactive') {
            if (isProvinceDivision) {
                if ($provinceWrapper.length) {
                    $provinceWrapper.show();
                }
                if ($districtWrapper.length) {
                    $districtWrapper.hide();
                }
            } else {
                if ($districtWrapper.length) {
                    $districtWrapper.show();
                }
                if ($provinceWrapper.length) {
                    $provinceWrapper.hide();
                }
            }
        } else {
            if ($provinceWrapper.length) {
                $provinceWrapper.hide();
            }
            if ($districtWrapper.length) {
                $districtWrapper.hide();
            }
            if ($('#moveToProvince').length) {
                $('#moveToProvince').val('');
            }
            if ($('#moveToDistrict').length) {
                $('#moveToDistrict').val('');
            }
        }
    }

    $(document).ready(function () {
        if ($('.select2-element').length > 0 && $.isFunction($.fn.select2)) {
            $('.select2-element').select2({
                width: '100%',
                placeholder: selectPlaceholderText,
                allowClear: true
            });
        }
        toggleMoveSections();
        $('#geoStatus').on('change', toggleMoveSections);
    });

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
            function (data) {
                if (data === '1') {
                    alert(alrt);
                    document.getElementById(obj.id).value = "";
                }
            });
    }
</script>

<?php
require_once APPLICATION_PATH . '/footer.php';
