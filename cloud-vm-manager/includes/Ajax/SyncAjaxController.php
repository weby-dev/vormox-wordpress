<?php

/**
 * Synchronisation AJAX endpoints.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Ajax;

use CloudVmManager\Admin\Access;
use CloudVmManager\Model\Provider;
use CloudVmManager\Service\Provider\ProviderService;
use CloudVmManager\Service\Sync\CatalogueSynchronizer;
use CloudVmManager\Service\Sync\SyncResult;

defined('ABSPATH') || exit;

/**
 * Runs a catalogue synchronisation from the admin without a page reload.
 */
final class SyncAjaxController extends AbstractAjaxController
{
    public const ACTION_SYNC = 'cvm_sync_provider';

    /**
     * @var ProviderService
     */
    private $providers;

    /**
     * @var CatalogueSynchronizer
     */
    private $synchronizer;

    public function __construct(ProviderService $providers, CatalogueSynchronizer $synchronizer)
    {
        $this->providers = $providers;
        $this->synchronizer = $synchronizer;
    }

    public function register(): void
    {
        add_action('wp_ajax_' . self::ACTION_SYNC, [$this, 'sync']);
    }

    /**
     * Synchronise the catalogue of one provider.
     */
    public function sync(): void
    {
        $this->authorize(Access::AJAX_NONCE, Access::capability());

        $provider = $this->providers->find($this->intParam('provider_id'));

        if (!$provider instanceof Provider) {
            $this->failure(__('That provider no longer exists.', 'cloud-vm-manager'), 404);

            return;
        }

        $results = $this->synchronizer->syncProvider($provider);
        $payload = [];
        $failed = 0;
        $changes = 0;

        foreach ($results as $result) {
            $payload[] = $result->toArray();
            $changes += $result->changes();

            if ($result->isFailed()) {
                ++$failed;
            }
        }

        if ($failed > 0) {
            $this->failure($this->summary($results), 200, ['results' => $payload]);

            return;
        }

        $this->success(
            [
                'message' => $this->summary($results),
                'changes' => $changes,
                'results' => $payload,
                'synced_at' => gmdate('Y-m-d H:i:s'),
            ]
        );
    }

    /**
     * One sentence describing what the run did.
     *
     * @param SyncResult[] $results
     */
    private function summary(array $results): string
    {
        $added = 0;
        $updated = 0;
        $removed = 0;
        $failed = [];

        foreach ($results as $result) {
            $added += $result->added();
            $updated += $result->updated();
            $removed += $result->removed();

            if ($result->isFailed()) {
                $failed[] = $result->resource();
            }
        }

        if ($failed !== []) {
            return sprintf(
                /* translators: %s: comma separated list of resource names. */
                __('Synchronisation failed for: %s', 'cloud-vm-manager'),
                implode(', ', $failed)
            );
        }

        return sprintf(
            /* translators: 1: added records, 2: updated records, 3: removed records. */
            __('%1$d added, %2$d updated, %3$d removed.', 'cloud-vm-manager'),
            $added,
            $updated,
            $removed
        );
    }
}
