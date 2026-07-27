<?php declare(strict_types=1);

namespace WPM\Infrastructure\Repository;

use WPM\Domain\Contract\CategoryRepositoryInterface;

defined('ABSPATH') || exit;

/**
 * Infrastructure Repository for accessing WooCommerce product categories.
 */
final class CategoryRepository implements CategoryRepositoryInterface
{
    public function getAll(): array
    {
        if (!function_exists('get_terms')) {
            return [];
        }

        $terms = get_terms([
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
        ]);

        if (!is_array($terms) || is_wp_error($terms)) {
            return [];
        }

        $categories = [];
        foreach ($terms as $term) {
            if ($term instanceof \WP_Term) {
                $categories[] = [
                    'id' => (int) $term->term_id,
                    'name' => (string) $term->name,
                    'slug' => (string) $term->slug,
                    'parent_id' => $term->parent > 0 ? (int) $term->parent : null,
                    'count' => (int) $term->count,
                ];
            }
        }

        return $categories;
    }

    public function getDescendantIds(int $categoryId): array
    {
        $ids = [$categoryId];
        if (!function_exists('get_term_children')) {
            return $ids;
        }

        $children = get_term_children($categoryId, 'product_cat');
        if (is_array($children) && !is_wp_error($children)) {
            foreach ($children as $childId) {
                $ids[] = (int) $childId;
            }
        }

        return array_unique($ids);
    }
}
