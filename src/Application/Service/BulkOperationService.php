<?php declare(strict_types=1);

namespace WPM\Application\Service;

use WPM\Domain\Contract\ProductRepositoryInterface;
use WPM\Domain\Contract\ChangeLogRepositoryInterface;
use WPM\Domain\Pricing\PricingEngine;
use WPM\Domain\Stock\StockManager;
use WPM\Domain\ValueObject\BulkOperation;
use WPM\Domain\Exception\DomainException;

defined('ABSPATH') || exit;

/**
 * Application Service orchestrating bulk pricing and stock previews and batch executions (Module 05 / NFR-R03).
 */
final class BulkOperationService
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepo,
        private readonly PricingEngine $pricingEngine,
        private readonly StockManager $stockManager,
        private readonly ChangeLogRepositoryInterface $changeLog
    ) {
    }

    /**
     * Generate a side-effect-free preview of a bulk operation.
     *
     * @return array{data: array, meta: array}
     */
    public function preview(BulkOperation $operation): array
    {
        if (!$operation->isPriceOperation() && !$operation->isStockOperation()) {
            throw new DomainException("Invalid operation type: {$operation->operationType}");
        }

        $ids = $operation->productIds;
        $previewData = [];
        $validCount = 0;
        $invalidCount = 0;

        if ($operation->isPriceOperation()) {
            $snapshots = $this->productRepo->getPriceSnapshots($ids);
            foreach ($ids as $id) {
                if (!isset($snapshots[$id])) {
                    continue;
                }
                $snapshot = $snapshots[$id];
                $res = $this->pricingEngine->calculateBulkOperation($snapshot, $operation, "Product #{$id}");
                if ($res->isValid) {
                    $validCount++;
                } else {
                    $invalidCount++;
                }
                $previewData[] = $res->toArray();
            }
        } elseif ($operation->isStockOperation()) {
            $snapshots = $this->productRepo->getStockSnapshots($ids);
            foreach ($ids as $id) {
                if (!isset($snapshots[$id])) {
                    continue;
                }
                $snapshot = $snapshots[$id];
                $res = $this->stockManager->calculateBulkOperation($snapshot, $operation);
                $isValid = $res['isValid'];
                if ($isValid) {
                    $validCount++;
                } else {
                    $invalidCount++;
                }
                $origQty = $snapshot->quantity;
                $newQty = $isValid && $res['snapshot'] !== null ? $res['snapshot']->quantity : $origQty;

                $previewData[] = [
                    'product_id' => $id,
                    'product_name' => "Product #{$id}",
                    'original_price' => $origQty,
                    'calculated_price' => $newQty,
                    'operation_applied' => $operation->operationType,
                    'is_valid' => $isValid,
                    'violation' => $res['violation'],
                ];
            }
        }

        return [
            'data' => $previewData,
            'meta' => [
                'total' => count($previewData),
                'valid' => $validCount,
                'invalid' => $invalidCount,
            ],
        ];
    }

    /**
     * Execute a confirmed bulk operation in chunks of 50 without aborting on partial failure (NFR-R03).
     *
     * @return array{succeeded: int, failed: int, bulk_operation_id: int, errors: array}
     */
    public function execute(BulkOperation $operation, int $userId, bool $alsoUpdateSale = false, array $excludedIds = []): array
    {
        if (!$operation->isPriceOperation() && !$operation->isStockOperation()) {
            throw new DomainException("Invalid operation type: {$operation->operationType}");
        }

        $targetIds = array_diff($operation->productIds, $excludedIds);
        $bulkOperationId = (int) (microtime(true) * 1000);

        $succeeded = 0;
        $failed = 0;
        $errors = [];

        $chunks = array_chunk($targetIds, 50);
        foreach ($chunks as $chunkIds) {
            if ($operation->isPriceOperation()) {
                $snapshots = $this->productRepo->getPriceSnapshots($chunkIds);
                foreach ($chunkIds as $id) {
                    try {
                        if (!isset($snapshots[$id])) {
                            throw new DomainException("Product ID {$id} not found.");
                        }
                        $snapshot = $snapshots[$id];
                        $res = $this->pricingEngine->calculateBulkOperation($snapshot, $operation);
                        if (!$res->isValid) {
                            throw new DomainException("Validation violation: {$res->violation}");
                        }

                        $newReg = $snapshot->regularPrice;
                        $newSale = $snapshot->salePrice;
                        $target = $operation->targetField;

                        if ($target === 'regular_price' || $target === 'both') {
                            $newReg = $res->calculatedPrice;
                            if ($newReg !== $snapshot->regularPrice) {
                                $this->changeLog->record(
                                    $id,
                                    $userId,
                                    'regular_price',
                                    $snapshot->regularPrice !== null ? (string) $snapshot->regularPrice : null,
                                    $newReg !== null ? (string) $newReg : '',
                                    'bulk_operation',
                                    $bulkOperationId
                                );
                            }
                        }
                        if ($target === 'sale_price' || $target === 'both' || ($alsoUpdateSale && $snapshot->salePrice !== null)) {
                            $newSale = $target === 'sale_price' ? $res->calculatedPrice : $newSale;
                            if ($newSale !== $snapshot->salePrice) {
                                $this->changeLog->record(
                                    $id,
                                    $userId,
                                    'sale_price',
                                    $snapshot->salePrice !== null ? (string) $snapshot->salePrice : null,
                                    $newSale !== null ? (string) $newSale : '',
                                    'bulk_operation',
                                    $bulkOperationId
                                );
                            }
                        }

                        $this->productRepo->updatePrice($id, $newReg, $newSale);
                        $succeeded++;
                    } catch (\Throwable $e) {
                        $failed++;
                        $errors[] = [
                            'product_id' => $id,
                            'message' => $e->getMessage(),
                        ];
                    }
                }
            } elseif ($operation->isStockOperation()) {
                $snapshots = $this->productRepo->getStockSnapshots($chunkIds);
                foreach ($chunkIds as $id) {
                    try {
                        if (!isset($snapshots[$id])) {
                            throw new DomainException("Product ID {$id} not found.");
                        }
                        $snapshot = $snapshots[$id];
                        $res = $this->stockManager->calculateBulkOperation($snapshot, $operation);
                        if (!$res['isValid'] || $res['snapshot'] === null) {
                            throw new DomainException("Stock violation: {$res['violation']}");
                        }

                        $newSnap = $res['snapshot'];
                        $this->productRepo->updateStock($id, $newSnap->isManaged, $newSnap->quantity);

                        if ($newSnap->quantity !== $snapshot->quantity) {
                            $this->changeLog->record(
                                $id,
                                $userId,
                                'stock_quantity',
                                $snapshot->quantity !== null ? (string) $snapshot->quantity : null,
                                $newSnap->quantity !== null ? (string) $newSnap->quantity : '',
                                'bulk_operation',
                                $bulkOperationId
                            );
                        }
                        $succeeded++;
                    } catch (\Throwable $e) {
                        $failed++;
                        $errors[] = [
                            'product_id' => $id,
                            'message' => $e->getMessage(),
                        ];
                    }
                }
            }
        }

        return [
            'succeeded' => $succeeded,
            'failed' => $failed,
            'bulk_operation_id' => $bulkOperationId,
            'errors' => $errors,
        ];
    }
}
