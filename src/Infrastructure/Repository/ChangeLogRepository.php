<?php declare(strict_types=1);

namespace WPM\Infrastructure\Repository;

use WPM\Domain\Contract\ChangeLogRepositoryInterface;
use WPM\Domain\Entity\ChangeRecord;

defined('ABSPATH') || exit;

/**
 * Infrastructure Repository for auditing and retrieving product change history (Rule R-02 / Module 06).
 */
final class ChangeLogRepository implements ChangeLogRepositoryInterface
{
    private string $table;

    public function __construct(private readonly \wpdb $wpdb)
    {
        $this->table = $this->wpdb->prefix . 'wpm_change_log';
    }

    public function record(
        int $productId,
        int $userId,
        string $field,
        ?string $oldValue,
        string $newValue,
        string $operationType = 'manual_edit',
        ?int $bulkOperationId = null,
        ?int $rolledBackFromId = null
    ): int {
        $result = $this->wpdb->insert(
            $this->table,
            [
                'product_id' => $productId,
                'user_id' => $userId,
                'field' => $field,
                'old_value' => $oldValue,
                'new_value' => $newValue,
                'operation_type' => $operationType,
                'bulk_operation_id' => $bulkOperationId,
                'rolled_back_from_id' => $rolledBackFromId,
            ],
            ['%d', '%d', '%s', '%s', '%s', '%s', '%d', '%d']
        );

        if ($result === false) {
            return 0;
        }

        $insertId = (int) $this->wpdb->insert_id;
        $this->pruneByProductLimit($productId, 5);

        return $insertId;
    }

    public function getByProduct(int $productId, int $limit = 5): array
    {
        $usersTable = $this->wpdb->users;
        $sql = $this->wpdb->prepare(
            "SELECT cl.*, u.display_name as user_name
             FROM {$this->table} cl
             LEFT JOIN {$usersTable} u ON cl.user_id = u.ID
             WHERE cl.product_id = %d
             ORDER BY cl.created_at DESC
             LIMIT %d",
            $productId,
            $limit
        );

        $results = $this->wpdb->get_results($sql, ARRAY_A);
        if (!is_array($results)) {
            return [];
        }

        $records = [];
        foreach ($results as $row) {
            $records[] = $this->mapRowToEntity($row);
        }

        return $records;
    }

    public function findById(int $id): ?ChangeRecord
    {
        $usersTable = $this->wpdb->users;
        $sql = $this->wpdb->prepare(
            "SELECT cl.*, u.display_name as user_name
             FROM {$this->table} cl
             LEFT JOIN {$usersTable} u ON cl.user_id = u.ID
             WHERE cl.id = %d
             LIMIT 1",
            $id
        );

        $row = $this->wpdb->get_row($sql, ARRAY_A);
        if (!is_array($row)) {
            return null;
        }

        return $this->mapRowToEntity($row);
    }

    public function countByProduct(int $productId): int
    {
        $sql = $this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table} WHERE product_id = %d",
            $productId
        );

        return (int) $this->wpdb->get_var($sql);
    }

    public function prune(int $daysOld): int
    {
        if ($daysOld < 1) {
            return 0;
        }

        $sql = $this->wpdb->prepare(
            "DELETE FROM {$this->table} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $daysOld
        );

        $deleted = $this->wpdb->query($sql);
        return is_numeric($deleted) ? (int) $deleted : 0;
    }

    public function pruneByProductLimit(int $productId, int $limit = 5): int
    {
        if ($limit < 1) {
            return 0;
        }

        $sql = $this->wpdb->prepare(
            "SELECT id FROM {$this->table} WHERE product_id = %d ORDER BY id DESC LIMIT %d, 999999",
            $productId,
            $limit
        );
        $oldIds = $this->wpdb->get_col($sql);
        if (empty($oldIds)) {
            return 0;
        }

        $idsList = implode(',', array_map('intval', $oldIds));
        $deleted = $this->wpdb->query("DELETE FROM {$this->table} WHERE id IN ({$idsList})");
        return is_numeric($deleted) ? (int) $deleted : 0;
    }

    private function mapRowToEntity(array $row): ChangeRecord
    {
        return new ChangeRecord(
            (int) ($row['id'] ?? 0),
            (int) ($row['product_id'] ?? 0),
            (int) ($row['user_id'] ?? 0),
            (string) ($row['field'] ?? ''),
            isset($row['old_value']) ? (string) $row['old_value'] : null,
            (string) ($row['new_value'] ?? ''),
            (string) ($row['operation_type'] ?? 'manual_edit'),
            isset($row['bulk_operation_id']) ? (int) $row['bulk_operation_id'] : null,
            isset($row['rolled_back_from_id']) ? (int) $row['rolled_back_from_id'] : null,
            isset($row['created_at']) ? (string) $row['created_at'] : null,
            (string) ($row['user_name'] ?? 'System')
        );
    }
}
