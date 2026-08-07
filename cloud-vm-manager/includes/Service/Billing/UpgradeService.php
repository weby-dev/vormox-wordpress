<?php

/**
 * Upgrade and renewal.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Service\Billing;

use CloudVmManager\Contracts\LoggerInterface;
use CloudVmManager\Exception\ApiException;
use CloudVmManager\Http\ApiRequest;
use CloudVmManager\Http\Endpoints;
use CloudVmManager\Model\LogEntry;
use CloudVmManager\Model\PricingRule;
use CloudVmManager\Model\Provider;
use CloudVmManager\Model\VmOrder;
use CloudVmManager\Repository\VmOrderRepository;
use CloudVmManager\Service\Provider\ProviderGateway;
use CloudVmManager\Service\Provisioning\GatewayResolver;
use CloudVmManager\Service\Vm\ControlResult;
use CloudVmManager\Service\Vm\VmService;
use CloudVmManager\Support\Arr;
use CloudVmManager\Support\Cache;
use CloudVmManager\Support\Settings;

defined('ABSPATH') || exit;

/**
 * Moves a machine to a larger tier, or extends its term.
 *
 * The three documented calls run in the order the API guide describes: read the
 * options, ask the backend what the change costs, then pay for it. The plugin
 * never computes a pro rata amount itself; every figure shown to the customer
 * comes from the backend calculation.
 */
final class UpgradeService
{
    /**
     * Response keys holding the options of each resource.
     *
     * @var array<string, string>
     */
    private const OPTION_KEYS = [
        PricingRule::RESOURCE_CPU => 'cpuOptions',
        PricingRule::RESOURCE_RAM => 'ramOptions',
        PricingRule::RESOURCE_DISK => 'diskOptions',
        PricingRule::RESOURCE_BANDWIDTH => 'bandwidthOptions',
    ];

    /**
     * @var ProviderGateway
     */
    private $gateway;

    /**
     * @var VmService
     */
    private $machines;

    /**
     * @var VmOrderRepository
     */
    private $orders;

    /**
     * @var GatewayResolver
     */
    private $gateways;

    /**
     * @var Cache
     */
    private $cache;

