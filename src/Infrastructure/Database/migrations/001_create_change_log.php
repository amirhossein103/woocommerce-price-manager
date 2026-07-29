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
        $sqlPrune = "DELETE t1 FROM {$table} t1
        JOIN (
            SELECT id, DENSE_RANK() OVER (PARTITION BY product_id ORDER BY id DESC) as rnk
            FROM {$table}
        ) t2 ON t1.id = t2.id
        WHERE t2.rnk > 5";
        $wpdb->query($sqlPrune);
    }
};
