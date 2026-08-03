<?php

/**
 * VM order model.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Model;

defined('ABSPATH') || defined('WP_UNINSTALL_PLUGIN') || exit;

/**
 * A virtual machine sold through WooCommerce.
 *
 * Holds the local order context, the identifiers returned by the provisioning
 * backend and the provisioning state machine.
 */
final class VmOrder extends AbstractModel
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROVISIONING = 'provisioning';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_TERMINATED = 'terminated';

    public const PROVISIONING_PENDING = 'pending';
    public const PROVISIONING_QUEUED = 'queued';
    public const PROVISIONING_RUNNING = 'running';
    public const PROVISIONING_COMPLETED = 'completed';
    public const PROVISIONING_RETRYING = 'retrying';
    public const PROVISIONING_FAILED = 'failed';

    public const CYCLE_MONTHLY = 'monthly';
    public const CYCLE_QUARTERLY = 'quarterly';
    public const CYCLE_SEMI_ANNUAL = 'semi_annual';
    public const CYCLE_ANNUAL = 'annual';

    /**
     * Billing cycle slug to number of months.
     *
     * @return array<string, int>
     */
    public static function billingCycles(): array
    {
        return [
            self::CYCLE_MONTHLY => 1,
            self::CYCLE_QUARTERLY => 3,
            self::CYCLE_SEMI_ANNUAL => 6,
            self::CYCLE_ANNUAL => 12,
        ];
    }

    /**
     * Number of months covered by a billing cycle slug.
     */
    public static function monthsForCycle(string $cycle): int
    {
        $cycles = self::billingCycles();

        return $cycles[$cycle] ?? 1;
    }

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'int',
            'wc_order_id' => 'int',
            'wc_order_item_id' => 'int',
            'product_id' => 'int',
            'user_id' => 'int',
            'provider_id' => 'int',
            'remote_user_id' => 'int',
            'remote_vm_id' => 'int',
            'proxmox_vmid' => 'int',
            'remote_order_id' => 'string',
            'remote_payment_id' => 'string',
            'group_id' => 'string',
            'zone_remote_id' => 'int',
            'iso_remote_id' => 'int',
            'plan_type' => 'string',
            'cpu_price_id' => 'int',
            'ram_price_id' => 'int',
            'disk_price_id' => 'int',
            'bandwidth_price_id' => 'int',
            'months' => 'int',
            'quantity' => 'int',
            'billing_cycle' => 'string',
            'hostname' => 'string',
            'ip_address' => 'string',
            'os_name' => 'string',
            'cpu_cores' => 'int',
            'ram_mb' => 'int',
            'disk_gb' => 'int',
            'bandwidth_gb' => 'int',
            'status' => 'string',
            'provisioning_status' => 'string',
            'attempts' => 'int',
            'last_attempt_at' => 'string',
            'next_retry_at' => 'string',
            'currency' => 'string',
            'provider_amount' => 'float',
            'sale_amount' => 'float',
            'coupon_code' => 'string',
            'error_message' => 'string',
            'meta' => 'json',
            'provisioned_at' => 'string',
            'renews_at' => 'string',
            'terminated_at' => 'string',
            'created_at' => 'string',
            'updated_at' => 'string',
        ];
    }

    public function getWcOrderId(): int
    {
        return $this->getInt('wc_order_id');
    }

    public function getWcOrderItemId(): int
    {
        return $this->getInt('wc_order_item_id');
    }

    public function getProductId(): int
    {
        return $this->getInt('product_id');
    }

    public function getUserId(): int
    {
        return $this->getInt('user_id');
    }

    public function getProviderId(): int
    {
        return $this->getInt('provider_id');
    }

    /**
     * Internal database identifier of the machine on the backend.
     */
    public function getRemoteVmId(): int
    {
        return $this->getInt('remote_vm_id');
    }

    /**
     * Hypervisor identifier. Only used for support and diagnostics.
     */
    public function getProxmoxVmid(): int
    {
        return $this->getInt('proxmox_vmid');
    }

    public function getRemoteOrderId(): string
    {
        return $this->getString('remote_order_id');
    }

    public function getRemotePaymentId(): string
    {
        return $this->getString('remote_payment_id');
    }

    public function getGroupId(): string
    {
        return $this->getString('group_id');
    }

    public function getStatus(): string
    {
        return $this->getString('status', self::STATUS_PENDING);
    }

    public function getProvisioningStatus(): string
    {
        return $this->getString('provisioning_status', self::PROVISIONING_PENDING);
    }

    public function isProvisioned(): bool
    {
        return $this->getRemoteVmId() > 0
            && $this->getProvisioningStatus() === self::PROVISIONING_COMPLETED;
    }

    public function isActive(): bool
    {
        return $this->getStatus() === self::STATUS_ACTIVE;
    }

    public function isTerminated(): bool
    {
        return $this->getStatus() === self::STATUS_TERMINATED;
    }

    public function getAttempts(): int
    {
        return $this->getInt('attempts');
    }

    /**
     * Whether the provisioning may be retried again.
     */
    public function canRetry(int $maxAttempts): bool
    {
        if ($this->isProvisioned() || $this->isTerminated()) {
            return false;
        }

        return $this->getAttempts() < $maxAttempts;
    }

    public function getHostname(): string
    {
        return $this->getString('hostname');
    }

    public function getIpAddress(): string
    {
        return $this->getString('ip_address');
    }

    public function getOsName(): string
    {
        return $this->getString('os_name');
    }

    public function getBillingCycle(): string
    {
        return $this->getString('billing_cycle', self::CYCLE_MONTHLY);
    }

    public function getMonths(): int
    {
        $months = $this->getInt('months', 1);

        return $months > 0 ? $months : 1;
    }

    public function getQuantity(): int
    {
        $quantity = $this->getInt('quantity', 1);

        return $quantity > 0 ? $quantity : 1;
    }

    public function getPlanType(): string
    {
        return $this->getString('plan_type', AbstractPlan::TYPE_SHARED);
    }

    public function getCpuPriceId(): int
    {
        return $this->getInt('cpu_price_id');
    }

    public function getRamPriceId(): int
    {
        return $this->getInt('ram_price_id');
    }

    public function getDiskPriceId(): int
    {
        return $this->getInt('disk_price_id');
    }

    public function getBandwidthPriceId(): int
    {
        return $this->getInt('bandwidth_price_id');
    }

    public function getZoneRemoteId(): int
    {
        return $this->getInt('zone_remote_id');
    }

    public function getIsoRemoteId(): int
    {
        return $this->getInt('iso_remote_id');
    }

    public function getCurrency(): string
    {
        return $this->getString('currency');
    }

    public function getSaleAmount(): float
    {
        return $this->getFloat('sale_amount');
    }

    public function getProviderAmount(): float
    {
        return $this->getFloat('provider_amount');
    }

    public function getErrorMessage(): string
    {
        return $this->getString('error_message');
    }

    /**
     * @return array<string, mixed>
     */
    public function getMeta(): array
    {
        return $this->getArray('meta');
    }
}
