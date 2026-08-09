# Configuration

## Getting started

1. Activate the plugin with WooCommerce active.
2. Open **Cloud VM → Providers** and add your provider: name, API URL, account
   email and password. Credentials are encrypted before they are stored.
3. Press **Test** on the provider row. This calls the public settings endpoint
   to prove the URL resolves, then the login endpoint to prove the credentials
   work, and reports each step separately.
4. Open **Cloud VM → Synchronisation** and press **Synchronise**. This pulls the
   zones, operating system images and the four pricing resources.
5. Create a product, choose the **Cloud Virtual Machine** type, and configure it
   on the *Virtual machine* tab.
6. Put `[cloud_vm_dashboard]` on a page so customers can manage their machines, then
   name that page under **Settings → Customer dashboard**.
7. Set the provider's **Host URL** to its panel address. It is what the
   *Login to …* buttons open, on the machine list and on the customer dashboard.

## Settings

Found under **Cloud VM → Settings**.

### API connection
| Setting | Default | Purpose |
| --- | --- | --- |
| `api_timeout` | 30 | Seconds before a request is abandoned. A provider may override it. |
| `api_retries` | 2 | Extra attempts after a network error or a temporary server error. |
| `api_retry_delay` | 2 | Seconds before the next attempt, multiplied by the attempt number. |

### Synchronisation
| Setting | Default | Purpose |
| --- | --- | --- |
| `auto_sync_enabled` | on | Whether the scheduled synchronisation runs. |
| `sync_interval` | twicedaily | Recurrence of the synchronisation job. |
| `sync_retention_days` | 30 | How long run history is kept. |

### Pricing
| Setting | Default | Purpose |
| --- | --- | --- |
| `default_markup_type` | percent | How new catalogue entries are priced. |
| `default_markup_value` | 0 | Percentage or fixed amount added to the provider price. |

A price book entry edited by hand keeps its price; a synchronisation only
refreshes its provider cost.

### Provisioning
| Setting | Default | Purpose |
| --- | --- | --- |
| `provisioning_retry_limit` | 5 | Creation attempts before a machine is marked failed. A machine the backend confirmed it created is waited on instead, not retried. |
| `provisioning_retry_delay` | 300 | Seconds between attempts, growing per attempt. |
| `provisioning_gateway` | CASHFREE | Gateway named on the creation request. The endpoint rejects a request without one. |
| `use_wallet_balance` | on | Settle the platform charge from the account wallet first. |

### Customer dashboard
| Setting | Default | Purpose |
| --- | --- | --- |
| `enable_client_dashboard` | on | Whether customers may view and control machines. |
| `dashboard_page_id` | 0 | Page holding the shortcode. Every dashboard link is built from it, so pagination and machine links stay correct even when the shortcode is rendered outside its own page. Falls back to the permalink in the loop. |
| `metrics_refresh_interval` | 30 | Seconds between live refreshes. |

### Performance
| Setting | Default | Purpose |
| --- | --- | --- |
| `cache_enabled` | on | Cache API responses in the `api_cache` table. |
| `cache_ttl` | 300 | Default lifetime of a cached response. |

### Logging
| Setting | Default | Purpose |
| --- | --- | --- |
| `debug_mode` | off | Record every request and response. Credentials are always redacted. |
| `log_level` | info | Minimum severity stored. Debug mode lowers it to debug. |
| `log_retention_days` | 30 | How long entries are kept. |

### Uninstall
| Setting | Default | Purpose |
| --- | --- | --- |
| `delete_data_on_uninstall` | off | Drop the tables and settings when the plugin is deleted. |

## Constants

Define in `wp-config.php`:

```php
define( 'CVM_ENCRYPTION_KEY', 'a-long-random-string' );
```

Without it the encryption key is derived from the WordPress salts, which means
rotating the salts makes stored credentials unreadable and providers have to be
re-authenticated. Setting the constant avoids that.

## Scheduled jobs

| Hook | Recurrence | Work |
| --- | --- | --- |
| `cvm_sync_catalogue` | `sync_interval` | Refresh the provider catalogue. |
| `cvm_provisioning_retry` | every five minutes | Finish machines that are still pending. |
| `cvm_provisioning_build_check` | one-off, ~20s after creation | Check a machine that has just been created, so a customer never waits for the next five minute tick. |
| `cvm_usage_refresh` | hourly | Read the disk and transfer figures of the machines whose usage is most out of date. |
| `cvm_maintenance` | daily | Prune expired cache rows, old logs and old sync runs. |

## Multisite

Tables are created per site. Network activation installs the first 100 sites and
a site created later is installed when it is initialised. Larger networks are
upgraded lazily by the migrator on each site's first request.
