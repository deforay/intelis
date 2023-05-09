<?php
$title = "Sync History";

require_once APPLICATION_PATH . '/header.php';

?>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <h1><em class="fa-solid fa-gears"></em> Sync History</h1>
        <ol class="breadcrumb">
            <li><a href="/"><em class="fa-solid fa-chart-pie"></em> Home</a></li>
            <li class="active">Sync History</li>
        </ol>
    </section>

    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-xs-12">
                <div class="box">
                    <!-- /.box-header -->
                    <div class="box-body">
                        <table id="syncDataTable" class="table table-bordered table-striped" aria-hidden="true">
                            <thead>
                                <tr>
                                    <th scope="row">Lab Name</th>
                                    <th scope="row">Sync Date and Time</th>
                                    <th scope="row">No. of records synced</th>
                                    <th scope="row">Type of Sync</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="4" class="dataTables_empty">Loading data from server</td>
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
        oTable = $('#syncDataTable').dataTable({
            "oLanguage": {
                "sLengthMenu": "_MENU_ records per page"
            },
            "bJQueryUI": false,
            "bAutoWidth": false,
            "bInfo": true,
            "bScrollCollapse": true,
            "bStateSave": true,
            "bRetrieve": true,
            "aoColumns": [{
                    "sClass": "center"
                },
                {
                    "sClass": "center"
                },
                {
                    "sClass": "center"
                },
                {
                    "sClass": "center"
                }
            ],
            "aaSorting": [
                [1, "desc"]
            ],
            "bProcessing": true,
            "bServerSide": true,
            "sAjaxSource": "get-sync-history-helper.php",
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
