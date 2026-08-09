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

    /* Actions whose result changes the row markup, so the page is reloaded. */
    var RELOADING_ACTIONS = ['cvm_connect_provider', 'cvm_disconnect_provider', 'cvm_refresh_token'];

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
            case 'cvm_sync_provider':
                return strings.syncing || strings.working;
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

                    if (RELOADING_ACTIONS.indexOf(action) !== -1) {
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

    /**
     * Run a machine row action and write the answer back into the row.
     *
     * @param {HTMLElement} button  Button that was pressed.
     * @param {string}      action  Registered AJAX action.
     * @param {string}      machine Machine row identifier.
     */
    function runMachineAction(button, action, machine) {
        var row = button.closest('tr');
        var feedback = row ? row.querySelector('[data-cvm-row-feedback]') : null;

        button.disabled = true;

        if (feedback) {
            feedback.textContent = strings.working || '';
            feedback.className = 'cvm-row-feedback is-busy';
        }

        request(action, { machine_id: machine })
            .then(function (envelope) {
                var data = (envelope && envelope.data) || {};
                var ok = Boolean(envelope && envelope.success);

                if (feedback) {
                    feedback.textContent = data.message || (ok ? '' : strings.unexpectedError);
                    feedback.className = 'cvm-row-feedback ' + (ok ? 'is-success' : 'is-error');
                }

                if (ok && data.machine) {
                    applyMachine(row, data.machine);
                }
            })
            .catch(function () {
                if (feedback) {
                    feedback.textContent = strings.unexpectedError || '';
                    feedback.className = 'cvm-row-feedback is-error';
                }
            })
            .finally(function () {
                button.disabled = false;
            });
    }

    /**
     * Write refreshed machine fields into a row without reloading the page.
     *
     * @param {HTMLElement} row     Table row of the machine.
     * @param {Object}      machine Machine payload.
     */
    function applyMachine(row, machine) {
        if (!row) {
            return;
        }

        var usage = row.querySelector('[data-cvm-usage]');

        if (usage && machine.usage_updated_at) {
            usage.textContent = '';

            [
                [strings.usageDisk, machine.disk_used_mb],
                [strings.usageTransfer, machine.bandwidth_used_mb]
            ].forEach(function (pair) {
                var line = document.createElement('div');

                line.textContent = String(pair[0] || '').replace('%s', formatSize(Number(pair[1]) || 0));
                usage.appendChild(line);
            });
        }
    }

    /**
     * Format a megabyte count the way the server does.
     *
     * @param {number} megabytes Size in megabytes.
     *
     * @return {string} Human readable size.
     */
    function formatSize(megabytes) {
        var units = ['MB', 'GB', 'TB'];
        var value = megabytes;
        var unit = 0;

        while (value >= 1024 && unit < units.length - 1) {
            value /= 1024;
            unit += 1;
        }

        return (Math.round(value * 10) / 10) + ' ' + units[unit];
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-cvm-sync-machine]').forEach(function (button) {
            button.addEventListener('click', function () {
                runMachineAction(button, 'cvm_sync_machine', button.getAttribute('data-cvm-sync-machine'));
            });
        });

        document.querySelectorAll('[data-cvm-refresh-usage]').forEach(function (button) {
            button.addEventListener('click', function () {
                runMachineAction(button, 'cvm_refresh_usage', button.getAttribute('data-cvm-refresh-usage'));
            });
        });

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
