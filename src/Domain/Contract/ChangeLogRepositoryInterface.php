<?php declare(strict_types=1);

namespace WPM\Domain\Contract;

use WPM\Domain\Entity\ChangeRecord;

defined('ABSPATH') || exit;

/**
 * Domain Contract for auditing and retrieving product change history.
 */
interface ChangeLogRepositoryInterface
{
    /**
     * Record a single field change in history.
     */
    public function record(
        int $productId,
        int $userId,
        string $field,
        ?string $oldValue,
        string $newValue,
        string $operationType = 'manual_edit',
        ?int $bulkOperationId = null,
        ?int $rolledBackFromId = null
    ): int;

    /**
     * Get recent changes for a product.
     *
     * @return ChangeRecord[]
     */
    public function getByProduct(int $productId, int $limit = 5): array;

    /**
     * Get aggregated global bulk jobs.
     *
     * @return array
     */
    public function getGlobalJobs(int $limit = 20): array;

    /**
     * Get all individual change records tied to a specific bulk operation ID.
     *
     * @return ChangeRecord[]
     */
    public function getRecordsByBulkId(int $bulkOperationId): array;

    /**
     * Get a single change record by ID.
     */
    public function findById(int $id): ?ChangeRecord;

    /**
     * Count total changes for a product.
     */
    public function countByProduct(int $productId): int;

    /**
     * Prune records older than N days.
     */
    public function prune(int $daysOld): int;

    /**
     * Prune older change records for a product, keeping only the last N changes.
     */
    public function pruneByProductLimit(int $productId, int $limit = 5): int;
}
