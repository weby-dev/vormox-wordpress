/*
 * Cloud VM Manager — admin script.
 *
 * Drives the provider connection actions without reloading the page. Written
 * against the DOM API only, so it carries no framework dependency.
 */
(function () {
    'use strict';

    var settings = window.cvmAdmin || {};
    var strings = settings.i18n || {};

    /**
     * Post an action to admin-ajax.php.
     *
     * @param {string} action     Registered AJAX action.
     * @param {Object} parameters Extra request parameters.
     *
     * @return {Promise<Object>} Resolved with the parsed response envelope.
     */
    function request(action, parameters) {
        var body = new URLSearchParams();

        body.append('action', action);
        body.append('nonce', settings.nonce || '');

        Object.keys(parameters || {}).forEach(function (key) {
            body.append(key, parameters[key]);
        });

        return fetch(settings.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: body.toString()
        }).then(function (response) {
            return response.json().catch(function () {
                return { success: false, data: { message: strings.unexpectedError } };
            });
        });
    }

    /**
     * Busy label for an action.
     *
     * @param {string} action Registered AJAX action.
     *
     * @return {string} Human readable status.
     */
    function busyLabel(action) {
        switch (action) {
            case 'cvm_test_connection':
                return strings.testing || strings.working;
            case 'cvm_connect_provider':
                return strings.connecting || strings.working;
            case 'cvm_disconnect_provider':
                return strings.disconnecting || strings.working;
            case 'cvm_refresh_token':
                return strings.refreshing || strings.working;
            default:
                return strings.working || '';
        }
    }

    /**
     * Write a message into the feedback area of a row.
     *
     * @param {HTMLElement} row     Provider row.
     * @param {string}      message Message to display.
     * @param {string}      state   One of busy, success or error.
     */
    function setFeedback(row, message, state) {
        var feedback = row.querySelector('.cvm-action-feedback');

        if (!feedback) {
            return;
        }

        feedback.textContent = message || '';
        feedback.className = 'cvm-action-feedback' + (state ? ' is-' + state : '');
    }

    /**
     * Reflect a new connection status on the row badge.
     *
     * @param {HTMLElement} row    Provider row.
     * @param {Object}      status Status payload returned by the endpoint.
     */
    function applyStatus(row, status) {
        if (!status || !status.value) {
            return;
        }

        var badge = row.querySelector('.cvm-status');

        if (badge) {
            badge.className = 'cvm-badge cvm-status cvm-status-' + status.value;
            badge.textContent = status.label || status.value;
        }
    }

    /**
     * Enable or disable every action button of a row.
     *
     * @param {HTMLElement} row      Provider row.
     * @param {boolean}     disabled Whether the buttons are disabled.
     */
    function setBusy(row, disabled) {
        row.querySelectorAll('.cvm-action').forEach(function (button) {
            button.disabled = disabled;
        });
    }

    /**
     * Run a provider action and report the outcome.
     *
     * @param {HTMLElement} button Clicked button.
     */
    function runAction(button) {
        var row = button.closest('.cvm-provider-row');

        if (!row) {
            return;
        }

        var action = button.getAttribute('data-action');
        var providerId = row.getAttribute('data-provider-id');

        if (button.hasAttribute('data-confirm') && !window.confirm(strings.confirmDisconnect)) {
            return;
        }

        setBusy(row, true);
        setFeedback(row, busyLabel(action), 'busy');

        request(action, { provider_id: providerId })
            .then(function (envelope) {
                var data = envelope && envelope.data ? envelope.data : {};

                applyStatus(row, data.status);

                if (envelope && envelope.success) {
                    setFeedback(row, data.message || (data.result && data.result.message) || '', 'success');

                    if (action !== 'cvm_test_connection') {
                        window.location.reload();
                    }

                    return;
                }

                setFeedback(row, data.message || strings.unexpectedError, 'error');
            })
            .catch(function () {
                setFeedback(row, strings.unexpectedError, 'error');
            })
            .finally(function () {
                setBusy(row, false);
            });
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.cvm-action').forEach(function (button) {
            button.addEventListener('click', function (event) {
                event.preventDefault();
                runAction(button);
            });
        });

        document.querySelectorAll('.cvm-delete').forEach(function (link) {
            link.addEventListener('click', function (event) {
                if (!window.confirm(link.getAttribute('data-confirm') || '')) {
                    event.preventDefault();
                }
            });
        });
    });
}());
