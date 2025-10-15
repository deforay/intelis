<?php

//require_once(__DIR__."/../../../bootstrap.php");
use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Registries\ContainerRegistry;


/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
?>
<script type="text/javascript">
    // Extend dayjs with the customParseFormat plugin if available
    if (dayjs.extend && typeof dayjs_plugin_customParseFormat !== 'undefined') {
        dayjs.extend(dayjs_plugin_customParseFormat);
    }

    const dayjsDateFormat = "<?= $_SESSION['dayjsDateFieldFormat'] ?>"; // e.g., "DD-MM-YYYY" or "DD-MMM-YYYY"

    // Helper Functions
    function parseDate(value) {
        return dayjs(value, dayjsDateFormat);
    }

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
            yearRange: "<?= DateUtility::getYearMinus(100) . ":" . DateUtility::getCurrentYear(); ?>"
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
                yearRange: "<?= DateUtility::getYearMinus(100) . ":" . DateUtility::getCurrentYear(); ?>",
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
                yearRange: "<?= DateUtility::getYearMinus(100) . ":" . DateUtility::getCurrentYear(); ?>"
            }).click(function() {
                $('.ui-datepicker-calendar').show();
            });
        }

        if ($('#sampleCollectionDate').length && !$('#sampleCollectionDate').hasClass('daterangefield')) {
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
                yearRange: "<?= DateUtility::getYearMinus(100) . ":" . DateUtility::getCurrentYear(); ?>"
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
                $('#sampleDispatchedDate').datetimepicker('option', 'maxDate', "+7d");
                if ('<?= $_SESSION['formId'] ?? $general->getGlobalConfig('vl_form'); ?>' != 2) {
                    checkSampleDispatchDate();
                }
            });
        }

        if ($('#sampleDispatchedDate').length && !$('#sampleDispatchedDate').hasClass('daterangefield')) {
            $('#sampleDispatchedDate').datetimepicker({
                changeMonth: true,
                changeYear: true,
                dateFormat: '<?= $_SESSION['jsDateFieldFormat'] ?? 'dd-M-yy'; ?>',
                timeFormat: "HH:mm",
                maxDate: "+7d",
                onChangeMonthYear: function(year, month, widget) {
                    setTimeout(function() {
                        $('.ui-datepicker-calendar').show();
                    });
                },
                yearRange: "<?= DateUtility::getYearMinus(100) . ":" . DateUtility::getCurrentYear(); ?>"
            }).click(function() {
                $('.ui-datepicker-calendar').show();
            });
            $('#sampleDispatchedDate').on('change', function() {
                checkSampleDispatchDate();
            });
        }

        if ($('#sampleReceivedDate').length && !$('#sampleReceivedDate').hasClass('daterangefield')) {
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
                yearRange: "<?= DateUtility::getYearMinus(100) . ":" . DateUtility::getCurrentYear(); ?>"
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

        if ($('#sampleTestedDateTime, #sampleTestingDateAtLab').length && !$('#sampleTestedDateTime, #sampleTestingDateAtLab').hasClass('daterangefield')) {
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
                yearRange: <?= DateUtility::getYearMinus(100); ?> + ":" + "<?= date('Y') ?>"
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

        if ($('#approvedOnDateTime').length && !$('#approvedOnDateTime').hasClass('daterangefield')) {
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
                yearRange: <?= DateUtility::getYearMinus(100); ?> + ":" + "<?= date('Y') ?>"
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
                yearRange: <?= DateUtility::getYearMinus(100); ?> + ":" + "<?= date('Y') ?>"
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
                yearRange: <?= DateUtility::getYearMinus(100); ?> + ":" + "<?= date('Y') ?>"
            }).click(function() {
                $('.ui-datepicker-calendar').show();
            });
        });
    }

    function checkSampleReceivedDate() {
        var date1 = parseDate($("#sampleCollectionDate").val());
        var date2 = parseDate($("#sampleReceivedDate").val());

        if (date1.isValid() && date2.isValid()) {
            if (date2.isBefore(date1)) {
                alert("<?= _translate('Sample Received at Testing Lab Date cannot be earlier than Sample Collection Date', true); ?>");
                $("#sampleReceivedDate").val("");
            }
        }
    }

    function checkSampleReceivedAtHubDate() {
        var date1 = parseDate($("#sampleCollectionDate").val());
        var date2 = parseDate($("#sampleReceivedAtHubOn").val());

        if (date1.isValid() && date2.isValid()) {
            if (date2.isBefore(date1)) {
                alert("<?= _translate('Sample Received at Hub Date cannot be earlier than Sample Collection Date', true); ?>");
                $("#sampleReceivedAtHubOn").val("");
            }
        }
    }

    function checkSampleTestingDate() {
        var date1 = parseDate($("#sampleCollectionDate").val());
        var date2 = parseDate($("#sampleTestingDateAtLab").val());

        if (date1.isValid() && date2.isValid()) {
            if (date2.isBefore(date1)) {
                alert("<?= _translate('Sample Testing Date cannot be earlier than Sample Collection Date', true); ?>");
                $("#sampleTestingDateAtLab").val("");
            }
        }
    }

    function checkSampleDispatchDate() {
        var date1 = parseDate($("#sampleCollectionDate").val());
        var date2 = parseDate($("#sampleDispatchedDate").val());

        if (date1.isValid() && date2.isValid()) {
            if (date2.isBefore(date1)) {
                $('#sampleDispatchedDate').val($("#sampleCollectionDate").val());
            }
        }
    }
</script>