# Architecture

Cloud VM Manager is layered. Each layer talks only to the layer below it, and
every layer is programmed against an interface rather than a concrete class.

```
Bootstrap ─── Plugin ─── Container ─── ServiceProvider
                             │
     ┌───────────────────────┼───────────────────────┐
     │                       │                       │
   Admin                 Frontend               WooCommerce
   Ajax                  Templates              ProductType
     │                       │                       │
     └───────── Service (Provider, Sync, Provisioning, Vm, Billing) ─────────┘
                             │
                    Http (ApiClient, Endpoints)
                             │
              Repository ─── Model ─── Database
                             │
                  Support (Settings, Cache, Encryptor)
                        Logging
```

## Boot sequence

1. `cloud-vm-manager.php` defines the constants, registers the PSR-4 autoloader
   and the lifecycle hooks, and declares WooCommerce HPOS compatibility.
2. On `plugins_loaded` (priority 5) `Requirements` checks PHP, WordPress and the
   `json` and `openssl` extensions. A failure stops the boot and shows a notice.
3. `Plugin::boot()` registers every service provider into the container, runs
   `Migrator::maybeUpgrade()`, boots the providers, then fires
   `cloud_vm_manager_booted`.

Service providers split `register()` (bind into the container, no hooks) from
`boot()` (attach hooks), so every service is known before any of them runs.

## Rules the codebase enforces

| Rule | Where it is enforced |
| --- | --- |
| No SQL outside the repository layer | `AbstractRepository` is the only place that builds statements |
| Column names are validated | `AbstractRepository::assertColumn()` against a declared map |
| Values are always bound | `wpdb::prepare()` with per-column printf formats |
| WordPress core tables are never modified | The plugin owns 13 prefixed tables |
| Credentials never leave the service layer in clear | `Encryptor` at the repository boundary |
| Secrets never reach the log | `Redactor` runs on every log write |
| Provisioning uses identifiers, never names or prices | `Endpoints` plus the id-only request bodies |
| No undocumented endpoint can be called | `Endpoints` is the only source of paths |
| A customer only ever reaches their own machine | `VmService::findOwned()` |

## Layers

### Bootstrap
Requirements, activation, deactivation and uninstall. Activation installs the
tables, seeds the settings and schedules the cron jobs, including across a
multisite network. Uninstall removes data only when the operator opted in.

### Container
A PSR-11 style container with lazy factories, shared instances, aliases,
decoration, cycle detection and constructor autowiring.

### Database
`TableRegistry` is the single source of table names. `Schema` declares the
tables for `dbDelta()`. `Migrator` runs the installer only when the stored
schema version is behind, behind a transient lock.

### Model and Repository
Models are typed entities with declared casts and domain behaviour.
Repositories are the only place that builds SQL.

### Http
`Endpoints` is a registry of every documented endpoint. `ApiRequest` is
immutable so a prepared call can be replayed with a refreshed token.
`ApiResponse` decodes once and converts a failure status into a typed
exception. `ApiClient` handles timeouts, TLS, retries, JSON and logging.

### Service
* `Service\Provider` — provider CRUD, authentication, gateway, connection test.
* `Service\Sync` — catalogue synchronisation with change detection.
* `Service\Provisioning` — turning a paid order into a running machine.
* `Service\Vm` — reading and controlling a machine.
* `Service\Billing` — upgrade options, quotes and payment.

### Presentation
`Admin` renders the WordPress screens, `Frontend` the customer dashboard behind
the `[cloud_vm_dashboard]` shortcode, `WooCommerce` the product type, and `Ajax`
serves both. Templates live in `templates/` and can be overridden by a theme in
a `cloud-vm-manager/` directory.

## Request flows

**Catalogue synchronisation**
`SyncJob` or the Sync button → `CatalogueSynchronizer` → `ZoneSync`,
`IsoTemplateSync`, `PlanSync` → `ProviderGateway` → `ApiClient` → repositories,
one `sync_runs` row per resource.

**Provisioning**
WooCommerce marks an order paid → `OrderHandler` creates one `vm_orders` row per
machine line → `ProvisioningService` → `VmProvisioner` creates the machine →
`VmResolver` reads it back → the row is completed, or scheduled for retry.

**Customer action**
Dashboard → AJAX (nonce, signed in, ownership) → `VmControlService` (lock guard)
→ `ProviderGateway` → backend.
