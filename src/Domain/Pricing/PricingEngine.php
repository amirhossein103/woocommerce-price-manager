<?php declare(strict_types=1);

namespace WPM\Domain\Pricing;

use WPM\Domain\ValueObject\BulkOperation;
use WPM\Domain\ValueObject\PriceCalculationResult;
use WPM\Domain\ValueObject\PriceSnapshot;
use WPM\Domain\Exception\InvalidPriceException;

defined('ABSPATH') || exit;

/**
 * Pure Domain Service responsible for pricing calculations and invariant validations (Module 03).
 */
final class PricingEngine
{
    /**
     * Calculate the new price snapshot for a product given a regular price and sale price.
     * Enforces domain invariants (BR-001, BR-002).
     *
     * @throws InvalidPriceException When invariants are violated.
     */
    public function createSnapshot(int $productId, ?float $regularPrice, ?float $salePrice = null): PriceSnapshot
    {
        return new PriceSnapshot($productId, $regularPrice, $salePrice);
    }

    /**
     * Preview a bulk pricing operation on a single product's PriceSnapshot without side effects.
     */
    public function calculateBulkOperation(
        PriceSnapshot $currentSnapshot,
        BulkOperation $operation,
        string $productName = 'Product'
    ): PriceCalculationResult {
        if (!$operation->isPriceOperation()) {
            return new PriceCalculationResult(
                $currentSnapshot->productId,
                $productName,
                $currentSnapshot->effectivePrice,
                $currentSnapshot->effectivePrice,
                $operation->operationType,
                false,
                'not_a_price_operation'
            );
        }

        $target = $operation->targetField;
        $param = $operation->parameter;
        $type = $operation->operationType;

        $origRegular = $currentSnapshot->regularPrice;
        $origSale = $currentSnapshot->salePrice;

        $newRegular = $origRegular;
        $newSale = $origSale;

        if ($target === 'regular_price' || $target === 'both') {
            $newRegular = $this->applyFormula($origRegular, $type, $param);
        }
        if ($target === 'sale_price' || $target === 'both') {
            if ($origSale !== null || $type === BulkOperation::TYPE_FIXED_SET) {
                $newSale = $this->applyFormula($origSale ?? 0.0, $type, $param);
            }
        }

        if ($newRegular !== null && $newRegular < 0.0) {
            return new PriceCalculationResult(
                $currentSnapshot->productId,
                $productName,
                $origRegular,
                $newRegular,
                $type,
                false,
                'negative_regular_price'
            );
        }
        if ($newSale !== null && $newSale < 0.0) {
            return new PriceCalculationResult(
                $currentSnapshot->productId,
                $productName,
                $origSale,
                $newSale,
                $type,
                false,
                'negative_sale_price'
            );
        }
        if ($newRegular !== null && $newSale !== null && $newSale >= $newRegular) {
            return new PriceCalculationResult(
                $currentSnapshot->productId,
                $productName,
                $origRegular,
                $newSale,
                $type,
                false,
                'sale_price_exceeds_regular'
            );
        }

        $calculatedDisplay = $target === 'sale_price' ? $newSale : $newRegular;
        $originalDisplay = $target === 'sale_price' ? $origSale : $origRegular;

        return new PriceCalculationResult(
            $currentSnapshot->productId,
            $productName,
            $originalDisplay,
            $calculatedDisplay,
            $type,
            true,
            null
        );
    }

    private function applyFormula(?float $currentValue, string $operationType, float $parameter): ?float
    {
        if ($currentValue === null && $operationType !== BulkOperation::TYPE_FIXED_SET) {
            return null;
        }

        $val = $currentValue ?? 0.0;

        switch ($operationType) {
            case BulkOperation::TYPE_PERCENTAGE_INCREASE:
                $result = $val * (1.0 + ($parameter / 100.0));
                break;
            case BulkOperation::TYPE_PERCENTAGE_DECREASE:
                $result = $val * (1.0 - ($parameter / 100.0));
                break;
            case BulkOperation::TYPE_FIXED_INCREASE:
                $result = $val + $parameter;
                break;
            case BulkOperation::TYPE_FIXED_DECREASE:
                $result = $val - $parameter;
                break;
            case BulkOperation::TYPE_FIXED_SET:
                $result = $parameter;
                break;
            default:
                $result = $val;
        }

        return round($result, 4);
    }
}
