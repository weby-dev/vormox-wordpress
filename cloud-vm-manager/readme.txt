=== Cloud VM Manager ===
Contributors: weby
Tags: woocommerce, vps, cloud, virtual machine, hosting
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
WC requires at least: 7.0
WC tested up to: 9.9
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Sell, provision and manage cloud virtual machines directly from WordPress using WooCommerce.

== Description ==

Cloud VM Manager turns a WooCommerce store into a virtual machine control panel. It connects
WordPress to a cloud provisioning backend, synchronises the provider catalogue (zones, ISO
templates, CPU / RAM / disk / bandwidth plans and their pricing identifiers), exposes a dedicated
WooCommerce product type for virtual machines, provisions the machine when an order is paid and
gives the customer a frontend dashboard to operate the machine.

Highlights:

* Unlimited cloud providers with encrypted credential storage.
* Catalogue synchronisation with added / updated / removed change detection.
* Dedicated "Cloud Virtual Machine" WooCommerce product type.
* Automatic provisioning with retry handling after a successful payment.
* Customer dashboard with live status, metrics, storage usage, invoices and activity logs.
* Power controls, rebuild, password reset, MAC regeneration, network reconfiguration.
* Resource upgrades with pro-rata pricing and coupon support.
* Full request / response logging with a debug mode.

== Installation ==

1. Upload the `cloud-vm-manager` directory to `/wp-content/plugins/`.
2. Activate the plugin through the *Plugins* screen in WordPress.
3. Ensure WooCommerce is installed and active.
4. Open *Cloud VM* and register your cloud provider credentials.

== Frequently Asked Questions ==

= Which PHP versions are supported? =

PHP 7.4 up to the current stable release. The codebase uses strict types and avoids deprecated
functionality.

= Are credentials stored safely? =

Provider passwords and API tokens are encrypted with AES-256-CBC using a key derived from the
WordPress salts (or from the `CVM_ENCRYPTION_KEY` constant when defined) and authenticated with an
HMAC-SHA256 signature.

= Does the plugin support High Performance Order Storage? =

Yes. Compatibility with WooCommerce custom order tables is declared by the plugin.

== Changelog ==

= 1.0.0 =
* Initial release.
