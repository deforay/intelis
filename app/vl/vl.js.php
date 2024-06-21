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

    function checkARTInitiationDate() {
        var dobInput = $("#dob").val();
        var artInitiationDateInput = $("#dateOfArtInitiation").val();

        if ($.trim(dobInput) !== '' && $.trim(artInitiationDateInput) !== '') {
            var dob = dayjs(dobInput, globalDayjsDateFormat);
            var artInitiationDate = dayjs(artInitiationDateInput, globalDayjsDateFormat);

            if (!dob.isValid() || !artInitiationDate.isValid()) {
                alert("<?= _translate('Invalid date format. Please check the input dates.'); ?>");
                return;
            }

            if (artInitiationDate.isBefore(dob)) {
                alert("<?= _translate('ART Initiation Date cannot be earlier than Patient Date of Birth'); ?>");
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
                        //     text: "<?= _translate('This Sample ID already exists', true) ?>",
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

    function getTreatmentLine(artRegimen) {
        var char = artRegimen.charAt(0);
        $("#lineOfTreatment").val(char);
    }

    function checkCollectionDate(collectionDate, allowFutureDate = false){
        if (collectionDate != "") {
                $.post("/common/date-validation.php", {
                        sampleCollectionDate: collectionDate,
                        allowFutureDates : allowFutureDate
                    },
                    function(data) {
                        console.log(data);
                        if (data == "1") {
                            alert("Please enter valid Sample Collection Date & Date should not be in future")
                            return false;
                        }
                    });
            }

    }
</script>
