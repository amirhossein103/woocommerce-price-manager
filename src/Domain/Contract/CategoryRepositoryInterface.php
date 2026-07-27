<?php declare(strict_types=1);

namespace WPM\Domain\Contract;

defined('ABSPATH') || exit;

/**
 * Domain Contract for retrieving product categories and hierarchy info.
 */
interface CategoryRepositoryInterface
{
    /**
     * Get all product categories as a flat list with hierarchy info.
     *
     * @return array{id: int, name: string, slug: string, parent_id: ?int, count: int}[]
     */
    public function getAll(): array;

    /**
     * Get category and all its descendant IDs (for inclusive filtering).
     *
     * @return int[]
     */
    public function getDescendantIds(int $categoryId): array;
}
