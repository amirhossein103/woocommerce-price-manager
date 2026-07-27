<?php declare(strict_types=1);

namespace WPM;

defined('ABSPATH') || exit;

/**
 * PSR-4 Autoloader for WPM namespace when Composer vendor/autoload.php is not present.
 */
final class Autoloader
{
    private string $prefix;
    private string $baseDir;

    public function __construct(string $prefix = 'WPM\\', string $baseDir = __DIR__ . '/')
    {
        $this->prefix = $prefix;
        $this->baseDir = rtrim($baseDir, '/\\') . '/';
    }

    public function register(): void
    {
        spl_autoload_register([$this, 'loadClass']);
    }

    public function loadClass(string $class): void
    {
        $len = strlen($this->prefix);
        if (strncmp($this->prefix, $class, $len) !== 0) {
            return;
        }

        $relativeClass = substr($class, $len);
        $file = $this->baseDir . str_replace('\\', '/', $relativeClass) . '.php';

        if (file_exists($file)) {
            require $file;
        }
    }
}
