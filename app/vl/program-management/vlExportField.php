<?php

require_once APPLICATION_PATH . '/header.php';
?>
<script type="text/javascript">
    $(document).ready(function() {
        exportInexcel();
    });

    function exportInexcel() {
        $.post("/vl/program-management/vlResultAllFieldExportInExcel.php",
            function(data) {
                if (data == "" || data == null || data == undefined) {

                    alert('Unable to generate the excel file');
                } else {

                    location.href = '/temporary/' + data;
                }
            });
    }
</script>