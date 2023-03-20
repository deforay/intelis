

$(document).on('select2:open', (e) => {
    const selectId = e.target.id

    $(".select2-search__field[aria-controls='select2-" + selectId + "-results']").each(function (
        key,
        value,
    ) {
        value.focus();
    })
});


function getAgeFromDob(dob) {
    if ($.trim(dob) != '') {
        let millisecondsBetweenDOBAnd1970 = Date.parse(dob);
        let millisecondsBetweenNowAnd1970 = Date.now();
        let ageInMilliseconds = millisecondsBetweenNowAnd1970 - millisecondsBetweenDOBAnd1970;
        //--We will leverage Date.parse and now method to calculate age in milliseconds refer here https://www.w3schools.com/jsref/jsref_parse.asp

        let milliseconds = ageInMilliseconds;
        let second = 1000;
        let minute = second * 60;
        let hour = minute * 60;
        let day = hour * 24;
        let month = day * 30;
        /*using 30 as base as months can have 28, 29, 30 or 31 days depending a month in a year it itself is a different piece of comuptation*/
        let year = day * 365;

        //let the age conversion begin
        let years = Math.round(milliseconds / year);
        let months = years * 12;
        //let days = years * 365;
        //let hours = Math.round(milliseconds / hour);
        //let seconds = Math.round(milliseconds / second);
        return {
            years: years,
            months: months,
            //days: days,
            //hours: hours,
            //seconds: seconds
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


// function to check if a given string is a number or not
function isNumeric(str) {
    if (typeof str != "string") return false // we only process strings!  
    return !isNaN(str) && // use type coercion to parse the _entirety_ of the string (`parseFloat` alone does not do this)...
        !isNaN(parseFloat(str)) // ...and ensure strings of whitespace fail
}


jQuery(".checkNum,.forceNumeric").keydown(function (e) {
    // Allow: backspace, delete, tab, escape, enter and .
    if ($.inArray(e.keyCode, [46, 8, 9, 27, 13, 110, 190]) !== -1 ||
        // Allow: Ctrl+A
        (e.keyCode == 65 && e.ctrlKey === true) ||
        // Allow: home, end, left, right
        (e.keyCode >= 35 && e.keyCode <= 39)) {
        // let it happen, don't do anything
        return;
    }
    // Ensure that it is a number and stop the keypress
    if ((e.shiftKey || (e.keyCode < 48 || e.keyCode > 57)) && (e.keyCode < 96 || e.keyCode > 105)) {
        e.preventDefault();
    }
});


// Create Base64 Object
let Base64 = { _keyStr: "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/=", encode: function (e) { var t = ""; var n, r, i, s, o, u, a; var f = 0; e = Base64._utf8_encode(e); while (f < e.length) { n = e.charCodeAt(f++); r = e.charCodeAt(f++); i = e.charCodeAt(f++); s = n >> 2; o = (n & 3) << 4 | r >> 4; u = (r & 15) << 2 | i >> 6; a = i & 63; if (isNaN(r)) { u = a = 64 } else if (isNaN(i)) { a = 64 } t = t + this._keyStr.charAt(s) + this._keyStr.charAt(o) + this._keyStr.charAt(u) + this._keyStr.charAt(a) } return t }, decode: function (e) { var t = ""; var n, r, i; var s, o, u, a; var f = 0; e = e.replace(/[^A-Za-z0-9\+\/\=]/g, ""); while (f < e.length) { s = this._keyStr.indexOf(e.charAt(f++)); o = this._keyStr.indexOf(e.charAt(f++)); u = this._keyStr.indexOf(e.charAt(f++)); a = this._keyStr.indexOf(e.charAt(f++)); n = s << 2 | o >> 4; r = (o & 15) << 4 | u >> 2; i = (u & 3) << 6 | a; t = t + String.fromCharCode(n); if (u != 64) { t = t + String.fromCharCode(r) } if (a != 64) { t = t + String.fromCharCode(i) } } t = Base64._utf8_decode(t); return t }, _utf8_encode: function (e) { e = e.replace(/\r\n/g, "\n"); var t = ""; for (var n = 0; n < e.length; n++) { var r = e.charCodeAt(n); if (r < 128) { t += String.fromCharCode(r) } else if (r > 127 && r < 2048) { t += String.fromCharCode(r >> 6 | 192); t += String.fromCharCode(r & 63 | 128) } else { t += String.fromCharCode(r >> 12 | 224); t += String.fromCharCode(r >> 6 & 63 | 128); t += String.fromCharCode(r & 63 | 128) } } return t }, _utf8_decode: function (e) { var t = ""; var n = 0; var r = c1 = c2 = 0; while (n < e.length) { r = e.charCodeAt(n); if (r < 128) { t += String.fromCharCode(r); n++ } else if (r > 191 && r < 224) { c2 = e.charCodeAt(n + 1); t += String.fromCharCode((r & 31) << 6 | c2 & 63); n += 2 } else { c2 = e.charCodeAt(n + 1); c3 = e.charCodeAt(n + 2); t += String.fromCharCode((r & 15) << 12 | (c2 & 63) << 6 | c3 & 63); n += 3 } } return t } }


function autoSelectSingleOption(selectId) {
    let nonEmptyOptions = $('#' + selectId).find("option[value!='']");
    if (nonEmptyOptions !== null && nonEmptyOptions.length === 1) {
        $('#' + selectId).val(nonEmptyOptions[0].value).trigger('change');
    }
}


let notificationCounts = 0;
let Notifier = new function () {
    this._alert = null;
    return {
        notify: function (f, j) {
            let n = this.main;
            let i = this.main.document;
            let a = i.documentElement;
            let h = n.innerWidth ? n.innerWidth + n.pageXOffset : a.clientWidth + a.scrollLeft;
            let e = n.innerHeight ? n.innerHeight + n.pageYOffset : a.clientHeight + a.scrollTop;
            let l = i.createElement("div");
            l.id = "Message";
            l.className = j || "";
            l.style.cssText = "position:fixed;white-space:nowrap;";
            if (l.className.length == 0) {
                l.className = "notifier"
            }
            l = i.body.insertBefore(l, i.body.firstChild);
            l.innerHTML = f;
            let g = l.offsetHeight;
            l.style.display = "none";
            let bottomPosition = g * notificationCounts;
            l.style.left = 0;
            l.setAttribute("style", "bottom:" + bottomPosition + "px;");
            l.style.bottom = bottomPosition;
            l.style.display = "block";
            notificationCounts++;
            setFading(l, 150, 0, 2000, function () {
                i.body.removeChild(l);
                notificationCounts--;
            })
        },
        init: function (a, b) {
            this.main = a;
            if (b == "" || b == "null") {
                b = "notifier"
            }
            this.classname = b || "";
            // if (this._alert == null) {
            //     this._alert = this.main.alert;
            //     this.main.alert = function (c, d) {
            //         Notifier.notify(c, d)
            //     }
            // }
        },
        shut: function () {
            // if (this._alert != null) {
            //     this.main.alert = this._alert;
            //     this._alert = null
            // }
        }
    }
};

function setFading(j, a, h, i, g) {
    let c = setInterval(function () {
        a = stepFX(a, h, 2);
        setOpacity(j, a / 100);
        if (a == h) {
            if (c) {
                clearInterval(c);
                c = null
            }
            if (typeof g == "function") {
                g()
            }
        }
    }, i / 50)
}

function setOpacity(a, b) {
    a.style.filter = "alpha(opacity=" + b * 100 + ")";
    a.style.opacity = b
}

function stepFX(a, d, c) {
    return a > d ? a - c > d ? a - c : d : a < d ? a + c < d ? a + c : d : a
}
//let __alert = window.alert;
let classN = "";
Notifier.init(window, "notifier");