<?php declare(strict_types=1);

namespace WPM\Domain\Exception;

defined('ABSPATH') || exit;

/**
 * Thrown when a price invariant or rule is violated.
 */
final class InvalidPriceException extends DomainException
{
    public static function negativePrice(float $price): self
    {
        return new self(sprintf('Price cannot be negative, got: %f', $price));
    }

    public static function saleExceedsRegular(float $salePrice, float $regularPrice): self
    {
        return new self(sprintf('Sale price (%f) must be strictly less than regular price (%f)', $salePrice, $regularPrice));
    }
}
