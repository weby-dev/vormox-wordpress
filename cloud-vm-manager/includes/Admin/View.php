<?php

/**
 * Template renderer.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Admin;

defined('ABSPATH') || exit;

/**
 * Renders a template from the plugin template directory.
 *
 * Template names are restricted to a safe character set and resolved against
 * the plugin directory, so no caller can escape it. A child theme may override
 * any template by placing a file of the same relative path inside a
 * `cloud-vm-manager` directory.
 */
final class View
{
    /**
     * @var string
     */
    private $basePath;

    public function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, '/\\') . '/';
    }

    /**
     * Whether the template exists.
     */
    public function exists(string $template): bool
    {
        return $this->locate($template) !== '';
    }

    /**
     * Render a template.
     *
     * @param array<string, mixed> $data Variables exposed to the template.
     */
    public function render(string $template, array $data = []): void
    {
        $file = $this->locate($template);

        if ($file === '') {
            return;
        }

        /*
         * Templates only receive already validated data and escape every value
         * they print themselves.
         */
        // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
        extract($data, EXTR_SKIP);

        include $file;
    }

    /**
     * Render a template into a string.
     *
     * @param array<string, mixed> $data
     */
    public function capture(string $template, array $data = []): string
    {
        ob_start();
        $this->render($template, $data);

        return (string) ob_get_clean();
    }

    /**
     * Resolve a template name to a readable file, empty when not found.
     */
    private function locate(string $template): string
    {
        if (preg_match('/^[a-z0-9\-_]+(\/[a-z0-9\-_]+)*$/', $template) !== 1) {
            return '';
        }

        $relative = $template . '.php';

        $override = locate_template(['cloud-vm-manager/' . $relative]);

        if (is_string($override) && $override !== '' && is_readable($override)) {
            return $override;
        }

        $file = $this->basePath . $relative;

        return is_readable($file) ? $file : '';
    }
}
