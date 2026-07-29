<?php declare(strict_types=1);

namespace WPM\Infrastructure\Rest;

use WPM\Application\Service\ProductListingService;
use WPM\Application\Service\ProductSaveService;
use WPM\Domain\Exception\DomainException;

defined('ABSPATH') || exit;

/**
 * REST Controller for product listings, single product retrieval/update, variations, and categories (Module 08).
 */
final class ProductController extends \WP_REST_Controller
{
    protected $namespace = 'woo-ops/v1';

    public function __construct(
        private readonly ProductListingService $listingService,
        private readonly ProductSaveService $saveService
    ) {
    }

    public function register_routes(): void
    {
        if (!function_exists('register_rest_route')) {
            return;
        }

        register_rest_route($this->namespace, '/products', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'listProducts'],
                'permission_callback' => [$this, 'checkPermission'],
                'args' => $this->getListArgs(),
            ],
        ]);

        register_rest_route($this->namespace, '/products/(?P<id>\d+)', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'getProduct'],
                'permission_callback' => [$this, 'checkPermission'],
                'args' => [
                    'id' => [
                        'required' => true,
                        'sanitize_callback' => 'absint',
                    ],
                ],
            ],
            [
                'methods' => 'PUT',
                'callback' => [$this, 'updateProduct'],
                'permission_callback' => [$this, 'checkPermission'],
                'args' => $this->getUpdateArgs(),
            ],
        ]);

        register_rest_route($this->namespace, '/products/(?P<id>\d+)/variations', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'getVariations'],
                'permission_callback' => [$this, 'checkPermission'],
                'args' => [
                    'id' => [
                        'required' => true,
                        'sanitize_callback' => 'absint',
                    ],
                ],
            ],
        ]);

        register_rest_route($this->namespace, '/products/(?P<id>\d+)/default-variation', [
            [
                'methods' => 'PUT',
                'callback' => [$this, 'setDefaultVariation'],
                'permission_callback' => [$this, 'checkPermission'],
                'args' => [
                    'id' => [
                        'required' => true,
                        'sanitize_callback' => 'absint',
                    ],
                    'variation_id' => [
                        'required' => true,
                        'sanitize_callback' => 'absint',
                    ],
                ],
            ],
        ]);

        register_rest_route($this->namespace, '/categories', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'listCategories'],
                'permission_callback' => [$this, 'checkPermission'],
                'args' => [],
            ],
        ]);
    }

    public function checkPermission(\WP_REST_Request $request): bool|\WP_Error
    {
        if (function_exists('is_user_logged_in') && !is_user_logged_in()) {
            return new \WP_Error('auth_failed', 'User not authenticated.', ['status' => 401]);
        }
        if (function_exists('current_user_can') && !current_user_can('manage_product_pricing')) {
            return new \WP_Error('forbidden', 'User lacks manage_product_pricing capability.', ['status' => 403]);
        }
        
        $method = $request->get_method();
        if ($method !== 'GET' && $method !== 'OPTIONS') {
            $nonce = $request->get_header('x_wp_nonce');
            if (!$nonce || !function_exists('wp_verify_nonce') || !wp_verify_nonce($nonce, 'wp_rest')) {
                return new \WP_Error('invalid_nonce', 'Nonce verification failed.', ['status' => 403]);
            }
        }
        
        return true;
    }

    public function listProducts(\WP_REST_Request $request): \WP_REST_Response
    {
        $cleanParam = static function ($val) {
            return ($val === null || $val === 'null' || $val === 'undefined' || $val === '' || $val === 'all') ? null : $val;
        };

        $filters = [
            'page' => (int) ($request->get_param('page') ?? 1),
            'limit' => (int) ($request->get_param('per_page') ?? 20),
            'category' => $cleanParam($request->get_param('category')),
            'stock_status' => $cleanParam($request->get_param('stock_status')),
            'status' => $cleanParam($request->get_param('status')),
            'search' => $cleanParam($request->get_param('search')),
            'orderby' => (string) ($request->get_param('orderby') ?? 'name'),
            'order' => (string) ($request->get_param('order') ?? 'asc'),
        ];

        $result = $this->listingService->listProducts($filters);
        return rest_ensure_response($result);
    }

    public function getProduct(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $id = (int) $request->get_param('id');
        $product = $this->listingService->getProduct($id);

        if ($product === null) {
            return new \WP_Error('not_found', 'Product not found.', ['status' => 404]);
        }

        return rest_ensure_response(['data' => $product]);
    }

    public function updateProduct(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $id = (int) $request->get_param('id');
        $userId = (int) get_current_user_id();
        if ($userId <= 0) {
            return new \WP_Error('unauthorized', 'Valid user identity required.', ['status' => 401]);
        }

        $params = $request->get_params();
        $changes = [];
        if (array_key_exists('regular_price', $params)) {
            $changes['regular_price'] = $params['regular_price'];
        }
        if (array_key_exists('sale_price', $params)) {
            $changes['sale_price'] = $params['sale_price'];
        }
        if (array_key_exists('date_on_sale_from', $params)) {
            $changes['date_on_sale_from'] = $params['date_on_sale_from'];
        }
        if (array_key_exists('date_on_sale_to', $params)) {
            $changes['date_on_sale_to'] = $params['date_on_sale_to'];
        }
        if (array_key_exists('stock_quantity', $params)) {
            $changes['stock_quantity'] = $params['stock_quantity'];
        }
        if (array_key_exists('manage_stock', $params)) {
            $changes['manage_stock'] = (bool) $params['manage_stock'];
        }
        if (array_key_exists('is_managed', $params)) {
            $changes['manage_stock'] = (bool) $params['is_managed'];
            $changes['is_managed'] = (bool) $params['is_managed'];
        }
        if (array_key_exists('stock_status', $params)) {
            $changes['stock_status'] = (string) $params['stock_status'];
        }
        if (array_key_exists('status', $params)) {
            $changes['stock_status'] = (string) $params['status'];
            $changes['status'] = (string) $params['status'];
        }

        try {
            $updated = $this->saveService->saveProduct($id, $changes, $userId);
            return rest_ensure_response(['data' => $updated]);
        } catch (DomainException $e) {
            return new \WP_Error('business_rule_violation', $e->getMessage(), ['status' => 422, 'field' => 'stock_quantity']);
        }
    }

    public function getVariations(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $id = (int) $request->get_param('id');
        $product = $this->listingService->getProduct($id);

        if ($product === null || ($product['type'] ?? '') !== 'variable') {
            return new \WP_Error('not_found', 'Product not found or is not a variable product.', ['status' => 404]);
        }

        $variations = $this->listingService->getVariations($id);
        return rest_ensure_response(['data' => $variations]);
    }

    public function setDefaultVariation(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $id = (int) $request->get_param('id');
        $variationId = (int) $request->get_param('variation_id');
        $userId = get_current_user_id();

        try {
            $this->saveService->setDefaultVariation($id, $variationId, $userId);
            return rest_ensure_response(['success' => true]);
        } catch (DomainException $e) {
            return new \WP_Error('business_rule_violation', $e->getMessage(), ['status' => 422]);
        }
    }

    public function listCategories(\WP_REST_Request $request): \WP_REST_Response
    {
        $categories = $this->listingService->listCategories();
        return rest_ensure_response(['data' => $categories]);
    }

    private function getListArgs(): array
    {
        return [
            'page' => [
                'default' => 1,
                'sanitize_callback' => 'absint',
            ],
            'per_page' => [
                'default' => 20,
                'sanitize_callback' => 'absint',
            ],
            'category' => [
                'default' => null,
                'sanitize_callback' => 'absint',
            ],
            'stock_status' => [
                'default' => null,
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'status' => [
                'default' => null,
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'search' => [
                'default' => null,
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'orderby' => [
                'default' => 'name',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'order' => [
                'default' => 'asc',
                'sanitize_callback' => 'sanitize_text_field',
            ],
        ];
    }

    private function getUpdateArgs(): array
    {
        return [
            'id' => [
                'required' => true,
                'sanitize_callback' => 'absint',
            ],
            'regular_price' => [
                'required' => false,
                'sanitize_callback' => fn($val) => $val !== null && $val !== '' ? floatval($val) : null,
            ],
            'sale_price' => [
                'required' => false,
                'sanitize_callback' => fn($val) => $val !== null && $val !== '' ? floatval($val) : null,
            ],
            'stock_quantity' => [
                'required' => false,
                'sanitize_callback' => fn($val) => $val !== null && $val !== '' ? absint($val) : null,
            ],
            'manage_stock' => [
                'required' => false,
                'sanitize_callback' => 'rest_sanitize_boolean',
            ],
            'is_managed' => [
                'required' => false,
                'sanitize_callback' => 'rest_sanitize_boolean',
            ],
            'stock_status' => [
                'required' => false,
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'status' => [
                'required' => false,
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'date_on_sale_from' => [
                'required' => false,
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'date_on_sale_to' => [
                'required' => false,
                'sanitize_callback' => 'sanitize_text_field',
            ],
        ];
    }
}
