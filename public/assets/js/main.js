

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
    let dobDate = dayjs(dob);
    if (dob != '' && dobDate.isValid()) {
        let currentDate = dayjs();
        let ageInYears = currentDate.diff(dobDate, 'year');
        let ageInMonths = currentDate.diff(dobDate, 'month') % 12;
        return {
            years: ageInYears,
            months: ageInMonths
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


jQuery('.forceNumeric').on('input', function () {
    this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1');
});

jQuery('#ageInYears').on('input', function () {
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

function autoSelectSingleOption(selectId) {
    let nonEmptyOptions = $('#' + selectId).find("option[value!='']");
    if (nonEmptyOptions.length === 1) {
        $('#' + selectId).val(nonEmptyOptions.val()).trigger('change');
    }
};


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
    }, i / 50);
}

function setOpacity(a, b) {
    a.style.filter = "alpha(opacity=" + b * 100 + ")";
    a.style.opacity = b;
};

function stepFX(a, d, c) {
    return (a > d ? a - c > d ? a - c : d : a < d ? a + c < d ? a + c : d : a);
};
//let __alert = window.alert;
let classN = "";
Notifier.init(window, "notifier");
