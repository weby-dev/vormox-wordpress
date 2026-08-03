# Cloud VM Manager

Enterprise WordPress plugin that lets a hosting provider sell and manage Virtual Machines from
WordPress. The plugin integrates with WooCommerce and talks to the cloud provisioning REST API.

The plugin lives in [`cloud-vm-manager/`](cloud-vm-manager).

## Requirements

| Component   | Version                     |
| ----------- | --------------------------- |
| WordPress   | 6.0 or newer                |
| WooCommerce | 7.0 or newer                |
| PHP         | 7.4, 8.0, 8.1, 8.2, 8.3, 8.4|
| Extensions  | `json`, `openssl`           |

## Architecture

The plugin follows SOLID principles with a strict separation of layers. Everything is namespaced
under `CloudVmManager\` and autoloaded PSR-4 from `cloud-vm-manager/includes`.

| Layer             | Responsibility                                                   | Phase |
| ----------------- | ---------------------------------------------------------------- | ----- |
| `Bootstrap`       | Lifecycle: requirements, activation, deactivation, uninstall      | 1     |
| `Container`       | PSR-11 style DI container and service providers                   | 1     |
| `Contracts`       | Interfaces every layer is programmed against                      | 1     |
| `Database`        | Table registry, schema, installer, migrator                       | 1     |
| `Model`           | Typed entities hydrated from database rows                        | 1     |
| `Repository`      | Data access — the only layer allowed to build SQL                 | 1     |
| `Support`         | Settings, encryption, cache, array and string helpers             | 1     |
| `Logging`         | PSR-3 style logger with secret redaction                          | 1     |
| `Cron`            | Scheduled jobs and custom intervals                               | 1     |
| `ServiceProvider` | Wiring of each layer into the container                           | 1     |
| `Service`         | Business logic: providers, sync, provisioning, billing            | 2–8   |
| `Http`            | REST client, requests, responses, authentication                  | 2     |
| `Admin`           | Admin screens, controllers, assets                                | 2     |
| `WooCommerce`     | Product type, cart, checkout, order handling                      | 4     |
| `Frontend`        | Customer dashboard, shortcodes, templates                         | 6     |
| `Ajax`            | AJAX controllers                                                  | 6–8   |

### Rules the codebase enforces

* No SQL outside `Repository`. Column names are validated against a declared map and every value is
  bound through `wpdb::prepare()`.
* WordPress core tables are never modified — the plugin owns 13 prefixed tables of its own.
* Credentials are stored as authenticated ciphertext and never leave the service layer in clear.
* Log context is redacted before it is written, so a secret cannot reach the log table.
* Provisioning always uses backend identifiers, never names or prices.

## Build phases

| Phase | Scope                                                        | Status      |
| ----- | ------------------------------------------------------------ | ----------- |
| 1     | Architecture, folder structure, database schema, bootstrap    | Delivered   |
| 2     | Settings, provider management, authentication                 | Planned     |
| 3     | Synchronisation engine                                        | Planned     |
| 4     | WooCommerce product type                                      | Planned     |
| 5     | Provisioning engine                                           | Planned     |
| 6     | Customer dashboard                                            | Planned     |
| 7     | VM controls                                                   | Planned     |
| 8     | Upgrade system                                                | Planned     |
| 9     | Optimisation, security audit, testing, documentation          | Planned     |

## Development

```bash
cd cloud-vm-manager
composer install
composer run lint      # php -l over every PHP file
composer run compat    # PHP 7.4+ compatibility sniff
```

## Configuration constants

| Constant               | Purpose                                                                 |
| ---------------------- | ----------------------------------------------------------------------- |
| `CVM_ENCRYPTION_KEY`   | Overrides the key material used to encrypt stored credentials.          |

Define it in `wp-config.php` to keep credentials readable after a salt rotation:

```php
define( 'CVM_ENCRYPTION_KEY', 'a-long-random-string' );
```
