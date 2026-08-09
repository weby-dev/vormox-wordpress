<?php

/**
 * Machine metrics and storage.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Service\Vm;

use CloudVmManager\Exception\ApiException;
use CloudVmManager\Http\ApiRequest;
use CloudVmManager\Http\Endpoints;
use CloudVmManager\Model\LogEntry;
use CloudVmManager\Model\Provider;
use CloudVmManager\Model\VmOrder;
use CloudVmManager\Service\Provider\ProviderGateway;
use CloudVmManager\Support\Arr;
use CloudVmManager\Support\Cache;

defined('ABSPATH') || exit;

/**
 * Live performance and disk usage of a machine.
 *
 * Responses are cached for a few seconds so a dashboard left open with a short
 * refresh interval cannot flood the backend, while still feeling live.
 */
final class VmMetricsService
{
    /**
     * Timeframes the metrics endpoint documents.
     *
     * @var string[]
     */
    private const TIMEFRAMES = ['hour', 'day', 'week', 'month', 'year'];

    private const DEFAULT_TIMEFRAME = 'hour';

    /**
     * How long a metrics answer is reused.
     */
    private const CACHE_TTL = 20;

    /**
     * @var ProviderGateway
     */
    private $gateway;

    /**
     * @var VmService
     */
    private $machines;

    /**
     * @var Cache
     */
    private $cache;

    public function __construct(ProviderGateway $gateway, VmService $machines, Cache $cache)
    {
        $this->gateway = $gateway;
        $this->machines = $machines;
        $this->cache = $cache;
    }

    /**
     * Normalise a requested timeframe to one the backend documents.
     */
    public function normalizeTimeframe(string $timeframe): string
    {
        $timeframe = strtolower(trim($timeframe));

        return in_array($timeframe, self::TIMEFRAMES, true) ? $timeframe : self::DEFAULT_TIMEFRAME;
    }

    /**
     * Timeframes offered in the interface.
     *
     * @return string[]
     */
    public function timeframes(): array
    {
        return self::TIMEFRAMES;
    }

    /**
     * Live metrics of a machine.
     *
     * @return array{
     *     available: bool,
     *     message: string,
     *     current: array<string, mixed>,
     *     series: array<string, array<int, float>>,
     *     labels: array<int, string>
     * }
     */
    public function metrics(VmOrder $order, string $timeframe = self::DEFAULT_TIMEFRAME): array
    {
        $timeframe = $this->normalizeTimeframe($timeframe);

        /*
         * A machine that is still starting up already has an identifier, but it
         * has no metrics to report yet, so it is not asked for any.
         */
        if (!$order->isProvisioned()) {
            return $this->emptyMetrics(__('This machine is not ready yet.', 'cloud-vm-manager'));
        }

        $key = sprintf('metrics:%d:%s', $order->id(), $timeframe);
        $cached = $this->cache->get($key);

        if (is_array($cached)) {
            return $cached;
        }

        $provider = $this->machines->providerFor($order);

        if (!$provider instanceof Provider) {
            return $this->emptyMetrics(__('The provider of this machine is unavailable.', 'cloud-vm-manager'));
        }

        try {
            $response = $this->gateway->request(
                $provider,
                ApiRequest::get(
                    Endpoints::vmMetrics($order->getRemoteVmId()),
                    ['timeframe' => $timeframe]
                ),
                ['channel' => LogEntry::CHANNEL_CUSTOMER, 'vm_order_id' => $order->id()]
            );

            $response->assertSuccessful();
        } catch (ApiException $exception) {
            return $this->emptyMetrics($exception->getMessage());
        }

        $metrics = $this->normalizeMetrics($response->data());

        $this->cache->put($key, $metrics, self::CACHE_TTL, $provider->id());

        return $metrics;
    }

    /**
     * File system usage reported by the guest agent.
     *
     * @return array{available: bool, message: string, drives: array<int, array<string, mixed>>}
     */
    public function storage(VmOrder $order): array
    {
        if ($order->getRemoteVmId() <= 0) {
            return [
                'available' => false,
                'message' => __('This machine is not ready yet.', 'cloud-vm-manager'),
                'drives' => [],
            ];
        }

        $provider = $this->machines->providerFor($order);

        if (!$provider instanceof Provider) {
            return [
                'available' => false,
                'message' => __('The provider of this machine is unavailable.', 'cloud-vm-manager'),
                'drives' => [],
            ];
        }

        try {
            $response = $this->gateway->request(
                $provider,
                ApiRequest::get(Endpoints::vmStorage($order->getRemoteVmId())),
                ['channel' => LogEntry::CHANNEL_CUSTOMER, 'vm_order_id' => $order->id()]
            );
        } catch (ApiException $exception) {
            return [
                'available' => false,
                'message' => $exception->getMessage(),
                'drives' => [],
            ];
        }

        if ($response->statusCode() === 503) {
            return [
                'available' => false,
                'message' => __(
                    'Live storage statistics are unavailable. Check that the QEMU guest agent is running.',
                    'cloud-vm-manager'
                ),
                'drives' => [],
            ];
        }

        if (!$response->isSuccessful()) {
            return [
                'available' => false,
                'message' => $response->errorMessage(),
                'drives' => [],
            ];
        }

        $drives = [];
        $entries = Arr::get($response->data(), 'drives', []);

        if (!is_array($entries)) {
            $entries = [];
        }

        foreach ($entries as $drive) {
            if (!is_array($drive)) {
                continue;
            }

            $drives[] = [
                'mount_point' => (string) Arr::first($drive, ['mountPoint', 'mount'], ''),
                'fs_type' => (string) Arr::first($drive, ['fsType', 'filesystem'], ''),
                'total' => (string) Arr::first($drive, ['totalGb', 'total'], ''),
                'used' => (string) Arr::first($drive, ['usedGb', 'used'], ''),
                'free' => (string) Arr::first($drive, ['freeGb', 'free'], ''),
                'percent' => $this->percentage((string) Arr::first($drive, ['usagePercentage', 'usage'], '0')),
            ];
        }

        return [
            'available' => true,
            'message' => '',
            'drives' => $drives,
        ];
    }

