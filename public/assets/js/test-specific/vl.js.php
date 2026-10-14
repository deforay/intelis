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
                    try {
                        let sCodeKey = JSON.parse(data);
                        if ($('#sampleCodeInText').length > 0) {
                            $("#sampleCodeInText").text(sCodeKey.sampleCode);
                        }
                        $("#sampleCode").val(sCodeKey.sampleCode);
                        $("#sampleCodeFormat").val(sCodeKey.sampleCodeFormat);
                        $("#sampleCodeKey").val(sCodeKey.maxId);
                        $("#provinceId").val(provinceId);
                    } catch (err) {
                        if (typeof reportError === 'function') {
                            reportError(err, { type: 'ajax_error', context: 'generateSampleCode response: ' + String(data).substring(0, 2000) });
                        }
                    }
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
                }).fail(function(jqXHR, textStatus, errorThrown) {
                    if (typeof reportError === 'function') {
                        reportError('VL patient search failed: ' + textStatus + ' ' + errorThrown, { type: 'ajax_error', context: 'Status: ' + (jqXHR.status || '') + '\nResponse: ' + String(jqXHR.responseText || '').substring(0, 2000) });
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
    // A result is never saved without the testing platform it was read on. The
    // save helpers refuse one, so the form asks for the platform first. Applied
    // just before validation, from what the form is about to send: a result box
    // that is disabled is not sent, so it keeps the saved result and needs nothing.
    (function() {
        var MARK = 'platformForResult';

        function isRejected($form) {
            return $form.find('select[name="isSampleRejected"]').val() === 'yes' ||
                $form.find('input[name="isSampleRejected"]:checked').val() === 'yes';
        }

        function sendsAResult($form) {
            var $boxes = $form.find('[name="vlResult"], [name="cphlVlResult"], [name="finalViralLoadResult"]')
                .filter(':enabled');
            if (!$boxes.length) {
                return false;
            }
            // The log and the detection line make a result on their own once a result box is sent
            var entered = false;
            $boxes.add($form.find('[name="vlLog"], [name="hivDetection"]').filter(':enabled')).each(function() {
                if ($.trim($(this).val() || '') !== '') {
                    entered = true;
                }
            });
            return entered;
        }

        function requirePlatformForResult(form) {
            if (!form) {
                return;
            }
            var $form = $(form);
            var $platform = $form.find('[name="testingPlatform"]');
            if (!$platform.length) {
                return;
            }
            if (!isRejected($form) && sendsAResult($form)) {
                if (!$platform.hasClass('isRequired')) {
                    $platform.addClass('isRequired ' + MARK)
                        .attr('title', "<?= _jsTranslate('Please choose VL Testing Platform'); ?>");
                }
            } else if ($platform.hasClass(MARK)) {
                // Only lift a requirement this added, never one the form set itself
                $platform.removeClass('isRequired ' + MARK);
            }
        }

        $(function() {
            if (typeof deforayValidator === 'undefined' || typeof deforayValidator.validate !== 'function') {
                return;
            }
            var originalValidate = deforayValidator.validate;
            deforayValidator.validate = function() {
                requirePlatformForResult(document.getElementById(this.settings && this.settings.formId));
                return originalValidate.apply(this, arguments);
            };
        });
    })();
</script>