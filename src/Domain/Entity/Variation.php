<?php declare(strict_types=1);

namespace WPM\Domain\Entity;

use WPM\Domain\ValueObject\PriceSnapshot;
use WPM\Domain\ValueObject\StockSnapshot;

defined('ABSPATH') || exit;

/**
 * Domain Entity representing a product variation.
 */
final class Variation
{
    public function __construct(
        public readonly int $id,
        public readonly int $parentId,
        public readonly array $attributes,
        public readonly bool $isDefault,
        public readonly PriceSnapshot $price,
        public readonly StockSnapshot $stock
    ) {
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'parent_id' => $this->parentId,
            'attributes' => $this->attributes,
            'is_default' => $this->isDefault,
            'price' => $this->price->toArray(),
            'stock' => $this->stock->toArray(),
        ];
    }
}
