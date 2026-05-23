<?php

namespace thekitchenagency\craftcommerceproductfeed\formatters;

use Craft;
use thekitchenagency\craftcommerceproductfeed\helpers\ProductDataHelper;

/**
 * Formatter for Google Shopping XML feeds.
 */
class GoogleFeedFormatter
{
    /**
     * Generate Google Shopping feed XML DOMDocument.
     *
     * @param array|null $productsData
     * @return \DOMDocument
     */
    public static function generateFeed(?array $productsData = null): \DOMDocument
    {
        if ($productsData === null) {
            $productsData = ProductDataHelper::getAllProductsData();
        }

        $siteName = Craft::$app->getSystemName();
        $siteUrl = \craft\helpers\UrlHelper::siteUrl();

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $rss = $dom->createElement('rss');
        $rss->setAttribute('xmlns:g', 'http://base.google.com/ns/1.0');
        $rss->setAttribute('version', '2.0');
        $dom->appendChild($rss);

        $channel = $dom->createElement('channel');
        $rss->appendChild($channel);

        $channel->appendChild($dom->createElement('title', htmlspecialchars($siteName)));
        $channel->appendChild($dom->createElement('link', $siteUrl));
        $channel->appendChild($dom->createElement('description', 'Product Feed for Google Shopping'));

        foreach ($productsData as $product) {
            self::formatProductDOM($product, $dom, $channel);
        }

        return $dom;
    }

    /**
     * Format individual product using DOMDocument.
     */
    private static function formatProductDOM(array $product, \DOMDocument $dom, \DOMElement $channel): ?\DOMElement
    {
        if (empty($product['name']) || empty($product['url'])) {
            return null;
        }

        $item = $dom->createElement('item');
        $channel->appendChild($item);

        $ns = 'http://base.google.com/ns/1.0';

        // Required fields
        $item->appendChild($dom->createElementNS($ns, 'g:id', htmlspecialchars((string)$product['id'])));
        $item->appendChild($dom->createElementNS($ns, 'g:title', htmlspecialchars($product['name'])));
        $item->appendChild($dom->createElementNS($ns, 'g:description', htmlspecialchars($product['description'] ?: $product['short_description'] ?: $product['name'])));
        $item->appendChild($dom->createElementNS($ns, 'g:link', htmlspecialchars($product['url'])));
        $item->appendChild($dom->createElementNS($ns, 'g:availability', self::getGoogleAvailability($product['stock_status'])));
        $item->appendChild($dom->createElementNS($ns, 'g:condition', $product['condition']));

        // Price formatting
        $price = $product['price'] ?? 0;
        $priceStr = ProductDataHelper::formatPrice($price, $product['currency']);
        $item->appendChild($dom->createElementNS($ns, 'g:price', htmlspecialchars($priceStr)));

        // Sale price if active
        if (!empty($product['sale_price']) && $product['sale_price'] > 0) {
            $salePriceStr = ProductDataHelper::formatPrice($product['sale_price'], $product['currency']);
            $item->appendChild($dom->createElementNS($ns, 'g:sale_price', htmlspecialchars($salePriceStr)));
        }

        // Images
        if (!empty($product['images'])) {
            $item->appendChild($dom->createElementNS($ns, 'g:image_link', htmlspecialchars($product['images'][0])));

            for ($i = 1; $i < count($product['images']) && $i <= 10; $i++) {
                $item->appendChild($dom->createElementNS($ns, 'g:additional_image_link', htmlspecialchars($product['images'][$i])));
            }
        }

        // Brand
        if (!empty($product['brand'])) {
            $item->appendChild($dom->createElementNS($ns, 'g:brand', htmlspecialchars($product['brand'])));
        }

        // GTIN
        if (!empty($product['gtin'])) {
            $item->appendChild($dom->createElementNS($ns, 'g:gtin', htmlspecialchars($product['gtin'])));
        }

        // MPN
        if (!empty($product['mpn'])) {
            $item->appendChild($dom->createElementNS($ns, 'g:mpn', htmlspecialchars($product['mpn'])));
        }

        // Product category tree
        if (!empty($product['categories'])) {
            $hierarchy = ProductDataHelper::getCategoryHierarchy($product['categories']);
            if ($hierarchy) {
                $item->appendChild($dom->createElementNS($ns, 'g:product_type', htmlspecialchars($hierarchy)));
            }
        }

        // Map common category keywords to Google Category path
        $googleCategory = self::getGoogleProductCategory($product['categories']);
        if ($googleCategory) {
            $item->appendChild($dom->createElementNS($ns, 'g:google_product_category', htmlspecialchars($googleCategory)));
        }

        // Weight
        if (!empty($product['weight'])) {
            $item->appendChild($dom->createElementNS($ns, 'g:shipping_weight', htmlspecialchars($product['weight'] . ' kg')));
        }

        // Item Group ID for variant groups
        if ($product['type'] === 'variable' && !empty($product['variations'])) {
            $item->appendChild($dom->createElementNS($ns, 'g:item_group_id', htmlspecialchars((string)$product['id'])));
        }

        // Extract structured attributes (e.g. Size, Color, Age Group)
        $ageGroup = self::getAttributeValue($product['attributes'], 'age_group');
        if ($ageGroup) {
            $item->appendChild($dom->createElementNS($ns, 'g:age_group', htmlspecialchars($ageGroup)));
        }

        $gender = self::getAttributeValue($product['attributes'], 'gender');
        if ($gender) {
            $item->appendChild($dom->createElementNS($ns, 'g:gender', htmlspecialchars($gender)));
        }

        $size = self::getAttributeValue($product['attributes'], 'size');
        if ($size) {
            $item->appendChild($dom->createElementNS($ns, 'g:size', htmlspecialchars($size)));
        }

        $color = self::getAttributeValue($product['attributes'], 'color');
        if ($color) {
            $item->appendChild($dom->createElementNS($ns, 'g:color', htmlspecialchars($color)));
        }

        $material = self::getAttributeValue($product['attributes'], 'material');
        if ($material) {
            $item->appendChild($dom->createElementNS($ns, 'g:material', htmlspecialchars($material)));
        }

        $pattern = self::getAttributeValue($product['attributes'], 'pattern');
        if ($pattern) {
            $item->appendChild($dom->createElementNS($ns, 'g:pattern', htmlspecialchars($pattern)));
        }

        // Add custom labels (up to 5)
        $customLabels = self::getCustomLabels($product);
        foreach ($customLabels as $index => $label) {
            $item->appendChild($dom->createElementNS($ns, 'g:custom_label_' . ($index), htmlspecialchars($label)));
        }

        return $item;
    }

