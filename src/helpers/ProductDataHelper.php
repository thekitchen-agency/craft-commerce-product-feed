<?php

namespace thekitchenagency\craftcommerceproductfeed\helpers;

use Craft;
use craft\base\Element;
use craft\commerce\elements\Product;
use craft\commerce\elements\Variant;
use craft\commerce\Plugin as Commerce;
use thekitchenagency\craftcommerceproductfeed\CommerceProductFeed;

/**
 * Helper class to extract and normalize product data from Craft Commerce.
 */
class ProductDataHelper
{
    /**
     * Fetch all enabled Craft Commerce products.
     *
     * @return Product[]
     */
    public static function getProducts(): array
    {
        return Product::find()
            ->all();
    }

    /**
     * Extract normalized product data for feed formatting.
     *
     * @param Product $product
     * @return array|null
     */
    public static function extractProductData(Product $product): ?array
    {
        $settings = CommerceProductFeed::getInstance()->getSettings();

        // 1. Check if the product has been explicitly excluded via a mapped Lightswitch field
        if ($settings->excludeField) {
            $excludeValue = $product->getFieldValue($settings->excludeField);
            if ($excludeValue === true) {
                return null;
            }
        }

        // 2. Fetch variants
        $variants = $product->getVariants();
        $variantsArray = is_array($variants) ? $variants : iterator_to_array($variants);
        if (empty($variantsArray)) {
            return null;
        }

        $defaultVariant = $product->getDefaultVariant();
        if (!$defaultVariant) {
            $defaultVariant = $variantsArray[0] ?? null;
        }

        // 3. Resolve Description
        $description = '';
        if ($settings->descriptionField) {
            $description = (string)$product->getFieldValue($settings->descriptionField);
        }
        // Fallback: search layout for plain text or rich text fields if description is empty
        if (empty($description)) {
            $fieldLayout = $product->getFieldLayout();
            if ($fieldLayout) {
                foreach ($fieldLayout->getCustomFields() as $field) {
                    $className = get_class($field);
                    if (str_contains($className, 'PlainText') || str_contains($className, 'Field') || str_contains($className, 'CKEditorField')) {
                        $val = $product->getFieldValue($field->handle);
                        if ($val && is_string($val)) {
                            $description = $val;
                            break;
                        }
                    }
                }
            }
        }
        // Clean description HTML tags
        $description = trim(strip_tags($description));

        // 4. Resolve Images (Assets)
        $images = [];
        if ($settings->imageField) {
            $assets = $product->getFieldValue($settings->imageField)->all();
            foreach ($assets as $asset) {
                $images[] = $asset->getUrl();
            }
        }
        // Fallback: find first Asset field in product layout
        if (empty($images)) {
            $fieldLayout = $product->getFieldLayout();
            if ($fieldLayout) {
                foreach ($fieldLayout->getCustomFields() as $field) {
                    if (str_contains(get_class($field), 'Assets')) {
                        $assets = $product->getFieldValue($field->handle)->all();
                        foreach ($assets as $asset) {
                            $images[] = $asset->getUrl();
                        }
                        if (!empty($images)) {
                            break;
                        }
                    }
                }
            }
        }

        // 5. Resolve Categories
        $categories = [];
        if ($settings->categoryField) {
            $categoryElements = $product->getFieldValue($settings->categoryField)->all();
            foreach ($categoryElements as $cat) {
                $categories[] = [
                    'id' => $cat->id,
                    'name' => $cat->title,
                    'slug' => $cat->slug,
                    'parent' => $cat->parentId
                ];
            }
        }
        // Fallback: check if categories list is empty and try to find a Categories field
        if (empty($categories)) {
            $fieldLayout = $product->getFieldLayout();
            if ($fieldLayout) {
                foreach ($fieldLayout->getCustomFields() as $field) {
                    if (str_contains(get_class($field), 'Categories')) {
                        $categoryElements = $product->getFieldValue($field->handle)->all();
                        foreach ($categoryElements as $cat) {
                            $categories[] = [
                                'id' => $cat->id,
                                'name' => $cat->title,
                                'slug' => $cat->slug,
                                'parent' => $cat->parentId
                            ];
                        }
                        if (!empty($categories)) {
                            break;
                        }
                    }
                }
            }
        }

        // 6. Get primary store currency
        $primaryCurrency = 'CHF';
        try {
            $primaryCurrency = Commerce::getInstance()->getPaymentCurrencies()->getPrimaryPaymentCurrencyIso();
        } catch (\Throwable $e) {
            // Fallback if Commerce is not fully initialized or active
        }

        // 7. Extract Custom Mapped Fields (Brand, GTIN, MPN)
        $brand = self::getMappedFieldValue($product, $defaultVariant, $settings->brandField);
        $gtin = self::getMappedFieldValue($product, $defaultVariant, $settings->gtinField);
        $mpn = self::getMappedFieldValue($product, $defaultVariant, $settings->mpnField);

        // 8. Determine regular and sale prices
        $price = (float)$defaultVariant->price;
        $salePrice = (float)$defaultVariant->getSalePrice();
        // If the calculated sale price is identical or larger than the regular price, set sale_price to null
        if ($salePrice >= $price) {
            $salePrice = null;
        }

        // 9. Standard stock status
        $stockStatus = self::getStockStatus($defaultVariant);
        $stockQuantity = $defaultVariant->hasUnlimitedStock ? null : (int)$defaultVariant->stock;

        $data = [
            'id' => $product->id,
            'sku' => $defaultVariant->sku ?: '',
            'name' => $product->title,
            'description' => $description,
            'short_description' => '',
            'url' => $product->getUrl(),
            'price' => $price,
            'sale_price' => $salePrice,
            'currency' => $primaryCurrency,
            'stock_status' => $stockStatus,
            'stock_quantity' => $stockQuantity,
            'weight' => (float)$defaultVariant->weight,
            'dimensions' => [
                'length' => (float)$defaultVariant->length,
                'width' => (float)$defaultVariant->width,
                'height' => (float)$defaultVariant->height,
            ],
            'images' => $images,
            'categories' => $categories,
            'tags' => [], // Craft CMS entries do not have standard WC tags, can be added if custom fields map
            'attributes' => self::getElementAttributes($product),
            'variations' => self::getProductVariations($product, $variantsArray, $settings, $primaryCurrency),
            'brand' => $brand,
            'gtin' => $gtin,
            'mpn' => $mpn,
            'condition' => $settings->condition ?: 'new',
            'type' => count($variants) > 1 ? 'variable' : 'simple',
        ];

        return $data;
    }

