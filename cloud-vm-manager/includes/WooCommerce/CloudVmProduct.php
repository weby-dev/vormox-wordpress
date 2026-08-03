<?php

/**
 * Cloud virtual machine product.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\WooCommerce;

use WC_Product;

defined('ABSPATH') || exit;

/**
 * WooCommerce product representing one virtual machine plan.
 *
 * The class is only ever loaded once WooCommerce is confirmed active, which is
 * why nothing outside the WooCommerce service provider references it.
 *
 * Method signatures follow WooCommerce, which declares no return types, so none
 * are added here either.
 *
 * The overridden methods keep the snake_case names WooCommerce declares them
 * with. Renaming them to camelCase would stop them overriding anything, so the
 * PSR-1 method naming rule is switched off for this class only.
 *
 * phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
 */
class CloudVmProduct extends WC_Product
{
    /**
     * WooCommerce product type slug.
     *
     * @return string
     */
    public function get_type()
    {
        return ProductType::SLUG;
    }

    /**
     * A machine is delivered by the backend, never shipped.
     *
     * @return bool
     */
    public function needs_shipping()
    {
        return false;
    }

    /**
     * @return bool
     */
    public function is_virtual()
    {
        return true;
    }

    /**
     * Only a fully configured machine can be bought.
     *
     * @return bool
     */
    public function is_purchasable()
    {
        return parent::is_purchasable() && $this->getConfiguration()->isComplete();
    }

    /**
     * Machine configuration stored on the product.
     */
    public function getConfiguration(): ProductConfiguration
    {
        return ProductConfiguration::fromProduct($this);
    }

    /**
     * Add to cart label used on shop and product pages.
     *
     * @return string
     */
    public function add_to_cart_text()
    {
        return $this->is_purchasable() && $this->is_in_stock()
            ? __('Deploy now', 'cloud-vm-manager')
            : __('Read more', 'cloud-vm-manager');
    }

    /**
     * Add to cart label for screen readers.
     *
     * @return string
     */
    public function add_to_cart_description()
    {
        return sprintf(
            /* translators: %s: product name. */
            __('Deploy %s', 'cloud-vm-manager'),
            $this->get_name()
        );
    }
}
