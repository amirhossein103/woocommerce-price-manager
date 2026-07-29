<?php declare(strict_types=1);

namespace WPM;

use WPM\Infrastructure\DI\Container;
use WPM\Infrastructure\AccessControl\AccessControlService;
use WPM\Infrastructure\Admin\AdminMenu;
use WPM\Domain\Contract\ProductRepositoryInterface;
use WPM\Domain\Contract\ChangeLogRepositoryInterface;
use WPM\Domain\Contract\CategoryRepositoryInterface;
use WPM\Domain\Pricing\PricingEngine;
use WPM\Domain\Stock\StockManager;
use WPM\Application\Service\ProductListingService;
use WPM\Application\Service\ProductSaveService;
use WPM\Application\Service\BulkOperationService;
use WPM\Application\Service\RollbackService;
use WPM\Infrastructure\Adapter\WooCommerceProductAdapter;
use WPM\Infrastructure\Repository\ChangeLogRepository;
use WPM\Infrastructure\Repository\CategoryRepository;
use WPM\Infrastructure\Rest\ProductController;
use WPM\Infrastructure\Rest\HistoryController;
use WPM\Infrastructure\Rest\BulkOperationController;
use WPM\Infrastructure\Database\MigrationManager;

defined('ABSPATH') || exit;

/**
 * Main orchestrator and application bootstrap for WooCommerce Price Manager.
 */
final class Plugin
{
    private static ?self $instance = null;
    private ?Container $container = null;
    private bool $booted = false;