    /**
     * Helper to resolve custom mapped field value checking both Variant and Product levels.
     */
    private static function getMappedFieldValue(Product $product, Variant $variant, string $fieldHandle): string
    {
        if (empty($fieldHandle)) {
            return '';
        }

        // First check variant level (e.g. unique GTIN per variant size/color)
        if ($variant->fieldExists($fieldHandle)) {
            $val = $variant->getFieldValue($fieldHandle);
            if ($val) {
                return trim((string)$val);
            }
        }

        // Then check parent product level
        if ($product->fieldExists($fieldHandle)) {
            $val = $product->getFieldValue($fieldHandle);
            if ($val) {
                return trim((string)$val);
            }
        }

        return '';
    }

    /**
     * Map variant stock status.
     */
    private static function getStockStatus(Variant $variant): string
    {
        if ($variant->hasUnlimitedStock) {
            return 'instock';
        }
        return ($variant->stock > 0) ? 'instock' : 'outofstock';
    }

    /**
     * Extract product variations.
     */
    private static function getProductVariations(Product $product, array $variants, $settings, string $currency): array
    {
        if (count($variants) <= 1) {
            return [];
        }

        $formatted = [];
        foreach ($variants as $variant) {
            $price = (float)$variant->price;
            $salePrice = (float)$variant->getSalePrice();
            if ($salePrice >= $price) {
                $salePrice = null;
            }

            // Resolve images for this variant
            $images = [];
            // Many commerce sites map images on variants or product layout.
            // Check if variant has assets field mapped in imageField
            if ($settings->imageField && $variant->fieldExists($settings->imageField)) {
                $assets = $variant->getFieldValue($settings->imageField)->all();
                foreach ($assets as $asset) {
                    $images[] = $asset->getUrl();
                }
            }

            // Resolve attributes (size, color, etc.) by listing custom variant fields
            $attributes = self::getElementAttributes($variant);

            $formatted[] = [
                'id' => $variant->id,
                'sku' => $variant->sku ?: '',
                'price' => $price,
                'sale_price' => $salePrice,
                'currency' => $currency,
                'stock_status' => self::getStockStatus($variant),
                'stock_quantity' => $variant->hasUnlimitedStock ? null : (int)$variant->stock,
                'weight' => (float)$variant->weight,
                'dimensions' => [
                    'length' => (float)$variant->length,
                    'width' => (float)$variant->width,
                    'height' => (float)$variant->height,
                ],
                'images' => $images,
                'attributes' => $attributes,
            ];
        }

        return $formatted;
    }

