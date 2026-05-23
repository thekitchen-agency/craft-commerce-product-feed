<?php

namespace thekitchenagency\craftcommerceproductfeed\formatters;

use thekitchenagency\craftcommerceproductfeed\helpers\ProductDataHelper;

/**
 * Formatter for generic CSV product feeds.
 */
class CsvFeedFormatter
{
    /**
     * @var array Standard CSV column headers list
     */
    private static array $headers = [
        'id',
        'name',
        'description',
        'price',
        'currency',
        'sale_price',
        'url',
        'image_url',
        'brand',
        'category',
        'sku',
        'gtin',
        'mpn',
        'availability',
        'stock_quantity',
        'weight',
        'condition',
        'tags',
        'specifications'
    ];

    /**
     * Generate raw multi-dimensional CSV rows array (with headers).
     *
     * @param array|null $productsData
     * @return array
     */
    public static function generateFeed(?array $productsData = null): array
    {
        if ($productsData === null) {
            $productsData = ProductDataHelper::getAllProductsData();
        }

        $csvData = [];
        $csvData[] = self::$headers;

        foreach ($productsData as $product) {
            $row = self::formatProduct($product);
            if ($row) {
                $csvData[] = $row;
            }
        }

        return $csvData;
    }

    /**
     * Format individual product into positional CSV row.
     */
    private static function formatProduct(array $product): ?array
    {
        if (empty($product['name']) || empty($product['url'])) {
            return null;
        }

        $row = [];

        // Build standard row values mapped by static header indices
        $row['id'] = (string)$product['id'];
        $row['name'] = $product['name'];
        $row['description'] = $product['description'] ?: ($product['short_description'] ?: $product['name']);
        $row['price'] = number_format($product['price'], 2, '.', '');
        $row['currency'] = $product['currency'];
        
        $row['sale_price'] = !empty($product['sale_price']) && $product['sale_price'] > 0 
            ? number_format($product['sale_price'], 2, '.', '') 
            : '';

        $row['url'] = $product['url'];
        $row['image_url'] = !empty($product['images']) ? $product['images'][0] : '';
        $row['brand'] = $product['brand'] ?: '';
        $row['category'] = !empty($product['categories']) ? $product['categories'][0]['name'] : '';
        $row['sku'] = $product['sku'] ?: '';
        $row['gtin'] = $product['gtin'] ?: '';
        $row['mpn'] = $product['mpn'] ?: '';
        
        $row['availability'] = self::getCsvAvailability($product['stock_status']);
        $row['stock_quantity'] = $product['stock_quantity'] !== null ? (string)$product['stock_quantity'] : '';
        $row['weight'] = !empty($product['weight']) ? $product['weight'] . ' kg' : '';
        $row['condition'] = $product['condition'];
        
        $row['tags'] = !empty($product['tags']) 
            ? implode(';', array_column($product['tags'], 'name')) 
            : '';

        // Specifications list represented as a packed key:value semi-colon separated string
        $specs = self::getSpecifications($product['attributes']);
        $row['specifications'] = !empty($specs)
            ? implode(';', array_map(function($k, $v) {
                return $k . ':' . $v;
            }, array_keys($specs), $specs))
            : '';

        // Align values in same order as static headers
        $aligned = [];
        foreach (self::$headers as $h) {
            $aligned[] = $row[$h] ?? '';
        }

        return $aligned;
    }

    /**
     * Map stock status to CSV standard availability values.
     */
    private static function getCsvAvailability(string $stockStatus): string
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
     * Pack standard custom attributes lists.
     */
    private static function getSpecifications(array $attributes): array
    {
        $specs = [];
        foreach ($attributes as $attr) {
            if (empty($attr['options'])) {
                continue;
            }
            $label = $attr['label'] ?: $attr['name'];
            $cleanKey = str_replace([' ', '-'], '_', strtolower($label));
            $specs[$cleanKey] = $attr['options'][0];
        }
        return $specs;
    }

