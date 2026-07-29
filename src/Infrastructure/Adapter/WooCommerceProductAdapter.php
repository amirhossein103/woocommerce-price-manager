<?php declare(strict_types=1);

namespace WPM\Infrastructure\Adapter;

use WPM\Domain\Contract\ProductRepositoryInterface;
use WPM\Domain\Entity\Product;
use WPM\Domain\Entity\Variation;
use WPM\Domain\ValueObject\PriceSnapshot;
use WPM\Domain\ValueObject\StockSnapshot;
use WPM\Domain\Exception\DomainException;

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
            $catFilters = (array) $filters['category'];
            $catSlugs = [];
            foreach ($catFilters as $catVal) {
                if (is_numeric($catVal)) {
                    if (function_exists('get_term')) {
                        $term = get_term((int) $catVal, 'product_cat');
                        if ($term instanceof \WP_Term && !is_wp_error($term)) {
                            $catSlugs[] = $term->slug;
                        }
                    }
                } elseif (is_string($catVal) && $catVal !== '') {
                    $catSlugs[] = $catVal;
                }
            }
            if (!empty($catSlugs)) {
                $args['category'] = $catSlugs;
            }
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

        $parent = function_exists('wc_get_product') ? wc_get_product($productId) : null;
        $parentDefaultAttributes = $parent && method_exists($parent, 'get_default_attributes') ? $parent->get_default_attributes() : [];

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
                $variations[] = $this->mapWcVariationToEntity($wcVariation, $parentDefaultAttributes);
            }
        }

        return $variations;
    }

    public function updatePrice(int $id, ?float $regularPrice, ?float $salePrice, ?string $dateFrom = null, ?string $dateTo = null): void
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
        if ($dateFrom !== null && method_exists($wcProduct, 'set_date_on_sale_from')) {
            $wcProduct->set_date_on_sale_from($dateFrom !== '' ? $dateFrom : '');
        }
        if ($dateTo !== null && method_exists($wcProduct, 'set_date_on_sale_to')) {
            $wcProduct->set_date_on_sale_to($dateTo !== '' ? $dateTo : '');
        }
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

    public function setDefaultVariation(int $parentId, int $variationId): void
    {
        if (!function_exists('wc_get_product')) {
            return;
        }

        $parent = wc_get_product($parentId);
        $variation = wc_get_product($variationId);

        if (!$parent || !$parent->is_type('variable') || !$variation || !$variation->is_type('variation')) {
            throw new DomainException("Invalid parent or variation product ID.");
        }

        // Make sure the variation actually belongs to this parent
        if ($variation->get_parent_id() !== $parentId) {
            throw new DomainException("Variation does not belong to the specified parent product.");
        }

        $variationAttributes = method_exists($variation, 'get_attributes') ? $variation->get_attributes() : [];
        if (!empty($variationAttributes)) {
            // 1. Force update post meta directly to bypass any WooCommerce change-detection bugs
            update_post_meta($parentId, '_default_attributes', $variationAttributes);
            
            // 2. Also update the WC_Product object to ensure hooks/cache clear routines fire
            $parent->set_default_attributes($variationAttributes);
            $parent->save();
            
            // 3. Aggressively clear all possible caches for this product
            if (function_exists('wc_delete_product_transients')) {
                wc_delete_product_transients($parentId);
            }
            if (function_exists('clean_post_cache')) {
                clean_post_cache($parentId);
            }
        }
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
                $reg = method_exists($wcProduct, 'get_regular_price') ? $wcProduct->get_regular_price('edit') : '';
                $sale = method_exists($wcProduct, 'get_sale_price') ? $wcProduct->get_sale_price('edit') : '';
                [$dateFrom, $dateTo] = $this->extractSaleDates($wcProduct);
                $snapshots[$pid] = new PriceSnapshot(
                    $pid,
                    $reg !== '' && $reg !== null ? (float) $reg : null,
                    $sale !== '' && $sale !== null ? (float) $sale : null,
                    null,
                    $dateFrom,
                    $dateTo
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

    public function resolveToOperableIds(array $productIds, bool $forStock = false): array
    {
        if (empty($productIds) || !function_exists('wc_get_product')) {
            return [];
        }

        $resolved = [];
        foreach ($productIds as $id) {
            $product = wc_get_product($id);
            if (!$product) {
                continue;
            }

            if ($product->is_type('variable')) {
                if ($forStock && $product->get_manage_stock()) {
                    $resolved[] = $id;
                } else {
                    $children = $product->get_children();
                    if (is_array($children)) {
                        foreach ($children as $childId) {
                            $resolved[] = $childId;
                        }
                    }
                }
            } else {
                $resolved[] = $id;
            }
        }

        return array_unique($resolved);
    }

    private function mapWcProductToEntity(object $wcProduct): Product
    {
        $id = (int) $wcProduct->get_id();
        $reg = method_exists($wcProduct, 'get_regular_price') ? $wcProduct->get_regular_price('edit') : '';
        $sale = method_exists($wcProduct, 'get_sale_price') ? $wcProduct->get_sale_price('edit') : '';
        [$dateFrom, $dateTo] = $this->extractSaleDates($wcProduct);
        $priceSnapshot = new PriceSnapshot(
            $id,
            $reg !== '' && $reg !== null ? (float) $reg : null,
            $sale !== '' && $sale !== null ? (float) $sale : null,
            null,
            $dateFrom,
            $dateTo
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

    private function mapWcVariationToEntity(object $wcVariation, array $parentDefaultAttributes = []): Variation
    {
        $id = (int) $wcVariation->get_id();
        $parentId = (int) $wcVariation->get_parent_id();
        
        $rawAttrs = method_exists($wcVariation, 'get_attributes') ? (array) $wcVariation->get_attributes() : [];
        $attrs = [];
        
        $isDefault = false;
        if (!empty($parentDefaultAttributes) && !empty($rawAttrs)) {
            $matches = true;
            foreach ($rawAttrs as $rawKey => $rawValue) {
                $cleanKey = str_starts_with($rawKey, 'attribute_') ? substr($rawKey, 10) : $rawKey;
                
                // Find matching key in parent defaults with robust decoding
                $defValue = null;
                $found = false;
                foreach ($parentDefaultAttributes as $pdKey => $pdVal) {
                    $pdCleanKey = str_starts_with($pdKey, 'attribute_') ? substr($pdKey, 10) : $pdKey;
                    if ($pdCleanKey === $cleanKey || urldecode($pdCleanKey) === urldecode($cleanKey) || urldecode(urldecode($pdCleanKey)) === urldecode(urldecode($cleanKey))) {
                        $defValue = $pdVal;
                        $found = true;
                        break;
                    }
                }
                
                // If the variation has a specific value set (not 'any')
                if ($rawValue !== '') {
                    if ($found) {
                        // Compare the values robustly
                        if ($rawValue !== $defValue && urldecode($rawValue) !== urldecode($defValue) && urldecode(urldecode((string)$rawValue)) !== urldecode(urldecode((string)$defValue))) {
                            $matches = false;
                            break;
                        }
                    }
                }
            }
            if ($matches) {
                $isDefault = true;
            }
        }

        foreach ($rawAttrs as $key => $value) {
            $taxKey = str_starts_with($key, 'attribute_') ? substr($key, 10) : $key;
            $decodedValue = urldecode(urldecode((string)$value));
            
            $label = $taxKey;
            $valueName = $decodedValue;
            
            if (function_exists('wc_attribute_label')) {
                $lbl = wc_attribute_label($taxKey);
                if ($lbl && $lbl !== $taxKey) {
                    $label = $lbl;
                }
            }
            
            if ($label === $taxKey && str_starts_with($taxKey, 'pa_') && function_exists('get_taxonomy')) {
                $tax = get_taxonomy($taxKey);
                if ($tax && isset($tax->labels->singular_name)) {
                    $label = $tax->labels->singular_name;
                }
            }

            if (str_starts_with($taxKey, 'pa_') && function_exists('get_term_by')) {
                $term = get_term_by('slug', $decodedValue, $taxKey);
                
                if (!$term || is_wp_error($term)) {
                    $term = get_term_by('slug', $value, $taxKey);
                }
                
                if ($term instanceof \WP_Term && !is_wp_error($term)) {
                    $valueName = $term->name;
                }
            }
            
            $attrs[$label] = $valueName;
        }

        $reg = method_exists($wcVariation, 'get_regular_price') ? $wcVariation->get_regular_price('edit') : '';
        $sale = method_exists($wcVariation, 'get_sale_price') ? $wcVariation->get_sale_price('edit') : '';
        [$dateFrom, $dateTo] = $this->extractSaleDates($wcVariation);
        $priceSnapshot = new PriceSnapshot(
            $id,
            $reg !== '' && $reg !== null ? (float) $reg : null,
            $sale !== '' && $sale !== null ? (float) $sale : null,
            null,
            $dateFrom,
            $dateTo
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
            $isDefault,
            $priceSnapshot,
            $stockSnapshot
        );
    }

    private function extractSaleDates(object $wcObj): array
    {
        $fromObj = method_exists($wcObj, 'get_date_on_sale_from') ? $wcObj->get_date_on_sale_from('edit') : null;
        $dateFrom = $fromObj !== null && is_object($fromObj) && method_exists($fromObj, 'date') ? $fromObj->date('Y-m-d') : (is_string($fromObj) && $fromObj !== '' ? $fromObj : null);
        $toObj = method_exists($wcObj, 'get_date_on_sale_to') ? $wcObj->get_date_on_sale_to('edit') : null;
        $dateTo = $toObj !== null && is_object($toObj) && method_exists($toObj, 'date') ? $toObj->date('Y-m-d') : (is_string($toObj) && $toObj !== '' ? $toObj : null);
        return [$dateFrom, $dateTo];
    }
}
