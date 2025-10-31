<?php

$title = _translate("Enter Covid-19 Result");

require_once APPLICATION_PATH . '/header.php';
?>
<style>
    .disabledForm {
        background: #efefef;
    }

    :disabled,
    .disabledForm .input-group-addon {
        background: none !important;
        border: none !important;
    }

    .ui_tpicker_second_label {
        display: none !important;
    }

    .ui_tpicker_second_slider {
        display: none !important;
    }

    .ui_tpicker_millisec_label {
        display: none !important;
    }

    .ui_tpicker_millisec_slider {
        display: none !important;
    }

    .ui_tpicker_microsec_label {
        display: none !important;
    }

    .ui_tpicker_microsec_slider {
        display: none !important;
    }

    .ui_tpicker_timezone_label {
        display: none !important;
    }

    .ui_tpicker_timezone {
        display: none !important;
    }

    .ui_tpicker_time_input {
        width: 100%;
    }
</style>

<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <h1><em class="fa-solid fa-virus-covid"></em> <?php echo _translate("Covid-19 QC Data"); ?></h1>
        <ol class="breadcrumb">
            <li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?php echo _translate("Home"); ?></a></li>
            <li class="active"><?php echo _translate("Covid-19 QC Data"); ?></li>
        </ol>
    </section>

    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-xs-12">
                <div class="box">
                    <div class="box-header with-border">
                        <?php if (_isAllowed("add-covid-19-qc-data.php")) { ?>
                            <a href="add-covid-19-qc-data.php" class="btn btn-primary pull-right"> <em class="fa-solid fa-plus"></em> <?php echo _translate("Add New Covid-19 QC Data"); ?></a>
                        <?php } ?>
                    </div>
                    <!-- /.box-header -->
                    <div class="box-body">
                        <table aria-describedby="table" id="qcTestKitsDataTable" class="table table-bordered table-striped" aria-hidden="true">
                            <thead>
                                <tr>
                                    <th><?php echo _translate("QC Code"); ?></th>
                                    <th><?php echo _translate("Test Kit"); ?></th>
                                    <th><?php echo _translate("Lot Number"); ?></th>
                                    <th><?php echo _translate("Expiry Date"); ?></th>
                                    <th scope="row"><?php echo _translate("Testing Lab"); ?></th>
                                    <th><?php echo _translate("Tester Name"); ?></th>
                                    <th><?php echo _translate("Tested On"); ?></th>
                                    <th><?php echo _translate("Last Modified On"); ?></th>
                                    <?php if (_isAllowed("edit-covid-19-qc-data.php")) { ?>
                                        <th><?php echo _translate("Action"); ?></th>
                                    <?php } ?>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="9" class="dataTables_empty"><?php echo _translate("Loading data from server"); ?></td>
                                </tr>
                            </tbody>

                        </table>
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

<script>
    var oTable = null;

    $(document).ready(function() {
        $.blockUI();
        oTable = $('#qcTestKitsDataTable').dataTable({
            "oLanguage": {
                "sLengthMenu": "_MENU_ <?= _translate("records per page", true); ?>"
            },
            "bJQueryUI": false,
            "bAutoWidth": false,
            "bInfo": true,
            "bScrollCollapse": true,
            //"bStateSave" : true,
            "bRetrieve": true,
            "aoColumns": [{
                "sClass": "center"
            }, {
                "sClass": "center"
            }, {
                "sClass": "center"
            }, {
                "sClass": "center"
            }, {
                "sClass": "center"
            }, {
                "sClass": "center"
            }, {
                "sClass": "center"
            }, {
                "sClass": "center"
            }, {
                "sClass": "center",
                "bSortable": false
            }],
            "aaSorting": [
                [7, "desc"]
            ],
            "bProcessing": true,
            "bServerSide": true,
            "sAjaxSource": "get-covid19-qc-data-list.php",
            "fnServerData": function(sSource, aoData, fnCallback) {
                $.ajax({
                    "dataType": 'json',
                    "type": "POST",
                    "url": sSource,
                    "data": aoData,
                    "success": fnCallback
                });
            }
        });
        $.unblockUI();



    });
</script>


<?php
require_once APPLICATION_PATH . '/footer.php';
