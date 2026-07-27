<?php declare(strict_types=1);

namespace WPM\Application\Service;

use WPM\Domain\Contract\ProductRepositoryInterface;
use WPM\Domain\Contract\ChangeLogRepositoryInterface;
use WPM\Domain\Pricing\PricingEngine;
use WPM\Domain\Stock\StockManager;
use WPM\Domain\Exception\DomainException;

defined('ABSPATH') || exit;

/**
 * Application Service orchestrating individual product price and stock updates with audit logging.
 */
final class ProductSaveService
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepo,
        private readonly PricingEngine $pricingEngine,
        private readonly StockManager $stockManager,
        private readonly ChangeLogRepositoryInterface $changeLog
    ) {
    }

    /**
     * Save changes to a single product's pricing and/or inventory.
     *
     * @throws DomainException When business rules or invariants are violated.
     */
    public function saveProduct(int $productId, array $changes, int $userId): array
    {
        $current = $this->productRepo->findById($productId);
        if ($current === null) {
            throw new DomainException("Product with ID {$productId} not found.");
        }

        $regPrice = array_key_exists('regular_price', $changes)
            ? ($changes['regular_price'] !== null && $changes['regular_price'] !== '' ? (float) $changes['regular_price'] : null)
            : $current->price?->regularPrice;

        $salePrice = array_key_exists('sale_price', $changes)
            ? ($changes['sale_price'] !== null && $changes['sale_price'] !== '' ? (float) $changes['sale_price'] : null)
            : $current->price?->salePrice;

        $dateFrom = array_key_exists('date_on_sale_from', $changes)
            ? ($changes['date_on_sale_from'] !== null ? (string) $changes['date_on_sale_from'] : '')
            : $current->price?->dateOnSaleFrom;

        $dateTo = array_key_exists('date_on_sale_to', $changes)
            ? ($changes['date_on_sale_to'] !== null ? (string) $changes['date_on_sale_to'] : '')
            : $current->price?->dateOnSaleTo;

        if (array_key_exists('regular_price', $changes) || array_key_exists('sale_price', $changes) || array_key_exists('date_on_sale_from', $changes) || array_key_exists('date_on_sale_to', $changes)) {
            // Enforce domain invariants via PricingEngine
            $this->pricingEngine->createSnapshot($productId, $regPrice, $salePrice, $dateFrom, $dateTo);

            $oldReg = $current->price?->regularPrice;
            $oldSale = $current->price?->salePrice;
            $oldDateFrom = $current->price?->dateOnSaleFrom;
            $oldDateTo = $current->price?->dateOnSaleTo;

            if ($regPrice !== $oldReg || $salePrice !== $oldSale || $dateFrom !== $oldDateFrom || $dateTo !== $oldDateTo) {
                $this->productRepo->updatePrice($productId, $regPrice, $salePrice, $dateFrom, $dateTo);

                if ($regPrice !== $oldReg) {
                    $this->changeLog->record(
                        $productId,
                        $userId,
                        'regular_price',
                        $oldReg !== null ? (string) $oldReg : null,
                        $regPrice !== null ? (string) $regPrice : '',
                        'manual_edit'
                    );
                }
                if ($salePrice !== $oldSale) {
                    $this->changeLog->record(
                        $productId,
                        $userId,
                        'sale_price',
                        $oldSale !== null ? (string) $oldSale : null,
                        $salePrice !== null ? (string) $salePrice : '',
                        'manual_edit'
                    );
                }
                if ($dateFrom !== $oldDateFrom && array_key_exists('date_on_sale_from', $changes)) {
                    $this->changeLog->record(
                        $productId,
                        $userId,
                        'date_on_sale_from',
                        $oldDateFrom !== null ? (string) $oldDateFrom : null,
                        $dateFrom !== null && $dateFrom !== '' ? (string) $dateFrom : '',
                        'manual_edit'
                    );
                }
                if ($dateTo !== $oldDateTo && array_key_exists('date_on_sale_to', $changes)) {
                    $this->changeLog->record(
                        $productId,
                        $userId,
                        'date_on_sale_to',
                        $oldDateTo !== null ? (string) $oldDateTo : null,
                        $dateTo !== null && $dateTo !== '' ? (string) $dateTo : '',
                        'manual_edit'
                    );
                }
            }
        }

        $managedKey = array_key_exists('is_managed', $changes) ? 'is_managed' : (array_key_exists('manage_stock', $changes) ? 'manage_stock' : null);
        $managed = $managedKey !== null
            ? (bool) $changes[$managedKey]
            : ($current->stock?->isManaged ?? false);

        $quantity = array_key_exists('stock_quantity', $changes)
            ? ($changes['stock_quantity'] !== null && $changes['stock_quantity'] !== '' ? (int) $changes['stock_quantity'] : null)
            : $current->stock?->quantity;

        $statusKey = array_key_exists('status', $changes) ? 'status' : (array_key_exists('stock_status', $changes) ? 'stock_status' : null);
        $status = $statusKey !== null
            ? (string) $changes[$statusKey]
            : $current->stock?->status;

        if ($managedKey !== null || array_key_exists('stock_quantity', $changes) || $statusKey !== null) {
            // Validate managed stock quantity before snapshot creation
            if ($managed && $quantity === null) {
                // Use existing quantity if available, otherwise raise a domain exception
                $existingQty = $current->stock?->quantity;
                // If there is no existing quantity, default to 0 to allow enabling managed stock
                if ($existingQty === null) {
                    $quantity = 0;
                } else {
                    $quantity = $existingQty;
                }
            }
            try {
                $this->stockManager->createSnapshot($productId, $managed, $quantity, $status);
            } catch (\WPM\Domain\Exception\InvalidStockException $e) {
                // Convert domain exception to a generic business rule violation for the API layer
                throw new DomainException($e->getMessage(), 422);
            }

            $oldQty = $current->stock?->quantity;
            $oldManaged = $current->stock?->isManaged;
            $oldStatus = $current->stock?->status;

            if ($managed !== $oldManaged || $quantity !== $oldQty || $status !== $oldStatus) {
                $this->productRepo->updateStock($productId, $managed, $quantity, $status);
                
                if ($managed !== $oldManaged) {
                    $this->changeLog->record($productId, $userId, 'manage_stock', $oldManaged ? '1' : '0', $managed ? '1' : '0', 'manual_edit');
                }
                if ($quantity !== $oldQty) {
                    $this->changeLog->record($productId, $userId, 'stock_quantity', $oldQty !== null ? (string) $oldQty : null, $quantity !== null ? (string) $quantity : '', 'manual_edit');
                }
                if ($status !== $oldStatus) {
                    $this->changeLog->record($productId, $userId, 'stock_status', $oldStatus ?? '', $status ?? '', 'manual_edit');
                }
            }
        }

        $updated = $this->productRepo->findById($productId);
        return $updated ? $updated->toArray() : $current->toArray();
    }
}
