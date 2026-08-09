<?php

/**
 * Machine usage collection.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Service\Vm;

use CloudVmManager\Contracts\LoggerInterface;
use CloudVmManager\Model\LogEntry;
use CloudVmManager\Model\VmOrder;
use CloudVmManager\Repository\VmOrderRepository;

defined('ABSPATH') || exit;

/**
 * Records how much disk and bandwidth each machine is using.
 *
 * The figures come from the endpoints the dashboard already reads, so nothing
 * new is requested: the disk total is the sum of the drives the guest agent
 * reports, and the transfer counters come from the metrics record. Storing them
 * means the machine list can show usage without a request per row, and a
 * machine that is switched off keeps the last figures that were true.
 */
final class UsageService
{
    /**
     * Timeframe the transfer counters are read over.
     */
    private const TIMEFRAME = 'month';

    private const MB_PER_GB = 1024;

    /**
     * @var VmMetricsService
     */
    private $metrics;

    /**
     * @var VmOrderRepository
     */
    private $orders;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        VmMetricsService $metrics,
        VmOrderRepository $orders,
        LoggerInterface $logger
    ) {
        $this->metrics = $metrics;
        $this->orders = $orders;
        $this->logger = $logger;
    }

    /**
     * Read and store the usage of one machine.
     *
     * @return array{disk_used_mb: int, bandwidth_used_mb: int}|null Null when the
     *                                                              machine cannot
     *                                                              report yet.
     */
    public function refresh(VmOrder $order): ?array
    {
        if (!$order->isProvisioned() || $order->isTerminated()) {
            return null;
        }

        $storage = $this->metrics->storage($order);
        $disk = $storage['available'] === true ? $this->diskUsedMb($storage['drives']) : null;
        $transfer = $this->transferUsedMb($order);

        if ($disk === null && $transfer === null) {
            return null;
        }

        $usage = [
            'disk_used_mb' => $disk ?? $order->getDiskUsedMb(),
            'bandwidth_used_mb' => $transfer ?? $order->getBandwidthUsedMb(),
        ];

        $this->orders->update(
            $order->id(),
            array_merge($usage, ['usage_updated_at' => gmdate('Y-m-d H:i:s')])
        );

        return $usage;
    }

    /**
     * Refresh the machines whose usage is most out of date.
     *
     * @return int Machines refreshed.
     */
    public function refreshBatch(int $limit = 20): int
    {
        $refreshed = 0;

        foreach ($this->orders->staleUsage($limit) as $order) {
            if ($this->refresh($order) !== null) {
                ++$refreshed;
            }
        }

        if ($refreshed > 0) {
            $this->logger->info(
                sprintf('Usage refreshed for %d machine(s).', $refreshed),
                ['channel' => LogEntry::CHANNEL_CRON]
            );
        }

        return $refreshed;
    }

    /**
     * Sum the used space of every drive the guest agent reported.
     *
     * @param array<int, array<string, mixed>> $drives
     */
    private function diskUsedMb(array $drives): ?int
    {
        if ($drives === []) {
            return null;
        }

        $total = 0.0;

        foreach ($drives as $drive) {
            $used = isset($drive['used']) ? (string) $drive['used'] : '';

            if (!is_numeric($used)) {
                continue;
            }

            /* The storage endpoint reports gigabytes. */
            $total += (float) $used * self::MB_PER_GB;
        }

        return (int) round($total);
    }

    /**
     * Transfer counters of the current period.
     *
     * The metrics service resolves the counters and leaves them null when the
     * backend named none, which is why a machine that cannot report keeps the
     * last figure that was true rather than being reset to zero.
     */
    private function transferUsedMb(VmOrder $order): ?int
    {
        $metrics = $this->metrics->metrics($order, self::TIMEFRAME);

        if ($metrics['available'] !== true || !is_array($metrics['current'])) {
            return null;
        }

        $in = $metrics['current']['net_in_bytes'] ?? null;
        $out = $metrics['current']['net_out_bytes'] ?? null;

        if ($in === null && $out === null) {
            return null;
        }

        /* The counters are byte totals. */
        $bytes = (float) ($in ?? 0.0) + (float) ($out ?? 0.0);

        return (int) round($bytes / self::MB_PER_GB / self::MB_PER_GB);
    }
}
