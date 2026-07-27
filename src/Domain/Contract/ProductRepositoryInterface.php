<?php declare(strict_types=1);

namespace WPM\Domain\Contract;

use WPM\Domain\Entity\Product;
use WPM\Domain\Entity\Variation;
use WPM\Domain\ValueObject\PriceSnapshot;
use WPM\Domain\ValueObject\StockSnapshot;

defined('ABSPATH') || exit;

/**
 * Domain Contract for accessing and updating product and variation data (DIP).
 */
interface ProductRepositoryInterface
{
    /**
     * Query products with filters, search, sorting, and pagination.
     *
     * @param array $filters Query arguments.
     * @return array{products: Product[], total: int, pages: int}
     */
    public function query(array $filters): array;

    /**
     * Find a single product by ID.
     */
    public function findById(int $id): ?Product;

    /**
     * Get all variations for a variable product.
     *
     * @return Variation[]
     */
    public function getVariations(int $productId): array;

    /**
     * Update a product's price and optional sale schedule.
     */
    public function updatePrice(int $id, ?float $regularPrice, ?float $salePrice, ?string $dateFrom = null, ?string $dateTo = null): void;

    /**
     * Update a product's stock. Optionally set stock status when management is disabled.
     */
    public function updateStock(int $id, bool $managed, ?int $quantity, ?string $status = null): void;

    /**
     * Get price snapshots for multiple products in one query.
     *
     * @param int[] $productIds
     * @return array<int, PriceSnapshot> Keyed by product ID
     */
    public function getPriceSnapshots(array $productIds): array;

    /**
     * Get stock snapshots for multiple products in one query.
     *
     * @param int[] $productIds
     * @return array<int, StockSnapshot> Keyed by product ID
     */
    public function getStockSnapshots(array $productIds): array;
}