    /**
     * Generate file-ready CSV text string with UTF-8 BOM bytes.
     *
     * @param array|null $productsData
     * @return string
     */
    public static function generateCsv(?array $productsData = null): string
    {
        $csvData = self::generateFeed($productsData);
        
        $output = fopen('php://temp', 'r+');
        
        // Prepends UTF-8 BOM byte sequence (EF BB BF) for MS Excel compatibility
        fwrite($output, "\xEF\xBB\xBF");
        
        foreach ($csvData as $row) {
            fputcsv($output, $row, ',', '"');
        }
        
        rewind($output);
        $csvString = stream_get_contents($output);
        fclose($output);
        
        return $csvString;
    }

    /**
     * Filename for feed.
     */
    public static function getFilename(): string
    {
        return 'product-feed.csv';
    }

    /**
     * MIME-type for HTTP response.
     */
    public static function getMimeType(): string
    {
        return 'text/csv';
    }

    /**
     * Get defined headers list.
     */
    public static function getHeaders(): array
    {
        return self::$headers;
    }

    /**
     * Validate CSV structure and rules.
     *
     * @param array|string $csvData
     * @return array List of error strings
     */
    public static function validateFeed($csvData): array
    {
        $errors = [];

        if (is_string($csvData)) {
            // Remove BOM if present before parsing
            if (str_starts_with($csvData, "\xEF\xBB\xBF")) {
                $csvData = substr($csvData, 3);
            }
            
            $lines = str_getcsv($csvData, "\n");
            $rows = [];
            foreach ($lines as $line) {
                if (trim($line)) {
                    $rows[] = str_getcsv($line, ',');
                }
            }
            $csvData = $rows;
        }

        if (empty($csvData)) {
            $errors[] = 'CSV data is empty';
            return $errors;
        }

        if (!is_array($csvData[0])) {
            $errors[] = 'Invalid CSV format - first row must contain column headers';
            return $errors;
        }

        $headers = $csvData[0];
        $required = ['id', 'name', 'price', 'url'];

        foreach ($required as $r) {
            if (!in_array($r, $headers, true)) {
                $errors[] = "Missing required column header: {$r}";
            }
        }

        for ($i = 1; $i < count($csvData); $i++) {
            $row = $csvData[$i];
            $rowErrors = self::validateRow($row, $headers, $i);
            $errors = array_merge($errors, $rowErrors);
        }

        return $errors;
    }

    /**
     * Validate individual CSV row.
     */
    private static function validateRow(array $row, array $headers, int $rowIndex): array
    {
        $errors = [];

        if (count($row) !== count($headers)) {
            $errors[] = "Row {$rowIndex}: Column count mismatch (expected " . count($headers) . ", got " . count($row) . ")";
            return $errors;
        }

        $rowData = array_combine($headers, $row);
        $required = ['id', 'name', 'price', 'url'];

        foreach ($required as $field) {
            if (empty($rowData[$field])) {
                $errors[] = "Row {$rowIndex}: Missing required value '{$field}'";
            }
        }

        if (!empty($rowData['price']) && !is_numeric($rowData['price'])) {
            $errors[] = "Row {$rowIndex}: Price value must be numeric";
        }

        if (!empty($rowData['url']) && !filter_var($rowData['url'], FILTER_VALIDATE_URL)) {
            $errors[] = "Row {$rowIndex}: Invalid URL format";
        }

        return $errors;
    }

    /**
     * Compile rich feed statistics for CSV row sets.
     *
     * @param array $csvData
     * @return array
     */
    public static function getFeedStats(array $csvData): array
    {
        $headers = self::$headers;
        $stats = [
            'product_count' => max(0, count($csvData) - 1),
            'total_products' => max(0, count($csvData) - 1),
            'total_columns' => count($headers),
            'products_with_images' => 0,
            'products_with_brand' => 0,
            'products_with_gtin' => 0,
            'products_in_stock' => 0
        ];

        for ($i = 1; $i < count($csvData); $i++) {
            $row = $csvData[$i];
            if (count($row) !== count($headers)) {
                continue;
            }
            $rowData = array_combine($headers, $row);

            if (!empty($rowData['image_url'])) {
                $stats['products_with_images']++;
            }
            if (!empty($rowData['brand'])) {
                $stats['products_with_brand']++;
            }
            if (!empty($rowData['gtin'])) {
                $stats['products_with_gtin']++;
            }
            if ($rowData['availability'] === 'in_stock') {
                $stats['products_in_stock']++;
            }
        }

        return $stats;
    }
}
