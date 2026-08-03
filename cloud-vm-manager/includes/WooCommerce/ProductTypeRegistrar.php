<?php

/**
 * Product type registration.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\WooCommerce;

defined('ABSPATH') || exit;

/**
 * Introduces the cloud virtual machine product type to WooCommerce.
 *
 * Registration is deliberately separate from the admin panel and the save
 * handler, so the storefront only ever loads what it needs.
 */
final class ProductTypeRegistrar
{
    /**
     * Attach the product type to WooCommerce.
     */
    public function register(): void
    {
        add_filter('product_type_selector', [$this, 'addTypeToSelector']);
        add_filter('woocommerce_product_class', [$this, 'mapProductClass'], 10, 2);
        add_filter('woocommerce_product_type_query', [$this, 'resolveTypeQuery'], 10, 2);
    }

    /**
     * Offer the type in the product data dropdown.
     *
     * @param array<string, string> $types
     *
     * @return array<string, string>
     */
    public function addTypeToSelector($types): array
    {
        $types = is_array($types) ? $types : [];
        $types[ProductType::SLUG] = ProductType::label();

        return $types;
    }

    /**
     * Point WooCommerce at the product class of the type.
     *
     * @param string $className   Class WooCommerce resolved so far.
     * @param string $productType Product type being instantiated.
     */
    public function mapProductClass($className, $productType): string
    {
        if ($productType === ProductType::SLUG) {
            return CloudVmProduct::class;
        }

        return is_string($className) ? $className : '';
    }

    /**
     * Resolve the type of a product WooCommerce has not classified yet.
     *
     * @param string|false $type      Type resolved so far.
     * @param int          $productId Product being resolved.
     *
     * @return string|false
     */
    public function resolveTypeQuery($type, $productId)
    {
        if ($type !== false && $type !== '') {
            return $type;
        }

        $terms = get_the_terms($productId, 'product_type');

        if (!is_array($terms)) {
            return $type;
        }

        foreach ($terms as $term) {
            if (isset($term->slug) && $term->slug === ProductType::SLUG) {
                return ProductType::SLUG;
            }
        }

        return $type;
    }
}
