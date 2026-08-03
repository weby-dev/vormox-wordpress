<?php

/**
 * Machine control AJAX endpoints.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Ajax;

use CloudVmManager\Admin\Access;
use CloudVmManager\Model\VmOrder;
use CloudVmManager\Service\Vm\ControlResult;
use CloudVmManager\Service\Vm\VmControlService;
use CloudVmManager\Service\Vm\VmService;
use CloudVmManager\Support\Settings;

defined('ABSPATH') || exit;

/**
 * Power, rebuild, password, MAC and network actions from the dashboard.
 *
 * Every endpoint verifies the nonce, requires a signed in customer and resolves
 * the machine through the ownership check, so an identifier from the request
 * can never reach somebody else's machine.
 */
final class VmControlAjaxController extends AbstractAjaxController
{
    public const ACTION_POWER = 'cvm_vm_power';
    public const ACTION_REBUILD = 'cvm_vm_rebuild';
    public const ACTION_PASSWORD = 'cvm_vm_password';
    public const ACTION_MAC = 'cvm_vm_regenerate_mac';
    public const ACTION_NETWORK = 'cvm_vm_reconfigure_network';

    /**
     * @var VmService
     */
    private $machines;

    /**
     * @var VmControlService
     */
    private $controls;

    /**
     * @var Settings
     */
    private $settings;

    public function __construct(VmService $machines, VmControlService $controls, Settings $settings)
    {
        $this->machines = $machines;
        $this->controls = $controls;
        $this->settings = $settings;
    }

    public function register(): void
    {
        add_action('wp_ajax_' . self::ACTION_POWER, [$this, 'power']);
        add_action('wp_ajax_' . self::ACTION_REBUILD, [$this, 'rebuild']);
        add_action('wp_ajax_' . self::ACTION_PASSWORD, [$this, 'password']);
        add_action('wp_ajax_' . self::ACTION_MAC, [$this, 'regenerateMac']);
        add_action('wp_ajax_' . self::ACTION_NETWORK, [$this, 'reconfigureNetwork']);
    }

    /**
     * Start, stop, reboot, pause, hibernate or resume a machine.
     */
    public function power(): void
    {
        $machine = $this->requireOwnedMachine();

        $this->respond($this->controls->power($machine, $this->keyParam('vm_action')));
    }

    /**
     * Reinstall the operating system.
     */
    public function rebuild(): void
    {
        $machine = $this->requireOwnedMachine();

        $this->respond($this->controls->rebuild($machine, $this->intParam('iso_id')));
    }

    /**
     * Change the administrator password.
     */
    public function password(): void
    {
        $machine = $this->requireOwnedMachine();

        /*
         * The password is the one value that must not be sanitised: trimming or
         * stripping characters would silently set a different password than the
         * customer typed. It is validated for length and sent as given.
         */
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in requireOwnedMachine().
        $password = isset($_POST['password']) ? (string) wp_unslash((string) $_POST['password']) : '';

        $this->respond($this->controls->changePassword($machine, $password));
    }

    /**
     * Generate a new MAC address.
     */
    public function regenerateMac(): void
    {
        $machine = $this->requireOwnedMachine();

        $this->respond($this->controls->regenerateMac($machine));
    }

    /**
     * Re-apply the guest network configuration.
     */
    public function reconfigureNetwork(): void
    {
        $machine = $this->requireOwnedMachine();

        $this->respond($this->controls->reconfigureNetwork($machine));
    }

    /**
     * Turn a control result into a response envelope.
     */
    private function respond(ControlResult $result): void
    {
        if (!$result->isSuccessful()) {
            $this->failure($result->message(), 200, ['result' => $result->toArray()]);

            return;
        }

        $this->success(
            [
                'message' => $result->message(),
                'result' => $result->toArray(),
            ]
        );
    }

    /**
     * Authorise the request and load the addressed machine.
     */
    private function requireOwnedMachine(): VmOrder
    {
        check_ajax_referer(Access::AJAX_NONCE, 'nonce');

        if (!$this->settings->getBool('enable_client_dashboard', true)) {
            $this->failure(__('Machine management is disabled.', 'cloud-vm-manager'), 403);

            exit;
        }

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
