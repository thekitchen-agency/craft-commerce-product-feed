<?php

namespace thekitchenagency\craftcommerceproductfeed\console\controllers;

use craft\console\Controller;
use yii\helpers\Console;
use thekitchenagency\craftcommerceproductfeed\CommerceProductFeed;

/**
 * Console controller for managing Commerce Product Feeds.
 */
class FeedController extends Controller
{
    /**
     * Warm/regenerate feed caches.
     *
     * @param string|null $feedType The specific feed to generate ('google', 'openai', 'ricardo', 'csv', 'meta', 'pinterest', 'tiktok')
     * @return int
     */
    public function actionGenerate(?string $feedType = null): int
    {
        $this->stdout("Warming product feed caches...\n", Console::FG_YELLOW);
        
        $service = CommerceProductFeed::getInstance()->getFeedService();

        try {
            if ($feedType) {
                $this->stdout("Regenerating cache for feed type '{$feedType}'...\n");
                $service->warmCache($feedType);
            } else {
                $this->stdout("Regenerating cache for all enabled feeds...\n");
                $service->warmCache();
            }
            $this->stdout("Feed caches successfully warmed!\n", Console::FG_GREEN);
        } catch (\Throwable $e) {
            $this->stderr("Error: " . $e->getMessage() . "\n", Console::FG_RED);
            return Controller::EXIT_CODE_ERROR;
        }

        return Controller::EXIT_CODE_NORMAL;
    }

    /**
     * Clear feed caches.
     *
     * @param string|null $feedType The specific feed cache to clear
     * @return int
     */
    public function actionClear(?string $feedType = null): int
    {
        $this->stdout("Clearing product feed caches...\n", Console::FG_YELLOW);
        
        try {
            CommerceProductFeed::getInstance()->getFeedService()->clearCache($feedType);
            $this->stdout("Feed caches successfully cleared!\n", Console::FG_GREEN);
        } catch (\Throwable $e) {
            $this->stderr("Error: " . $e->getMessage() . "\n", Console::FG_RED);
            return Controller::EXIT_CODE_ERROR;
        }

        return Controller::EXIT_CODE_NORMAL;
    }

    /**
     * Display status diagnostics for all feeds.
     *
     * @return int
     */
    public function actionStatus(): int
    {
        $service = CommerceProductFeed::getInstance()->getFeedService();
        $stats = $service->getFeedStats();
        $urls = $service->getFeedUrls();

        $this->stdout("\n--- Commerce Product Feed CLI Status ---\n\n", Console::BOLD);

        foreach ($stats as $type => $info) {
            $statusLabel = $info['enabled'] ? "ENABLED" : "DISABLED";
            $statusColor = $info['enabled'] ? Console::FG_GREEN : Console::FG_RED;

            $this->stdout(strtoupper($type) . ": ", Console::BOLD);
            $this->stdout("{$statusLabel}\n", $statusColor);

            if ($info['enabled']) {
                $cachedLabel = $info['cached'] ? "Yes" : "No";
                $cachedColor = $info['cached'] ? Console::FG_GREEN : Console::FG_YELLOW;

                $this->stdout("  Cached: ");
                $this->stdout("{$cachedLabel}\n", $cachedColor);

                if ($info['cached']) {
                    $sizeKb = number_format($info['size'] / 1024, 2);
                    $this->stdout("  Cache Size: {$sizeKb} KB\n");
                    $this->stdout("  Last Updated: " . ($info['lastUpdated'] ? date('Y-m-d H:i:s', $info['lastUpdated']) : 'Never') . "\n");
                }

                $this->stdout("  Products Count: {$info['product_count']}\n");
                $this->stdout("  Feed URL: {$urls[$type]}\n");

                if (!empty($info['errors'])) {
                    $this->stdout("  Issues Found (" . count($info['errors']) . "):\n", Console::FG_RED);
                    foreach ($info['errors'] as $err) {
                        $this->stdout("    - {$err}\n", Console::FG_RED);
                    }
                } else {
                    $this->stdout("  Validation: ", Console::FG_GREEN);
                    $this->stdout("PASS (0 issues)\n", Console::FG_GREEN);
                }
            }
            $this->stdout("\n");
        }

        return Controller::EXIT_CODE_NORMAL;
    }
}
