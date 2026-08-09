<?php

/**
 * Provider panel links.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Service\Vm;

use CloudVmManager\Model\Provider;
use CloudVmManager\Model\VmOrder;

defined('ABSPATH') || exit;

/**
 * Builds the link that opens a machine at the provider.
 *
 * The API documentation defines no single sign on endpoint, so no token is
 * minted and none is invented: this is a plain deep link to the panel, and the
 * customer signs in there as they normally would. If a sign on endpoint is
 * added later, filtering the URL is enough to upgrade every link at once.
 *
 * The panel address comes from the provider's host URL. A provider that has
 * none gets no link rather than a guess built from its API address, because a
 * link that goes somewhere wrong is worse than no link.
 */
final class PanelLink
{
    /**
     * Path a machine is opened at, relative to the panel root.
     */
    private const MACHINE_PATH = 'vm';

    /**
     * URL of the provider panel, empty when the provider has no host URL.
     */
    public function forProvider(Provider $provider): string
    {
        $host = trim($provider->getHostUrl());

        if ($host === '') {
            return '';
        }

        $url = rtrim($host, '/');

        /**
         * Filter the link that opens the provider panel.
         *
         * @param string        $url      Panel URL.
         * @param Provider      $provider Provider being opened.
         * @param VmOrder|null  $order    Machine being opened, when there is one.
         */
        return (string) apply_filters('cloud_vm_manager_panel_url', $url, $provider, null);
    }

    /**
     * URL that opens one machine at the provider.
     */
    public function forMachine(VmOrder $order, ?Provider $provider): string
    {
        if (!$provider instanceof Provider) {
            return '';
        }

        $base = $this->forProvider($provider);

        if ($base === '' || $order->getRemoteVmId() <= 0) {
            return $base;
        }

        $url = sprintf('%s/%s/%d', $base, self::MACHINE_PATH, $order->getRemoteVmId());

        /** This filter is documented above in this class. */
        return (string) apply_filters('cloud_vm_manager_panel_url', $url, $provider, $order);
    }

    /**
     * Label the link is shown with, named after the provider.
     */
    public function label(?Provider $provider): string
    {
        $name = $provider instanceof Provider ? trim($provider->getName()) : '';

        if ($name === '') {
            return __('Open provider panel', 'cloud-vm-manager');
        }

        return sprintf(
            /* translators: %s: provider name. */
            __('Login to %s', 'cloud-vm-manager'),
            $name
        );
    }
}
