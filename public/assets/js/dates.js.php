<script type="text/javascript">
    $(document).ready(function() {


        initDatePicker();
        initDateTimePicker();


        $('.expDate').datepicker({
            changeMonth: true,
            changeYear: true,
            onSelect: function() {
                $(this).change();
            },
            dateFormat: '<?= $_SESSION['jsDateFieldFormat'] ?? 'dd-M-yy'; ?>',
            timeFormat: "HH:mm",
            // minDate: "Today",
            yearRange: <?= (date('Y') - 100); ?> + ":" + "<?= date('Y') ?>"
        }).click(function() {
            $('.ui-datepicker-calendar').show();
        });


        if ($("#patientDob").length) {
            $("#patientDob").datepicker('option', {
                onSelect: function(dateText, inst) {
                    $("#sampleCollectionDate").datetimepicker("option", "minDate", $("#patientDob").datepicker("getDate"));
                    $(this).change();
                }
            });
        }

        if ($("#childDob").length) {
            $("#childDob").datepicker('option', {
                minDate: "-48m",
                onSelect: function(dateText, inst) {
                    $("#sampleCollectionDate").datetimepicker("option", "minDate", $("#childDob").datepicker("getDate"));
                    $(this).change();
                }
            });
        }

        if ($("#mothersDob").length) {
            $("#mothersDob").datepicker({
                changeMonth: true,
                changeYear: true,
                dateFormat: '<?= $_SESSION['jsDateFieldFormat'] ?? 'dd-M-yy'; ?>',
                maxDate: "Today",
                yearRange: <?php echo (date('Y') - 120); ?> + ":" + "<?= date('Y') ?>",
                onSelect: function(dateText, inst) {
                    $(this).change();
                }
            }).click(function() {
                $('.ui-datepicker-calendar').show();
            });
        }


        if ($("#nextAppointmentDate").length) {
            $('#nextAppointmentDate').datepicker({
                changeMonth: true,
                changeYear: true,
                onSelect: function() {
                    $(this).change();
                },
                dateFormat: '<?= $_SESSION['jsDateFieldFormat'] ?? 'dd-M-yy'; ?>',
                timeFormat: "HH:mm",
                yearRange: <?= (date('Y') - 100); ?> + ":" + "<?= date('Y') ?>"
            }).click(function() {
                $('.ui-datepicker-calendar').show();
            });
        }




        if ($('#sampleCollectionDate').length) {
            $('#sampleCollectionDate').datetimepicker({
                changeMonth: true,
                changeYear: true,
                dateFormat: '<?= $_SESSION['jsDateFieldFormat'] ?? 'dd-M-yy'; ?>',
                timeFormat: "HH:mm",
                maxDate: "Today",
                onChangeMonthYear: function(year, month, widget) {
                    setTimeout(function() {
                        $('.ui-datepicker-calendar').show();
                    });
                },
                yearRange: '<?= (date('Y') - 100); ?>:' + '<?= date('Y'); ?>'
            }).click(function() {
                $('.ui-datepicker-calendar').show();
            });

            $('#sampleCollectionDate').on('change', function() {

                var selectedDate = $(this).datetimepicker('getDate');
                var currentReceivedAtHubOn = $('#sampleReceivedAtHubOn').datetimepicker('getDate');
                var currentReceivedDate = $('#sampleReceivedDate').datetimepicker('getDate');
                var currentDispatchedDate = $('#sampleDispatchedDate').datetimepicker('getDate');

                if (selectedDate > currentReceivedAtHubOn) {
                    $('#sampleReceivedAtHubOn').val('');
                }
                if (selectedDate > currentReceivedDate) {
                    $('#sampleReceivedDate').val('');
                }
                if (selectedDate > currentDispatchedDate) {
                    $('#sampleDispatchedDate').val('');
                }

                $('#sampleReceivedAtHubOn').datetimepicker('option', 'minDate', selectedDate);
                $('#sampleReceivedDate').datetimepicker('option', 'minDate', selectedDate);
                $('#sampleDispatchedDate').datetimepicker('option', 'minDate', selectedDate);

                checkSampleDispatchDate();
            });
        }


        if ($('#sampleDispatchedDate').length) {
            $('#sampleDispatchedDate').datetimepicker({
                changeMonth: true,
                changeYear: true,
                dateFormat: '<?= $_SESSION['jsDateFieldFormat'] ?? 'dd-M-yy'; ?>',
                timeFormat: "HH:mm",
                maxDate: "Today",
                onChangeMonthYear: function(year, month, widget) {
                    setTimeout(function() {
                        $('.ui-datepicker-calendar').show();
                    });
                },
                yearRange: <?= (date('Y') - 100); ?> + ":" + "<?= date('Y') ?>"
            }).click(function() {
                $('.ui-datepicker-calendar').show();
            });
            $('#sampleDispatchedDate').on('change', function() {
                checkSampleDispatchDate();
            });
        }


        if ($('#sampleReceivedDate').length) {
            $('#sampleReceivedDate').datetimepicker({
                changeMonth: true,
                changeYear: true,
                dateFormat: '<?= $_SESSION['jsDateFieldFormat'] ?? 'dd-M-yy'; ?>',
                timeFormat: "HH:mm",
                maxDate: "Today",
                onChangeMonthYear: function(year, month, widget) {
                    setTimeout(function() {
                        $('.ui-datepicker-calendar').show();
                    });
                },
                yearRange: <?= (date('Y') - 100); ?> + ":" + "<?= date('Y') ?>"
            }).click(function() {
                $('.ui-datepicker-calendar').show();
            });

            $('#sampleReceivedDate').on('change', function() {

                var selectedDate = $(this).datetimepicker('getDate');
                var currentTestingDateAtLab = $('#sampleTestingDateAtLab').datetimepicker('getDate');

                if (selectedDate > currentTestingDateAtLab) {
                    $('#sampleTestingDateAtLab').val('');
                }
                $('#sampleTestedDateTime').val('');
                $('#sampleTestedDateTime, #sampleTestingDateAtLab').datetimepicker('option', 'minDate', selectedDate);
            });
        }
        if ($('#sampleTestedDateTime, #sampleTestingDateAtLab').length) {
            $('#sampleTestedDateTime, #sampleTestingDateAtLab').datetimepicker({
                changeMonth: true,
                changeYear: true,
                dateFormat: '<?= $_SESSION['jsDateFieldFormat'] ?? 'dd-M-yy'; ?>',
                timeFormat: "HH:mm",
                maxDate: "Today",
                onChangeMonthYear: function(year, month, widget) {
                    setTimeout(function() {
                        $('.ui-datepicker-calendar').show();
                    });
                },
                yearRange: <?= (date('Y') - 100); ?> + ":" + "<?= date('Y') ?>"
            }).click(function() {
                $('.ui-datepicker-calendar').show();
            });

            $('#sampleTestedDateTime, #sampleTestingDateAtLab').on('change', function() {
                var selectedDate = $(this).datetimepicker('getDate');
                var currentresultDispatchedOn = $('#resultDispatchedOn').datetimepicker('getDate');

                if (selectedDate > currentresultDispatchedOn) {
                    $('#resultDispatchedOn').val('');
                }
                $('#approvedOnDateTime').val('');

                $('#approvedOnDateTime').datetimepicker('option', 'minDate', selectedDate);
                $('#resultDispatchedOn').datetimepicker('option', 'minDate', selectedDate);
            });
        }

        if ($('#approvedOnDateTime').length) {
            $('#approvedOnDateTime').datetimepicker({
                changeMonth: true,
                changeYear: true,
                dateFormat: '<?= $_SESSION['jsDateFieldFormat'] ?? 'dd-M-yy'; ?>',
                timeFormat: "HH:mm",
                maxDate: "Today",
                onChangeMonthYear: function(year, month, widget) {
                    setTimeout(function() {
                        $('.ui-datepicker-calendar').show();
                    });
                },
                yearRange: <?= (date('Y') - 100); ?> + ":" + "<?= date('Y') ?>"
            }).click(function() {
                $('.ui-datepicker-calendar').show();
            });
            $('#approvedOnDateTime').on('change', function() {
                var selectedDate = $(this).datetimepicker('getDate');
                $('#resultDispatchedOn').val('');
                $('#resultDispatchedOn').datetimepicker('option', 'minDate', selectedDate);
            });
        }

        let dateFormatMask = '<?= $_SESSION['jsDateFormatMask'] ?? '99-aaa-9999'; ?>';
        $('.date').mask(dateFormatMask);
        $('.dateTime, .date-time').mask(dateFormatMask + ' 99:99');

    });


    function initDatePicker() {
        $('.date:not(.hasDatePicker)').each(function() {
            $(this).addClass('hasDatePicker').datepicker({
                changeMonth: true,
                changeYear: true,
                onSelect: function() {
                    $(this).change();
                },
                dateFormat: '<?= $_SESSION['jsDateFieldFormat'] ?? 'dd-M-yy'; ?>',
                maxDate: "Today",
                yearRange: <?= (date('Y') - 100); ?> + ":" + "<?= date('Y') ?>"
            }).click(function() {
                $('.ui-datepicker-calendar').show();
            });
        });
    }

    function initDateTimePicker() {

        $('.dateTime:not(.hasDateTimePicker), .date-time:not(.hasDateTimePicker)').each(function() {
            $(this).addClass('hasDateTimePicker').datetimepicker({
                changeMonth: true,
                changeYear: true,
                dateFormat: '<?= $_SESSION['jsDateFieldFormat'] ?? 'dd-M-yy'; ?>',
                timeFormat: "HH:mm",
                maxDate: "Today",
                onChangeMonthYear: function(year, month, widget) {
                    setTimeout(function() {
                        $('.ui-datepicker-calendar').show();
                    });
                },
                yearRange: <?= (date('Y') - 100); ?> + ":" + "<?= date('Y') ?>"
            }).click(function() {
                $('.ui-datepicker-calendar').show();
            });
        });
    }


    function checkSampleReceivedDate() {
        var sampleCollectionDate = $("#sampleCollectionDate").val().trim();
        var sampleReceivedDate = $("#sampleReceivedDate").val().trim();

        // Proceed only if both dates are provided
        if (sampleCollectionDate && sampleReceivedDate) {
            var date1 = new Date(sampleCollectionDate);
            var date2 = new Date(sampleReceivedDate);

            // Ensure date2 is not earlier than date1
            if (date2 < date1) {
                alert("<?= _translate('Sample Received at Testing Lab Date cannot be earlier than Sample Collection Date'); ?>");
                $("#sampleReceivedDate").val(""); // Clear the incorrect entry
            }
        }
    }

    function checkSampleReceivedAtHubDate() {
        var sampleCollectionDate = $("#sampleCollectionDate").val().trim();
        var sampleReceivedAtHubOn = $("#sampleReceivedAtHubOn").val().trim();

        if (sampleCollectionDate && sampleReceivedAtHubOn) {
            var date1 = new Date(sampleCollectionDate);
            var date2 = new Date(sampleReceivedAtHubOn);

            if (date2 < date1) {
                alert("<?= _translate('Sample Received at Hub Date cannot be earlier than Sample Collection Date'); ?>");
                $("#sampleReceivedAtHubOn").val("");
            }
        }
    }

    function checkSampleTestingDate() {
        var sampleCollectionDate = $("#sampleCollectionDate").val().trim();
        var sampleTestingDate = $("#sampleTestingDateAtLab").val().trim();

        if (sampleCollectionDate && sampleTestingDate) {
            var date1 = new Date(sampleCollectionDate);
            var date2 = new Date(sampleTestingDate);

            if (date2 < date1) {
                alert("<?= _translate('Sample Testing Date cannot be earlier than Sample Collection Date'); ?>");
                $("#sampleTestingDateAtLab").val("");
            }
        }
    }

    function checkSampleDispatchDate() {
        let collectionDate = $('#sampleCollectionDate').datetimepicker('getDate');
        let dispatchedDate = $('#sampleDispatchedDate').datetimepicker('getDate');

        // Ensure dispatchedDate is set to collectionDate if it's earlier than collectionDate
        if (!dispatchedDate || collectionDate > dispatchedDate) {
            $('#sampleDispatchedDate').datetimepicker('setDate', collectionDate);
        }
    }
</script>
