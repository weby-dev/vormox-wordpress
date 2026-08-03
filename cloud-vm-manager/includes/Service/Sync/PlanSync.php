<?php

/**
 * Resource plan synchronisation.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Service\Sync;

use CloudVmManager\Contracts\LoggerInterface;
use CloudVmManager\Http\ApiRequest;
use CloudVmManager\Http\Endpoints;
use CloudVmManager\Model\AbstractPlan;
use CloudVmManager\Model\PricingRule;
use CloudVmManager\Model\Provider;
use CloudVmManager\Repository\AbstractPlanRepository;
use CloudVmManager\Repository\AbstractRepository;
use CloudVmManager\Repository\PricingRepository;
use CloudVmManager\Service\Provider\ProviderGateway;
use CloudVmManager\Support\Settings;

defined('ABSPATH') || exit;

/**
 * Synchronises one resource of the pricing catalogue.
 *
 * The same class serves CPU, RAM, disk and bandwidth: only the repository, the
 * endpoint resource and the specification column differ. Each tier also
 * produces a local price book entry, which is where the store markup lives. An
 * entry an administrator edited by hand keeps its price, so a synchronisation
 * never overwrites a deliberate decision.
 *
 * Only backend identifiers are stored for provisioning; the price is kept for
 * display and margin calculation and is never sent back to the API.
 */
final class PlanSync extends AbstractResourceSync
{
    /**
     * Field names a backend may use for the specification of each resource.
     *
     * @var array<string, string[]>
     */
    private const SPEC_KEYS = [
        PricingRule::RESOURCE_CPU => ['cores', 'vcpu', 'vcpus', 'cpu', 'cpuCores'],
        PricingRule::RESOURCE_RAM => ['ramMb', 'ram', 'memoryMb', 'memory', 'sizeMb'],
        PricingRule::RESOURCE_DISK => ['diskGb', 'disk', 'storageGb', 'storage', 'sizeGb'],
        PricingRule::RESOURCE_BANDWIDTH => ['bandwidthGb', 'bandwidth', 'transferGb', 'transfer', 'sizeGb'],
    ];

    /**
     * @var string
     */
    private $resourceType;

    /**
     * @var AbstractPlanRepository
     */
    private $plans;

    /**
     * @var PricingRepository
     */
    private $pricing;

    /**
     * @var Settings
     */
    private $settings;

    public function __construct(
        ProviderGateway $gateway,
        LoggerInterface $logger,
        string $resourceType,
        AbstractPlanRepository $plans,
        PricingRepository $pricing,
        Settings $settings
    ) {
        parent::__construct($gateway, $logger);

        $this->resourceType = $resourceType;
        $this->plans = $plans;
        $this->pricing = $pricing;
        $this->settings = $settings;
    }

    public function resource(): string
    {
        return $this->resourceType;
    }

    /**
     * Synchronise the tiers, then retire the price book entries that went with
     * a tier the backend no longer lists.
     *
     * @param array<string, mixed> $context
     */
    public function sync(Provider $provider, array $context = []): SyncResult
    {
        $result = parent::sync($provider, $context);

        if (!$result->isSuccessful()) {
            return $result;
        }

        $this->pricing->markMissingAsRemoved(
            $provider->id(),
            $this->resourceType,
            $this->planType($context),
            $this->activeRemoteIds($provider, $context)
        );

        return $result;
    }

    /**
     * Backend identifiers of the tiers that are currently active.
     *
     * @param array<string, mixed> $context
     *
     * @return int[]
     */
    private function activeRemoteIds(Provider $provider, array $context): array
    {
        $ids = [];

        foreach ($this->plans->forProvider($provider->id(), $this->planType($context)) as $plan) {
            $ids[] = $plan->getRemoteId();
        }

        return $ids;
    }

    /**
     * @param array<string, mixed> $context
     */
    protected function request(array $context): ApiRequest
    {
        $planType = $this->planType($context) === AbstractPlan::TYPE_DEDICATED
            ? Endpoints::PLAN_DEDICATED
            : Endpoints::PLAN_SHARED;

        return ApiRequest::get(Endpoints::pricing($planType, $this->resourceType));
    }

    protected function repository(): AbstractRepository
    {
        return $this->plans;
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    protected function scope(Provider $provider, array $context): array
    {
        return [
            'provider_id' => $provider->id(),
            'plan_type' => $this->planType($context),
        ];
    }

    /**
     * @param array<string, mixed> $item
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    protected function map(array $item, Provider $provider, array $context): array
    {
        return [
            'label' => $this->stringField($item, ['label', 'name', 'title']),
            'price' => $this->floatField($item, ['price', 'monthlyPrice', 'amount']),
            'currency' => $this->stringField($item, ['currency'], $provider->getCurrency()),
            $this->plans->specColumn() => $this->intField($item, self::SPEC_KEYS[$this->resourceType] ?? []),
        ];
    }

    /**
     * Keep the local price book in step with the catalogue.
     *
     * @param array<string, mixed> $item
     * @param array<string, mixed> $context
     */
    protected function afterItem(int $recordId, array $item, Provider $provider, array $context): void
    {
        $planType = $this->planType($context);
        $remoteId = $this->remoteId($item);
        $providerPrice = $this->floatField($item, ['price', 'monthlyPrice', 'amount']);

        $match = [
            'provider_id' => $provider->id(),
            'resource_type' => $this->resourceType,
            'plan_type' => $planType,
            'remote_price_id' => $remoteId,
        ];

        $existing = $this->pricing->findOneBy($match);

        $data = [
            'plan_id' => $recordId,
            'label' => $this->stringField($item, ['label', 'name', 'title']),
            'provider_price' => $providerPrice,
            'currency' => $this->stringField($item, ['currency'], $provider->getCurrency()),
            'status' => PricingRule::STATUS_ACTIVE,
            'synced_at' => gmdate('Y-m-d H:i:s'),
        ];

        if ($existing instanceof PricingRule) {
            /*
             * A manually priced entry keeps its selling price. Everything else
             * follows the markup that is already configured for it.
             */
            if (!$existing->isManual()) {
                $existing->set('provider_price', $providerPrice);
                $data['selling_price'] = $existing->calculateSellingPrice();
            }

            $this->pricing->update($existing->id(), $data);

            return;
        }

        $markupType = $this->settings->getString('default_markup_type', PricingRule::MARKUP_PERCENT);
        $markupValue = $this->settings->getFloat('default_markup_value');

        $rule = new PricingRule(
            array_merge(
                $data,
                [
                    'provider_price' => $providerPrice,
                    'markup_type' => $markupType,
                    'markup_value' => $markupValue,
                ]
            )
        );

        $this->pricing->insert(
            array_merge(
                $match,
                $data,
                [
                    'markup_type' => $markupType,
                    'markup_value' => $markupValue,
                    'selling_price' => $rule->calculateSellingPrice(),
                    'is_manual' => false,
                ]
            )
        );
    }

    /**
     * @param array<string, mixed> $context
     */
    private function planType(array $context): string
    {
        $planType = strtoupper((string) ($context['plan_type'] ?? AbstractPlan::TYPE_SHARED));

        return $planType === AbstractPlan::TYPE_DEDICATED
            ? AbstractPlan::TYPE_DEDICATED
            : AbstractPlan::TYPE_SHARED;
    }
}
