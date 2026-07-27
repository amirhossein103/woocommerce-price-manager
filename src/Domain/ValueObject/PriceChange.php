<?php declare(strict_types=1);

namespace WPM\Domain\ValueObject;

defined('ABSPATH') || exit;

/**
 * Immutable Value Object representing a transition from an old PriceSnapshot to a new PriceSnapshot.
 */
final class PriceChange
{
    public function __construct(
        public readonly int $productId,
        public readonly ?PriceSnapshot $oldSnapshot,
        public readonly PriceSnapshot $newSnapshot,
        public readonly string $changeReason = 'manual_update'
    ) {
    }

    public function hasChanged(): bool
    {
        if ($this->oldSnapshot === null) {
            return true;
        }

        return $this->oldSnapshot->regularPrice !== $this->newSnapshot->regularPrice
            || $this->oldSnapshot->salePrice !== $this->newSnapshot->salePrice;
    }

    public function toArray(): array
    {
        return [
            'product_id' => $this->productId,
            'old_snapshot' => $this->oldSnapshot?->toArray(),
            'new_snapshot' => $this->newSnapshot->toArray(),
            'change_reason' => $this->changeReason,
            'has_changed' => $this->hasChanged(),
        ];
    }
}
