<?php declare(strict_types=1);

namespace WPM\Domain\ValueObject;

defined('ABSPATH') || exit;

/**
 * Represents the result of applying a bulk pricing operation to a single product's current price.
 */
final class PriceCalculationResult
{
    public function __construct(
        public readonly int $productId,
        public readonly string $productName,
        public readonly ?float $originalPrice,
        public readonly ?float $calculatedPrice,
        public readonly string $operationApplied,
        public readonly bool $isValid,
        public readonly ?string $violation = null
    ) {
    }

    public function toArray(): array
    {
        return [
            'product_id' => $this->productId,
            'product_name' => $this->productName,
            'original_price' => $this->originalPrice !== null ? round($this->originalPrice, 4) : null,
            'calculated_price' => $this->calculatedPrice !== null ? round($this->calculatedPrice, 4) : null,
            'operation_applied' => $this->operationApplied,
            'is_valid' => $this->isValid,
            'violation' => $this->violation,
        ];
    }
}
