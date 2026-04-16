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
</script>