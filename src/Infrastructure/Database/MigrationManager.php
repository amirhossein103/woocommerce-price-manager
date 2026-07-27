<?php declare(strict_types=1);

namespace WPM\Infrastructure\Database;

defined('ABSPATH') || exit;

/**
 * Executes versioned schema migrations using dbDelta() (Rule R-04).
 */
final class MigrationManager
{
    public const VERSION_OPTION = 'wpm_db_version';

    public function __construct(private readonly \wpdb $wpdb)
    {
    }

    public function getCurrentVersion(): int
    {
        if (!function_exists('get_option')) {
            return 0;
        }

        return (int) get_option(self::VERSION_OPTION, 0);
    }

    public function runPending(): void
    {
        $currentVersion = $this->getCurrentVersion();
        $migrationFiles = glob(__DIR__ . '/migrations/*.php');
        if (!is_array($migrationFiles)) {
            return;
        }
        sort($migrationFiles);

        foreach ($migrationFiles as $file) {
            $filename = basename($file);
            $version = (int) $filename;

            $tableMissing = false;
            if ($version === 1 && isset($this->wpdb)) {
                $table = $this->wpdb->prefix . 'wpm_change_log';
                $tableMissing = ($this->wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table);
            }

            if ($version > $currentVersion || $tableMissing) {
                $migration = require $file;
                if (is_object($migration) && method_exists($migration, 'up')) {
                    $migration->up($this->wpdb);
                    if (function_exists('update_option')) {
                        update_option(self::VERSION_OPTION, $version);
                    }
                    $currentVersion = $version;
                }
            }
        }
    }
}
