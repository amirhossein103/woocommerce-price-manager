<?php declare(strict_types=1);

namespace WPM\Infrastructure\Rest;

use WPM\Application\Service\BulkOperationService;
use WPM\Domain\ValueObject\BulkOperation;
use WPM\Domain\Exception\DomainException;

defined('ABSPATH') || exit;

/**
 * REST Controller for bulk operation previews and batch executions (Module 08 / Module 05).
 */
final class BulkOperationController extends \WP_REST_Controller
{
    protected $namespace = 'woo-ops/v1';

    public function __construct(private readonly BulkOperationService $bulkService)
    {
    }

    public function register_routes(): void
    {
        if (!function_exists('register_rest_route')) {
            return;
        }

        register_rest_route($this->namespace, '/products/bulk/preview', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'preview'],
                'permission_callback' => [$this, 'checkPermission'],
                'args' => $this->getBulkArgs(),
            ],
        ]);

        register_rest_route($this->namespace, '/products/bulk/execute', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'execute'],
                'permission_callback' => [$this, 'checkPermission'],
                'args' => $this->getExecuteArgs(),
            ],
        ]);
    }

    public function checkPermission(): bool|\WP_Error
    {
        if (function_exists('is_user_logged_in') && !is_user_logged_in()) {
            return new \WP_Error('auth_failed', 'User not authenticated.', ['status' => 401]);
        }
        if (function_exists('current_user_can') && !current_user_can('manage_product_pricing')) {
            return new \WP_Error('forbidden', 'User lacks manage_product_pricing capability.', ['status' => 403]);
        }
        return true;
    }

    public function preview(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        try {
            $operation = $this->createBulkOperationFromRequest($request);
            $result = $this->bulkService->preview($operation);
            return rest_ensure_response($result);
        } catch (DomainException $e) {
            return new \WP_Error('validation_error', $e->getMessage(), ['status' => 400]);
        }
    }

    public function execute(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        try {
            $operation = $this->createBulkOperationFromRequest($request);
            $userId = function_exists('get_current_user_id') ? (int) get_current_user_id() : 1;
            $alsoUpdateSale = (bool) ($request->get_param('also_update_sale') ?? false);
            $excludedIds = array_map('absint', (array) ($request->get_param('excluded_ids') ?? []));

            $result = $this->bulkService->execute($operation, $userId, $alsoUpdateSale, $excludedIds);
            return rest_ensure_response(['data' => $result]);
        } catch (DomainException $e) {
            return new \WP_Error('validation_error', $e->getMessage(), ['status' => 400]);
        }
    }

    private function createBulkOperationFromRequest(\WP_REST_Request $request): BulkOperation
    {
        $type = (string) $request->get_param('operation_type');
        if ($type === 'stock_set') {
            $type = BulkOperation::TYPE_STOCK_SET;
        } elseif ($type === 'stock_increase') {
            $type = BulkOperation::TYPE_STOCK_INCREASE;
        } elseif ($type === 'stock_clear') {
            $type = BulkOperation::TYPE_STOCK_CLEAR;
        }

        $field = (string) ($request->get_param('target_field') ?? 'regular_price');
        $param = (float) ($request->get_param('parameter') ?? 0);
        $ids = array_map('absint', (array) ($request->get_param('product_ids') ?? []));

        return new BulkOperation($type, $field, $param, $ids);
    }

    private function getBulkArgs(): array
    {
        return [
            'operation_type' => [
                'required' => true,
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'target_field' => [
                'default' => 'regular_price',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'parameter' => [
                'default' => 0,
                'sanitize_callback' => fn($val) => floatval($val),
            ],
            'product_ids' => [
                'required' => true,
                'sanitize_callback' => fn($ids) => array_map('absint', (array) $ids),
            ],
        ];
    }

    private function getExecuteArgs(): array
    {
        $args = $this->getBulkArgs();
        $args['also_update_sale'] = [
            'default' => false,
            'sanitize_callback' => 'rest_sanitize_boolean',
        ];
        $args['excluded_ids'] = [
            'default' => [],
            'sanitize_callback' => fn($ids) => array_map('absint', (array) $ids),
        ];
        return $args;
    }
}
