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
                        artPatientNo: $.trim(patientCode)
                    },
                    function(data) {
                        if (data >= '1') {
                            showModal('patientModal.php?artNo=' + $.trim(patientCode), 900, 520);
                        } else {
                            $("#showEmptyResult").show();
                        }
                    });
            }


        }, timeOutDuration);

    }

    function calculateAgeInMonths() {
        let dateOfBirth = moment($("#childDob").val(), '<?= $_SESSION['jsDateRangeFormat'] ?? 'DD-MMM-YYYY'; ?>');
        $("#childAge").val(moment().diff(dateOfBirth, 'months'));
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
