<?php

/**
 * Provider management.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Service\Provider;

use CloudVmManager\Contracts\EncryptorInterface;
use CloudVmManager\Contracts\LoggerInterface;
use CloudVmManager\Exception\ValidationException;
use CloudVmManager\Model\LogEntry;
use CloudVmManager\Model\Provider;
use CloudVmManager\Repository\ProviderRepository;
use CloudVmManager\Support\Cache;

defined('ABSPATH') || exit;

/**
 * Creates, validates and removes cloud providers.
 *
 * Credentials are encrypted here and nowhere else, and a blank password on an
 * update keeps the stored one, so an operator can edit a provider without ever
 * seeing or resubmitting its secrets.
 */
final class ProviderService
{
    private const MIN_TIMEOUT = 5;
    private const MAX_TIMEOUT = 300;

    /**
     * @var ProviderRepository
     */
    private $providers;

    /**
     * @var EncryptorInterface
     */
    private $encryptor;

    /**
     * @var ProviderAuthenticator
     */
    private $authenticator;

    /**
     * @var Cache
     */
    private $cache;

    /**
     * @var ProviderPurger
     */
    private $purger;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        ProviderRepository $providers,
        EncryptorInterface $encryptor,
        ProviderAuthenticator $authenticator,
        Cache $cache,
        ProviderPurger $purger,
        LoggerInterface $logger
    ) {
        $this->providers = $providers;
        $this->encryptor = $encryptor;
        $this->authenticator = $authenticator;
        $this->cache = $cache;
        $this->purger = $purger;
        $this->logger = $logger;
    }

    /**
     * Every provider, ordered for display.
     *
     * @return Provider[]
     */
    public function all(bool $activeOnly = false): array
    {
        return $this->providers->all($activeOnly);
    }

    public function find(int $id): ?Provider
    {
        return $this->providers->findProvider($id);
    }

    /**
     * Provider used when nothing names one explicitly.
     */
    public function getDefault(): ?Provider
    {
        return $this->providers->getDefault();
    }

    /**
     * Create a provider from submitted data.
     *
     * @param array<string, mixed> $input
     *
     * @throws ValidationException When the submitted data is incomplete.
     */
    public function create(array $input): Provider
    {
        $data = $this->validate($input, 0, true);
        $data['slug'] = $this->providers->uniqueSlug($data['name']);
        $data['status'] = Provider::STATUS_DISCONNECTED;

        $id = $this->providers->insert($data);

        if ((bool) ($input['is_default'] ?? false) || $this->providers->count() === 1) {
            $this->providers->markAsDefault($id);
        }

        $provider = $this->providers->findProvider($id);

        if ($provider === null) {
            throw new ValidationException(__('The provider could not be stored.', 'cloud-vm-manager'));
        }

        $this->logger->info(
            sprintf('Provider "%s" created.', $provider->getName()),
            ['channel' => LogEntry::CHANNEL_ADMIN, 'provider_id' => $id]
        );

        return $provider;
    }

    /**
     * Update a provider from submitted data.
     *
     * @param array<string, mixed> $input
     *
     * @throws ValidationException When the provider is unknown or the data is incomplete.
     */
    public function update(int $id, array $input): Provider
    {
        $existing = $this->providers->findProvider($id);

        if ($existing === null) {
            throw new ValidationException(__('The provider no longer exists.', 'cloud-vm-manager'));
        }

        $data = $this->validate($input, $id, false);

        if ($data['name'] !== $existing->getName()) {
            $data['slug'] = $this->providers->uniqueSlug($data['name'], $id);
        }

        $credentialsChanged = $this->credentialsChanged($existing, $data);

        if ($credentialsChanged && !isset($data['api_token'])) {
            $data['api_token'] = '';
            $data['token_expires_at'] = null;
            $data['status'] = Provider::STATUS_DISCONNECTED;
        }

        $this->providers->update($id, $data);

        if ((bool) ($input['is_default'] ?? false)) {
            $this->providers->markAsDefault($id);
        }

        $this->cache->flush($id);
        $this->authenticator->flush();

        $provider = $this->providers->findProvider($id);

        if ($provider === null) {
            throw new ValidationException(__('The provider could not be stored.', 'cloud-vm-manager'));
        }

        $this->logger->info(
            sprintf('Provider "%s" updated.', $provider->getName()),
            [
                'channel' => LogEntry::CHANNEL_ADMIN,
                'provider_id' => $id,
                'credentials_changed' => $credentialsChanged,
            ]
        );

        return $provider;
    }

    /**
     * Remove a provider together with the catalogue it owns.
     *
     * The catalogue is purged before the provider row so a failure part way
     * through leaves the provider in place and the operator able to retry,
     * rather than leaving rows nothing points at any more.
     */
    public function delete(int $id): bool
    {
        $provider = $this->providers->findProvider($id);

        if ($provider === null) {
            return false;
        }

        $this->cache->flush($id);
        $purged = $this->purger->purge($id);
        $deleted = $this->providers->delete($id);

        if ($deleted) {
            $this->logger->notice(
                sprintf('Provider "%s" deleted with %d catalogue rows.', $provider->getName(), $purged),
                ['channel' => LogEntry::CHANNEL_ADMIN, 'provider_id' => $id]
            );
        }

        return $deleted;
    }

    /**
     * Enable or disable a provider without deleting it.
     */
    public function setActive(int $id, bool $active): bool
    {
        $provider = $this->providers->findProvider($id);

        if ($provider === null) {
            return false;
        }

        $this->providers->update($id, ['is_active' => $active]);

        $this->logger->info(
            sprintf('Provider "%s" %s.', $provider->getName(), $active ? 'enabled' : 'disabled'),
            ['channel' => LogEntry::CHANNEL_ADMIN, 'provider_id' => $id]
        );

        return true;
    }

    /**
     * Mark a provider as the default one.
     */
    public function setDefault(int $id): bool
    {
        if ($this->providers->findProvider($id) === null) {
            return false;
        }

        $this->providers->markAsDefault($id);

        return true;
    }

    /**
     * Validate and normalise submitted provider data.
     *
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     *
     * @throws ValidationException When a required field is missing or malformed.
     */
    public function validate(array $input, int $ignoreId = 0, bool $isCreate = true): array
    {
        $errors = [];

        $name = sanitize_text_field((string) ($input['name'] ?? ''));

        if ($name === '') {
            $errors['name'] = __('A provider name is required.', 'cloud-vm-manager');
        }

        $apiUrl = esc_url_raw(trim((string) ($input['api_url'] ?? '')));

        if ($apiUrl === '' || !wp_http_validate_url($apiUrl)) {
            $errors['api_url'] = __('A valid API URL is required.', 'cloud-vm-manager');
        }

        $hostUrl = trim((string) ($input['host_url'] ?? ''));
        $hostUrl = $hostUrl === '' ? '' : esc_url_raw($hostUrl);

        if ($hostUrl !== '' && !wp_http_validate_url($hostUrl)) {
            $errors['host_url'] = __('The panel URL is not a valid URL.', 'cloud-vm-manager');
        }

        $email = sanitize_email((string) ($input['email'] ?? ''));

        if ($email === '' || !is_email($email)) {
            $errors['email'] = __('A valid account email is required.', 'cloud-vm-manager');
        }

        $password = (string) ($input['password'] ?? '');

        if ($isCreate && $password === '') {
            $errors['password'] = __('A password is required.', 'cloud-vm-manager');
        }

        if ($errors !== []) {
            throw new ValidationException(
                __('The provider could not be saved. Please review the highlighted fields.', 'cloud-vm-manager'),
                $errors
            );
        }

        $timeout = (int) ($input['timeout'] ?? 30);
        $timeout = max(self::MIN_TIMEOUT, min($timeout, self::MAX_TIMEOUT));

        $data = [
            'name' => $name,
            'api_url' => untrailingslashit($apiUrl),
            'host_url' => $hostUrl === '' ? '' : untrailingslashit($hostUrl),
            'email' => $email,
            'verify_ssl' => !empty($input['verify_ssl']),
            'timeout' => $timeout,
            'region' => sanitize_text_field((string) ($input['region'] ?? '')),
            'description' => sanitize_textarea_field((string) ($input['description'] ?? '')),
            'currency' => strtoupper(sanitize_text_field((string) ($input['currency'] ?? ''))),
            'is_active' => !empty($input['is_active']),
            'sort_order' => (int) ($input['sort_order'] ?? 0),
        ];

        if ($password !== '') {
            $data['password'] = $this->encryptor->encrypt($password);
        }

        $apiToken = trim((string) ($input['api_token'] ?? ''));

        if ($apiToken !== '') {
            $data['api_token'] = $this->encryptor->encrypt($apiToken);
        }

        return $data;
    }

    /**
     * Whether the change invalidates the stored token.
     *
     * @param array<string, mixed> $data
     */
    private function credentialsChanged(Provider $existing, array $data): bool
    {
        if (isset($data['password']) && $data['password'] !== '') {
            return true;
        }

        if (isset($data['email']) && $data['email'] !== $existing->getEmail()) {
            return true;
        }

        return isset($data['api_url']) && $data['api_url'] !== $existing->getApiUrl();
    }
}
