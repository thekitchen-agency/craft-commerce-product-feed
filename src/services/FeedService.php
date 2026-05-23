<?php

namespace thekitchenagency\craftcommerceproductfeed\services;

use Craft;
use craft\base\Component;
use thekitchenagency\craftcommerceproductfeed\CommerceProductFeed;
use thekitchenagency\craftcommerceproductfeed\helpers\ProductDataHelper;
use thekitchenagency\craftcommerceproductfeed\formatters\GoogleFeedFormatter;
use thekitchenagency\craftcommerceproductfeed\formatters\OpenaiFeedFormatter;
use thekitchenagency\craftcommerceproductfeed\formatters\RicardoFeedFormatter;
use thekitchenagency\craftcommerceproductfeed\formatters\CsvFeedFormatter;
use thekitchenagency\craftcommerceproductfeed\formatters\MetaFeedFormatter;
use thekitchenagency\craftcommerceproductfeed\formatters\PinterestFeedFormatter;
use thekitchenagency\craftcommerceproductfeed\formatters\TiktokFeedFormatter;

/**
 * Service to orchestrate feed generation, caching, and validation.
 */
class FeedService extends Component
{
    const CACHE_PREFIX = 'tka_product_feed_';
    const CACHE_DURATION = 900; // 15 minutes (900 seconds)

    /**
     * Get elegant public URLs for all feeds.
     *
     * @return array
     */
    public function getFeedUrls(): array
    {
        return [
            'openai' => \craft\helpers\UrlHelper::siteUrl('feeds/openai.json'),
            'google' => \craft\helpers\UrlHelper::siteUrl('feeds/google-feed.xml'),
            'ricardo' => \craft\helpers\UrlHelper::siteUrl('feeds/ricardo.json'),
            'csv' => \craft\helpers\UrlHelper::siteUrl('feeds/product-feed.csv'),
            'meta' => \craft\helpers\UrlHelper::siteUrl('feeds/meta.xml'),
            'pinterest' => \craft\helpers\UrlHelper::siteUrl('feeds/pinterest.xml'),
            'tiktok' => \craft\helpers\UrlHelper::siteUrl('feeds/tiktok.xml'),
            'products' => \craft\helpers\UrlHelper::siteUrl('feeds/products.json'),
        ];
    }

    /**
     * Fetch feed content, generating and caching if needed.
     *
     * @param string $feedType
     * @param bool $nocache Bypasses yii cache
     * @return string|null
     */
    public function getFeedContent(string $feedType, bool $nocache = false): ?string
    {
        $settings = CommerceProductFeed::getInstance()->getSettings();
        if (!in_array($feedType, $settings->enabledFeeds)) {
            return null;
        }

        $cacheKey = self::CACHE_PREFIX . $feedType;

        if (!$nocache) {
            $cached = Craft::$app->cache->get($cacheKey);
            if ($cached !== false) {
                return $cached;
            }
        }

        // Generate feed content
        $productsData = ProductDataHelper::getAllProductsData();
        $content = null;

        switch ($feedType) {
            case 'openai':
                $content = OpenaiFeedFormatter::generateJson($productsData);
                break;
            case 'google':
                $content = GoogleFeedFormatter::generateXml($productsData);
                break;
            case 'ricardo':
                $content = RicardoFeedFormatter::generateJson($productsData);
                break;
            case 'csv':
                $content = CsvFeedFormatter::generateCsv($productsData);
                break;
            case 'meta':
                $content = MetaFeedFormatter::generateXml($productsData);
                break;
            case 'pinterest':
                $content = PinterestFeedFormatter::generateXml($productsData);
                break;
            case 'tiktok':
                $content = TiktokFeedFormatter::generateXml($productsData);
                break;
        }

        if ($content !== null) {
            // Save to cache
            Craft::$app->cache->set($cacheKey, $content, self::CACHE_DURATION);
            
            // Record last updated timestamp
            $this->updateLastUpdatedTimestamp($feedType);
        }

        return $content;
    }

