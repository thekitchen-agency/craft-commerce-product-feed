<?php

namespace thekitchenagency\craftcommerceproductfeed\formatters;

use Craft;

/**
 * Formatter for Meta (Facebook & Instagram) XML feeds.
 */
class MetaFeedFormatter
{
    /**
     * Generate Meta Catalog feed XML using the Google XML structure.
     *
     * @param array|null $productsData
     * @return \DOMDocument
     */
    public static function generateFeed(?array $productsData = null): \DOMDocument
    {
        $dom = GoogleFeedFormatter::generateFeed($productsData);
        
        // Customize Title and Description for Meta context
        $channel = $dom->getElementsByTagName('channel')->item(0);
        if ($channel) {
            $titles = $channel->getElementsByTagName('title');
            if ($titles->length > 0) {
                $siteName = Craft::$app->getSystemName();
                $titles->item(0)->textContent = htmlspecialchars($siteName) . ' - Meta Product Catalog';
            }
            
            $descriptions = $channel->getElementsByTagName('description');
            if ($descriptions->length > 0) {
                $descriptions->item(0)->textContent = 'Product Feed for Facebook & Instagram Catalog';
            }
        }
        
        return $dom;
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
        return 'meta.xml';
    }

    /**
     * MIME-type for HTTP response.
     */
    public static function getMimeType(): string
    {
        return 'application/xml';
    }

    /**
     * Validate feed compliance.
     *
     * @param string|\DOMDocument $xmlData
     * @return array List of error/warning strings
     */
    public static function validateFeed($xmlData): array
    {
        // Meta adheres fully to the Google Shopping RSS specification
        return GoogleFeedFormatter::validateFeed($xmlData);
    }
}
