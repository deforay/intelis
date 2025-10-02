<script type="text/javascript" src="/assets/js/toastify.js?v=<?= filemtime(WEB_ROOT . "/assets/js/toastify.js") ?>"></script>
<script type="text/javascript" src="/assets/js/jquery-ui-timepicker-addon.js"></script>
<script type="text/javascript" src="/assets/js/js.cookie.js"></script>
<script type="text/javascript" src="/assets/js/select2.min.js"></script>
<script type="text/javascript" src="/assets/js/bootstrap.min.js"></script>
<script type="text/javascript" src="/assets/plugins/datatables/jquery.dataTables.min.js"></script>
<script type="text/javascript" src="/assets/plugins/datatables/dataTables.bootstrap.min.js"></script>
<script type="text/javascript" src="/assets/js/moment.min.js"></script>
<script type="text/javascript" src="/assets/plugins/daterangepicker/daterangepicker.js"></script>
<script type="text/javascript" src="/assets/js/dayjs.min.js"></script>
<script type="text/javascript" src="/assets/js/dayjs.customParseFormat.js"></script>
<script type="text/javascript" src="/assets/js/dayjs.utc.js"></script>
<script type="text/javascript" src="/assets/js/dayjs.timezone.js"></script>
<script type="text/javascript" src="/assets/js/app.min.js"></script>
<script type="text/javascript" src="/assets/js/deforayValidation.js"></script>
<script type="text/javascript" src="/assets/js/jquery.maskedinput.js"></script>
<script type="text/javascript" src="/assets/js/jquery.blockUI.js"></script>
<script type="text/javascript" src="/assets/js/highcharts.js"></script>
<script type="text/javascript" src="/assets/js/highcharts-exporting.js"></script>
<script type="text/javascript" src="/assets/js/highcharts-offline-exporting.js"></script>
<script type="text/javascript" src="/assets/js/highcharts-accessibility.js"></script>
<script type="text/javascript" src="/assets/js/summernote.min.js"></script>
<script type="text/javascript" src="/assets/js/selectize.js"></script>
<script type="text/javascript" src="/assets/js/sqids.min.js"></script>
<script type="text/javascript" src="/assets/js/dataTables.buttons.min.js"></script>
<script type="text/javascript" src="/assets/js/jszip.min.js"></script>
<script type="text/javascript" src="/assets/js/buttons.html5.min.js"></script>
<script type="text/javascript" src="/assets/js/utils.js"></script>
<script type="text/javascript" src="/assets/js/storage.js"></script>

<?php

use App\Services\CommonService;
use App\Registries\ContainerRegistry;
use App\Services\SystemService;


/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var SystemService $systemService */
$systemService = ContainerRegistry::get(SystemService::class);

$remoteURL = $general->getRemoteURL();
?>

