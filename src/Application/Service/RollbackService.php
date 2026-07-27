<?php declare(strict_types=1);

namespace WPM\Application\Service;

use WPM\Domain\Contract\ChangeLogRepositoryInterface;
use WPM\Domain\Contract\ProductRepositoryInterface;
use WPM\Domain\Pricing\PricingEngine;
use WPM\Domain\Stock\StockManager;
use WPM\Domain\Entity\ChangeRecord;
use WPM\Domain\Exception\DomainException;

defined('ABSPATH') || exit;

/**
 * Application Service responsible for rolling back historical pricing or inventory changes (Module 06).
 */
final class RollbackService
{
    public function __construct(
        private readonly ChangeLogRepositoryInterface $changeLog,
        private readonly ProductRepositoryInterface $productRepo,
        private readonly PricingEngine $pricingEngine,
        private readonly StockManager $stockManager
    ) {
    }

    /**
     * Roll back a specific change record, restoring its previous value and auditing the rollback event.
     *
     * @return array{rollback_record: array, product: array}
     * @throws DomainException When record not found or rollback violates current rules.
     */
    public function rollback(int $changeRecordId, int $userId): array
    {
        $record = $this->changeLog->findById($changeRecordId);
        if ($record === null) {
            throw new DomainException("Change record #{$changeRecordId} not found.");
        }

        $productId = $record->productId;
        $product = $this->productRepo->findById($productId);
        if ($product === null) {
            throw new DomainException("Target product #{$productId} no longer exists.");
        }

        $field = $record->field;
        $restoredValue = $record->oldValue;

        if ($field === 'regular_price' || $field === 'sale_price' || $field === 'date_on_sale_from' || $field === 'date_on_sale_to') {
            $reg = $field === 'regular_price'
                ? ($restoredValue !== null && $restoredValue !== '' ? (float) $restoredValue : null)
                : $product->price?->regularPrice;

            $sale = $field === 'sale_price'
                ? ($restoredValue !== null && $restoredValue !== '' ? (float) $restoredValue : null)
                : $product->price?->salePrice;

            $dateFrom = $field === 'date_on_sale_from'
                ? ($restoredValue !== null ? (string) $restoredValue : '')
                : $product->price?->dateOnSaleFrom;

            $dateTo = $field === 'date_on_sale_to'
                ? ($restoredValue !== null ? (string) $restoredValue : '')
                : $product->price?->dateOnSaleTo;

            // Enforce domain rules (e.g. sale < regular)
            $this->pricingEngine->createSnapshot($productId, $reg, $sale, $dateFrom, $dateTo);

            $currentVal = match ($field) {
                'regular_price' => $product->price?->regularPrice !== null ? (string) $product->price?->regularPrice : null,
                'sale_price' => $product->price?->salePrice !== null ? (string) $product->price?->salePrice : null,
                'date_on_sale_from' => $product->price?->dateOnSaleFrom,
                'date_on_sale_to' => $product->price?->dateOnSaleTo,
                default => null,
            };
            $this->productRepo->updatePrice($productId, $reg, $sale, $dateFrom, $dateTo);

            $newRecordId = $this->changeLog->record(
                $productId,
                $userId,
                $field,
                $currentVal !== null ? (string) $currentVal : null,
                $restoredValue !== null ? (string) $restoredValue : '',
                'rollback',
                null,
                $changeRecordId
            );
        } elseif ($field === 'stock_quantity' || $field === 'manage_stock' || $field === 'is_managed' || $field === 'stock_status' || $field === 'status') {
            $managed = $field === 'manage_stock' || $field === 'is_managed'
                ? ((bool) $restoredValue && $restoredValue !== '0' && $restoredValue !== 'false')
                : ($product->stock?->isManaged ?? true);

            $qty = $field === 'stock_quantity'
                ? ($restoredValue !== null && $restoredValue !== '' ? (int) $restoredValue : null)
                : $product->stock?->quantity;
            if ($managed && $qty === null) {
                $qty = 0;
            }

            $status = $field === 'stock_status' || $field === 'status'
                ? (string) $restoredValue
                : $product->stock?->status;

            $this->stockManager->createSnapshot($productId, $managed, $qty, $status);

            $currentVal = match ($field) {
                'manage_stock', 'is_managed' => ($product->stock?->isManaged ? '1' : '0'),
                'stock_quantity' => ($product->stock?->quantity !== null ? (string) $product->stock?->quantity : null),
                default => (string) ($product->stock?->status ?? ''),
            };

            $this->productRepo->updateStock($productId, $managed, $qty, $status);

            $newRecordId = $this->changeLog->record(
                $productId,
                $userId,
                $field,
                $currentVal,
                $restoredValue !== null ? (string) $restoredValue : '',
                'rollback',
                null,
                $changeRecordId
            );
        } else {
            throw new DomainException("Unsupported rollback field: {$field}");
        }

        $rollbackRecord = $this->changeLog->findById($newRecordId);
        $updatedProduct = $this->productRepo->findById($productId);

        return [
            'rollback_record' => $rollbackRecord ? $rollbackRecord->toArray() : [],
            'product' => $updatedProduct ? $updatedProduct->toArray() : [],
        ];
    }
}
