<?php

/**
 * VM orders screen.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Admin\Controller;

use CloudVmManager\Admin\Access;
use CloudVmManager\Admin\View;
use CloudVmManager\Model\VmOrder;
use CloudVmManager\Repository\ProviderRepository;
use CloudVmManager\Repository\VmOrderRepository;

defined('ABSPATH') || exit;

/**
 * Lists the machines the store sold and how their provisioning went.
 */
final class VmOrdersController
{
    public const PAGE = 'cloud-vm-manager-orders';

    private const PER_PAGE = 20;

    /**
     * @var VmOrderRepository
     */
    private $orders;

    /**
     * @var ProviderRepository
     */
    private $providers;

    /**
     * @var View
     */
    private $view;

    public function __construct(VmOrderRepository $orders, ProviderRepository $providers, View $view)
    {
        $this->orders = $orders;
        $this->providers = $providers;
        $this->view = $view;
    }

    public function render(): void
    {
        Access::assert();

        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read only listing.
        $page = isset($_GET['paged']) ? max(1, absint(wp_unslash((string) $_GET['paged']))) : 1;
        $status = isset($_GET['status']) ? sanitize_key(wp_unslash((string) $_GET['status'])) : '';
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

        $conditions = [];

        if ($status !== '' && in_array($status, $this->statuses(), true)) {
            $conditions['status'] = $status;
        }

        $paginated = $this->orders->paginate(
            $conditions,
            [
                'page' => $page,
                'per_page' => self::PER_PAGE,
                'order_by' => 'created_at',
                'order' => 'DESC',
            ]
        );

        $providerNames = [];

        foreach ($this->providers->all() as $provider) {
            $providerNames[$provider->id()] = $provider->getName();
        }

        $this->view->render(
            'admin/vm-orders',
            [
                'orders' => $paginated['items'],
                'total' => $paginated['total'],
                'page' => $paginated['page'],
                'pages' => $paginated['pages'],
                'status' => $status,
                'statuses' => $this->statusLabels(),
                'counts' => $this->orders->countByStatus(),
                'providerNames' => $providerNames,
                'baseUrl' => self::url(),
            ]
        );
    }

    /**
     * URL of the orders screen.
     *
     * @param array<string, string|int> $args
     */
    public static function url(array $args = []): string
    {
        return add_query_arg(
            array_merge(['page' => self::PAGE], $args),
            admin_url('admin.php')
        );
    }

    /**
     * @return string[]
     */
    private function statuses(): array
    {
        return [
            VmOrder::STATUS_PENDING,
            VmOrder::STATUS_PROVISIONING,
            VmOrder::STATUS_ACTIVE,
            VmOrder::STATUS_FAILED,
            VmOrder::STATUS_SUSPENDED,
            VmOrder::STATUS_TERMINATED,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function statusLabels(): array
    {
        return [
            VmOrder::STATUS_PENDING => __('Pending', 'cloud-vm-manager'),
            VmOrder::STATUS_PROVISIONING => __('Provisioning', 'cloud-vm-manager'),
            VmOrder::STATUS_ACTIVE => __('Active', 'cloud-vm-manager'),
            VmOrder::STATUS_FAILED => __('Failed', 'cloud-vm-manager'),
            VmOrder::STATUS_SUSPENDED => __('Suspended', 'cloud-vm-manager'),
            VmOrder::STATUS_TERMINATED => __('Terminated', 'cloud-vm-manager'),
        ];
    }
}
