<?php

/**
 * Documented API endpoints.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Http;

defined('ABSPATH') || exit;

/**
 * Registry of every endpoint documented by the backend API.
 *
 * Nothing in the plugin builds an endpoint path by hand. Any request that is
 * not expressible through this class is an endpoint the backend does not
 * document, and therefore must not be called.
 */
final class Endpoints
{
    /* Authentication. */
    public const LOGIN = '/api/login';
    public const LOGIN_OTP_INITIATE = '/api/login/otp/initiate';
    public const LOGIN_OTP_VERIFY = '/api/login/otp/verify';

    /* Registration. */
    public const REGISTER_VALIDATE = '/api/register/validate';
    public const REGISTER_INITIATE_VERIFICATION = '/api/register/initiate-verification';
    public const REGISTER_COMPLETE = '/api/register/complete-registration';

    /* Password recovery. */
    public const PASSWORD_FORGOT = '/api/password/forgot';
    public const PASSWORD_VERIFY_OTP = '/api/password/verify-otp';
    public const PASSWORD_RESET = '/api/password/reset';

    /* Catalogue. */
    public const ZONES = '/api/users/zones';

    /* Account. */
    public const WALLET = '/api/wallet';
    public const WALLET_LOGS = '/api/wallet/logs';
    public const WALLET_TOP_UP = '/api/wallet/top-up';
    public const PAYMENT_GATEWAYS = '/api/user/payments/gateways';

    /* Provisioning. */
    public const VM_CREATE = '/api/users/vms/create';

    /* Dashboard and metadata. */
    public const DASHBOARD_STATS = '/api/user/dashboard/stats';
    public const PUBLIC_SETTINGS = '/api/public/settings/general';
    public const AUDIT_LOGS = '/api/users/audit-logs';

    /* Orders. */
    public const ORDERS_OVERVIEW = '/api/users/orders/overview';
    public const ORDERS_PAST = '/api/users/orders/past-orders';

    /* Billing. */
    public const CALCULATE_RENEWAL = '/api/billing/price/calculate-renewal';

    /* Plan types accepted by the pricing catalogue. */
    public const PLAN_SHARED = 'shared';
    public const PLAN_DEDICATED = 'dedicated';

    /* Resources of the pricing catalogue. */
    public const RESOURCE_CPU = 'cpu';
    public const RESOURCE_RAM = 'ram';
    public const RESOURCE_DISK = 'disk';
    public const RESOURCE_BANDWIDTH = 'bandwidth';

    /**
     * Not instantiable.
     */
    private function __construct()
    {
    }

    /**
     * Operating system images available inside a zone.
     */
    public static function zoneIsos(int $zoneId): string
    {
        return self::ZONES . '/' . $zoneId . '/isos';
    }

    /**
     * Pricing catalogue of one resource, for shared or dedicated plans.
     *
     * @param string $planType One of the PLAN_* constants.
     * @param string $resource One of the RESOURCE_* constants.
     */
    public static function pricing(string $planType, string $resource): string
    {
        return '/api/pricing/' . $planType . '/' . $resource;
    }

    /**
     * Upgrade options of a machine.
     */
    public static function upgradeOptions(int $vmId): string
    {
        return '/api/pricing/upgrades/' . $vmId;
    }

    /**
     * Detailed information about a single machine.
     */
    public static function orderDetails(int $vmId): string
    {
        return '/api/users/orders/' . $vmId . '/details';
    }

    /**
     * Invoice of a payment, returned as binary PDF data.
     */
    public static function invoice(int $paymentId): string
    {
        return '/api/users/orders/' . $paymentId . '/invoice';
    }

    /**
     * Lock and suspension state of a machine.
     */
    public static function lockStatus(int $vmId): string
    {
        return '/api/vms/' . $vmId . '/lock-status';
    }

    /**
     * Power control of a machine.
     */
    public static function vmControl(int $userId, int $vmId): string
    {
        return '/api/users/' . $userId . '/vms/' . $vmId . '/control';
    }

    /**
     * Operating system reinstall of a machine.
     */
    public static function vmRebuild(int $userId, int $vmId): string
    {
        return '/api/users/' . $userId . '/vms/' . $vmId . '/rebuild';
    }

    /**
     * Root or administrator password of a machine.
     */
    public static function vmPassword(int $userId, int $vmId): string
    {
        return '/api/users/' . $userId . '/vms/' . $vmId . '/password';
    }

    /**
     * Live performance metrics of a machine.
     */
    public static function vmMetrics(int $vmId): string
    {
        return '/api/users/vms/' . $vmId . '/metrics';
    }

    /**
     * File system usage reported by the guest agent.
     */
    public static function vmStorage(int $vmId): string
    {
        return '/api/users/vms/' . $vmId . '/storage';
    }

    /**
     * MAC address regeneration of a machine.
     */
    public static function vmRegenerateMac(int $vmId): string
    {
        return '/api/users/vms/' . $vmId . '/mac/regenerate';
    }

    /**
     * Guest network reconfiguration of a machine.
     */
    public static function vmReconfigureNetwork(int $vmId): string
    {
        return '/api/users/vms/' . $vmId . '/reconfigure-network';
    }

    /**
     * Renewal or upgrade payment of a machine.
     */
    public static function vmUpgradeRenew(int $vmId): string
    {
        return '/api/vms/renew/' . $vmId . '/upgrade-renew';
    }

    /**
     * Billing address of a freshly registered account.
     */
    public static function registerBilling(string $email): string
    {
        return '/api/register/step2/billing/' . rawurlencode($email);
    }
}
