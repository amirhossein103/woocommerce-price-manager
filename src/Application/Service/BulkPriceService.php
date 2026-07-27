<?php declare(strict_types=1);

namespace WPM\Application\Service;

defined('ABSPATH') || exit;

/**
 * Alias for BulkOperationService to maintain compatibility across naming references in specifications.
 */
final class BulkPriceService
{
    public function __construct(private readonly BulkOperationService $bulkOperationService)
    {
    }

    public function preview($operation): array
    {
        return $this->bulkOperationService->preview($operation);
    }

    public function execute($operation, int $userId, bool $alsoUpdateSale = false, array $excludedIds = []): array
    {
        return $this->bulkOperationService->execute($operation, $userId, $alsoUpdateSale, $excludedIds);
    }
}