    /**
     * @var Settings
     */
    private $settings;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        ProviderGateway $gateway,
        VmService $machines,
        VmOrderRepository $orders,
        GatewayResolver $gateways,
        Cache $cache,
        Settings $settings,
        LoggerInterface $logger
    ) {
        $this->gateway = $gateway;
        $this->machines = $machines;
        $this->orders = $orders;
        $this->gateways = $gateways;
        $this->cache = $cache;
        $this->settings = $settings;
        $this->logger = $logger;
    }

    /**
     * Tiers a machine can move to, per resource.
     *
     * @return array{available: bool, message: string, plan: string, options: array<string, UpgradeOption[]>}
     */
    public function options(VmOrder $order): array
    {
        $empty = [
            'available' => false,
            'message' => '',
            'plan' => $order->getPlanType(),
            'options' => [],
        ];

        if (!$order->isProvisioned()) {
            $empty['message'] = __('This machine is not ready yet.', 'cloud-vm-manager');

            return $empty;
        }

        $provider = $this->machines->providerFor($order);

        if (!$provider instanceof Provider) {
            $empty['message'] = __('The provider of this machine is unavailable.', 'cloud-vm-manager');

            return $empty;
        }

        try {
            $response = $this->gateway->request(
                $provider,
                ApiRequest::get(Endpoints::upgradeOptions($order->getRemoteVmId())),
                ['channel' => LogEntry::CHANNEL_CUSTOMER, 'vm_order_id' => $order->id()]
            );

            $response->assertSuccessful();
        } catch (ApiException $exception) {
            $empty['message'] = $exception->getMessage();

            return $empty;
        }

        $data = $response->data();
        $current = $this->currentPriceIds($order);
        $options = [];

        foreach (self::OPTION_KEYS as $resource => $key) {
            $entries = Arr::get($data, $key, []);

            if (!is_array($entries)) {
                continue;
            }

            $options[$resource] = $this->mapOptions($entries, $current[$resource] ?? 0);
        }

        return [
            'available' => true,
            'message' => '',
            'plan' => (string) Arr::get($data, 'currentPlan', $order->getPlanType()),
            'options' => $options,
        ];
    }

    /**
     * Ask the backend what a change costs.
     *
     * @param array<string, int> $priceIds    Chosen pricing identifier per resource.
     * @param int                $monthsToAdd Months to extend the term by.
     */
    public function quote(VmOrder $order, array $priceIds, int $monthsToAdd, string $couponCode = ''): RenewalQuote
    {
        if (!$order->isProvisioned()) {
            return RenewalQuote::unavailable(__('This machine is not ready yet.', 'cloud-vm-manager'));
        }

        $provider = $this->machines->providerFor($order);

        if (!$provider instanceof Provider) {
            return RenewalQuote::unavailable(__('The provider of this machine is unavailable.', 'cloud-vm-manager'));
        }

        $body = $this->quoteBody($order, $priceIds, $monthsToAdd, $couponCode);

        try {
            $response = $this->gateway->request(
                $provider,
                ApiRequest::post(Endpoints::CALCULATE_RENEWAL, $body),
                ['channel' => LogEntry::CHANNEL_CUSTOMER, 'vm_order_id' => $order->id()]
            );
        } catch (ApiException $exception) {
            return RenewalQuote::unavailable($exception->getMessage());
        }

        if (!$response->isSuccessful()) {
            return RenewalQuote::unavailable($response->errorMessage());
        }

        $data = $response->data();

        return new RenewalQuote(
            true,
            (float) Arr::get($data, 'originalAmount', 0),
            (float) Arr::get($data, 'discountAmount', 0),
            (float) Arr::get($data, 'payableAmount', 0),
            (string) Arr::get($data, 'couponStatus', ''),
            (bool) Arr::first($data, ['isSpecChange', 'specChange'], false)
        );
    }

    /**
     * Pay for and apply an upgrade or a renewal.
     *
     * @param array<string, int> $priceIds
     */
    public function apply(VmOrder $order, array $priceIds, int $monthsToAdd, string $couponCode = ''): ControlResult
    {
        if (!$order->isProvisioned()) {
            return ControlResult::failure(__('This machine is not ready yet.', 'cloud-vm-manager'));
        }

        $lock = $this->machines->lockStatus($order);

        if ($lock['locked']) {
            return ControlResult::failure(
                $lock['message'] !== ''
                    ? $lock['message']
                    : __('This machine is locked. Please contact support.', 'cloud-vm-manager')
            );
        }

        $provider = $this->machines->providerFor($order);

        if (!$provider instanceof Provider) {
            return ControlResult::failure(__('The provider of this machine is unavailable.', 'cloud-vm-manager'));
        }

        $body = $this->quoteBody($order, $priceIds, $monthsToAdd, $couponCode);
        $body['useWalletBalance'] = $this->settings->getBool('use_wallet_balance', true);

        try {
            $response = $this->gateway->request(
                $provider,
                ApiRequest::post(
                    Endpoints::vmUpgradeRenew($order->getRemoteVmId()),
                    $body,
                    ['gateway' => $this->gateways->resolve($provider)]
                ),
                [
                    'channel' => LogEntry::CHANNEL_CUSTOMER,
                    'vm_order_id' => $order->id(),
                    'user_id' => $order->getUserId(),
                ]
            );
        } catch (ApiException $exception) {
            $this->logger->warning(
                sprintf('Upgrade of machine %d failed: %s', $order->getRemoteVmId(), $exception->getMessage()),
                ['channel' => LogEntry::CHANNEL_CUSTOMER, 'vm_order_id' => $order->id()]
            );

            return ControlResult::failure($exception->getMessage());
        }

        if (!$response->isSuccessful()) {
            return ControlResult::failure($response->errorMessage());
        }

        $this->persist($order, $priceIds, $monthsToAdd, $couponCode);
        $this->cache->flush($provider->id());

        $this->logger->info(
            sprintf('Machine %d upgraded or renewed.', $order->getRemoteVmId()),
            [
                'channel' => LogEntry::CHANNEL_CUSTOMER,
                'vm_order_id' => $order->id(),
                'user_id' => $order->getUserId(),
                'months_added' => $monthsToAdd,
            ]
        );

        /**
         * Fires after a machine was upgraded or renewed.
         *
         * @param VmOrder            $order       Machine that changed.
         * @param array<string, int> $priceIds    Pricing identifiers now in force.
         * @param int                $monthsToAdd Months added to the term.
         */
        do_action('cloud_vm_manager_vm_upgraded', $order, $priceIds, $monthsToAdd);

        $message = Arr::get($response->data(), 'message');

        return ControlResult::success(
            is_string($message) && $message !== ''
                ? $message
                : __('The change was applied.', 'cloud-vm-manager'),
            $response->data()
        );
    }

    /**
     * Pricing identifiers currently in force for a machine.
     *
     * @return array<string, int>
     */
    public function currentPriceIds(VmOrder $order): array
    {
        return [
            PricingRule::RESOURCE_CPU => $order->getCpuPriceId(),
            PricingRule::RESOURCE_RAM => $order->getRamPriceId(),
            PricingRule::RESOURCE_DISK => $order->getDiskPriceId(),
            PricingRule::RESOURCE_BANDWIDTH => $order->getBandwidthPriceId(),
        ];
    }

    /**
     * Merge the chosen identifiers over the current ones.
     *
     * A resource the customer did not change keeps the tier it already has, so
     * an upgrade never silently downgrades something else.
     *
     * @param array<string, int> $priceIds
     *
     * @return array<string, int>
     */
    public function resolvePriceIds(VmOrder $order, array $priceIds): array
    {
        $resolved = $this->currentPriceIds($order);

        foreach ($resolved as $resource => $currentId) {
            $chosen = (int) ($priceIds[$resource] ?? 0);

            if ($chosen > 0) {
                $resolved[$resource] = $chosen;
            }
        }

        return $resolved;
    }

    /**
     * Body shared by the calculation and the payment call.
     *
     * @param array<string, int> $priceIds
     *
     * @return array<string, mixed>
     */
    private function quoteBody(VmOrder $order, array $priceIds, int $monthsToAdd, string $couponCode): array
    {
        $resolved = $this->resolvePriceIds($order, $priceIds);

        $body = [
            'vmId' => $order->getRemoteVmId(),
            'monthsToAdd' => max(0, $monthsToAdd),
            'cpuPriceId' => $resolved[PricingRule::RESOURCE_CPU],
            'ramPriceId' => $resolved[PricingRule::RESOURCE_RAM],
            'diskPriceId' => $resolved[PricingRule::RESOURCE_DISK],
            'bandwidthPriceId' => $resolved[PricingRule::RESOURCE_BANDWIDTH],
        ];

        if ($couponCode !== '') {
            $body['couponCode'] = $couponCode;
        }

        return $body;
    }

    /**
     * Record the new specification and term locally.
     *
     * @param array<string, int> $priceIds
     */
    private function persist(VmOrder $order, array $priceIds, int $monthsToAdd, string $couponCode): void
    {
        $resolved = $this->resolvePriceIds($order, $priceIds);

        $data = [
            'cpu_price_id' => $resolved[PricingRule::RESOURCE_CPU],
            'ram_price_id' => $resolved[PricingRule::RESOURCE_RAM],
            'disk_price_id' => $resolved[PricingRule::RESOURCE_DISK],
            'bandwidth_price_id' => $resolved[PricingRule::RESOURCE_BANDWIDTH],
        ];

        if ($couponCode !== '') {
            $data['coupon_code'] = $couponCode;
        }

        if ($monthsToAdd > 0) {
            $renews = $order->getDateTime('renews_at');
            $from = $renews !== null ? $renews->getTimestamp() : time();
            $extended = strtotime('+' . $monthsToAdd . ' months', $from);

            $data['renews_at'] = gmdate('Y-m-d H:i:s', $extended !== false ? $extended : $from);
            $data['months'] = $order->getMonths() + $monthsToAdd;
        }

        $this->orders->update($order->id(), $data);
    }

    /**
     * Turn the option entries of one resource into value objects.
     *
     * @param array<int, mixed> $entries
     *
     * @return UpgradeOption[]
     */
    private function mapOptions(array $entries, int $currentPriceId): array
    {
        $options = [];

        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $tier = Arr::get($entry, 'tier', []);
            $tier = is_array($tier) ? $tier : [];

            $priceId = Arr::first($tier, ['id'], Arr::get($entry, 'id', 0));
            $priceId = is_numeric($priceId) ? (int) $priceId : 0;

            if ($priceId <= 0) {
                continue;
            }

            $options[] = new UpgradeOption(
                $priceId,
                $this->optionLabel($tier, $entry),
                (float) Arr::first($entry, ['monthlyPrice', 'price'], Arr::get($tier, 'price', 0)),
                (float) Arr::first($entry, ['proRataUpgradeCost', 'proRataCost'], 0),
                $priceId === $currentPriceId
            );
        }

        return $options;
    }

    /**
     * Readable label of one option.
     *
     * @param array<string, mixed> $tier
     * @param array<string, mixed> $entry
     */
    private function optionLabel(array $tier, array $entry): string
    {
        $label = Arr::first($tier, ['label', 'name'], Arr::get($entry, 'label', ''));

        if (is_string($label) && $label !== '') {
            return $label;
        }

        foreach (['cores', 'ram', 'ramMb', 'disk', 'diskGb', 'bandwidth', 'bandwidthGb'] as $key) {
            $value = Arr::get($tier, $key);

            if (is_numeric($value)) {
                return (string) $value;
            }
        }

        return __('Tier', 'cloud-vm-manager');
    }
}
