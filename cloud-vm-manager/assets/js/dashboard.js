/*
 * Cloud VM Manager — customer dashboard.
 *
 * Refreshes the live status, metrics and storage of a machine, and draws the
 * usage charts as inline SVG. No charting library is loaded: the series are
 * small and a polyline is all they need.
 */
(function () {
    'use strict';

    var settings = window.cvmDashboard || {};
    var strings = settings.i18n || {};

    var CHART_WIDTH = 600;
    var CHART_HEIGHT = 180;
    var CHART_PADDING = 8;
    var SVG_NS = 'http://www.w3.org/2000/svg';

    /**
     * Post an action to admin-ajax.php.
     *
     * @param {string} action     Registered AJAX action.
     * @param {Object} parameters Extra request parameters.
     *
     * @return {Promise<Object>} Parsed response envelope.
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
            return response.json();
        });
    }

    /**
     * Draw one or more series into an SVG element.
     *
     * @param {SVGElement} svg    Target element.
     * @param {Array}      series List of {values, color} objects.
     * @param {number}     max    Upper bound of the value axis, or 0 to derive it.
     */
    function draw(svg, series, max) {
        while (svg.firstChild) {
            svg.removeChild(svg.firstChild);
        }

        var longest = 0;
        var ceiling = max || 0;

        series.forEach(function (entry) {
            longest = Math.max(longest, entry.values.length);

            entry.values.forEach(function (value) {
                ceiling = Math.max(ceiling, value);
            });
        });

        if (longest < 2) {
            var label = document.createElementNS(SVG_NS, 'text');

            label.setAttribute('x', String(CHART_WIDTH / 2));
            label.setAttribute('y', String(CHART_HEIGHT / 2));
            label.setAttribute('text-anchor', 'middle');
            label.setAttribute('fill', 'currentColor');
            label.setAttribute('opacity', '0.6');
            label.setAttribute('font-size', '14');
            label.textContent = strings.noData || '';
            svg.appendChild(label);

            return;
        }

        if (ceiling <= 0) {
            ceiling = 1;
        }

        [0.25, 0.5, 0.75].forEach(function (fraction) {
            var y = CHART_PADDING + (CHART_HEIGHT - CHART_PADDING * 2) * fraction;
            var line = document.createElementNS(SVG_NS, 'line');

            line.setAttribute('x1', '0');
            line.setAttribute('x2', String(CHART_WIDTH));
            line.setAttribute('y1', String(y));
            line.setAttribute('y2', String(y));
            line.setAttribute('stroke', 'currentColor');
            line.setAttribute('stroke-opacity', '0.12');
            line.setAttribute('stroke-width', '1');
            svg.appendChild(line);
        });

        series.forEach(function (entry) {
            if (entry.values.length < 2) {
                return;
            }

            var step = CHART_WIDTH / (entry.values.length - 1);
            var usable = CHART_HEIGHT - CHART_PADDING * 2;

            var points = entry.values.map(function (value, index) {
                var x = index * step;
                var y = CHART_PADDING + usable - (value / ceiling) * usable;

                return x.toFixed(1) + ',' + y.toFixed(1);
            });

            var polyline = document.createElementNS(SVG_NS, 'polyline');

            polyline.setAttribute('points', points.join(' '));
            polyline.setAttribute('fill', 'none');
            polyline.setAttribute('stroke', entry.color);
            polyline.setAttribute('stroke-width', '2');
            polyline.setAttribute('stroke-linejoin', 'round');
            polyline.setAttribute('stroke-linecap', 'round');
            svg.appendChild(polyline);
        });
    }

    /**
     * Resolve a CSS custom property to its computed value.
     *
     * @param {HTMLElement} element Element to read from.
     * @param {string}      name    Property name.
     *
     * @return {string} Colour value.
     */
    function colour(element, name) {
        return getComputedStyle(element).getPropertyValue(name).trim() || '#2271b1';
    }

    /**
     * Paint the metrics payload into the page.
     *
     * @param {HTMLElement} root    Machine view root.
     * @param {Object}      metrics Metrics payload.
     */
    function applyMetrics(root, metrics) {
        var message = root.querySelector('[data-cvm-metrics-message]');

        if (message) {
            message.textContent = metrics.available ? '' : (metrics.message || '');
        }

        ['cpu', 'disk'].forEach(function (key) {
            var value = key === 'cpu' ? metrics.current.cpu_percent : metrics.current.disk_percent;
            var bar = root.querySelector('[data-cvm-gauge="' + key + '"]');
            var text = root.querySelector('[data-cvm-gauge-value="' + key + '"]');

            if (bar) {
                bar.style.width = Math.max(0, Math.min(Number(value) || 0, 100)) + '%';
            }

            if (text) {
                text.textContent = (Number(value) || 0).toFixed(1) + '%';
            }
        });

        var usage = root.querySelector('[data-cvm-chart="usage"]');

        if (usage) {
            draw(
                usage,
                [
                    { values: metrics.series.cpu || [], color: colour(root, '--cvm-cpu') },
                    { values: metrics.series.memory || [], color: colour(root, '--cvm-memory') }
                ],
                100
            );
        }

        var network = root.querySelector('[data-cvm-chart="network"]');

        if (network) {
            draw(
                network,
                [
                    { values: metrics.series.netin || [], color: colour(root, '--cvm-cpu') },
                    { values: metrics.series.netout || [], color: colour(root, '--cvm-memory') }
                ],
                0
            );
        }
    }

    /**
     * Replace the status pill of a card or the machine view.
     *
     * @param {HTMLElement} root   Element carrying the pill.
     * @param {Object}      status Status payload.
     */
    function applyStatus(root, status) {
        var pill = root.querySelector('[data-cvm-live-status]');

        if (!pill) {
            return;
        }

        var value = status.live || status.stored || '';

        pill.textContent = value;
        pill.className = 'cvm-pill cvm-pill-' + String(value).toLowerCase();

        var address = root.querySelector('[data-cvm-field="ip_address"]');

        if (address && status.ip_address) {
            address.textContent = status.ip_address;
        }
    }

    /**
     * Reload metrics for the machine view.
     *
     * @param {HTMLElement} root Machine view root.
     */
    function refreshMetrics(root) {
        var machineId = root.getAttribute('data-machine-id');
        var timeframe = root.querySelector('[data-cvm-timeframe]');

        request('cvm_vm_metrics', {
            machine_id: machineId,
            timeframe: timeframe ? timeframe.value : 'hour'
        })
            .then(function (envelope) {
                if (envelope && envelope.success && envelope.data.metrics) {
                    applyMetrics(root, envelope.data.metrics);
                }
            })
            .catch(function () {
                var message = root.querySelector('[data-cvm-metrics-message]');

                if (message) {
                    message.textContent = strings.failed || '';
                }
            });
    }

    /**
     * Reload the live status of one element that carries a machine id.
     *
     * @param {HTMLElement} root Element carrying data-machine-id.
     */
    function refreshStatus(root) {
        request('cvm_vm_status', { machine_id: root.getAttribute('data-machine-id') })
            .then(function (envelope) {
                if (envelope && envelope.success && envelope.data.status) {
                    applyStatus(root, envelope.data.status);
                }
            })
            .catch(function () {
                /* The stored status stays on screen. */
            });
    }

    /**
     * Print a message into the control feedback area.
     *
     * @param {HTMLElement} root    Machine view root.
     * @param {string}      message Message to display.
     * @param {string}      state   One of busy, success or error.
     */
    function setControlFeedback(root, message, state) {
        var feedback = root.querySelector('.cvm-control-feedback');

        if (!feedback) {
            return;
        }

        feedback.textContent = message || '';
        feedback.className = 'cvm-control-feedback' + (state ? ' is-' + state : '');
    }

    /**
     * Enable or disable every control of the machine view.
     *
     * @param {HTMLElement} root     Machine view root.
     * @param {boolean}     disabled Whether the controls are disabled.
     */
    function setControlsBusy(root, disabled) {
        root.querySelectorAll('.cvm-control').forEach(function (button) {
            button.disabled = disabled;
        });
    }

    /**
     * Run a control action for the machine currently on screen.
     *
     * @param {HTMLElement} root   Machine view root.
     * @param {HTMLElement} button Clicked control.
     */
    function runControl(root, button) {
        var action = button.getAttribute('data-cvm-action');
        var confirmation = button.getAttribute('data-cvm-confirm');

        if (confirmation && !window.confirm(confirmation)) {
            return;
        }

        var parameters = { machine_id: root.getAttribute('data-machine-id') };

        if (action === 'cvm_vm_power') {
            parameters.vm_action = button.getAttribute('data-cvm-power') || '';
        }

        if (action === 'cvm_vm_rebuild') {
            var iso = document.getElementById('cvm-rebuild-iso');

            parameters.iso_id = iso ? iso.value : '0';
        }

        if (action === 'cvm_vm_password') {
            var password = document.getElementById('cvm-new-password');

            parameters.password = password ? password.value : '';

            if (!parameters.password) {
                setControlFeedback(root, strings.passwordRequired || '', 'error');

                return;
            }
        }

        setControlsBusy(root, true);
        setControlFeedback(root, strings.working || '', 'busy');

        request(action, parameters)
            .then(function (envelope) {
                var data = envelope && envelope.data ? envelope.data : {};

                if (envelope && envelope.success) {
                    setControlFeedback(root, data.message || '', 'success');

                    if (action === 'cvm_vm_password') {
                        var field = document.getElementById('cvm-new-password');

                        if (field) {
                            field.value = '';
                        }
                    }

                    refreshStatus(root);

                    return;
                }

                setControlFeedback(root, data.message || strings.failed || '', 'error');
            })
            .catch(function () {
                setControlFeedback(root, strings.failed || '', 'error');
            })
            .finally(function () {
                setControlsBusy(root, false);
            });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var machineView = document.querySelector('.cvm-machine-view');

        document.querySelectorAll('.cvm-machine-card[data-machine-id]').forEach(function (card) {
            refreshStatus(card);
        });

        if (!machineView) {
            return;
        }

        refreshMetrics(machineView);

        var timeframe = machineView.querySelector('[data-cvm-timeframe]');

        if (timeframe) {
            timeframe.addEventListener('change', function () {
                refreshMetrics(machineView);
            });
        }

        machineView.querySelectorAll('.cvm-control').forEach(function (button) {
            button.addEventListener('click', function (event) {
                event.preventDefault();
                runControl(machineView, button);
            });
        });

        var invoice = machineView.querySelector('[data-cvm-invoice]');

        if (invoice) {
            invoice.addEventListener('click', function () {
                var url = settings.ajaxUrl
                    + '?action=cvm_vm_invoice&nonce=' + encodeURIComponent(settings.nonce || '')
                    + '&machine_id=' + encodeURIComponent(machineView.getAttribute('data-machine-id') || '');

                window.location.href = url;
            });
        }

        var interval = Number(settings.refreshInterval) || 30000;

        window.setInterval(function () {
            if (document.hidden) {
                return;
            }

            refreshMetrics(machineView);
            refreshStatus(machineView);
        }, interval);
    });
}());
