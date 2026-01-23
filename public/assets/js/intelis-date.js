(function (global) {
    if (!global || !global.dayjs) {
        return;
    }

    var dayjs = global.dayjs;

    if (typeof dayjs.extend === 'function') {
        if (typeof global.dayjs_plugin_customParseFormat !== 'undefined') {
            dayjs.extend(global.dayjs_plugin_customParseFormat);
        }
        if (typeof global.dayjs_plugin_utc !== 'undefined') {
            dayjs.extend(global.dayjs_plugin_utc);
        }
        if (typeof global.dayjs_plugin_timezone !== 'undefined') {
            dayjs.extend(global.dayjs_plugin_timezone);
        }
    }

    // IntelisDate is a thin wrapper around Day.js for new code.
    // Daterangepicker still uses Moment.js, so we do not touch those usages yet.
    function IntelisDate() {
        return dayjs.apply(null, arguments);
    }

    IntelisDate.dayjs = dayjs;
    if (typeof dayjs.utc === 'function') {
        IntelisDate.utc = function () {
            return dayjs.utc.apply(dayjs, arguments);
        };
    }
    if (typeof dayjs.tz === 'function') {
        IntelisDate.tz = function () {
            return dayjs.tz.apply(dayjs, arguments);
        };
    }

    global.IntelisDate = IntelisDate;
})(window);
