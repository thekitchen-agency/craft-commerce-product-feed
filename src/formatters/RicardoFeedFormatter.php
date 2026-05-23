<?php

namespace thekitchenagency\craftcommerceproductfeed\formatters;

use thekitchenagency\craftcommerceproductfeed\helpers\ProductDataHelper;

/**
 * Formatter for Ricardo.ch JSON feeds.
 */
class RicardoFeedFormatter
{
    /**
     * Generate Ricardo feed data structure.
     *
     * @param array|null $productsData
     * @return array
     */
    public static function generateFeed(?array $productsData = null): array
    {
        if ($productsData === null) {
            $productsData = ProductDataHelper::getAllProductsData();
        }

        $feedData = [
            'products' => []
        ];

        foreach ($productsData as $product) {
            $formatted = self::formatProduct($product);
            if ($formatted) {
                $feedData['products'][] = $formatted;
            }
        }

        return $feedData;
    }

    /**
     * Format individual product for Ricardo.ch.
     */
    private static function formatProduct(array $product): ?array
    {
        if (empty($product['name']) || empty($product['url'])) {
            return null;
        }

        $ricardoProduct = [
            'id' => (string)$product['id'],
            'title' => $product['name'],
            'description' => $product['description'] ?: ($product['short_description'] ?: $product['name']),
            'url' => $product['url'],
            'price' => (float)$product['price'],
            'currency' => $product['currency'],
            'availability' => self::getRicardoAvailability($product['stock_status']),
            'condition' => $product['condition'],
            'sku' => $product['sku'],
            'weight' => !empty($product['weight']) ? (float)$product['weight'] : null,
            'shipping_costs' => 0.0 // Default to free shipping
        ];

        // GTIN
        if (!empty($product['gtin'])) {
            $ricardoProduct['gtin'] = $product['gtin'];
        }

        // Brand
        if (!empty($product['brand'])) {
            $ricardoProduct['brand'] = $product['brand'];
        }

        // Primary Category name
        if (!empty($product['categories'])) {
            $ricardoProduct['category'] = $product['categories'][0]['name'];
        }

        // Images
        if (!empty($product['images'])) {
            $ricardoProduct['image_url'] = $product['images'][0];
            
            if (count($product['images']) > 1) {
                $ricardoProduct['additional_images'] = array_slice($product['images'], 1);
            }
        }

        // Stock quantity
        if ($product['stock_quantity'] !== null) {
            $ricardoProduct['stock_quantity'] = (int)$product['stock_quantity'];
        }

        // Sale price
        if (!empty($product['sale_price']) && $product['sale_price'] > 0) {
            $ricardoProduct['sale_price'] = (float)$product['sale_price'];
        }

        // Specifications
        $specs = self::getSpecifications($product['attributes']);
        if (!empty($specs)) {
            $ricardoProduct['specifications'] = $specs;
        }

        // Dimensions
        if (!empty($product['dimensions'])) {
            $dims = $product['dimensions'];
            if ($dims['length'] && $dims['width'] && $dims['height']) {
                $ricardoProduct['dimensions'] = [
                    'length' => (float)$dims['length'],
                    'width' => (float)$dims['width'],
                    'height' => (float)$dims['height'],
                    'unit' => 'cm'
                ];
            }
        }

        // MPN
        if (!empty($product['mpn'])) {
            $ricardoProduct['mpn'] = $product['mpn'];
        }

        // Product type (variable/simple)
        $ricardoProduct['product_type'] = $product['type'];

        // Tags
        if (!empty($product['tags'])) {
            $ricardoProduct['tags'] = array_column($product['tags'], 'name');
        }

        // Shipping and Return structures
        $ricardoProduct['shipping'] = self::getShippingInfo($product);
        $ricardoProduct['return_policy'] = self::getReturnPolicy();

        return $ricardoProduct;
    }

    /**
     * Map availability status to Ricardo values.
     */
    private static function getRicardoAvailability(string $stockStatus): string
    {
        switch ($stockStatus) {
            case 'instock':
                return 'in_stock';
            case 'outofstock':
                return 'out_of_stock';
            default:
                return 'in_stock';
        }
    }

