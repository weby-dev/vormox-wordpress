<?php

/**
 * Machine creation.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Service\Provisioning;

use CloudVmManager\Contracts\LoggerInterface;
use CloudVmManager\Exception\ApiException;
use CloudVmManager\Exception\AuthenticationException;
use CloudVmManager\Exception\TransportException;
use CloudVmManager\Http\ApiRequest;
use CloudVmManager\Http\Endpoints;
use CloudVmManager\Model\LogEntry;
use CloudVmManager\Model\Provider;
use CloudVmManager\Model\VmOrder;
use CloudVmManager\Repository\VmOrderRepository;
use CloudVmManager\Service\Provider\ProviderGateway;
use CloudVmManager\Support\Settings;

defined('ABSPATH') || exit;

/**
 * Sends the documented creation request for one machine.
 *
 * The request body carries identifiers only: the backend validates the pricing
 * identifiers itself, so no price the store calculated is ever submitted.
 *
 * The response names the machines it created, so their identifiers, addresses
 * and names are stored the moment the call returns rather than being searched
 * for afterwards. Creation therefore never reports success on its own: the
 * status in the response describes the payment, and readiness is decided later
 * by the machine's own detail endpoint.
 */
final class VmProvisioner
{
    /**
     * Backend statuses that mean the payment settled and work has started.
     *
     * @var string[]
     */
    private const ACCEPTED_STATUSES = ['COMPLETED', 'SUCCESS', 'ACTIVE'];

    /**
     * Backend statuses that mean the request is still being processed.
     *
     * @var string[]
     */
    private const PENDING_STATUSES = ['PENDING', 'PROCESSING', 'INITIATED', 'IN_PROGRESS', 'CREATED'];

    /**
     * @var ProviderGateway
     */
    private $gateway;

    /**
     * @var GatewayResolver
     */
    private $gateways;

    /**
     * @var VmOrderRepository
     */
    private $orders;

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
        GatewayResolver $gateways,
        VmOrderRepository $orders,
        Settings $settings,
        LoggerInterface $logger
    ) {
        $this->gateway = $gateway;
        $this->gateways = $gateways;
        $this->orders = $orders;
        $this->settings = $settings;
        $this->logger = $logger;
    }

    /**
     * Create the machine described by a local order row.
     */
    public function create(Provider $provider, VmOrder $order): ProvisioningResult
    {
        $request = ApiRequest::post(
            Endpoints::VM_CREATE,
            $this->body($order),
            ['gateway' => $this->gateways->resolve($provider)]
        );

        try {
            $response = $this->gateway->request(
                $provider,
                $request,
                [
                    'channel' => LogEntry::CHANNEL_PROVISIONING,
                    'vm_order_id' => $order->id(),
                    'wc_order_id' => $order->getWcOrderId(),
                ]
            );
        } catch (TransportException $exception) {
            return ProvisioningResult::temporaryFailure($exception->getMessage());
        } catch (AuthenticationException $exception) {
            return ProvisioningResult::temporaryFailure($exception->getMessage());
        } catch (ApiException $exception) {
            return ProvisioningResult::permanentFailure($exception->getMessage());
        }

        if (!$response->isSuccessful()) {
            return $this->classifyFailure($response->statusCode(), $response->errorMessage());
        }

        $identifiers = [
            'remote_payment_id' => (string) $response->get('paymentId', ''),
            'remote_order_id' => (string) $response->get('orderId', ''),
            'group_id' => (string) $response->get('groupId', ''),
        ];

        $status = strtoupper((string) $response->get('status', ''));
        $accepted = $status === ''
            || in_array($status, self::ACCEPTED_STATUSES, true)
            || in_array($status, self::PENDING_STATUSES, true);

        if (!$accepted) {
            return ProvisioningResult::temporaryFailure(
                sprintf(
                    /* translators: %s: status reported by the backend. */
                    __('The backend reported status "%s".', 'cloud-vm-manager'),
                    $status
                )
            );
        }

        $machines = CreatedMachine::fromResponse($response->data());
        $stored = $identifiers;

        if ($machines !== []) {
            /*
             * The first machine belongs to this row. Any further machine of a
             * bulk request is split off into its own row by the caller, which
             * owns the billing fields that have to be divided with it.
             */
            $stored = array_merge($identifiers, $machines[0]->toColumns(), [
                'building_since' => gmdate('Y-m-d H:i:s'),
            ]);
        }

        $this->orders->update($order->id(), $stored);

        $this->logger->info(
            sprintf(
                'Creation request for order %d accepted with status %s, %d machine(s) named.',
                $order->getWcOrderId(),
                $status,
                count($machines)
            ),
            [
                'channel' => LogEntry::CHANNEL_PROVISIONING,
                'provider_id' => $provider->id(),
                'vm_order_id' => $order->id(),
                'wc_order_id' => $order->getWcOrderId(),
                'identifiers' => $identifiers,
            ]
        );

        if ($machines === []) {
            return ProvisioningResult::pending(
                __('The backend accepted the request and is still working on it.', 'cloud-vm-manager'),
                $identifiers
            );
        }

        return ProvisioningResult::building(
            __('The machine was created and is starting up.', 'cloud-vm-manager'),
            array_merge($identifiers, ['machines' => $machines])
        );
    }

    /**
     * Build the documented creation body.
     *
     * @return array<string, mixed>
     */
    private function body(VmOrder $order): array
    {
        $body = [
            'zoneId' => $order->getZoneRemoteId(),
            'zoneIsoId' => $order->getIsoRemoteId(),
            'planType' => $order->getPlanType(),
            'cpuPriceId' => $order->getCpuPriceId(),
            'ramPriceId' => $order->getRamPriceId(),
            'diskPriceId' => $order->getDiskPriceId(),
            'bandwidthPriceId' => $order->getBandwidthPriceId(),
            'months' => $order->getMonths(),
            'quantity' => $order->getQuantity(),
            'useWalletBalance' => $this->settings->getBool('use_wallet_balance', true),
        ];

        $coupon = $order->getCouponCode();

        if ($coupon !== '') {
            $body['couponCode'] = $coupon;
        }

        /**
         * Filter the body of a machine creation request.
         *
         * @param array<string, mixed> $body  Documented request body.
         * @param VmOrder              $order Local order row being provisioned.
         */
        $filtered = apply_filters('cloud_vm_manager_create_vm_body', $body, $order);

        return is_array($filtered) ? $filtered : $body;
    }

    /**
     * Decide whether a failure status is worth another attempt.
     */
    private function classifyFailure(int $statusCode, string $message): ProvisioningResult
    {
        if ($statusCode >= 500 || in_array($statusCode, [408, 425, 429], true)) {
            return ProvisioningResult::temporaryFailure($message);
        }

        return ProvisioningResult::permanentFailure($message);
    }
}