<script type="text/javascript">
    let stsURL = '<?= $remoteURL; ?>';
    window.csrf_token = '<?= $_SESSION['csrf_token']; ?>';

    function addCsrfTokenToForm(form) {
        if (form.find('input[name="csrf_token"]').length === 0) {
            $('<input>').attr({
                type: 'hidden',
                name: 'csrf_token',
                value: window.csrf_token
            }).appendTo(form);
        }
    }

    Highcharts.setOptions({
        chart: {
            style: {
                fontFamily: 'Arial', // Set global font family (optional)
                fontSize: '16px' // Set global font size
            }
        },
        exporting: {
            buttons: {
                contextButton: {
                    menuItems: [
                        "viewFullscreen",
                        "printChart",
                        "separator",
                        "downloadPNG",
                        "downloadJPEG",
                        "downloadSVG"
                    ]
                }
            }
        }
    });

    $.ajaxSetup({
        beforeSend: function(xhr, settings) {
            if (settings.type === 'POST' || settings.type === 'PUT' || settings.type === 'DELETE') {
                xhr.setRequestHeader('X-CSRF-Token', window.csrf_token);
            }
        }
    });


    function setCrossLogin() {
        StorageHelper.storeInSessionStorage('crosslogin', 'true');
    }


    function verifyManifest(testType) {
        if ($("#manifestCode").val() != "") {
            $.blockUI();
            if ($.fn.DataTable.isDataTable("#manifestDataTable")) {
                $('#manifestDataTable').DataTable().clear().destroy();

                // Restore the translated empty row
                $("#manifestDataTable tbody").html(`
        <tr>
            <td colspan="13" class="dataTables_empty" style="text-align:center;">
                <?= _translate("Please enter a valid Manifest Code to activate", true); ?>
            </td>
        </tr>
    `);
            }




            $.post("/specimen-referral-manifest/verify-manifest.php", {
                    manifestCode: $("#manifestCode").val(),
                    testType: testType
                },
                function(data) {
                    $.unblockUI();
                    try {
                        if (typeof data === 'string') {
                            data = data.trim();
                        }

                        if (!data) {
                            toast.error("<?= _translate("Unable to verify manifest", true); ?>");
                            return;
                        }

                        let response = data;
                        if (typeof data === 'string') {
                            try {
                                response = JSON.parse(data);
                            } catch (parseError) {
                                // fall back to legacy behaviour
                            }
                        }

                        if (typeof response === 'object' && response !== null && response.status) {
                            if (response.status === 'not-found') {
                                toast.error("<?= _translate("Manifest not found", true); ?>");
                                return;
                            }
                            if (response.status === 'match') {
                                $('.activateSample').show();
                                loadRequestData();
                                return;
                            }
                            if (response.status === 'mismatch') {
                                getSamplesForManifest();
                                return;
                            }
                        }

                        if (response === 'match') {
                            $('.activateSample').show();
                            loadRequestData();
                        } else if (response === 'not-found') {
                            toast.error("<?= _translate("Manifest not found", true); ?>");
                        } else {
                            syncManifestFromSTS(testType);
                        }
                    } catch (e) {
                        console.error(e);
                        toast.error("<?= _translate("Some error occurred while processing the manifest", true); ?>");
                    }
                });
        } else {
            alert("<?php echo _translate("Please enter the Sample Manifest Code", true); ?>");
        }
    }

    function syncManifestFromSTS(testType) {
        if ($("#manifestCode").val() != "") {
            $.blockUI();

            $.post("/tasks/remote/requests-receiver.php", {
                    manifestCode: $("#manifestCode").val(),
                    testType: testType
                },
                function(data) {
                    $.unblockUI();
                    let parsed;
                    try {
                        parsed = JSON.parse(data);
                        if (
                            parsed &&
                            typeof parsed === 'object' &&
                            !Array.isArray(parsed) &&
                            Object.keys(parsed).length === 0
                        ) {
                            toast.error("<?= _translate("No samples found in the manifest", true); ?>");
                        } else {
                            $('.activateSample').show();
                            $('#sampleId').val(data);
                            loadRequestData();
                        }
                    } catch (e) {
                        toast.error("<?= _translate("Some error occurred while processing the manifest", true); ?>");
                    }
                });
        } else {
            alert("<?php echo _translate("Please enter the Sample Manifest Code", true); ?>");
        }
    }

    let remoteSync = false;
    let globalDayjsDateFormat = '<?= $systemService->getDateFormat('dayjs'); ?>';
    let systemTimezone = '<?= $_SESSION['APP_TIMEZONE'] ?? 'UTC'; ?>';

    <?php if (!empty($remoteURL) && $general->isLISInstance()) { ?>
        remoteSync = true;

        function receiveMetaData() {
            if (!navigator.onLine) {
                alert("<?= _translate("Please connect to internet to sync with STS", escapeTextOrContext: true); ?>");
                return false;
            }

            if (remoteSync) {
                $.blockUI({
                    message: "<h3><?= _translate("Receiving Metadata from STS", escapeTextOrContext: true); ?><br><?= _translate("Please wait...", escapeTextOrContext: true); ?></h3>"
                });
                $.ajax({
                        url: "/tasks/remote/sts-metadata-receiver.php",
                    })
                    .done(function(data) {
                        console.log("Metadata Synced | STS -> LIS");
                        $.unblockUI();
                    })
                    .fail(function() {
                        $.unblockUI();
                        alert("<?= _translate("Unable to do STS Sync. Please contact technical team for assistance.", escapeTextOrContext: true); ?>");
                    })
                    .always(function() {
                        sendLabMetaData();
                    });
            }
        }

        function sendLabMetaData() {
            if (!navigator.onLine) {
                alert("<?= _translate("Please connect to internet to sync with STS", escapeTextOrContext: true); ?>");
                return false;
            }

            if (remoteSync) {
                $.blockUI({
                    message: "<h3><?= _translate("Sending Lab Metadata", escapeTextOrContext: true); ?><br><?= _translate("Please wait...", escapeTextOrContext: true); ?></h3>"
                });
                $.ajax({
                        url: "/tasks/remote/lab-metadata-sender.php",
                    })
                    .done(function(data) {
                        console.log("Lab Metadata Synced | LIS -> STS");
                        $.unblockUI();
                    })
                    .fail(function() {
                        $.unblockUI();
                        alert("<?= _translate("Unable to do STS Sync. Please contact technical team for assistance.", escapeTextOrContext: true); ?>");
                    })
                    .always(function() {
                        sendTestResults();
                    });
            }
        }

        function sendTestResults() {

            $.blockUI({
                message: "<h3><?= _translate("Sending Test Results", escapeTextOrContext: true); ?><br><?= _translate("Please wait...", escapeTextOrContext: true); ?></h3>"
            });

            if (remoteSync) {
                $.ajax({
                        url: "/tasks/remote/results-sender.php",
                    })
                    .done(function(data) {
                        console.log("Results Synced | LIS -> STS");
                        $.unblockUI();
                    })
                    .fail(function() {
                        $.unblockUI();
                        alert("<?= _translate("Unable to do STS Sync. Please contact technical team for assistance.", escapeTextOrContext: true); ?>");
                    })
                    .always(function() {
                        receiveTestRequests();
                    });
            }
        }


        function receiveTestRequests() {
            $.blockUI({
                message: "<h3><?= _translate("Receiving Test Requests", escapeTextOrContext: true); ?><br><?= _translate("Please wait...", escapeTextOrContext: true); ?></h3>"
            });

            if (remoteSync) {
                $.ajax({
                        url: "/tasks/remote/requests-receiver.php",
                    })
                    .done(function(data) {
                        console.log("Requests Synced | STS -> LIS");
                        $.unblockUI();
                    })
                    .fail(function() {
                        $.unblockUI();
                        alert("<?= _translate("Unable to do STS Sync. Please contact technical team for assistance.", escapeTextOrContext: true); ?>");
                    });
            }
        }

        if (remoteSync) {
            (function getLastSTSSyncDateTime() {
                let currentDateTime = new Date();
                $.ajax({
                    url: '/tasks/remote/get-last-sts-sync-datetime.php',
                    cache: false,
                    success: function(lastSyncDateString) {
                        if (lastSyncDateString != null && lastSyncDateString != undefined) {
                            $('.lastSyncDateTime').html(lastSyncDateString);
                            $('.syncHistoryDiv').show();
                        }
                    },
                    error: function(data) {}
                });
                setTimeout(getLastSTSSyncDateTime, 15 * 60 * 1000);
            })();

            // Every 5 mins check if STS is reachable
            (function checkSTSConnection() {
                if (<?= empty($remoteURL) ? 1 : 0 ?>) {
                    $('.is-remote-server-reachable').hide();
                } else {
                    $.ajax({
                        url: stsURL + '/api/version.php',
                        cache: false,
                        success: function(data) {
                            $('.is-remote-server-reachable').fadeIn(1000);
                            $('.is-remote-server-reachable').css('color', '#4dbc3c');
                            if ($('.sts-server-reachable').length > 0) {
                                $('.sts-server-reachable').show();
                                $('.sts-server-reachable-span').html("<strong class='text-info'><?= _translate("STS server is reachable", escapeTextOrContext: true); ?></strong>");
                            }
                        },
                        error: function() {
                            $('.is-remote-server-reachable').fadeIn(1000);
                            $('.is-remote-server-reachable').css('color', 'red');
                            if ($('.sts-server-reachable').length > 0) {
                                $('.sts-server-reachable').show();
                                $('.sts-server-reachable-span').html("<strong class='mandatory'><?= _translate("STS server is unreachable", escapeTextOrContext: true); ?></strong>");
                            }
                        }
                    });
                }
                setTimeout(checkSTSConnection, 15 * 60 * 1000);
            })();
        }
    <?php } ?>



    function screenshot(supportId, attached) {
        if (supportId != "" && attached == 'yes') {
            closeModal();
            html2canvas(document.querySelector("#lis-body")).then(canvas => {
                dataURL = canvas.toDataURL();
                $.blockUI();
                $.post("/support/saveScreenshot.php", {
                        image: dataURL,
                        supportId: supportId
                    },
                    function(data) {
                        $.unblockUI();
                        alert("<?= _translate("Thank you.Your message has been submitted.", escapeTextOrContext: true); ?>");
                    });
            });
        } else {
            closeModal();
            $.blockUI();
            $.post("/support/saveScreenshot.php", {
                    supportId: supportId
                },
                function(data) {
                    $.unblockUI();
                    alert("<?= _translate("Thank you.Your message has been submitted.", escapeTextOrContext: true); ?>");
                });
        }
    }


    $(document).on('select2:open', (e) => {
        const selectId = e.target.id
        $(".select2-search__field[aria-controls='select2-" + selectId + "-results']").each(function(
            key,
            value,
        ) {
            value.focus();
        })
    });


    jQuery('.daterange,#daterange,#sampleCollectionDate,#sampleTestDate,#printSampleCollectionDate,#printSampleTestDate,#vlSampleCollectionDate,#eidSampleCollectionDate,#covid19SampleCollectionDate,#recencySampleCollectionDate,#hepatitisSampleCollectionDate,#hvlSampleTestDate,#printDate,#hvlSampleTestDate')
        .on('cancel.daterangepicker', function(ev, picker) {
            $(this).val('');
        });


    jQuery('.forceNumeric').on('input', function() {
        this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1');
    });

    jQuery('#ageInYears').on('input', function() {
        let age = Math.round(parseFloat(this.value));
        if (Number.isNaN(age)) {
            this.value = '';
        } else if (age > 150) {
            this.value = 150;
        } else if (age < 0) {
            this.value = 0;
        } else {
            this.value = age;
        }
    });


    function calculateAgeInYears(calcFrom, calcTo) {
        var dateOfBirth = moment($("#" + calcFrom).val(), '<?= $_SESSION['jsDateRangeFormat'] ?? 'DD-MMM-YYYY'; ?>');
        $("#" + calcTo).val(moment().diff(dateOfBirth, 'years'));
    }

    function getAge() {
        const dob = $.trim($("#dob").val());
        // Clear the fields initially

        if (dob && dob != "") {
            $("#ageInYears, #ageInMonths").val("");
            const age = Utilities.getAgeFromDob(dob, globalDayjsDateFormat);
            if (age.years && age.years >= 1) {
                $("#ageInYears").val(age.years);
            } else {
                $("#ageInMonths").val(age.months);
            }
        }
    }

    function showModal(url, w, h) {
        displayDeforayModal('dDiv', w, h);
        document.getElementById('dFrame').style.height = h + 'px';
        document.getElementById('dFrame').style.width = w + 'px';
        document.getElementById('dFrame').src = url;
    }

    function closeModal() {
        document.getElementById('dFrame').src = "";
        removeDeforayModal('dDiv');
    }

    function editableSelect(id, _fieldName, table, _placeholder) {
        $("#" + id).select2({
            placeholder: _placeholder,
            minimumInputLength: 0,
            width: '100%',
            allowClear: true,
            id: function(bond) {
                return bond._id;
            },
            ajax: {
                placeholder: "<?= _translate("Type one or more character to search", escapeTextOrContext: true); ?>",
                url: "/includes/get-data-list-for-generic.php",
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    return {
                        fieldName: _fieldName,
                        tableName: table,
                        q: params.term, // search term
                        page: params.page
                    };
                },
                processResults: function(data, params) {
                    params.page = params.page || 1;
                    return {
                        results: data.result,
                        pagination: {
                            more: (params.page * 30) < data.total_count
                        }
                    };
                },
                //cache: true
            },
            escapeMarkup: function(markup) {
                return markup;
            }
        });
    }


    function clearCache() {
        $.ajax({
            url: '/includes/clear-cache.php',
            cache: false,
            success: function(data) {
                toast.success("<?= _translate("Cache cleared successfully", true); ?>");
            },
            error: function() {
                console.error("An error occurred while clearing the cache.");
            }
        });
    }

    function forceMetadataSync(tbl) {
        if (tbl != "") {
            $.blockUI();
            $.post("/common/force-metadata-sync.php", {
                    table: tbl
                },
                function(data) {
                    $.unblockUI();
                    toast.success("<?= _translate("Synced successfully", true); ?>");
                });
        }
        $.unblockUI();
    }

    function checkCollectionDate(collectionDate, allowFutureDate = false) {
        if (collectionDate != "") {
            const dateC = collectionDate.split(" ");
            dt = dateC[0];
            f = dt.split("-");
            cDate = f[2] + '-' + f[1] + '-' + f[0];
            $.post("/common/date-validation.php", {
                    sampleCollectionDate: collectionDate,
                    allowFutureDates: allowFutureDate
                },
                function(data) {
                    if (data == "1") {
                        alert("<?= _translate("Sample Collection date cannot be in the future"); ?>")
                        return false;
                    } else {
                        var diff = (new Date(cDate).getTime() - new Date().getTime()) / 1000;
                        diff = diff / (60 * 60 * 24 * 10 * 3);
                        var diffMonths = Math.abs(Math.round(diff));
                        if (diffMonths > 6) {
                            $('.expiredCollectionDate').html("<?= _translate("Sample Collection Date is over 6 months old", escapeTextOrContext: true); ?>");
                            $('.expiredCollectionDate').show();
                        } else {
                            $('.expiredCollectionDate').hide();
                        }

                    }
                });
        }

    }

    // Generic scheduler function to run scripts asynchronously at specified intervals
    function runScheduledScripts(scriptsConfig) {
        Object.keys(scriptsConfig).forEach(scriptUrl => {
            const interval = scriptsConfig[scriptUrl];

            // Run the script immediately, then every interval milliseconds
            executeScript(scriptUrl);
            setInterval(() => executeScript(scriptUrl), interval);
        });
    }

    // Function to execute a script via AJAX asynchronously
    function executeScript(scriptUrl) {
        $.ajax({
            url: scriptUrl,
            method: 'GET',
            cache: false,
            success: function(data) {
                console.log(`Script ${scriptUrl} executed successfully`);
            },
            error: function(xhr, status, error) {
                console.error(`Failed to execute script ${scriptUrl}: ${status} - ${error}`);
            }
        });
    }


    // // Define your scripts with their intervals in milliseconds
    // const scriptsToRun = {
    //     // "/tasks/sample-code-generator.php": 60000, // Run every 1 minute
    //     //"/tasks/archive-audit-tables.php": 1800000 // Run every 30 minutes
    // };

    function checkARTRegimenValue() {
        var artRegimen = $("#artRegimen").val();
        if (artRegimen == 'not_reported') {
            $(".curRegimenDate .mandatory").remove();
            $("#regimenInitiatedOn").removeClass("isRequired");
        } else {
            $(".curRegimenDate").append(' <span class="mandatory">*</span>');
            $("#regimenInitiatedOn").addClass("isRequired");
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

    /**
     * Clears any date fields with placeholder-like values and triggers necessary events
     * @param {string} selector - jQuery selector for the elements to check
     * @returns {void}
     */
    function clearDatePlaceholderValues(selector) {
        $(selector).each(function() {
            var value = $(this).val();
            // Check if the value contains placeholder characters (* or _ or --)
            if (value && (/[*_]|--/.test(value))) {
                $(this).val(''); // Clear the field
                // Trigger multiple events to ensure all handlers are notified
                $(this).trigger('change input blur');
            }
        });
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
            $("#province").html("<?= !empty($province) ? $province : ''; ?>");
            $("#facilityId").html("<?= !empty($facility) ? $facility : ''; ?>");
        }
        $.unblockUI();
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


    $(document).ready(function() {

        // Run the scheduler with the defined scripts and intervals
        //runScheduledScripts(scriptsToRun);
        existingPatientId = "";
        if ($('.patientId').val() !== "") {
            existingPatientId = $('.patientId').val();
        }

        // Add CSRF token to all existing forms
        $('form').each(function() {
            addCsrfTokenToForm($(this));
        });

        // Add CSRF token to forms added in the future
        // If your application adds forms dynamically via AJAX or other means
        $(document).on('submit', 'form', function(e) {
            addCsrfTokenToForm($(this));
        });


        $('.richtextarea').summernote({
            toolbar: [
                // [groupName, [list of button]]
                ['style', ['bold', 'italic', 'underline', 'clear']],
                ['font', ['strikethrough', 'superscript', 'subscript']],
                ['fontsize', ['fontsize']],
                ['color', ['color']],
                ['para', ['ul', 'ol', 'paragraph']],
                ['height', ['height']]
            ],
            height: 200
        });


        $(".allMenu").removeClass('active');

        let url = window.location.pathname + window.location.search;
        let currentMenuItem = $('a[href="' + url + '"]');

        if (!currentMenuItem.length) {
            let currentPaths = Utilities.splitPath(url).map(path => btoa(path));
            currentMenuItem = $('a[data-inner-pages]').filter(function() {
                return currentPaths.some(path => $(this).data('inner-pages').split(';').includes(path));
            });
        }

        if (currentMenuItem.length) {
            currentMenuItem.parent().addClass('active');
            let treeview = currentMenuItem.parents('li.treeview').addClass('active')[0];
            let offset = treeview ? treeview.offsetTop : 0;
            if (offset > 200) {
                $('.main-sidebar').scrollTop(offset);
            }
        }

        // Phone number validation
        const countryCode = "<?= $countryCode ?? ''; ?>";
        $('.phone-number').on('input', Utilities.debounce(function() {
            let inputElement = $(this);
            let phoneNumber = inputElement.val().trim();

            if (phoneNumber === countryCode || phoneNumber === "") {
                inputElement.val("");
                return;
            }

            phoneNumber = phoneNumber.replace(/[^0-9+]/g, ''); // Remove non-numeric and non-plus characters
            inputElement.val(phoneNumber);

            $.ajax({
                type: 'POST',
                url: '/includes/validatePhoneNumber.php',
                data: {
                    phoneNumber: phoneNumber
                },
                success: function(response) {
                    if (!response.isValid) {
                        toast.error("<?= _translate("Invalid phone number. Please enter full phone number with the proper country code", true); ?>");
                    }
                },
                error: function() {
                    console.error("An error occurred while validating the phone number.");
                }
            });
        }, 700));

        $('.phone-number').on('focus', function() {
            let phoneNumber = $(this).val().trim();
            if (phoneNumber === "") {
                $(this).val(countryCode);
            }
        });

        $('.phone-number').on('blur', function() {
            let phoneNumber = $(this).val().trim();
            if (phoneNumber === countryCode || phoneNumber === "") {
                $(this).val("");
            }
        });

        $('.patientId').on('change', function() {


            var patientId = $(this).val();

            if (existingPatientId !== "" && existingPatientId != patientId) {
                if (confirm("Are you sure you want to change the Patient ID from '" + existingPatientId + "' to '" + patientId + "'? This can lead to data mismatch or data loss.")) {
                    $(this).val(patientId);
                } else {
                    $(this).val(existingPatientId);
                }
            }


            var minLength = '<?= $minPatientIdLength ?? 0; ?>';

            if (patientId.length < minLength) {
                $(".lengthErr").remove();
                var txt = "<?= _translate('Please enter minimum length for Patient Id : ', escapeTextOrContext: true); ?>" + minLength;
                $(this).parent().append('<span class="lengthErr" style="color:red;">' + txt + '</span>');
            } else {
                $(".lengthErr").remove();
            }

        });
    });


    const typeLabels = {
        fs_perms: "Folder Permissions",
        disk: "Disk Space",
        mysql: "Database",
        message: "System Message"
    };
    (function() {

        try {
            const lastId = localStorage.getItem('sseLastId') || 0;
            const es = new EventSource('/sse/alerts.php?last_id=' + lastId);

            const handle = (alert) => {
                const typeLabel = typeLabels[alert.type] || alert.type;
                const msg = `[${typeLabel}] ${alert.message}`;

                switch (alert.level) {
                    case 'info':
                        toast.success(msg);
                        break;
                    case 'warn':
                        toast.warn ? toast.warn(msg) : toast.error(msg);
                        break;
                    case 'error':
                    case 'critical':
                        toast.error(msg);
                        break;
                }

                // persist last seen ID
                localStorage.setItem('sseLastId', alert.id);
            };

            es.addEventListener('disk', e => handle(JSON.parse(e.data)));
            es.addEventListener('mysql', e => handle(JSON.parse(e.data)));
            es.addEventListener('fs_perms', e => handle(JSON.parse(e.data)));
            es.addEventListener('message', e => handle(JSON.parse(e.data)));
        } catch (err) {
            console.error('SSE init failed', err);
        }
    })();
</script>