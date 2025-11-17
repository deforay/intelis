<?php

use App\Registries\AppRegistry;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

$title = _translate("Merge Geographical Divisions");

require_once APPLICATION_PATH . '/header.php';

if ($general->isSTSInstance() === false) {
    $_SESSION['alertMsg'] = _translate("This feature is only available on STS instances.");
    header("Location: geographical-divisions-details.php");
    exit;
}

$provinceOptions = $db->rawQuery("SELECT geo_id, geo_name, geo_status FROM geographical_divisions WHERE geo_parent = 0 ORDER BY geo_name");

$districtOptions = $db->rawQuery(
    "SELECT d.geo_id,
            d.geo_name,
            d.geo_status,
            CAST(NULLIF(d.geo_parent, '') AS UNSIGNED) AS province_id,
            p.geo_name AS province_name
        FROM geographical_divisions d
        LEFT JOIN geographical_divisions p ON p.geo_id = CAST(NULLIF(d.geo_parent, '') AS UNSIGNED)
        WHERE d.geo_parent IS NOT NULL
        AND d.geo_parent != 0
        ORDER BY p.geo_name, d.geo_name"
);

?>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <h1><em class="fa-solid fa-layer-group"></em> <?php echo _translate("Merge Geographical Divisions"); ?></h1>
        <ol class="breadcrumb">
            <li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?php echo _translate("Home"); ?></a></li>
            <li><a href="/common/reference/geographical-divisions-details.php"><?php echo _translate("Geographical Divisions"); ?></a></li>
            <li class="active"><?php echo _translate("Merge"); ?></li>
        </ol>
    </section>
    <section class="content">
        <div class="box box-default">
            <div class="box-header with-border">
                <div class="pull-right" style="font-size:15px;">
                    <span class="mandatory">*</span> <?php echo _translate("indicates required fields"); ?>
                </div>
                <p class="text-muted" style="margin-bottom:0;">
                    <?php echo _translate("Merging moves all associated districts and facilities to the selected primary division. Non-primary divisions are marked inactive but remain in the system."); ?>
                </p>
            </div>
            <form class="form-horizontal" method="post" id="mergeGeographicalDivisions" action="merge-geographical-divisions-helper.php">
                <div class="box-body">
                    <div class="form-group">
                        <label class="col-lg-3 control-label"><?php echo _translate("Merge Type"); ?><span class="mandatory">*</span></label>
                        <div class="col-lg-7">
                            <label class="radio-inline">
                                <input type="radio" name="mergeType" value="province" checked>
                                <?php echo _translate("Provinces"); ?>
                            </label>
                            <label class="radio-inline">
                                <input type="radio" name="mergeType" value="district">
                                <?php echo _translate("Districts"); ?>
                            </label>
                        </div>
                    </div>

                    <div id="provinceMergeSection">
                        <div class="form-group">
                            <label for="selectedProvinces" class="col-lg-3 control-label"><?php echo _translate("Select Provinces to Merge"); ?><span class="mandatory">*</span></label>
                            <div class="col-lg-7">
                                <select name="selectedProvinces[]" id="selectedProvinces" class="form-control select2-element" multiple data-placeholder="<?php echo _translate("Choose one or more provinces"); ?>">
                                    <?php foreach ($provinceOptions as $province) { ?>
                                        <option value="<?php echo $province['geo_id']; ?>">
                                            <?php echo htmlspecialchars((string) $province['geo_name']); ?> (<?php echo htmlspecialchars((string) $province['geo_status']); ?>)
                                        </option>
                                    <?php } ?>
                                </select>
                                <p class="help-block"><?php echo _translate("Select at least two provinces. All districts and facilities from the non-primary provinces will move to the chosen primary province."); ?></p>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="primaryProvince" class="col-lg-3 control-label"><?php echo _translate("Primary Province (remains active)"); ?><span class="mandatory">*</span></label>
                            <div class="col-lg-7">
                                <select name="primaryProvince" id="primaryProvince" class="form-control select2-element" data-placeholder="<?php echo _translate("Select primary province"); ?>">
                                    <option value=""><?php echo _translate("-- Select --"); ?></option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div id="districtMergeSection" style="display:none;">
                        <div class="form-group">
                            <label for="selectedDistricts" class="col-lg-3 control-label"><?php echo _translate("Select Districts to Merge"); ?><span class="mandatory">*</span></label>
                            <div class="col-lg-7">
                                <select name="selectedDistricts[]" id="selectedDistricts" class="form-control select2-element" multiple data-placeholder="<?php echo _translate("Choose one or more districts"); ?>">
                                    <?php foreach ($districtOptions as $district) { ?>
                                        <option value="<?php echo $district['geo_id']; ?>" data-province="<?php echo $district['province_id']; ?>">
                                            <?php echo htmlspecialchars((string) $district['geo_name']); ?> - <?php echo htmlspecialchars((string) ($district['province_name'] ?? _translate("No Province"))); ?> (<?php echo htmlspecialchars((string) $district['geo_status']); ?>)
                                        </option>
                                    <?php } ?>
                                </select>
                                <p class="help-block"><?php echo _translate("Select at least two districts. All facilities from the non-primary districts will move to the chosen primary district."); ?></p>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="primaryDistrict" class="col-lg-3 control-label"><?php echo _translate("Primary District (remains active)"); ?><span class="mandatory">*</span></label>
                            <div class="col-lg-7">
                                <select name="primaryDistrict" id="primaryDistrict" class="form-control select2-element" data-placeholder="<?php echo _translate("Select primary district"); ?>">
                                    <option value=""><?php echo _translate("-- Select --"); ?></option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="box-footer">
                    <a href="javascript:void(0);" class="btn btn-primary" onclick="submitMergeForm();">
                        <em class="fa-solid fa-code-branch"></em> <?php echo _translate("Merge Selected Divisions"); ?>
                    </a>
                    <a href="geographical-divisions-details.php" class="btn btn-default"><?php echo _translate("Cancel"); ?></a>
                </div>
            </form>
        </div>
    </section>
