<?php declare(strict_types=1);

namespace WPM\Domain\ValueObject;

use WPM\Domain\Exception\InvalidPriceException;

defined('ABSPATH') || exit;

/**
 * Immutable Value Object representing a monetary amount and currency.
 */
final class Money
{
    private readonly float $amount;

    public function __construct(
        float $amount,
        public readonly string $currency
    ) {
        if ($amount < 0.0) {
            throw InvalidPriceException::negativePrice($amount);
        }
        $this->amount = round($amount, 4);
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function getRoundedAmount(int $decimals): float
    {
        return round($this->amount, $decimals);
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);
        return new self($this->amount + $other->amount, $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);
        $result = $this->amount - $other->amount;
        if ($result < 0.0) {
            $result = 0.0;
        }
        return new self($result, $this->currency);
    }

    public function multiply(float $multiplier): self
    {
        $result = $this->amount * $multiplier;
        if ($result < 0.0) {
            $result = 0.0;
        }
        return new self($result, $this->currency);
    }

    public function percentageIncrease(float $percentage): self
    {
        return $this->multiply(1.0 + ($percentage / 100.0));
    }

    public function percentageDecrease(float $percentage): self
    {
        $factor = 1.0 - ($percentage / 100.0);
        if ($factor < 0.0) {
            $factor = 0.0;
        }
        return $this->multiply($factor);
    }

    public function equals(self $other): bool
    {
        return $this->currency === $other->currency
            && abs($this->amount - $other->amount) < 0.00001;
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new \InvalidArgumentException(sprintf('Currency mismatch: %s vs %s', $this->currency, $other->currency));
        }
    }
}