    /**
     * Expose a raw debug products JSON representation.
     */
    public function getRawProductsJson(bool $nocache = false): string
    {
        $cacheKey = self::CACHE_PREFIX . 'raw_products';

        if (!$nocache) {
            $cached = Craft::$app->cache->get($cacheKey);
            if ($cached !== false) {
                return $cached;
            }
        }

        $productsData = ProductDataHelper::getAllProductsData();
        $response = [
            'products' => $productsData,
            'meta' => [
                'generated_at' => date('c'),
                'total_products' => count($productsData)
            ]
        ];

        $json = json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        Craft::$app->cache->set($cacheKey, $json, self::CACHE_DURATION);

        return $json;
    }

    /**
     * Clear feed cache.
     *
     * @param string|null $feedType If null, clears all feed caches
     * @return void
     */
    public function clearCache(?string $feedType = null): void
    {
        if ($feedType) {
            Craft::$app->cache->delete(self::CACHE_PREFIX . $feedType);
        } else {
            foreach (['openai', 'google', 'ricardo', 'csv', 'meta', 'pinterest', 'tiktok', 'raw_products'] as $type) {
                Craft::$app->cache->delete(self::CACHE_PREFIX . $type);
            }
        }
    }

    /**
     * Forcefully warms cache by regenerating feed.
     *
     * @param string|null $feedType
     * @return void
     */
    public function warmCache(?string $feedType = null): void
    {
        if ($feedType) {
            $this->getFeedContent($feedType, true);
        } else {
            foreach (['openai', 'google', 'ricardo', 'csv', 'meta', 'pinterest', 'tiktok'] as $type) {
                $this->getFeedContent($type, true);
            }
            $this->getRawProductsJson(true);
        }
    }

    /**
     * Validate feed compliance schema.
     *
     * @param string $feedType
     * @return array List of warning and error messages
     */
    public function validateFeed(string $feedType): array
    {
        $content = $this->getFeedContent($feedType, true);
        if ($content === null) {
            return ["Feed '{$feedType}' is either disabled or could not be generated."];
        }

        switch ($feedType) {
            case 'openai':
                return OpenaiFeedFormatter::validateFeed($content);
            case 'google':
                return GoogleFeedFormatter::validateFeed($content);
            case 'ricardo':
                return RicardoFeedFormatter::validateFeed($content);
            case 'csv':
                return CsvFeedFormatter::validateFeed($content);
            case 'meta':
                return MetaFeedFormatter::validateFeed($content);
            case 'pinterest':
                return PinterestFeedFormatter::validateFeed($content);
            case 'tiktok':
                return TiktokFeedFormatter::validateFeed($content);
            default:
                return ["Invalid feed type: {$feedType}"];
        }
    }

