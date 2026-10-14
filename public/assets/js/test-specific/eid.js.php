<script type="text/javascript">
    let patientSearchTimeout = null;



    function showPatientList(patientCode, timeOutDuration) {
        if (patientSearchTimeout != null) {
            clearTimeout(patientSearchTimeout);
        }
        patientSearchTimeout = setTimeout(function() {
            patientSearchTimeout = null;

            $("#showEmptyResult").hide();
            if ($.trim(patientCode) != '') {
                $.post("/eid/requests/search-patients.php", {
                        childIdNo: $.trim(patientCode)
                    },
                    function(data) {
                        data = parseInt(data);
                        if (data >= 1) {
                            showModal('/eid/requests/patientModal.php?id=' + $.trim(patientCode), 900, 520);
                        } else {
                            $("#showEmptyResult").show();
                        }
                    }).fail(function(jqXHR, textStatus, errorThrown) {
                        if (typeof reportError === 'function') {
                            reportError('EID patient search failed: ' + textStatus + ' ' + errorThrown, { type: 'ajax_error', context: 'Status: ' + (jqXHR.status || '') + '\nResponse: ' + String(jqXHR.responseText || '').substring(0, 2000) });
                        }
                    });
            }


        }, timeOutDuration);

    }

    function showPatientListByMother(patientCode, timeOutDuration) {
        if (patientSearchTimeout != null) {
            clearTimeout(patientSearchTimeout);
        }
        patientSearchTimeout = setTimeout(function() {
            patientSearchTimeout = null;

            $("#showEmptyResult").hide();
            if ($.trim(patientCode) != '') {
                $.post("/eid/requests/search-patients.php", {
                        motherNo: $.trim(patientCode)
                    },
                    function(data) {
                        data = parseInt(data);
                        if (data >= 1) {
                            showModal('/eid/requests/patientModal.php?motherId=' + $.trim(patientCode), 900, 520);
                        } else {
                            $("#showEmptyResultMother").show();
                        }
                    }).fail(function(jqXHR, textStatus, errorThrown) {
                        if (typeof reportError === 'function') {
                            reportError('EID patient search failed: ' + textStatus + ' ' + errorThrown, { type: 'ajax_error', context: 'Status: ' + (jqXHR.status || '') + '\nResponse: ' + String(jqXHR.responseText || '').substring(0, 2000) });
                        }
                    });
            }


        }, timeOutDuration);

    }

    function calculateAgeInMonths() {
        const dobVal = $("#childDob").val();
        if (!dobVal || $.trim(dobVal) === '') return;

        const dateOfBirth = moment(dobVal, '<?= $_SESSION['jsDateRangeFormat'] ?? 'DD-MMM-YYYY'; ?>');
        if (!dateOfBirth.isValid()) return;

        const today = moment();

        const totalMonths = today.diff(dateOfBirth, 'months');
        const totalWeeks = today.diff(dateOfBirth, 'weeks');
        const totalDays = today.diff(dateOfBirth, 'days');

        // Age in months (existing field — "Age en mois")
        $("#childAge").val(totalMonths);

        // Age in weeks
        $("#childAgeInWeeks").val(totalWeeks);

        // Age in days
        $("#childAgeInDays").val(totalDays);
    }

    // Function to calculate the total age in months
    function calculateTotalAge() {
        let ageInMonths = $('#childAge').val() ? parseFloat($('#childAge').val()) : 0;
        let ageInWeeks = $('#childAgeInWeeks').val() ? parseFloat($('#childAgeInWeeks').val()) : 0;

        // Convert weeks to months (assuming 4 weeks per month)
        let ageInMonthsFromWeeks = ageInWeeks / 4;

        // Calculate total age in months
        let totalAge = ageInMonths + ageInMonthsFromWeeks;

        // Check if the total age exceeds 24 months
        if (totalAge > 24) {
            alert("<?= _translate('The total age must not exceed 24 months.', true); ?>");
        }
    }

    $(document).ready(function() {
        if ($('#childAgeInWeeks').length) {
            // The childAgeInWeeks element exists, attach the event handler
            $('#childAge, #childAgeInWeeks').on('change', calculateTotalAge);
        }
    });

    // A result is never saved without the testing platform it was read on. The
    // save helpers refuse one, so the form asks for the platform first. Applied
    // just before validation, from what the form is about to send: a result field
    // that is disabled is not sent, so it keeps the saved result and needs nothing.
    (function() {
        var MARK = 'platformForResult';

        function isRejected($form) {
            return $form.find('select[name="isSampleRejected"]').val() === 'yes' ||
                $form.find('input[name="isSampleRejected"]:checked').val() === 'yes';
        }

        function sendsAResult($form) {
            var entered = false;
            $form.find('[name="result"]').filter(':enabled').each(function() {
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
            var $platform = $form.find('[name="eidPlatform"]');
            if (!$platform.length) {
                return;
            }
            if (!isRejected($form) && sendsAResult($form)) {
                if (!$platform.hasClass('isRequired')) {
                    $platform.addClass('isRequired ' + MARK)
                        .attr('title', "<?= _jsTranslate('Please select the testing platform'); ?>");
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