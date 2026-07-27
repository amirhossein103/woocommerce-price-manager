<?php declare(strict_types=1);

namespace WPM\Domain\Stock;

use WPM\Domain\ValueObject\BulkOperation;
use WPM\Domain\ValueObject\StockSnapshot;
use WPM\Domain\Exception\InvalidStockException;

defined('ABSPATH') || exit;

/**
 * Pure Domain Service responsible for inventory calculations and invariant validations (Module 04).
 */
final class StockManager
{
    /**
     * Create a validated StockSnapshot.
     *
     * @throws InvalidStockException When quantity is negative or missing for managed stock.
     */
    public function createSnapshot(int $productId, bool $isManaged, ?int $quantity = null, ?string $status = null): StockSnapshot
    {
        return new StockSnapshot($productId, $isManaged, $quantity, $status);
    }

    /**
     * Calculate the new inventory state resulting from a bulk stock operation.
     * Returns an associative array representing the new StockSnapshot or validation error.
     *
     * @return array{isValid: bool, snapshot: ?StockSnapshot, violation: ?string}
     */
    public function calculateBulkOperation(StockSnapshot $current, BulkOperation $operation): array
    {
        if (!$operation->isStockOperation()) {
            return [
                'isValid' => false,
                'snapshot' => null,
                'violation' => 'not_a_stock_operation',
            ];
        }

        if (!$current->isManaged && $operation->operationType !== BulkOperation::TYPE_STOCK_CLEAR && $operation->operationType !== BulkOperation::TYPE_STOCK_SET) {
            return [
                'isValid' => false,
                'snapshot' => null,
                'violation' => 'unmanaged_stock',
            ];
        }

        $type = $operation->operationType;
        $param = (int) round($operation->parameter);
        $currentQty = $current->quantity ?? 0;

        try {
            switch ($type) {
                case BulkOperation::TYPE_STOCK_SET:
                case 'stock_set':
                    $newSnapshot = new StockSnapshot($current->productId, true, $param);
                    break;
                case BulkOperation::TYPE_STOCK_INCREASE:
                case 'stock_increase':
                    $newQty = $currentQty + $param;
                    if ($newQty < 0) {
                        return [
                            'isValid' => false,
                            'snapshot' => null,
                            'violation' => 'negative_stock_result',
                        ];
                    }
                    $newSnapshot = new StockSnapshot($current->productId, true, $newQty);
                    break;
                case BulkOperation::TYPE_STOCK_CLEAR:
                case 'stock_clear':
                    $newSnapshot = new StockSnapshot($current->productId, false, null, 'instock');
                    break;
                default:
                    return [
                        'isValid' => false,
                        'snapshot' => null,
                        'violation' => 'unknown_stock_operation',
                    ];
            }

            return [
                'isValid' => true,
                'snapshot' => $newSnapshot,
                'violation' => null,
            ];
        } catch (InvalidStockException $e) {
            return [
                'isValid' => false,
                'snapshot' => null,
                'violation' => $e->getMessage(),
            ];
        }
    }
}