    /**
     * Singleton instance accessor (enforces single container instance).
     */
    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
    }

    /**
     * Retrieve the Dependency Injection container.
     */
    public function container(): Container
    {
        if ($this->container === null) {
            $this->container = new Container();
            $this->configureContainer($this->container);
        }
        return $this->container;
    }

    /**
     * Configure service bindings in the DI container.
     */
    private function configureContainer(Container $c): void
    {
        if (isset($GLOBALS['wpdb']) && $GLOBALS['wpdb'] instanceof \wpdb) {
            $c->instance(\wpdb::class, $GLOBALS['wpdb']);
        }

        $c->bind(AccessControlService::class, fn() => new AccessControlService(), true);
        $c->bind(AdminMenu::class, fn(Container $cnt) => new AdminMenu($cnt->get(AccessControlService::class)), true);
        $c->bind(MigrationManager::class, fn(Container $cnt) => new MigrationManager($cnt->get(\wpdb::class)), true);

        // Domain & Infrastructure Repositories
        $c->bind(ProductRepositoryInterface::class, fn() => new WooCommerceProductAdapter(), true);
        $c->bind(ChangeLogRepositoryInterface::class, fn(Container $cnt) => new ChangeLogRepository($cnt->get(\wpdb::class)), true);
        $c->bind(CategoryRepositoryInterface::class, fn() => new CategoryRepository(), true);

        // Domain Services
        $c->bind(PricingEngine::class, fn() => new PricingEngine(), true);
        $c->bind(StockManager::class, fn() => new StockManager(), true);

        // Application Services
        $c->bind(ProductListingService::class, fn(Container $cnt) => new ProductListingService($cnt->get(ProductRepositoryInterface::class), $cnt->get(CategoryRepositoryInterface::class)), true);
        $c->bind(ProductSaveService::class, fn(Container $cnt) => new ProductSaveService($cnt->get(ProductRepositoryInterface::class), $cnt->get(PricingEngine::class), $cnt->get(StockManager::class), $cnt->get(ChangeLogRepositoryInterface::class)), true);
        $c->bind(BulkOperationService::class, fn(Container $cnt) => new BulkOperationService($cnt->get(ProductRepositoryInterface::class), $cnt->get(PricingEngine::class), $cnt->get(StockManager::class), $cnt->get(ChangeLogRepositoryInterface::class)), true);
        $c->bind(RollbackService::class, fn(Container $cnt) => new RollbackService($cnt->get(ChangeLogRepositoryInterface::class), $cnt->get(ProductRepositoryInterface::class), $cnt->get(PricingEngine::class), $cnt->get(StockManager::class)), true);

        // REST Controllers
        $c->bind(ProductController::class, fn(Container $cnt) => new ProductController($cnt->get(ProductListingService::class), $cnt->get(ProductSaveService::class)), true);
        $c->bind(HistoryController::class, fn(Container $cnt) => new HistoryController($cnt->get(ChangeLogRepositoryInterface::class), $cnt->get(RollbackService::class)), true);
        $c->bind(BulkOperationController::class, fn(Container $cnt) => new BulkOperationController($cnt->get(BulkOperationService::class)), true);
    }

    /**
     * Boot plugin services, register hooks, and load internationalization.
     */
    public function boot(): void
    {
        if ($this->booted) {
            return;
        }
        $this->booted = true;

        if (function_exists('add_action')) {
            add_action('plugins_loaded', [$this, 'initPluginsLoaded']);
            add_action('init', [$this, 'initWordPress']);
            add_action('rest_api_init', [$this, 'initRestApi']);
        }
    }

    /**
     * Hook: plugins_loaded. Check WooCommerce dependency and load translations.
     */
    public function initPluginsLoaded(): void
    {
        if (!class_exists('WooCommerce')) {
            if (function_exists('add_action')) {
                add_action('admin_notices', [$this, 'renderWooCommerceMissingNotice']);
            }
            return;
        }

        if (function_exists('load_plugin_textdomain') && function_exists('plugin_basename')) {
            load_plugin_textdomain(
                'woo-price-manager',
                false,
                dirname(plugin_basename(defined('WPM_FILE') ? WPM_FILE : __FILE__)) . '/languages/'
            );
        }

        // Register Admin Menu
        $adminMenu = $this->container()->get(AccessControlService::class) ? $this->container()->get(AdminMenu::class) : null;
        if ($adminMenu instanceof AdminMenu) {
            $adminMenu->register();
        }

        // Run schema migrations
        $migrationManager = $this->container()->get(MigrationManager::class);
        if ($migrationManager instanceof MigrationManager) {
            $migrationManager->runPending();
        }
    }

    /**
     * Hook: init. Register REST controllers and application hooks.
     */
    public function initWordPress(): void
    {
        if (!class_exists('WooCommerce')) {
            return;
        }
    }

    /**
     * Hook: rest_api_init. Register REST API endpoint routes.
     */
    public function initRestApi(): void
    {
        if (!class_exists('WooCommerce')) {
            return;
        }

        $controllers = [
            ProductController::class,
            HistoryController::class,
            BulkOperationController::class,
        ];

        foreach ($controllers as $controllerClass) {
            if ($this->container()->has($controllerClass)) {
                $controller = $this->container()->get($controllerClass);
                if ($controller && method_exists($controller, 'register_routes')) {
                    $controller->register_routes();
                }
            }
        }
    }

    /**
     * Admin notice when WooCommerce is missing or inactive.
     */
    public function renderWooCommerceMissingNotice(): void
    {
        if (!function_exists('esc_html__')) {
            return;
        }
        ?>
        <div class="notice notice-error">
            <p><?php echo esc_html__('WooCommerce Price Manager requires WooCommerce to be installed and active.', 'woo-price-manager'); ?></p>
        </div>
        <?php
    }

    /**
     * Execute installation procedures upon plugin activation.
     */
    public static function activate(): void
    {
        $accessControl = self::instance()->container()->get(AccessControlService::class);
        if ($accessControl instanceof AccessControlService) {
            $accessControl->install();
        }

        $migrationManager = self::instance()->container()->get(MigrationManager::class);
        if ($migrationManager instanceof MigrationManager) {
            $migrationManager->runPending();
        }
    }

    /**
     * Execute uninstallation/cleanup procedures upon plugin deactivation.
     */
    public static function deactivate(): void
    {
        // On deactivation, we do not destroy data (history is preserved by design)
    }
}
