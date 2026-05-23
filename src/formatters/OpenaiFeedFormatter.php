<?php

namespace thekitchenagency\craftcommerceproductfeed\formatters;

use thekitchenagency\craftcommerceproductfeed\helpers\ProductDataHelper;

/**
 * Formatter for OpenAI JSON feeds.
 */
class OpenaiFeedFormatter
{
    /**
     * Generate OpenAI feed data structure.
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
            'version' => '1.0',
            'last_updated' => gmdate('c'),
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
     * Format individual product for OpenAI.
     */
    private static function formatProduct(array $product): ?array
    {
        if (empty($product['name']) || empty($product['url'])) {
            return null;
        }

        $openaiProduct = [
            'id' => (string)$product['id'],
            'name' => $product['name'],
            'description' => $product['description'] ?: ($product['short_description'] ?: $product['name']),
            'url' => $product['url'],
            'price' => [
                'amount' => number_format($product['price'], 2, '.', ''),
                'currency' => $product['currency']
            ],
            'availability' => ProductDataHelper::getAvailabilityStatus($product['stock_status']),
            'sku' => $product['sku'],
            'condition' => $product['condition']
        ];

        // Mapped image
        if (!empty($product['images'])) {
            $openaiProduct['image_url'] = $product['images'][0];
            
            if (count($product['images']) > 1) {
                $openaiProduct['additional_image_urls'] = array_slice($product['images'], 1);
            }
        }

        // Brand
        if (!empty($product['brand'])) {
            $openaiProduct['brand'] = $product['brand'];
        }

        // Category
        if (!empty($product['categories'])) {
            $openaiProduct['category'] = $product['categories'][0]['name'];
        }

        // Sale price if active
        if (!empty($product['sale_price']) && $product['sale_price'] > 0) {
            $openaiProduct['sale_price'] = [
                'amount' => number_format($product['sale_price'], 2, '.', ''),
                'currency' => $product['currency']
            ];
        }

        // Mapped variations
        if (!empty($product['variations'])) {
            $openaiProduct['variants'] = self::formatVariants($product['variations']);
        }

        // Weight and dimensions attributes
        $attributes = [];
        if (!empty($product['weight'])) {
            $attributes['weight'] = $product['weight'] . 'kg';
        }
        
        if (!empty($product['dimensions'])) {
            $dims = $product['dimensions'];
            if ($dims['length'] && $dims['width'] && $dims['height']) {
                $attributes['dimensions'] = sprintf(
                    '%sx%sx%scm',
                    $dims['length'],
                    $dims['width'],
                    $dims['height']
                );
            }
        }

        if (!empty($attributes)) {
            $openaiProduct['attributes'] = $attributes;
        }

        // GTIN & MPN
        if (!empty($product['gtin'])) {
            $openaiProduct['gtin'] = $product['gtin'];
        }

        if (!empty($product['mpn'])) {
            $openaiProduct['mpn'] = $product['mpn'];
        }

        return $openaiProduct;
    }

    /**
     * Format nested variations.
     */
    private static function formatVariants(array $variations): array
    {
        $formatted = [];

        foreach ($variations as $variation) {
            $variant = [
                'id' => (string)$variation['id'],
                'price' => [
                    'amount' => number_format($variation['price'], 2, '.', ''),
                    'currency' => $variation['currency'] ?? 'CHF'
                ],
                'availability' => ProductDataHelper::getAvailabilityStatus($variation['stock_status']),
                'sku' => $variation['sku'] ?: ''
            ];

            if (!empty($variation['attributes'])) {
                $attrs = [];
                foreach ($variation['attributes'] as $attr) {
                    if (!empty($attr['options'])) {
                        $attrs[$attr['name']] = $attr['options'][0];
                    }
                }
                if (!empty($attrs)) {
                    $variant['attributes'] = $attrs;
                }
            }

            if (!empty($variation['sale_price']) && $variation['sale_price'] > 0) {
                $variant['sale_price'] = [
                    'amount' => number_format($variation['sale_price'], 2, '.', ''),
                    'currency' => $variation['currency'] ?? 'CHF'
                ];
            }

            if (!empty($variation['images'])) {
                $variant['image_url'] = $variation['images'][0];
            }

            $formatted[] = $variant;
        }

        return $formatted;
    }

    /**
     * Generate pretty printed JSON output.
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
        return 'openai-feed.json';
    }

    /**
     * MIME-type for HTTP response.
     */
    public static function getMimeType(): string
    {
        return 'application/json';
    }

    /**
     * Validate generated JSON structure.
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

        if (!isset($feedData['version'])) {
            $errors[] = 'Missing version field';
        }

        if (!isset($feedData['last_updated'])) {
            $errors[] = 'Missing last_updated field';
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
     * Validate individual product structure.
     */
    private static function validateProduct(array $product, int $index): array
    {
        $errors = [];
        $requiredFields = ['id', 'name', 'url', 'price'];

        foreach ($requiredFields as $field) {
            if (!isset($product[$field]) || empty($product[$field])) {
                $errors[] = "Product {$index}: Missing required field '{$field}'";
            }
        }

        if (isset($product['price']) && is_array($product['price'])) {
            if (!isset($product['price']['amount']) || !isset($product['price']['currency'])) {
                $errors[] = "Product {$index}: Invalid price structure";
            }
        }

        return $errors;
    }
}