    /**
     * Map element custom attributes to standard Ricardo specifications keywords.
     */
    private static function getSpecifications(array $attributes): array
    {
        $specs = [];

        foreach ($attributes as $attr) {
            if (empty($attr['options'])) {
                continue;
            }

            $name = strtolower($attr['name']);
            $val = $attr['options'][0];

            switch ($name) {
                case 'size':
                case 'groesse':
                case 'größe':
                    $specs['size'] = $val;
                    break;
                case 'color':
                case 'farbe':
                    $specs['color'] = $val;
                    break;
                case 'material':
                    $specs['material'] = $val;
                    break;
                case 'gender':
                case 'geschlecht':
                    $specs['gender'] = $val;
                    break;
                case 'age_group':
                case 'altersgruppe':
                    $specs['age_group'] = $val;
                    break;
                case 'brand':
                case 'marke':
                    $specs['brand'] = $val;
                    break;
                case 'model':
                case 'modell':
                    $specs['model'] = $val;
                    break;
                case 'warranty':
                case 'garantie':
                    $specs['warranty'] = $val;
                    break;
                default:
                    $cleanKey = str_replace([' ', '-'], '_', $name);
                    $specs[$cleanKey] = $val;
                    break;
            }
        }

        return $specs;
    }

    /**
     * Compile standard shipping info profile.
     */
    private static function getShippingInfo(array $product): array
    {
        $shipping = [
            'free_shipping' => true,
            'shipping_time' => '1-3 business days',
            'shipping_methods' => ['standard', 'express']
        ];

        if (!empty($product['weight'])) {
            $shipping['weight'] = $product['weight'] . ' kg';
        }

        return $shipping;
    }

    /**
     * Standard return policy profile.
     */
    private static function getReturnPolicy(): array
    {
        return [
            'return_period' => '30 days',
            'return_conditions' => 'Original packaging, unused condition',
            'return_shipping' => 'Customer pays return shipping'
        ];
    }

    /**
     * Output pretty JSON feed string.
     *
     * @param array|null $productsData
     * @return string
     */
    public static function generateJson(?array $productsData = null): string
    {
        $feedData = self::generateFeed($productsData);
        return json_encode($feedData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Filename for feed.
     */
    public static function getFilename(): string
    {
        return 'ricardo-feed.json';
    }

    /**
     * MIME-type for HTTP response.
     */
    public static function getMimeType(): string
    {
        return 'application/json';
    }

    /**
     * Validate Ricardo JSON format and constraints.
     *
     * @param array|string $feedData
     * @return array List of error strings
     */
    public static function validateFeed($feedData): array
    {
        $errors = [];

        if (is_string($feedData)) {
            $decoded = json_decode($feedData, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $errors[] = 'Invalid JSON format: ' . json_last_error_msg();
                return $errors;
            }
            $feedData = $decoded;
        }

        if (!isset($feedData['products']) || !is_array($feedData['products'])) {
            $errors[] = 'Missing or invalid products array';
            return $errors;
        }

        foreach ($feedData['products'] as $index => $product) {
            $productErrors = self::validateProduct($product, $index + 1);
            $errors = array_merge($errors, $productErrors);
        }

        return $errors;
    }

    /**
     * Validate individual product.
     */
    private static function validateProduct(array $product, int $index): array
    {
        $errors = [];
        $required = ['id', 'title', 'url', 'price', 'currency', 'availability'];

        foreach ($required as $field) {
            if (!isset($product[$field]) || empty($product[$field])) {
                $errors[] = "Product {$index}: Missing required field '{$field}'";
            }
        }

        if (isset($product['price']) && !is_numeric($product['price'])) {
            $errors[] = "Product {$index}: Price must be numeric";
        }

        if (isset($product['currency']) && $product['currency'] !== 'CHF') {
            $errors[] = "Product {$index}: Currency should be CHF for Swiss market";
        }

        if (!isset($product['gtin']) || empty($product['gtin'])) {
            $errors[] = "Product {$index}: GTIN is strongly recommended for Ricardo.ch";
        }

        return $errors;
    }

    /**
     * Compile rich feed statistics.
     *
     * @param array $feedData
     * @return array
     */
    public static function getFeedStats(array $feedData): array
    {
        $stats = [
            'product_count' => count($feedData['products']),
            'total_products' => count($feedData['products']),
            'products_with_gtin' => 0,
            'products_with_images' => 0,
            'products_in_stock' => 0,
            'products_on_sale' => 0
        ];

        foreach ($feedData['products'] as $product) {
            if (!empty($product['gtin'])) {
                $stats['products_with_gtin']++;
            }
            if (!empty($product['image_url'])) {
                $stats['products_with_images']++;
            }
            if ($product['availability'] === 'in_stock') {
                $stats['products_in_stock']++;
            }
            if (!empty($product['sale_price'])) {
                $stats['products_on_sale']++;
            }
        }

        return $stats;
    }
}
