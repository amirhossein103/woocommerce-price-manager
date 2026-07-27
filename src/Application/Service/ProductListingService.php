<?php declare(strict_types=1);

namespace WPM\Application\Service;

use WPM\Domain\Contract\ProductRepositoryInterface;
use WPM\Domain\Contract\CategoryRepositoryInterface;

defined('ABSPATH') || exit;

/**
 * Application Service orchestrating product listings, variation listings, and category queries.
 */
final class ProductListingService
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepo,
        private readonly CategoryRepositoryInterface $categoryRepo
    ) {
    }

    /**
     * List products with filtering, search, and pagination.
     *
     * @return array{data: array, meta: array}
     */
    public function listProducts(array $filters): array
    {
        if (!empty($filters['category'])) {
            $catId = (int) $filters['category'];
            $descendantIds = $this->categoryRepo->getDescendantIds($catId);
            $filters['category'] = $descendantIds;
        }

        $result = $this->productRepo->query($filters);

        $data = [];
        foreach ($result['products'] as $product) {
            $data[] = $product->toArray();
        }

        return [
            'data' => $data,
            'meta' => [
                'total' => $result['total'],
                'pages' => $result['pages'],
                'page' => (int) ($filters['page'] ?? 1),
                'per_page' => (int) ($filters['limit'] ?? 20),
            ],
        ];
    }

    /**
     * Get details for a single product.
     */
    public function getProduct(int $productId): ?array
    {
        $product = $this->productRepo->findById($productId);
        return $product?->toArray();
    }

    /**
     * Get variations for a variable product.
     */
    public function getVariations(int $productId): array
    {
        $variations = $this->productRepo->getVariations($productId);
        $data = [];
        foreach ($variations as $var) {
            $data[] = $var->toArray();
        }
        return $data;
    }

    /**
     * Get all product categories.
     */
    public function listCategories(): array
    {
        return $this->categoryRepo->getAll();
    }
}