    /**
     * Map stock status to Google availability values.
     */
    private static function getGoogleAvailability(string $stockStatus): string
    {
        switch ($stockStatus) {
            case 'instock':
                return 'in stock';
            case 'outofstock':
                return 'out of stock';
            default:
                return 'in stock';
        }
    }

    /**
     * Map categories to standard Google product category path.
     */
    private static function getGoogleProductCategory(array $categories): string
    {
        if (empty($categories)) {
            return '';
        }

        $mapping = [
            'clothing' => 'Apparel & Accessories > Clothing',
            'shoes' => 'Apparel & Accessories > Shoes',
            'bags' => 'Apparel & Accessories > Handbags, Wallets & Cases',
            'jewelry' => 'Apparel & Accessories > Jewelry',
            'watches' => 'Apparel & Accessories > Watches',
            'electronics' => 'Electronics',
            'computers' => 'Electronics > Computers',
            'phones' => 'Electronics > Communications > Telephony',
            'home' => 'Home & Garden',
            'furniture' => 'Home & Garden > Furniture',
            'kitchen' => 'Home & Garden > Kitchen & Dining',
            'sports' => 'Sporting Goods',
            'books' => 'Media > Books',
            'toys' => 'Toys & Games',
            'beauty' => 'Health & Beauty > Personal Care',
            'health' => 'Health & Beauty',
            'automotive' => 'Vehicles & Parts',
            'tools' => 'Hardware > Tools',
            'garden' => 'Home & Garden > Garden & Outdoor Living'
        ];

        foreach ($categories as $category) {
            $name = strtolower($category['name']);
            foreach ($mapping as $keyword => $googleCategory) {
                if (str_contains($name, $keyword)) {
                    return $googleCategory;
                }
            }
        }

        return '';
    }

