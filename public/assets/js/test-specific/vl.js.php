<script type="text/javascript">
    let generateSampleCodeRequest = null;
    let lastSampleCollectionDate = '';
    let lastProvinceCode = '';

    function generateSampleCode(checkProvince = false) {
        let sampleCollectionDate = $("#sampleCollectionDate").val();
        let provinceElement = $("#province").find(":selected");
        let provinceCode = (provinceElement.attr("data-code") == null || provinceElement.attr("data-code") == '') ?
            provinceElement.attr("data-name") :
            provinceElement.attr("data-code");
        let provinceId = provinceElement.attr("data-province-id");

        if (sampleCollectionDate !== '' && (sampleCollectionDate !== lastSampleCollectionDate || (checkProvince && provinceCode !== lastProvinceCode))) {
            lastSampleCollectionDate = sampleCollectionDate; // Update the last sample collection date
            lastProvinceCode = provinceCode; // Update the last province code

            if (generateSampleCodeRequest) {
                generateSampleCodeRequest.abort();
            }

            generateSampleCodeRequest = $.post("/vl/requests/generateSampleCode.php", {
                    sampleCollectionDate: sampleCollectionDate,
                    provinceCode: provinceCode,
                    provinceId: provinceId
                },
                function(data) {
                    let sCodeKey = JSON.parse(data);
                    if ($('#sampleCodeInText').length > 0) {
                        $("#sampleCodeInText").text(sCodeKey.sampleCode);
                    }
                    $("#sampleCode").val(sCodeKey.sampleCode);
                    $("#sampleCodeFormat").val(sCodeKey.sampleCodeFormat);
                    $("#sampleCodeKey").val(sCodeKey.maxId);
                    $("#provinceId").val(provinceId);
                }).always(function() {
                generateSampleCodeRequest = null; // Reset the request object after completion
            });
        }
    }


    function changeFormat(date) {
        splitDate = date.split("-");
        var fDate = new Date(splitDate[1] + splitDate[2] + ", " + splitDate[0]);
        var monthDigit = fDate.getMonth();
        var fMonth = isNaN(monthDigit) ? 1 : (parseInt(monthDigit) + parseInt(1));
        fMonth = (fMonth < 10) ? '0' + fMonth : fMonth;
        return splitDate[2] + '-' + fMonth + '-' + splitDate[0];
    }


    function clearDOB(val) {
        if ($.trim(val) != "") {
            $("#dob").val("");
        }
    }


    function showPatientList() {
        $("#showEmptyResult").hide();
        if ($.trim($("#artPatientNo").val()) != '') {
            $.post("/vl/requests/search-patients.php", {
                    artPatientNo: $.trim($("#artPatientNo").val())
                },
                function(data) {
                    if (data >= '1') {
                        showModal('/vl/requests/patientModal.php?artNo=' + $.trim($("#artPatientNo").val()), 900, 520);
                    } else {
                        $("#showEmptyResult").show();
                    }
                });
        }
    }


    function checkSampleNameValidation(tableName, fieldName, id, fnct, alrt) {
        if ($.trim($("#" + id).val()) != '') {
            //$.blockUI();
            $.post("/vl/requests/checkSampleDuplicate.php", {
                    tableName: tableName,
                    fieldName: fieldName,
                    value: $("#" + id).val(),
                    fnct: fnct,
                    format: "html"
                },
                function(data) {
                    if (data != 0) {}
                });
            //$.unblockUI();
        }
    }




    function getTreatmentLine(artRegimen) {
        var char = artRegimen.charAt(0);
        $("#lineOfTreatment").val(char);
    }
</script>