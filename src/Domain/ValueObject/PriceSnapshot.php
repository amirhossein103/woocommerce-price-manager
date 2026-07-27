<?php declare(strict_types=1);

namespace WPM\Domain\ValueObject;

use WPM\Domain\Exception\InvalidPriceException;

defined('ABSPATH') || exit;

/**
 * Immutable Value Object representing the current pricing state of a product or variation at a specific moment in time.
 */
final class PriceSnapshot
{
    public readonly ?float $regularPrice;
    public readonly ?float $salePrice;
    public readonly ?float $effectivePrice;
    public readonly string $capturedAt;

    public function __construct(
        public readonly int $productId,
        ?float $regularPrice,
        ?float $salePrice = null,
        ?string $capturedAt = null,
        public readonly ?string $dateOnSaleFrom = null,
        public readonly ?string $dateOnSaleTo = null
    ) {
        if ($regularPrice !== null && $regularPrice < 0.0) {
            throw InvalidPriceException::negativePrice($regularPrice);
        }
        if ($salePrice !== null && $salePrice < 0.0) {
            throw InvalidPriceException::negativePrice($salePrice);
        }
        if ($regularPrice !== null && $salePrice !== null && $salePrice >= $regularPrice) {
            throw InvalidPriceException::saleExceedsRegular($salePrice, $regularPrice);
        }
        if ($regularPrice === null && $salePrice !== null) {
            throw new InvalidPriceException('Cannot set a sale price when regular price is null.');
        }

        $this->regularPrice = $regularPrice !== null ? round($regularPrice, 4) : null;
        $this->salePrice = $salePrice !== null ? round($salePrice, 4) : null;
        $this->effectivePrice = $this->salePrice ?? $this->regularPrice;
        $this->capturedAt = $capturedAt ?? gmdate('c');
    }

    public function isOnSale(): bool
    {
        return $this->salePrice !== null && $this->regularPrice !== null && $this->salePrice < $this->regularPrice;
    }

    public function isFree(): bool
    {
        return $this->effectivePrice === 0.0;
    }

    public function toArray(): array
    {
        return [
            'product_id' => $this->productId,
            'regular_price' => $this->regularPrice,
            'sale_price' => $this->salePrice,
            'effective_price' => $this->effectivePrice,
            'captured_at' => $this->capturedAt,
            'date_on_sale_from' => $this->dateOnSaleFrom,
            'date_on_sale_to' => $this->dateOnSaleTo,
        ];
    }
}
