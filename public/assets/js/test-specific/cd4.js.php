<script type="text/javascript">
    function showPatientList() {
        $("#showEmptyResult").hide();
        if ($.trim($("#artPatientNo").val()) != '') {
            $.post("/cd4/requests/search-patients.php", {
                    artPatientNo: $.trim($("#artPatientNo").val())
                },
                function(data) {
                    if (parseInt(data) >= 1) {
                        showModal('/cd4/requests/patientModal.php?artNo=' + encodeURIComponent($.trim($("#artPatientNo").val())), 900, 520);
                    } else {
                        $("#showEmptyResult").show();
                    }
                }).fail(function(jqXHR, textStatus, errorThrown) {
                    if (typeof reportError === 'function') {
                        reportError('CD4 patient search failed: ' + textStatus + ' ' + errorThrown, { type: 'ajax_error', context: 'Status: ' + (jqXHR.status || '') + '\nResponse: ' + String(jqXHR.responseText || '').substring(0, 2000) });
                    }
                });
        }
    }
</script>
