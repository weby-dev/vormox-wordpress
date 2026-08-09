# Backend API usage

Every endpoint the plugin calls, and where it calls it from. Nothing outside
this list is ever requested: `CloudVmManager\Http\Endpoints` is the only source
of paths in the codebase, so a call that is not documented cannot be made by
accident.

## Authentication

| Method | Path | Used by |
| --- | --- | --- |
| POST | `/api/login` | `ProviderAuthenticator::login()` |

The documentation defines no refresh endpoint, so renewing a token means
logging in again. The expiry is read from the `exp` claim of the token when it
is a JWT, and the plugin renews five minutes before it. When the backend
rejects a token early, `ProviderGateway` re-authenticates once and replays the
original call.

## Catalogue

| Method | Path | Used by |
| --- | --- | --- |
| GET | `/api/users/zones` | `ZoneSync` |
| GET | `/api/users/zones/{zoneId}/isos` | `IsoTemplateSync` |
| GET | `/api/pricing/{shared\|dedicated}/{cpu\|ram\|disk\|bandwidth}` | `PlanSync` |

A 404 on a dedicated catalogue is treated as "not offered" and reported as
skipped, so a shared-only backend synchronises cleanly.

## Provisioning

| Method | Path | Used by |
| --- | --- | --- |
| GET | `/api/user/payments/gateways` | `GatewayResolver` |
| POST | `/api/users/vms/create?gateway={gateway}` | `VmProvisioner` |
| GET | `/api/users/orders/overview` | `VmResolver` |
| GET | `/api/users/orders/{vmId}/details` | `VmResolver`, `VmService` |

The creation body carries identifiers only — zone, image, plan type, the four
pricing identifiers, months, quantity, the wallet flag and the coupon code. No
price the store calculated is ever submitted; the backend validates the pricing
identifiers itself.

### What the creation response gives back

```json
{
  "orderId": 1258,
  "vmid": 125,
  "paymentId": 1258,
  "groupId": "BULK-1786303881790-0C39",
  "vmip": "10.0.0.35",
  "message": "Paid fully. Provisioning started.",
  "vms": [
    { "dbVmId": 675, "vmName": "dev-ubuntu-4gb-20gb", "vmid": 125, "vmip": "10.0.0.35" }
  ],
  "status": "COMPLETED"
}
```

Every field of `vms[]` is stored the moment the call returns:

| Response field | Stored as | Used for |
| --- | --- | --- |
| `dbVmId` | `remote_vm_id` | Every documented endpoint path |
| `vmid` | `proxmox_vmid` | Support and diagnostics only |
| `vmName` | `hostname` | Shown to the customer |
| `vmip` | `ip_address` | Shown to the customer |

`status: COMPLETED` describes the payment, not the machine — the message beside
it says provisioning has only just started. A machine takes about a minute to
build, so it is polled on `/api/users/orders/{dbVmId}/details` until it answers,
and only then is it shown as ready.

A creation carrying a quantity returns one entry per machine under a bulk group
identifier. Each entry becomes its own local row, so a customer can manage every
machine they bought, and the billed amounts are divided across those rows
without changing the order total.

The overview is only read back when a response names no machine at all, matching
on the payment, order and group identifiers instead.

## Machine management

| Method | Path | Used by |
| --- | --- | --- |
| GET | `/api/users/vms/{vmId}/metrics?timeframe=` | `VmMetricsService` |
| GET | `/api/users/vms/{vmId}/storage` | `VmMetricsService` |
| GET | `/api/vms/{vmId}/lock-status` | `VmService` |
| GET | `/api/users/audit-logs?limit=` | `VmService` |
| POST | `/api/users/{userId}/vms/{vmId}/control?action=` | `VmControlService::power()` |
| POST | `/api/users/{userId}/vms/{vmId}/rebuild?isoId=` | `VmControlService::rebuild()` |
| PUT | `/api/users/{userId}/vms/{vmId}/password` | `VmControlService::changePassword()` |
| POST | `/api/users/vms/{vmId}/mac/regenerate` | `VmControlService::regenerateMac()` |
| POST | `/api/users/vms/{vmId}/reconfigure-network` | `VmControlService::reconfigureNetwork()` |

Only the six documented power actions are accepted: start, stop, reboot, pause,
hibernate and resume. Anything else is refused locally and never reaches the
backend.

A 503 from the storage endpoint is surfaced as the documented guest agent hint
rather than as an error.

## Billing

| Method | Path | Used by |
| --- | --- | --- |
| GET | `/api/pricing/upgrades/{vmId}` | `UpgradeService::options()` |
| POST | `/api/billing/price/calculate-renewal` | `UpgradeService::quote()` |
| POST | `/api/vms/renew/{vmId}/upgrade-renew?gateway=` | `UpgradeService::apply()` |
| GET | `/api/wallet` | `WalletService::balance()` |
| GET | `/api/wallet/logs` | `WalletService::transactions()` |
| GET | `/api/users/orders/past-orders` | `WalletService::pastOrders()` |
| GET | `/api/users/orders/{paymentId}/invoice` | `WalletService::invoice()` |

The plugin never computes a pro rata amount. Every figure a customer sees comes
from the calculation endpoint, so the amount they confirm is the amount the
backend charges.

## Public

| Method | Path | Used by |
| --- | --- | --- |
| GET | `/api/public/settings/general` | `ConnectionTester` |

## Identifiers

Two different identifiers exist for a machine and they are not
interchangeable:

* **`remote_vm_id`** — the internal database identifier. Every documented
  endpoint path uses this one.
* **`proxmox_vmid`** — the hypervisor identifier. Stored for support and
  diagnostics only, never used in a request.

The `{userId}` in the control paths is submitted as documented, although the
backend resolves the account from the bearer token and ignores it.

## Response shapes the documentation does not define

Where the documentation names an endpoint without its field names, the plugin
reads the identifier from `id` and resolves the descriptive fields from the
names a backend commonly uses, always storing the raw payload alongside so
nothing is lost.

| Endpoint | Resolved defensively |
| --- | --- |
| `/api/users/zones` | name, country, description |
| `/api/pricing/*/ram\|disk\|bandwidth` | the specification value, falling back to the leading number of the documented `label` |
| `/api/users/orders/overview` | machine id, hostname, address |
| `/api/users/orders/{vmId}/details` | operating system, backend user id |

A detail record that omits a field never blanks what the creation response
already supplied, so a sparse response cannot erase a machine's address or
name.

If you can supply a real response for any of these, the mapping can be pinned
exactly.

## Not implemented

Reseller endpoints (`/api/reseller/*`) and the reseller admin features are
deliberately absent: the plugin operates as a normal user account.

Customer registration (`/api/register/*`) is not automated because it requires
an emailed OTP that no server-to-server call can complete. Machines are
provisioned under the account configured on the provider, and the mapping from
a WordPress user to their machines is held locally.
