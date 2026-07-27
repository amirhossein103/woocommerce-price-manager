<?php declare(strict_types=1);

namespace WPM\Domain\Entity;

use WPM\Domain\ValueObject\PriceSnapshot;
use WPM\Domain\ValueObject\StockSnapshot;

defined('ABSPATH') || exit;

/**
 * Domain Entity representing a WooCommerce product (Module 01 / Module 02).
 */
final class Product
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $sku,
        public readonly string $type,
        public readonly string $status,
        public readonly string $imageUrl,
        public readonly string $permalink,
        public readonly ?PriceSnapshot $price,
        public readonly ?StockSnapshot $stock,
        public readonly int $variationCount = 0,
        public readonly ?string $priceRange = null,
        public readonly ?int $totalStock = null,
        public readonly ?string $lastModified = null
    ) {
    }

    public function isSimple(): bool
    {
        return $this->type === 'simple';
    }

    public function isVariable(): bool
    {
        return $this->type === 'variable';
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'sku' => $this->sku,
            'type' => $this->type,
            'status' => $this->status,
            'image_url' => $this->imageUrl,
            'permalink' => $this->permalink,
            'price' => $this->price?->toArray(),
            'stock' => $this->stock?->toArray(),
            'variation_count' => $this->variationCount,
            'price_range' => $this->priceRange,
            'total_stock' => $this->totalStock,
            'last_modified' => $this->lastModified ?? gmdate('c'),
        ];
    }
}
