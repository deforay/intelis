<?php

// imported in importedStatistics.php

$tsQuery = "SELECT COUNT(temp_sample_id) AS totalCount,
                    SUM(CASE WHEN vl.result_status like '7' AND vl.vl_result_category like 'not suppressed' THEN 1 ELSE 0 END) AS HighViralLoad,
                    SUM(CASE WHEN vl.result_status like '7' AND vl.vl_result_category like 'suppressed' THEN 1 ELSE 0 END) AS LowViralLoad,
                    SUM(CASE WHEN vl.result_status like '4' OR vl.vl_result_category like 'rejected' THEN 1 ELSE 0 END) AS Rejected,
                    SUM(CASE WHEN vl.result_status like '1' OR  vl.vl_result_category like 'failed' THEN 1 ELSE 0 END) AS HoldOrFailed
                    FROM temp_sample_import as tsr
                    $joinTypeWithTestTable form_vl as vl ON vl.sample_code=tsr.sample_code
                    WHERE  imported_by ='$importedBy' ";
$tsResult = $db->rawQueryOne($tsQuery);

//set print query
$hQuery = "SELECT hsr.sample_code
                FROM hold_sample_import as hsr
                $joinTypeWithTestTable form_vl as vl ON vl.sample_code=hsr.sample_code";
$hResult = $db->rawQuery($hQuery);
$holdSample = [];
if ($hResult) {
    foreach ($hResult as $sample) {
        $holdSample[] = $sample['sample_code'];
    }
}
$saQuery = "SELECT tsr.sample_code
            FROM temp_sample_import as tsr
            $joinTypeWithTestTable form_vl as vl ON vl.sample_code=tsr.sample_code
            WHERE imported_by = ? ";
$saResult = $db->rawQuery($saQuery, [$importedBy]);
$sampleCode = [];
foreach ($saResult as $sample) {
    if (!in_array($sample['sample_code'], $holdSample)) {
        $sampleCode[] = "'" . $sample['sample_code'] . "'";
    }
}
$sCode = implode(', ', $sampleCode);

unset($_SESSION['controllertrack']);

?>

<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <h1>
            <?= _translate("Imported Results"); ?>
        </h1>
        <ol class="breadcrumb">
            <li><a href="/"><em class="fa-solid fa-chart-pie"></em> Home</a></li>
        </ol>
    </section>
    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-xs-12">
                <div class="box">
                    <div class="box-header">
                    </div>
                    <!-- /.box-header -->
                    <div class="box-body">
                        <table aria-describedby="table" id="vlRequestDataTable" class="table table-bordered table-striped" aria-hidden="true">
                            <thead>
                                <tr>
                                    <th><?= _translate("Total No. of Results imported"); ?></th>
                                    <th><?= _translate("No. of High Viral Load results"); ?></th>
                                    <th><?= _translate("No. of Low Viral Load results"); ?></th>
                                    <th><?= _translate("No. of Rejected results"); ?></th>
                                    <th><?= _translate("No. of Failed/On Hold results"); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>
                                        <?= $tsResult['totalCount'] ?? 0; ?>
                                    </td>
                                    <td>
                                        <?= $tsResult['HighViralLoad'] ?? 0; ?>
                                    </td>
                                    <td>
                                        <?= $tsResult['LowViralLoad'] ?? 0; ?>
                                    </td>
                                    <td>
                                        <?= $tsResult['Rejected'] ?? 0; ?>
                                    </td>
                                    <td>
                                        <?= $tsResult['HoldOrFailed'] ?? 0; ?>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <table aria-describedby="table" class="table" aria-hidden="true" style="margin-left:1%;margin-top:30px;width: 75%;">
                        <tr>
                            <td>
                                <?php
                                if (isset($tsResult['totalCount']) && $tsResult['totalCount'] > 0) { ?>
                                    <input type="button" onclick="convertSearchResultToPdf();return false;" value="Print all results" class="btn btn-success btn-sm">&nbsp;&nbsp;
                                    <a href="/vl/results/vlPrintResult.php" class="btn btn-success btn-sm">
                                        <?= _translate("Continue without printing results"); ?>
                                    </a>
                                <?php } else { ?>
                                    <a href="/vl/results/vlPrintResult.php" class="btn btn-success btn-sm">
                                        <?= _translate("Continue"); ?>
                                    </a>
                                <?php } ?>
                            </td>
                        </tr>

                    </table>
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
<script>
    function convertSearchResultToPdf() {
        $.blockUI();
        <?php
        $path = '';
        $path = '/vl/results/generate-result-pdf.php';
        ?>
        $.post("<?php echo $path; ?>", {
                source: 'print',
                id: '',
                sampleCodes: "<?php echo $sCode; ?>"
            },
            function(data) {
                if (data == "" || data == null || data == undefined) {
                    $.unblockUI();
                    alert("<?= _translate("Unable to generate download"); ?>");
                } else {
                    $.unblockUI();
                    window.open('/download.php?f=' + data, '_blank');
                    window.location.href = "/vl/results/vlPrintResult.php";
                }

            });
    }
</script>
