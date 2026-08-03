<?php

/**
 * Product catalogue AJAX endpoints.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Ajax;

use CloudVmManager\Admin\Access;
use CloudVmManager\WooCommerce\Admin\ProductDataPanel;

defined('ABSPATH') || exit;

/**
 * Feeds the product panel when the provider, plan type or zone changes.
 *
 * Everything served here comes from the locally synchronised catalogue, so the
 * editor never waits on the backend.
 */
final class ProductAjaxController extends AbstractAjaxController
{
    public const ACTION_CATALOGUE = 'cvm_product_catalogue';

    /**
     * @var ProductDataPanel
     */
    private $panel;

    public function __construct(ProductDataPanel $panel)
    {
        $this->panel = $panel;
    }

    public function register(): void
    {
        add_action('wp_ajax_' . self::ACTION_CATALOGUE, [$this, 'catalogue']);
    }

    /**
     * Return the zones, images and pricing tiers of one provider.
     */
    public function catalogue(): void
    {
        $this->authorize(Access::AJAX_NONCE, Access::capability());

        $providerId = $this->intParam('provider_id');
        $planType = strtoupper($this->textParam('plan_type', 'SHARED'));
        $zoneRemoteId = $this->intParam('zone_remote_id');

        $this->success(
            [
                'zones' => $this->panel->zoneOptions($providerId),
                'isos' => $this->panel->isoOptions($providerId, $zoneRemoteId),
                'plans' => $this->panel->planOptions($providerId, $planType),
            ]
        );
    }
}
