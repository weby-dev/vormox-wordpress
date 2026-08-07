<?php

/**
 * Upgrade AJAX endpoints.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Ajax;

use CloudVmManager\Admin\Access;
use CloudVmManager\Model\PricingRule;
use CloudVmManager\Model\VmOrder;
use CloudVmManager\Service\Billing\UpgradeService;
use CloudVmManager\Service\Vm\VmService;
use CloudVmManager\Support\Settings;

defined('ABSPATH') || exit;

/**
 * Drives the three step upgrade flow from the dashboard.
 *
 * Options, then a quote, then the payment. The quote is always fetched from the
 * backend immediately before the payment, so the amount the customer confirms
 * is the amount the backend charges.
 */
final class UpgradeAjaxController extends AbstractAjaxController
{
    public const ACTION_OPTIONS = 'cvm_upgrade_options';
    public const ACTION_QUOTE = 'cvm_upgrade_quote';
    public const ACTION_APPLY = 'cvm_upgrade_apply';

    /**
     * Largest term a customer may add in one go.
     */
    private const MAX_MONTHS = 36;

    /**
     * @var VmService
     */
    private $machines;

    /**
     * @var UpgradeService
     */
    private $upgrades;

    /**
     * @var Settings
     */
    private $settings;

    public function __construct(VmService $machines, UpgradeService $upgrades, Settings $settings)
    {
        $this->machines = $machines;
        $this->upgrades = $upgrades;
        $this->settings = $settings;
    }

    public function register(): void
    {
        add_action('wp_ajax_' . self::ACTION_OPTIONS, [$this, 'options']);
        add_action('wp_ajax_' . self::ACTION_QUOTE, [$this, 'quote']);
        add_action('wp_ajax_' . self::ACTION_APPLY, [$this, 'apply']);
    }

    /**
     * Tiers the machine can move to.
     */
    public function options(): void
    {
        $machine = $this->requireOwnedMachine();
        $options = $this->upgrades->options($machine);

        if (!$options['available']) {
            $this->failure($options['message'], 200);

            return;
        }

        $serialised = [];

        foreach ($options['options'] as $resource => $entries) {
            $serialised[$resource] = array_map(
                static function ($option): array {
                    return $option->toArray();
                },
                $entries
            );
        }

        $this->success(
            [
                'plan' => $options['plan'],
                'current' => $this->upgrades->currentPriceIds($machine),
                'options' => $serialised,
            ]
        );
    }

    /**
     * What the chosen change costs.
     */
    public function quote(): void
    {
        $machine = $this->requireOwnedMachine();

        $quote = $this->upgrades->quote(
            $machine,
            $this->readPriceIds(),
            $this->readMonths(),
            $this->textParam('coupon_code')
        );

        if (!$quote->isAvailable()) {
            $this->failure($quote->message(), 200);

            return;
        }

        $this->success(['quote' => $quote->toArray()]);
    }

    /**
     * Pay for and apply the change.
     */
    public function apply(): void
    {
        $machine = $this->requireOwnedMachine();
        $priceIds = $this->readPriceIds();
        $months = $this->readMonths();
        $coupon = $this->textParam('coupon_code');

        if ($months <= 0 && $priceIds === $this->upgrades->currentPriceIds($machine)) {
            $this->failure(__('Choose a larger tier or a longer term first.', 'cloud-vm-manager'), 200);

            return;
        }

        $result = $this->upgrades->apply($machine, $priceIds, $months, $coupon);

        if (!$result->isSuccessful()) {
            $this->failure($result->message(), 200);

            return;
        }

        $this->success(['message' => $result->message()]);
    }

    /**
     * Pricing identifiers submitted for each resource.
     *
     * @return array<string, int>
     */
    private function readPriceIds(): array
    {
        $priceIds = [];

        foreach (PricingRule::resourceTypes() as $resource) {
            $priceIds[$resource] = $this->intParam($resource . '_price_id');
        }

        return $priceIds;
    }

    /**
     * Months to add, clamped to a sane range.
     */
    private function readMonths(): int
    {
        return max(0, min($this->intParam('months_to_add'), self::MAX_MONTHS));
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
