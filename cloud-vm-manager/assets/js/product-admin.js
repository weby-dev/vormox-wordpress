/*
 * Cloud VM Manager — product edit screen.
 *
 * Keeps the zone, image and tier selectors in step with the chosen provider and
 * recalculates the margin summary as the configuration changes.
 */
(function () {
    'use strict';

    var settings = window.cvmProduct || {};
    var RESOURCES = ['cpu', 'ram', 'disk', 'bandwidth'];

    /**
     * @return {HTMLElement|null} The product panel, when it is on the page.
     */
    function panel() {
        return document.getElementById('cvm_product_data');
    }

    /**
     * @param {string} id Element id.
     *
     * @return {HTMLSelectElement|null} The select, when present.
     */
    function select(id) {
        var element = document.getElementById(id);

        return element instanceof HTMLSelectElement ? element : null;
    }

    /**
     * Replace the options of a select, keeping the current value when possible.
     *
     * @param {HTMLSelectElement} element     Select to refill.
     * @param {Object}            options     Value to label map.
     * @param {string}            placeholder Label of the empty option.
     */
    function fill(element, options, placeholder) {
        if (!element) {
            return;
        }

        var previous = element.value;

        element.innerHTML = '';

        var empty = document.createElement('option');
        empty.value = '0';
        empty.textContent = placeholder;
        element.appendChild(empty);

        Object.keys(options || {}).forEach(function (value) {
            var option = document.createElement('option');
            var entry = options[value];

            option.value = value;

            if (entry && typeof entry === 'object') {
                option.textContent = entry.label;
                option.setAttribute('data-provider-price', String(entry.provider));
                option.setAttribute('data-selling-price', String(entry.selling));
            } else {
                option.textContent = String(entry);
            }

            element.appendChild(option);
        });

        if (element.querySelector('option[value="' + previous + '"]')) {
            element.value = previous;
        }
    }

    /**
     * Months covered by the selected billing cycle.
     *
     * @return {number} Number of months.
     */
    function months() {
        var cycle = select('cvm_billing_cycle');
        var map = settings.months || {};

        return cycle && map[cycle.value] ? map[cycle.value] : 1;
    }

    /**
     * Recalculate and print the margin summary.
     */
    function refreshSummary() {
        var container = panel();

        if (!container) {
            return;
        }

        var providerCost = 0;
        var sellingPrice = 0;

        RESOURCES.forEach(function (resource) {
            var element = select('cvm_' + resource + '_price_id');

            if (!element || !element.selectedOptions.length) {
                return;
            }

            var option = element.selectedOptions[0];

            providerCost += parseFloat(option.getAttribute('data-provider-price') || '0') || 0;
            sellingPrice += parseFloat(option.getAttribute('data-selling-price') || '0') || 0;
        });

        var cycleMonths = months();

        providerCost *= cycleMonths;
        sellingPrice *= cycleMonths;

        var mode = select('cvm_price_mode');
        var manual = document.getElementById('cvm_manual_price');

        if (mode && mode.value === 'manual' && manual) {
            sellingPrice = parseFloat(String(manual.value).replace(',', '.')) || 0;
        }

        var markup = sellingPrice - providerCost;

        print(container, 'provider_cost', providerCost.toFixed(2));
        print(container, 'selling_price', sellingPrice.toFixed(2));
        print(container, 'markup', markup.toFixed(2));
        print(container, 'markup_percent', providerCost > 0 ? (markup / providerCost * 100).toFixed(2) : '0.00');
        print(container, 'margin_percent', sellingPrice > 0 ? (markup / sellingPrice * 100).toFixed(2) : '0.00');
    }

    /**
     * @param {HTMLElement} container Panel element.
     * @param {string}      key       Summary key.
     * @param {string}      value     Value to display.
     */
    function print(container, key, value) {
        var target = container.querySelector('[data-cvm-summary="' + key + '"]');

        if (target) {
            target.textContent = value;
        }
    }

    /**
     * Show the fixed price field only when the fixed mode is selected.
     */
    function toggleManualPrice() {
        var mode = select('cvm_price_mode');
        var row = document.querySelector('.cvm-manual-price');

        if (mode && row) {
            row.style.display = mode.value === 'manual' ? '' : 'none';
        }
    }

    /**
     * Reload the catalogue for the current provider, plan type and zone.
     *
     * @param {boolean} includeZones Whether the zone list should be replaced.
     */
    function reload(includeZones) {
        var provider = select('cvm_provider_id');
        var planType = select('cvm_plan_type');
        var zone = select('cvm_zone_remote_id');

        if (!provider) {
            return;
        }

        var body = new URLSearchParams();

        body.append('action', 'cvm_product_catalogue');
        body.append('nonce', settings.nonce || '');
        body.append('provider_id', provider.value);
        body.append('plan_type', planType ? planType.value : 'SHARED');
        body.append('zone_remote_id', zone ? zone.value : '0');

        fetch(settings.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: body.toString()
        })
            .then(function (response) {
                return response.json();
            })
            .then(function (envelope) {
                if (!envelope || !envelope.success) {
                    return;
                }

                var data = envelope.data || {};

                if (includeZones) {
                    fill(select('cvm_zone_remote_id'), data.zones, settings.i18n.selectZone);
                }

                fill(select('cvm_iso_remote_id'), data.isos, settings.i18n.selectImage);

                RESOURCES.forEach(function (resource) {
                    fill(select('cvm_' + resource + '_price_id'), (data.plans || {})[resource], settings.i18n.selectTier);
                });

                refreshSummary();
            })
            .catch(function () {
                /* The panel keeps the options it already has. */
            });
    }

    document.addEventListener('DOMContentLoaded', function () {
        if (!panel()) {
            return;
        }

        toggleManualPrice();
        refreshSummary();

        ['cvm_provider_id', 'cvm_plan_type'].forEach(function (id) {
            var element = select(id);

            if (element) {
                element.addEventListener('change', function () {
                    reload(true);
                });
            }
        });

        var zone = select('cvm_zone_remote_id');

        if (zone) {
            zone.addEventListener('change', function () {
                reload(false);
            });
        }

        RESOURCES.forEach(function (resource) {
            var element = select('cvm_' + resource + '_price_id');

            if (element) {
                element.addEventListener('change', refreshSummary);
            }
        });

        ['cvm_billing_cycle', 'cvm_manual_price'].forEach(function (id) {
            var element = document.getElementById(id);

            if (element) {
                element.addEventListener('change', refreshSummary);
                element.addEventListener('keyup', refreshSummary);
            }
        });

        var mode = select('cvm_price_mode');

        if (mode) {
            mode.addEventListener('change', function () {
                toggleManualPrice();
                refreshSummary();
            });
        }
    });
}());
