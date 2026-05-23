<?php

namespace thekitchenagency\craftcommerceproductfeed;

use Craft;
use craft\base\Model;
use craft\base\Plugin;
use craft\commerce\elements\Product;
use craft\commerce\elements\Variant;
use craft\events\RegisterUrlRulesEvent;
use craft\events\DefineHtmlEvent;
use craft\web\UrlManager;
use yii\base\Event;
use thekitchenagency\craftcommerceproductfeed\models\Settings;
use thekitchenagency\craftcommerceproductfeed\services\FeedService;

/**
 * Commerce Product Feed plugin
 *
 * @method static CommerceProductFeed getInstance()
 * @method Settings getSettings()
 * @property FeedService $feedService
 * @author thekitchen.agency
 * @copyright thekitchen.agency
 * @license https://craftcms.github.io/license/ Craft License
 */
class CommerceProductFeed extends Plugin
{
    public string $schemaVersion = '1.0.0';
    public bool $hasCpSettings = true;

    /**
     * @inheritdoc
     */
    public static function config(): array
    {
        return [
            'components' => [
                'feedService' => FeedService::class,
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();

        $this->attachEventHandlers();

        // Register public URL rules and hook element saves once Craft is fully loaded
        Craft::$app->onInit(function() {
            $this->registerPublicRoutes();
            $this->registerCacheInvalidators();
            $this->registerSidebarValidation();
        });
    }

    /**
     * Helper property getter to fetch FeedService easily.
     */
    public function getFeedService(): FeedService
    {
        return $this->get('feedService');
    }

    /**
     * @inheritdoc
     */
    protected function createSettingsModel(): ?Model
    {
        return Craft::createObject(Settings::class);
    }

    /**
     * @inheritdoc
     */
    protected function settingsHtml(): ?string
    {
        $fields = Craft::$app->getFields()->getAllFields();
        $fieldOptions = [['value' => '', 'label' => '--- Select Field ---']];
        foreach ($fields as $field) {
            $fieldOptions[] = [
                'value' => $field->handle,
                'label' => ($field->name ?: $field->handle) . ' (' . $field->handle . ')',
            ];
        }

        return Craft::$app->view->renderTemplate('commerce-product-feed/_settings.twig', [
            'plugin' => $this,
            'settings' => $this->getSettings(),
            'stats' => $this->getFeedService()->getFeedStats(),
            'urls' => $this->getFeedService()->getFeedUrls(),
            'fieldOptions' => $fieldOptions,
        ]);
    }

    /**
     * Set up front-end routes mapping feeds URLs to controllers actions.
     */
    private function registerPublicRoutes(): void
    {
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_SITE_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules['feeds/openai.json'] = 'commerce-product-feed/feed/openai';
                $event->rules['feeds/google-feed.xml'] = 'commerce-product-feed/feed/google';
                $event->rules['feeds/ricardo.json'] = 'commerce-product-feed/feed/ricardo';
                $event->rules['feeds/product-feed.csv'] = 'commerce-product-feed/feed/csv';
                $event->rules['feeds/meta.xml'] = 'commerce-product-feed/feed/meta';
                $event->rules['feeds/pinterest.xml'] = 'commerce-product-feed/feed/pinterest';
                $event->rules['feeds/tiktok.xml'] = 'commerce-product-feed/feed/tiktok';
                $event->rules['feeds/products.json'] = 'commerce-product-feed/feed/products';
            }
        );
    }

    /**
     * Bind save/delete element events to invalidate cache in real-time.
     */
    private function registerCacheInvalidators(): void
    {
        $clearCache = function() {
            try {
                $this->getFeedService()->clearCache();
            } catch (\Throwable $e) {
                Craft::error('Error clearing feed cache: ' . $e->getMessage(), __METHOD__);
            }
        };

        Event::on(Product::class, Product::EVENT_AFTER_SAVE, $clearCache);
        Event::on(Product::class, Product::EVENT_AFTER_DELETE, $clearCache);
        Event::on(Variant::class, Variant::EVENT_AFTER_SAVE, $clearCache);
        Event::on(Variant::class, Variant::EVENT_AFTER_DELETE, $clearCache);
    }

    /**
     * Register internal hooks or actions.
     */
    private function attachEventHandlers(): void
    {
        // Place custom general action hooks here...
    }

    /**
     * Bind Product sidebar HTML compilation to inject compliance validation results.
     */
    private function registerSidebarValidation(): void
    {
        Event::on(
            Product::class,
            Product::EVENT_DEFINE_SIDEBAR_HTML,
            function(DefineHtmlEvent $event) {
                /** @var Product $product */
                $product = $event->sender;
                
                // Exclude new unsaved products as they do not have fields filled out yet
                if (!$product->id) {
                    return;
                }

                $html = $this->getFeedService()->getProductSidebarValidationHtml($product);
                if (!empty($html)) {
                    $event->html .= $html;
                }
            }
        );
    }
}
