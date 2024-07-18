<script type="text/javascript" src="/assets/js/toastify.js"></script>
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
<?php

use App\Utilities\MiscUtility;
use App\Services\CommonService;
use App\Registries\ContainerRegistry;
use App\Services\SystemService;


/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var SystemService $systemService */
$systemService = ContainerRegistry::get(SystemService::class);

$remoteUrl = $general->getRemoteURL();


?>

<script type="text/javascript">
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

    window.additionalXHRParams = {
        layout: 0,
        'X-CSRF-Token': '<?php echo $_SESSION['csrf_token'] = $_SESSION['csrf_token'] ?? MiscUtility::generateRandomString(); ?>'
    };

    $.ajaxSetup({
        headers: window.additionalXHRParams
    });

    function setCrossLogin() {
        StorageHelper.storeInSessionStorage('crosslogin', 'true');
    }

    let remoteSync = false;
    let globalDayjsDateFormat = '<?= $systemService->getDateFormat('dayjs'); ?>';
    let systemTimezone = '<?= $_SESSION['APP_TIMEZONE'] ?? 'UTC'; ?>';



    <?php if (!empty($remoteUrl) && $general->isLISInstance()) { ?>
        remoteSync = true;

        function receiveMetaData() {
            if (!navigator.onLine) {
                alert("<?= _translate("Please connect to internet to sync with STS", escapeText: true); ?>");
                return false;
            }

            if (remoteSync) {
                $.blockUI({
                    message: "<h3><?= _translate("Receiving Metadata from STS", escapeText: true); ?><br><?= _translate("Please wait...", escapeText: true); ?></h3>"
                });
                $.ajax({
                        url: "/scheduled-jobs/remote/sts-metadata-receiver.php",
                    })
                    .done(function(data) {
                        console.log("Metadata Synced | STS -> LIS");
                        $.unblockUI();
                    })
                    .fail(function() {
                        $.unblockUI();
                        alert("<?= _translate("Unable to do STS Sync. Please contact technical team for assistance.", escapeText: true); ?>");
                    })
                    .always(function() {
                        sendLabMetaData();
                    });
            }
        }

        function sendLabMetaData() {
            if (!navigator.onLine) {
                alert("<?= _translate("Please connect to internet to sync with STS", escapeText: true); ?>");
                return false;
            }

            if (remoteSync) {
                $.blockUI({
                    message: "<h3><?= _translate("Sending Lab Metadata", escapeText: true); ?><br><?= _translate("Please wait...", escapeText: true); ?></h3>"
                });
                $.ajax({
                        url: "/scheduled-jobs/remote/lab-metadata-sender.php",
                    })
                    .done(function(data) {
                        console.log("Lab Metadata Synced | LIS -> STS");
                        $.unblockUI();
                    })
                    .fail(function() {
                        $.unblockUI();
                        alert("<?= _translate("Unable to do STS Sync. Please contact technical team for assistance.", escapeText: true); ?>");
                    })
                    .always(function() {
                        sendTestResults();
                    });
            }
        }

        function sendTestResults() {

            $.blockUI({
                message: "<h3><?= _translate("Sending Test Results", escapeText: true); ?><br><?= _translate("Please wait...", escapeText: true); ?></h3>"
            });

            if (remoteSync) {
                $.ajax({
                        url: "/scheduled-jobs/remote/results-sender.php",
                    })
                    .done(function(data) {
                        console.log("Results Synced | LIS -> STS");
                        $.unblockUI();
                    })
                    .fail(function() {
                        $.unblockUI();
                        alert("<?= _translate("Unable to do STS Sync. Please contact technical team for assistance.", escapeText: true); ?>");
                    })
                    .always(function() {
                        receiveTestRequests();
                    });
            }
        }


        function receiveTestRequests() {
            $.blockUI({
                message: "<h3><?= _translate("Receiving Test Requests", escapeText: true); ?><br><?= _translate("Please wait...", escapeText: true); ?></h3>"
            });

            if (remoteSync) {
                $.ajax({
                        url: "/scheduled-jobs/remote/requests-receiver.php",
                    })
                    .done(function(data) {
                        console.log("Requests Synced | STS -> LIS");
                        $.unblockUI();
                    })
                    .fail(function() {
                        $.unblockUI();
                        alert("<?= _translate("Unable to do STS Sync. Please contact technical team for assistance.", escapeText: true); ?>");
                    });
            }
        }

        if (remoteSync) {
            (function getLastSTSSyncDateTime() {
                let currentDateTime = new Date();
                $.ajax({
                    url: '/scheduled-jobs/remote/get-last-sts-sync-datetime.php',
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
                if (<?= empty($remoteUrl) ? 1 : 0 ?>) {
                    $('.is-remote-server-reachable').hide();
                } else {
                    $.ajax({
                        url: '<?= $remoteUrl; ?>' + '/api/version.php',
                        cache: false,
                        success: function(data) {
                            $('.is-remote-server-reachable').fadeIn(1000);
                            $('.is-remote-server-reachable').css('color', '#4dbc3c');
                        },
                        error: function() {
                            $('.is-remote-server-reachable').fadeIn(1000);
                            $('.is-remote-server-reachable').css('color', 'red');
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
                        alert("<?= _translate("Thank you.Your message has been submitted.", escapeText: true); ?>");
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
                    alert("<?= _translate("Thank you.Your message has been submitted.", escapeText: true); ?>");
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


    $('.daterange,#daterange,#sampleCollectionDate,#sampleTestDate,#printSampleCollectionDate,#printSampleTestDate,#vlSampleCollectionDate,#eidSampleCollectionDate,#covid19SampleCollectionDate,#recencySampleCollectionDate,#hepatitisSampleCollectionDate,#hvlSampleTestDate,#printDate,#hvlSampleTestDate').on('cancel.daterangepicker', function(ev, picker) {
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

    class Utilities {

        static validatePhoneNumber(phoneNumber, countryCode, minDigits, maxDigits) {
            // Remove all non-numeric characters from the phone number
            const numericPhoneNumber = phoneNumber.replace(/\D/g, '');

            // Check if the phone number starts with the country code, if countryCode is not null or empty
            if (countryCode && !phoneNumber.startsWith(countryCode)) {
                return false;
            }

            // Calculate the length of the phone number without the country code
            const countryCodeLength = countryCode ? countryCode.replace(/\D/g, '').length : 0;
            const lengthWithoutCountryCode = numericPhoneNumber.length - countryCodeLength;

            // Check the length of the phone number
            if (minDigits && lengthWithoutCountryCode < minDigits) {
                return false;
            }
            if (maxDigits && lengthWithoutCountryCode > maxDigits) {
                return false;
            }
            return true;
        }

        static async copyToClipboard(text) {
            let succeed;
            try {
                await navigator.clipboard.writeText(text);
                succeed = true;
            } catch (e) {
                succeed = false;
            }
            return succeed;
        }

        static toSnakeCase(str) {
            return str
                // Handle sequences of uppercase letters as single words
                .replace(/([A-Z]+)([A-Z][a-z])/g, '$1_$2')
                // Add an underscore before any uppercase letter followed by lowercase letters,
                // ensuring it's not the start of the string
                .replace(/([a-z\d])([A-Z])/g, '$1_$2')
                // Lowercase the whole string
                .toLowerCase()
                // Replace spaces and any non-alphanumeric characters (excluding underscores) with underscores
                .replace(/[\s]+/g, '_');
        }

        /**
         * Calculates age in years and months from a given date of birth (dob) and an optional format.
         * @param {string} dob - Date of birth.
         * @param {string} [format='DD-MMM-YYYY'] - Optional format of the dob.
         * @returns {Object} An object containing the age in years and months.
         */
        static getAgeFromDob(dob, format = 'DD-MMM-YYYY') {

            // Ensure dayjs and its plugin are available
            if (typeof dayjs === 'undefined' || !dayjs.extend) {
                console.error("Day.js or its customParseFormat plugin is not loaded");
                return {
                    years: 0,
                    months: 0
                };
            }

            // Extend dayjs with the customParseFormat plugin
            dayjs.extend(dayjs_plugin_customParseFormat);

            if (!dob || !dayjs(dob, format).isValid()) {
                console.error("Invalid or missing date of birth");
                return {
                    years: 0,
                    months: 0
                };
            }

            const dobDate = dayjs(dob, format);
            const currentDate = dayjs();

            if (dobDate.isAfter(currentDate)) {
                console.error("Date of birth is in the future");
                return {
                    years: 0,
                    months: 0
                };
            }

            const ageInYears = currentDate.diff(dobDate, 'year');
            const ageInMonths = currentDate.diff(dobDate, 'month') % 12;

            return {
                years: ageInYears,
                months: ageInMonths
            };
        }

        static splitPath(path) {
            let parts = path.split('?');
            let paths = [parts[0]];
            if (parts.length > 1) {
                let queryParams = parts[1].split('&');
                for (let i = 0; i < queryParams.length; i++) {
                    paths.push(parts[0] + '?' + queryParams.slice(0, i + 1).join('&'));
                }
            }
            return paths;
        }

        static autoSelectSingleOption(selectId) {
            let nonEmptyOptions = $('#' + selectId).find("option[value!='']");
            //  alert(nonEmptyOptions.length);
            if (nonEmptyOptions.length === 1) {
                $('#' + selectId).val(nonEmptyOptions.val()).trigger('change');
            }
        }

    }

    function calculateAgeInYears(calcFrom, calcTo) {
        var dateOfBirth = moment($("#" + calcFrom).val(), '<?= $_SESSION['jsDateRangeFormat'] ?? 'DD-MMM-YYYY'; ?>');
        $("#" + calcTo).val(moment().diff(dateOfBirth, 'years'));
    }

    function getAge() {
        const dob = $.trim($("#dob").val());
        // Clear the fields initially

        if (dob) {
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
        showdefModal('dDiv', w, h);
        document.getElementById('dFrame').style.height = h + 'px';
        document.getElementById('dFrame').style.width = w + 'px';
        document.getElementById('dFrame').src = url;
    }

    function closeModal() {
        document.getElementById('dFrame').src = "";
        hidedefModal('dDiv');
    }

    class StorageHelper {
        static isSupported() {
            try {
                const storage = window['localStorage'];
                const x = '__storage_test__';
                storage.setItem(x, x);
                storage.removeItem(x);
                return true;
            } catch (e) {
                return e instanceof DOMException && (
                        // everything except Firefox
                        e.name === 'QuotaExceededError' ||
                        // Firefox
                        e.name === 'NS_ERROR_DOM_QUOTA_REACHED') &&
                    // acknowledge QuotaExceededError only if there's something already stored
                    storage.length !== 0;
            }
        }

        static storeInLocalStorage(key, value) {
            if (!StorageHelper.isSupported()) {
                console.error('localStorage is not supported in this browser');
                return;
            }

            try {
                if (typeof value !== 'string') {
                    value = JSON.stringify(value);
                }
                localStorage.setItem(key, value);
            } catch (error) {
                console.error(`Error storing item in localStorage: ${error}`);
            }
        }

        static getFromLocalStorage(key) {
            if (!StorageHelper.isSupported()) {
                console.error('localStorage is not supported in this browser');
                return;
            }

            const value = localStorage.getItem(key);
            try {
                return JSON.parse(value);
            } catch (error) {
                return value;
            }
        }

        static removeItemFromLocalStorage(key) {
            if (!StorageHelper.isSupported()) {
                console.error('localStorage is not supported in this browser');
                return;
            }

            try {
                localStorage.removeItem(key);
            } catch (error) {
                console.error(`Error removing item from localStorage: ${error}`);
            }
        }

        static clearLocalStorage() {
            if (!StorageHelper.isSupported()) {
                console.error('localStorage is not supported in this browser');
                return;
            }

            try {
                localStorage.clear();
            } catch (error) {
                console.error(`Error clearing localStorage: ${error}`);
            }
        }

        static storeInSessionStorage(key, value) {
            if (!StorageHelper.isSupported()) {
                console.error('sessionStorage is not supported in this browser');
                return;
            }

            try {
                if (typeof value !== 'string') {
                    value = JSON.stringify(value);
                }
                sessionStorage.setItem(key, value);
            } catch (error) {
                console.error(`Error storing item in sessionStorage: ${error}`);
            }
        }

        static getFromSessionStorage(key) {
            if (!StorageHelper.isSupported()) {
                console.error('sessionStorage is not supported in this browser');
                return;
            }

            const value = sessionStorage.getItem(key);
            try {
                return JSON.parse(value);
            } catch (error) {
                return value;
            }
        }

        static removeItemFromSessionStorage(key) {
            if (!StorageHelper.isSupported()) {
                console.error('sessionStorage is not supported in this browser');
                return;
            }

            try {
                sessionStorage.removeItem(key);
            } catch (error) {
                console.error(`Error removing item from sessionStorage: ${error}`);
            }
        }

        static clearSessionStorage() {
            if (!StorageHelper.isSupported()) {
                console.error('sessionStorage is not supported in this browser');
                return;
            }

            try {
                sessionStorage.clear();
            } catch (error) {
                console.error(`Error clearing sessionStorage: ${error}`);
            }
        }
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
                placeholder: "<?= _translate("Type one or more character to search", escapeText: true); ?>",
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



    function formatStringToSnakeCase(inputStr) {
        // Remove special characters except underscore
        var result = inputStr.replace(/[^a-zA-Z0-9_ ]/g, '');
        // Convert to lowercase
        result = result.toLowerCase();
        // Replace spaces with underscore
        return result.replace(/ /g, '_');
    }



    function clearCache() {
        $.ajax({
            url: '/includes/clear-cache.php',
            cache: false,
            success: function(data) {
                Toastify({
                    text: "<?= _translate('Cache cleared successfully', escapeText: true) ?>",
                    duration: 3000,
                    style: {
                        background: 'green'
                    }
                }).showToast();
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
                    Toastify({
                        text: "<?= _translate("Sync Forced Successfully", true); ?>",
                        duration: 3000,
                        style: {
                            background: 'green'
                        }
                    }).showToast();
                });
        }
        $.unblockUI();
    }


    $(document).ready(function() {

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


        let phoneNumberDebounceTimeout;
        const countryCode = "<?= $countryCode ?? ''; ?>";

        $('.phone-number').on('input', function() {
            clearTimeout(phoneNumberDebounceTimeout);
            let inputElement = $(this);
            let phoneNumber = inputElement.val().trim();

            phoneNumberDebounceTimeout = setTimeout(function() {
                phoneNumber = inputElement.val().trim();

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
                            Toastify({
                                text: "<?= _translate('Invalid phone number. Please enter full phone number with the proper country code', escapeText: true) ?>",
                                duration: 3000,
                                style: {
                                    background: 'red'
                                }
                            }).showToast();
                        }
                    },
                    error: function() {
                        console.error("An error occurred while validating the phone number.");
                    }
                });
            }, 700);
        });

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

            var minLength = '<?= $minPatientIdLength ?? 0; ?>';

            if (patientId.length < minLength) {
                $(".lengthErr").remove();
                var txt = "<?= _translate('Please enter minimum length for Patient Id : ', escapeText: true); ?>" + minLength;
                $(this).parent().append('<span class="lengthErr" style="color:red;">' + txt + '</span>');
            } else {
                $(".lengthErr").remove();
            }


        });
    });
</script>
