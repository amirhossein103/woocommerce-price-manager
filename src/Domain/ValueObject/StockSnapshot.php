<?php declare(strict_types=1);

namespace WPM\Domain\ValueObject;

use WPM\Domain\Exception\InvalidStockException;

defined('ABSPATH') || exit;

/**
 * Immutable Value Object representing the inventory state of a product or variation.
 */
final class StockSnapshot
{
    public readonly string $status;
    public readonly string $capturedAt;

    public function __construct(
        public readonly int $productId,
        public readonly bool $isManaged,
        public readonly ?int $quantity = null,
        ?string $status = null,
        ?string $capturedAt = null
    ) {
        $normalizedStatus = $status;
        if ($status === 'in_stock') {
            $normalizedStatus = 'instock';
        } elseif ($status === 'out_of_stock') {
            $normalizedStatus = 'outofstock';
        } elseif ($status === 'on_backorder') {
            $normalizedStatus = 'onbackorder';
        }

        if ($isManaged) {
            if ($quantity === null) {
                throw new InvalidStockException('Managed stock requires an explicit quantity.');
            }
            if ($quantity < 0) {
                throw InvalidStockException::negativeStock($quantity);
            }
            $this->status = $quantity > 0 ? 'instock' : 'outofstock';
        } else {
            $this->status = $normalizedStatus !== null && in_array($normalizedStatus, ['instock', 'outofstock', 'onbackorder'], true)
                ? $normalizedStatus
                : 'instock';
        }

        $this->capturedAt = $capturedAt ?? gmdate('c');
    }

    public function isInStock(): bool
    {
        return $this->status === 'instock' || $this->status === 'in_stock';
    }

    public function toArray(): array
    {
        return [
            'product_id' => $this->productId,
            'is_managed' => $this->isManaged,
            'manage_stock' => $this->isManaged,
            'quantity' => $this->isManaged ? $this->quantity : null,
            'status' => $this->status,
            'stock_status' => $this->status,
            'captured_at' => $this->capturedAt,
        ];
    }
}