</div>

<script>
    function updatePrimaryOptions(sourceSelector, targetSelector) {
        var selectedValues = $(sourceSelector).val() || [];
        var target = $(targetSelector);
        var options = ['<option value=""><?php echo _translate("-- Select --"); ?></option>'];
        selectedValues.forEach(function(value) {
            var text = $(sourceSelector + ' option[value="' + value + '"]').text();
            options.push('<option value="' + value + '">' + text + '</option>');
        });
        target.html(options.join(''));
        target.trigger('change');
    }

    function toggleMergeSections() {
        var type = $('input[name="mergeType"]:checked').val();
        if (type === 'province') {
            $('#provinceMergeSection').show();
            $('#districtMergeSection').hide();
        } else {
            $('#provinceMergeSection').hide();
            $('#districtMergeSection').show();
        }
    }

    function submitMergeForm() {
        var type = $('input[name="mergeType"]:checked').val();
        var selectedList = type === 'province' ? $('#selectedProvinces').val() : $('#selectedDistricts').val();
        var primaryValue = type === 'province' ? $('#primaryProvince').val() : $('#primaryDistrict').val();

        if (!selectedList || selectedList.length < 2) {
            alert("<?php echo _translate('Please select at least two divisions to merge.'); ?>");
            return;
        }

        if (!primaryValue) {
            alert("<?php echo _translate('Please choose the division that will remain active.'); ?>");
            return;
        }

        if (selectedList.indexOf(primaryValue) === -1) {
            alert("<?php echo _translate('Primary division must be part of the selected list.'); ?>");
            return;
        }

        if (confirm("<?php echo _translate('Are you sure you want to merge the selected divisions? This action cannot be undone.'); ?>")) {
            $('#mergeGeographicalDivisions').submit();
        }
    }

    $(document).ready(function() {
        $('.select2-element').select2({
            width: '100%',
            placeholder: function() {
                return $(this).data('placeholder');
            },
            allowClear: true
        });

        $('input[name="mergeType"]').change(toggleMergeSections);
        $('#selectedProvinces').on('change', function() {
            updatePrimaryOptions('#selectedProvinces', '#primaryProvince');
        });
        $('#selectedDistricts').on('change', function() {
            updatePrimaryOptions('#selectedDistricts', '#primaryDistrict');
        });
    });
</script>

<?php
require_once APPLICATION_PATH . '/footer.php';
