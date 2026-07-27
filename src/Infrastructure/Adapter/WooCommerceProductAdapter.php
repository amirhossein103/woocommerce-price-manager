<?php declare(strict_types=1);

namespace WPM\Infrastructure\Adapter;

use WPM\Domain\Contract\ProductRepositoryInterface;
use WPM\Domain\Entity\Product;
use WPM\Domain\Entity\Variation;
use WPM\Domain\ValueObject\PriceSnapshot;
use WPM\Domain\ValueObject\StockSnapshot;

defined('ABSPATH') || exit;

/**
 * Infrastructure Adapter wrapping WooCommerce CRUD API (Module 02 / Rule R-05 / Rule R-03).
 */
final class WooCommerceProductAdapter implements ProductRepositoryInterface
{
    public function query(array $filters): array
    {
        if (!function_exists('wc_get_products')) {
            return ['products' => [], 'total' => 0, 'pages' => 0];
        }

        $status = $filters['status'] ?? null;
        if (empty($status) || $status === 'null' || $status === 'undefined' || $status === 'all') {
            $status = ['publish', 'draft'];
        }

        $args = [
            'status' => $status,
            'type' => ['simple', 'variable'],
            'limit' => (int) ($filters['limit'] ?? 20),
            'page' => (int) ($filters['page'] ?? 1),
            'paginate' => true,
            'orderby' => (string) ($filters['orderby'] ?? 'title'),
            'order' => (string) ($filters['order'] ?? 'ASC'),
        ];

        if (!empty($filters['category']) && $filters['category'] !== 'null' && $filters['category'] !== 'undefined' && $filters['category'] !== 'all') {
            $args['category'] = (array) $filters['category'];
        }
        if (!empty($filters['stock_status']) && $filters['stock_status'] !== 'null' && $filters['stock_status'] !== 'undefined' && $filters['stock_status'] !== 'all') {
            $args['stock_status'] = (string) $filters['stock_status'];
        }
        if (!empty($filters['search']) && $filters['search'] !== 'null' && $filters['search'] !== 'undefined') {
            if (is_scalar($filters['search']) && ctype_digit((string) $filters['search'])) {
                $args['include'] = [(int) $filters['search']];
            } else {
                $args['s'] = (string) $filters['search'];
            }
        }

        $result = wc_get_products($args);
        if (!is_object($result) || !isset($result->products)) {
            return ['products' => [], 'total' => 0, 'pages' => 0];
        }

        $products = [];
        foreach ($result->products as $wcProduct) {
            if (is_object($wcProduct) && method_exists($wcProduct, 'get_id')) {
                $products[] = $this->mapWcProductToEntity($wcProduct);
            }
        }

        return [
            'products' => $products,
            'total' => (int) ($result->total ?? 0),
            'pages' => (int) ($result->max_num_pages ?? 0),
        ];
    }

    public function findById(int $id): ?Product
    {
        if (!function_exists('wc_get_product')) {
            return null;
        }

        $wcProduct = wc_get_product($id);
        if (!$wcProduct || !is_object($wcProduct) || !method_exists($wcProduct, 'get_id')) {
            return null;
        }

        return $this->mapWcProductToEntity($wcProduct);
    }

    public function getVariations(int $productId): array
    {
        if (!function_exists('wc_get_products')) {
            return [];
        }

        $args = [
            'type' => 'variation',
            'parent' => $productId,
            'limit' => -1,
            'return' => 'objects',
        ];

        $results = wc_get_products($args);
        if (!is_array($results)) {
            return [];
        }

        $variations = [];
        foreach ($results as $wcVariation) {
            if (is_object($wcVariation) && method_exists($wcVariation, 'get_id')) {
                $variations[] = $this->mapWcVariationToEntity($wcVariation);
            }
        }

        return $variations;
    }

    public function updatePrice(int $id, ?float $regularPrice, ?float $salePrice): void
    {
        if (!function_exists('wc_get_product')) {
            return;
        }

        $wcProduct = wc_get_product($id);
        if (!$wcProduct || !is_object($wcProduct) || !method_exists($wcProduct, 'save')) {
            return;
        }

        $wcProduct->set_regular_price($regularPrice !== null ? (string) $regularPrice : '');
        $wcProduct->set_sale_price($salePrice !== null ? (string) $salePrice : '');
        $wcProduct->save();
    }

    public function updateStock(int $id, bool $managed, ?int $quantity, ?string $status = null): void
    {
        if (!function_exists('wc_get_product')) {
            return;
        }

        $wcProduct = wc_get_product($id);
        if (!$wcProduct || !is_object($wcProduct) || !method_exists($wcProduct, 'save')) {
            return;
        }

        $wcProduct->set_manage_stock($managed);
        if ($managed) {
            $qty = $quantity ?? 0;
            $wcProduct->set_stock_quantity($qty);
            $wcProduct->set_stock_status($qty > 0 ? 'instock' : 'outofstock');
        } else {
            $wcProduct->set_stock_quantity('');
            if ($status !== null) {
                // Ensure status matches WooCommerce expected values
                $valid = ['instock', 'outofstock', 'onbackorder'];
                $wcProduct->set_stock_status(in_array($status, $valid) ? $status : 'instock');
            }
        }

        $wcProduct->save();
    }

