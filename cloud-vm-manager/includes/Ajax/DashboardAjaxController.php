<?php

/**
 * Dashboard AJAX endpoints.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Ajax;

use CloudVmManager\Admin\Access;
use CloudVmManager\Model\VmOrder;
use CloudVmManager\Service\Provisioning\ProvisioningService;
use CloudVmManager\Service\Vm\VmMetricsService;
use CloudVmManager\Service\Vm\VmService;
use CloudVmManager\Service\Vm\WalletService;

defined('ABSPATH') || exit;

/**
 * Live data for the customer dashboard.
 *
 * These endpoints are reachable by any logged in customer, so every one of them
 * resolves the machine through the ownership check rather than trusting the
 * identifier in the request.
 */
final class DashboardAjaxController extends AbstractAjaxController
{
    public const ACTION_METRICS = 'cvm_vm_metrics';
    public const ACTION_STORAGE = 'cvm_vm_storage';
    public const ACTION_STATUS = 'cvm_vm_status';
    public const ACTION_INVOICE = 'cvm_vm_invoice';

    /**
     * @var VmService
     */
    private $machines;

    /**
     * @var VmMetricsService
     */
    private $metrics;

    /**
     * @var WalletService
     */
    private $wallet;

    /**
     * @var ProvisioningService
     */
    private $provisioning;

    public function __construct(
        VmService $machines,
        VmMetricsService $metrics,
        WalletService $wallet,
        ProvisioningService $provisioning
    ) {
        $this->machines = $machines;
        $this->metrics = $metrics;
        $this->wallet = $wallet;
        $this->provisioning = $provisioning;
    }

    public function register(): void
    {
        add_action('wp_ajax_' . self::ACTION_METRICS, [$this, 'metrics']);
        add_action('wp_ajax_' . self::ACTION_STORAGE, [$this, 'storage']);
        add_action('wp_ajax_' . self::ACTION_STATUS, [$this, 'status']);
        add_action('wp_ajax_' . self::ACTION_INVOICE, [$this, 'invoice']);
    }

    /**
     * Live metrics of one machine.
     */
    public function metrics(): void
    {
        $machine = $this->requireOwnedMachine();

        $this->success(
            [
                'metrics' => $this->metrics->metrics($machine, $this->textParam('timeframe', 'hour')),
            ]
        );
    }

    /**
     * File system usage of one machine.
     */
    public function storage(): void
    {
        $machine = $this->requireOwnedMachine();

        $this->success(['storage' => $this->metrics->storage($machine)]);
    }

    /**
     * Power state and lock state of one machine.
     */
    public function status(): void
    {
        $machine = $this->requireOwnedMachine();

        /*
         * A customer watching a machine that is starting up is the fastest
         * signal there is, so this poll finishes the provisioning itself rather
         * than leaving the customer waiting for the next scheduled check. The
         * work is a single detail request and is locked against the job, so two
         * of them can never run at once.
         */
        if ($machine->isBuilding()) {
            $this->provisioning->refresh($machine->id());
            $machine = $this->machines->findOwned($machine->id(), get_current_user_id()) ?? $machine;
        }

        $metrics = $this->metrics->metrics($machine);
        $lock = $this->machines->lockStatus($machine);

        $this->success(
            [
                'status' => [
                    'live' => (string) $metrics['current']['status'],
                    'stored' => $machine->getStatus(),
                    'provisioning' => $machine->getProvisioningStatus(),
                    'ready' => $machine->isProvisioned(),
                    'locked' => $lock['locked'],
                    'message' => $lock['message'],
                    'ip_address' => $machine->getIpAddress(),
                    'hostname' => $machine->getHostname(),
                ],
            ]
        );
    }

    /**
     * Stream the invoice document of a machine payment.
     */
    public function invoice(): void
    {
        $machine = $this->requireOwnedMachine();
        $paymentId = (int) $machine->getRemotePaymentId();

        if ($paymentId <= 0) {
            $this->failure(__('This machine has no invoice yet.', 'cloud-vm-manager'), 404);

            return;
        }

        $invoice = $this->wallet->invoice($machine->getProviderId(), $paymentId);

        if (!$invoice['available']) {
            $this->failure($invoice['message'], 502);

            return;
        }

        nocache_headers();
        header('Content-Type: ' . $invoice['content_type']);
        header('Content-Disposition: attachment; filename="invoice-' . $paymentId . '.pdf"');
        header('Content-Length: ' . strlen($invoice['body']));

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Binary document.
        echo $invoice['body'];

        exit;
    }

    /**
     * Authorise the request and load the addressed machine.
     */
    private function requireOwnedMachine(): VmOrder
    {
        check_ajax_referer(Access::AJAX_NONCE, 'nonce');

        if (!is_user_logged_in()) {
            $this->failure(__('Please sign in to manage your machines.', 'cloud-vm-manager'), 401);

            exit;
        }

        $machine = $this->machines->findOwned($this->intParam('machine_id'), get_current_user_id());

        if ($machine instanceof VmOrder) {
            return $machine;
        }

        $this->failure(__('That machine is not available.', 'cloud-vm-manager'), 404);

        exit;
    }
}
