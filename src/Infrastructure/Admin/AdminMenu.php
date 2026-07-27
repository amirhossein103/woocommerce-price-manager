<?php declare(strict_types=1);

namespace WPM\Infrastructure\Admin;

use WPM\Infrastructure\AccessControl\AccessControlService;

defined('ABSPATH') || exit;

/**
 * Registers the WooCommerce Price Manager admin interface inside WordPress wp-admin.
 */
final class AdminMenu
{
    public const MENU_SLUG = 'woo-price-manager';
    public const HOOK_SUFFIX_PREFIX = 'woocommerce_page_woo-price-manager';

    private string $pageHook = '';

    public function __construct(private readonly AccessControlService $accessControl)
    {
    }

    /**
     * Register WordPress admin menu hooks.
     */
    public function register(): void
    {
        if (!function_exists('add_action')) {
            return;
        }

        add_action('admin_menu', [$this, 'addMenuPage'], 50);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    /**
     * Add submenu page under WooCommerce menu.
     */
    public function addMenuPage(): void
    {
        if (!function_exists('add_submenu_page')) {
            return;
        }

        $hook = add_submenu_page(
            'woocommerce',
            __('Price Manager', 'woo-price-manager'),
            __('Price Manager', 'woo-price-manager'),
            AccessControlService::CAPABILITY,
            self::MENU_SLUG,
            [$this, 'renderPage']
        );

        if (is_string($hook)) {
            $this->pageHook = $hook;
        }
    }

    /**
     * Enqueue JS and CSS assets strictly on our admin page only (R-07, R-08, R-09).
     *
     * @param string $hookSuffix The current admin page hook.
     */
    public function enqueueAssets(string $hookSuffix): void
    {
        if ($hookSuffix !== $this->pageHook && $hookSuffix !== self::HOOK_SUFFIX_PREFIX) {
            return;
        }

        if (!function_exists('wp_enqueue_style') || !function_exists('wp_enqueue_script') || !function_exists('wp_localize_script')) {
            return;
        }

        $pluginUrl = defined('WPM_URL') ? WPM_URL : plugin_dir_url(dirname(__DIR__, 2) . '/woocommerce-price-manager.php');
        $version = defined('WPM_VERSION') ? WPM_VERSION : '1.0.0';

        $assetPath = dirname(__DIR__, 3) . '/assets/build/index.asset.php';
        $asset = file_exists($assetPath) ? require $assetPath : ['dependencies' => ['wp-element', 'wp-components', 'wp-data', 'wp-api-fetch', 'wp-i18n'], 'version' => $version];

        wp_enqueue_style(
            'wpm-admin-style',
            $pluginUrl . (function_exists('is_rtl') && is_rtl() ? 'assets/build/style-rtl.css' : 'assets/build/style.css'),
            [],
            $asset['version'] ?? $version
        );

        wp_enqueue_script(
            'wpm-admin-script',
            $pluginUrl . 'assets/build/index.js',
            $asset['dependencies'] ?? ['wp-element', 'wp-components', 'wp-data', 'wp-api-fetch', 'wp-i18n'],
            $asset['version'] ?? $version,
            true
        );

        if (function_exists('wp_set_script_translations')) {
            wp_set_script_translations(
                'wpm-admin-script',
                'woo-price-manager',
                dirname(__DIR__, 3) . '/languages/'
            );
        }

        if (function_exists('rest_url') && function_exists('wp_create_nonce') && function_exists('esc_url_raw')) {
            wp_localize_script(
                'wpm-admin-script',
                'wpmData',
                [
                    'root' => esc_url_raw(rest_url('woo-ops/v1/')),
                    'nonce' => wp_create_nonce('wp_rest'),
                    'canManage' => $this->accessControl->canManagePricing(),
                    'version' => $version,
                    'currency' => [
                        'symbol' => html_entity_decode((string) get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8'),
                        'position' => get_option('woocommerce_currency_pos', 'left'),
                        'decimal_separator' => wc_get_price_decimal_separator(),
                        'thousand_separator' => wc_get_price_thousand_separator(),
                        'decimals' => (int) wc_get_price_decimals(),
                    ],
                ]
            );
        }
    }

    /**
     * Render the admin page HTML wrapper (container for React SPA).
     */
    public function renderPage(): void
    {
        if (!$this->accessControl->canManagePricing()) {
            if (function_exists('wp_die') && function_exists('esc_html__')) {
                wp_die(esc_html__('You do not have permission to access Price Manager.', 'woo-price-manager'));
            }
            return;
        }

        if (function_exists('esc_html__') && function_exists('esc_attr__')) {
            echo '<div class="wrap wpm-admin-wrap">';
            echo '<h1 class="wp-heading-inline">' . esc_html__('WooCommerce Price Manager', 'woo-price-manager') . '</h1>';
            echo '<hr class="wp-header-end">';
            echo '<div id="wpm-root" class="wpm-app-root" role="main" aria-label="' . esc_attr__('Price Manager Application', 'woo-price-manager') . '">';
            echo '<p class="wpm-loading">' . esc_html__('Loading Price Manager workspace...', 'woo-price-manager') . '</p>';
            echo '</div>';
            echo '</div>';
        }
    }
}
