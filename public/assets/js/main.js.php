<script type="text/javascript">
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

    function forceMetadataSync(tbl){
		if(tbl != ""){
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
</script>
