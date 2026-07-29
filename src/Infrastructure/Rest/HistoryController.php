<?php declare(strict_types=1);

namespace WPM\Infrastructure\Rest;

use WPM\Application\Service\RollbackService;
use WPM\Domain\Contract\ChangeLogRepositoryInterface;
use WPM\Domain\Exception\DomainException;

defined('ABSPATH') || exit;

/**
 * REST Controller for product change log history and rollback execution (Module 08 / Module 06).
 */
final class HistoryController extends \WP_REST_Controller
{
    protected $namespace = 'woo-ops/v1';

    public function __construct(
        private readonly ChangeLogRepositoryInterface $changeLogRepo,
        private readonly RollbackService $rollbackService
    ) {
    }

    public function register_routes(): void
    {
        if (!function_exists('register_rest_route')) {
            return;
        }

        register_rest_route($this->namespace, '/products/(?P<id>\d+)/history', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'getHistory'],
                'permission_callback' => [$this, 'checkPermission'],
                'args' => [
                    'id' => [
                        'required' => true,
                        'sanitize_callback' => 'absint',
                    ],
                    'limit' => [
                        'default' => 5,
                        'sanitize_callback' => fn($val) => min(50, max(1, absint($val))),
                    ],
                ],
            ],
        ]);

        register_rest_route($this->namespace, '/products/(?P<id>\d+)/history/(?P<change_id>\d+)/rollback', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'rollback'],
                'permission_callback' => [$this, 'checkPermission'],
                'args' => [
                    'id' => [
                        'required' => true,
                        'sanitize_callback' => 'absint',
                    ],
                    'change_id' => [
                        'required' => true,
                        'sanitize_callback' => 'absint',
                    ],
                ],
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

    public function getHistory(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $limit = (int) ($request->get_param('limit') ?? 5);

        $records = $this->changeLogRepo->getByProduct($id, $limit);
        $data = [];
        foreach ($records as $rec) {
            $data[] = $rec->toArray();
        }

        return rest_ensure_response([
            'data' => $data,
            'meta' => ['total' => count($data)],
        ]);
    }

    public function rollback(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $changeId = (int) $request->get_param('change_id');
        $userId = (int) get_current_user_id();
        if ($userId <= 0) {
            return new \WP_Error('unauthorized', 'Valid user identity required.', ['status' => 401]);
        }

        try {
            $result = $this->rollbackService->rollback($changeId, $userId);
            return rest_ensure_response(['data' => $result]);
        } catch (DomainException $e) {
            return new \WP_Error('business_rule_violation', $e->getMessage(), ['status' => 422]);
        }
    }
}