    /**
     * Extract single value from custom attributes array.
     */
    private static function getAttributeValue(array $attributes, string $name): string
    {
        foreach ($attributes as $attr) {
            if (strtolower($attr['name']) === strtolower($name) && !empty($attr['options'])) {
                return $attr['options'][0];
            }
        }
        return '';
    }

    /**
     * Compile up to 5 custom labels (new, sale, backorder, etc.)
     */
    private static function getCustomLabels(array $product): array
    {
        $labels = [];

        if ($product['stock_status'] === 'outofstock') {
            $labels[] = 'Out of Stock';
        }

        if (!empty($product['sale_price']) && $product['sale_price'] > 0) {
            $labels[] = 'Sale';
        }

        return array_slice($labels, 0, 5);
    }

    /**
     * Output feed XML as string.
     *
     * @param array|null $productsData
     * @return string
     */
    public static function generateXml(?array $productsData = null): string
    {
        $dom = self::generateFeed($productsData);
        return $dom->saveXML();
    }

    /**
     * File name for feed.
     */
    public static function getFilename(): string
    {
        return 'google-feed.xml';
    }

    /**
     * MIME-type for HTTP response.
     */
    public static function getMimeType(): string
    {
        return 'application/xml';
    }

    /**
     * Validate generated XML schema compliance.
     *
     * @param string|\DOMDocument $xmlData
     * @return array List of error/warning strings
     */
    public static function validateFeed($xmlData): array
    {
        $errors = [];

        if (is_string($xmlData)) {
            libxml_use_internal_errors(true);
            $dom = new \DOMDocument();
            $dom->loadXML($xmlData);
            
            $libxmlErrors = libxml_get_errors();
            if (!empty($libxmlErrors)) {
                $errors[] = 'Invalid XML format';
                foreach ($libxmlErrors as $error) {
                    $errors[] = 'XML Error: ' . trim($error->message);
                }
                return $errors;
            }
        } else {
            $dom = $xmlData;
        }

        $channel = $dom->getElementsByTagName('channel')->item(0);
        if (!$channel) {
            $errors[] = 'Missing channel element';
            return $errors;
        }

        $items = $channel->getElementsByTagName('item');
        if ($items->length === 0) {
            $errors[] = 'No products found in Google feed';
            return $errors;
        }

        for ($i = 0; $i < $items->length; $i++) {
            $item = $items->item($i);
            $itemErrors = self::validateProductDOM($item, $i + 1);
            $errors = array_merge($errors, $itemErrors);
        }

        return $errors;
    }

    /**
     * Validate individual namespaced product elements.
     */
    private static function validateProductDOM(\DOMNode $item, int $index): array
    {
        $errors = [];
        $requiredFields = ['g:id', 'g:title', 'g:description', 'g:link', 'g:price', 'g:availability', 'g:condition'];
        $ns = 'http://base.google.com/ns/1.0';

        foreach ($requiredFields as $field) {
            $parts = explode(':', $field);
            $elementName = $parts[1];

            $elements = $item->getElementsByTagNameNS($ns, $elementName);
            if ($elements->length === 0 || empty(trim($elements->item(0)->textContent))) {
                $errors[] = "Product {$index}: Missing required field '{$field}'";
            }
        }

        // Check for at least one unique identifier (brand, GTIN, or MPN)
        $hasIdentifier = false;
        foreach (['brand', 'gtin', 'mpn'] as $field) {
            $elements = $item->getElementsByTagNameNS($ns, $field);
            if ($elements->length > 0 && !empty(trim($elements->item(0)->textContent))) {
                $hasIdentifier = true;
                break;
            }
        }

        if (!$hasIdentifier) {
            $errors[] = "Product {$index}: Missing required identifier (brand, gtin, or mpn) - at least one must have a value";
        }

        return $errors;
    }
}
