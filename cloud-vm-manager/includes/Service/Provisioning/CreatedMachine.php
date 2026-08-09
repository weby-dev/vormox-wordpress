<?php

/**
 * Machine named by a creation response.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Service\Provisioning;

defined('ABSPATH') || exit;

/**
 * One entry of the `vms` list a creation response returns.
 *
 * The response names the machine straight away:
 *
 *     {
 *       "orderId": 1258, "vmid": 125, "paymentId": 1258,
 *       "groupId": "BULK-...", "vmip": "10.0.0.35",
 *       "message": "Paid fully. Provisioning started.",
 *       "vms": [{ "dbVmId": 675, "vmName": "dev-ubuntu-4gb-20gb",
 *                 "vmid": 125, "vmip": "10.0.0.35" }],
 *       "status": "COMPLETED"
 *     }
 *
 * Two identifiers arrive together and they are not interchangeable. `dbVmId` is
 * the database identifier every documented endpoint path takes, so it is what
 * gets stored as the machine identifier. `vmid` is the hypervisor identifier,
 * kept for support and never used in a request.
 *
 * `status: COMPLETED` describes the payment, not the machine: the message that
 * comes with it says provisioning has only just started.
 */
final class CreatedMachine
{
    /**
     * @var int
     */
    private $remoteVmId;

    /**
     * @var int
     */
    private $proxmoxVmid;

    /**
     * @var string
     */
    private $hostname;

    /**
     * @var string
     */
    private $ipAddress;

    /**
     * @var array<string, mixed>
     */
    private $payload;

    /**
     * @param array<string, mixed> $payload
     */
    private function __construct(
        int $remoteVmId,
        int $proxmoxVmid,
        string $hostname,
        string $ipAddress,
        array $payload
    ) {
        $this->remoteVmId = $remoteVmId;
        $this->proxmoxVmid = $proxmoxVmid;
        $this->hostname = $hostname;
        $this->ipAddress = $ipAddress;
        $this->payload = $payload;
    }

    /**
     * Read one `vms` entry.
     *
     * @param mixed $entry
     *
     * @return self|null Null when the entry carries no database identifier,
     *                   which is the only field the plugin cannot work without.
     */
    public static function fromEntry($entry): ?self
    {
        if (!is_array($entry)) {
            return null;
        }

        $remoteVmId = isset($entry['dbVmId']) && is_numeric($entry['dbVmId']) ? (int) $entry['dbVmId'] : 0;

        if ($remoteVmId <= 0) {
            return null;
        }

        $proxmoxVmid = isset($entry['vmid']) && is_numeric($entry['vmid']) ? (int) $entry['vmid'] : 0;

        return new self(
            $remoteVmId,
            $proxmoxVmid,
            isset($entry['vmName']) ? (string) $entry['vmName'] : '',
            isset($entry['vmip']) ? (string) $entry['vmip'] : '',
            $entry
        );
    }

    /**
     * Read every machine a creation response names.
     *
     * A creation request carrying a quantity produces one entry per machine,
     * which is why the response groups them under a bulk group identifier.
     *
     * @param array<string, mixed> $response
     *
     * @return self[]
     */
    public static function fromResponse(array $response): array
    {
        if (!isset($response['vms']) || !is_array($response['vms'])) {
            return [];
        }

        $machines = [];

        foreach ($response['vms'] as $entry) {
            $machine = self::fromEntry($entry);

            if ($machine instanceof self) {
                $machines[] = $machine;
            }
        }

        return $machines;
    }

    public function remoteVmId(): int
    {
        return $this->remoteVmId;
    }

    public function proxmoxVmid(): int
    {
        return $this->proxmoxVmid;
    }

    public function hostname(): string
    {
        return $this->hostname;
    }

    public function ipAddress(): string
    {
        return $this->ipAddress;
    }

    /**
     * The stored fields this machine contributes to an order row.
     *
     * @return array<string, mixed>
     */
    public function toColumns(): array
    {
        return [
            'remote_vm_id' => $this->remoteVmId,
            'proxmox_vmid' => $this->proxmoxVmid,
            'hostname' => $this->hostname,
            'ip_address' => $this->ipAddress,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return $this->payload;
    }
}
