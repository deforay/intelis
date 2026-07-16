(function (window, document, $) {
    'use strict';

    var config = window.InterfaceConnectionsConfig;
    if (!config || !$) {
        return;
    }

    var activeCodeId = null;
    var countdownTimer = null;

    function notify(type, message) {
        if (window.toastr && typeof window.toastr[type] === 'function') {
            window.toastr[type](message);
            return;
        }
        if (type === 'error') {
            window.alert(message);
        }
    }

    function copyValue(elementId) {
        var input = document.getElementById(elementId);
        if (!input) {
            return;
        }
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(input.value).then(function () {
                notify('success', config.messages.copied);
            });
            return;
        }
        input.select();
        document.execCommand('copy');
        input.setSelectionRange(0, 0);
        notify('success', config.messages.copied);
    }

    function clearCode() {
        activeCodeId = null;
        window.clearInterval(countdownTimer);
        countdownTimer = null;
        $('#interfaceConnectionCode').val('');
        $('#interfaceCodeCountdown').text('--:--');
        $('#interfaceCodePanel').hide();
    }

    function startCountdown(expiresAt) {
        var expiry = Date.parse(expiresAt);
        window.clearInterval(countdownTimer);
        function update() {
            var remaining = Math.max(0, Math.floor((expiry - Date.now()) / 1000));
            var minutes = String(Math.floor(remaining / 60)).padStart(2, '0');
            var seconds = String(remaining % 60).padStart(2, '0');
            $('#interfaceCodeCountdown').text(minutes + ':' + seconds);
            if (remaining <= 0) {
                clearCode();
            }
        }
        update();
        countdownTimer = window.setInterval(update, 1000);
    }

    function postAction(action, extra, onSuccess) {
        var payload = $.extend({
            action: action,
            facilityId: config.facilityId,
            csrf_token: config.csrfToken
        }, extra || {});

        $.ajax({
            url: config.endpoint,
            method: 'POST',
            dataType: 'json',
            data: payload,
            headers: {'X-CSRF-Token': config.csrfToken}
        }).done(onSuccess).fail(function (xhr) {
            var message = xhr.responseJSON && xhr.responseJSON.error
                ? xhr.responseJSON.error.message
                : config.messages.requestFailed;
            notify('error', message);
        });
    }

    function showCode(connectionCode) {
        activeCodeId = connectionCode.activationCodeId;
        $('#interfaceCodeTitle').text(
            connectionCode.purpose === 'reconnect'
                ? config.messages.reconnectTitle
                : config.messages.newTitle
        );
        $('#interfaceConnectionCode').val(connectionCode.activationCode);
        $('#interfaceCodePanel').show();
        startCountdown(connectionCode.expiresAt);
    }

    function ensureNoActiveCode() {
        if (activeCodeId !== null) {
            notify('error', config.messages.activeCode);
            return false;
        }
        return true;
    }

    $('#copyInterfaceUrl').on('click', function () {
        copyValue('interfaceIntelisUrl');
    });
    $('#copyInterfaceCode').on('click', function () {
        copyValue('interfaceConnectionCode');
    });
    $('#generateInterfaceCode').on('click', function () {
        if (ensureNoActiveCode()) {
            postAction('generate-new', {}, function (response) {
                showCode(response.connectionCode);
            });
        }
    });
    $('.interface-reconnect').on('click', function () {
        if (ensureNoActiveCode()) {
            postAction('generate-reconnect', {
                installationId: $(this).data('installation-id')
            }, function (response) {
                showCode(response.connectionCode);
            });
        }
    });
    $('.interface-revoke').on('click', function () {
        if (!window.confirm(config.messages.confirmRevoke)) {
            return;
        }
        postAction('revoke-installation', {
            installationId: $(this).data('installation-id')
        }, function () {
            window.location.reload();
        });
    });
    $('#cancelInterfaceCode').on('click', function () {
        if (activeCodeId === null) {
            return;
        }
        postAction('revoke-code', {activationCodeId: activeCodeId}, function () {
            clearCode();
        });
    });
})(window, document, window.jQuery);