    /**
     * Extract key attributes (custom field values) for products and variants.
     */
    private static function getElementAttributes(Element $element): array
    {
        $attributes = [];
        $fieldLayout = $element->getFieldLayout();
        if (!$fieldLayout) {
            return [];
        }

        foreach ($fieldLayout->getCustomFields() as $field) {
            // Skip mappings fields we already handle explicitly
            $settings = CommerceProductFeed::getInstance()->getSettings();
            if (in_array($field->handle, [$settings->brandField, $settings->gtinField, $settings->mpnField, $settings->descriptionField, $settings->imageField, $settings->categoryField, $settings->excludeField])) {
                continue;
            }

            $val = $element->getFieldValue($field->handle);
            if (empty($val)) {
                continue;
            }

            // Clean values depending on field type
            $className = get_class($field);
            $options = [];

            if (str_contains($className, 'Assets') || str_contains($className, 'Categories') || str_contains($className, 'Entries') || str_contains($className, 'Users')) {
                // Relational fields
                $relElements = $val->all();
                foreach ($relElements as $el) {
                    $options[] = $el->title ?? $el->username ?? (string)$el;
                }
            } elseif (str_contains($className, 'MultiSelect') || str_contains($className, 'Checkboxes')) {
                // Multi values fields
                $options = (array)$val;
            } elseif (is_bool($val)) {
                $options[] = $val ? 'Yes' : 'No';
            } elseif (is_string($val) || is_numeric($val)) {
                $options[] = (string)$val;
            }

            if (!empty($options)) {
                $attributes[] = [
                    'name' => $field->handle,
                    'label' => $field->name ?: $field->handle,
                    'visible' => true,
                    'variation' => ($element instanceof Variant),
                    'options' => $options,
                ];
            }
        }

        return $attributes;
    }

    /**
     * Format price.
     */
    public static function formatPrice(float $price, string $currency = 'CHF'): string
    {
        return number_format($price, 2, '.', '') . ' ' . $currency;
    }

    /**
     * Get category hierarchy string.
     */
    public static function getCategoryHierarchy(array $categories): string
    {
        if (empty($categories)) {
            return '';
        }

        // We can sort them to build parent hierarchy or simply implode
        $names = array_column($categories, 'name');
        return implode(' > ', $names);
    }

    /**
     * Map availability status to WooCommerce/Google strings.
     */
    public static function getAvailabilityStatus(string $stockStatus): string
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
     * Get all products parsed and formatted.
     */
    public static function getAllProductsData(): array
    {
        $products = self::getProducts();
        $data = [];

        foreach ($products as $product) {
            $extracted = self::extractProductData($product);
            if ($extracted) {
                $data[] = $extracted;
            }
        }

        return $data;
    }
}
