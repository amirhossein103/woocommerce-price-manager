<?php declare(strict_types=1);

defined('ABSPATH') || exit;

return new class {
    public function up(\wpdb $wpdb): void
    {
        $table = $wpdb->prefix . 'wpm_change_log';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            product_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            field varchar(50) NOT NULL,
            old_value text NULL,
            new_value text NOT NULL,
            operation_type varchar(20) NOT NULL DEFAULT 'manual_edit',
            bulk_operation_id bigint(20) unsigned NULL,
            rolled_back_from_id bigint(20) unsigned NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_product_id (product_id),
            KEY idx_user_id (user_id),
            KEY idx_created_at (created_at),
            KEY idx_bulk_operation (bulk_operation_id),
            KEY idx_product_created (product_id, created_at)
        ) {$charset};";

        if (file_exists(ABSPATH . 'wp-admin/includes/upgrade.php')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        if (function_exists('dbDelta')) {
            dbDelta($sql);
        }

        // Ensure table exists even if dbDelta silently failed
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
            $wpdb->query($sql);
        }

        // Enforce the 5-change retention limit per product on migration run
        $productIds = $wpdb->get_col("SELECT DISTINCT product_id FROM {$table}");
        if (!empty($productIds)) {
            foreach ($productIds as $pid) {
                $pid = (int) $pid;
                $oldIds = $wpdb->get_col($wpdb->prepare(
                    "SELECT id FROM {$table} WHERE product_id = %d ORDER BY id DESC LIMIT 5, 999999",
                    $pid
                ));
                if (!empty($oldIds)) {
                    $idsList = implode(',', array_map('intval', $oldIds));
                    $wpdb->query("DELETE FROM {$table} WHERE id IN ({$idsList})");
                }
            }
        }
    }
};
