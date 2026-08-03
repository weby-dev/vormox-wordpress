<?php

/**
 * PSR-4 autoloader.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager;

defined('ABSPATH') || defined('WP_UNINSTALL_PLUGIN') || exit;

/**
 * Minimal PSR-4 autoloader so the plugin runs without a Composer vendor directory.
 */
final class Autoloader
{
    /**
     * Namespace prefix handled by this autoloader, terminated by a separator.
     *
     * @var string
     */
    private $prefix;

    /**
     * Absolute base directory for the namespace prefix, terminated by a slash.
     *
     * @var string
     */
    private $baseDirectory;

    public function __construct(string $prefix, string $baseDirectory)
    {
        $this->prefix = rtrim($prefix, '\\') . '\\';
        $this->baseDirectory = rtrim($baseDirectory, '/\\') . '/';
    }

    /**
     * Create an autoloader and register it on the SPL stack.
     */
    public static function register(string $prefix, string $baseDirectory): self
    {
        $autoloader = new self($prefix, $baseDirectory);

        spl_autoload_register([$autoloader, 'autoload']);

        return $autoloader;
    }

    /**
     * Resolve a fully qualified class name to a file and load it.
     */
    public function autoload(string $class): void
    {
        if (strncmp($class, $this->prefix, strlen($this->prefix)) !== 0) {
            return;
        }

        $relativeClass = substr($class, strlen($this->prefix));
        $relativePath = str_replace('\\', '/', $relativeClass) . '.php';
        $file = $this->baseDirectory . $relativePath;

        if (is_readable($file)) {
            require_once $file;
        }
    }
}
