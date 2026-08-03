<?php

/**
 * Machine read operations.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Service\Vm;

use CloudVmManager\Exception\ApiException;
use CloudVmManager\Http\ApiRequest;
use CloudVmManager\Http\Endpoints;
use CloudVmManager\Model\LogEntry;
use CloudVmManager\Model\Provider;
use CloudVmManager\Model\VmOrder;
use CloudVmManager\Repository\ProviderRepository;
use CloudVmManager\Repository\VmOrderRepository;
use CloudVmManager\Service\Provider\ProviderGateway;
use CloudVmManager\Support\Arr;

defined('ABSPATH') || exit;

/**
 * Everything the customer dashboard reads about a machine.
 *
 * Ownership is checked here rather than in the controllers, so no caller can
 * reach a machine that belongs to somebody else by passing a different
 * identifier.
 */
final class VmService
{
    /**
     * @var VmOrderRepository
     */
    private $orders;

    /**
     * @var ProviderRepository
     */
    private $providers;

    /**
     * @var ProviderGateway
     */
    private $gateway;

    public function __construct(
        VmOrderRepository $orders,
        ProviderRepository $providers,
        ProviderGateway $gateway
    ) {
        $this->orders = $orders;
        $this->providers = $providers;
        $this->gateway = $gateway;
    }

    /**
     * Machines of a customer, newest first.
     *
     * @param array<string, mixed> $args
     *
     * @return array{items: VmOrder[], total: int, page: int, per_page: int, pages: int}
     */
    public function paginateFor(int $userId, array $args = []): array
    {
        return $this->orders->paginateForUser($userId, $args);
    }

    /**
     * Machines of a customer without paging, for summary cards.
     *
     * @return VmOrder[]
     */
    public function allFor(int $userId): array
    {
        return $this->orders->forUser($userId);
    }

    /**
     * Load a machine, but only when the customer owns it.
     */
    public function findOwned(int $vmOrderId, int $userId): ?VmOrder
    {
        if ($vmOrderId <= 0 || $userId <= 0) {
            return null;
        }

        $order = $this->orders->findOrder($vmOrderId);

        if ($order === null || $order->getUserId() !== $userId) {
            return null;
        }

        return $order;
    }

    /**
     * Provider a machine belongs to.
     */
    public function providerFor(VmOrder $order): ?Provider
    {
        return $this->providers->findProvider($order->getProviderId());
    }

    /**
     * Summary counters for the dashboard header.
     *
     * @return array<string, int|float>
     */
    public function summaryFor(int $userId): array
    {
        $machines = $this->allFor($userId);
        $active = 0;
        $spent = 0.0;

        foreach ($machines as $machine) {
            if ($machine->isActive()) {
                ++$active;
            }

            $spent += $machine->getSaleAmount();
        }

        return [
            'total' => count($machines),
            'active' => $active,
            'spent' => round($spent, 2),
        ];
    }

    /**
     * Diagnostic detail of a machine as the backend reports it.
     *
     * @return array<string, mixed>
     */
    public function details(VmOrder $order): array
    {
        if ($order->getRemoteVmId() <= 0) {
            return [];
        }

        return $this->read($order, ApiRequest::get(Endpoints::orderDetails($order->getRemoteVmId())));
    }

    /**
     * Lock and suspension state of a machine.
     *
     * @return array{locked: bool, status: string, message: string}
     */
    public function lockStatus(VmOrder $order): array
    {
        $default = [
            'locked' => false,
            'status' => $order->getStatus(),
            'message' => '',
        ];

        if ($order->getRemoteVmId() <= 0) {
            return $default;
        }

        $data = $this->read($order, ApiRequest::get(Endpoints::lockStatus($order->getRemoteVmId())));

        if ($data === []) {
            return $default;
        }

        return [
            'locked' => (bool) Arr::first($data, ['isLocked', 'locked'], false),
            'status' => (string) Arr::get($data, 'status', $order->getStatus()),
            'message' => (string) Arr::get($data, 'message', ''),
        ];
    }

    /**
     * Recent activity recorded by the backend for the account.
     *
     * @return array<int, array<string, mixed>>
     */
    public function auditLogs(VmOrder $order, int $limit = 8): array
    {
        $provider = $this->providerFor($order);

        if (!$provider instanceof Provider) {
            return [];
        }

        try {
            $response = $this->gateway->request(
                $provider,
                ApiRequest::get(Endpoints::AUDIT_LOGS, ['limit' => max(1, min($limit, 50))]),
                ['channel' => LogEntry::CHANNEL_CUSTOMER, 'vm_order_id' => $order->id()]
            );

            $response->assertSuccessful();
        } catch (ApiException $exception) {
            return [];
        }

        $entries = [];

        foreach ($response->list() as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $entries[] = [
                'operation' => (string) Arr::first($entry, ['operation', 'action'], ''),
                'status' => (string) Arr::get($entry, 'status', ''),
                'timestamp' => (string) Arr::first($entry, ['timestamp', 'createdAt'], ''),
            ];
        }

        return $entries;
    }

    /**
     * Whether the machine can accept control actions right now.
     */
    public function isOperable(VmOrder $order): bool
    {
        if (!$order->isProvisioned() || $order->isTerminated()) {
            return false;
        }

        $lock = $this->lockStatus($order);

        return !$lock['locked'] && strtoupper($lock['status']) !== 'SUSPENDED';
    }

    /**
     * Perform a read request for a machine and return its payload.
     *
     * @return array<string, mixed>
     */
    private function read(VmOrder $order, ApiRequest $request): array
    {
        $provider = $this->providerFor($order);

        if (!$provider instanceof Provider) {
            return [];
        }

        try {
            $response = $this->gateway->request(
                $provider,
                $request,
                ['channel' => LogEntry::CHANNEL_CUSTOMER, 'vm_order_id' => $order->id()]
            );

            if (!$response->isSuccessful()) {
                return [];
            }
        } catch (ApiException $exception) {
            return [];
        }

        $data = $response->data();

        return Arr::isList($data) ? [] : $data;
    }
}
