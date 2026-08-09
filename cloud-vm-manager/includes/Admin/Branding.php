<?php

/**
 * Plugin branding.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Admin;

defined('ABSPATH') || exit;

/**
 * The one place the plugin decides what mark to show and where.
 *
 * The shipped files are placeholders. Replacing `assets/images/vormox-mark.svg`
 * and `assets/images/vormox-mark-mono.svg` rebrands every surface at once, and a
 * site that would rather point somewhere else entirely can filter the URLs
 * without touching the plugin.
 */
final class Branding
{
    /**
     * Colour mark, used wherever the logo is shown at size.
     */
    private const MARK = 'assets/images/vormox-mark.svg';

    /**
     * Single colour mark, used where the admin theme supplies the colour.
     */
    private const MARK_MONO = 'assets/images/vormox-mark-mono.svg';

    /**
     * @var string[]
     */
    private $screens = [];

    /**
     * Screen identifiers the plugin owns, so branding stays on its own pages.
     *
     * @param string[] $screens
     */
    public function setScreens(array $screens): void
    {
        $this->screens = $screens;
    }

    public function register(): void
    {
        add_action('admin_head', [$this, 'printFavicon']);
        add_action('admin_head-plugins.php', [$this, 'printPluginRowIcon']);
    }

    /**
     * URL of the colour mark.
     */
    public function logoUrl(): string
    {
        /**
         * Filter the logo shown on the plugin screens and in the plugin list.
         *
         * @param string $url Absolute URL of an image.
         */
        return (string) apply_filters('cloud_vm_manager_logo_url', CVM_PLUGIN_URL . self::MARK);
    }

    /**
     * The menu icon, as the base64 data URI `add_menu_page()` expects.
     *
     * WordPress renders a data URI as a background image, so the mark has to
     * carry its own colour. It is drawn in the default menu grey and turned
     * white on hover and on the current item by the admin stylesheet, which is
     * how the core icons behave.
     */
    public function menuIcon(): string
    {
        $path = CVM_PLUGIN_DIR . self::MARK_MONO;

        if (!is_readable($path)) {
            return 'dashicons-cloud';
        }

        $svg = file_get_contents($path);

        if ($svg === false) {
            return 'dashicons-cloud';
        }

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    /**
     * Brand the browser tab and the page header of a plugin screen.
     *
     * Only the plugin's own screens are branded: replacing the site icon
     * everywhere in the admin would be someone else's decision to make.
     *
     * The logo reaches the stylesheet as a custom property rather than as
     * markup, so every screen is branded by one rule and a site that filters
     * the URL changes all of them at once.
     */
    public function printFavicon(): void
    {
        if (!$this->isPluginScreen()) {
            return;
        }

        $url = esc_url($this->logoUrl());

        printf(
            '<link rel="icon" type="image/svg+xml" href="%s">' . "\n"
            . '<style id="cvm-branding">:root{--cvm-logo:url("%s")}</style>' . "\n",
            $url,
            $url
        );
    }

    /**
     * Show the mark beside the plugin in the installed plugins list.
     *
     * WordPress prints no icon there, so it is drawn onto the plugin's own row
     * rather than by filtering markup that other plugins also render into.
     */
    public function printPluginRowIcon(): void
    {
        $selector = sprintf('tr[data-slug="%s"] .plugin-title strong', esc_attr($this->slug()));

        printf(
            '<style id="cvm-plugin-row-icon">%s{display:flex;align-items:center;gap:8px}%s::before'
            . '{content:"";width:20px;height:20px;flex:0 0 20px;background:url("%s") no-repeat center/contain}</style>'
            . "\n",
            $selector,
            $selector,
            esc_url($this->logoUrl())
        );
    }

    /**
     * Directory name WordPress uses as the plugin slug in the list table.
     */
    private function slug(): string
    {
        return dirname(CVM_PLUGIN_BASENAME);
    }

    private function isPluginScreen(): bool
    {
        if (!function_exists('get_current_screen')) {
            return false;
        }

        $screen = get_current_screen();

        return $screen !== null && in_array($screen->id, $this->screens, true);
    }
}
