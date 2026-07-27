<?php declare(strict_types=1);

namespace WPM\Domain\Entity;

defined('ABSPATH') || exit;

/**
 * Domain Entity representing a single change audit record in history.
 */
final class ChangeRecord
{
    public function __construct(
        public readonly int $id,
        public readonly int $productId,
        public readonly int $userId,
        public readonly string $field,
        public readonly ?string $oldValue,
        public readonly string $newValue,
        public readonly string $operationType = 'manual_edit',
        public readonly ?int $bulkOperationId = null,
        public readonly ?int $rolledBackFromId = null,
        public readonly ?string $createdAt = null,
        public readonly string $userName = 'System'
    ) {
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->productId,
            'user_id' => $this->userId,
            'field' => $this->field,
            'old_value' => $this->oldValue,
            'new_value' => $this->newValue,
            'operation_type' => $this->operationType,
            'bulk_operation_id' => $this->bulkOperationId,
            'rolled_back_from_id' => $this->rolledBackFromId,
            'created_at' => $this->createdAt ?? gmdate('c'),
            'user_name' => $this->userName,
        ];
    }
}
