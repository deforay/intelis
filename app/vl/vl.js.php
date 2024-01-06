<script type="text/javascript">
    $(document).ready(function() {

        let dateFormatMask = '<?= $_SESSION['jsDateFormatMask'] ?? '99-aaa-9999' ?>';
        $('.date').mask(dateFormatMask);
        $('.dateTime').mask(dateFormatMask + ' 99:99');

        $('.date').datepicker({
            changeMonth: true,
            changeYear: true,
            dateFormat: '<?= $_SESSION['jsDateFieldFormat'] ?? 'dd-M-yy'; ?>',
            timeFormat: "hh:mm",
            maxDate: "Today",
            yearRange: <?= (date('Y') - 100); ?> + ":" + "<?= date('Y') ?>"
        }).click(function() {
            $('.ui-datepicker-calendar').show();
        });
        $('.dateTime').datetimepicker({
            changeMonth: true,
            changeYear: true,
            dateFormat: '<?= $_SESSION['jsDateFieldFormat'] ?? 'dd-M-yy'; ?>',
            timeFormat: "HH:mm",
            maxDate: "Today",
            onChangeMonthYear: function(year, month, widget) {
                setTimeout(function() {
                    $('.ui-datepicker-calendar').show();
                });
            }
        }).click(function() {
            $('.ui-datepicker-calendar').show();
        });
    });


    function changeFormat(date) {
        splitDate = date.split("-");
        var fDate = new Date(splitDate[1] + splitDate[2] + ", " + splitDate[0]);
        var monthDigit = fDate.getMonth();
        var fMonth = isNaN(monthDigit) ? 1 : (parseInt(monthDigit) + parseInt(1));
        fMonth = (fMonth < 10) ? '0' + fMonth : fMonth;
        return splitDate[2] + '-' + fMonth + '-' + splitDate[0];
    }



    function checkARTRegimenValue() {
        var artRegimen = $("#artRegimen").val();
        if (artRegimen == 'other') {
            $(".newArtRegimen").show();
            $("#newArtRegimen").addClass("isRequired");
            $("#newArtRegimen").focus();
        } else {
            $(".newArtRegimen").hide();
            $("#newArtRegimen").removeClass("isRequired");
            $('#newArtRegimen').val("");
        }
    }

    function clearDOB(val) {
        if ($.trim(val) != "") {
            $("#dob").val("");
        }
    }

    function checkSampleReceviedDate() {
        var sampleCollectionDate = $("#sampleCollectionDate").val();
        var sampleReceivedDate = $("#sampleReceivedDate").val();
        if ($.trim(sampleCollectionDate) != '' && $.trim(sampleReceivedDate) != '') {

            date1 = new Date(sampleCollectionDate);
            date2 = new Date(sampleReceivedDate);

            if (date2.getTime() < date1.getTime()) {
                alert("<?= _translate("Sample Received at Testing Lab Date cannot be earlier than Sample Collection Date"); ?>");
                $("#sampleReceivedDate").val("");
            }
        }
    }

    function checkSampleReceviedAtHubDate() {
        var sampleCollectionDate = $("#sampleCollectionDate").val();
        var sampleReceivedAtHubOn = $("#sampleReceivedAtHubOn").val();
        if ($.trim(sampleCollectionDate) != '' && $.trim(sampleReceivedAtHubOn) != '') {

            date1 = new Date(sampleCollectionDate);
            date2 = new Date(sampleReceivedAtHubOn);

            if (date2.getTime() < date1.getTime()) {
                alert("<?= _translate("Sample Received at Hub Date cannot be earlier than Sample Collection Date"); ?>");
                $("#sampleReceivedAtHubOn").val("");
            }
        }
    }

    function checkSampleTestingDate() {
        var sampleCollectionDate = $("#sampleCollectionDate").val();
        var sampleTestingDate = $("#sampleTestingDateAtLab").val();
        if ($.trim(sampleCollectionDate) != '' && $.trim(sampleTestingDate) != '') {

            date1 = new Date(sampleCollectionDate);
            date2 = new Date(sampleTestingDate);

            if (date2.getTime() < date1.getTime()) {
                alert("<?= _translate("Sample Testing Date cannot be earlier than Sample Collection Date"); ?>");
                $("#sampleTestingDateAtLab").val("");
            }
        }
    }

    function checkARTInitiationDate() {
        var dob = changeFormat($("#dob").val());
        var artInitiationDate = $("#dateOfArtInitiation").val();
        if ($.trim(dob) != '' && $.trim(artInitiationDate) != '') {

            date1 = new Date(dob);
            date2 = new Date(artInitiationDate);

            if (date2.getTime() < date1.getTime()) {
                alert("<?= _translate("ART Initiation Date cannot be earlier than Patient Date of Birth"); ?>");
                $("#dateOfArtInitiation").val("");
            }
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

    function checkPatientDetails(tableName, fieldName, obj, fnct) {
        //if ($.trim(obj.value).length == 10) {
        if ($.trim(obj.value) != '') {
            $.post("/includes/checkDuplicate.php", {
                    tableName: tableName,
                    fieldName: fieldName,
                    value: obj.value,
                    fnct: fnct,
                    format: "html"
                },
                function(data) {
                    if (data === '1') {
                        showModal('patientModal.php?artNo=' + obj.value, 900, 520);
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
                    if (data != 0) {
                        // Toastify({
                        //     text: "<?= _translate('This Sample Code already exists', true) ?>",
                        //     duration: 3000,
                        //     style: {
                        //         background: 'red',
                        //     }
                        // }).showToast();
                    }
                });
            //$.unblockUI();
        }
    }



    function getfacilityProvinceDetails(obj) {
        $.blockUI();
        //check facility name`
        var cName = $("#facilityId").val();
        var pName = $("#province").val();
        if (cName != '' && provinceName && facilityName) {
            provinceName = false;
        }
        if (cName != '' && facilityName) {
            $.post("/includes/siteInformationDropdownOptions.php", {
                    cName: cName,
                    testType: 'vl'
                },
                function(data) {
                    if (data != "") {
                        details = data.split("###");
                        $("#province").html(details[0]);
                        $("#district").html(details[1]);
                        $("#clinicianName").val(details[2])
                    }
                });
        } else if (pName == '' && cName == '') {
            provinceName = true;
            facilityName = true;
            $("#province").html("<?php echo $province ?? ""; ?>");
            $("#facilityId").html("<?= (string) $facility ?? ""; ?>");
        }
        $.unblockUI();
    }
</script>
