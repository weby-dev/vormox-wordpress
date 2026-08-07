# Hooks

Every extension point the plugin exposes.

## Actions

| Hook | Arguments | Fired when |
| --- | --- | --- |
| `cloud_vm_manager_booted` | `Container $container` | Every service has been booted. |
| `cloud_vm_manager_activated` | — | The plugin finished installing on a site. |
| `cloud_vm_manager_deactivated` | — | The plugin was deactivated. |
| `cloud_vm_manager_schema_upgraded` | `string $from, string $to` | The database schema was upgraded. |
| `cloud_vm_manager_catalogue_synced` | `Provider $provider, SyncResult[] $results` | A provider catalogue finished synchronising. |
| `cloud_vm_manager_vm_provisioned` | `VmOrder $order, array $details` | A machine finished provisioning. |
| `cloud_vm_manager_provisioning_failed` | `VmOrder $order, string $message` | A machine could not be provisioned. |
| `cloud_vm_manager_vm_action` | `VmOrder $order, string $action` | A control action was accepted by the backend. |
| `cloud_vm_manager_vm_upgraded` | `VmOrder $order, array $priceIds, int $monthsToAdd` | A machine was upgraded or renewed. |

### Example: notify a customer when their machine is ready

```php
add_action(
    'cloud_vm_manager_vm_provisioned',
    function ( $order, $details ) {
        $user = get_userdata( $order->getUserId() );

        if ( ! $user ) {
            return;
        }

        wp_mail(
            $user->user_email,
            __( 'Your machine is ready', 'my-theme' ),
            sprintf( 'Address: %s', $order->getIpAddress() )
        );
    },
    10,
    2
);
```

## Filters

| Hook | Argument | Purpose |
| --- | --- | --- |
| `cloud_vm_manager_admin_capability` | `string $capability` | Capability guarding every admin screen and admin AJAX endpoint. Defaults to `manage_options`. |
| `cloud_vm_manager_service_providers` | `string[] $providers` | Service provider classes the plugin boots. |
| `cloud_vm_manager_cron_jobs` | `string[] $jobs` | Job classes the cron manager owns. |
| `cloud_vm_manager_http_args` | `array $args, ApiRequest $request` | WordPress HTTP arguments before a backend request is sent. |
| `cloud_vm_manager_create_vm_body` | `array $body, VmOrder $order` | Body of a machine creation request. |

### Example: let shop managers manage providers

```php
add_filter(
    'cloud_vm_manager_admin_capability',
    function () {
        return 'manage_woocommerce';
    }
);
```

### Example: add a proxy to every backend request

```php
add_filter(
    'cloud_vm_manager_http_args',
    function ( $args ) {
        $args['headers']['X-Forwarded-For'] = '203.0.113.10';

        return $args;
    }
);
```

## Shortcode

`[cloud_vm_dashboard]` renders the customer dashboard. It shows a sign in panel
to signed out visitors and nothing at all when the dashboard is switched off in
the settings.

Query parameters it understands:

| Parameter | Purpose |
| --- | --- |
| `cvm_machine` | Show one machine instead of the list. |
| `cvm_page` | Page of the machine list. |

## Template overrides

Any template under `templates/` can be overridden by a theme. Copy the file to
`your-theme/cloud-vm-manager/` keeping the same relative path, for example
`your-theme/cloud-vm-manager/frontend/machine.php`.

Templates receive already validated data and escape every value where it is
printed. An override must do the same.