    public function getPriceSnapshots(array $productIds): array
    {
        if (empty($productIds) || !function_exists('wc_get_products')) {
            return [];
        }

        $products = wc_get_products([
            'include' => $productIds,
            'limit' => count($productIds),
            'return' => 'objects',
        ]);

        if (!is_array($products)) {
            return [];
        }

        $snapshots = [];
        foreach ($products as $wcProduct) {
            if (is_object($wcProduct) && method_exists($wcProduct, 'get_id')) {
                $pid = (int) $wcProduct->get_id();
                $reg = $wcProduct->get_regular_price();
                $sale = $wcProduct->get_sale_price();
                $snapshots[$pid] = new PriceSnapshot(
                    $pid,
                    $reg !== '' && $reg !== null ? (float) $reg : null,
                    $sale !== '' && $sale !== null ? (float) $sale : null
                );
            }
        }

        return $snapshots;
    }

    public function getStockSnapshots(array $productIds): array
    {
        if (empty($productIds) || !function_exists('wc_get_products')) {
            return [];
        }

        $products = wc_get_products([
            'include' => $productIds,
            'limit' => count($productIds),
            'return' => 'objects',
        ]);

        if (!is_array($products)) {
            return [];
        }

        $snapshots = [];
        foreach ($products as $wcProduct) {
            if (is_object($wcProduct) && method_exists($wcProduct, 'get_id')) {
                $pid = (int) $wcProduct->get_id();
                $managed = (bool) $wcProduct->get_manage_stock();
                $qty = $wcProduct->get_stock_quantity();
                $status = (string) $wcProduct->get_stock_status();

                $snapshots[$pid] = new StockSnapshot(
                    $pid,
                    $managed,
                    $qty !== null && $qty !== '' ? (int) $qty : null,
                    $status
                );
            }
        }

        return $snapshots;
    }

    private function mapWcProductToEntity(object $wcProduct): Product
    {
        $id = (int) $wcProduct->get_id();
        $reg = $wcProduct->get_regular_price();
        $sale = $wcProduct->get_sale_price();
        $priceSnapshot = new PriceSnapshot(
            $id,
            $reg !== '' && $reg !== null ? (float) $reg : null,
            $sale !== '' && $sale !== null ? (float) $sale : null
        );

        $managed = (bool) $wcProduct->get_manage_stock();
        $qty = $wcProduct->get_stock_quantity();
        $status = (string) $wcProduct->get_stock_status();
        $stockSnapshot = new StockSnapshot(
            $id,
            $managed,
            $qty !== null && $qty !== '' ? (int) $qty : null,
            $status
        );

        $type = method_exists($wcProduct, 'get_type') ? (string) $wcProduct->get_type() : 'simple';
        $varCount = 0;
        if ($type === 'variable' && method_exists($wcProduct, 'get_children')) {
            $children = $wcProduct->get_children();
            $varCount = is_array($children) ? count($children) : 0;
        }

        $imageUrl = '';
        if (method_exists($wcProduct, 'get_image_id') && function_exists('wp_get_attachment_image_url')) {
            $imgId = (int) $wcProduct->get_image_id();
            if ($imgId > 0) {
                $url = wp_get_attachment_image_url($imgId, 'thumbnail');
                if (is_string($url)) {
                    $imageUrl = $url;
                }
            }
        }

        $permalink = method_exists($wcProduct, 'get_permalink') ? (string) $wcProduct->get_permalink() : '';
        $sku = method_exists($wcProduct, 'get_sku') ? (string) $wcProduct->get_sku() : '';
        $name = method_exists($wcProduct, 'get_name') ? (string) $wcProduct->get_name() : '';
        $prodStatus = method_exists($wcProduct, 'get_status') ? (string) $wcProduct->get_status() : 'publish';

        return new Product(
            $id,
            $name,
            $sku,
            $type,
            $prodStatus,
            $imageUrl,
            $permalink,
            $priceSnapshot,
            $stockSnapshot,
            $varCount
        );
    }

    private function mapWcVariationToEntity(object $wcVariation): Variation
    {
        $id = (int) $wcVariation->get_id();
        $parentId = method_exists($wcVariation, 'get_parent_id') ? (int) $wcVariation->get_parent_id() : 0;
        $attrs = method_exists($wcVariation, 'get_attributes') ? (array) $wcVariation->get_attributes() : [];

        $reg = $wcVariation->get_regular_price();
        $sale = $wcVariation->get_sale_price();
        $priceSnapshot = new PriceSnapshot(
            $id,
            $reg !== '' && $reg !== null ? (float) $reg : null,
            $sale !== '' && $sale !== null ? (float) $sale : null
        );

        $managed = (bool) $wcVariation->get_manage_stock();
        $qty = $wcVariation->get_stock_quantity();
        $status = (string) $wcVariation->get_stock_status();
        $stockSnapshot = new StockSnapshot(
            $id,
            $managed,
            $qty !== null && $qty !== '' ? (int) $qty : null,
            $status
        );

        return new Variation(
            $id,
            $parentId,
            $attrs,
            false,
            $priceSnapshot,
            $stockSnapshot
        );
    }
}
