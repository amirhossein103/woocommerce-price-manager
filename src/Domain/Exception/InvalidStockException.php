<?php declare(strict_types=1);

namespace WPM\Domain\Exception;

defined('ABSPATH') || exit;

/**
 * Thrown when an inventory invariant or rule is violated.
 */
final class InvalidStockException extends DomainException
{
    public static function negativeStock(int $quantity): self
    {
        return new self(sprintf('Managed stock quantity cannot be negative, got: %d', $quantity));
    }
}
