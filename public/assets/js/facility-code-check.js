/**
 * Live facility-code validator for the Add / Edit Facility forms.
 *
 * As the user types, it asks the server (checkFacilityCode.php) to normalise the
 * code the same way the save path does and to check uniqueness, then shows inline
 * feedback ("only letters/numbers allowed", "already in use", "will be saved as
 * ABC1"). It also exposes isOk() so the form's submit handler can block a save
 * on a code the server would reject.
 *
 * All user-visible strings are passed in from PHP via init({messages}), so this
 * file stays translation-free.
 */
(function (window, $) {
    'use strict';

    var cfg = {
        facilityId: '',
        storedCode: '',
        endpoint: '/facilities/checkFacilityCode.php',
        messages: {}
    };

    // Optimistic until the server says otherwise, so a slow/failed check never
    // traps the user on the form.
    var lastOk = true;

    function debounce(fn, wait) {
        var timer;
        return function () {
            var self = this, args = arguments;
            clearTimeout(timer);
            timer = setTimeout(function () {
                fn.apply(self, args);
            }, wait);
        };
    }

    function msgEl() {
        return document.getElementById('facilityCodeMsg');
    }

    function render(res) {
        var el = msgEl();
        var input = document.getElementById('facilityCode');
        if (!el || !input) {
            return;
        }

        input.classList.remove('is-invalid', 'is-valid');

        if (res.status === 'empty') {
            el.textContent = '';
            el.className = 'form-text text-muted';
            lastOk = true;
            return;
        }

        if (res.status === 'invalid') {
            el.textContent = cfg.messages.invalid;
            el.className = 'form-text text-danger';
            input.classList.add('is-invalid');
            lastOk = false;
            return;
        }

        if (res.status === 'taken') {
            el.textContent = cfg.messages.taken + ' (' + res.normalized + ')';
            el.className = 'form-text text-danger';
            input.classList.add('is-invalid');
            lastOk = false;
            return;
        }

        // Available.
        lastOk = true;
        input.classList.add('is-valid');
        if (res.normalized !== res.raw) {
            el.textContent = cfg.messages.willSaveAs + ' ' + res.normalized;
        } else {
            el.textContent = cfg.messages.available;
        }
        el.className = 'form-text text-success';
    }

    function check() {
        var input = document.getElementById('facilityCode');
        if (!input) {
            return;
        }
        var raw = (input.value || '').trim();
        if (raw === '') {
            render({ status: 'empty' });
            return;
        }
        // An untouched existing code is saved verbatim by the server (legacy codes
        // are preserved as-is), so don't warn or show a "will be normalised" preview.
        if (cfg.storedCode !== '' && raw === cfg.storedCode) {
            render({ status: 'ok', raw: raw, normalized: raw });
            return;
        }
        $.post(cfg.endpoint, { code: raw, facilityId: cfg.facilityId }, null, 'json')
            .done(function (res) {
                render(res);
            })
            .fail(function () {
                // Don't block the user if the check itself fails.
                lastOk = true;
            });
    }

    var FacilityCodeCheck = {
        init: function (options) {
            options = options || {};
            cfg.facilityId = options.facilityId || '';
            cfg.storedCode = (options.storedCode || '').trim();
            cfg.messages = options.messages || {};
            if (options.endpoint) {
                cfg.endpoint = options.endpoint;
            }

            $(function () {
                var input = document.getElementById('facilityCode');
                if (!input) {
                    return;
                }
                var debounced = debounce(check, 350);
                input.addEventListener('input', debounced);
                input.addEventListener('blur', check);

                // Validate a pre-filled value (edit form) on load.
                if ((input.value || '').trim() !== '') {
                    check();
                }
            });
        },

        // Force an immediate (synchronous-intent) re-check, e.g. from a submit handler.
        check: check,

        // True unless the last server check flagged the code invalid/taken.
        isOk: function () {
            return lastOk;
        }
    };

    window.FacilityCodeCheck = FacilityCodeCheck;
})(window, jQuery);
