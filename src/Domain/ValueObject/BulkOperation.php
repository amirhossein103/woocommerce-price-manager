<?php declare(strict_types=1);

namespace WPM\Domain\ValueObject;

use WPM\Domain\Exception\DomainException;

defined('ABSPATH') || exit;

/**
 * Value Object representing a bulk operation specification on prices or stock.
 */
final class BulkOperation
{
    public const TYPE_PERCENTAGE_INCREASE = 'price_percentage_increase';
    public const TYPE_PERCENTAGE_DECREASE = 'price_percentage_decrease';
    public const TYPE_FIXED_INCREASE = 'price_fixed_increase';
    public const TYPE_FIXED_DECREASE = 'price_fixed_decrease';
    public const TYPE_FIXED_SET = 'price_fixed_set';
    public const TYPE_STOCK_SET = 'stock_fixed_set';
    public const TYPE_STOCK_INCREASE = 'stock_fixed_increase';
    public const TYPE_STOCK_CLEAR = 'stock_clear';

    /**
     * @param string      $operationType One of the TYPE_* constants.
     * @param string      $targetField   'regular_price' | 'sale_price' | 'both' | 'stock_quantity'.
     * @param float       $parameter     Numeric parameter (e.g., 10.0 for 10% or $10).
     * @param int[]       $productIds    List of target product or variation IDs.
     * @param string|null $dateFrom      Optional sale start date (YYYY-MM-DD).
     * @param string|null $dateTo        Optional sale end date (YYYY-MM-DD).
     */
    public function __construct(
        public readonly string $operationType,
        public readonly string $targetField,
        public readonly float $parameter,
        public readonly array $productIds,
        public readonly ?string $dateFrom = null,
        public readonly ?string $dateTo = null
    ) {
        if (empty($productIds)) {
            throw new DomainException('Bulk operation requires at least one target product ID.');
        }
        foreach ($productIds as $id) {
            if (!is_int($id) && !ctype_digit((string) $id)) {
                throw new DomainException(sprintf('Invalid product ID in bulk operation: %s', var_export($id, true)));
            }
        }
    }

    public function isPriceOperation(): bool
    {
        return str_starts_with($this->operationType, 'price_');
    }

    public function isStockOperation(): bool
    {
        return str_starts_with($this->operationType, 'stock_');
    }

    public static function fromArray(array $data): self
    {
        if (!isset($data['operation_type'], $data['target_field'], $data['product_ids'])) {
            throw new DomainException('Missing required fields for BulkOperation.');
        }

        return new self(
            (string) $data['operation_type'],
            (string) $data['target_field'],
            (float) ($data['parameter'] ?? 0.0),
            array_map('intval', (array) $data['product_ids']),
            empty($data['date_from']) ? null : (string) $data['date_from'],
            empty($data['date_to']) ? null : (string) $data['date_to']
        );
    }

    public function toArray(): array
    {
        return [
            'operation_type' => $this->operationType,
            'target_field' => $this->targetField,
            'parameter' => $this->parameter,
            'product_ids' => $this->productIds,
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
        ];
    }
}
