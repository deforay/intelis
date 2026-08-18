<?php

use App\Utilities\DateUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

use const SAMPLE_STATUS\REJECTED;

// Sanitized values from $request object
/** @var Psr\Http\Message\ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$tResult = [];
$tableResult = [];
$dateRange = trim((string) ($_POST['sampleCollectionDate'] ?? ''));

if ($dateRange !== '') {
    $queryParams = [];

    [$start_date, $end_date] = DateUtility::convertDateRange($dateRange, includeTime: true);

    //get value by rejection reason id
    $vlQuery = "SELECT count(*) as `total`,
                vl.reason_for_sample_rejection,
                vl.lab_id,
                vl.facility_id,
                sr.rejection_reason_name,
                sr.rejection_type,
                sr.rejection_reason_code,
                fd.facility_name,
                lab.facility_name as `labname`
                FROM form_vl as vl
                LEFT JOIN r_vl_sample_rejection_reasons as sr ON sr.rejection_reason_id=vl.reason_for_sample_rejection
                LEFT JOIN facility_details as fd ON fd.facility_id=vl.facility_id
                LEFT JOIN facility_details as lab ON lab.facility_id=vl.lab_id";

    // A sample counts as rejected when the rejection flag OR the sample status
    // says so. The two disagree on older records -- samples rejected from the
    // Add Request page were saved with the status set and the flag left blank --
    // and this is the same rule the Lab Performance Indicators report counts on.
    $sWhere = [' (vl.is_sample_rejected = "yes" OR vl.result_status = ' . REJECTED . ') '];

    // Control samples are excluded from every other VL report and export.
    $sWhere[] = ' IFNULL(vl.reason_for_vl_testing, 0) != 9999 ';

    $sWhere[] = ' vl.sample_collection_date BETWEEN ? AND ? ';
    $queryParams[] = $start_date;
    $queryParams[] = $end_date;

    if (isset($_POST['sampleType']) && trim((string) $_POST['sampleType']) !== '') {
        $sWhere[] = ' vl.specimen_type = ? ';
        $queryParams[] = (int) $_POST['sampleType'];
    }
    if (isset($_POST['labName']) && trim((string) $_POST['labName']) !== '') {
        $sWhere[] = ' vl.lab_id = ? ';
        $queryParams[] = (int) $_POST['labName'];
    }
    if (isset($_POST['clinicName']) && is_array($_POST['clinicName'])) {
        $clinics = array_values(array_filter(array_map('intval', $_POST['clinicName'])));
        if ($clinics !== []) {
            $sWhere[] = ' vl.facility_id IN (' . implode(',', array_fill(0, count($clinics), '?')) . ') ';
            $queryParams = array_merge($queryParams, $clinics);
        }
    }
    if (!empty($_SESSION['facilityMap'])) {
        $sWhere[] = " vl.facility_id IN (" . $_SESSION['facilityMap'] . ")";
    }

    if ($labScope = $general->labScopeWhere('vl')) {
        $sWhere[] = $labScope;
    }

    $vlQuery .= " WHERE " . implode(' AND ', $sWhere) . " GROUP BY vl.reason_for_sample_rejection, vl.lab_id, vl.facility_id";

    // Keyed per module -- VL, CD4 and TB rejection reports all used to share one
    // session key, so exporting from one could hand back another module's rows.
    $_SESSION['vlRejectedSamplesQuery'] = ['query' => $vlQuery, 'params' => $queryParams];
    $tableResult = $db->rawQuery($vlQuery, $queryParams);

    foreach ($tableResult as $tableRow) {
        $reasonName = trim((string) $tableRow['rejection_reason_name']) ?: _translate('Unspecified reason for rejection');
        $reasonType = trim((string) $tableRow['rejection_type']) ?: _translate('Unspecified');


        $tResult[$reasonName]['total'] = ($tResult[$reasonName]['total'] ?? 0) + $tableRow['total'];
        $tResult[$reasonName]['category'] = $reasonType;
    }
}

$totalRejected = array_sum(array_column($tResult, 'total'));


if ($tResult !== []) {
    ?>
    <div id="container" style="width: 100%; height: 500px; margin: 20px auto;"></div>
    <!-- <div id="rejectedType" style="width: 100%; height: 400px; margin: 20px auto;margin-top:50px;"></div> -->
<?php }
if (!empty($tableResult)) { ?>
    <div class="pull-right">
        <button class="btn btn-success" type="button" onclick="exportInexcel()"><em
                class="fa-solid fa-cloud-arrow-down"></em>
            <?php echo _translate("Export Excel"); ?>
        </button>
    </div>
<?php } ?>
<table aria-describedby="table" id="vlRequestDataTable" class="table table-bordered table-striped table-hover">
    <thead>
        <tr>
            <th>
                <?php echo _translate("Lab Name"); ?>
            </th>
            <th>
                <?php echo _translate("Facility Name"); ?>
            </th>
            <th>
                <?php echo _translate("Rejection Reason"); ?>
            </th>
            <th>
                <?php echo _translate("Reason Category"); ?>
            </th>
            <th>
                <?php echo _translate("No. of Rejected Samples"); ?>
            </th>
        </tr>
    </thead>
    <tbody>
        <?php
        if (!empty($tableResult)) {
            foreach ($tableResult as $tableRow) {
                // Drill down to this row's lab and facility, not to whatever the
                // page filters happened to be set to.
                ?>
                <tr data-lab="<?php echo base64_encode((string) $tableRow['lab_id']); ?>"
                    data-facility="<?php echo base64_encode((string) $tableRow['facility_id']); ?>"
                    data-daterange="<?= _sanitizeOutput($dateRange); ?>" data-type="rejection">
                    <td>
                        <?php echo _sanitizeOutput($tableRow['labname']); ?>
                    </td>
                    <td>
                        <?php echo _sanitizeOutput($tableRow['facility_name']); ?>
                    </td>
                    <td>
                        <?php echo _sanitizeOutput(trim((string) $tableRow['rejection_reason_name']) ?: _translate('Unspecified reason for rejection')); ?>
                    </td>
                    <td>
                        <?php echo _sanitizeOutput(trim((string) $tableRow['rejection_type']) ?: _translate("Unspecified")); ?>
                    </td>
                    <td>
                        <?php echo (int) $tableRow['total']; ?>
                    </td>
                </tr>
                <?php
            }
        }
        ?>
    </tbody>
</table>
<script>
    $(function () {
        $("#vlRequestDataTable").DataTable();
    });
    $(document).ready(function () {
        $('#vlRequestDataTable tbody').on('click', 'tr', function () {
            let facilityId = $(this).attr('data-facility');
            let lab = $(this).attr('data-lab');
            let daterange = $(this).attr('data-daterange');
            let type = $(this).attr('data-type');
            if (!facilityId || !lab) {
                return;
            }
            let link = "/vl/requests/vl-requests.php?labId=" + encodeURIComponent(lab) +
                "&facilityId=" + encodeURIComponent(facilityId) +
                "&daterange=" + encodeURIComponent(daterange) +
                "&status=<?= REJECTED; ?>&type=" + encodeURIComponent(type);
            window.open(link);
        });
    });

    <?php
    if ($tResult !== []) { ?>
        $('#container').highcharts({
            chart: {
                plotBackgroundColor: null,
                plotBorderWidth: null,
                plotShadow: false,
                type: 'pie'
            },
            title: {
                text: <?= _jsEscape(_translate('Sample Rejection Reasons') . ' (N = ' . $totalRejected . ')'); ?>
            },
            credits: {
                enabled: false
            },
            tooltip: {
                pointFormat: '{point.number}: <strong>{point.y}</strong>'
            },
            plotOptions: {
                pie: {
                    allowPointSelect: true,
                    cursor: 'pointer',
                    dataLabels: {
                        enabled: true,
                        format: '<strong>{point.name}</strong>: {point.y}',
                        style: {
                            color: (Highcharts.theme && Highcharts.theme.contrastTextColor) || 'black'
                        }
                    }
                }
            },
            series: [{
                colorByPoint: true,
                point: {
                    events: {
                        click: function (e) {
                            e.preventDefault();
                        }
                    }
                },
                data: [
                    <?php
                    foreach ($tResult as $reasonName => $values) {
                        ?> {
                            name: <?= _jsEscape($reasonName); ?>,
                            y: <?= (int) $values['total']; ?>,
                            number: <?= _jsEscape($values['category']); ?>
                        },
                        <?php
                    }
                    ?>
                ]
            }]
        });
    <?php } ?>
</script>
