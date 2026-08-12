<?php declare(strict_types=1);
/**
 * Plugin Name: WooCommerce Price Manager
 * Plugin URI: https://rtl-theme.com/
 * Description: High-performance WooCommerce operations plugin for store managers and pricing professionals.
 * Version: 1.0.0
 * Requires PHP: 8.0
 * Requires at least: 6.3
 * WC requires at least: 8.0
 * Author: Amirhossein Lalehei
 * Author URI: https://github.com/amirhossein103
 * License: GPL-2.0-or-later
 * Text Domain: woo-price-manager
 * Domain Path: /languages
 */

defined('ABSPATH') || exit;

if (version_compare(PHP_VERSION, '8.0.0', '<')) {
    if (function_exists('add_action')) {
        add_action('admin_notices', static function (): void {
            if (function_exists('esc_html__')) {
                echo '<div class="notice notice-error"><p>' .
                    esc_html__('WooCommerce Price Manager requires PHP 8.0 or higher.', 'woo-price-manager') .
                    '</p></div>';
            }
        });
    }
    return;
}

if (!defined('WPM_VERSION')) {
    define('WPM_VERSION', '1.0.0');
}
if (!defined('WPM_FILE')) {
    define('WPM_FILE', __FILE__);
}
if (!defined('WPM_PATH')) {
    define('WPM_PATH', plugin_dir_path(__FILE__));
}
if (!defined('WPM_URL')) {
    define('WPM_URL', plugin_dir_url(__FILE__));
}

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
} elseif (file_exists(__DIR__ . '/src/Autoloader.php')) {
    require_once __DIR__ . '/src/Autoloader.php';
    (new \WPM\Autoloader('WPM\\', __DIR__ . '/src/'))->register();
}

if (function_exists('register_activation_hook')) {
    register_activation_hook(__FILE__, [\WPM\Plugin::class, 'activate']);
}
if (function_exists('register_deactivation_hook')) {
    register_deactivation_hook(__FILE__, [\WPM\Plugin::class, 'deactivate']);
}
\WPM\Plugin::instance()->boot();

if (function_exists('add_filter')) {
    add_filter('plugin_action_links_' . plugin_basename(__FILE__), static function (array $links): array {
        if (function_exists('admin_url') && function_exists('esc_html__') && function_exists('esc_url')) {
            $manage_link = '<a href="' . esc_url(admin_url('admin.php?page=woo-price-manager')) . '">' . esc_html__('Manage Price', 'woo-price-manager') . '</a>';
            array_unshift($links, $manage_link);
        }
        return $links;
    });
}