    /**
     * Compile status statistics for enabled feeds.
     *
     * @return array
     */
    public function getFeedStats(): array
    {
        $settings = CommerceProductFeed::getInstance()->getSettings();
        $stats = [];
        $productsData = ProductDataHelper::getAllProductsData();

        foreach (['openai', 'google', 'ricardo', 'csv', 'meta', 'pinterest', 'tiktok'] as $type) {
            $enabled = in_array($type, $settings->enabledFeeds);
            $cacheKey = self::CACHE_PREFIX . $type;
            $cached = Craft::$app->cache->get($cacheKey);
            $lastUpdated = $settings->lastUpdated[$type] ?? 0;

            $feedStats = [
                'enabled' => $enabled,
                'cached' => ($cached !== false),
                'size' => $cached !== false ? strlen($cached) : 0,
                'lastUpdated' => $lastUpdated,
                'product_count' => 0,
                'errors' => []
            ];

            if ($enabled) {
                try {
                    switch ($type) {
                        case 'openai':
                            $feedData = OpenaiFeedFormatter::generateFeed($productsData);
                            $feedStats['product_count'] = count($feedData['products']);
                            $feedStats['errors'] = OpenaiFeedFormatter::validateFeed($feedData);
                            break;
                        case 'google':
                            $xml = GoogleFeedFormatter::generateXml($productsData);
                            $feedStats['product_count'] = substr_count($xml, '<item>');
                            $feedStats['errors'] = GoogleFeedFormatter::validateFeed($xml);
                            break;
                        case 'ricardo':
                            $feedData = RicardoFeedFormatter::generateFeed($productsData);
                            $ricardoStats = RicardoFeedFormatter::getFeedStats($feedData);
                            $feedStats['product_count'] = $ricardoStats['product_count'];
                            $feedStats['errors'] = RicardoFeedFormatter::validateFeed($feedData);
                            break;
                        case 'csv':
                            $csvData = CsvFeedFormatter::generateFeed($productsData);
                            $csvStats = CsvFeedFormatter::getFeedStats($csvData);
                            $feedStats['product_count'] = $csvStats['product_count'];
                            $feedStats['errors'] = CsvFeedFormatter::validateFeed($csvData);
                            break;
                        case 'meta':
                            $xml = MetaFeedFormatter::generateXml($productsData);
                            $feedStats['product_count'] = substr_count($xml, '<item>');
                            $feedStats['errors'] = MetaFeedFormatter::validateFeed($xml);
                            break;
                        case 'pinterest':
                            $xml = PinterestFeedFormatter::generateXml($productsData);
                            $feedStats['product_count'] = substr_count($xml, '<item>');
                            $feedStats['errors'] = PinterestFeedFormatter::validateFeed($xml);
                            break;
                        case 'tiktok':
                            $xml = TiktokFeedFormatter::generateXml($productsData);
                            $feedStats['product_count'] = substr_count($xml, '<item>');
                            $feedStats['errors'] = TiktokFeedFormatter::validateFeed($xml);
                            break;
                    }
                } catch (\Throwable $e) {
                    $feedStats['errors'] = ['Generation failed: ' . $e->getMessage()];
                }
            }

            $stats[$type] = $feedStats;
        }

        return $stats;
    }

    /**
     * Persist last updated timestamps in plugin settings.
     */
    private function updateLastUpdatedTimestamp(string $feedType): void
    {
        $plugin = CommerceProductFeed::getInstance();
        $settings = $plugin->getSettings();
        
        $lastUpdated = $settings->lastUpdated;
        $lastUpdated[$feedType] = time();
        $settings->lastUpdated = $lastUpdated;

        try {
            Craft::$app->getPlugins()->savePluginSettings($plugin, $settings->getAttributes());
        } catch (\Throwable $e) {
            Craft::error('Failed to save feed last updated timestamp settings: ' . $e->getMessage(), __METHOD__);
        }
    }

    /**
     * Validate a single product array against enabled feeds.
     *
     * @param array $productData
     * @return array Array mapping feedType => array of issues (empty means pass)
     */
    public function validateSingleProduct(array $productData): array
    {
        $settings = CommerceProductFeed::getInstance()->getSettings();
        $validation = [];

        foreach ($settings->enabledFeeds as $feedType) {
            $issues = [];
            
            // Simple validator rules matching the main formats
            if (in_array($feedType, ['google', 'meta', 'pinterest', 'tiktok'])) {
                // XML compliance checks
                if (empty($productData['name'])) {
                    $issues[] = "Missing product title";
                }
                if (empty($productData['description'])) {
                    $issues[] = "Missing description";
                }
                if (empty($productData['images'])) {
                    $issues[] = "Missing product images (Assets)";
                }
                if (empty($productData['url'])) {
                    $issues[] = "Missing product URL";
                }
                if (empty($productData['price'])) {
                    $issues[] = "Missing price";
                }
                if (empty($productData['brand']) && empty($productData['gtin']) && empty($productData['mpn'])) {
                    $issues[] = "Missing brand, gtin, or mpn identifier";
                }
            } elseif ($feedType === 'openai') {
                if (empty($productData['name'])) {
                    $issues[] = "Missing product name";
                }
                if (empty($productData['price'])) {
                    $issues[] = "Missing price";
                }
            } elseif ($feedType === 'ricardo') {
                // Ricardo json spec checks
                if (empty($productData['description'])) {
                    $issues[] = "Missing description";
                }
                if (empty($productData['images'])) {
                    $issues[] = "Missing images";
                }
            }

            $validation[$feedType] = $issues;
        }

        return $validation;
    }

