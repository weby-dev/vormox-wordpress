# Cloud VM Manager

Enterprise WordPress plugin that lets a hosting provider sell and manage Virtual Machines from
WordPress. Integrates with WooCommerce and talks to the cloud provisioning REST API.

The plugin lives in [`cloud-vm-manager/`](cloud-vm-manager).

## Requirements

| Component   | Version                      |
| ----------- | ---------------------------- |
| WordPress   | 6.0 or newer                 |
| WooCommerce | 7.0 or newer                 |
| PHP         | 7.4, 8.0, 8.1, 8.2, 8.3, 8.4 |
| Extensions  | `json`, `openssl`            |

## What it does

* Unlimited cloud providers with encrypted credentials, connect, disconnect, test and refresh.
* Catalogue synchronisation of zones, OS images and the four pricing resources, with added,
  updated and removed change detection, on a schedule or on demand.
* A **Cloud Virtual Machine** WooCommerce product type priced from the synchronised catalogue,
  showing provider cost, selling price, markup and profit margin.
* Automatic provisioning when an order is paid: the creation response names the
  machine, so its identifier, address and name are stored immediately, and the
  machine is polled every few seconds until it has finished starting up.
* A customer dashboard with live status, metrics, usage charts, storage, activity, wallet and
  invoices.
* Power controls, OS reinstall, password reset, MAC regeneration and network repair.
* Resource upgrades and renewals priced by the backend, with coupon support.

## Documentation

| Document | Contents |
| --- | --- |
| [Architecture](cloud-vm-manager/docs/architecture.md) | Layers, boot sequence, request flows, enforced rules |
| [Configuration](cloud-vm-manager/docs/configuration.md) | Setup walkthrough, every setting, constants, cron jobs |
| [Hooks](cloud-vm-manager/docs/hooks.md) | Actions, filters, shortcode, template overrides |
| [Backend API usage](cloud-vm-manager/docs/api-usage.md) | Every endpoint called and from where |

## Build phases

| Phase | Scope | Status |
| ----- | ----- | ------ |
| 1 | Architecture, folder structure, database schema, bootstrap | Delivered |
| 2 | Settings, provider management, authentication | Delivered |
| 3 | Synchronisation engine | Delivered |
| 4 | WooCommerce product type | Delivered |
| 5 | Provisioning engine | Delivered |
| 6 | Customer dashboard | Delivered |
| 7 | VM controls | Delivered |
| 8 | Upgrade system | Delivered |
| 9 | Optimisation, security audit, testing, documentation | Delivered |

## Development

```bash
cd cloud-vm-manager
composer install
composer run lint      # php -l over every plugin file
composer run compat    # PHP 7.4+ compatibility sniff
composer run style     # PSR-12
```

## Verification

Every phase is verified before it lands:

| Check | Result |
| --- | --- |
| `php -l` on every file | clean |
| PHPCompatibility, `testVersion 7.4-` | 0 issues across 7.4 → 8.4 |
| PSR-12 | clean apart from the mandatory `ABSPATH` guard and the WooCommerce method names the product class must keep |
| Functional suites | 9 suites, 910 checks |

The suites boot the plugin against stubbed WordPress and WooCommerce with an
in-memory `wpdb` that really executes the statements the repositories generate,
and a scriptable HTTP transport, so authentication, retries, change detection,
provisioning, ownership and the admin form handlers are exercised end to end.

## Security posture

* Credentials are stored as AES-256-CBC ciphertext authenticated with HMAC-SHA256 and verified
  in constant time.
* Log context is redacted recursively before it is written; suites assert no password or token
  reaches the database, the log table or rendered markup.
* No SQL exists outside the repository layer; column names are validated against a declared map
  and every value is bound.
* Every admin screen checks a capability, every write checks a nonce, and every customer
  endpoint resolves the machine through an ownership check.
* WordPress core tables are never modified.

## Configuration constants

| Constant             | Purpose                                                       |
| -------------------- | ------------------------------------------------------------- |
| `CVM_ENCRYPTION_KEY` | Overrides the key material used to encrypt stored credentials |

```php
define( 'CVM_ENCRYPTION_KEY', 'a-long-random-string' );
```
