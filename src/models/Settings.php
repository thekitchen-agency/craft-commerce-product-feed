<?php

namespace thekitchenagency\craftcommerceproductfeed\models;

use craft\base\Model;

/**
 * Commerce Product Feed settings model.
 */
class Settings extends Model
{
    /**
     * @var array List of enabled feed types (openai, google, ricardo, csv, meta, pinterest, tiktok)
     */
    public array $enabledFeeds = ['openai', 'google', 'ricardo', 'csv'];

    /**
     * @var string Feed generation/update frequency
     */
    public string $updateFrequency = 'hourly';

    /**
     * @var string Custom field handle for product Brand
     */
    public string $brandField = '';

    /**
     * @var string Custom field handle for product GTIN / EAN
     */
    public string $gtinField = '';

    /**
     * @var string Custom field handle for product MPN
     */
    public string $mpnField = '';

    /**
     * @var string Custom field handle (Lightswitch) to exclude product from feed
     */
    public string $excludeField = '';

    /**
     * @var string Custom field handle for product Description
     */
    public string $descriptionField = '';

    /**
     * @var string Custom field handle (Assets) for product Images
     */
    public string $imageField = '';

    /**
     * @var string Custom field handle (Categories) for product Categories
     */
    public string $categoryField = '';

    /**
     * @var string Default product condition ('new', 'used', 'refurbished')
     */
    public string $condition = 'new';

    /**
     * @var array Timestamps of when each feed type was last successfully updated
     */
    public array $lastUpdated = [];

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['enabledFeeds', 'lastUpdated'], 'safe'],
            [['updateFrequency', 'brandField', 'gtinField', 'mpnField', 'excludeField', 'descriptionField', 'imageField', 'categoryField', 'condition'], 'string'],
            [['updateFrequency', 'brandField', 'gtinField', 'mpnField', 'excludeField', 'descriptionField', 'imageField', 'categoryField', 'condition'], 'default', 'value' => ''],
            ['condition', 'in', 'range' => ['new', 'used', 'refurbished']],
            ['updateFrequency', 'in', 'range' => ['hourly', 'twicedaily', 'daily', 'manual']],
        ];
    }
}