    /**
     * Turn the backend payload into a shape the dashboard can draw.
     *
     * @param array<string, mixed>|array<int, mixed> $data
     *
     * @return array{
     *     available: bool,
     *     message: string,
     *     current: array<string, mixed>,
     *     series: array<string, array<int, float>>,
     *     labels: array<int, string>
     * }
     */
    private function normalizeMetrics(array $data): array
    {
        $current = Arr::get($data, 'current', []);
        $current = is_array($current) ? $current : [];

        $points = Arr::get($data, 'graphs.data', []);

        if (!is_array($points)) {
            $points = [];
        }

        $series = ['cpu' => [], 'memory' => [], 'netin' => [], 'netout' => []];
        $labels = [];

        foreach ($points as $point) {
            if (!is_array($point)) {
                continue;
            }

            $labels[] = (string) Arr::first($point, ['time', 'timestamp', 'date'], '');

            $series['cpu'][] = $this->ratioToPercent(Arr::get($point, 'cpu', 0));
            $series['memory'][] = $this->memoryPercent($point);
            $series['netin'][] = (float) $this->numeric(Arr::first($point, ['netin', 'netIn'], 0));
            $series['netout'][] = (float) $this->numeric(Arr::first($point, ['netout', 'netOut'], 0));
        }

        $diskTotal = (float) $this->numeric(Arr::first($current, ['disk_total_bytes', 'diskTotalBytes'], 0));
        $diskUsed = (float) $this->numeric(Arr::first($current, ['disk_used_bytes', 'diskUsedBytes'], 0));

        /*
         * The transfer counters are carried through so usage is summed from the
         * same reading the dashboard draws, rather than costing a second call.
         */
        $netIn = Arr::first($current, ['netin', 'netIn', 'networkIn', 'trafficIn'], null);
        $netOut = Arr::first($current, ['netout', 'netOut', 'networkOut', 'trafficOut'], null);

        return [
            'available' => true,
            'message' => '',
            'current' => [
                'status' => (string) Arr::get($current, 'status', ''),
                'cpu_percent' => $this->ratioToPercent(Arr::get($current, 'cpu', 0)),
                'disk_total_bytes' => $diskTotal,
                'disk_used_bytes' => $diskUsed,
                'disk_percent' => $diskTotal > 0 ? round($diskUsed / $diskTotal * 100, 1) : 0.0,
                'net_in_bytes' => is_numeric($netIn) ? (float) $netIn : null,
                'net_out_bytes' => is_numeric($netOut) ? (float) $netOut : null,
            ],
            'series' => $series,
            'labels' => $labels,
        ];
    }

    /**
     * The backend reports CPU load as a ratio between zero and one.
     *
     * @param mixed $value
     */
    private function ratioToPercent($value): float
    {
        $number = (float) $this->numeric($value);

        if ($number <= 1.0) {
            $number *= 100;
        }

        return round(max(0.0, min($number, 100.0)), 2);
    }

    /**
     * Memory usage of one data point as a percentage.
     *
     * @param array<string, mixed> $point
     */
    private function memoryPercent(array $point): float
    {
        $used = (float) $this->numeric(Arr::first($point, ['mem', 'memory', 'memUsed'], 0));
        $total = (float) $this->numeric(Arr::first($point, ['maxmem', 'memoryTotal', 'memTotal'], 0));

        if ($total <= 0) {
            return 0.0;
        }

        return round(max(0.0, min($used / $total * 100, 100.0)), 2);
    }

    /**
     * Read a percentage that may arrive as "20.0%".
     */
    private function percentage(string $value): float
    {
        return round((float) str_replace('%', '', trim($value)), 1);
    }

    /**
     * @param mixed $value
     *
     * @return float|int
     */
    private function numeric($value)
    {
        return is_numeric($value) ? $value + 0 : 0;
    }

    /**
     * @return array{
     *     available: bool,
     *     message: string,
     *     current: array<string, mixed>,
     *     series: array<string, array<int, float>>,
     *     labels: array<int, string>
     * }
     */
    private function emptyMetrics(string $message): array
    {
        return [
            'available' => false,
            'message' => $message,
            'current' => [
                'status' => '',
                'cpu_percent' => 0.0,
                'disk_total_bytes' => 0.0,
                'disk_used_bytes' => 0.0,
                'disk_percent' => 0.0,
                'net_in_bytes' => null,
                'net_out_bytes' => null,
            ],
            'series' => ['cpu' => [], 'memory' => [], 'netin' => [], 'netout' => []],
            'labels' => [],
        ];
    }
}
