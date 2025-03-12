<script type="text/javascript">
    /**
     * Function to manage hepatitis test inputs
     * Only one test (HBV or HCV) can have a value at a time
     */
    function hepatitisTestResults() {
        let testType = $("#hepatitisTestType").val().toLowerCase();

        if (testType === '' || testType === null) {
            alert("<?= _translate("Please select the test type", true); ?>");
        }

        // Handle initial state based on the test type parameter
        if (testType === "hcv") {
            $("#hbvCount").val("");
        } else if (testType === "hbv") {
            $("#hcvCount").val("");
        }
    }

    $(document).ready(function() {

        hepatitisTestResults();

        // Set up event listeners for both inputs
        $("#hcvCount, #hbvCount").on("input", function() {
            hepatitisTestResults();
        });

        // Update when test type changes
        $("#hepatitisTestType").on("change", function() {
            hepatitisTestResults();
        });
    });
</script>