    /**
     * Get HTML for sidebar feed validation card.
     *
     * @param \craft\commerce\elements\Product $product
     * @return string
     */
    public function getProductSidebarValidationHtml(\craft\commerce\elements\Product $product): string
    {
        $productData = ProductDataHelper::extractProductData($product);
        
        $html = '';
        $html .= '<fieldset style="margin-top: 24px; padding-top: 16px; border-top: 1px solid #e3e5e8;">';
        $html .= '<legend class="h6" style="font-weight: 600; margin-bottom: 12px; font-size: 13px;">Feeds Compliance</legend>';
        $html .= '<div class="meta">';
        
        if ($productData === null) {
            $html .= '<div class="note" style="margin: 0; padding: 10px; font-size: 12px;">';
            $html .= '⚠️ <strong>Product Excluded</strong><br>This product has no active variants or is explicitly excluded from feed generation.';
            $html .= '</div>';
            $html .= '</div>';
            $html .= '</fieldset>';
            return $html;
        }

        $validation = $this->validateSingleProduct($productData);
        $html .= '<ul style="list-style: none; padding: 0; margin: 0; font-size: 13px; line-height: 1.8;">';
        
        $hasIssuesGlobal = false;
        foreach ($validation as $feedType => $issues) {
            $label = '';
            switch ($feedType) {
                case 'google': $label = 'Google Shopping'; break;
                case 'openai': $label = 'OpenAI Merchant'; break;
                case 'ricardo': $label = 'Ricardo.ch'; break;
                case 'csv': $label = 'Generic CSV'; break;
                case 'meta': $label = 'Meta Catalog'; break;
                case 'pinterest': $label = 'Pinterest Catalog'; break;
                case 'tiktok': $label = 'TikTok Shop'; break;
                default: $label = ucfirst($feedType); break;
            }

            if (empty($issues)) {
                $html .= '<li style="color: #3b8070; font-weight: 500; display: flex; align-items: center; gap: 6px;">';
                $html .= '<span style="color: #3b8070; font-weight: bold; font-size: 14px;">✓</span> ' . htmlspecialchars($label) . ': <span style="font-size: 11px; opacity: 0.85; font-weight: normal;">Pass</span>';
                $html .= '</li>';
            } else {
                $hasIssuesGlobal = true;
                $html .= '<li style="color: #da5a47; font-weight: 600; display: flex; flex-direction: column; margin-bottom: 6px;">';
                $html .= '<span style="display: flex; align-items: center; gap: 6px;">';
                $html .= '<span style="color: #da5a47; font-size: 14px;">⚠️</span> ' . htmlspecialchars($label) . ': <span style="font-size: 11px; font-weight: normal; opacity: 0.85;">' . count($issues) . ' issue(s)</span>';
                $html .= '</span>';
                $html .= '<ul style="list-style: disc; padding-left: 20px; margin: 2px 0 4px 0; font-size: 11px; font-weight: normal; color: #606d7b; line-height: 1.4;">';
                foreach ($issues as $issue) {
                    $html .= '<li>' . htmlspecialchars($issue) . '</li>';
                }
                $html .= '</ul>';
                $html .= '</li>';
            }
        }
        
        $html .= '</ul>';

        if (!$hasIssuesGlobal) {
            $html .= '<div class="note success" style="margin-top: 12px; padding: 8px 10px; font-size: 11px; line-height: 1.4; color: #3b8070; background: #eef7f2; border: 1px solid #d1ebd9; border-radius: 3px;">';
            $html .= 'All enabled channels pass compliance check.';
            $html .= '</div>';
        } else {
            $html .= '<div class="note warning" style="margin-top: 12px; padding: 8px 10px; font-size: 11px; line-height: 1.4; color: #a04030; background: #fdf3f2; border: 1px solid #f9dcd8; border-radius: 3px;">';
            $html .= 'Fix the highlighted issues above to pass feed requirements.';
            $html .= '</div>';
        }

        $html .= '</div>';
        $html .= '</fieldset>';
        
        return $html;
    }
}
